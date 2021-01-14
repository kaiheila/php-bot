<?php

namespace kaiheila\api\base;

/**
 *  本类只实现整个client的状态变化，跟实际的websocket连接无关。
 *  继承的类需要实现其abstract方法：
 *      reqGateWay     // 获取gateWay, 可以写死，也可以从http获取
 *      connectWebsocket  // 连接websocket
 *      sendData      // 发送数据给服务端
 *  如果希望代码重启后，自动恢复session连接，需要覆盖saveSessionId, getSessionId方法，并将其持久化保存
 *
 * */
abstract class StateSession extends Session
{
    /**                                                _________________
     *       获取gateWay     连接ws          收到hello |    心跳超时    |
     *             |           |                |      |      |         |
     *             v           v                v      |      V         |
     *      INIT  --> GATEWAY -->  WS_CONNECTED --> CONNECTED --> RETRY |
     *       ^        |   ^             |                  ^_______|    |
     *       |        |   |_____________|__________________________|    |
     *       |        |                 |                          |    |
     *       |________|_________________|__________________________|____|
     *
     **/
    // 初始状态
    const STATUS_INIT = 0;
    // 网关已获取
    const STATUS_GATEWAY = 10;
    // ws已经连接，等待hello包
    const STATUS_WS_CONNECTED = 20;
    // 已连接状态
    const STATUS_CONNECTED = 30;
    // resume
    const STATUS_RETRY = 40;

    public static $statusMap = [
       null => [
            self::STATUS_INIT,
            self::STATUS_GATEWAY,
        ],
        self::STATUS_INIT => [
            self::STATUS_GATEWAY,
        ],
        self::STATUS_GATEWAY => [
            self::STATUS_WS_CONNECTED,
            self::STATUS_INIT,
        ],
        self::STATUS_WS_CONNECTED => [
            self::STATUS_CONNECTED,
            self::STATUS_GATEWAY,
            self::STATUS_INIT,
        ],
        self::STATUS_CONNECTED => [
            self::STATUS_RETRY,
            self::STATUS_INIT,
        ],
        self::STATUS_RETRY => [
            self::STATUS_GATEWAY,
            self::STATUS_INIT,
        ],
    ];

    //整个的状态参数，每个状态都有一个异步函数在跑
    public $statusArr = [
        null => [
            'async' => '',
            'start' => 0,
            'max' => 60,
        ],
        self::STATUS_INIT => [
            'async' => 'getGateWay',
            'start' => 0,
            'max' => 60,
        ],
        self::STATUS_GATEWAY => [
            'async' => 'wsConnect',
            'start' => 1,
            'max' => 32,
            'retry' => 0,
            'maxRetry' => 2,
        ],
        self::STATUS_WS_CONNECTED => [
            'async' => 'helloFail',
            'start' => 6,
            'max' => 6,
        ],
        self::STATUS_CONNECTED => [
            'async' => 'sendHeartBeat',
            'start' => 30,
            'max' => 30,
        ],
        self::STATUS_RETRY => [
            'async' => 'sendHeartBeat',
            'start' => 4,
            'max' => 8,
            'retry' => 0,
            'maxRetry' => 2,
        ],
    ];

    protected $schedule = null;

    //当前的sessionId;
    protected $sessionId;
    protected $status;
    protected $gateWay;
    // 默认的timeout, 发了包最多等待ack的时间
    protected $timeout = 6;

    protected $recvQueue = [];
    protected $maxSn = 0;

    public function __construct($gateWay = null, $compress = 1)
    {
        parent::__construct($compress);
        $this->gateWay = $gateWay;
    }

    public function start()
    {
        if ($this->schedule) {
            $this->schedule->close();
        }
        $this->schedule = new Schedule();
        $this->schedule->start();
        if ($this->gateWay) {
            $this->setStatus(self::STATUS_GATEWAY, 0);
        } else {
            $this->setStatus(self::STATUS_INIT, 0);
        }
    }

    public function close()
    {
        $this->schedule->close();
    }

    public function getGateWay()
    {
        $this->log('state', 'getGateWay');
        $this->trigger('status_getGateWay');
        $gateway = $this->reqGateWay();
        if (!empty($gateway)) {
            $this->getGateWayOk($gateway);
        }
    }

    // 获取gateWay
    abstract public function reqGateWay();

    public function getGateWayOk($gateWay)
    {
        $this->log('getGateWayOk', ['gateWay' => $gateWay]);
        $this->gateWay = $gateWay;
        $this->setStatus(self::STATUS_GATEWAY, 0);
    }

    public function wsConnect($data)
    {
        $conf = $this->statusArr[$this->status];
        $retry = intval($conf['retry'] ?? 0);
        $maxRetry = intval($conf['maxRetry'] ?? 0);
        if ($retry >= $maxRetry) {
            return $this->wsConnectFail();
        }
        $this->log('state', 'Connecting websocket, Retry:'.($retry + 1));
        $this->trigger('status_wsConnect');
        $retry++;
        $this->statusArr[$this->status]['retry'] = $retry;
        //正常情况下，此处会阻塞住，不停地收发消息
        $this->connectWebsocket($this->gateWay);
    }

    abstract public function connectWebsocket($gateWay);

    // ws连接成功
    public function wsConnectOk()
    {
        $this->log('wsConnectOk', '');
        $this->setStatus(self::STATUS_WS_CONNECTED);
    }

    // ws连接失败
    public function wsConnectFail()
    {
        $this->log('wsConnectFail', '');
        $this->setStatus(self::STATUS_INIT);
    }

    // 在约定时间内没有收到hello包
    public function helloFail()
    {
        $this->log('helloFail', '');
        $this->setStatus(self::STATUS_GATEWAY);
    }

    public function receiveHello($frame)
    {
        $data = $frame->d;
        $code = $data['code'] ?? 40100;
        if ($code == 0) {
            $this->log('receiveHello', '');
            $this->saveSessionId($frame->d['sessionId'] ?? '');
            $this->setStatus(self::STATUS_CONNECTED);
        } else {
            $this->log('connectFailed', $code);
            // 这几种错误代表gateWay不太对，因此回退重连ws是不可能有结果的，所以直接回退至获取gateWay
            if (in_array($code, [40100, 40101, 40102, 40103])) {
                return $this->setStatus(self::STATUS_INIT, 6);
            }
            //其它错误暂时不管，让它6s超时再回退至重连ws
        }
    }

    public function canChange($from, $to)
    {
        if (isset(self::$statusMap[$from]) && in_array($to, self::$statusMap[$from])) {
            return true;
        }
        return false;
    }

    public function setStatus($status, $start = null)
    {
        if (!isset($this->statusArr[$status])) {
            throw new \Exception('不支持的状态');
        }
        // 判断是否能通过状态机
        if (!$this->canChange($this->status, $status)) {
            $this->log('statusFailed', $this->status."-> ${status}");
            return;
        }

        $toConf = $this->statusArr[$status];
        $fromConf = $this->statusArr[$this->status] ?? [];
        $fromStatus = $this->status;
        $this->log('statusChange', $this->status." -> ${status}");
        //清除所有的定时器
        $this->schedule->removeAll();
        $this->status = $status;
        if (!is_null($start)) {
            $toConf['start'] = $start;
        } else {
            // 代表回退
            if ($fromStatus > $status) {
                $retry = $fromConf['retry'] ?? 0;
                $toConf['start'] = pow(2, $retry + 1);
            }
        }
        if (isset($fromConf['retry'])) {
            $fromConf['retry'] = 0;
        }
        $async = $toConf['async'] ?? '';
        is_string($async) && $async = [$this, $async];
        $this->schedule->addExponentialJob($toConf['max'], $async, null, $toConf['start']);
        $this->statusArr[$fromStatus] = $fromConf;
        $this->statusArr[$status] = $toConf;
    }

    //收到消息
    public function processEvent($frame)
    {
        //仅在连接状态接收事件消息
        if ($this->status != self::STATUS_CONNECTED) {
            return;
        }
        $sn = $frame->sn;
        //先将消息放入接收队列
        $this->recvQueue[$sn] = $frame;
        //再按顺序从接收队列中读取
        while (true) {
            if (isset($this->recvQueue[$this->maxSn + 1])) {
                $this->maxSn++;
                $outFrame = $this->recvQueue[$this->maxSn];
                unset($this->recvQueue[$this->maxSn]);
                $this->processDataFrame($outFrame);
            } else {
                break;
            }
        }
    }

    public function processDataFrame($outFrame)
    {
        parent::receiveFrame($outFrame);
    }

    // 处理Frame
    public function receiveFrame($frame)
    {
        if ($frame->s == Event::SIG_EVENT) {
            $this->processEvent($frame);
        } elseif ($frame->s == Event::SIG_HELLO) {
            $this->receiveHello($frame);
        } elseif ($frame->s == Event::SIG_PONG) {
            $this->receivePong($frame);
        } elseif ($frame->s == Event::SIG_RESUME_ACK) {
            $this->resumeOk();
        } elseif ($frame->s == Event::SIG_RECONNECT) {
            $this->reconnect();
        }
    }

    private $hbTimers = [];

    public function sendHeartBeat()
    {
        $conf = $this->statusArr[$this->status];
        if (isset($conf['maxRetry']) && isset($conf['retry'])) {
            if ($conf['retry'] >= $conf['maxRetry']) {
                return;
            }
            $conf['retry']++;
            $this->log('heartBeatRetry', 'ws连接内尝试重连,Retry:'.$conf['retry']);
            $this->statusArr[$this->status] = $conf;
        }
        //发送ping
        $frame = Frame::getPingFrame($this->maxSn);
        $this->trigger('status_sendPing');
        $this->sendData($frame->__toString());
        $this->log('sendingPing', $frame);
        $this->hbTimers[] = $this->schedule->addExponentialJob($this->timeout, [$this, 'hbTimeout'], null, $this->timeout);
    }

    //收到pong, 尝试将状态维持为连接状态
    public function receivePong($frame)
    {
        $this->log('receivePong', $frame);
        foreach ($this->hbTimers as $timer) {
            $this->schedule->removeJob($timer);
        }
        if ($this->status != self::STATUS_CONNECTED) {
            $this->setStatus(self::STATUS_CONNECTED);
        }
    }

    public function hbTimeout()
    {
        $this->log('heartBeatTimeout', '');
        if ($this->status != self::STATUS_RETRY) {
            $this->setStatus(self::STATUS_RETRY);
            $this->hbTimers = [];
        } else {
            if (count($this->hbTimers) > 2) {
                $timer = array_shift($this->hbTimers);
                $this->schedule->removeJob($timer);
            } else {
                foreach ($this->hbTimers as $timer) {
                    $this->schedule->removeJob($timer);
                }
                $this->setStatus(self::STATUS_GATEWAY);
            }
        }
    }

    public function resumeOk()
    {
        $this->trigger('status_resumeOk');
        $this->log('resumeOk', '');
        if ($this->status != self::STATUS_CONNECTED) {
            $this->setStatus(self::STATUS_CONNECTED);
        }
    }

    public function reconnect()
    {
        $this->trigger('status_reconnect');
        $this->log('reconnect', '');
        $this->schedule->removeAll();
        $this->gateWay = '';
        $this->recvQueue = [];
        $this->maxSn = 0;
        $this->saveSessionId('');
        $this->setStatus(self::STATUS_INIT);
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    // 上层可以考虑覆盖此函数，进行持久化存储
    public function saveSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
    }
}

<?php

namespace kaiheila\api\base;

use kaiheila\api\helpers\ApiHelper;
use Swlib\SaberGM;

class WebsocketSession extends StateSession
{
    private $token;
    private $baseUrl;
    protected $wsClient;
    // 存储sessionId的文件
    protected $sessionFile;

    public function __construct($token, $baseUrl, $sessionFile = null, $gateWay = null, $compress = 1)
    {
        $this->token = $token;
        $this->baseUrl = $baseUrl;
        $this->sessionFile = $sessionFile;
        $sessionId = @file_get_contents($sessionFile);
        if (!empty($sessionId)) {
            $this->sessionId = trim($sessionId);
        }
        parent::__construct($gateWay, $compress);
    }

    public function saveSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
        if ($this->sessionFile) {
            @file_put_contents($this->sessionFile, $this->sessionId);
        }
    }

    public function reqGateWay()
    {
        $client = new ApiHelper('/api/v3/gateway/index', $this->token, $this->baseUrl);
        $ret = $client->setQuery([
            'compress' => $this->compress,
        ])->send();
        if ($ret['code'] == 0 && !empty($ret['data']['url'])) {
            return $ret['data']['url'];
        }
        return null;
    }

    public function sendData($data)
    {
        $this->wsClient->push($data);
    }

    public function connectWebsocket($gateWay)
    {
        if ($this->getSessionId()) {
            $gateWay = $gateWay.'&'.http_build_query([
                'sn' => $this->maxSn,
                'sessionId' => $this->getSessionId(),
                'resume' => 1,
            ]);
        }

        try {
            $this->wsClient = SaberGM::websocket($gateWay);
        } catch (\Exception $e) {
            $this->log('wsConnectError', $e->getMessage());
            return false;
        }
        $this->wsConnectOk();
        while (true) {
            if ($this->status < self::STATUS_WS_CONNECTED) {
                break;
            }
            try {
                $wframe = $this->wsClient->recv(1);
                if ($wframe && $wframe->opcode == 2) {
                    $this->receiveData($wframe->getData());
                }
            } catch (\Exception $e) {
                $this->wsClient->close();
                break;
            }
            \co::sleep(0.1);
        }
    }
}

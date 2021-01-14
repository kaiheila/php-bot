<?php

namespace kaiheila\api\base;

//全局事件总线
class Schedule extends BaseObject
{
    const EVENT_TIMER_TICK = 'event_timer_ticker';

    private $timer;
    private $step;

    /*格式
     *   [
     *      //指数退避
    *       "name" => [
    *           'type' => 'exponential',
    *           'start' => 1,
    *           'max' => 60
    *           'func' => function(){}
    *       ]
    *   ]
    */
    private $cronList;

    //step与精度有关，精度越高越消耗性能
    public function __construct($step = 1000)
    {
        $this->step = $step;
    }

    public function start()
    {
        $this->stop();
        $this->timer = \Swoole\Timer::tick($this->step, [$this, 'processTimer']);
    }

    public static function getNow()
    {
        return intval(microtime(true) * 1000);
    }

    // 处理timer回调
    public function processTimer()
    {
        $now = static::getNow();
        $this->trigger(self::EVENT_TIMER_TICK, [
            'time' => $now,
        ]);
        foreach ($this->cronList as $key => $cron) {
            if ($now < $cron['next']) {
                continue;
            }
            if (is_callable($cron['func'])) {
                go(function () use ($cron, $key) {
                    $now = static::getNow();
                    call_user_func($cron['func'], [
                        'now' => $now,
                        'name' => $key,
                        'job' => $cron,
                        'schedule' => $this,
                    ]);
                    $cron['retry'] = intval($cron['retry'] ?? 0);
                    $cron['retry']++;
                    $type = $cron['type'] ?? 'exponential';
                    if ($type == 'exponential') {
                        $limit = log($cron['max'] / $cron['start'], 2);
                        if ($cron['retry'] > $limit) {
                            $cron['next'] = $now + $cron['max'];
                        } else {
                            $cron['next'] = $now + pow(2, $cron['retry']) * $cron['start'];
                        }
                        $interval = $cron['next'] - $now;
                        //需要随机20%
                        $cron['next'] += intval($interval * mt_rand(-200, 200) / 1000);
                    } else {
                        $cron['next'] = PHP_INT_MAX;
                    }
                    if (isset($this->cronList[$key])) {
                        $this->cronList[$key] = $cron;
                    }
                });
            }
        }
    }

    public function addJob($job, $name = null)
    {
        empty($name) && $name = uniqid();
        $this->cronList[$name] = $job;
        return $name;
    }

    public function removeJob($name)
    {
        unset($this->cronList[$name]);
    }

    public function removeAll()
    {
        $this->cronList = [];
    }

    // 添加指数倒退job
    // $max 最大重试间隔的秒数
    // $start 最初的间隔，$start*pow(2, $retry) <= $max
    public function addExponentialJob($max, $func, $name = null, $start = 0)
    {
        if ($max <= 0) {
            throw new \Exception('max必须大于0');
        }
        // 比较小的值需要较长时间涨上来
        if ($start < 1) {
            $start = 0.5;
        }
        $now = static::getNow();
        return $this->addJob([
            'type' => 'exponential',
            'next' => $now + $start * 1000,
            'start' => $start * 1000,
            'max' => $max * 1000,
            'func' => $func,
        ], $name);
    }

    public function stop()
    {
        if ($this->timer) {
            Timer::clear($this->timer);
        }
    }

    public function close()
    {
        $this->removeAll();
        $this->stop();
    }
}

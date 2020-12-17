<?php

namespace kaiheila\api\base;

class Session extends BaseObject
{
    const EVENT_RECEIVE_FRAME = 'EVENT-GLOBAL:RECEIVE_FRAME';

    //channel_type 列表
    const CHANNEL_TYPE_CHALLENGE = 'WEBHOOK_CHALLENGE';
    const CHANNEL_TYPE_GROUP = "GROUP";

    // 事件列表
    const TYPE_SYSTEM = 255;
    const TYPE_TEXTMSG = 1;
    const TYPE_KMARKDOWN = 9;

    //当前是否压缩
    public $compress = 1;

    public function __construct($compress = 1)
    {
        $this->compress = $compress;
        parent::__construct();
    }

    //收到消息, 需要处理
    public function receiveData($data)
    {
        if ($this->compress) {
            $retData = @zlib_decode($data);
            if (empty($retData)) {
                $this->log('Zlib_decode Error', $data);
                throw new \Exception('Zlib decode Error');
            }
            $data = $retData;
        }
        $retData = json_decode($data, true);
        if (empty($retData)) {
            $this->log('Json_decode Error', $data);
            throw new \Exception('Json_decode Error');
        }

        // 数据预处理，websocket不需要，webhook可能需要
        $retData = $this->processData($retData);
        $frame = Frame::getFromData($retData);
        if ($frame) {
            return $this->receiveFrame($frame);
        } else {
            $this->log('数据不是合法的frame'. $data);
        }
    }

    // 数据预处理
    public function processData($data)
    {
        return $data;
    }

    public function receiveFrame($frame)
    {
        $this->trigger(self::EVENT_RECEIVE_FRAME, $frame);
        if ($frame->s == EVENT::SIG_EVENT) {
            $event_type = isset($frame->d['type']) ? $frame->d['type'] : '';
            $channel_type = isset($frame->d['channel_type']) ? $frame->d['channel_type'] : '';
            if ($event_type) {
                $this->trigger($channel_type.'_'.$event_type, $frame);
            }
        }
    }
}

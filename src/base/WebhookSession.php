<?php

namespace kaiheila\api\base;

use kaiheila\api\helpers\Security;

class WebhookSession extends Session
{
    // 加密key, 如果设置，会用该key进行解密
    public $encrypt_key;
    // 如果不设置，则不进行该校验
    public $verify_token;

    public function __construct($encrypt_key = '', $verify_token = '', $compress = 1)
    {
        $verify_token && $this->verify_token = $verify_token;
        $encrypt_key && $this->encrypt_key = $encrypt_key;
        parent::__construct($compress);
    }

    public function processData($data)
    {
        //如果有加密，则对数据进行解密
        if ($this->encrypt_key) {
            if (empty($data['encrypt'])) {
                $this->log('Encrypt_Data Not Exist', $data);
                throw new \Exception('Encrypt Data Not Exist');
            }
            $dataStr = Security::decryptData($data['encrypt'], $this->encrypt_key);
            $data = json_decode($dataStr, true);
            if (empty($data)) {
                $this->log('Encrypt_Data Error', $dataStr);
                throw new \Exception('Encrypt Data Error');
            }
        }
        return $data;
    }

    public function receiveFrame($frame)
    {
        if ($this->verify_token) {
            $verify_token = isset($frame->d['verify_token']) ? $frame->d['verify_token'] : '';
            if ($this->verify_token !== $verify_token) {
                $this->log('Verify_Token Error', $frame->d);
                return;
            }
        }

        $retData = '';
        //webhook下，需要对challenge事件做特殊处理，同步返回对应的challenage
        if ($frame->s == Event::SIG_EVENT && isset($frame->d['type']) && $frame->d['type'] === self::TYPE_SYSTEM && $frame->d['channel_type'] === self::CHANNEL_TYPE_CHALLENGE) {
            $challenge = isset($frame->d['challenge']) ? $frame->d['challenge'] : '';
            $retData = json_encode([
                'challenge' => $challenge,
            ]);
        }
        //默认情况下为异步处理,
        parent::receiveFrame($frame);
        return $retData;
    }

    public function sendData($data)
    {
        throw new \Exception('webhook不能主动发消息给服务端');
    }
}

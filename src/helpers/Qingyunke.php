<?php

namespace kaiheila\api\helpers;

// 参见 http://www.qingyunke.com/
class Qingyunke extends BaseHttpClient
{
    public $baseUrl = 'http://api.qingyunke.com';
    public $key = 'free';
    public $appid = 0;

    public function __construct($path = '/api.php', $key = null, $appid = null)
    {
        parent::__construct($this->baseUrl.$path);
        !empty($key) && $this->key = $key;
        !empty($appid) && $this->appid = $appid;
    }

    public function getReply($msg)
    {
        $res = $this->setQuery([
            'key' => $this->key,
            'msg' => $msg,
            'appid' => $this->appid,
        ])->send();
        $data = json_decode($res, true);
        return $data;
    }
}

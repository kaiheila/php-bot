<?php

namespace kaiheila\api\helpers;

class ApiHelper extends BaseHttpClient
{
    public $token = '';
    //可以为 Bot 或者Bearer
    public $type = 'Bot';
    public $language = 'zh-CN';
    public $baseUrl = 'https://www.kaiheila.cn';

    public function __construct($path, $token, $baseUrl = null, $type = 'Bot', $language = 'zh-CN')
    {
        !empty($baseUrl) && $this->baseUrl = $baseUrl;
        $this->token = $token;
        $this->type = $type;
        parent::__construct($this->baseUrl.$path);
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setLanguage($language)
    {
        $this->language = $language;
    }

    public function send($method = self::GET)
    {
        $this->setHeaders([
            'Authorization' => "{$this->type} {$this->token}",
        ]);
        $this->setHeaders(['Accept-Language' => $this->language]);
        $result = parent::send($method);
        $data = json_decode($result, true);
        if (empty($data)) {
            return [
                'code' => 1,
                'msg' => '未知错误',
                'data' => $result,
            ];
        }
        return $data;
    }
}

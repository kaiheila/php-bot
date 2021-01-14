<?php

namespace kaiheila\api\base;

interface SessionInterface
{
    //接收数据
    public function receiveData($data);

    public function sendData($data);

    //注册
    public function on($message, $callback);
}

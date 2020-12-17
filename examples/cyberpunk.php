<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__.'/config.php';

use kaiheila\api\base\WebhookSession;

$http = new Swoole\Http\Server(HTTP_SERVER_IP, HTTP_SERVER_PORT);
$session = new WebhookSession(ENCRYPTKEY, VERIFYTOKEN);
// 第一个参数是机器人的id，如果输入了，仅仅在@机器人时，才会一定回话
$cyberpunk = new \kaiheila\api\helpers\CyberPunk('', __DIR__.'/keywords.json');
//侦听所有的频道事件
$session->on('GROUP*', [$cyberpunk, 'processMsg']);

//处理消息
$http->on('request', function ($request, $response) use ($session) {
    //处理消息
    $getArr = $request->get;
    $response->header('Content-Type', 'application/json');
    try {
        $result = $session->receiveData($request->getContent());
        $response->end($result);
    } catch (\Exception $e) {
        $response->status(500, 500);
        $response->end($e->getMessage());
    }
});

$http->start();

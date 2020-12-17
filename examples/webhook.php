<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__.'/config.php';

use kaiheila\api\base\Session;
use kaiheila\api\base\WebhookSession;
use kaiheila\api\helpers\ApiHelper;

$http = new Swoole\Http\Server(HTTP_SERVER_IP, HTTP_SERVER_PORT);
$session = new WebhookSession(ENCRYPTKEY, VERIFYTOKEN);
// 侦听所有的接收frame事件
$session->on(Session::EVENT_RECEIVE_FRAME, function ($frame) {
    var_dump($frame);
    echo "收到frame\n";
});
//侦听所有的频道事件
$session->on('GROUP*', function($frame){
    var_dump($frame);
    echo "收到频道消息";
});
//只侦听频道内的文字消息，并回复
$session->on('GROUP_1', function($frame){
    var_dump($frame);
    echo "收到文字消息";
    $client = new ApiHelper('/api/v3/channel/message', TOKEN, BASE_URL);
    $ret = $client->setBody([
        'channel_id' => $frame->d['target_id'],
        'content' => "恭喜你完成整个的对接",
        'object_name' => 1,
    ])->send(ApiHelper::POST);
});

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

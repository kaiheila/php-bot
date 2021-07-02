# Bot
本代码是[kaiheila](http://developer.kaiheila.cn/doc)机器人的php示例sdk， 用户可以直接使用该代码或者参照该代码来构建自己的机器人。

 本代码既可以当成一个composer包，来供系统调用。也可以当成一个独立的机器人来运行。在本代码中有一个重要的概念叫session。当websocket/webhook在线时，我们认为它和服务器保持了一个session,然后我们可以通过session来处理数据了。

## 代码说明
该包依赖了swoole，请确定你安装了[swoole扩展](https://wiki.swoole.com/#/environment?id=%e5%ae%89%e8%a3%85swoole)。

### docker 
如果你觉得安装php相关环境比较麻烦，我们也提供了docker来方便你的使用[php-bot](https://hub.docker.com/r/kaiheila/php-bot):

```bash
docker pull kaiheila/php-bot
```

### 代码使用

```php 
//$session = new WebsocketSession();
$session = new WebhookSession();

// 注册接收frame事件回调，当session收到了正确的frame数据时，就会调用此方法
$session->on($session::EVENT_RECEIVE_FRAME, function($frame){
    
});

// 事件名默认为 channel_type + _ + type组成， 如下代表侦听群聊的文字消息
$session->on("GROUP_1", function($frame){
    
});

// 事件名支持通配符匹配，如下代表侦听群聊的所有消息
$session->on("GROUP*", function($frame){
     
});

// 事件默认是采用了swoole的协程进行异步回调，如果需要同步回调，如下所示
$session->on("GROUP*", function(){}, ['async' => false]);

// 通过webhook/websocket收到消息后，把数据传给session处理即可，session就会自动按上面注册的事件进行处理。
$session->receiveData($data);

```

在回调中，我们通常会跟据服务端返回的消息，来做一些动作，我们统一封装了ApiClient:
```
$client = new \kaiheila\api\helpers\ApiHelper($path);
// post json示例
$client->setBody(["foo" => "bar"])->send(\kaiheila\api\helpers\ApiHelper::POST);
// get示例
$client->setQuery(["foo" => "bar"])->send(\kaiheila\api\helpers\ApiHelper::GET);

```

## kaiheila/api 作为composer集成至其它服务内

```
composer require  kaiheila/api dev-master
````
参数上文或example, 直接使用`session->receiveData($data)`来处理数据即可。

**注意：** 在php-fpm中，正常是不支持async-io的，可能会报`async-io must be used in PHP CLI mode`的错误，因此在php-fpm模式下时，需要同步处理事件，示例如下：
```php
$session->on($eventName, function(){},  ['async' => false]);
```


## 独立机器人

本代码也可以作为一个独立的机器人来运行。

1. git clone git@github.com:kaiheila/php-bot.git
2. 进入代码目录`cd php-bot`，运行`composer update`
3. 打开[开发者中心](https://developer.kaiheila.cn/bot), 创建机器人，并更改为webhook/websocket模式。
4. 更改配置，将开发者中心的配置填入config.php文件中。

```bash
cp examples/config.php.sample examples/config.php
# 按照参数说明，修改config.php的配置, vim examples/config.php
# 运行webhook机器人
php examples/webhook.php
# 运行websocket机器人
````
4. 在开发者后台，把机器人的地址填入后台。

在做好上述配置后，你也可以试试我们做的一个小机器人【强尼机器人】, 注意它是webhook模式，需要配置ip及端口。
```bash
php examples/cyberpunk.php
````



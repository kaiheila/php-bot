<?php

namespace kaiheila\api\base;

class Event
{
    // 事件
    const SIG_EVENT = 0;
    // hello
    const SIG_HELLO = 1;
    // 心跳ping
    const SIG_PING = 2;
    // 心跳回应pong
    const SIG_PONG = 3;
    // RESUME消息
    const SIG_RESUME = 4;
    // RECONNECT
    const SIG_RECONNECT = 5;
    // RESUME ACK
    const SIG_RESUME_ACK = 6;
}

<?php

namespace kaiheila\api\base;

class Frame
{
    // 数据, mixed
    public $d;
    // 信号, int
    public $s;
    // 序列号, int
    public $sn;

    public static function getFromData($data)
    {
        if (!isset($data['s']) || !isset($data['d'])) {
            return null;
        }
        $frame = new self();
        $frame->d = $data['d'];
        $frame->s = intval($data['s']);
        $frame->sn = $data['sn'] ?? 0;
        return $frame;
    }
}

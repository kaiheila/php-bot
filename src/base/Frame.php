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
        if (!isset($data['s'])) {
            return null;
        }
        $frame = new self();
        $frame->d = $data['d'] ?? [];
        $frame->s = intval($data['s']);
        $frame->sn = $data['sn'] ?? 0;
        return $frame;
    }

    public static function getPingFrame($sn)
    {
        $frame = new self();
        $frame->s = Event::SIG_PING;
        $frame->d = [];
        $frame->sn = $sn;
        return $frame;
    }

    public function __toString()
    {
        return json_encode([
            'd' => $this->d,
            's' => $this->s,
            'sn' => $this->sn,
        ]);
    }
}

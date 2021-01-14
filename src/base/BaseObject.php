<?php

namespace kaiheila\api\base;

use kaiheila\api\helpers\StringHelper;

// 主要处理事件添加，侦听机制。支持匹配符
class BaseObject
{
    private $_events = [];
    private $_eventWildcards = [];
    public $logFile = '';

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
    }

    public function on($name, $handler, $data = null, $append = true)
    {
        if (strpos($name, '*') !== false) {
            if ($append || empty($this->_eventWildcards[$name])) {
                $this->_eventWildcards[$name][] = [$handler, $data];
            } else {
                array_unshift($this->_eventWildcards[$name], [$handler, $data]);
            }
            return;
        }

        if ($append || empty($this->_events[$name])) {
            $this->_events[$name][] = [$handler, $data];
        } else {
            array_unshift($this->_events[$name], [$handler, $data]);
        }
    }

    public function off($name, $handler = null)
    {
        if (empty($this->_events[$name]) && empty($this->_eventWildcards[$name])) {
            return false;
        }
        if ($handler === null) {
            unset($this->_events[$name], $this->_eventWildcards[$name]);
            return true;
        }

        $removed = false;
        // plain event names
        if (isset($this->_events[$name])) {
            foreach ($this->_events[$name] as $i => $event) {
                if ($event[0] === $handler) {
                    unset($this->_events[$name][$i]);
                    $removed = true;
                }
            }
            if ($removed) {
                $this->_events[$name] = array_values($this->_events[$name]);
                return $removed;
            }
        }

        // wildcard event names
        if (isset($this->_eventWildcards[$name])) {
            foreach ($this->_eventWildcards[$name] as $i => $event) {
                if ($event[0] === $handler) {
                    unset($this->_eventWildcards[$name][$i]);
                    $removed = true;
                }
            }
            if ($removed) {
                $this->_eventWildcards[$name] = array_values($this->_eventWildcards[$name]);
                // remove empty wildcards to save future redundant regex checks:
                if (empty($this->_eventWildcards[$name])) {
                    unset($this->_eventWildcards[$name]);
                }
            }
        }
        return $removed;
    }

    // 通知接收到某个事件,此时会调用用户的回调
    public function trigger($name, $data = null)
    {
        $eventHandlers = [];
        foreach ($this->_eventWildcards as $wildcard => $handlers) {
            if (StringHelper::matchWildcard($wildcard, $name)) {
                $eventHandlers = array_merge($eventHandlers, $handlers);
            }
        }

        if (!empty($this->_events[$name])) {
            $eventHandlers = array_merge($eventHandlers, $this->_events[$name]);
        }

        if (!empty($eventHandlers)) {
            foreach ($eventHandlers as $handler) {
                $userData = $handler[1];
                $async = true;
                if ($userData && is_array($userData) && isset($userData['async'])) {
                    $async = $userData['async'];
                }
                if ($async) {
                    go(function () use ($handler, $data) {
                        call_user_func($handler[0], $data);
                    });
                } else {
                    call_user_func($handler[0], $data);
                }
            }
        }
    }

    public function log($info, $data = [])
    {
        $datetime = date('Y-m-d H:i:s');
        if (!is_string($data)) {
            $data = json_encode($data);
        }
        $str = "[$datetime] ${info} ".$data."\n";
        if ($this->logFile) {
            //为了性能应该批量写入，用户可以自行覆写然后优化
            if (!$fp = fopen($logFile, 'a')) {
                echo "打开文件{$logFile}失败！\n";
            }
            fwrite($fp, $str);
            fclose($fp);
        } else {
            echo $str;
        }
    }
}

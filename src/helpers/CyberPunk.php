<?php

namespace kaiheila\api\helpers;

class CyberPunk
{
    public $keywordsFile;
    public $id;

    private $jsonData;

    public function __construct($id, $keywordsFile)
    {
        $this->id = $id;
        $this->keywordsFile = $keywordsFile;
    }

    public function getJsonData()
    {
        if ($this->jsonData) {
            return $this->jsonData;
        }
        $str = file_get_contents($this->keywordsFile);
        $this->jsonData = json_decode($str, true);
        return $this->jsonData;
    }

    // 获取消息对应的语料库
    public function getMsgLib($msg, $keywords)
    {
        $arr = [];
        foreach ($keywords as $tempArr) {
            $flag = false;
            foreach ($tempArr['words'] as $word) {
                $i = stripos($msg, $word);
                if ($i !== false) {
                    $flag = true;
                    break;
                }
            }
            if ($flag) {
                $arr = array_merge($arr, $tempArr['list']);
            }
        }
        return $arr;
    }

    public function getIntimacy($frame)
    {
        $client = new ApiHelper('/api/v3/intimacy/index', TOKEN, BASE_URL);
        $result = $client->setQuery(['user_id' => $frame->d['author_id']])->send();
        if ($result['code'] !== 0) {
            echo '请求失败';
            throw new \Exception('请求失败');
        }
        return $result['data'];
    }

    public function setIntimacy($data)
    {
        $client = new ApiHelper('/api/v3/intimacy/update', TOKEN, BASE_URL);
        $result = $client->setBody($data)->send(ApiHelper::POST);
        if ($result['code'] !== 0) {
            throw new \Exception('请求失败');
        }
        return $result['data'];
    }

    public function processMsg($frame)
    {
        $data = $this->getJsonData();
        $filesSpeechs = $data['filesSpeechs'] ?? [];
        $keywords = $data['keywords'] ?? [];
        $randSpeechs = $data['randSpeechs'] ?? [];

        $msg = $frame->d['content'] ?? '';
        $type = $frame->d['type'] ?? 1;
        $target_id = $frame->d['target_id'];
        //是否为@机器人的信息
        $isAt = false;
        $mention = $frame->d['extra']['mention'] ?? [];
        if (in_array($this->id, $mention)) {
            $isAt = true;
        }
        //如果没有填id,则当做所有的都是at消息
        empty($this->id) && $isAt = true;
        $isFile = in_array($type, [2, 3, 4, 8]);

        $returnMsg = '';

        if ($isFile) {
            //文件有10%的概率触发
            if (mt_rand(0, 100) < 10) {
                $returnMsg = $filesSpeechs[mt_rand(0, count($filesSpeechs) - 1)];
            }
        } elseif ($isAt) {
            $lib = $this->getMsgLib($msg, $keywords);
            // 代表有触发词
            if (!empty($lib)) {
                $returnMsg = $lib[mt_rand(0, count($lib) - 1)];
            } else {
                //70%的机率从接口拿
                if (mt_rand(0, 100) < 70) {
                    $client = new Qingyunke();
                    $retData = $client->getReply($msg);
                    if (isset($retData['result']) && $retData['result'] == 0) {
                        $returnMsg = $retData['content'];
                    }
                }
                if (empty($returnMsg)) {
                    $returnMsg = $randSpeechs[mt_rand(0, count($randSpeechs) - 1)];
                }
            }
        } else {
            $lib = $this->getMsgLib($msg, $keywords);
            // 30%的机率触发
            if (!empty($lib) && mt_rand(0, 100) < 30) {
                $returnMsg = $lib[mt_rand(0, count($lib) - 1)];
            }
        }

        if (empty($returnMsg)) {
            return;
        }
        //在此处做自己的消息处理
        $client = new ApiHelper('/api/v3/channel/message', TOKEN, BASE_URL);
        $ret = $client->setBody([
            'channel_id' => $target_id,
            'content' => $returnMsg,
            'object_name' => 1,
        ])->send(ApiHelper::POST);
    }
}

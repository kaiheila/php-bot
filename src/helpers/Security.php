<?php

namespace kaiheila\api\helpers;

class Security
{
    // decryptData
    public static function decryptData($eData, $encryptKey)
    {
        $eData = base64_decode($eData);
        $iv = substr($eData, 0, 16);
        return openssl_decrypt(substr($eData, 16), 'aes-256-cbc', $encryptKey, 0, $iv);
    }
}

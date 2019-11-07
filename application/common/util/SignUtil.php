<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/25
 * Time: 14:10
 */

namespace app\common\util;


class SignUtil
{
    private $key = 'w3tb';

    public function stringSign($str)
    {
        $strKey = $str . " " . "key=" .$this->key;

        return strtoupper(md5($strKey));
    }

    public function signIsOK($signStr, $str)
    {
        if($signStr == null || $str == null || $this->stringSign($str) != $signStr){
            return false;
        }
        return true;
    }
}
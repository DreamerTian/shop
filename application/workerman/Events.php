<?php
/**
 * User: Tegic
 * Date: 2018/6/13
 * Time: 09:47
 */

namespace app\workerman;

use GatewayWorker\Lib\Gateway;

class Events
{

    public static function onWorkerStart($businessWorker)
    {
        file_put_contents(__DIR__ .'/workermandebug.txt', "时间:".date('Y-m-d H:i:s')."-----------连接成功".PHP_EOL, FILE_APPEND);
    }

    public static function onConnect($client_id)
    {
        file_put_contents(__DIR__ .'/workermandebug.txt', "时间:".date('Y-m-d H:i:s')."-----------进入程序2".PHP_EOL, FILE_APPEND);
        file_put_contents(__DIR__ .'/workermandebug.txt', "时间:".date('Y-m-d H:i:s')."-----------".$client_id." ".PHP_EOL, FILE_APPEND);
    }

    public static function onWebSocketConnect($client_id, $data)
    {
    }

    public static function onMessage($client_id, $message)
    {
        file_put_contents(__DIR__ .'/workermandebug.txt', "时间:".date('Y-m-d H:i:s')."-----------接收报文-------".$message." ".PHP_EOL, FILE_APPEND);

        $GoldNet = new Gold();

        $result_message = $GoldNet->transfer($message);

        file_put_contents(__DIR__ .'/workermandebug.txt', "时间:".date('Y-m-d H:i:s')."-----------响应报文-------".$result_message." ".PHP_EOL, FILE_APPEND);

        return GateWay::sendToAll($result_message);
    }

    public static function onClose($client_id)
    {
        file_put_contents(__DIR__ .'/workermandebug.txt', "时间:".date('Y-m-d H:i:s')."-----------关闭程序".PHP_EOL, FILE_APPEND);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/7
 * Time: 13:47
 */

namespace app\index\controller;

use app\common\util\ClientSocket;
use app\common\util\Ftp;
use app\common\util\MessageHead;
use Common\Util\File;
use think\Db;

class Gold
{

    /**
     *  中转函数
     * */
    public function transfer($data_str)
    {
        file_put_contents(__DIR__ .'/gold_debug.txt', "时间:".date('Y-m-d H:i:s')."-----------接收报文-------".$data_str." ".PHP_EOL, FILE_APPEND);

        $mh = new MessageHead();

        if(empty($data_str)){
            $mh->RspCode = "111111";
            $mh->RspMsg  = "接收到的参数为空";
            return $mh->setPkgHead('1318','02','1','0');
        }

        //不为空处理数据
        $mh->split_head($data_str);

        $body_str = substr($data_str,118);

        $body_arr = explode('&',$body_str);

        if($mh->ServType == "01" && $mh->RspCode == "999999"){

            switch ($mh->TranFunc){
                case "1318":
                    return $this->in_gold($body_arr,$mh->ThirdLogNob);
                    break;
                case "1006":
                    return $this->reconciliation($body_arr,$mh->ThirdLogNob);
                    break;
                case "1014":
                    return $this->details($body_arr);
                    break;
                case "1325":
                    return $this->account_info($body_arr);
                    break;
            }
        }

    }


    /**
     *  入金处理
     * */
    public function in_gold($data,$ThirdLogNob)
    {
        $mh = new MessageHead();

        $user_id = $data[5];

        $money = $data[15];

        //查询用户信息
        $user_info = Db::name('users')->where('user_id',$user_id)->find();

        if(!$user_info){
            $mh->RspCode = "222222";
            $mh->RspMsg  = "用户不存在";
            return $mh->setPkgHead('1318','02','1','0');
        }

        Db::startTrans();

        $res1 = Db::name('users')->where('user_id',$user_id)->setInc('user_money',$money);

        $order_id = date('YmdHis').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 6);

        $res2 = Db::name('cash_access')->insert([
            'user_id'   => $user_id,
            'systemid'  => $user_info['systemid'],
            'name'      => $user_info['name'],
            'type'      => 2,
            'money'     => $money,
            'ccycode'   => "RMB",
            'serial_num'   => $order_id,
            'remark'   => $data[17],
            'create_time' => time(),
            'exc_serial_num' => $ThirdLogNob,
        ]);

        if(!$res1 || !$res2){
            Db::rollback();
            $mh->RspCode = "333333";
            $mh->RspMsg  = "转入失败";
            return $mh->setPkgHead('1318','02','1','1');
        }

        Db::commit();

        $body_data[] = $order_id;
        $body_data[] = "0";
        $body_data[] = " ";
        $body_data[] = "转入成功";

        $body_str = implode('&',$body_data);

        $mh->RspCode = "000000";
        $head_str = $mh->setPkgHead('1318','02',strlen($body_str).'',$order_id);

        $content = $head_str.$body_str;

        return $content;
    }


    /**
     *  对账
     * */
    public function reconciliation($data, $ThirdLogNob)
    {
        $mh = new MessageHead();

        if(empty($data)){
            $mh->RspCode = "222222";
            $mh->RspMsg  = "请求参数不能为空";
            return $mh->setPkgHead('1006','02','1','0');
        }

        $start_time = strtotime(date("Y-m-d",strtotime("-1 day")));   //昨天开始时间
        $end_time = $start_time+24 * 60 * 60-1;  //昨天结束时间


        if(strlen($data[2]) == 8){
            $BeginDate = strtotime($data[2]."000001");
        }elseif (strlen($data[2]) == 14){
            $BeginDate = strtotime($data[2]);
        }else{
            $BeginDate = $start_time;
        }

        if(strlen($data[3]) == 8){
            $EndDate = strtotime($data[3]."000001");
        }elseif (strlen($data[3]) == 14){
            $EndDate = strtotime($data[3]);
        }else{
            $EndDate = $end_time;
        }

        $info = Db::name('cash_access')->where('create_time','between time',[$BeginDate,$EndDate])->select();

        //总笔数
        $count = count($info);
        //出金总金额
        $out = Db::name('cash_access')->where('create_time','between time',[$BeginDate,$EndDate])
            ->where('type',2)
            ->sum('money');
        $in = Db::name('cash_access')->where('create_time','between time',[$BeginDate,$EndDate])
            ->where('type',1)
            ->sum('money');


        $filepath = "/data/wwwroot/www.jzwhsc.com/public/upload/cash_access/";
        $filename = "CRJ".$ThirdLogNob."2222".date('YmdHis').".txt";

        $content = "{$count}&{$out}&{$in}&0\r\n";

        foreach ($info as $key=>$val){
            $gold_type = $val['type'] == 1 ? 2 : 1;
            $fkr = $val['type'] == 1 ? $val['user_id'] : $val['systemid'];
            $skr = $val['type'] == 2 ? $val['user_id'] : $val['systemid'];
            $date_str = date("Ymd",$val['create_time']);
            $time_str = date("His",$val['create_time']);

            $content .= "{$val['id']}&{$gold_type}& &{$fkr}&{$skr}&{$val['systemid']}&{$val['user_id']}&{$val['name']}&{$val['money']}&0&{$val['user_id']}&{$val['name']}&{$date_str}&{$time_str}&{$val['serial_num']}&\r\n";
        }
        //创建文件
        file_put_contents($filepath.$filename,$content);
        if(!$this->upload_ftp($filepath,$filename)){
            //上传失败
            $mh->RspCode = "ERR100";
            $mh->RspMsg  = "ftp存取文件失败";
            return $mh->setPkgHead('1006','02','1','0');
        }

        //上传成功后发送通知

        $body_data[] = $filename;
        $body_data[] = " ";
        $body_str = implode('&',$body_data);

        $mh->RspCode = "000000";
        $mh->RspMsg  = "上传文件成功";
        $head_str = $mh->setPkgHead('1006','02',strlen($body_str).'',$ThirdLogNob);

        $content = $head_str.$body_str;

        return $content;
    }

    public function upload_ftp($filepath,$filename)
    {
        $ftp_config = [
            'host'  => '116.62.210.102',
            'user'  => 'ftp_sxhl_scbank',
            'pass'  => 'ftp_sxhl_scbank',
        ];

        $ftp = new Ftp($ftp_config);

        $conn_result = $ftp->connect();
        if ( ! $conn_result){
            $error_msg = $ftp->get_error_msg();
            file_put_contents(__DIR__ .'/gold_debug.txt', "时间:".date('Y-m-d H:i:s')."1005-----------FTP连接失败-------".$error_msg." ".PHP_EOL, FILE_APPEND);
            return false;
        }
        $local_file = $filepath.$filename;
        $remote_file = "/".$filename;
        //上传文件
        if (!$ftp->upload($local_file,$remote_file)) {
            //没有上传成功
            file_put_contents(__DIR__ .'/gold_debug.txt', "时间:".date('Y-m-d H:i:s')."1005-----------FTP上传失败-------".PHP_EOL, FILE_APPEND);
            return false;
        }
        //上传成功
        $body_data[] = "3";
        $body_data[] = " ";
        $body_data[] = $filename;
        $body_data[] = " ";

        $body_str = implode('&',$body_data);


        $mh = new MessageHead();

        $ThirdLogNob = date('YmdHis').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 6);

        $head_str = $mh->setPkgHead('1005','01',strlen($body_str).'',$ThirdLogNob);

        $ip = "116.62.210.102";

        $port = "17820";

        $content = $head_str.$body_str;

        $socket = new ClientSocket($ip, $port);

        $result = $socket->wr($content);
        //收到回复的信息 保存转码 保存到变量中
        //var_dump(iconv('GB2312','UTF-8',$result));
        //$respond = iconv('GB2312','UTF-8',$result);

        $socket->__destruct();

        //处理信息
        $mh->split_head($result);
        //如果不是6个零 那么就是失败
        if($mh->RspCode != "000000"){
            file_put_contents(__DIR__ .'/gold_debug.txt', "时间:".date('Y-m-d H:i:s')."1005-----------相应报文判断失败-------".PHP_EOL, FILE_APPEND);
            return false;
        }

        return true;

    }


    /**
     *  订单详情
     * */
    public function details($data)
    {
        $mh = new MessageHead();

        $serial_num = $data[0];

        //查询出入金表

        $cash_info = Db::name("cash_access")->where('serial_num',$serial_num)->find();

        if(!$cash_info){
            $mh->RspCode = "222222";
            $mh->RspMsg  = "该交易不存在";
            return $mh->setPkgHead('1014','02','1','1');
        }

        $body_data[] = $cash_info['type'] == 1 ? "2" :"1";
        $body_data[] = "0";
        $body_data[] = $cash_info['money'];
        $body_data[] = "0";
        $body_data[] = "0";
        $body_data[] = $cash_info['type'] == 1 ? $cash_info['systemid'] : $cash_info['user_id'];
        $body_data[] = $cash_info['name'];
        $body_data[] = "0";
        $body_data[] = $cash_info['type'] == 1 ? $cash_info['user_id'] : $cash_info['systemid'];
        $body_data[] = $cash_info['name'];
        $body_data[] = "0";
        $body_data[] = "RMB";
        $body_data[] = date('Ymd',$cash_info['create_time']);
        $body_data[] = $cash_info['remark']." ";

        $body_str = implode('&',$body_data);

        $mh->RspCode = "000000";
        $head_str = $mh->setPkgHead('1318','02',strlen($body_str).'',$serial_num);

        $content = $head_str.$body_str;

        return $content;

    }


    /**
     *  查询时间段流水信息
     * */
    public function account_info($data)
    {
        $mh = new MessageHead();

        if(empty($data)){
            $mh->RspCode = "222222";
            $mh->RspMsg  = "请求参数不能为空";
            return $mh->setPkgHead('1325','02','1','1');
        }

        if(strlen($data[2]) == 8){
            $BeginDate = strtotime($data[2]."000001");
        }elseif (strlen($data[2]) == 14){
            $BeginDate = strtotime($data[2]);
        }

        if(strlen($data[3]) == 8){
            $EndDate = strtotime($data[3]."000001");
        }elseif (strlen($data[3]) == 14){
            $EndDate = strtotime($data[3]);
        }

        $PageNum = $data[4];

    }
}
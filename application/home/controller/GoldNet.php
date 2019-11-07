<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/2
 * Time: 11:51
 */

namespace app\home\controller;



use app\common\util\ClientSocket;
use app\common\util\Ftp;
use app\common\util\MessageHead;
use Common\Util\File;
use think\Db;


class GoldNet extends Base
{

    //出金
    public function out_gold()
    {
        $user_id = I('user_id'); //用户ID
        $remark = I('remark'); //备注
        $money = I('money');    //钱
        $paypwd = I('paypwd'); //交易密码

        //先判断此人是否签约
        $user_info = Db::name('users')->where('user_id',$user_id)->find();

        if(!$user_info){
             $this->ajaxReturn(['status'=> -1 ,'msg' => "用户不存在"]);
        }
        if(empty($user_info['systemid']) || $user_info['systemid']==0 || empty($user_info['name'])){
            $this->ajaxReturn(['status'=>-5, 'msg'=>'请先完善并同步用户信息']);
        }

        if(encrypt($paypwd) != $user_info['paypwd']){
            $this->ajaxReturn(['status'=>-2, 'msg'=>'支付密码错误']);
        }
        if ($money > $user_info['user_money']) {
            $this->ajaxReturn(['status'=>-3, 'msg'=>"本次提现余额不足"]);
        }
        if ($money <= 0) {
            $this->ajaxReturn(['status'=>-4, 'msg'=>'提现额度必须大于0']);
        }

        $data[] = "";
        $data[] = $user_id;
        $data[] = $money;
        $data[] = $user_info['systemid'];
        $data[] = $user_info['name'];
        $data[] = "RMB";
        $data[] = date('Ymd');
        $data[] = $remark." ";

        $body_str = implode('&',$data);

        $mh = new MessageHead();

        $ThirdLogNob = date('YmdHis').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 6);

        $head_str = $mh->setPkgHead('1310','01',strlen($body_str).'',$ThirdLogNob);

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
            $this->ajaxReturn(['status'=>-6, 'msg'=>'转出失败！请联系网管!错误信息：'.$mh->RspMsg]);
        }
        //扣除用户账户中的钱 然后写入出入金记录中
        Db::startTrans();

        $res1 = Db::name('users')->where('user_id',$user_id)->setDec('user_money',$money);

        $res2 = Db::name('cash_access')->insert([
            'user_id'   => $user_id,
            'systemid'  => $user_info['systemid'],
            'name'      => $user_info['name'],
            'type'      => 1,
            'money'     => $money,
            'ccycode'   => "RMB",
            'serial_num'   => $ThirdLogNob,
            'remark'   => $remark,
            'create_time' => time(),
            'exc_serial_num' => empty($mh->ThirdLogNob) ? '' : $mh->ThirdLogNob,
        ]);

        if(!$res1 || !$res2){
            Db::rollback();
            $this->ajaxReturn(['status'=>-6, 'msg'=>'转出入库失败！请联系网管']);
        }

        Db::commit();
        $this->ajaxReturn(['status'=>1, 'msg'=>'转出成功！',['url'=>U('Home/User/recharge',['type'=>3])]]);


    }

    public function test()
    {

//        $body_str = "&47&10&0101000000000026&aaaaaa&RMB&20190107&aaa";
//        $str = "131001CMacmACmAcmaCmac20190107141846999999                                       aaa1  ".strlen($body_str)."NO001201901071418465497562222".$body_str;

//        $str = "100601CMacmACmAcmaCmac20190108164439999999                                          100761    007885478483853336  33011&99999999&20190108000001&20190108235959&查询20190108到20190108的出入金流水& ";
        $str = "100601CMacmACmAcmaCmac20190110132109999999                                          100761    0795691150752955    33011&99999999&20190110000001&20190110235959&查询20190110到20190110的出入金流水&";
//
        $ip = "www.jzwhsc.com";
        //$ip = "116.62.210.102";

        $port = "8282";
        //$port = "17820";

//        /*$head = substr($str,0,118);
//
//        var_dump($head);
//
//        exit();*/
//
        $socket = new ClientSocket($ip, $port);
//
        $result = $socket->wr($str);
//
        $socket->__destruct();
//
        var_dump($result);
//        $BeginDate = "1546592150";
//        $EndDate = "1546842413";
//
//        $ThirdLogNob = "20190107142654101571";
//
//        $info = Db::name('cash_access')->where('create_time','between time',[$BeginDate,$EndDate])->select();
//
//        //总笔数
//        $count = count($info);
//        //出金总金额
//        $out = Db::name('cash_access')->where('create_time','between time',[$BeginDate,$EndDate])
//            ->where('type',2)
//            ->sum('money');
//        $in = Db::name('cash_access')->where('create_time','between time',[$BeginDate,$EndDate])
//            ->where('type',1)
//            ->sum('money');
//
//
//        $filepath = UPLOAD_PATH."cash_access/";
//        $filename = "CRJ".$ThirdLogNob."2222".date('YmdHis').".txt";
//
//        $content = "{$count}&{$out}&{$in}&0\r\n";
//
//        foreach ($info as $key=>$val){
//            $gold_type = $val['type'] == 1 ? 2 : 1;
//            $fkr = $val['type'] == 1 ? $val['user_id'] : $val['systemid'];
//            $skr = $val['type'] == 2 ? $val['user_id'] : $val['systemid'];
//            $date_str = date("Ymd",$val['create_time']);
//            $time_str = date("His",$val['create_time']);
//
//            $content .= "{$val['id']}&{$gold_type}& &{$fkr}&{$skr}&{$val['systemid']}&{$val['user_id']}&{$val['name']}&{$val['money']}&0&{$val['user_id']}&{$val['name']}&{$date_str}&{$time_str}&{$val['serial_num']}&\r\n";
//        }
//
//        //创建文件
//        file_put_contents($filepath.$filename,$content);
//
//
//        if(!$this->upload_ftp($filepath,$filename)){
//            //上传失败
//            echo "上传失败";
//            exit();
//        }
//        //上传成功
//        echo "上传成功";


    }


    public function upload_ftp($filepath,$filename)
    {
        $ftp_config = [
            'server'  => '116.62.210.102',
            'username'  => 'ftp_sxhl_scbank',
            'password'  => 'ftp_sxhl_scbank',
            'port'  => '21',
            //'pasv'  => true,
        ];

        $ftp = new Ftp();

        if(!$ftp->start($ftp_config)){
            return false;
        }
        $local_file = $filepath.$filename;
        $remote_file = date('Y-m-d')."/".$filename;

        if( !$ftp->put($remote_file,$local_file) ) {
            //上传文件成功!
            return false;
        }
        $ftp->close();

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
            return false;
        }

        return true;

    }




}
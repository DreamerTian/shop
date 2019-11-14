<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/26
 * Time: 15:12
 */

namespace app\home\controller;


use app\common\logic\UsersLogic;
use app\common\util\Aes;
use app\common\util\SignUtil;
use Think\Db;

class AppApi extends Base
{

    public function openAccountPush()
    {
        $data = I('post.');
        file_put_contents(UPLOAD_PATH.'/data.txt', "时间:".date('Y-m-d H:i:s')."-----------进入程序".PHP_EOL, FILE_APPEND);

        file_put_contents(UPLOAD_PATH.'/data.txt', var_export($data, TRUE), FILE_APPEND);

        $data_arr = $data;

        //交易系统ID
        $requestId  = $data_arr['requestId'];
        //签名
        $signature  = $data_arr['signature'];
        //协议名
        $method     = $data_arr['method'];
        //主体数据
        $params     = $data_arr['params'];

        //校验签名
        $signUtil = new SignUtil();
        if(!$signUtil->signIsOK($signature,$params)){
            return $this->jsonReturn($requestId, $method, ['recode'=>'-40000004','result'=>'签名错误']);
        }
        //解密主体数据 进行业务判断
        $aes = new Aes();
        $params_str = $aes->aes128cbcBase64Decrypt($params);
        if(!$params_str){
            return $this->jsonReturn($requestId, $method, ['recode'=>'-40000005','result'=>'解密失败']);
        }
        $params_arr = json_decode($params_str,true);
        if(empty($params_arr)){
            return $this->jsonReturn($requestId, $method, ['recode'=>'-2','result'=>'请求参数为空']);
        }

        if($method == "openAccountPush"){
            //然后进行 业务添加 返回
            if(empty($params_arr['mobile'])){
                return $this->jsonReturn($requestId, $method, ['recode'=>'-3','result'=>'手机号码不能为空']);
            }
            //查询是否有
            $user_info = Db::name('users')->where('mobile',$params_arr['mobile'])->where('systemid',$params_arr['systemId'])->find();

            if(!empty($user_info)){
                return $this->jsonReturn($requestId, $method, ['recode'=>'-1','result'=>'已经存在',
                    'info'=>['mallId'=>$user_info['user_id']."",'systemId'=>$params_arr['systemId']]]);
            }

            $logic = new UsersLogic();
            $password = encrypt('88888888');
            $result_data = $logic->reg($params_arr['mobile'],$password,$password);
            if($result_data['status'] != 1){
                return $this->jsonReturn($requestId, $method, ['recode'=>'-4','result'=>'添加失败']);
            }
            //修改用户信息
            $result = Db::name('users')->where('user_id',$result_data['result']['user_id'])->update([
                'cardtype'  => $params_arr['cardtype'],
                'idcard'    => $params_arr['idcard'],
                'systemid'  => $params_arr['systemId'],
                'name'      => $params_arr['name'],
            ]);
            if(!$result){
                return $this->jsonReturn($requestId, $method, ['recode'=>'-4','result'=>'添加失败']);
            }

            return $this->jsonReturn($requestId, $method, ['recode'=>'200','result'=>'添加成功',
                'info'=>['mallId'=>$result_data['result']['user_id']."",'systemId'=>$params_arr['systemId']]]);
        }

    }

    public function jsonReturn($requestId, $method, $result=[])
    {
        $data['requestId']  = $requestId;
        $data['method']     = $method;

        //加密返回参数
        $result_json_str = json_encode($result);
        $aes = new Aes();
        $data['result'] = $aes->aes128cbcBase64Encrypt($result_json_str);
        //通过加密参数 生成签名
        $signUtil = new SignUtil();
        $data['signature'] = $signUtil->stringSign($data['result']);

        $this->ajaxReturn($data);
    }

    public function sync_user_info()
    {
        $data = I('post.');

        $params = [
            "mallId"    => $data['user_id']."",
            "idcard"    => $data['idcard']."",
            "cardtype"  => $data['cardtype']."",
            "name"      => $data['name']."",
            "mobile"    => $data['mobile']."",
        ];


        $request_data['method']     = "openAccountReceive";
        $request_data['requestId']  = date('YmdHis').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 7);

        //加密参数
        $aes = new Aes();
        $request_data['params']     = $aes->aes128cbcBase64Encrypt(json_encode($params));

        //生成签名
        $signUtil = new SignUtil();
        $request_data['signature']  = $signUtil->stringSign($request_data['params']);

        $request_data_str = json_encode($request_data);

        $url = "http://116.62.210.102:16986/interactive_mall_core/http.ht";

        $result = httpRequest($url,"POST",$request_data_str);


        $result_arr = json_decode($result,true);

        //校验签名
        if(!$signUtil->signIsOK($result_arr['signature'],$result_arr['result'])){
            $this->ajaxReturn(['status'=> -11 ,'msg' => "签名校验失败"]);
        }

        //解密返回包
        $result_json = $aes->aes128cbcBase64Decrypt($result_arr['result']);

        $result_data = json_decode($result_json,true);

        //$this->ajaxReturn(['status'=> $result_data['recode'] ,'msg' => $result_data['result'] , 'data'=> $result_data['info']]);

        if($result_data['recode'] != 200 && $result_data['recode'] != -1){
            $this->ajaxReturn(['status'=> $result_data['recode'] ,'msg' => $result_data['result']]);
        }

        //修改数据库中的 交易所ID
        $result = Db::name('users')->where('user_id',$result_data['info']['mallId'])->update(['systemid'=>$result_data['info']['systemId']]);

        if(!$result){
            $this->ajaxReturn(['status'=> -12 ,'msg' => "修改交易ID失败"]);
        }

        //同步成功了给信用分
        $config = tpCache('credit');
        accountLog1($data['user_id'],0,$config['sync_to_gold_gave'],0,"同步到金网成功赠送信用额度");

        $this->ajaxReturn(['status'=> 1 ,'msg' => $result_data['result']]);

    }

    public function test()
    {
        $str = "JHx6dacbeXT+vGc+YY2GEwuOII3omDaw0yzRA5puEWxBxv941hdvYbccWVPeavNnNRYYNvVpjIz1If1XXm3n0rWQHpGD53ZUG510bKCO81jD6wEeBoRoJ4ExHI4+cYatru9knRustVIiLnhNpbM0mg==";
        $aes = new Aes();

        echo $aes->aes128cbcBase64Decrypt($str);
    }
}
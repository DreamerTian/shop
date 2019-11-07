<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/3
 * Time: 15:22
 */

namespace app\common\util;


class MessageHead
{
        /**交易类型 */
    public $TranFunc="";

        /**服务类型(01:请求02:应答)*/
    public $ServType="";

        /**MAC码 */
    public $MacCode="";

        /**交易日期*/
    public $TranDate="";

        /**交易时间*/
    public $TranTime="";

        /**应答码(000000成功，其余失败)*/
    public $RspCode="999999";

        /**应答描述*/
    public $RspMsg="a";

        /**后续包标志 (0结束包,1还有后续包，此域暂时没用)*/
    public $ConFlag=1;

        /**包体长度*/
    public $Length;

        /**操作员号*/
    public $CounterId="";

        /**请求or应答方系统流水号*/
    public $ThirdLogNob="";

        /**交易网代码 [必输]*/
    public $Qydm="";


    public function setPkgHead($transCode, $servType, $Length, $ThirdLogNob){

        $data[]     = $this->param_handle($transCode,4);
        $data[]     = $this->param_handle($servType,2);
        $data[]     = $this->param_handle("CMacmACmAcmaCmac",16);
        $data[]     = $this->param_handle(date('Ymd'),8);
        $data[]     = $this->param_handle(date('His'),6);
        $data[]     = $this->param_handle($this->RspCode,6);
        $data[]     = $this->param_handle($this->RspMsg,42);
        $data[]     = $this->param_handle($this->ConFlag,1);
        $data[]     = $this->param_handle($Length,4,2);
        $data[]     = $this->param_handle("NO001",5);
        $data[]     = $this->param_handle($ThirdLogNob,20);
        $data[]     = $this->param_handle("2222",4);

        return implode("",$data);
    }

    /**
     *  不够补位 多了截取
     * */
    public function param_handle($param, $len, $type=1)
    {
        $str = "";
        if(empty($param)){
            return $str;
        }

        $str = $param;

        if(strlen($param) > $len){
            $str = substr($param,0,$len);
        }

        if(strlen($param) < $len){
            $cha = $len-strlen($param);
            $lin = "";
            for ($i = 0 ; $i < $cha; $i++){
                if($type == 1){
                    $lin .= " ";
                }elseif ($type == 2){
                    $lin .= "0";
                }else{
                    $lin .= " ";
                }
            }
            $str = $lin.$param;
        }

        return $str;
    }

    /**
     *  拆分头部信息
     * */
    public function split_head($str)
    {
        $str = substr($str,0,118);

        $num = 0;

        $this->TranFunc = trim(substr($str,$num,4));

        $num += 4;

        $this->ServType = trim(substr($str,$num,2));

        $num += 2;

        $this->MacCode = trim(substr($str,$num,16));

        $num += 16;

        $this->TranDate = trim(substr($str,$num,8));

        $num += 8;

        $this->TranTime = trim(substr($str,$num,6));

        $num += 6;

        $this->RspCode = trim(substr($str,$num,6));

        $num += 6;

        $this->RspMsg = trim(substr($str,$num,42));

        $num += 42;

        $this->ConFlag = trim(substr($str,$num,1));

        $num += 1;

        $this->Length = trim(substr($str,$num,4));

        $num += 4;

        $this->CounterId = trim(substr($str,$num,5));

        $num += 5;

        $this->ThirdLogNob = trim(substr($str,$num,20));

        $num += 20;

        $this->Qydm = trim(substr($str,$num,4));

    }


}
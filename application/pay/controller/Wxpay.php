<?php
/*
* 微信支付官方接口
*/
namespace app\pay\controller;

use think\Request;
use think\Controller;
use think\Db;
use think\Route;
use think\Url;
use think\Validate;


use app\model\model\User;

//引入User模型
use app\model\model\Pay;

//引入Pay模型
use app\model\model\Order;

//引入Order模型
use app\model\model\Core;

//引入Core模型

use JsApiPay;
use WxPayUnifiedOrder;
use WxPayApi;
use WxPayNotify;
use WxPayConfig;
use WxPayNotifyReply;
use WxPayRefund;

require_once(PAY_PATH . "wxpay/lib/WxPay.Api.php");
class Wxpay extends Controller
{
    /*
     * 获取配置信息
     */
    private function getConfig($id)
    {
        $pay = Pay::where('id', $id)->find();
        if(trim($pay['value6'])=="*" or trim($pay['value7'])=="*"){
            $pay['value6'] = "";
            $pay['value7'] = "";
        }
        if($pay['value8']=="" or $pay['value8']==NULL){
            $value8 = [];
        }else {
            $value8 = explode("|",$pay['value8']);
            if (!in_array("native",$value8) && !in_array("h5",$value8) && !in_array("jsapi",$value8)) {
                $value8 = [];
            }
        }
        $arr = [
            "app_id"=>$pay['value1'],
            "merchant_id"=>$pay['value2'],
            "merchant_key"=>$pay['value3'],
            "app_secret"=>$pay['value4'],
            "txt"=>$pay['value5'],
            "apiclient_cert"=>$pay['value6'],
            "apiclient_key"=>$pay['value7'],
            "ability_to_pay"=>$value8
        ];
        return $arr;
    }
    /*
     * 发起支付
     */
    public function Submit(Request $request)
    {
        $getArray = $request->get();
        $accessMode = getAccessMode();
        $validate = new Validate([
            '__token__' => 'require|token'
        ]);
        if (!$validate->check($getArray) && $accessMode!="inside" && $accessMode!="external") {
            weuiMsg('info', $validate->getError());
            return;
        }
        if (!array_key_exists('out_trade_no', $getArray)) {
            weuiMsg('warn-primary', '请重新扫码并发起支付', '错误的订单号');
            return;
        }
        if (trim($getArray['out_trade_no']) == "") {
            weuiMsg('warn-primary', '请重新扫码并发起支付', '订单号为空');
            return;
        }
        $order = Order::getByOutTradeNo($getArray['out_trade_no']);  //获取订单信息
        if (!$order) {
            weuiMsg('warn-primary', '请重新发起支付', '订单号不存在');
            return;
        }
        if ($order['state'] != 0) {
            weuiMsg('info', '无需再次支付', '该订单已支付');
            return;
        }
        $order = Order::where("out_trade_no",$getArray['out_trade_no'])
            ->whereTime('expiration_time', '>', date('Y-m-d H:i:s'))
            ->find();  //获取订单信息
        if (!$order) {
            weuiMsg('warn-primary', '请重新发起支付', '订单已超时');
            return;
        }
        if ($order['type'] != "wxpay") {
            weuiMsg('info', '该订单为微信支付方式！', '抱歉');
            return;
        }
        /*
         * =====开始支付
         */
        $pay_config = $this->getConfig($order['pid']);
        $pay_config['notify_url'] = $request->domain() . '/notify/';  //异步回调
        $pay_config['return_url'] = $request->domain() . '/return/?out_trade_no=' . $order['out_trade_no'];  //同步回调

        require_once(PAY_PATH . "wxpay/lib/WxPay.Config.php");
        require_once(PAY_PATH . "wxpay/lib/WxPay.JsApiPay.php");

        $this->assign('out_trade_no',  $order['out_trade_no']);  //输出变量
        $this->assign('redirect_url',  $pay_config['return_url']);  //输出变量
        //统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody($order['name']);
        $input->SetOut_trade_no($order['out_trade_no']);
        $input->SetSpbill_create_ip(getIp());
        $input->SetTotal_fee(strval($order['money']*100));
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));  //十分钟过期
        $input->SetNotify_url($pay_config['notify_url']);
        $config = new WxPayConfig();
        switch ($accessMode) {
            case "inside":
                //=============内置浏览器
                if(in_array("jsapi",$pay_config['ability_to_pay'])) {
                    //======JSAPI支付
                    //1：获取用户openid
                    $tools = new JsApiPay();
                    $tools->curl_timeout = '30s';
                    $openId = $tools->GetOpenid();
                    if (!$openId or $openId == NULL) {
                        weuiMsg('warn-primary', $tools->data['errmsg'], 'OpenId获取失败，请检查微信公众号配置是否正确');
                        return;
                    }
                    $input->SetTrade_type("JSAPI");
                    $input->SetOpenid($openId);
                    $order_wx = WxPayApi::unifiedOrder($config, $input);
                    if (array_key_exists("result_code", $order_wx) && $order_wx["result_code"] == 'SUCCESS') {
                        $jsApiParameters = $tools->GetJsApiParameters($order_wx);

                        $this->assign('money', $order['money']);  //输出变量
                        $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
                        $this->assign('redirect_url', $pay_config['return_url']);  //输出变量
                        $this->assign('jsApiParameters', $jsApiParameters);  //输出变量

                        $order->trade_type = "JSAPI";
                        if ($order->save() !== false) {
                            return $this->fetch('jspay/wxpay');  //进入模板
                        } else {
                            weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                            return;
                        }
                    } elseif (isset($result["err_code"])) {
                        weuiMsg('warn-primary', $order_wx["err_code"], '下单失败：' . $order_wx["err_code_des"]);
                        return;
                    } else {
                        weuiMsg('warn-primary', $order_wx["return_code"], '下单失败：' . $order_wx["return_msg"]);
                        return;
                    }
                }else if(in_array("h5",$pay_config['ability_to_pay'])) {
                    //======H5支付
                    $input->SetTrade_type("MWEB");
                    $order_wx = WxPayApi::unifiedOrder($config, $input);
                    if (array_key_exists("result_code", $order_wx) && $order_wx["result_code"] == 'SUCCESS') {
                        $url = $order_wx['mweb_url'] . '&redirect_url=' . urlencode($pay_config['return_url']);
                        $order->trade_type = "H5";
                        if ($order->save() !== false) {
                            $jump_url =  $request->domain() . '/submit/wxpay/?out_trade_no=' . $order['out_trade_no'];
                            jumpBrowser($jump_url);
                        } else {
                            weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                            return;
                        }
                    } elseif (isset($order_wx["err_code"])) {
                        weuiMsg('warn-primary', $order_wx["err_code"], '下单失败：' . $order_wx["err_code_des"]);
                        return;
                    } else {
                        weuiMsg('warn-primary', $order_wx["return_code"], '下单失败：' . $order_wx["return_msg"]);
                        return;
                    }
                }else{
                    weuiMsg('warn-primary', '商户需要至少签约[JSAPI]或[H5支付]一种能力！');
                    return;
                }
                break;
            case "external":
                //=============手机浏览器
                if(in_array("h5",$pay_config['ability_to_pay'])) {
                $input->SetTrade_type("MWEB");
                $order_wx = WxPayApi::unifiedOrder($config,$input);
                if(array_key_exists("result_code",$order_wx) && $order_wx["result_code"]=='SUCCESS'){
                    $url=$order_wx['mweb_url'].'&redirect_url='.urlencode($pay_config['return_url']);
                    $order->trade_type = "H5";
                    if ($order->save() !== false) {
                        $html_text = "<script>window.location.replace('{$url}');</script>";
                        $this->assign('redirect_url', $pay_config['return_url']);  //输出变量
                        $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
                        $this->assign('tune_up_pay', $html_text);  //输出变量
                        return $this->fetch('transfer/waiting');  //进入模板
                    }else{
                        weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                        return;
                    }
                }elseif(isset($order_wx["err_code"])){
                    weuiMsg('warn-primary', $order_wx["err_code"] , '下单失败：'.$order_wx["err_code_des"]);
                    return;
                }else{
                    weuiMsg('warn-primary', $order_wx["return_code"] , '下单失败：'.$order_wx["return_msg"]);
                    return;
                }
                }else{
                    weuiMsg('warn-primary', '商户暂不支持H5（手机浏览器）支付方式！');
                    return;
                }
                break;
            case "pc":
                //=============电脑浏览器
                if(in_array("native",$pay_config['ability_to_pay'])) {
                    $input->SetTrade_type("NATIVE");
                    $input->SetProduct_id("01001");
                    $order_wx = WxPayApi::unifiedOrder($config,$input);
                    if(array_key_exists("result_code",$order_wx) && $order_wx["result_code"]=='SUCCESS'){
                        $code_url=$order_wx['code_url'];

                        $pay_info = getPayList("wxpay", 'alias');//支付方式信息

                        $this->assign('pay_info', $pay_info);  //输出变量
                        $this->assign('return_url', $pay_config['return_url']);  //输出变量
                        $this->assign('title', "微信扫码支付");  //输出变量
                        $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
                        $this->assign('money', $order['money']);  //输出变量
                        $this->assign('date', $order['creation_time']);  //输出变量
                        $this->assign('qrurl', $code_url);  //输出变量
                        $this->assign('name', $order['name']);  //输出变量
                        $order->trade_type = "NATIVE";
                        if ($order->save() !== false) {
                            return $this->fetch('qrpay/default/index');  //进入模板
                        }else{
                            weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                            return;
                        }
                    }elseif(isset($order_wx["err_code"])){
                        weuiMsg('warn-primary', $order_wx["err_code"] , '下单失败：'.$order_wx["err_code_des"]);
                        return;
                    }else{
                        weuiMsg('warn-primary', $order_wx["return_code"] , '下单失败：'.$order_wx["return_msg"]);
                        return;
                    }
                }else{
                    weuiMsg('warn-primary', '商户暂不支持Native（扫码）支付方式！');
                    return;
                }
                break;
            default:
                weuiMsg('warn-primary', '非法访问方式');
                return;
        }
    }
    /*
     * 同步跳转
     */
    public function ReturnUrl($paraArray,$template)
    {
        $order = Order::getByOutTradeNo($paraArray['out_trade_no']);  //获取订单信息
        if (!$order) {
            weuiMsg('warn-primary', '请重新发起支付', '订单号不存在');
            return;
        }
        if($order['state']<=0){
            if($order['trade_type'] == "H5"){
                //H5支付
                $this->assign('redirect_url', Request::instance()->domain() . '/return/?out_trade_no=' . $order['out_trade_no']);  //输出变量
                $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
                $this->assign('tune_up_pay', "");  //输出变量
                return $this->fetch('transfer/waiting');  //进入模板
            }else{
                weuiMsg('warn-primary', '请重新发起支付', '未支付');
                return;
            }
        }else{
            if($order['source']=="api"){
                $url = creat_callback($order['return_url'], $order, "TRADE_SUCCESS");
                $this->assign('url', $url);  //输出变量
                return $this->fetch('/jump');  //进入模板
            }
            $this->assign('money', $order['money'],2);  //输出变量
            return $this->fetch('return/'.$template.'/success');  //进入模板
        }
    }
    /*
     * 异步通知
     */
    public function NotifyUrl($paraArray)
    {
        $order = Order::getByOutTradeNo($paraArray['out_trade_no']);  //获取订单信息
        $pay_config = $this->getConfig($order['pid']);
        $pay_config['notify_url'] = '';

        require_once(PAY_PATH . "wxpay/lib/WxPay.Config.php");
        require_once(PAY_PATH . "wxpay/lib/WxPay.Notify.php");

        $notify = new WxPayNotify;
        $config = new WxPayConfig;
        $notify->Handle($config,false);
    }
    /*
     * 交易退款
     */
    public function Refund($paraArray,$order)
    {
        $pay_config = $this->getConfig($order['pid']);
        $pay_config['notify_url'] = '';
        require_once(PAY_PATH . "wxpay/lib/WxPay.Config.php");

        if($pay_config['apiclient_cert']=="" or $pay_config['apiclient_key']==""){
            return json_encode(['code'=>1, 'msg'=>'商户证书信息未填写，无法请求退款！']);
        }
        $config = new WxPayConfig;
        try{
            $input = new WxPayRefund();
            $input->SetTransaction_id($order['api_trade_no']);
            $input->SetTotal_fee(strval($order['money']*100));
            $input->SetRefund_fee(strval($order['money']*100));
            $input->SetOut_refund_no($order['out_trade_no']);
            $input->SetOp_user_id($config->GetMerchantId());
            $result = WxPayApi::refund($config,$input);
            if($result['return_code']=='SUCCESS' && $result['result_code']=='SUCCESS'){

                $order = Order::getByOutTradeNo($order['out_trade_no']);
                if (!$order) {
                    return json_encode(['code'=>1, 'msg'=>'订单不存在！']);
                }

                $data = [
                    'out_trade_no'=>$order['out_trade_no'],
                    'money'=>number_format(round(trim($order['money']), 2), 2, ".", "")   //退款金额
                ];
                //=====支付完成[业务逻辑]=====
                $payment_business = controller('Business');
                $success = $payment_business->Refund($data);
                //=====支付完成[业务逻辑]=====
                if ($success) {
                    return json_encode(['code'=>0, 'msg'=>'退款成功，可能会有几分钟的延迟！']);
                }else{
                    return json_encode(['code'=>1, 'msg'=>'退款成功，数据库更新失败！']);
                }
            }elseif(isset($result["err_code"])){
                return json_encode(['code'=>1, 'msg'=>'['.$result["err_code"].']'.$result["err_code_des"]]);
            }else{
                return json_encode(['code'=>1, 'msg'=>'[错误代码：'.$result["return_code"].']'.$result["return_msg"]]);
            }
        } catch(Exception $e) {
            return json_encode(['code'=>-1, 'msg'=>$e->getMessage()]);
        }


    }
}
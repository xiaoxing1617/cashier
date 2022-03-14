<?php
/*
* QQ钱包官方接口
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

use QpayMchAPI;
use QpayMchUtil;
use QpayNotify;

require_once(PAY_PATH . 'qqpay/lib/qpayMchAPI.class.php');

class Qqpay extends Controller
{
    /*
     * 获取配置信息
     */
    private function getConfig($id)
    {
        $pay = Pay::where('id', $id)->find();
        if(trim($pay['value3'])=="*" or trim($pay['value4'])=="*"){
            $pay['value3'] = "";
            $pay['value4'] = "";
        }
        if($pay['value7']=="" or $pay['value7']==NULL){
            $value7 = [];
        }else {
            $value7 = explode("|",$pay['value7']);
            if (!in_array("native",$value7) && !in_array("jsapi",$value7)) {
                $value7 = [];
            }
        }
        $arr = [
            "mchid" => $pay['value1'],
            "mchkey" => $pay['value2'],
            "userid" => $pay['value3'],
            "userpwd" => $pay['value4'],
            "sslcert" => $pay['value5'],
            "sslkey" => $pay['value6'],
            "ability_to_pay" => $value7
        ];
        return $arr;
    }

    /*
     * 发起支付
     */
    public function Submit(Request $request)
    {
        $getArray = $request->get();
        $validate = new Validate([
            '__token__' => 'require|token'
        ]);
        if (!$validate->check($getArray)) {
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
        if ($order['type'] != "qqpay") {
            weuiMsg('info', '该订单为QQ钱包支付方式！', '抱歉');
            return;
        }
        /*
         * =====开始支付
         */

        $accessMode = getAccessMode();
        $pay_config = $this->getConfig($order['pid']);
        $ability_to_pay = $pay_config['ability_to_pay'];
        unset($pay_config['ability_to_pay']);
        if(!in_array("jsapi",$ability_to_pay) && !in_array("native",$ability_to_pay)){
            weuiMsg('warn-primary', '商户需要至少签约[JSAPI]或[Native]一种能力！');
            return;
        }
        require_once(PAY_PATH . 'qqpay/lib/qpayMch.config.php');
        $pay_config['notify_url'] = $request->domain() . '/notify/';  //异步回调
        $pay_config['return_url'] = $request->domain() . '/return/?out_trade_no=' . $order['out_trade_no'];  //同步回调

        $input['notify_url'] = $request->domain() . '/notify/';  //异步回调
        $input["out_trade_no"] = $order['out_trade_no'];
        $input["body"] = $order['name'];
        $input["fee_type"] = "CNY";
        $input["spbill_create_ip"] = getIp();
        $input["total_fee"] = strval($order['money'] * 100);
        $input["time_expire"] = date("YmdHis", time() + 600);  //十分钟过期

        switch ($accessMode) {
            case ($accessMode=="inside" or $accessMode=="external" or $accessMode=="pc"):
                //=============内置浏览器 or 手机浏览器 or 电脑浏览器
                //api调用
                $qpayApi = new QpayMchAPI('https://qpay.qq.com/cgi-bin/pay/qpay_unified_order.cgi', null, 10);
                if($accessMode=="inside" && in_array("jsapi",$ability_to_pay)){
                    $input["trade_type"] = "JSAPI";
                }else{
                    $input["trade_type"] = "NATIVE";
                }
                $ret = $qpayApi->reqQpay($input);
                $result = QpayMchUtil::xmlToArray($ret);
                if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
                    if($input["trade_type"]=="JSAPI"){
                        //=============内置浏览器
                        $prepay_id = $result['prepay_id'];
                        $this->assign('MCH_ID', $pay_config['mchid']);  //输出变量
                        $this->assign('money', $order['money']);  //输出变量
                        $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
                        $this->assign('redirect_url', $pay_config['return_url']);  //输出变量
                        $this->assign('prepay_id', $prepay_id);  //输出变量
                        $order->trade_type = "JSAPI";
                        if ($order->save() !== false) {
                            return $this->fetch('jspay/qqpay');  //进入模板
                        }else{
                            weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                            return;
                        }
                    }else{
                        if($accessMode=="external" or $accessMode=="inside"){
                            //=============手机浏览器 or 内置浏览器
                            $code_url = 'https://myun.tenpay.com/mqq/pay/qrcode.html?_wv=1027&_bid=2183&t='.$result['prepay_id'];
                            $order->trade_type = "NATIVE";
                            if ($order->save() !== false) {
                                if($accessMode=="inside"){
                                    $html_text = "<script>window.location.href='{$code_url}';</script>";
                                    $this->assign('redirect_url', $pay_config['return_url']);  //输出变量
                                    $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
                                    $this->assign('tune_up_pay', $html_text);  //输出变量
                                    return $this->fetch('transfer/waiting');  //进入模板
                                }
                                $this->assign('data', $code_url);  //输出变量
                                $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
                                $this->assign('redirect_url', $pay_config['return_url']);  //输出变量
                                return $this->fetch('transfer/qqpay');  //进入模板
                            }else{
                                weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                                return;
                            }
                        }else{
                            //=============电脑浏览器
                            $code_url = $result['code_url'];
                            $pay_info = getPayList("qqpay", 'alias');//支付方式信息

                            $this->assign('pay_info', $pay_info);  //输出变量
                            $this->assign('return_url', $pay_config['return_url']);  //输出变量
                            $this->assign('title', "手机QQ扫码支付");  //输出变量
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
                        }
                    }
                } elseif (isset($result["err_code"])) {
                    weuiMsg('warn-primary', $result["err_code_des"], '下单失败');
                    return;
                } else {
                    weuiMsg('warn-primary', $result["return_msg"], '下单失败');
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
    public function ReturnUrl($paraArray, $template)
    {
        $order = Order::getByOutTradeNo($paraArray['out_trade_no']);  //获取订单信息
        if (!$order) {
            weuiMsg('warn-primary', '请重新扫码并发起支付', '订单号不存在');
            return;
        }
        if ($order['state'] <= 0) {
            weuiMsg('warn-primary', '请重新发起支付', '未支付');
            return;
        } else {
            if($order['source']=="api"){
                $url = creat_callback($order['return_url'], $order, "TRADE_SUCCESS");
                $this->assign('url', $url);  //输出变量
                return $this->fetch('/jump');  //进入模板
            }
            $this->assign('money', $order['money'], 2);  //输出变量
            return $this->fetch('return/' . $template . '/success');  //进入模板
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

        require_once(PAY_PATH . 'qqpay/lib/qpayMch.config.php');
        require_once(PAY_PATH . 'qqpay/lib/qpayNotify.class.php');

        $qpayNotify = new QpayNotify();
        $result = $qpayNotify->getParams();

        //判断签名
        if ($qpayNotify->verifySign()) {

            //判断签名及结果（即时到帐）
            if ($result['trade_state'] == "SUCCESS") {
                //商户订单号
                $out_trade_no = $result['out_trade_no'];
                //QQ钱包订单号
                $transaction_id = $result['transaction_id'];
                //金额,以分为单位
                $total_fee = $result['total_fee'];
                //币种
                $fee_type = $result['fee_type'];
                //用户标识
                if(array_key_exists("openid",$result)){
                    $openid = $result['openid'];
                }else{
                    $openid = NULL;
                }

                //------------------------------
                //处理业务开始
                //------------------------------
                if ($out_trade_no == $order['out_trade_no'] && $total_fee == strval($order['money'] * 100)) {

                    if($order['state']!=0){
                        return "<xml>
<return_code>SUCCESS</return_code>
</xml>";
                    }
                    $success_data = [
                        "out_trade_no"=>$paraArray['out_trade_no'],
                        "api_trade_no"=>$transaction_id,
                        "payment_time"=>date('Y-m-d H:i:s'),
                        "buyer"=>$openid
                    ];
                    //=====支付完成[业务逻辑]=====
                    $payment_business = controller('Business');
                    $success = $payment_business->Business($success_data);
                    //=====支付完成[业务逻辑]=====
                    if($success){
                        //======支付成功
                        return "<xml>
<return_code>SUCCESS</return_code>
</xml>";
                    }else{
                        //=====更新数据失败
                        return "<xml>
<return_code>FAIL</return_code>
</xml>";
                    }
                    //------------------------------
                    //处理业务完毕
                    //------------------------------
                }else{
                    return "<xml>
<return_code>FAIL</return_code>
</xml>";
                }
            }else{
                return "<xml>
<return_code>FAIL</return_code>
</xml>";
            }

        }else{
            return "<xml>
<return_code>FAIL</return_code>
<return_msg>签名失败</return_msg>
</xml>";
        }
    }
    public function refund($paraArray,$order){
        $pay_config = $this->getConfig($order['pid']);
        $pay_config['notify_url'] = '';

        if($pay_config['userid']=="" or $pay_config['userpwd']==""){
            return json_encode(['code'=>1, 'msg'=>'企业付款信息未填写，无法请求退款！']);
        }
        if($pay_config['sslcert']=="" or $pay_config['sslkey']==""){
            return json_encode(['code'=>1, 'msg'=>'商户证书信息未填写，无法请求退款！']);
        }
        require_once(PAY_PATH . 'qqpay/lib/qpayMch.config.php');
        //入参
        $params = array();
        $params["transaction_id"] = $order['api_trade_no'];
        $params["out_refund_no"] = $order['out_trade_no'];
        $params["refund_fee"] = strval($order['money']*100);
        $params["op_user_id"] = $pay_config['userid'];
        $params["op_user_passwd"] = md5($pay_config['userpwd']);
//api调用
        $qpayApi = new QpayMchAPI('https://api.qpay.qq.com/cgi-bin/pay/qpay_refund.cgi', true, 10);
        $ret = $qpayApi->reqQpay($params,"refund");
        if($ret['code']!=0){
            return json_encode(['code'=>1, 'msg'=>$ret['msg']]);
        }else{
            $ret = $ret['data'];
        }
        $result = QpayMchUtil::xmlToArray($ret);

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
            return json_encode(['code'=>1, 'msg'=>'['.$result["return_code"].']'.$result["return_msg"]]);
        }
    }
}
<?php
/*
 * 易支付接口
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

use AlipaySubmit;
use AlipayNotify;

class Epay extends Controller
{
    /*
     * 获取配置信息
     */
    private function getConfig($id)
    {
        $pay = Pay::where('id', $id)->find();
        $arr = [
            "partner" => $pay['value2'],  //易支付ID
            "key" => $pay['value3'],  //易支付密钥
            "transport" => (substr($pay['value1'], 5) == "https") ? "https" : "http",  //判断接口是什么协议
            "apiurl" => $pay['value1']  //易支付接口url
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
            weuiMsg('warn-primary', '请重新扫码并发起支付', '订单号不存在');
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
        if ($order['state'] != 0) {
            weuiMsg('info', '无需再次支付', '该订单已支付');
            return;
        }
        /*
         * =====开始支付
         */
        $pay_config = $this->getConfig($order['pid']);
        $notify_url = $request->domain() . '/notify/';  //异步回调
        $return_url = $request->domain() . '/return/?out_trade_no=' . $order['out_trade_no'];  //同步回调
        require_once(PAY_PATH . "epay/epay.config.php");
        require_once(PAY_PATH . "epay/lib/epay_submit.class.php");
        //构造要请求的参数数组，无需改动
        $parameter = array(
            "pid" => trim($alipay_config['partner']),
            "type" => $order['type'],
            "notify_url" => $notify_url,
            "return_url" => $return_url,
            "out_trade_no" => $order['out_trade_no'],
            "name" => $order['name'],
            "money" => $order['money'],
            "sitename" => Core::getByName('title')['value1']
        );
        //建立请求
        $alipaySubmit = new AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestForm($parameter);
//        echo $html_text;
        if(isTemplateName('return',Core::getByName('return_template')['value1'])){
            $template = Core::getByName('return_template')['value1'];  //同步回调模板
        }else{
            $template = 'default';  //同步回调模板
        }
        $this->assign('redirect_url', $return_url);  //输出变量
        $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
        $this->assign('tune_up_pay', $html_text);  //输出变量
        $order->trade_type = "EPAY";
        if ($order->save() !== false) {
            return $this->fetch('transfer/waiting');  //进入模板
        }else{
            weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
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
        $pay_config = $this->getConfig($order['pid']);
        require_once(PAY_PATH . "epay/epay.config.php");
        require_once(PAY_PATH . "epay/lib/epay_notify.class.php");
        //计算得出通知验证结果
        $alipayNotify = new AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyNotify();
        if ($verify_result) {//验证成功
            //商户订单号
            $out_trade_no = $paraArray['out_trade_no'];
            //易支付订单号
            $trade_no = $paraArray['trade_no'];
            //交易状态
            $trade_status = $paraArray['trade_status'];
            //支付方式
            $type = $paraArray['type'];
            //支付金额
            $money = $paraArray['money'];
            if ($paraArray['trade_status'] == 'TRADE_SUCCESS' && round($money, 2) == round($order['money'], 2)) {
                if ($order['state'] != 0) {
                    if($order['source']=="api"){
                        $url = creat_callback($order['return_url'], $order, "TRADE_SUCCESS");
                        $this->assign('url', $url);  //输出变量
                        return $this->fetch('/jump');  //进入模板
                    }
                    $this->assign('money', $order['money'], 2);  //输出变量
                    return $this->fetch('return/' . $template . '/success');  //进入模板
                }
                $success_data = [
                    "out_trade_no"=>$paraArray['out_trade_no'],
                    "api_trade_no"=>$trade_no,
                    "payment_time"=>date('Y-m-d H:i:s'),
                    "buyer"=>NULL
                ];
                //=====支付完成[业务逻辑]=====
                $payment_business = controller('Business');
                $success = $payment_business->Business($success_data);
                //=====支付完成[业务逻辑]=====
                if($success){
                    //======支付成功
                    if($order['source']=="api"){
                        $url = creat_callback($order['return_url'], $order, "TRADE_SUCCESS");
                        $this->assign('url', $url);  //输出变量
                        return $this->fetch('/jump');  //进入模板
                    }
                    $this->assign('money', $order['money'], 2);  //输出变量
                    return $this->fetch('return/' . $template . '/success');  //进入模板
                }else{
                    //=====更新数据失败
                    weuiMsg('warn-primary', '数据更新失败', '支付失败');
                    return;
                }
            }else{
                weuiMsg('warn-primary', '支付状态或支付金额异常', '支付失败');
                return;
            }
        }else{
            weuiMsg('warn-primary', '同步跳转验签失败', '支付失败');
            return;
        }
    }

    /*
     * 异步通知
     */
    public function NotifyUrl($paraArray)
    {
        $order = Order::getByOutTradeNo($paraArray['out_trade_no']);  //获取订单信息
        if (!$order) {
            return "fail";
        }
        $pay_config = $this->getConfig($order['pid']);
        require_once(PAY_PATH . "epay/epay.config.php");
        require_once(PAY_PATH . "epay/lib/epay_notify.class.php");
        //计算得出通知验证结果
        $alipayNotify = new AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyNotify();

        if ($verify_result) {//验证成功
            //商户订单号
            $out_trade_no = $paraArray['out_trade_no'];
            //易支付订单号
            $trade_no = $paraArray['trade_no'];
            //交易状态
            $trade_status = $paraArray['trade_status'];
            //支付方式
            $type = $paraArray['type'];
            //支付金额
            $money = $paraArray['money'];

            if ($paraArray['trade_status'] == 'TRADE_SUCCESS' && round($money, 2) == round($order['money'], 2)) {
                if ($order['state'] != 0) {
                    return "success";
                }
                $success_data = [
                    "out_trade_no"=>$paraArray['out_trade_no'],
                    "api_trade_no"=>$trade_no,
                    "payment_time"=>date('Y-m-d H:i:s'),
                    "buyer"=>NULL
                ];
                //=====支付完成[业务逻辑]=====
                $payment_business = controller('Business');
                $success = $payment_business->Business($success_data);
                //=====支付完成[业务逻辑]=====
                if ($success) {
                    //======支付成功
                    return "success";
                } else {
                    //=====更新数据失败
                    return "fail";
                }
            } else {
                return "fail";
            }
        } else {
            return "fail";
        }
        return "fail";
    }
}
<?php
/*
* 星益云聚合收银台接口
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
use CashierFunction;
use CashierCallback;
class Cashier extends Controller
{
    /*
     * 获取配置信息
     */
    private function getConfig($id)
    {
        $pay = Pay::where('id', $id)->find();
        $arr = [
            "uid" => $pay['value2'],  //商户UID
            "key" => $pay['value3'],  //商户密钥
            "apiurl" => $pay['value1']  //聚合收银台接口url
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
        $order = Order::where("out_trade_no", $getArray['out_trade_no'])
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
        $return_url = $request->domain() . '/return/';  //同步回调
        require_once(PAY_PATH . "cashier/config.php");
        require_once PAY_PATH . "cashier/lib/CashierFunction.php";
        $api = $config['url']."submit/";  //支付请求API接口
        $params = array(
            'time' => time(),
            'mod'=>'api',  //固定值，无需修改！！！
            'third_trade_no' => $order['out_trade_no'],  //系统订单号
            'type' => $order['type'],  //支付方式
            'money' => $order['money'],  //支付金额
            'trade_name' => $order['name'],  //商品名称
            'remarks' => $order['remarks'],  //备注
            'notify_url' => $notify_url,  //异步通知地址
            'return_url' => $return_url  //同步跳转地址
        );
        $CashierFunction = new CashierFunction;
        $url = $CashierFunction->createUrlStr(
            $params,  //参数
            true,  //需要商户验证
            $config,  //配置信息、商户信息
            $config['eliminate'],  //URL剔除字段
            true  //做urlcode编码
        );  //生成URL及签名
        $url = $api."?".$url;
        $order->trade_type = "CASHIER";
        if ($order->save() !== false) {
            Header("Location:$url");
            return;
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
        $order = Order::getByOutTradeNo($paraArray['third_trade_no']);  //获取订单信息
        if (!$order) {
            weuiMsg('warn-primary', '请重新发起支付', '订单号不存在');
            return;
        }
        $pay_config = $this->getConfig($order['pid']);
        require_once PAY_PATH . "cashier/config.php";
        require_once PAY_PATH . "cashier/lib/CashierCallback.php";

        //计算得出通知验证结果
        $CashierCallback = new CashierCallback($config);
        $verify_result = $CashierCallback->verifyNotify();

        if($verify_result) {  //验签成功
            $money = $paraArray['money'];
            $api_trade_no = $paraArray['api_trade_no'];
            $third_trade_no = $paraArray['third_trade_no'];
            $payment_time = $paraArray['payment_time'];

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
                    "out_trade_no"=>$third_trade_no,
                    "api_trade_no"=>$api_trade_no,
                    "payment_time"=>$payment_time,
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
        $order = Order::getByOutTradeNo($paraArray['third_trade_no']);  //获取订单信息
        if (!$order) {
            return "fail";
        }
        $pay_config = $this->getConfig($order['pid']);
        require_once PAY_PATH . "cashier/config.php";
        require_once PAY_PATH . "cashier/lib/CashierCallback.php";
        //计算得出通知验证结果
        $CashierCallback = new CashierCallback($config);
        $verify_result = $CashierCallback->verifyNotify();

        if($verify_result) {  //验签成功
            $money = $paraArray['money'];
            $api_trade_no = $paraArray['api_trade_no'];
            $third_trade_no = $paraArray['third_trade_no'];
            $payment_time = $paraArray['payment_time'];

            if ($paraArray['trade_status'] == 'TRADE_SUCCESS' && round($money, 2) == round($order['money'], 2)) {
                if ($order['state'] != 0) {
                    return "success";
                }
                $success_data = [
                    "out_trade_no"=>$third_trade_no,
                    "api_trade_no"=>$api_trade_no,
                    "payment_time"=>$payment_time,
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
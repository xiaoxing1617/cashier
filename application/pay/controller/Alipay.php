<?php
/*
* 支付宝官方接口
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

use AlipayTradeWapPayContentBuilder;
use AlipayTradePrecreateContentBuilder;
use AlipayTradeService;
use AlipayTradeRefundContentBuilder;
use AlipayTradePagePayContentBuilder;

class Alipay extends Controller
{
    /*
     * 获取配置信息
     */
    private function getConfig($id)
    {
        $pay = Pay::where('id', $id)->find();
        if ($pay['value4'] == "" or $pay['value4'] == NULL) {
            $value4 = [];
        } else {
            $value4 = explode("|", $pay['value4']);
            if (!in_array("pc", $value4) && !in_array("mobile", $value4) && !in_array("face_to_face", $value4)) {
                $value4 = [];
            }
        }
        $arr = [
            "appid" => $pay['value1'],  //应用ID
            "alipay_public_key" => $pay['value2'],  //支付宝公钥
            "merchant_private_key" => $pay['value3'],  //商户密钥
            "ability_to_pay" => $value4,  //支付能力
            "notify_url" => '',  //异步通知
            "return_url" => ''  //同步跳转
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
            weuiMsg('warn-primary', '请重新发起支付', '订单号为空');
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
        if ($order['type'] != "alipay") {
            weuiMsg('info', '该订单为支付宝支付方式！', '抱歉');
            return;
        }
        /*
         * =====开始支付
         */
        $pay_config = $this->getConfig($order['pid']);
        $pay_config['notify_url'] = $request->domain() . '/notify/';  //异步回调
        $pay_config['return_url'] = $request->domain() . '/return/?out_trade_no=' . $order['out_trade_no'];  //同步回调
        if (empty($pay_config['ability_to_pay'])) {
            weuiMsg('warn-primary', '商户未设置已签约能力，请尽快前往商户后台设置！');
            return;
        }

        require_once(PAY_PATH . "alipay/model/builder/AlipayTradeWapPayContentBuilder.php");  //手机网站
        require_once(PAY_PATH . "alipay/model/builder/AlipayTradePagePayContentBuilder.php");  //电脑网站
        require_once(PAY_PATH . "alipay/model/builder/AlipayTradePrecreateContentBuilder.php");  //当面付2.0
        require_once(PAY_PATH . "alipay/AlipayTradeService.php");
        require_once(PAY_PATH . "alipay/config.php");
        //【当面付 - 支付】构造参数
        $qrPayRequestBuilder = new AlipayTradePrecreateContentBuilder();
        $qrPayRequestBuilder->setSubject($order['name']);
        $qrPayRequestBuilder->setTotalAmount($order['money']);
        $qrPayRequestBuilder->setOutTradeNo($order['out_trade_no']);
        $qrPayRequestBuilder->setTimeExpress("10m");  //十分钟过期

        $accessMode = getAccessMode();
        $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
        $this->assign('redirect_url', $pay_config['return_url']);  //输出变量
        $this->assign("accessMode", $accessMode);
        switch ($accessMode) {
            case ($accessMode == "inside" or $accessMode == "external"):
                //=============内置浏览器 or 手机浏览器
                if (in_array("mobile", $pay_config['ability_to_pay'])) {
                    //【手机网站 - 支付】构造参数
                    $payRequestBuilder = new AlipayTradeWapPayContentBuilder();
                    $payRequestBuilder->setSubject($order['name']);
                    $payRequestBuilder->setTotalAmount($order['money']);
                    $payRequestBuilder->setOutTradeNo($order['out_trade_no']);
                    $payRequestBuilder->setTimeExpress("10m");  //十分钟过期
                    //传入配置信息
                    $aop = new AlipayTradeService($config);

                    $this->assign('title', "正在发起支付...");  //输出变量
                    $this->assign('mod', "mobile");  //输出变量
                    $this->assign('data', $aop->wapPay($payRequestBuilder));  //输出变量
                    $order->trade_type = "WapPay";
                    if ($order->save() !== false) {
                        return $this->fetch('transfer/alipay');  //进入模板
                    } else {
                        weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                        return;
                    }
                } else if (in_array("face_to_face", $pay_config['ability_to_pay'])) {
                    //========== 使用当面付
                    $aop = new AlipayTradeService($config);
                    try {
                        $qrPayResult = $aop->qrPay($qrPayRequestBuilder);
                    } catch (Exception $e) {
                        weuiMsg('warn-primary', $e->getMessage(), '接口请求失败');
                        return;
                    }
                    //	根据状态值进行业务处理
                    $status = $qrPayResult->getTradeStatus();
                    $response = $qrPayResult->getResponse();
                    if ($status == 'SUCCESS') {
                        $code_url = $response->qr_code;
                    } elseif ($status == 'FAILED') {
                        weuiMsg('warn-primary', $response->sub_msg, '创建订单失败');
                        return;
                    } else {
                        weuiMsg('warn-primary', $response, '系统异常');
                        return;
                    }
                    if ($accessMode == "external") {
                        $this->assign('data', "alipays://platformapi/startapp?appId=20000067&url=" . $code_url);  //输出变量
                    } else {
                        $this->assign('data', $code_url);  //输出变量
                    }
                    $this->assign('mod', "face_to_face");  //输出变量
                    $order->trade_type = "FACE";
                    if ($order->save() !== false) {
                        return $this->fetch('transfer/alipay');  //进入模板
                    } else {
                        weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                        return;
                    }
                } else {
                    weuiMsg('warn-primary', '商户需要至少签约[当面付]或[手机网站支付]一种能力！');
                    return;
                }
                break;
            case "pc":
                //=============电脑浏览器
                if (in_array("pc", $pay_config['ability_to_pay'])) {
                    //构造参数
                    $payRequestBuilder = new AlipayTradePagePayContentBuilder();
                    $payRequestBuilder->setSubject($order['name']);
                    $payRequestBuilder->setTotalAmount($order['money']);
                    $payRequestBuilder->setOutTradeNo($order['out_trade_no']);
                    $payRequestBuilder->setTimeExpress("10m");  //十分钟过期;
                    $aop = new AlipayTradeService($config);
                    $order->trade_type = "PagePay";
                    if ($order->save() !== false) {
                        return $aop->pagePay($payRequestBuilder);
                    } else {
                        weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                        return;
                    }
                } else if (in_array("face_to_face", $pay_config['ability_to_pay'])) {
                    $aop = new AlipayTradeService($config);
                    try {
                        $qrPayResult = $aop->qrPay($qrPayRequestBuilder);
                    } catch (Exception $e) {
                        weuiMsg('warn-primary', $e->getMessage(), '接口请求失败');
                        return;
                    }
                    //	根据状态值进行业务处理
                    $status = $qrPayResult->getTradeStatus();
                    $response = $qrPayResult->getResponse();
                    if ($status == 'SUCCESS') {
                        $code_url = $response->qr_code;
                    } elseif ($status == 'FAILED') {
                        weuiMsg('warn-primary', $response->sub_msg, '创建订单失败');
                        return;
                    } else {
                        weuiMsg('warn-primary', $response, '系统异常');
                        return;
                    }

                    $pay_info = getPayList("alipay", 'alias');//支付方式信息

                    $this->assign('pay_info', $pay_info);  //输出变量
                    $this->assign('return_url', $pay_config['return_url']);  //输出变量
                    $this->assign('title', "支付宝扫码支付");  //输出变量
                    $this->assign('out_trade_no', $order['out_trade_no']);  //输出变量
                    $this->assign('money', $order['money']);  //输出变量
                    $this->assign('date', $order['creation_time']);  //输出变量
                    $this->assign('qrurl', $code_url);  //输出变量
                    $this->assign('name', $order['name']);  //输出变量

                    $order->trade_type = "FACE";
                    if ($order->save() !== false) {
                        return $this->fetch('qrpay/default/index');  //进入模板
                    } else {
                        weuiMsg('warn-primary', '支付场景异常，请稍后重试！');
                        return;
                    }
                } else {
                    weuiMsg('warn-primary', '商户需要至少签约[当面付]或[电脑网站支付]一种能力！');
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
            weuiMsg('warn-primary', '请重新发起支付', '订单号不存在');
            return;
        }
        if ($order['trade_type'] == 'FACE') {
            //当面付支付场景
            if ($order['state'] <= 0) {
                weuiMsg('warn-primary', '请重新发起支付', '未支付');
                return;
            } else {
                if ($order['source'] == "api") {
                    $url = creat_callback($order['return_url'], $order, "TRADE_SUCCESS");
                    $this->assign('url', $url);  //输出变量
                    return $this->fetch('/jump');  //进入模板
                }
                $this->assign('money', $order['money'], 2);  //输出变量
                return $this->fetch('return/' . $template . '/success');  //进入模板
            }
        }
        $pay_config = $this->getConfig($order['pid']);
        require_once(PAY_PATH . "alipay/AlipayTradeService.php");
        require_once(PAY_PATH . "alipay/config.php");
        //计算得出通知验证结果
        $alipaySevice = new AlipayTradeService($config);
        $verify_result = $alipaySevice->check($paraArray);
        if ($verify_result) {//验证成功
            //商户订单号
            $out_trade_no = $paraArray['out_trade_no'];
            //支付宝交易号
            $trade_no = $paraArray['trade_no'];
            //交易金额
            $total_amount = $paraArray['total_amount'];
            //买家支付宝
            if (array_key_exists("buyer_id", $paraArray)) {
                $buyer = $paraArray['buyer_id'];
            } else {
                $buyer = NULL;
            }
            //支付时间
            if (!array_key_exists("gmt_payment", $paraArray)) {
                $paraArray['gmt_payment'] = date('Y-m-d H:i:s');
            }
            if (round($total_amount, 2) == round($order['money'], 2)) {
                if ($order['state'] != 0) {
                    if ($order['source'] == "api") {
                        $url = creat_callback($order['return_url'], $order, "TRADE_SUCCESS");
                        $this->assign('url', $url);  //输出变量
                        return $this->fetch('/jump');  //进入模板
                    }
                    $this->assign('money', $order['money']);  //输出变量
                    return $this->fetch('return/' . $template . '/success');  //进入模板
                }
                $success_data = [
                    "out_trade_no" => $paraArray['out_trade_no'],
                    "api_trade_no" => $trade_no,
                    "payment_time" => $paraArray['gmt_payment'],
                    "buyer" => $buyer
                ];
                //=====支付完成[业务逻辑]=====
                $payment_business = controller('Business');
                $success = $payment_business->Business($success_data);
                //=====支付完成[业务逻辑]=====
                if ($success) {
                    //======支付成功
                    if ($order['source'] == "api") {
                        $url = creat_callback($order['return_url'], $order, "TRADE_SUCCESS");
                        $this->assign('url', $url);  //输出变量
                        return $this->fetch('/jump');  //进入模板
                    }
                    $this->assign('money', $order['money'], 2);  //输出变量
                    return $this->fetch('return/' . $template . '/success');  //进入模板
                } else {
                    //=====更新数据失败
                    weuiMsg('warn-primary', '数据更新失败', '支付失败');
                    return;
                }

            } else {
                weuiMsg('warn-primary', '支付金额异常', '支付失败');
                return;
            }
        } else {
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
        require_once(PAY_PATH . "alipay/AlipayTradeService.php");
        require_once(PAY_PATH . "alipay/config.php");
        //计算得出通知验证结果
        $alipaySevice = new AlipayTradeService($config);
        $verify_result = $alipaySevice->check($paraArray);

        if ($verify_result) {//验证成功
            //商户订单号
            $out_trade_no = $paraArray['out_trade_no'];
            //支付宝交易号
            $trade_no = $paraArray['trade_no'];
            //交易金额
            $total_amount = $paraArray['total_amount'];
            //买家支付宝
            if (array_key_exists("buyer_id", $paraArray)) {
                $buyer = $paraArray['buyer_id'];
            } else {
                $buyer = NULL;
            }
            if ($paraArray['trade_status'] == 'TRADE_SUCCESS' && round($total_amount, 2) == round($order['money'], 2)) {
                if ($order['state'] != 0) {
                    return "success";
                }
                $success_data = [
                    "out_trade_no" => $paraArray['out_trade_no'],
                    "api_trade_no" => $trade_no,
                    "payment_time" => $paraArray['gmt_payment'],
                    "buyer" => $buyer
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

    /*
    * 交易退款
    */
    public function refund($paraArray, $order)
    {
        $pay_config = $this->getConfig($order['pid']);
        $pay_config['notify_url'] = '';
        require_once(PAY_PATH . "alipay/config.php");

        require_once(PAY_PATH . "alipay/model/builder/AlipayTradeRefundContentBuilder.php");
        require_once(PAY_PATH . "alipay/AlipayTradeService.php");

// 创建请求builder，设置请求参数
        $requestBuilder = new AlipayTradeRefundContentBuilder();
        $requestBuilder->setTradeNo($order['api_trade_no']);
        $requestBuilder->setRefundAmount($order['money']);

// 调用退款接口
        $trade = new AlipayTradeService($config);
        try {
            $refundResult = $trade->refund($requestBuilder);
        } catch (Exception $e) {
            return json_encode(['code' => -1, 'msg' => '支付宝接口请求失败！' . $e->getMessage()]);
        }

//	根据状态值进行业务处理
        $status = $refundResult->getTradeStatus();
        $response = $refundResult->getResponse();

        if ($status == 'SUCCESS') {

            $order = Order::getByOutTradeNo($order['out_trade_no']);
            if (!$order) {
                return json_encode(['code' => 1, 'msg' => '订单不存在！']);
            }

            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'money' => number_format(round(trim($order['money']), 2), 2, ".", "")   //退款金额
            ];
            //=====支付完成[业务逻辑]=====
            $payment_business = controller('Business');
            $success = $payment_business->Refund($data);
            //=====支付完成[业务逻辑]=====
            if ($success) {
                return json_encode(['code' => 0, 'msg' => '退款成功，可能会有几分钟的延迟！']);
            } else {
                return json_encode(['code' => 1, 'msg' => '退款成功，数据库更新失败！']);
            }

        } elseif ($status == 'FAILED') {
            return json_encode(['code' => 1, 'msg' => '[' . $response->sub_code . ']' . $response->sub_msg]);
        } else {
            return json_encode(['code' => 1, 'msg' => '未知错误']);
        }
    }
}
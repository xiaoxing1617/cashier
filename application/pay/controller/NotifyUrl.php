<?php

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


class NotifyUrl extends Controller
{
    public function Submit(Request $request)
    {
        weuiMsg('waiting', '<script>setInterval(function (){window.location.href="/"},1000);</script>',"正在为您跳转到首页...",false);
        return;
    }
    /*
     * 异步回调通知 - 业务逻辑
     */
    public function NotifyUrl(Request $request)
    {
        if ($request->isGet()) {
            $paraArray = $request->get();
        } else if ($request->isPost()) {
            $paraArray = $request->post();
        } else {
            return "fail";
        }

        if (!array_key_exists('out_trade_no', $paraArray)) {
            //获取通知的数据
            $xml = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");
            if (empty($xml)) {
                return "fail";
            }
            libxml_disable_entity_loader(true);
            $paraArray = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        }

        if (array_key_exists('third_trade_no', $paraArray)) {
            $third_trade_no = trim($paraArray['third_trade_no']);  //第三方订单号
            if ($third_trade_no == '') {
                return "fail";
            }
            $order = Order::getByOutTradeNo($third_trade_no);
        } else {
            if (!array_key_exists('out_trade_no', $paraArray)) {
                return "fail";
            } else {
                $out_trade_no = trim($paraArray['out_trade_no']);  //系统订单号
                if ($out_trade_no == '') {
                    return "fail";
                }
                $order = Order::getByOutTradeNo($out_trade_no);
            }
        }
        if (!$order) {
            return "fail";
        }

        $pay = Pay::where('id', $order['pid'])->find();
        if (!$pay) {
            return "fail";
        }
        switch ($pay['plug_in']) {
            case 'cashier':
                $obj = new Cashier;
                break;
            case 'epay':
                $obj = new Epay;
                break;
            case 'alipay':
                $obj = new Alipay;
                unset($paraArray['/notify/']);
                break;
            case 'wxpay':
                $obj = new Wxpay;
                break;
            case 'qqpay':
                $obj = new Qqpay;
                break;
            default:
                return "fail";
        }
        return $obj->NotifyUrl($paraArray);
    }
}
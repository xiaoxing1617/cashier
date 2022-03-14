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
use app\model\model\Service;
//引入Service模型

class Business extends Controller
{
    public function Submit(Request $request)
    {
        weuiMsg('waiting', '<script>setInterval(function (){window.location.href="/"},1000);</script>',"正在为您跳转到首页...",false);
        return;
    }
    public function Business($data = array())
    {
        if ($data == array()) {
            return false;
        }
        if (array_key_exists('out_trade_no', $data)) {
            $order = Order::getByOutTradeNo($data['out_trade_no']);
        } elseif(array_key_exists('api_trade_no', $data)){
            $order = Order::getByApiTradeNo($data['api_trade_no']);
        }else{
            return false;
        }
        if (!$order) {
            return false;
        }

        $order->state = 1;
        $order->api_trade_no = $data['api_trade_no'];
        $order->payment_time = $data['payment_time'];
        $order->buyer = $data['buyer'];
        if ($order->save() !== false) {
            if($order['source']=="api") {
                $next_notify_time = date("Y-m-d H:i:s", strtotime("1 minute", strtotime($data['payment_time'])));
                Order::where("id", $order['id'])->update(['notify_num' => 1, 'next_notify_time' => $next_notify_time]);  //下次通知：1分钟后
                $url = creat_callback($order['notify_url'], $order, "TRADE_SUCCESS");
                if (do_notify($url)) {
                    Order::where("id", $order['id'])->update(["notify_end" => "1"]);  //通知成功
                }
            }
            //===服务订单
            $service = Service::getByServiceTradeNo($order['service_trade_no']);
            $temp = User::getByUid($service['uid']);
            if ($order['service_trade_no'] != null or $order['service_trade_no'] != "") {
                $service = Service::getByServiceTradeNo($order['service_trade_no']);
                if ($service && round($order['money'], 2) == round($service['money'], 2)) {
                    if ($temp) {
                        $month = $service['month'];
                        $arr = merchantCheck($service['uid'], true);
                        if ($arr['code'] == 0) {
                            if(strtotime($arr['date'])>=time()){
                                $date = $arr['date'];
                            }else{
                                $date = date('Y-m-d H:i:s');
                            }
                        }else{
                            $date = date('Y-m-d H:i:s');
                        }
                        $temp->expiration_time = date("Y-m-d H:i:s", strtotime("+".$month." months", strtotime($date)));
                        $temp->save();
                    }
                }
            }
            //===服务订单
            return true;
        } else {
            return false;
        }
    }
    public function Refund($data = array()){
        if ($data == array()) {
            return false;
        }
        if (array_key_exists('out_trade_no', $data)) {
            $order = Order::getByOutTradeNo($data['out_trade_no']);
        } elseif(array_key_exists('api_trade_no', $data)){
            $order = Order::getByApiTradeNo($data['api_trade_no']);
        }else{
            return false;
        }
        if (!$order) {
            return false;
        }

        $order->state = 2;  //改为【已退款】
        $order->refund_time = date('Y-m-d H:i:s');  //退款时间
        $order->refund_money = number_format(round(trim($data['money']), 2), 2, ".", "");   //退款金额
        if ($order->save() !== false) {
            $url = creat_callback($data['notify_url'], $data, "FULL_REFUND");
            do_notify($url);
            return true;
        }else{
            return false;
        }

    }
}
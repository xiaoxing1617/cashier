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
            /**======发送邮箱=====*/
            /**
             * todo 【不上线】避免造成订单堵塞
            $user = User::getByUid($order['uid']);
            if($user && !empty($user['email'])){
                $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
                $client_arr = getPayList($order['type'], 'alias');//支付方式信息
                $type_name = empty($client_arr['name'])?'':$client_arr['name'];
                $title = "【". $type_name . "收款到账】".$order['money']."元（".$core['title']."）";

                $html = "————".$core['title']."————<br/>";
                $html .= "系统订单号：{$order->out_trade_no}<br/>";
                $html .= "订单来源：{$order->getSourceName()}<br/>";
                $html .= "支付场景：{$order->getTradeTypeName()}<br/>";
                $html .= "商户UID：{$order['uid']}<br/>";
                $html .= "支付方式：{$type_name}<br/>";
                $html .= "支付金额：{$order['money']}元<br/>";
                $html .= "支付时间：{$order['payment_time']}<br/>";
                $html .= "订单备注：{$order['remarks']}<br/>";
                $html .= "————[系统收款提示邮件]————<br/>";

                send_mail(trim($user['email']), trim($title), $html);
            }
            */
            /**======发送邮箱=====*/
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

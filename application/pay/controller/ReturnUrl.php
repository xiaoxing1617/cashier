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

class ReturnUrl extends Controller
{
    public function Submit(Request $request)
    {
        weuiMsg('waiting', '<script>setInterval(function (){window.location.href="/"},1000);</script>',"正在为您跳转到首页...",false);
        return;
    }
    /*
     * 同步跳转通知 - 业务逻辑
     */
    public function ReturnUrl(Request $request)
    {
        if ($request->isGet()) {
            $paraArray = $request->get();
        } else if ($request->isPost()) {
            $paraArray = $request->post();
        } else {
            weuiMsg('info', '不支持该请求方法', '抱歉');
            return;
        }
        if (array_key_exists('third_trade_no', $paraArray)) {
            $third_trade_no = trim($paraArray['third_trade_no']);  //第三方订单号
            if ($third_trade_no == '') {
                weuiMsg('warn-primary', '第三方订单号为空', '警告');
                return;
            }
            $order = Order::getByOutTradeNo($third_trade_no);
        } else {
            if (!array_key_exists('out_trade_no', $paraArray)) {
                weuiMsg('warn-primary', '必须传入系统订单号', '警告');
                return;
            } else {
                $out_trade_no = trim($paraArray['out_trade_no']);  //系统订单号
                if ($out_trade_no == '') {
                    weuiMsg('warn-primary', '系统订单号不能为空', '警告');
                    return;
                }
                $order = Order::getByOutTradeNo($out_trade_no);
            }
        }
        if (!$order) {
            weuiMsg('warn-primary', '该订单号不存在或已被删除', '警告');
            return;
        }
        $pay = Pay::where('id',$order['pid'])->find();
        if (!$pay) {
            weuiMsg('warn-primary', '该支付接口不存在或已被删除', '警告');
            return;
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
                unset($paraArray['/return/']);
                break;
            case 'wxpay':
                $obj = new Wxpay;
                break;
            case 'qqpay':
                $obj = new Qqpay;
                break;
            default:
                weuiMsg('info', '不存在此支付方式');
                return;
        }
        if(isTemplateName('return',Core::getByName('return_template')['value1'])){
            $template = Core::getByName('return_template')['value1'];  //同步回调模板
        }else{
            $template = 'default';  //同步回调模板
        }
        $accessMode = getAccessMode();
        if($accessMode=="inside"){
            $client = getClientkeyword();  //获取客户端打开方式
            $client_arr = getPayList($client, 'client_keyword');//支付方式信息
            if(array_key_exists("alias",$client_arr)){
                $client = $client_arr['alias'];
            }else{
                $client = "未知";
            }
        }else{
            $client = "浏览器";
        }
        $pay_list = getPayList();//支付方式信息
        if(array_key_exists($order['type'],$pay_list)){
            $this->assign('type',$pay_list[$order['type']]['name']);  //支付方式
        }else{
            $this->assign('type', "未知");  //支付方式
        }
        $user = User::getByUid($order['uid']);  //获取商户信息
        if(!$user){
            $this->assign('name', "[商户不存在或已注销]");  //支付名称
            $this->assign('qq', "0");  //支付名称
        }else{
            $this->assign('nickname', $user['nickname']);  //支付名称
            $this->assign('qq', $user['qq']);  //支付名称
        }
        $this->assign('client', $client);  //支付方式
        if($order['payment_time']==NULL or $order['payment_time']==""){
            $this->assign('date',date('Y-m-d H:i:s'));  //支付时间
        }else{
            $this->assign('date', $order['payment_time']);  //支付时间
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        $this->assign('core',$core);  //网站信息
        return $obj->ReturnUrl($paraArray,$template);
    }
}
<?php

namespace app\admin\controller;

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
use app\model\model\Captcha;

//引入Captcha模型
use app\model\model\Login;

//引入Login模型
use app\model\model\Service;

//引入Service模型
class PayPlugIn extends Controller
{
    public function Index(Request $request)
    {
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] == -1) {
            weuiMsg('info', "请先登录");
            return;
        }
        if ($loginCheck['code'] != 0) {
            weuiMsg('info', $loginCheck['msg']);
            return;
        }
        $core = $loginCheck['data'];
        //======验证登录======

        $getPayExtendList = getPayExtendList();
        $pay_arr = getPayList();

        foreach ($getPayExtendList as $kv => $v){
            $support = array();
            foreach ($v['support'] as $k => $s){
                if(array_key_exists($s,$pay_arr)){
                    $support[$s]['name'] = $pay_arr[$s]['name'];
                    $support[$s]['icon'] = $pay_arr[$s]['icon'];
                }else{
                    $support[$s]['name'] = "未知";
                    $support[$s]['icon'] = "unknown.png";
                }
            }
            $getPayExtendList[$kv]['support'] = $support;
        }

        $this->assign('getPayExtendList', $getPayExtendList);  //支付插件列表
        $this->assign('title', "支付插件");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/pay_plug_in');  //进入模板
    }
}
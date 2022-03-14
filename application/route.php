<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;

return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '/'=>'index/index/index',  //首页
    "install/"=>'install/install/index',  //安装
    "/:name"=>'index/index/:name',  //公共页面
    'cashier/:code'=>'index/cashier/cashier',  //收银台页面
    'submit/'=>'pay/submit/submit',  //创建订单（发起支付）
    "submit/:name"=>'pay/:name/submit',  //支付页面
    "api/:name"=>'api/api/:name',  //接口页面
    "notify"=>'pay/NotifyUrl/NotifyUrl',  //异步回调
    "return"=>'pay/ReturnUrl/ReturnUrl',  //同步回调
    "is_order"=>'pay/submit/IsOrder',  //判断订单是否支付
    "refund"=>'pay/Refund/Refund',  //交易退款

    "admin/"=>'admin/index/index',  //管理员后台首页
    "admin/:name"=>'admin/:name/Index',  //管理员后台
    "admin/:name/:fun"=>'admin/:name/:fun',  //管理员后台 - 方法
    "user/"=>'user/index/index',  //商户后台首页
    "user/:name"=>'user/:name/Index',  //商户员后台
    "user/:name/:fun"=>'user/:name/:fun',  //商户员后台 - 方法

    "login/:name"=>'login/:name/index',  //快捷登录
    "login/:name/:fun"=>'login/:name/:fun',  //快捷登录
];

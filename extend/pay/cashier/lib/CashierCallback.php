<?php
// +----------------------------------------------------------------------
// | 星益云 [ Remain true to our original aspiration ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018-至今 http://www.96xy.cn/ All rights reserved.
// +----------------------------------------------------------------------
// | 星益云聚合收银台系统 - 回调通知处理类
// +----------------------------------------------------------------------

require_once("CashierFunction.php");
class CashierCallback {
    var $config;

    public function __construct($config){
        $this->config = $config;
    }
    public function verifyNotify(){
        if(empty($_GET)) {//判断POST来的数组是否为空
            return false;
        }
        $CashierFunction = new CashierFunction;
        $arr = $CashierFunction->check($_GET, true, $this->config);
        if($arr['code']!=0){
            return false;
        }
        return true;
    }
}
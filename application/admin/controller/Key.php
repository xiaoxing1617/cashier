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
class Key extends Controller
{
    public function Index()
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

        $request = Request::instance();
        $domain = $request->domain();

        $this->assign('domain', $domain);  //地址
        $this->assign('title', "监控列表");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/key');  //进入模板
    }
    public function setKey(Request $request){
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] != 0) {
            return json_encode(['code' => 0, 'msg' => $loginCheck['msg']]);
        }
        $core = $loginCheck['data'];
        //======验证登录======
        if (!$request->isPost()) {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']);
        }
        $postArray = $request->post();


        $key = md5(date('YmdHis', time()) . substr(microtime(), 2, 6) . sprintf('%03d', rand(0, 999)));
        $temp = new Core;
        $arr = [
            "key"  =>$key,
        ];
        $i=0;
        foreach ($arr as $k=>$v){
            $num = $temp->where('name',$k)->update(['value1' => $v]);
            if($num==1){
                $i++;
            }
        }
        if(count($arr)==$i){
            return json_encode(['code' => 0, 'msg' => "重置成功，请尽快修改监控地址"]);
        }else{
            return json_encode(['code' => 1, 'msg' => "重置失败"]);
        }
    }
}
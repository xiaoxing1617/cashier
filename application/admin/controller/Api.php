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
class Api extends Controller
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


        $this->assign('title', "API配置");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/api');  //进入模板
    }

    public function setAppCode(Request $request)
    {
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
        if (!array_key_exists("thinkapi_appcode", $postArray)) {
            return json_encode(['code' => 1, 'msg' => '请转入code值']);
        }

        //修改
        $temp = new Core;
        $arr = [
            "thinkapi_appcode" => trim($postArray['thinkapi_appcode']),
        ];
        $i = 0;
        foreach ($arr as $k => $v) {
            $num = $temp->where('name', $k)->update(['value1' => $v]);
            if ($num == 1) {
                $i++;
            }
        }

        return json_encode(['code' => 0, 'msg' => "保存成功"]);
    }

    public function setApiSwitch(Request $request)
    {
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
        $validate = new Validate([
            ['text_review', 'number|between:0,1', '请正确选择文本审核API开关|非法的文本审核API']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }


        //修改
        $temp = new Core;
        $arr = [
            "text_review" => $postArray['text_review'],
        ];
        $i = 0;
        foreach ($arr as $k => $v) {
            $num = $temp->where('name', $k)->update(['value1' => $v]);
            if ($num == 1) {
                $i++;
            }
        }

        return json_encode(['code' => 0, 'msg' => "保存成功"]);
    }
}
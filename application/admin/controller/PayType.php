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
class PayType extends Controller
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

        $pay_list = getPayList();
        $this->assign('pay_list', $pay_list);  //支付方式列表
        $this->assign('title', "支付方式");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/pay_type');  //进入模板
    }
    //操作
    public function oper(Request $request)
    {
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] == -1) {
            return json_encode(['code' => 1, 'msg' => $loginCheck['msg'], "url" => $loginCheck['url']]);
        }
        if ($loginCheck['code'] != 0) {
            return json_encode(['code' => 1, 'msg' => $loginCheck['msg']]);
        }
        //======验证登录======
        $user = $loginCheck['data'];  //获取商户信息

        if (!$request->isPost()) {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']);
        }
        $postArray = $request->post();

        $validate = new Validate([
            ['mode', 'require', '请先选择操作类型'],
            ['alias', 'require', '请选择要操作的支付方式']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }

        $pay_list = getPayList();
        foreach ($pay_list as $v) {
            $pay_array[] = $v['alias'];
        }
        if (!in_array($postArray['alias'], $pay_array)) {
            return exit(json_encode(['code' => 1, 'msg' => '不存在此支付方式']));
        }
        $json_string = file_get_contents("./static/common/json/pay_list.json");  //读取
        $data = json_decode($json_string,true);  //JSON转成数组
        foreach ($data as $key=>$v){
            if($v['alias'] ==$postArray['alias']){
                $pay_alias_key = $key;
            }
        }
        switch ($postArray['mode']){
            case "switch_false":
                //修改
                $data[$pay_alias_key]["switch"]=false;
                $json_strings = json_encode($data);  //数组转JSON
                file_put_contents("./static/common/json/pay_list.json",$json_strings);  //写入
                return json_encode(['code' => 0, 'msg' => "关闭成功"]);
                break;
            case "switch_true":
                //修改
                $data[$pay_alias_key]["switch"]=true;
                $json_strings = json_encode($data);  //数组转JSON
                file_put_contents("./static/common/json/pay_list.json",$json_strings);  //写入
                return json_encode(['code' => 0, 'msg' => "开启成功"]);
                break;
            default:
                return json_encode(['code' => 1, 'msg' => "不支持该操作类型"]);
        }
    }
}
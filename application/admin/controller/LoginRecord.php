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
class LoginRecord extends Controller
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
        $uid = NULL;
        $getArray = $request->get();
        if (array_key_exists("uid", $getArray)) {
            if (trim($getArray['uid']) != "") {
                $uid = $getArray['uid'];
            }
        }

        $this->assign('uid', $uid);  //UID

        $this->assign('title', "登录记录");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/login');  //进入模板
    }
    public function getList(Request $request)
    {
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] != 0) {
            return exit(json_encode(['code' => 1, 'msg' => $loginCheck['msg']]));
        }

        $core = $loginCheck['data'];
        //======验证登录======
        if (!$request->isPost()) {
            return exit(json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']));
        }
        $postArray = $request->post();

        if (!array_key_exists("mode", $postArray)) {
            $mode = "all";
        } else {
            $mode = $postArray['mode'];
        }
        if (!array_key_exists("value", $postArray)) {
            $mode = "all";
        } else {
            if (trim($postArray['value']) == "" or $postArray['value'] == NULL) {
                $mode = "all";
            } else {
                $value = $postArray['value'];
            }
        }

        if (!array_key_exists("type", $postArray)) {
            $type="all";
        }else{
            $type = $postArray['type'];
            if(trim($type)=="all"){
                $type="all";
            }
        }
        if(array_key_exists("limit",$postArray)){
            $num = $postArray['limit'];  //每次显示数量
        }else{
            $num = 15;  //每次显示数量
        }
        if(array_key_exists("page",$postArray)){
            $page = $postArray['page'];  //第几页
        }else{
            $page = 1;  //第几页
        }
        if ($page <= 0) {
            $page = 1;
        }
        switch ($mode) {
            case "all":
                $sql_data = Login::where("id", "<>", "0");
                $sql_count = Login::where("id", "<>", "0");
                break;
            case "ip":
                $sql_data = Login::where("id", "<>", "0")->where("ip",$value);
                $sql_count = Login::where("id", "<>", "0")->where("ip",$value);
                break;
            case "uid":
                if($value=="管理员登录" or $value=="admin"){
                    $value = "admin";
                }
                $sql_data = Login::where("id", "<>", "0")->where("uid",$value);
                $sql_count = Login::where("id", "<>", "0")->where("uid",$value);
                break;
            default:
                return exit(json_encode(['code' => 1, 'msg' => '不支持该查询类型']));
        }
        if($type!="all"){
            $count = $sql_count->where("mode", $type)->count();
            $data = $sql_data->where("mode", $type)->order('id desc')->page($page, $num)->select();
        }else{
            $count = $sql_count->count();
            $data = $sql_data->order('id desc')->page($page, $num)->select();
        }

        return exit(json_encode(['code' => 0, 'msg' => '成功', "data" => $data]));
    }
}
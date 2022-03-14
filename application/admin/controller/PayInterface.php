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
class PayInterface extends Controller
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

        $this->assign('pay_list', $pay_list);  //支付方式
        $this->assign('title', "支付接口");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/pay_interface');  //进入模板
    }
    public function getList(Request $request){
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
            }else{
                $pay_list = getPayList();
                foreach ($pay_list as $v) {
                    $pay_array[] = $v['alias'];
                }
                if (!in_array($type, $pay_array)) {
                    return exit(json_encode(['code' => 1, 'msg' => '不存在此支付方式']));
                }
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
                $sql_data = Pay::where("id", "<>", "0");
                $sql_count = Pay::where("id", "<>", "0");
                break;
            case "name":
                $sql_count = Pay::where("id", "<>", "0")->where("name","like","%".$value."%");
                $sql_data = Pay::where("id", "<>", "0")->where("name","like","%".$value."%");
                break;
            case "uid":
                $sql_count = Pay::where("id", "<>", "0")->where("uid",$value);
                $sql_data = Pay::where("id", "<>", "0")->where("uid",$value);
                break;
            default:
                return exit(json_encode(['code' => 1, 'msg' => '不支持该查询类型']));
        }
        if($type!="all"){
            $count = $sql_count->where("type", $type)->order('id desc')->count();
            $data = $sql_data->where("type", $type)->order('id desc')->page($page, $num)->select();
        }else{
            $count = $sql_count->count();
            $data = $sql_data->order('id desc')->page($page, $num)->select();
        }

        $pay_arr = getPayList();
        $pay_extend = getPayExtendList();
        foreach ($data as $v) {
            $vtype = $v['type'];
            if(array_key_exists($vtype,$pay_arr)){
                $v['type'] = $pay_arr[$vtype]['name'];
                $v['type_icon'] = $pay_arr[$vtype]['icon'];
            }else{
                $v['type'] = "未知";
                $v['type_icon'] = "unknown.png";
            }
            $vplug_in = $v['plug_in'];
            if(array_key_exists($vplug_in,$pay_extend)){
                $v['plug_in'] = $pay_extend[$vplug_in]['name'];
            }else{
                $v['plug_in'] = "未知";
            }
        }
        return exit(json_encode(['code' => 0, 'msg' => '成功', "data" => $data,"count"=>$count]));
    }
    public function oper(Request $request)
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
            ['mode', 'require', '请选择操作类型'],
            ['id', 'require', '请选择接口ID']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }

        $pay = Pay::getById($postArray['id']);
        if(!$pay){
            return json_encode(['code' => 1, 'msg' => "接口不存在或已被删除"]);
        }
        switch ($postArray['mode']){
            case "state_0":
                $pay->state = "0";
                if (!$pay->save()) {
                    return json_encode(['code' => 1, 'msg' => "关闭失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "关闭成功"]);
                }
                break;
            case "state_1":
                $pay->state = "1";
                if (!$pay->save()) {
                    return json_encode(['code' => 1, 'msg' => "开启失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "开启成功"]);
                }
                break;
            case "del":
                if($pay->delete()){
                    return json_encode(['code' => 0, 'msg' => "删除成功"]);
                } else {
                    return json_encode(['code' => 1, 'msg' => "删除失败"]);
                }
                break;
            default:
                return json_encode(['code' => 1, 'msg' => "不支持该操作类型"]);
        }
    }
}
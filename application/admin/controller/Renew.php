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
class Renew extends Controller
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


        $this->assign('title', "续费记录");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/renew');  //进入模板
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
        $sql_data = Order::where("r.id", "<>", "0")->alias("r")
            ->join("xy_cashier_service s","s.service_trade_no=r.service_trade_no")
            ->where("r.state","<>","0")
            ->order('s.id desc');
        $sql_count = Order::where("r.id", "<>", "0")->alias("r")
            ->join("xy_cashier_service s","s.service_trade_no=r.service_trade_no")
            ->where("r.state","<>","0")
            ->order('s.id desc');
        switch ($mode) {
            case "all":
                $count = $sql_count->count();
                $data = $sql_data->page($page, $num)->select();
                break;
            case "uid":
                $count = $sql_count->where("s.uid",$value)->count();
                $data = $sql_data->where("s.uid",$value)->page($page, $num)->select();
                break;
            case "service_trade_no":
                $count = $sql_count->where("s.service_trade_no",$value)->count();
                $data = $sql_data->where("s.service_trade_no",$value)->page($page, $num)->select();
                break;
            case "out_trade_no":
                $count = $sql_count->where("r.out_trade_no",$value)->count();
                $data = $sql_data->where("r.out_trade_no",$value)->page($page, $num)->select();
                break;
            case "month":
                $count = $sql_count->where("s.month",$value)->count();
                $data = $sql_data->where("s.month",$value)->page($page, $num)->select();
                break;
            default:
                return exit(json_encode(['code' => 1, 'msg' => '不支持该查询类型']));
        }
        $pay_arr = getPayList();
        foreach ($data as $v) {
            $vtype = $v['type'];
            if(array_key_exists($vtype,$pay_arr)){
                $v['type'] = $pay_arr[$vtype]['name'];
                $v['type_icon'] = $pay_arr[$vtype]['icon'];
            }else{
                $v['type'] = "未知";
                $v['type_icon'] = "unknown.png";
            }
        }
        return exit(json_encode(['code' => 0, 'msg' => '成功', "data" => $data,"count"=>$count]));
    }
}
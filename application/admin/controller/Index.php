<?php

namespace app\admin\controller;
use think\Request;
use think\Controller;
use think\Db;
use think\Route;
use think\Url;

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
class Index extends Controller
{
    public function loginCheck(){
        if (!isLogin('admin')) {
            return array("code"=>-1,"msg"=>"请登录","url"=>"../login/?mode=admin");
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
        $core['ADMIN_BOTTOM'] = ADMIN_BOTTOM;
        return array("code"=>0,"msg"=>"成功","data"=>$core);
    }

    public function Index(){
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if($loginCheck['code']==-1){
            exit("<script language='javascript'>window.location.href='".$loginCheck['url']."';</script>");
        }
        if($loginCheck['code']!=0){
            weuiMsg('info', $loginCheck['msg']);
            return;
        }
        $core = $loginCheck['data'];
        //======验证登录======

        $this->assign('title', "后台首页");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/index');  //进入模板
    }
    public function home(){
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if($loginCheck['code']==-1){
            weuiMsg('info', "请先登录");
            return;
        }
        if($loginCheck['code']!=0){
            weuiMsg('info', $loginCheck['msg']);
            return;
        }
        $core = $loginCheck['data'];
        //======验证登录======

        $mysqlinfo = Db::query("select VERSION()");
        $order = new Order;
        $user = new User;
        $pay = new Pay;

        $today_income = number_format(round($order->where(
            [
                "state"=>["<>","0"]
            ]
        )->where('payment_time', 'between', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])->field('(sum(money) - sum(refund_money)) as money')->find()['money'],2),2,".","");
        $yesterday_income = number_format(round($order->where(
                [
                    "state"=>["<>","0"]
                ]
            )->where("refund_money","<=",0)->where('payment_time', 'between', [date('Y-m-d 00:00:00',strtotime("-1 day")), date('Y-m-d 23:59:59',strtotime("-1 day"))])->sum('money'),2),2,".","");
        $total_income = number_format(round($order::where(
                [
                    "state"=>["<>","0"]
                ]
            )->field('(sum(money) - sum(refund_money)) as money')->find()['money'],2),2,".","");

        $today_user = $user->where('creation_time', 'between', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])->count();
        $yesterday_user = $user->where('creation_time', 'between', [date('Y-m-d 00:00:00',strtotime("-1 day")), date('Y-m-d 23:59:59',strtotime("-1 day"))])->count();

        $last_month_income = number_format(round($order->where(
                [
                    "state"=>["<>","0"]
                ]
            )->where('payment_time', 'between', [date('Y-m-01 00:00:00',strtotime("-1 month")),date('Y-m-t 23:59:59',strtotime("-1 month"))])->field('(sum(money) - sum(refund_money)) as money')->find()['money'],2),2,".","");
        $this_month_income = number_format(round($order->where(
                [
                    "state"=>["<>","0"]
                ]
            )->where('payment_time', 'between', [date('Y-m-01 00:00:00'),date('Y-m-t 23:59:59')])->field('(sum(money) - sum(refund_money)) as money')->find()['money'],2),2,".","");
        $data = [
            'VERSION'=>VERSION,
            'BUILD' =>BUILD,
            'php_uname'=>php_uname(),
            'php_uname_s'=>php_uname('s'),
            'php_sapi_name'=>php_sapi_name(),
            'PHP_VERSION'=>PHP_VERSION,
            'mysql_VERSION'=>$mysqlinfo[0]['VERSION()'],
            'max_execution_time'=>get_cfg_var("max_execution_time")."s",
            'mysql_max_links'=>@get_cfg_var("mysql.max_links") == -1 ? "不限" : @get_cfg_var("mysql.max_links"),
            'mysql_allow_persistent'=>@get_cfg_var("mysql.allow_persistent")?"支持持续连接 ":"不支持持续连接",
            "order_count"=>$order->where("state","<>","0")->count(),
            "user_count"=>$user->count(),
            'today_income' => $today_income,
            'yesterday_income'=>$yesterday_income,
            'total_income'=>$total_income,
            'pay_count'=>$pay->count(),
            'pay_kai'=>$pay->where("state","1")->count(),
            'pay_guan'=>$pay->where("state","0")->count(),
            'today_user'=>$today_user,
            'yesterday_user'=>$yesterday_user,
            'user_state0'=>$user->where('state',"<>","1")->count(),
            'user_state1'=>$user->where('state',"1")->count(),
            'last_month_income'=>$last_month_income,
            'this_month_income'=>$this_month_income
        ];

        //更新
        if(!$curl_json = curl_get("http://auth.96xy.cn/api/cashier?mode=getnews&limit=6")){
            weuiMsg('info', "授权中心服务器异常");
            return;
        }
        if(!$curl_arr = json_decode($curl_json,true)){
            weuiMsg('info', "解析数据错误");
            return;
        }
        if($curl_arr['code']!=0){
            weuiMsg('info', $curl_arr['msg']);
            return;
        }else{
            if(count($curl_arr['list'])<=0){
                $data['news']['empty'] = true;
                $data['news']['list'] = [];
            }else{
                $data['news']['empty'] = false;
                $data['news']['list'] = $curl_arr['list'];
            }
        }

        $login = Login::where("uid", "admin")->order("id desc")->find();
        if (!$login) {
            $login['mode'] = "未知";
            $login['time'] = "你还未登录过！";
            $login['ip'] = "";
        } else {
            $login['mode'] = ($login['mode'] == 1) ? "手机" : "电脑";
        }

        $this->assign('login', $login);  //上次登录
        $this->assign('data', $data);  //标题
        $this->assign('title', "后台首页");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/home');  //进入模板
    }
}
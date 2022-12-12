<?php

namespace app\user\controller;

use think\Request;
use think\Controller;
use think\Validate;
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
class Bill extends Controller
{
    public function Index()
    {

        $this->assign('nav_active', "bill");  //nav名称
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] == -1) {
            exit("<script language='javascript'>window.location.href='" . $loginCheck['url'] . "';</script>");
        }
        if ($loginCheck['code'] != 0) {
            weuiMsg('info', $loginCheck['msg']);
            return;
        }
        //======验证登录======
        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
        $user = $loginCheck['data'];  //获取商户信息
        $ad = $loginCheck['ad'];  //广告
        $this->assign('ad', $ad);  //广告

        //=====数据统计=====
        $data = array();
        $pay_interface_array = $user->getPayInterfaceArray();
        //
        $data['total_income'] = number_format(round(Order::where(
            [
                "uid" => ["=", $user['uid']],
                "state" => ["<>", "0"]
            ]
        )->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
        //今日收入
        $data['today_income'] = number_format(round(Order::where(
            [
                "uid" => ["=", $user['uid']],
                "state" => ["<>", "0"]
            ]
        )->where('payment_time', 'between', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
        //昨日收入
        $data['yesterday_income'] = number_format(round(Order::where(
            [
                "uid" => ["=", $user['uid']],
                "state" => ["<>", "0"]
            ]
        )->where('payment_time', 'between', [date('Y-m-d 00:00:00', strtotime("-1 day")), date('Y-m-d 23:59:59', strtotime("-1 day"))])->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
        //上月收入
        $data['last_month_income'] = number_format(round(Order::where(
            [
                "uid" => ["=", $user['uid']],
                "state" => ["<>", "0"]
            ]
        )->where('payment_time', 'between', [date('Y-m-01 00:00:00', strtotime("-1 month")), date('Y-m-t 23:59:59', strtotime("-1 month"))])->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
        //本月收入
        $data['this_month_income'] = number_format(round(Order::where(
            [
                "uid" => ["=", $user['uid']],
                "state" => ["<>", "0"]
            ]
        )->where('payment_time', 'between', [date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59')])->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
        $this->assign('data', $data);  //总收入
        $data = [];
        foreach ($pay_interface_array as $key => $inter) {
            //总收入
            $data[$key]['total_income'] = number_format(round(Order::where(
                [
                    "uid" => ["=", $user['uid']],
                    "state" => ["<>", "0"],
                    "type" => ["=", $key]
                ]
            )->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
            //今日收入
            $data[$key]['today_income'] = number_format(round(Order::where(
                [
                    "uid" => ["=", $user['uid']],
                    "state" => ["<>", "0"],
                    "type" => ["=", $key]
                ]
            )->where('payment_time', 'between', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
            //昨日收入
            $data[$key]['yesterday_income'] = number_format(round(Order::where(
                [
                    "uid" => ["=", $user['uid']],
                    "state" => ["<>", "0"],
                    "type" => ["=", $key]
                ]
            )->where('payment_time', 'between', [date('Y-m-d 00:00:00', strtotime("-1 day")), date('Y-m-d 23:59:59', strtotime("-1 day"))])->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
            //上月收入
            $data[$key]['last_month_income'] = number_format(round(Order::where(
                [
                    "uid" => ["=", $user['uid']],
                    "state" => ["<>", "0"],
                    "type" => ["=", $key]
                ]
            )->where('payment_time', 'between', [date('Y-m-01 00:00:00', strtotime("-1 month")), date('Y-m-t 23:59:59', strtotime("-1 month"))])->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");
            //本月收入
            $data[$key]['this_month_income'] = number_format(round(Order::where(
                [
                    "uid" => ["=", $user['uid']],
                    "state" => ["<>", "0"],
                    "type" => ["=", $key]
                ]
            )->where('payment_time', 'between', [date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59')])->field('(sum(money) - sum(refund_money)) as money')->find()['money'], 2), 2, ".", "");

            $data[$key]['name'] = getPayList($key, "alias")['name'];
        }
        $this->assign('income', $data);  //支付方式收入

        $state = Order::getStateName(null, true);
        $source = Order::getSourceName(null, true);
        $pay_list = getPayList();

        $this->assign('state', $state);  //支付状态
        $this->assign('pay_list', $pay_list);  //支付状态

        $this->assign('source', $source);  //订单来源

        $user['qq_nickname'] = getQQnickname($user['qq']);
        $this->assign('title', "我的账单");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/bill');  //进入模板
    }

    //获取商户账单
    public function getBill(Request $request)
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
            ['mode', 'require', '请传入获取类型'],
            ['page', 'number', '页数不正确']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }

        $num = intval($postArray['num']);  //每次显示数量
        if($num<=0 || $num>24){
            $num = 10;
        }
        $page = $postArray['page'];
        if ($page <= 0) {
            $page = 1;
        }
        $validate = new Validate([
            ['value', 'require', '查询内容不能为空']
        ]);
        if (!$validate->check($postArray)) {
            $postArray['mode'] = "all";
        }else{
            $value = $postArray['value'];
        }
        $pay_list = getPayList();
        $pay_type_count = [];
        switch ($postArray['mode']) {
            case "all":
                //全部
                $sql_data = Order::where("uid", $user['uid']);
                $sql_count = Order::where("uid", $user['uid']);
                break;
            case "third_trade_no":
                //第三方订单号
                $sql_data = Order::where("uid", $user['uid'])
                    ->where("third_trade_no", $value);
                $sql_count = Order::where("uid", $user['uid'])
                    ->where("out_trade_no", $value);
                break;
            case "out_trade_no":
                //系统订单号
                $sql_data = Order::where("uid", $user['uid'])
                    ->where("out_trade_no", $value);
                $sql_count = Order::where("uid", $user['uid'])
                    ->where("out_trade_no", $value);
                break;
            case "api_trade_no":
                //商户订单号
                $sql_data = Order::where("uid", $user['uid'])
                    ->where("api_trade_no", $value);
                $sql_count = Order::where("uid", $user['uid'])
                    ->where("api_trade_no", $value);
                break;
            case "name":
                //订单名称
                $sql_data = Order::where("uid", $user['uid'])
                    ->where("name", 'like', "%" . $value . "%");
                $sql_count = Order::where("uid", $user['uid'])
                    ->where("name", 'like', "%" . $value . "%");
                break;
            case "fixed_amount_id":
                //固额码ID
                $sql_data = Order::where("uid", $user['uid'])
                    ->where("faid", $value);
                $sql_count = Order::where("uid", $user['uid'])
                    ->where("faid", $value);
                    //支付方式
                    foreach ($pay_list as $v){
                        $pay_type_count[$v['alias']] = Order::where("uid", $user['uid'])
                            ->where("faid", $value)->where("state", "<>", "0")->where("type",$v['alias'])->count()?:0;
                    }

                break;
            case "pname":
                $pay = Pay::where("uid", $user['uid'])
                    ->where("name", 'like', "%" . $value . "%")
                    ->column("id");
                if (!$pay) {
                    return json_encode(['code' => 1, 'msg' => "未找到相关接口名称"]);
                }
                //接口名称
                $sql_data = Order::where("uid", $user['uid'])
                    ->where("pid", 'in', join(",", $pay));
                $sql_count = Order::where("uid", $user['uid'])
                    ->where("pid", 'in', join(",", $pay));
                break;
            case "time":
                $validate = new Validate([
                    ['value1', 'require|date', '请填写起始时间|起始时间格式不正确'],
                    ['value2', 'require|date', '请填写结束时间|结束时间格式不正确']
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                $value1 = $postArray['value1'];
                $value2 = $postArray['value2'];
                //指定时间段
                $sql_count = Order::where("uid", $user['uid'])
                    ->page($page, $num)
                    ->where('payment_time', 'between', [$value1, $value2]);
                $count = Order::where("uid", $user['uid'])
                    ->where('payment_time', 'between', [$value1, $value2])
                    ->where("state", "<>", "0")
                    ->count();
                break;
            default:
                return json_encode(['code' => 1, 'msg' => "不支持该获取方式"]);
        }
        $pay_arr = getPayList();
        //支付时间
        if(array_key_exists('time_start',$postArray) && array_key_exists('time_end',$postArray)){
            if($postArray['time_start']!="" && $postArray['time_end']!=""){
                $validate = new Validate([
                    ['time_start', 'date', '起始时间格式不正确'],
                    ['time_end', 'date', '结束时间格式不正确']
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                $sql_count->where('payment_time', 'between', [$postArray['time_start'], $postArray['time_end']]);
                $sql_data->where('payment_time', 'between', [$postArray['time_start'], $postArray['time_end']]);
            }
        }
        //支付方式
        if(array_key_exists('type',$postArray)){
            $type = $postArray['type'];
            if (trim($type) != "all") {
                foreach ($pay_list as $v) {
                    $pay_array[] = $v['alias'];
                }
                if (!in_array($type, $pay_array)) {
                    return json_encode(['code' => 1, 'msg' => '不存在此支付方式']);
                }
                $sql_count->where('type', $type);
                $sql_data->where('type', $type);
            }
        }
        //订单状态
        if(array_key_exists('state',$postArray)) {
            if (trim($postArray['state']) != "all") {
                $state_arr = Order::getStateName(null, true);
                $state = $postArray['state'];
                if (!isset($state_arr[$state]) && $state != "all") {
                    return json_encode(['code' => 1, 'msg' => '不存在此订单状态']);
                }
                $sql_count->where('state', $state);
                $sql_data->where('state', $state);
            }
        }
        //订单来源
        if(array_key_exists('source',$postArray)) {
            if (trim($postArray['source']) != "all") {
                $source_arr = Order::getSourceName(null, true);
                $source = $postArray['source'];
                if (!isset($source_arr[$source]) && $source != "all") {
                    return json_encode(['code' => 1, 'msg' => '不存在此订单来源']);
                }
                $sql_count->where('source', $source);
                $sql_data->where('source', $source);
            }
        }
        $count = $sql_count->where("state", "<>", "0")->count()?:0;


        $list = $sql_data->where("state", "<>", "0")->order('id desc')->page($page, $num)->select();
        foreach ($list as $v) {
            $v['state_name'] = $v->getStateName();
            $vtype = $v['type'];
            if (array_key_exists($vtype, $pay_arr)) {
                $v['type'] = $pay_arr[$vtype]['name'];
                $v['type_icon'] = $pay_arr[$vtype]['icon'];
                $v['type_color'] = $pay_arr[$vtype]['color'];
            } else {
                $v['type'] = "未知";
                $v['type_icon'] = "unknown.png";
                $v['type_color'] = "#6e00ff";
            }
            $v['trade_type'] = $v->getTradeTypeName();
            $v['source_name'] = $v->getSourceName();
            $pname = Pay::getById($v['pid']);
            if (!$pname) {
                $v['pid'] = "未知";
            } else {
                $v['pid'] = $pname['name'];
            }
            if ($v['remarks'] == null or trim($v['remarks']) == "") {
                $v['remarks'] = null;
            }
        }

        $total_page = ceil($count / $num);
        return json_encode(['code' => 0, 'msg' => "成功", "list" => $list, "page" => $page, "total_page" => $total_page, "count" =>$count ,"current_page_count"=>count($list),"pay_type_count"=>$pay_type_count]);
    }

    //重新异步请求（单个）
    public function again_notify_single(Request $request)
    {
        //======验证管理员登录======
        $adminLoginCheck = new \app\admin\controller\Index;
        $loginCheck = $adminLoginCheck->loginCheck();
        if ($loginCheck['code'] == 0) {
            //管理员
            $is_admin = true;
        } else {
            //不是管理员
            $is_admin = false;
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
        }
        //======验证管理员登录======
        if (!$request->isPost()) {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']);
        }
        $postArray = $request->post();

        if (array_key_exists('out_trade_no', $postArray)) {
            $order = Order::where("out_trade_no", $postArray['out_trade_no']);
        } else if (array_key_exists('api_trade_no', $postArray)) {
            $order = Order::where("api_trade_no", $postArray['api_trade_no']);
        } else if (array_key_exists('third_trade_no', $postArray)) {
            $order = Order::where("third_trade_no", $postArray['third_trade_no']);
        } else {
            return json_encode(['code' => 1, 'msg' => '请至少传入out_trade_no、api_trade_no、third_trade_no任意一项']);
        }
        if ($is_admin) {
            $data = $order->where("source", "api")->find();
        } else {
            $data = $order->where("source", "api")->where("uid", $user['uid'])->find();
        }
        if (!$data) {
            return json_encode(['code' => 1, 'msg' => '该订单不存在']);
        }
        if ($data['state'] == "0") {
            return json_encode(['code' => 1, 'msg' => '订单未支付']);
        }
        if ($data['state'] == "1") {
            $trade_status = "TRADE_SUCCESS";  //支付成功
        } else if ($data['state'] == "2") {
            $trade_status = "FULL_REFUND";  //全额退款
        } else if (time() - strtotime($data['expiration_time']) > 0) {
            $trade_status = "PAYMENT_TIMEOUT";  //付款超时
        } else {
            return json_encode(['code' => 1, 'msg' => '未知的订单状态']);
        }
        $url = creat_callback($data['notify_url'], $data, $trade_status);
        if (do_notify($url)) {
            //通知成功
            return json_encode(['code' => 0, 'msg' => '异步通知成功']);
        } else {
            return json_encode(['code' => 1, 'msg' => '异步通知失败']);
        }
    }

}

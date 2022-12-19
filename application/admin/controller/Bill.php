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
class Bill extends Controller
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
        $out_trade_no = NULL;
        if (array_key_exists("out_trade_no", $getArray)) {
            if (trim($getArray['out_trade_no']) != "") {
                $out_trade_no = $getArray['out_trade_no'];
            }
        }

        $state = Order::getStateName(null, true);
        $source = Order::getSourceName(null, true);
        $pay_list = getPayList();

        $this->assign('state', $state);  //支付状态
        $this->assign('source', $source);  //订单来源
        $this->assign('uid', $uid);  //商户UID
        $this->assign('out_trade_no', $out_trade_no);  //系统订单号
        $this->assign('search_value', $out_trade_no);  //查询内容
        $this->assign('pay_list', $pay_list);  //支付方式
        $this->assign('title', "交易记录");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/bill');  //进入模板
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
        if (!array_key_exists("state", $postArray)) {
            $state = "all";
        } else {
            if (trim($postArray['state']) == "" or $postArray['state'] == NULL) {
                $state = "all";
            } else {
                $state_arr = Order::getStateName(null, true);
                $state = $postArray['state'];
                    if (!isset($state_arr[$state]) && $state!="all") {
                        return exit(json_encode(['code' => 1, 'msg' => '不存在此订单状态']));
                    }

            }
        }
        if (!array_key_exists("type", $postArray)) {
            $type = "all";
        } else {
            $type = $postArray['type'];
            if (trim($type) == "all") {
                $type = "all";
            } else {
                $pay_list = getPayList();
                foreach ($pay_list as $v) {
                    $pay_array[] = $v['alias'];
                }
                if (!in_array($type, $pay_array)) {
                    return exit(json_encode(['code' => 1, 'msg' => '不存在此支付方式']));
                }
            }
        }
        if (!array_key_exists("uid", $postArray)) {
            $uid = "all";
        } else {
            $uid = $postArray['uid'];
            if (trim($uid) == "") {
                $uid = "all";
            } else {
                $user = User::getByUid($uid);
                if (!$user) {
                    return exit(json_encode(['code' => 1, 'msg' => '该商户（UID：' . $uid . '）不存在或已被删除']));
                }
            }
        }
        if (array_key_exists("limit", $postArray)) {
            $num = $postArray['limit'];  //每次显示数量
        } else {
            $num = 15;  //每次显示数量
        }
        if (array_key_exists("page", $postArray)) {
            $page = $postArray['page'];  //第几页
        } else {
            $page = 1;  //第几页
        }
        if ($page <= 0) {
            $page = 1;
        }
        switch ($mode) {
            case "all":
                $sql_data = Order::where("id", "<>", "0");
                $sql_count = Order::where("id", "<>", "0");
                break;
            case "name":
                $sql_data = Order::where("id", "<>", "0")->where("name", "like", "%" . $value . "%");
                $sql_count = Order::where("id", "<>", "0")->where("name", "like", "%" . $value . "%");
                break;
            case "out_trade_no":
                $sql_data = Order::where("id", "<>", "0")->where("out_trade_no", $value);
                $sql_count = Order::where("id", "<>", "0")->where("out_trade_no", $value);
                break;
            case "api_trade_no":
                $sql_data = Order::where("id", "<>", "0")->where("api_trade_no", $value);
                $sql_count = Order::where("id", "<>", "0")->where("api_trade_no", $value);
                break;
            case "money":
                if (number_format(round($value, 2), 2, ".", "") <= 0 || !is_numeric($value) || !preg_match('/^[0-9.]+$/', $value)) {
                    return exit(json_encode(['code' => 1, 'msg' => '支付金额格式不正确']));
                }
                $value = number_format(round($value, 2), 2, ".", "");
                $sql_data = Order::where("id", "<>", "0")->where("money", $value);
                $sql_count = Order::where("id", "<>", "0")->where("money", $value);
                break;
            default:
                return exit(json_encode(['code' => 1, 'msg' => '不支持该查询类型']));
        }
        if ($type != "all" && $uid != "all") {
            if($state != "all"){
                $count = $sql_count->where("type", $type)->where("uid", $uid)->where("state", $state)->count();
                $data = $sql_data->where("type", $type)->where("uid", $uid)->where("state", $state)->order('id desc')->page($page, $num)->select();
            }else{
                $count = $sql_count->where("type", $type)->where("uid", $uid)->count();
                $data = $sql_data->where("type", $type)->where("uid", $uid)->order('id desc')->page($page, $num)->select();
            }
        } else {
            if ($type != "all") {
                if($state != "all"){
                    $count = $sql_count->where("type", $type)->where("state", $state)->count();
                    $data = $sql_data->where("type", $type)->where("state", $state)->order('id desc')->page($page, $num)->select();
                }else{
                    $count = $sql_count->where("type", $type)->count();
                    $data = $sql_data->where("type", $type)->order('id desc')->page($page, $num)->select();
                }
            } else if ($uid != "all") {
                if($state != "all"){
                    $count = $sql_count->where("uid", $uid)->where("uid", $uid)->where("state", $state)->count();
                    $data = $sql_data->where("uid", $uid)->order('id desc')->where("state", $state)->page($page, $num)->select();
                }else{
                    $count = $sql_count->where("uid", $uid)->where("uid", $uid)->count();
                    $data = $sql_data->where("uid", $uid)->order('id desc')->page($page, $num)->select();
                }
            } else {
                if($state != "all"){
                    $count = $sql_count->where("state", $state)->count();
                    $data = $sql_data->order('id desc')->where("state", $state)->page($page, $num)->select();
                }else{
                    $count = $sql_count->count();
                    $data = $sql_data->order('id desc')->page($page, $num)->select();
                }
            }
        }
        $pay_arr = getPayList();
        foreach ($data as $v) {
            if ($v['remarks'] == null or trim($v['remarks']) == "") {
                $v['remarks'] = null;
            }
            $vtype = $v['type'];
            if (array_key_exists($vtype, $pay_arr)) {
                $v['type'] = $pay_arr[$vtype]['name'];
                $v['type_icon'] = $pay_arr[$vtype]['icon'];
            } else {
                $v['type'] = "未知";
                $v['type_icon'] = "unknown.png";
            }
            $pname = Pay::getById($v['pid']);
            if (!$pname) {
                $v['pid'] = "未知";
            } else {
                $v['pid'] = $pname['name'];
            }
            $v['trade_type'] = $v->getTradeTypeName();
            $v['source_name'] = $v->getSourceName();
            $v['state_name'] = $v->getStateName($v['state']);
        }
        return exit(json_encode(['code' => 0, 'msg' => '成功', "data" => $data, "count" => $count]));
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
            ['id', 'require', '请选择交易订单']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $id_arr = explode(",", $postArray['id']);
        if (count($id_arr) <= 0) {
            return json_encode(['code' => 1, 'msg' => "至少操作一条数据"]);
        }
        foreach ($id_arr as $id) {
            $order = Order::getById($id);
            if (!$order) {
                return json_encode(['code' => 1, 'msg' => "【系统订单号：" . $order['out_trade_no'] . "】的交易不存在或已被删除"]);
            }
        }
        $order = new Order;
        $order->where("id", "in", $postArray['id']);

        switch ($postArray['mode']) {
            case "del":
                $validate = new Validate([
                    ['security_password', 'require|number|length:6,6', "请输入安全密码|请正确输入安全密码|请正确输入安全密码"]
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                if (md5($postArray['security_password']) != $core['security_password']) {
                    return json_encode(array("code" => 1, "msg" => "安全密码不正确"));
                }
                if (!$order->delete()) {
                    return json_encode(['code' => 1, 'msg' => "删除失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "删除成功"]);
                }
                break;
            default:
                return json_encode(['code' => 1, 'msg' => '不支持此操作类型']);
        }
    }

}

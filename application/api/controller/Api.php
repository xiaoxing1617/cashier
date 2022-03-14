<?php


namespace app\api\controller;

use think\Request;
use think\Controller;
use think\Db;
use think\Route;
use think\Url;
use think\Validate;
use think\Session;

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
use CashierFunction;
require_once PAY_PATH . "cashier/lib/CashierFunction.php";
class Api extends Controller
{
    /*
     * ==============================
     * 查询订单信息
     * ==============================
     */
    public function query_order(Request $request)
    {
        if ($request->isGet()) {
            $paraArray = $request->get();
        } else if ($request->isPost()) {
            $paraArray = $request->post();
        } else {
            return json_encode(['code' => 1000, 'msg' => '不支持此类型的请求方式']);
        }
        if (array_key_exists('uid', $paraArray)) {
            $user = User::getByUid($paraArray['uid']);
        } else {
            $user = [];
        }
        $CashierFunction = new CashierFunction;
        $check = $CashierFunction->check($paraArray, true, $user);
        if ($check['code'] != 0) {
            return json_encode($check);
        }
        //==========开始
        if (array_key_exists('out_trade_no', $paraArray)) {
            $out_trade_no = $paraArray['out_trade_no'];
            $order = Order::getByOutTradeNo($out_trade_no)->where("uid", $user['uid'])->find();
        } elseif (array_key_exists('api_trade_no', $paraArray)) {
            $api_trade_no = $paraArray['api_trade_no'];
            $order = Order::getByApiTradeNo($api_trade_no)->where("uid", $user['uid'])->find();
        } else {
            return json_encode(['code' => 1, 'msg' => 'out_trade_no或api_trade_no至少传入一个！']);
        }
        if (!$order) {
            return json_encode(['code' => 1, 'msg' => '订单不存在']);
        }

        $arr = [
            'uid' => $user['uid'],  //商户UID
            'out_trade_no' => $order['out_trade_no'],  //系统订单号
            'api_trade_no' => $order['api_trade_no'],  //接口订单号
            'type' => $order['type'],  //支付方式
            'money' => number_format(round(intval($order['money']), 2), 2, ".", ""),  //支付金额
            'name' => $order['name'],  //支付商品名称
            'creation_time' => $order['creation_time'],  //订单创建时间
            'payment_time' => $order['payment_time'],  //订单支付时间
            'remarks' => $order['remarks'],  //订单备注
            'refund_money' => number_format(round(intval($order['refund_money']), 2), 2, ".", ""),  //退款金额
            'refund_time' => $order['refund_time'],  //退款时间
            'ip' => $order['ip'],  //IP地址
            'buyer' => $order['buyer'],  //用户标识
            'state' => $order['state']  //订单状态
        ];
        return json_encode(['code' => 0, 'msg' => '成功', 'data' => $arr]);
    }

    /*
     * ==============================
     * 查询收款码图片
     * ==============================
     */
    public function query_code(Request $request)
    {
        if ($request->isGet()) {
            $paraArray = $request->get();
        } else if ($request->isPost()) {
            $paraArray = $request->post();
        } else {
            weuiMsg('info', '不支持此类型的请求方式', "code:1000", false);
            return;
        }
        if (!array_key_exists('mode', $paraArray)) {
            weuiMsg('info', '请指定获取模式', "code:1", false);
            return;
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
        $template = 'simpleBlue';
        if (array_key_exists('template', $paraArray)) {
            $template = $paraArray['template'];
        }
        if ($paraArray['mode'] == "third_web") {
            //========第三方获取
            if (array_key_exists('uid', $paraArray)) {
                $user = User::getByUid($paraArray['uid']);
                $CashierFunction = new CashierFunction;
                $check = $CashierFunction->check($paraArray, true, $user);
                if ($check['code'] != 0) {
                    weuiMsg('info', $check['msg'], "code:" . $check['code']);
                    return;
                }
            } else {
                weuiMsg('info', "商户UID或密钥错误", "code:1001");
                return;
            }
        } elseif ($paraArray['mode'] == "demo_web") {
            if ($core['demo_pay'] == "0") {
                weuiMsg('info', '演示收款功能已关闭', 'code:1');
                return;
            }
            $user = User::getByUid($core['demo_uid']);  //获取商户信息
            if (!$user) {
                weuiMsg('warn-primary', '收款商户号错误或不存在', 'code:1');
                return;
            }
            $template = $core['demo_pay'];
        } elseif ($paraArray['mode'] == "this_web") {
            //========当前登录的商户
            $controller = new \app\user\controller\Index;
            $loginCheck = $controller->loginCheck();
            if ($loginCheck['code'] == -1) {
                weuiMsg('info', "请登录", "code:1");
                return;
            }
            if ($loginCheck['code'] != 0) {
                weuiMsg('info', $loginCheck['msg'], "code:1");
                return;
            }
            $user = $loginCheck['data'];  //获取商户信息
        } else {
            weuiMsg('info', '不支持该获取模式', "code:1", false);
            return;
        }
        //==========开始
        $type = 'ordinary';  //默认
        if (array_key_exists('type', $paraArray)) {
            $type = $paraArray['type'];
        }
        $payment_code_list = getPaymentCodeList();
        foreach ($payment_code_list as $value) {
            $payment_code_array[] = $value['alias'];
        }
        if (!in_array($template, $payment_code_array)) {
            weuiMsg('info', '不存在该收款码模板', "code:1");
            return;
        }
        $code = getPaymentCodeList($template, 'alias');
        $name = mb_substr($user['nickname'], 0, $code['recNameNum']);
        if (mb_strlen($user['nickname']) > mb_strlen($name) && $code['recNameNum'] > 0) {
            $name .= "...";
        }
        $url = $request->domain() . '/cashier/' . $user['code'];
        switch ($type) {
            case "ordinary":
                //普通


                break;
            default:
                weuiMsg('info', '不支持该收款码类型', "code:1");
                return;
        }
        $pay_list = getPayList();
        $pay_names = "";
        foreach ($pay_list as $value) {
            if ($value['switch']) {
                $pay_names .= $value['client_name'] . '、';
            }
        }
        $pay_names = substr($pay_names, 0, -3);

        $this->assign('pay_names', $pay_names);  //客户端
        $code['circular_portrait'] = json_encode($code['circular_portrait']);
        $this->assign('user', $user);  //商户信息
        $this->assign('name', $name);  //商户名称
        $this->assign('code', $code);  //收款码配置
        $this->assign('title', '收款码 - ' . $code['name']);  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('url', $request->domain() . '/cashier/' . $user['code']);  //输出变量
        return $this->fetch('/query_code');  //进入模板
    }
}
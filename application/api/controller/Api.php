<?php


namespace app\api\controller;
//引入FixedAmount模型
use app\model\model\FixedAmount as FixedAmountModel;
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
        $url = "";
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
                $url = $request->domain() . '/cashier/' . $user['code'];
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
            $url = $request->domain() . '/cashier/' . $user['code'];
        } elseif ($paraArray['mode'] == "this_web") {
            //========当前登录的商户
            $controller = new \app\user\controller\Index;
            $loginCheck = $controller->loginCheck();
            if ($loginCheck['code'] == -1) {
                weuiMsg('info', "请先登录账号", "code:1");
                return;
            }
            if ($loginCheck['code'] != 0) {
                weuiMsg('info', $loginCheck['msg'], "code:1");
                return;
            }
            $user = $loginCheck['data'];  //获取商户信息
            $url = $request->domain() . '/cashier/' . $user['code'];
        }elseif($paraArray['mode'] == "fixed_amount"){
            //========当前登录的商户
            $controller = new \app\user\controller\Index;
            $loginCheck = $controller->loginCheck();
            if ($loginCheck['code'] == -1) {
                weuiMsg('info', "请先登录账号", "code:1");
                return;
            }
            if ($loginCheck['code'] != 0) {
                weuiMsg('info', $loginCheck['msg'], "code:1");
                return;
            }
            $user = $loginCheck['data'];  //获取商户信息
            $fixed_amount_id = intval($paraArray['id']);
            if($fixed_amount_id<=0){
                weuiMsg('info', "请传入固额码的ID", "code:1");
                return;
            }
            $fixed_amount_data = FixedAmountModel::where("id",$fixed_amount_id)->where("uid",$user['uid'])->find();
            if(!$fixed_amount_data){
                weuiMsg('info', "当前固额码不存在或已被删除", "code:1");
                return;
            }
            $url = $request->domain() . '/cashier/' . $fixed_amount_data['code'];
            $pay_arr = getPayList();
            $tmp_pay_type = $fixed_amount_data['pay_type'];
            $types = explode("|",$fixed_amount_data['pay_type']);
            $fixed_amount_data['pay_type'] = "";
            foreach ($types as $type){
                $fixed_amount_data['pay_type'] .= $pay_arr[$type]?$pay_arr[$type]['name']." | ":'未知 | ';
            }
            $fixed_amount_data['pay_type'] = rtrim($fixed_amount_data['pay_type'], "| ");
            $this->assign('fixed_amount_data', $fixed_amount_data);  //固额码数据
            $fixed_amount_data['pay_type'] = $tmp_pay_type;
        } else {
            weuiMsg('info', '不支持该获取模式', "code:1", false);
            return;
        }
        //==========模板校验并获取模板数据
        $payment_code_list = getPaymentCodeList();
        foreach ($payment_code_list as $value) {
            $payment_code_array[] = $value['alias'];
        }
        if (!in_array($template, $payment_code_array)) {
            weuiMsg('info', '不存在该收款码模板', "code:1");
            return;
        }
        $code = getPaymentCodeList($template, 'alias');
        //==========模板上的名称设置
        $code['texts'] = $this->textsFilter($code,$paraArray['mode'],$user,$fixed_amount_data);

        $pay_list = getPayList();
        $pay_names = "";
        foreach ($pay_list as $value) {
            if ($value['switch']) {
                $pay_names .= $value['client_name'] . '、';
            }
        }
        $pay_names = substr($pay_names, 0, -3);

        $this->assign('url', $url);  //地址
        $this->assign('mode', $paraArray['mode']);  //模式
        $this->assign('pay_names', $pay_names);  //客户端
        $code['circular_portrait'] = json_encode($code['circular_portrait']);
        $this->assign('user', $user);  //商户信息
        $this->assign('name', $user['nickname']);  //商户名称
        $this->assign('code', $code);  //收款码配置
        $this->assign('title',  $code['name']);  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('/query_code');  //进入模板
    }
    /**
     * 处理收款码的文本组
     */
    private function textsFilter($code,$mode,$user,$fixed_amount_data){
        if(!$code['texts']){
            //兼容老版本数据格式
            $UserName = mb_substr($user['nickname'], 0, intval($code['recNameNum']));
            if (mb_strlen($user['nickname']) > mb_strlen($UserName) && intval($code['recNameNum']) > 0) {
                $UserName .= "...";
            }
            $data[] = [
                "left"=>$code['recNameLeft'],
                "top"=>$code['recNameTop'],
                "color"=>$code['fontColor'],
                "font"=>$code['font'],
                "num"=>$code['recNameNum'],
                "txt"=>$UserName
            ];
            return json_encode($data);
        }

        $data = [];
        $pay_arr = getPayList();
        if($mode== "fixed_amount"){
            $FixedAmountMoney = "￥" . number_format(round(($fixed_amount_data['money'] / 100), 2), 2, ".", "")." ";
            $FixedAmountEndTime = $fixed_amount_data['end_time'];
            $types = explode("|",$fixed_amount_data['pay_type']);
            $PayTypeText = "";
            foreach ($types as $type){
                $PayTypeText .= $pay_arr[$type]?$pay_arr[$type]['name']." ":'';
            }
            $PayTypeText = trim($PayTypeText);
        }else{
            $FixedAmountEndTime = "";
            $FixedAmountMoney = "";
            $PayTypeText = "";
            foreach ($pay_arr as $item){
                $PayTypeText .= $item['name']." ";
            }
            $PayTypeText = trim($PayTypeText);
        }
        foreach ($code['texts'] as $item){
            if(isset($item['only_mode']) && !in_array($mode,$item['only_mode'])) {
                continue;
            }else if(isset($item['rule_out_mode']) && in_array($mode,$item['rule_out_mode'])){
                continue;
            }
            $txt = $item['txt'];
            $UserName = $user['nickname'];
            if(!isset($item['txt']) || empty($item['txt'])){
                $txt = $UserName;
            }

            //商户名称
            $txt = str_replace("__UserName__",$UserName,$txt);
            //固额码金额
            $txt = str_replace("__FixedAmountMoney__",$FixedAmountMoney,$txt);
            //固额码截止日期
            $txt = str_replace("__FixedAmountEndTime__",$FixedAmountEndTime,$txt);
            //支持的支付方式文本
            $txt = str_replace("__PayTypeText__",$PayTypeText,$txt);



            $tmp_txt = mb_substr($txt, 0, intval($item['num']));
            if (mb_strlen($txt) > mb_strlen($tmp_txt) && intval($item['num']) > 0) {
                $txt = $tmp_txt."...";
            }

            $item['txt'] = $txt;
            $data[] = $item;
        }
        return json_encode($data);
    }
}

<?php

namespace app\pay\controller;

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


class Refund extends Controller
{
    public function Submit(Request $request)
    {
        weuiMsg('waiting', '<script>setInterval(function (){window.location.href="/"},1000);</script>',"正在为您跳转到首页...",false);
        return;
    }
    /*
     * 交易退款 - 业务逻辑
     */
    public function Refund(Request $request)
    {
        if ($request->isGet()) {
            $paraArray = $request->get();
        } else if ($request->isPost()) {
            $paraArray = $request->post();
        } else {
            return json_encode(array("code" => 1, "msg" => "不支持此请求方式"));
        }
        if (!array_key_exists("way", $paraArray)) {
            return json_encode(array("code" => 1, "msg" => "请选择登录身份"));
        } else {
            if ($paraArray['way'] == "admin") {
                $adminLoginCheck = new \app\admin\controller\Index;
                //======验证管理员登录======
                $loginCheck = $adminLoginCheck->loginCheck();
                if ($loginCheck['code'] == -1) {
                    return json_encode(array("code" => 1, "msg" => '请先登录'));
                } else {
                    if ($loginCheck['code'] != 0) {
                        return json_encode(array("code" => 1, "msg" => $loginCheck['msg']));
                    }
                }
                //======验证管理员登录======
            } else if ($paraArray['way'] == "user") {
                //======验证商户登录======
                $userLoginCheck = new \app\user\controller\Index;
                $loginCheck = $userLoginCheck->loginCheck();
                if ($loginCheck['code'] == -1) {
                    return json_encode(array("code" => -1, "msg" => "请先登录"));
                }
                if ($loginCheck['code'] != 0) {
                    return json_encode(array("code" => -1, "msg" => $loginCheck['msg']));
                }
                $user = $loginCheck['data'];  //获取商户信息
                //======验证商户登录======
            } else {
                return json_encode(array("code" => 1, "msg" => "非法的登录身份"));
            }
        }
        //======验证管理员登录======
        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置

        $validate = new Validate([
            ['out_trade_no', 'require', '系统订单号(out_trade_no)不能为空'],
            ['security_password', 'require|number|length:6,6', "请输入安全密码|请正确输入安全密码|请正确输入安全密码"]
        ]);
        if (!$validate->check($paraArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $out_trade_no = trim($paraArray['out_trade_no']);  //系统订单号


        $md5 = md5($paraArray['security_password']);
        if ($paraArray['way'] == "admin") {
            //管理员登录
            if ($md5 != $core['security_password']) {
                return json_encode(array("code" => 1, "msg" => "安全密码不正确"));
            }
            $order = Order::where("out_trade_no", $out_trade_no)->find();
        } else {
            //用户登录
            if ($md5 != $user['security_password']) {
                return json_encode(array("code" => 1, "msg" => "安全密码不正确"));
            }
            $order = Order::where("out_trade_no", $out_trade_no)->where("uid", $user['uid'])->find();
        }
        if (!$order) {
            return json_encode(array("code" => 1, "msg" => "退款交易不存在"));
        }
        if ($order['state'] == "0") {
            return json_encode(array("code" => 1, "msg" => "交易未支付"));
        }


        if (!$order['refund_money'] or $order['refund_money'] == NULL or trim($order['refund_money']) == "") {
            $refund_money = 0;
        } else {
            $refund_money = $order['refund_money'];
        }
        if ($refund_money > 0) {
            return json_encode(array("code" => 1, "msg" => "抱歉，该交易已经全额退款过了。"));
        }
        $pay = Pay::where('id', $order['pid'])->find();
        if (!$pay) {
            return json_encode(array("code" => 1, "msg" => "订单所属支付接口不存在"));
        }
        switch ($pay['plug_in']) {
            case 'cashier':
                return json_encode(array("code" => 1, "msg" => "抱歉，聚合收银台接口暂不支持退款功能！"));
                break;
            case 'epay':
                return json_encode(array("code" => 1, "msg" => "抱歉，易支付接口暂不支持退款功能！"));
                break;
            case 'alipay':
                $obj = new Alipay;
                break;
            case 'wxpay':
                $obj = new Wxpay;
                break;
            case 'qqpay':
                $obj = new Qqpay;
                break;
            default:
                return "fail";
        }
        return $obj->Refund($paraArray, $order);
    }
}
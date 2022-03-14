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
class QrCode extends Controller
{
    public function Index()
    {
        $this->assign('nav_active', "qr_code");  //nav名称
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
        $user = $loginCheck['data'];  //获取商户信息
        $ad = $loginCheck['ad'];  //广告
        $this->assign('ad', $ad);  //广告

        //======验证登录======

        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
        $user["code_reset_time"] = ($user["code_reset_time"] != "" && $user["code_reset_time"] != null) ?$user["code_reset_time"]: "[暂未重置过]";

        $payment_code_arr = getPaymentCodeList();
        $user['qq_nickname'] = getQQnickname($user['qq']);

        $pay_list = getPayList();
        $pay_names = "";
        foreach ($pay_list as $value) {
            if($value['switch']){
                $pay_names .= $value['client_name'] . '、';
            }
        }
        $pay_names = substr($pay_names, 0, -3);

        $this->assign('pay_names', $pay_names);  //客户端
        $this->assign('payment_array', $payment_code_arr);  //模板列表
        $this->assign('title', "我的收款码");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/qr_code');  //进入模板
    }

    //重位收款码
    public function reset(Request $request)
    {
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
        $user = $loginCheck['data'];  //获取商户信息

        if (!$request->isPost()) {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']);
        }
        $postArray = $request->post();
        $validate = new Validate([
            ['only', 'require', '请先获取验证码'],
            ['captcha', 'require|max:6|min:6', '验证码不能为空|请正确填写验证码|请正确填写验证码']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $captcha = Captcha::where(['only' => $postArray['only'], 'code' => $postArray['captcha'], 'value1' => $user['email']])->find();
        if ($captcha) {
            if (strtotime($captcha['expiration_time']) - time() <= 0) {
                return json_encode(['code' => 1, 'msg' => "验证码过期，请重新获取"]);
            }
        } else {
            return json_encode(['code' => 1, 'msg' => "验证码错误或不存在，请重新获取"]);
        }
        $temp = User::get($user['id']);  //获取指定主键一条数据
        $temp->code = md5(createTradeNo() . $user['id']);
        $temp->code_reset_time = date('Y-m-d H:i:s');
        if (!$temp->save()) {
            return json_encode(['code' => 1, 'msg' => "收款码重置失败，请稍后重试！"]);
        } else {
            return json_encode(['code' => 0, 'msg' => "重置成功，请及时更换收款码！"]);
        }
    }

    //获取个人收款码信息
    public function getCodeInfo(Request $request)
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
        $paraArray = $request->post();
        if (!array_key_exists('alias', $paraArray)) {
            return json_encode(['code' => 1, 'msg' => '请选择收款码模板']);
        }

        $payment_code_list = getPaymentCodeList();
        foreach ($payment_code_list as $value) {
            $payment_code_array[] = $value['alias'];
        }
        if (!in_array($paraArray['alias'], $payment_code_array)) {
            return json_encode(['code' => 1, 'msg' => '不存在该收款码模板']);
        }
        $code = getPaymentCodeList($paraArray['alias'], 'alias');
        $name = mb_substr($user['nickname'], 0, $code['recNameNum']);
        if (mb_strlen($user['nickname']) > mb_strlen($name) && $code['recNameNum'] > 0) {
            $name .= "...";
        }
        $url = $request->domain() . '/cashier/' . $user['code'];

        return json_encode(['code' => 0, 'msg' => '成功', "name" => $name, "url" => $url, "info" => $code]);
    }
}
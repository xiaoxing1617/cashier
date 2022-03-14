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

class Info extends Controller
{
    public function Index()
    {
        $this->assign('nav_active', "info");  //nav名称
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
        $user['qq_nickname'] = getQQnickname($user['qq']);
        //======验证登录======
        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
        $cashier_arr = getTemplateList("cashier");
        $this->assign('cashier_arr', $cashier_arr);  //收银台模板列表
        $this->assign('title', "修改信息");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/info');  //进入模板
    }
    public function setInfo(Request $request){
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] == -1) {
            return json_encode(['code' => 1, 'msg' => $loginCheck['msg'],"url"=>$loginCheck['url']]);
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
            ['nickname', 'require|length:1,16|chsDash', '商户昵名称不能为空|商户昵名称最多设置16个字|商户名称只能是汉字、字母、数字和下划线(_)及破折号(-)'],
            ['qq', 'require|length:6,11|number', 'QQ号码不能为空|请正确填写QQ号码|请正确填写QQ号码'],
            ['weixin','alphaDash|length:6,20','请正确填写微信号|请正确填写微信号']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $temp = User::getById($user['id']);
        $temp->nickname = $postArray['nickname'];
        $temp->qq = $postArray['qq'];
        $temp->weixin = $postArray['weixin'];
        if($temp->save() !== false){
            return json_encode(['code' => 0, 'msg' => "修改成功"]);
        }else {
            return json_encode(['code' => 1, 'msg' => "修改失败"]);
        }

    }
    //重置KEY密钥
    public function reset_key(Request $request){
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] == -1) {
            return json_encode(['code' => 1, 'msg' => $loginCheck['msg'],"url"=>$loginCheck['url']]);
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
        $key = md5(createTradeNo() . $user['id']) . md5(rand(10000, 99999));
        $temp = User::get($user['id']);  //获取指定主键一条数据
        $temp->key = $key;
        if (!$temp->save()) {
            return json_encode(['code' => 1, 'msg' => "重置失败，请稍后重试！"]);
        } else {
            return json_encode(['code' => 0, 'msg' => "重置成功",'key'=>$key]);
        }
    }
}
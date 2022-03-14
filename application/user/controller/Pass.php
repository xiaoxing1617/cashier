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

class Pass extends Controller
{
    public function Index()
    {
        $this->assign('nav_active', "pass");  //nav名称
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
        $user['qq_nickname'] = getQQnickname($user['qq']);
        if($user['veloce_qq']==null or trim($user['veloce_qq'])==""){
            $user['veloce_qq'] = "未绑定";
        }else{
            $user['veloce_qq'] = substr($user['veloce_qq'] , 0 , 4)."******";
        }
        //======验证登录======
        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
        $ad = $loginCheck['ad'];  //广告
        $this->assign('ad', $ad);  //广告

        $this->assign('title', "账号安全");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/pass');  //进入模板
    }
    //修改密码
    public function setPass(Request $request){
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
            ['captcha', 'require|max:6|min:6', '验证码不能为空|请正确填写验证码|请正确填写验证码'],
            ['password', 'require|max:16|min:6|alphaNum', '密码不能为空|密码不能大于16个字符|密码不能小于6个字符|密码只能是字母和数字'],
            ['password_repeat', 'require', '请再次填写你的密码']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if ($postArray['password'] != $postArray['password_repeat']) {
            return json_encode(['code' => 1, 'msg' => '两次密码输入不一致']);
        }
        $captcha = Captcha::where(['only' => $postArray['only'], 'code' => $postArray['captcha'], 'value1' => $user['email']])->find();
        if ($captcha) {
            if (strtotime($captcha['expiration_time']) - time() <= 0) {
                return json_encode(['code' => 1, 'msg' => "验证码过期，请重新获取"]);
            }
        } else {
            return json_encode(['code' => 1, 'msg' => "验证码错误或不存在，请重新获取"]);
        }
        $temp = User::getById($user['id']);
        if(md5($postArray['password']) == $temp['password']){
            return json_encode(['code' => 1, 'msg' => "无需修改，新密码和旧密码一致"]);
        }
        $temp->password = md5($postArray['password']);
        if($temp->save()){
            return json_encode(['code' => 0, 'msg' => "修改成功","url"=>"../login?=user"]);
        }else {
            return json_encode(['code' => 1, 'msg' => "修改失败"]);
        }
    }

    //更改邮箱
    public function changeEmail(Request $request){
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
            ['only-jiu', 'require', '请先获取旧邮箱的验证码'],
            ['email', 'require|email', '电子邮箱不能为空|电子邮箱格式不正确'],
            ['only-xin', 'require', '请先获取新邮箱的验证码'],
            ['captcha_jiu', 'require|max:6|min:6', '旧邮箱验证码不能为空|请正确填写旧邮箱验证码|请正确填写旧邮箱验证码'],
            ['captcha_xin', 'require|max:6|min:6', '旧邮箱验证码不能为空|请正确填写新邮箱验证码|请正确填写新邮箱验证码']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if($postArray['email']==$user['email']){
            return json_encode(['code' => 1, 'msg' => '新邮箱不能和旧邮箱一致']);
        }
        //=====旧
        $captcha = Captcha::where(['only' => $postArray['only-jiu'], 'code' => $postArray['captcha_jiu'], 'value1' => $user['email']])->find();
        if ($captcha) {
            if (strtotime($captcha['expiration_time']) - time() <= 0) {
                return json_encode(['code' => 1, 'msg' => "旧邮箱的验证码过期，请重新获取"]);
            }
        } else {
            return json_encode(['code' => 1, 'msg' => "旧邮箱的验证码错误或不存在，请重新获取"]);
        }
        //=====新
        $captcha = Captcha::where(['only' => $postArray['only-xin'], 'code' => $postArray['captcha_xin'], 'value1' => $postArray['email']])->find();
        if ($captcha) {
            if (strtotime($captcha['expiration_time']) - time() <= 0) {
                return json_encode(['code' => 1, 'msg' => "新邮箱的验证码过期，请重新获取"]);
            }
        } else {
            return json_encode(['code' => 1, 'msg' => "新邮箱的验证码错误或不存在，请重新获取"]);
        }
        $temp = User::getById($user['id']);
        $temp->email = $postArray['email'];
        if($temp->save()){
            return json_encode(['code' => 0, 'msg' => "更改成功","url"=>""]);
        }else {
            return json_encode(['code' => 1, 'msg' => "更改失败"]);
        }

    }
    public function setAqmm(Request $request){
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
            ['jiu_aqmm', 'require|number|length:6,6', "请输入旧安全密码|请正确输入旧安全密码|请正确输入旧安全密码"],
            ['xin_aqmm', 'require|number|length:6,6', "请输入新安全密码|安全密码必须是6位数字|安全密码必须是6位数字"],
            ['zai_aqmm', 'require|number|length:6,6', "请再次输入安全密码|请正确再次输入安全密码|请正确再次输入安全密码"]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $jiu_aqmm = md5($postArray['jiu_aqmm']);
        if($jiu_aqmm!=$user['security_password']){
            return json_encode(['code' => 1, 'msg' => "旧安全密码输入不正确，无法修改！"]);
        }
        if(md5($postArray['xin_aqmm'])!=md5($postArray['zai_aqmm'])){
            return json_encode(['code' => 1, 'msg' => "两次安全密码输入不一致！"]);
        }
        $temp = User::getById($user['id']);
        if(md5($postArray['xin_aqmm']) == $temp['security_password']){
            return json_encode(['code' => 1, 'msg' => "无需修改，新安全密码和安全旧密码一致"]);
        }
        $temp->security_password = md5($postArray['xin_aqmm']);
        if($temp->save()){
            return json_encode(['code' => 0, 'msg' => "修改成功"]);
        }else {
            return json_encode(['code' => 1, 'msg' => "修改失败"]);
        }
    }
}
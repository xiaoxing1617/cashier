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
class Pass extends Controller
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


        $this->assign('title', "后台密码");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/pass');  //进入模板
    }
    public function setPass(Request $request)
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
            ['account', 'require|length:4,16|alphaNum', '后台账号不能为空|后台账号只能4-16个字符|后台账号只能是字母和数字'],
            ['jiu_pass', 'require', '请填写后台登录旧密码'],
            ['xin_pass', 'length:6,16|alphaNum', '后台密码不能为空|后台密码只能6-16个字符|后台密码只能是字母和数字']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $jiu_pass = md5($postArray['jiu_pass']);
        if($jiu_pass!=$core['admin_password']){
            return json_encode(['code' => 1, 'msg' => "旧密码输入不正确，无法修改！"]);
        }
        $url = null;
        $xin_pass = $core['admin_password'];
        if(array_key_exists('xin_pass',$postArray)) {
            if (trim($postArray['xin_pass'])!="") {
                if(!array_key_exists('zai_pass',$postArray)){
                    return json_encode(['code' => 1, 'msg' => "请再次输入新密码！"]);
                }
                $xin_pass = md5($postArray['xin_pass']);
                $url = "/admin/";
                if($xin_pass!=md5($postArray['zai_pass'])){
                    return json_encode(['code' => 1, 'msg' => "两次密码输入不一致！"]);
                }
            }
        }

        $temp = new Core;
        $arr = [
            "admin_account"  =>$postArray['account'],
            "admin_password"  =>$xin_pass,
        ];
        $i=0;
        foreach ($arr as $k=>$v){
            $num = $temp->where('name',$k)->update(['value1' => $v]);
            if($num==1){
                $i++;
            }
        }
        if(count($arr)!=$i){
            return json_encode(['code' => 0, 'msg' => "修改成功",'url'=>$url]);
        }else{
            return json_encode(['code' => 0, 'msg' => "成功修改".$i."条"]);
        }
    }

    public function setAqmm(Request $request)
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
            ['jiu_pass', 'require|number|length:6,6', "请输入旧安全密码|请正确输入旧安全密码|请正确输入旧安全密码"],
            ['xin_pass', 'require|number|length:6,6', "请输入新安全密码|安全密码必须是6位数字|安全密码必须是6位数字"],
            ['zai_pass', 'require|number|length:6,6', "请再次输入安全密码|请正确再次输入安全密码|请正确再次输入安全密码"]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $jiu_pass = md5($postArray['jiu_pass']);
        if($jiu_pass!=$core['security_password']){
            return json_encode(['code' => 1, 'msg' => "旧安全密码输入不正确，无法修改！"]);
        }
        $url = null;
        if(md5($postArray['xin_pass'])!=md5($postArray['zai_pass'])){
            return json_encode(['code' => 1, 'msg' => "两次安全密码输入不一致！"]);
        }

        if(md5($postArray['xin_pass'])==md5($core['security_password'])){
            return json_encode(['code' => 1, 'msg' => "新安全密码和旧安全密码相同，无需修改！"]);
        }

        $temp = new Core;
        $arr = [
            "security_password"  => md5($postArray['xin_pass'])
        ];
        $i=0;
        foreach ($arr as $k=>$v){
            $num = $temp->where('name',$k)->update(['value1' => $v]);
            if($num==1){
                $i++;
            }
        }
        if($i==1){
            return json_encode(['code' => 0, 'msg' => "更改成功",'url'=>$url]);
        }else{
            return json_encode(['code' => 1, 'msg' => "更改失败"]);
        }
    }

}
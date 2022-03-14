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
class Email extends Controller
{
    public function Index()
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

        $this->assign('title', "邮箱配置");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/email');  //进入模板
    }
    public function setEmail(Request $request)
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
            ['email_host','require','SMTP服务器地址不能为空'],
            ['email_user','require|email','邮箱用户名不能为空|错误的邮箱格式'],
            ['email_port','require','服务器端口不能为空'],
            ['email_pass','require','SMTP授权码']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }


        $temp = new Core;
        $arr = [
            "email_host"  =>$postArray['email_host'],
            "email_user"  =>$postArray['email_user'],
            "email_port"  =>$postArray['email_port'],
            "email_pass"  =>$postArray['email_pass'],
        ];
        $i=0;
        foreach ($arr as $k=>$v){
            $num = $temp->where('name',$k)->update(['value1' => $v]);
            if($num==1){
                $i++;
            }
        }
        if(count($arr)!=$i){
            return json_encode(['code' => 0, 'msg' => "修改成功"]);
        }else{
            return json_encode(['code' => 0, 'msg' => "成功修改".$i."条"]);
        }
    }
}
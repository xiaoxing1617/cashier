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
class Temp extends Controller
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

        $temp = array();
        //=====首页模板
        $arr = getTemplateList('index');
        $temp['index'] = $arr;
        //=====用户登录模板
        $arr = getTemplateList('user_login');
        $temp['user_login'] = $arr;
        //=====后台登录模板
        $arr = getTemplateList('admin_login');
        $temp['admin_login'] = $arr;
        //=====注册模板
        $arr = getTemplateList('register');
        $temp['register'] = $arr;
        //=====回调模板
        $arr = getTemplateList('return');
        $temp['return'] = $arr;

        $this->assign('temp', $temp);  //标题
        $this->assign('title', "模板管理");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/temp');  //进入模板
    }

    public function setTemp(Request $request)
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
            ['mode','require','请选择模板类型'],
            ['val','require','请选择模板']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        switch ($postArray['mode']){
            case "index":
                if (!isTemplateName('index', $postArray['val'])) {
                    return json_encode(['code' => 1, 'msg' => "该首页模板不存在或已被删除"]);
                }
                $name = "index_template";
                break;
            case "login_user":
                if (!isTemplateName('user_login', $postArray['val'])) {
                    return json_encode(['code' => 1, 'msg' => "该商户登录模板不存在或已被删除"]);
                }
                $name = "login_user_template";
                break;
            case "login_admin":
                if (!isTemplateName('admin_login', $postArray['val'])) {
                    return json_encode(['code' => 1, 'msg' => "该后台登录模板不存在或已被删除"]);
                }
                $name = "login_admin_template";
                break;
            case "register":
                if (!isTemplateName('register', $postArray['val'])) {
                    return json_encode(['code' => 1, 'msg' => "该商户注册模板不存在或已被删除"]);
                }
                $name = "register_template";
                break;
            case "return":
                if (!isTemplateName('return', $postArray['val'])) {
                    return json_encode(['code' => 1, 'msg' => "该支付回调模板不存在或已被删除"]);
                }
                $name = "return_template";
                break;
            case "user_theme_color":
                $user_theme_data_arr = json_decode($core['user_theme_data'],1)?:[];
                if(!isset($user_theme_data_arr['list'][$postArray['val']])){
                    return json_encode(['code' => 1, 'msg' => "该商户后台主题色不存在或已被删除"]);
                }
                $user_theme_data_arr['use'] = $postArray['val'];
                $postArray['val'] = json_encode($user_theme_data_arr);
                $name = "user_theme_data";
                break;
            default:
                return json_encode(['code' => 1, 'msg' => "不支持设置此模板类型"]);
        }
        $temp = Core::where('name',$name)->update(['value1' => $postArray['val']]);
        if($temp !== false){
            return json_encode(['code' => 0, 'msg' => "模板更改成功"]);
        }else{
            return json_encode(['code' => 1, 'msg' => "模板更改失败"]);
        }
    }
}

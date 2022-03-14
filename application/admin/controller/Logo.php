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
class Logo extends Controller
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
        $this->assign('title', "Logo上传");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/logo');  //进入模板
    }

    public function upload(Request $request)
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
        if (!array_key_exists("mode", $postArray)) {
            return json_encode(['code' => 1, 'msg' => '请选择要上传的类型']);
        }
        $file = $request->file('file');
        if (empty($file)) {
            return json_encode(['code' => 1, 'msg' => '请上传文件']);
        }
        switch ($postArray['mode']){
            case "ico":
                $ext = "ico";
                $size = 1024;
                $name = "favicon.ico";
                $validate = new Validate([
                    ['file','fileExt:'.$ext.'|fileSize:'.$size,'不支持该格式文件|最大支持上传'.$size.'字节的文件']
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                break;
            case "logo":
                $ext = "png";
                $size = 1024*5;
                $name = "logo.png";
                $validate = new Validate([
                    ['file','fileExt:'.$ext.'|fileSize:'.$size,'不支持该格式文件|最大支持上传'.$size.'字节的文件']
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                break;
            case "logo1":
                $ext = "png";
                $size = 1024*5;
                $name = "logo1.png";
                $validate = new Validate([
                    ['file','fileExt:'.$ext.'|fileSize:'.$size,'不支持该格式文件|最大支持上传'.$size.'字节的文件']
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                break;
            default:
                return json_encode(['code' => 1, 'msg' => '不支持该上传的类型']);
        }
        $info = $file->move(ROOT_PATH.'public/',$name);
        if ($info) {
            return json_encode(['code' => 0, 'msg' => "上传成功"]);
        } else {
            //上传失败获取错误信息
            return json_encode(['code' => 0, 'msg' => "上传失败：".$file->getError()]);
        }
    }
}
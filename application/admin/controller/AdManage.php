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
use app\model\model\Notice;

//引入Login模型Notice
use app\model\model\NoticeRecord;

//引入Login模型NoticeRecord
use app\model\model\Ad;

//引入Login模型Ad
class AdManage extends Controller
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

        $ad = new Ad();
        $ad_arr = $ad->getLevelName("","",true);
        $data = [];
        //商户后台头部横幅
        $ad = Ad::getByAlias("user_manage_top");
        if($ad){
           $data['user_manage_top']=[
             "state"=>$ad['state'],
               "data"=>json_decode($ad['data'],true),
               "time_start"=>$ad['time_start'],
               "time_end"=>$ad['time_end']
           ];
        }else{
            $data['user_manage_top']=[
                "state"=>0,
                "data"=>[],
                "time_start"=>"",
                "time_end"=>""
            ];
        }
        //商户后台底部横幅
        $ad = Ad::getByAlias("user_manage_bottom");
        if($ad){
            $data['user_manage_bottom']=[
                "state"=>$ad['state'],
                "data"=>json_decode($ad['data'],true),
                "time_start"=>$ad['time_start'],
                "time_end"=>$ad['time_end']
            ];
        }else{
            $data['user_manage_bottom']=[
                "state"=>0,
                "data"=>[],
                "time_start"=>"",
                "time_end"=>""
            ];
        }
        //剪切板内容
        $ad = Ad::getByAlias("payment_shear_plate");
        if($ad){
            $data['payment_shear_plate']=[
                "state"=>$ad['state'],
                "data"=>$ad['data'],
                "time_start"=>$ad['time_start'],
                "time_end"=>$ad['time_end']
            ];
        }else{
            $data['payment_shear_plate']=[
                "state"=>0,
                "data"=>"",
                "time_start"=>$ad['time_start'],
                "time_end"=>$ad['time_end']
            ];
        }
        //通知内容
        $ad = Ad::getByAlias("payment_popup_notified");
        if($ad){
            $data['payment_popup_notified']=[
                "state"=>$ad['state'],
                "data"=>$ad['data'],
                "time_start"=>$ad['time_start'],
                "time_end"=>$ad['time_end']
            ];
        }else{
            $data['payment_popup_notified']=[
                "state"=>0,
                "data"=>"",
                "time_start"=>"",
                "time_end"=>""
            ];
        }
        //支付完成底部横幅
        $ad = Ad::getByAlias("payment_bottom_banner");
        if($ad){
            $data['payment_bottom_banner']=[
                "state"=>$ad['state'],
                "data"=>json_decode($ad['data'],true),
                "time_start"=>$ad['time_start'],
                "time_end"=>$ad['time_end']
            ];
        }else{
            $data['payment_bottom_banner']=[
                "state"=>0,
                "data"=>[],
                "time_start"=>"",
                "time_end"=>""
            ];
        }

        $this->assign('data', $data);  //数据
        $this->assign('title', "广告管理");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/ad');  //进入模板
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
            case ($postArray['mode']=="user_manage_top" or $postArray['mode']=="user_manage_bottom" or $postArray['mode']=="payment_bottom_banner"):
                $ext = "jpg,png,gif";
                $type=['image/gif'=>'gif','image/jpeg'=>'jpg','image/png'=>'png'];
                $size = 1024*5;
                $validate = new Validate([
                    ['file','fileExt:'.$ext.'|fileSize:'.$size,'不支持该格式文件|最大支持上传'.$size.'字节的文件']
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                $file_type = $file->getInfo()['type'];
                if(!array_key_exists($file_type,$type)){
                    return json_encode(['code' => 1, 'msg' => '不支持该格式文件']);
                }
                $name = $postArray['mode'].".";
                break;
            default:
                return json_encode(['code' => 1, 'msg' => '不支持该上传的类型']);
        }
        $info = $file->move(ROOT_PATH.'public/static/ad-img',$name.$type[$file_type]);
        if ($info) {
            //删除
            foreach ($type as $k=>$v){
                if($file_type!=$k){
                    $path = ROOT_PATH.'public/static/ad-img/'.$name.$v;
                    if(file_exists($path)){
                        unlink($path);
                    }
                }
            }
            return json_encode(['code' => 0, 'msg' => "上传成功","name"=>$name.$type[$file_type]]);
        } else {
            //上传失败获取错误信息
            return json_encode(['code' => 0, 'msg' => "上传失败：".$file->getError()]);
        }
    }

    public function setUserManageTop(Request $request)
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
            ['switch', 'require|between:0,1', "请选择是否开启展示|请正确选择开启展示选项"],
            ['url', 'require|url', "请填写跳转网址|请正确填写跳转网址"],
            ['file_name', 'require', "未获取到图片名称"],
            ['time_start', 'require|date', "请设置起始时间|请正确设置起始时间"],
            ['time_end', 'require|date', "请设置结束时间|请正确设置结束时间"]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if(strtotime($postArray['time_start'])>strtotime($postArray['time_end'])){
            return json_encode(['code' => 1, 'msg' => "起始时间不能大于结束时间"]);
        }
        $path = ROOT_PATH.'public/static/ad-img/'.$postArray['file_name'];
        if(!file_exists($path)){
            return json_encode(['code' => 1, 'msg' => "图片不存在，无法更改"]);
        }
        $arr = [
            "url"=>$postArray['url'],
            "name"=>$postArray['file_name']
        ];

        $ad = Ad::getByAlias("user_manage_top");
        if(!$ad){
           $ad = new Ad;
        }
        $ad->top_level="bannerPicture";
        $ad->two_level="";
        $ad->alias = "user_manage_top";
        $ad->state = $postArray['switch'];
        $ad->time_start = $postArray['time_start'];
        $ad->time_end = $postArray['time_end'];
        $ad->data=json_encode($arr);
        if($ad->save() !== false){
            return json_encode(['code' => 0, 'msg' => "更改成功"]);
        }else {
            return json_encode(['code' => 1, 'msg' => "更改失败"]);
        }
    }

    public function setUserManageBottom(Request $request)
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
            ['switch', 'require|between:0,1', "请选择是否开启展示|请正确选择开启展示选项"],
            ['url', 'require|url', "请填写跳转网址|请正确填写跳转网址"],
            ['file_name', 'require', "未获取到图片名称"],
            ['time_start', 'require|date', "请设置起始时间|请正确设置起始时间"],
            ['time_end', 'require|date', "请设置结束时间|请正确设置结束时间"]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if(strtotime($postArray['time_start'])>strtotime($postArray['time_end'])){
            return json_encode(['code' => 1, 'msg' => "起始时间不能大于结束时间"]);
        }
        $path = ROOT_PATH.'public/static/ad-img/'.$postArray['file_name'];
        if(!file_exists($path)){
            return json_encode(['code' => 1, 'msg' => "图片不存在，无法更改"]);
        }
        $arr = [
            "url"=>$postArray['url'],
            "name"=>$postArray['file_name']
        ];

        $ad = Ad::getByAlias("user_manage_bottom");
        if(!$ad){
            $ad = new Ad;
        }
        $ad->top_level="bannerPicture";
        $ad->two_level="";
        $ad->alias = "user_manage_bottom";
        $ad->state = $postArray['switch'];
        $ad->time_start = $postArray['time_start'];
        $ad->time_end = $postArray['time_end'];
        $ad->data=json_encode($arr);
        if($ad->save() !== false){
            return json_encode(['code' => 0, 'msg' => "修改成功"]);
        }else {
            return json_encode(['code' => 1, 'msg' => "修改失败"]);
        }
    }
    public function setPaymentShearPlate(Request $request)
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
            ['switch', 'require|between:0,1', "请选择是否开启|请正确选择开启选项"],
            ['content', 'require', "请设置预选剪切板内容"],
            ['time_start', 'require|date', "请设置起始时间|请正确设置起始时间"],
            ['time_end', 'require|date', "请设置结束时间|请正确设置结束时间"]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if(strtotime($postArray['time_start'])>strtotime($postArray['time_end'])){
            return json_encode(['code' => 1, 'msg' => "起始时间不能大于结束时间"]);
        }
        $arr = explode("<;end>",$postArray['content']);
        $arr = array_filter($arr);
        if(count($arr)<=0){
            return json_encode(['code' => 1, 'msg' => "至少设置一个剪切板内容！"]);
        }
        $postArray['content'] = implode("<;end>",$arr);

        $ad = Ad::getByAlias("payment_shear_plate");
        if(!$ad){
            $ad = new Ad;
        }
        $ad->top_level="paymentSuccess";
        $ad->two_level="";
        $ad->alias = "payment_shear_plate";
        $ad->state = $postArray['switch'];
        $ad->data=$postArray['content'];
        $ad->time_start = $postArray['time_start'];
        $ad->time_end = $postArray['time_end'];
        if($ad->save() !== false){
            return json_encode(['code' => 0, 'msg' => "修改成功"]);
        }else {
            return json_encode(['code' => 1, 'msg' => "修改失败"]);
        }
    }
    public function setPaymentPopupNotified(Request $request)
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
            ['switch', 'require|between:0,1', "请选择是否开启|请正确选择开启选项"],
            ['time_start', 'require|date', "请设置起始时间|请正确设置起始时间"],
            ['time_end', 'require|date', "请设置结束时间|请正确设置结束时间"],
            ['content', 'require', "请设置广告内容"]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if(strtotime($postArray['time_start'])>strtotime($postArray['time_end'])){
            return json_encode(['code' => 1, 'msg' => "起始时间不能大于结束时间"]);
        }

        $ad = Ad::getByAlias("payment_popup_notified");
        if(!$ad){
            $ad = new Ad;
        }
        $ad->top_level="paymentSuccess";
        $ad->two_level="";
        $ad->alias = "payment_popup_notified";
        $ad->state = $postArray['switch'];
        $ad->data=$postArray['content'];
        $ad->time_start = $postArray['time_start'];
        $ad->time_end = $postArray['time_end'];
        if($ad->save() !== false){
            return json_encode(['code' => 0, 'msg' => "修改成功"]);
        }else {
            return json_encode(['code' => 1, 'msg' => "修改失败"]);
        }
    }
    public function setPaymentBottomBanner(Request $request)
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
            ['switch', 'require|between:0,1', "请选择是否开启展示|请正确选择开启展示选项"],
            ['url', 'require|url', "请填写跳转网址|请正确填写跳转网址"],
            ['file_name', 'require', "未获取到图片名称"],
            ['time_start', 'require|date', "请设置起始时间|请正确设置起始时间"],
            ['time_end', 'require|date', "请设置结束时间|请正确设置结束时间"]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if (strtotime($postArray['time_start']) > strtotime($postArray['time_end'])) {
            return json_encode(['code' => 1, 'msg' => "起始时间不能大于结束时间"]);
        }
        $path = ROOT_PATH . 'public/static/ad-img/' . $postArray['file_name'];
        if (!file_exists($path)) {
            return json_encode(['code' => 1, 'msg' => "图片不存在，无法更改"]);
        }
        $arr = [
            "url" => $postArray['url'],
            "name" => $postArray['file_name']
        ];

        $ad = Ad::getByAlias("payment_bottom_banner");
        if (!$ad) {
            $ad = new Ad;
        }
        $ad->top_level = "paymentSuccess";
        $ad->two_level = "";
        $ad->alias = "payment_bottom_banner";
        $ad->state = $postArray['switch'];
        $ad->time_start = $postArray['time_start'];
        $ad->time_end = $postArray['time_end'];
        $ad->data = json_encode($arr);
        if ($ad->save() !== false) {
            return json_encode(['code' => 0, 'msg' => "更改成功"]);
        } else {
            return json_encode(['code' => 1, 'msg' => "更改失败"]);
        }
    }
}
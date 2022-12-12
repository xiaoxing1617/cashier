<?php

namespace app\user\controller;

use think\Request;
use think\Controller;
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
use app\model\model\Login;

//引入Login模型
use app\model\model\Notice;

//引入Login模型Notice
use app\model\model\NoticeRecord;

//引入Login模型NoticeRecord
use app\model\model\Ad;

//引入Login模型Ad
class Index extends Controller
{
    public function loginCheck()
    {
        if (!isLogin('user')) {
            return array("code" => -1, "msg" => "请登录", "url" => "../login/?mode=user");
        }
        $user = User::where('uid', getUserUid())->find();  //获取商户信息
        if (!$user) {
            return array("code" => 1, "msg" => "商户不存在或被删除");
        }
        if ($user['state'] != 1) {
            return array("code" => 1, "msg" => "当前商户已被停封");
        }
        $data = [];
        //商户后台头部横幅
        $ad = Ad::where("alias", "user_manage_top")
            ->where("state", "1")
            ->where("time_start", "<", date('Y-m-d H:i:s'))
            ->where("time_end", ">", date('Y-m-d H:i:s'))
            ->find();
        if($ad){
            $data['user_manage_top']=[
                "state"=>$ad['state'],
                "data"=>json_decode($ad['data'],true)
            ];
        }else{
            $data['user_manage_top']=[
                "state"=>0,
                "data"=>[]
            ];
        }
        //商户后台底部横幅
        $ad = Ad::where("alias", "user_manage_bottom")
            ->where("state", "1")
            ->where("time_start", "<", date('Y-m-d H:i:s'))
            ->where("time_end", ">", date('Y-m-d H:i:s'))
            ->find();
        if($ad){
            $data['user_manage_bottom']=[
                "state"=>$ad['state'],
                "data"=>json_decode($ad['data'],true)
            ];
        }else{
            $data['user_manage_bottom']=[
                "state"=>0,
                "data"=>[]
            ];
        }
        return array("code" => 0, "msg" => "成功", "data" => $user,"ad"=>$data);
    }

    public function Index()
    {
        $this->assign('nav_active', "index");  //nav名称
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

        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
        $user = $loginCheck['data'];  //获取商户信息
        $ad = $loginCheck['ad'];  //广告
        $this->assign('ad', $ad);  //广告


        //=====数据统计=====
        $data = array();
        $pay_list = getPayList();

        $pay_interface_array = $user->getPayInterfaceArray();
        $merchantCheck = merchantCheck($user['id']);
        if ($merchantCheck['code'] != 0) {
            $user['expiration_time'] = "<span style='color: #f00'>" . $merchantCheck['msg'] . "</span>";
        } else {
            $user['expiration_time'] = "【正常】" . $merchantCheck['date'] . "到期";
        }

        foreach ($pay_interface_array as $key => $value) {
            $today_income = Order::where(
                [
                    "uid" => ["=", $user['uid']],
                    "state" => ["<>", "0"],
                    "type" => ["=", $key]
                ]
            )->where('payment_time', 'between', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])->field('(sum(money) - sum(refund_money)) as money')->find()['money'];

            $count = count($user->getPayInterfaceIdArray($key));
            $txt = ($value['switch'] == 0) ? "【接口已关闭】" : "【接口已开启】";
            $txt .= "已添加 " . $count . " 个接口，接口轮循方式：" . $user->getUseOrderName(null, $key);
            $state_0 = Pay::where("uid", $user['uid'])  //当前用户uid
            ->where('state', "0")  //接口状态
            ->where('type', $key)  //接口支付方式
            ->count();
            $txt .= "<br/>有 " . $state_0 . " 个接口已关闭，有 " . ($count - $state_0) . " 个接口已开启";

            $data['pay'][] = [
                "name" => getPayList($key, "alias")['name'],
                "count" => $count,
                "alias" => $key,
                "today_income" => number_format(round($today_income, 2), 2, ".", ""),
                "txt" => $txt,
            ];
        }
        $login = Login::where("uid", $user['uid'])->order("id desc")->find();
        if (!$login) {
            $login['mode'] = "未知";
            $login['time'] = "你还未登录过！";
            $login['ip'] = "";
        } else {
            $login['mode'] = ($login['mode'] == 1) ? "手机" : "电脑";
        }
        $this->assign('login', $login);  //登录数据

        $confirm_count = Notice::where("group", "user")
            ->where("show", 1)
            ->whereTime('release_time', '<', date('Y-m-d H:i:s'))
            ->where("confirm","1")
            ->count()-NoticeRecord::where("confirm","1")->where("uid", $user['uid'])->count();
        //公告获取
        $notice = Notice::where("group", "user")->where("show", 1)->whereTime('release_time', '<', date('Y-m-d H:i:s'))->limit(5)->order('id', 'desc')->select();

        $this->assign('confirm_count', $confirm_count);  //页面数据
        $data['notice'] = $notice;
        $this->assign('data', $data);  //页面数据
        $this->assign('title', "商户首页");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/index');  //进入模板
    }
    public function getNoticePopup(){
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


        $notice_data = Notice::where("group", "user")
            ->where("show", 1)
            ->whereTime('release_time', '<', date('Y-m-d H:i:s'))
            ->where("popup","1")
            ->find();
        if(!$notice_data){
            $notice_popup = false;
        }else{
            $notice_popup = true;
            $notice_record = NoticeRecord::where("nid", $notice_data['id'])
                ->where("uid", $user['uid'])
                ->find();
            if($notice_record){
                $notice_popup = false;
            }
        }
        if($notice_popup){
            return json_encode(['code' => 0, 'msg' => "有","data"=>$notice_data]);
        }else{
            return json_encode(['code' => 1, 'msg' => "无"]);
        }
    }
}

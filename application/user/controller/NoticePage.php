<?php

namespace app\user\controller;

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
use app\model\model\Notice;

//引入Login模型Notice
use app\model\model\NoticeRecord;

//引入Login模型NoticeRecord
class NoticePage extends Controller
{

    public function Index(Request $request)
    {
        $getArray = $request->get();
        $this->assign('nav_active', "notice_page");  //nav名称
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
        $count['browse']=0;
        $count['fabulous']=0;
        $count['negative']=0;

        if (array_key_exists("mod", $getArray) && array_key_exists("id", $getArray)) {
            $mod = $getArray['mod'];
            $id = $getArray['id'];
            $data = Notice::where("group", "user")
                ->where("id", $id)
                ->where("show", 1)
                ->whereTime('release_time', '<', date('Y-m-d H:i:s'))
                ->find();
            if (!$data) {
                $data['code'] = 1;
                $data['msg'] = "公告不存在或已被删除！";
            } else {
                $data['code'] = 0;
                if ($data['uid'] == "admin") {
                    $data['img'] = "http://q1.qlogo.cn/g?b=qq&nk=" . $core['qq'] . "&s=640";
                } else {
                    $temp = User::getByUid($data['uid']);
                    if (!$temp) {
                        $data['code'] = 1;
                        $data['msg'] = "发布者不存在或已注销账号！";
                    } else {
                        $data['img'] = "http://q1.qlogo.cn/g?b=qq&nk=" . $temp['qq'] . "&s=640";
                    }
                }
                $input = [
                    'uid' => $user['uid'],
                    'nid' => $data['id']
                ];
                $notice_record = NoticeRecord::where("nid", $data['id'])
                    ->where("uid", $user['uid'])
                    ->find();
                $count['fabulous'] = NoticeRecord::where("id", "<>", "0")
                    ->where("nid",$data['id'])
                    ->where("fabulous","1")
                    ->count();
                $count['negative'] = NoticeRecord::where("id", "<>", "0")
                    ->where("nid",$data['id'])
                    ->where("negative","1")
                    ->count();
                if(!$notice_record){
                    $insert = new NoticeRecord;
                    if ($insert->create($input)) {
                        $notice_record = NoticeRecord::where("nid", $data['id'])
                            ->where("uid", $user['uid'])
                            ->find();
                    } else {
                        weuiMsg('info', "公告读取失败，请稍后重试！");
                        return;
                    }
                }

                $count['browse'] = NoticeRecord::where("id", "<>", "0")
                    ->where("nid",$data['id'])
                    ->count();
                if ($data['confirm'] == '1') {
                    $data['confirm'] = true;
                    if ($notice_record['confirm'] == '1') {
                        $data['confirm'] = false;
                    }
                } else {
                    $data['confirm'] = false;
                }
                if($notice_record['fabulous'] == '0'){
                    $count['fabulous_switch'] = false;
                }else{
                    $count['fabulous_switch'] = true;
                }
                if($notice_record['negative'] == '0'){
                    $count['negative_switch'] = false;
                }else{
                    $count['negative_switch'] = true;
                }
            }
        } else {
            $data['code'] = 1;
            $data['msg'] = "";
            $mod = "";
        }

        $this->assign('count', $count);

        $this->assign('mod', $mod);
        $this->assign('data', $data);
        $user['qq_nickname'] = getQQnickname($user['qq']);
        $this->assign('title', "商户公告");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/notice_page');  //进入模板
    }

    public function getNotice(Request $request)
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
        $postArray = $request->post();
        $num = 15;  //每次显示数量
        $page = $postArray['page'];
        if ($page <= 0) {
            $page = 1;
        }
        switch ($postArray['mode']) {
            case "all":
                //全部
                $list = Notice::where("group", "user")
                    ->where("show", 1)
                    ->where("topping", 0)
                    ->whereTime('release_time', '<', date('Y-m-d H:i:s'))
                    ->page($page, $num)
                    ->order('id', 'desc')
                    ->select();
                $count = Notice::where("group", "user")
                    ->where("show", 1)
                    ->whereTime('release_time', '<', date('Y-m-d H:i:s'))
                    ->count();
                //置顶！！！
                $list_topping = Notice::where("group", "user")
                    ->where("show", 1)
                    ->where("topping", 1)
                    ->whereTime('release_time', '<', date('Y-m-d H:i:s'))
                    ->page($page, $num)
                    ->order('id', 'desc')
                    ->select();
                break;
            default:
                return json_encode(['code' => 1, 'msg' => "不支持该获取方式"]);
        }
        $list = array_merge($list_topping,$list);
        foreach ($list as $v){
            $notice_record = NoticeRecord::where("nid", $v['id'])
                ->where("uid", $user['uid'])
                ->find();
            if(!$notice_record){
                $v['read'] = false;
            }else{
                $v['read'] = true;
            }
            if($v['topping']!='1'){
                $v['topping'] = false;
            }else{
                $v['topping'] = true;
            }
            if ($v['confirm'] == '1') {
                if ($notice_record['confirm'] == '1') {
                    $v['confirm'] = 1;
                }else{
                    $v['confirm'] = 2;
                }
            } else {
                $v['confirm'] = 0;
            }
        }

        $total_page = ceil($count / $num);
        return json_encode(['code' => 0, 'msg' => "成功", "list" => $list, "page" => $page, "total_page" => $total_page, "count" => count($list)]);
    }
    public function setNotice(Request $request)
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
        $postArray = $request->post();
        $validate = new Validate([
            ['mode', 'require', '请选择操作类型'],
            ['nid', 'require', '请选择公告ID']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $mode = $postArray['mode'];
        $id = $postArray['nid'];
        $notice = Notice::where("group", "user")
            ->where("id", $id)
            ->where("show", 1)
            ->whereTime('release_time', '<', date('Y-m-d H:i:s'))
            ->find();
        if(!$notice){
            return json_encode(['code' => 1, 'msg' => "公告不存在或已被删除！","url"=>"/user/notice_page"]);
        }
        $notice_record = NoticeRecord::where("nid", $notice['id'])
            ->where("uid", $user['uid'])
            ->find();
        if(!$notice_record){
            return json_encode(['code' => 1, 'msg' => "异常操作，您还未读该公告","url"=>"/user/notice_page?mod=see&id=".$notice['id']]);
        }
        $url="";
        switch ($mode){
            case "fabulous":
                if($notice_record['fabulous']=='1'){
                    //取消赞
                    $notice_record['fabulous'] = "0";
                    $msg = "取消赞";
                }else{
                    //赞
                    $msg = "点赞";
                    if($notice_record['negative']=='1'){
                        //取消踩
                        $notice_record['negative'] = "0";
                    }
                    $notice_record['fabulous'] = "1";
                }
                break;
            case "negative":
                if($notice_record['negative']=='1'){
                    //取消踩
                    $msg = "取消差评";
                    $notice_record['negative'] = "0";
                }else{
                    //踩
                    $msg = "差评";
                    if($notice_record['fabulous']=='1'){
                        //取消赞
                        $notice_record['fabulous'] = "0";
                    }
                    $notice_record['negative'] = "1";
                }
                break;
            case "confirm":
                if($notice['confirm']=='1'){
                    $notice_record['confirm'] = "1";
                    $msg = "确认";
                    $url="/user/notice_page?mod=see&id=".$notice['id'];
                }else{
                    return json_encode(['code' => 1, 'msg' => '该公告无需确认']);
                }
                break;
            default:
                return json_encode(['code' => 1, 'msg' => '不支持此操作类型']);
        }
        if($notice_record->save() !== false){
            $count['browse'] = NoticeRecord::where("id", "<>", "0")
                ->where("nid",$notice['id'])
                ->count();
            $count['fabulous'] = NoticeRecord::where("id", "<>", "0")
                ->where("nid",$notice['id'])
                ->where("fabulous","1")
                ->count();
            $count['negative'] = NoticeRecord::where("id", "<>", "0")
                ->where("nid",$notice['id'])
                ->where("negative","1")
                ->count();
            if($notice_record['fabulous'] == '0'){
                $count['fabulous_switch'] = false;
            }else{
                $count['fabulous_switch'] = true;
            }
            if($notice_record['negative'] == '0'){
                $count['negative_switch'] = false;
            }else{
                $count['negative_switch'] = true;
            }
            return json_encode(['code' => 0, 'msg' => $msg."成功","count"=>$count,"url"=>$url]);
        }else {
            return json_encode(['code' => 1, 'msg' => $msg."失败"]);
        }
    }
}
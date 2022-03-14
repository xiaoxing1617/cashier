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
class NoticeManage extends Controller
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
        $getArray = $request->get();
        $mode = "";
        $notice = [];
        if (array_key_exists("mode", $getArray) && array_key_exists("id", $getArray)) {
            $mode = $getArray['mode'];
            if ($mode == "see" or $mode == "edit") {
                $notice = Notice::getById($getArray['id']);
                if (!$notice) {
                    weuiMsg('info', "公告不存在或已被删除");
                    return;
                }
                if ($mode == "see") {
                    $title = "查看公告";
                } elseif ($mode == "edit") {
                    $title = "编辑公告";
                }
            } elseif ($mode == "add") {
                $title = "发布新公告";
            } else {
                weuiMsg('info', "不支持该类型");
                return;
            }
        } else {
            $title = "公告管理";
        }
        $group = new Notice;
        $this->assign('group', $group->getGroupName("", true));  //公告类型
        $this->assign('notice', $notice);  //公告数据
        $this->assign('mode', $mode);  //类型
        $this->assign('title', $title);  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/notice_manage');  //进入模板
    }

    public function getList(Request $request)
    {
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] != 0) {
            return exit(json_encode(['code' => 1, 'msg' => $loginCheck['msg']]));
        }

        $core = $loginCheck['data'];
        //======验证登录======
        if (!$request->isPost()) {
            return exit(json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']));
        }
        $postArray = $request->post();

        if (!array_key_exists("mode", $postArray)) {
            $mode = "all";
        } else {
            $mode = $postArray['mode'];
        }
        if (!array_key_exists("value", $postArray)) {
            $mode = "all";
        } else {
            if (trim($postArray['value']) == "" or $postArray['value'] == NULL) {
                $mode = "all";
                $value = "";
            } else {
                $value = $postArray['value'];
            }
        }
        if (!array_key_exists("group", $postArray)) {
            $group = "all";
        } else {
            $group = $postArray['group'];
            if (trim($group) == "all") {
                $group = "all";
            } else {
                $temp = new Notice;
                if (!array_key_exists($group, $temp->getGroupName("", true))) {
                    return exit(json_encode(['code' => 1, 'msg' => '不存在此公告类型']));
                }
            }
        }
        if (!array_key_exists("uid", $postArray)) {
            $uid = "all";
        } else {
            $uid = $postArray['uid'];
            if (trim($uid) == "") {
                $uid = "all";
            } elseif (trim($uid) == "admin") {
                $uid = "admin";
            } else {
                $user = User::getByUid($uid);
                if (!$user) {
                    return exit(json_encode(['code' => 1, 'msg' => '该商户（UID：' . $uid . '）不存在或已被删除']));
                }
            }
        }
        if (array_key_exists("limit", $postArray)) {
            $num = $postArray['limit'];  //每次显示数量
        } else {
            $num = 15;  //每次显示数量
        }
        if (array_key_exists("page", $postArray)) {
            $page = $postArray['page'];  //第几页
        } else {
            $page = 1;  //第几页
        }
        if ($page <= 0) {
            $page = 1;
        }
        switch ($mode) {
            case "all":
                $sql_data = Notice::where("id", "<>", "0");
                $sql_count = Notice::where("id", "<>", "0");
                break;
            case "id":
                $sql_data = Notice::where("id", $value);
                $sql_count = Notice::where("id", $value);
                break;
            case "title":
                $sql_data = Notice::where("id", "<>", "0")->where("title", "like", "%" . $value . "%");
                $sql_count = Notice::where("id", "<>", "0")->where("title", "like", "%" . $value . "%");
                break;
            default:
                return exit(json_encode(['code' => 1, 'msg' => '不支持该查询类型']));
        }
        if ($group != "all" && $uid != "all") {
            $count = $sql_count->where("group", $group)->where("uid", $uid)->count();
            $data = $sql_data->where("group", $group)->where("uid", $uid)->order('id desc')->page($page, $num)->select();
        } else {
            if ($group != "all") {
                $count = $sql_count->where("group", $group)->count();
                $data = $sql_data->where("group", $group)->order('id desc')->page($page, $num)->select();
            } else if ($uid != "all") {
                $count = $sql_count->where("uid", $uid)->where("uid", $uid)->count();
                $data = $sql_data->where("uid", $uid)->order('id desc')->page($page, $num)->select();
            } else {
                $count = $sql_count->count();
                $data = $sql_data->order('id desc')->page($page, $num)->select();
            }
        }

        foreach ($data as $v) {
            $v['group'] = $v->getGroupName();
            $v['browse'] = NoticeRecord::where("id", "<>", "0")
                ->where("nid", $v['id'])
                ->count();
            $fabulous = NoticeRecord::where("id", "<>", "0")
                ->where("nid", $v['id'])
                ->where("fabulous", "1")
                ->count();
            $negative = NoticeRecord::where("id", "<>", "0")
                ->where("nid", $v['id'])
                ->where("negative", "1")
                ->count();
            $v['fabulous_negative'] = ($fabulous + $negative) . "次互动【" . $fabulous . "个点赞，" . $negative . "个差评】";

        }
        return exit(json_encode(['code' => 0, 'msg' => '成功', "data" => $data, "count" => $count]));
    }

    public function oper(Request $request)
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
            ['mode', 'require', '请选择操作类型'],
            ['id', 'require', '请选择要操作的公告']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $id_arr = explode(",", $postArray['id']);
        if (count($id_arr) <= 0) {
            return json_encode(['code' => 1, 'msg' => "至少操作一条数据"]);
        } else {
            if ($postArray['mode'] == "popup_1") {
                if (count($id_arr) > 1) {
                    return json_encode(['code' => 1, 'msg' => "开启弹窗公告只能操作一条"]);
                }
            }
        }
        foreach ($id_arr as $id) {
            $notice = Notice::getById($id);
            if (!$notice) {
                return json_encode(['code' => 1, 'msg' => "【ID：" . $notice['id'] . "】的公告不存在或已被删除"]);
            }
        }
        $notice = Notice::where("id", "in", $postArray['id']);
        switch ($postArray['mode']) {
            case "del":
                $validate = new Validate([
                    ['security_password', 'require|number|length:6,6', "请输入安全密码|请正确输入安全密码|请正确输入安全密码"]
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                if (md5($postArray['security_password']) != $core['security_password']) {
                    return json_encode(array("code" => 1, "msg" => "安全密码不正确"));
                }
                $NoticeRecord = NoticeRecord::where("nid", "in", $postArray['id']);
                if (!$notice->delete()) {
                    $NoticeRecord->delete();
                    return json_encode(['code' => 1, 'msg' => "删除失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "删除成功"]);
                }
                break;
            case "popup_0":
                //关闭弹窗
                if ($notice->update(['popup' => "0"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "关闭弹窗失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "关闭弹窗成功"]);
                }
                break;
            case "popup_1":
                //开启弹窗
                $temp = Notice::getById($postArray['id']);
                if (Notice::where("group", $temp['group'])->update(['popup' => "0"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "关闭之前的弹窗公告失败"]);
                }
                if ($notice->update(['popup' => "1"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "设置弹窗失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "设置弹窗成功"]);
                }
                break;
            case "confirm_0":
                //无需确认
                if ($notice->update(['confirm' => "0"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "设置无需确认失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "设置无需确认成功"]);
                }
                break;
            case "confirm_1":
                //需要确认
                if ($notice->update(['confirm' => "1"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "设置需确认失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "设置需确认成功"]);
                }
                break;
            case "topping_0":
                //取消置顶
                if ($notice->update(['topping' => "0"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "取消置顶失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "取消置顶成功"]);
                }
                break;
            case "topping_1":
                //设置置顶
                if ($notice->update(['topping' => "1"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "设置置顶失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "设置置顶成功"]);
                }
                break;
            case "show_0":
                //取消公开
                if ($notice->update(['show' => "0"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "取消公开失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "取消公开成功"]);
                }
                break;
            case "show_1":
                //公开
                if ($notice->update(['show' => "1"]) === false) {
                    return json_encode(['code' => 1, 'msg' => "开启公开失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "开启公开成功"]);
                }
                break;
            default:
                return json_encode(['code' => 1, 'msg' => '不支持此操作类型']);
        }
    }

    public function setNotice(Request $request)
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
            ['mode', 'require', '请选择操作类型'],
            ['title', 'require|chsDash|length:2,26', '请设置公告标题|公告标题只能为是汉字、字母、数字和下划线 _ 及破折号 -|公告标题只能设置2-26个字'],
            ['group', 'require', '请选择公告类型'],
            ['topping', 'between:0,1', '请选择正确选择置顶项'],
            ['popup', 'between:0,1', '请选择正确选择弹窗项'],
            ['content', 'require', '请填写公告内容'],
            ['show', 'between:0,1', '请选择正确选择公开项'],
            ['confirm', 'between:0,1', '请选择正确选择确认项'],
            ['release_time', 'require|date', '请设置发布时间|请正确设置发布时间']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if($postArray['mode']=="edit"){
            if(!array_key_exists('id',$postArray)){
                return exit(json_encode(['code' => 1, 'msg' => '选择要编辑的公告ID']));
            }
            $notice = Notice::getById($postArray['id']);
            if(!$notice){
                return exit(json_encode(['code' => 1, 'msg' => '当前编辑的公告不存在或已被删除']));
            }
            $msg="编辑";
            $id=$notice['id'];
        }elseif($postArray['mode']=="add"){
            $id=0;
            $notice = new Notice;
            $notice->uid="admin";
            $notice->establish_time=date('Y-m-d H:i:s');
            $msg="添加";
        }else{
            return exit(json_encode(['code' => 1, 'msg' => '非法操作类型']));
        }
        $group = $postArray['group'];
        $temp = new Notice;
        if (!array_key_exists($group, $temp->getGroupName("", true))) {
            return exit(json_encode(['code' => 1, 'msg' => '不存在此公告类型']));
        }

        if ($postArray['popup'] == "1") {
            //开启弹窗
            if (Notice::where("group", $group)->where("id","<>",$id)->update(['popup' => "0"]) === false) {
                return json_encode(['code' => 1, 'msg' => "关闭之前的弹窗公告失败"]);
            }
        }
        $notice->group=$group;
        $notice->popup=$postArray['popup'];
        $notice->topping=$postArray['topping'];
        $notice->confirm=$postArray['confirm'];
        $notice->title=$postArray['title'];
        $notice->content=$postArray['content'];
        $notice->show=$postArray['show'];
        $notice->release_time=$postArray['release_time'];
        if ($notice->save() !== false) {
            return json_encode(['code' => 0, 'msg' => $msg."成功"]);
        } else {
            return json_encode(['code' => 1, 'msg' => $msg."失败"]);
        }

    }

}
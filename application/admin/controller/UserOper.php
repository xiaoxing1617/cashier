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
class UserOper extends Controller
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

        $this->assign('title', "商户管理");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/user');  //进入模板
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
            } else {
                $value = $postArray['value'];
            }
        }
        $user = new User;
        if(array_key_exists("limit",$postArray)){
            $num = $postArray['limit'];  //每次显示数量
        }else{
            $num = 15;  //每次显示数量
        }
        if(array_key_exists("page",$postArray)){
            $page = $postArray['page'];  //第几页
        }else{
            $page = 1;  //第几页
        }
        if ($page <= 0) {
            $page = 1;
        }
        switch ($mode) {
            case "all":
                $count = $user->where("id", "<>", "0")->count();
                $data = $user->where("id", "<>", "0")->order('id desc')->page($page, $num)->select();
                break;
            case "uid":
                $count = $user->where("id", "<>", "0")
                    ->where("uid", $value)
                    ->count();
                $data = $user->where("id", "<>", "0")
                    ->where("uid", $value)
                    ->order('id desc')
                    ->page($page, $num)
                    ->select();
                break;
            case "qq":
                $count = $user->where("id", "<>", "0")
                    ->where("qq", $value)
                    ->count();
                $data = $user->where("id", "<>", "0")
                    ->where("qq", $value)
                    ->order('id desc')
                    ->page($page, $num)
                    ->select();
                break;
            case "nickname":
                $count = $user->where("id", "<>", "0")
                    ->where("nickname", "like", "%" . $value . "%")
                    ->count();
                $data = $user->where("id", "<>", "0")
                    ->where("nickname", "like", "%" . $value . "%")
                    ->order('id desc')
                    ->page($page, $num)
                    ->select();
                break;
            case "account":
                $count = $user->where("id", "<>", "0")
                    ->where("account", $value)
                    ->count();
                $data = $user->where("id", "<>", "0")
                    ->where("account", $value)
                    ->order('id desc')
                    ->page($page, $num)
                    ->select();
                break;
            case "email":
                $count = $user->where("id", "<>", "0")
                    ->where("email", $value)
                    ->count();
                $data = $user->where("id", "<>", "0")
                    ->where("email", $value)
                    ->order('id desc')
                    ->page($page, $num)
                    ->select();
                break;
            default:
                return exit(json_encode(['code' => 1, 'msg' => '不支持该查询类型']));
        }


        foreach ($data as $v) {
            if (!isTemplateName('cashier', $v['cashier_template'])) {
                $v['cashier_template'] = "未知";
            }
            $merchantCheck = merchantCheck($v['id']);
            if ($merchantCheck['code'] != 0) {
                $v['expiration_time'] = "<span style='color: #f00'>" . $merchantCheck['msg'] . "</span>";
            }
        }
        return exit(json_encode(['code' => 0, 'msg' => '成功', "data" => $data,"count"=>$count]));
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
            ['uid', 'require', '请选择商户UID']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $uid_arr = explode(",",$postArray['uid']);
        if(count($uid_arr)<=0){
            return json_encode(['code' => 1, 'msg' => "至少操作一条数据"]);
        }
        if($postArray['mode']!="renew"){
            foreach ($uid_arr as $uid){
                $user = User::getByUid($uid);
                if (!$user) {
                    return json_encode(['code' => 1, 'msg' => "【商户UID：".$uid."】不存在或已被删除"]);
                }
            }
            $user = new User;
            $user->where("uid","in",$postArray['uid']);
        }else{
            $user = User::getByUid($postArray['uid']);
            if (!$user) {
                return json_encode(['code' => 1, 'msg' => "【商户UID：".$postArray['uid']."】不存在或已被删除"]);
            }
        }
        switch ($postArray['mode']) {
            case "state_1":
                if (!$user->update(['state'=>"1"])) {
                    return json_encode(['code' => 1, 'msg' => "修改失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "修改成功"]);
                }
                break;
            case "state_0":
                if (!$user->update(['state'=>"0"])) {
                    return json_encode(['code' => 1, 'msg' => "修改失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "修改成功"]);
                }
                break;
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
                $login = Login::where("uid", "in",$postArray['uid']);
                $pay = Pay::where("uid", "in",$postArray['uid']);
                if (!$user->delete()) {
                    return json_encode(['code' => 1, 'msg' => "注销失败"]);
                } else {
                    $login->delete();
                    $pay->delete();
                    return json_encode(['code' => 0, 'msg' => "注销成功"]);
                }
                break;
            case "renew":
                $validate = new Validate([
                    ['value', 'require|integer|gt:0', '请先填写续费/减扣时长|时长只能是正整数|时长至少1天或1月'],
                    ['type', 'require', '请选择续费/减扣类型'],
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                $value = $postArray['value'];
                $arr = merchantCheck($user['uid'], true);
                if ($arr['code'] == 0) {
                    if($postArray['type']!='reduce_month' && $postArray['type']!='reduce_day') {
                        if (strtotime($arr['date']) >= time()) {
                            $user['expiration_time'] = $arr['date'];
                        } else {
                            $user['expiration_time'] = date('Y-m-d H:i:s');
                        }
                    }else{
                        $user['expiration_time'] = $arr['date'];
                    }
                } else {
                    $user['expiration_time'] = date('Y-m-d H:i:s');
                }
                if ($postArray['type'] == "add_month") {
                    $date = date("Y-m-d H:i:s", strtotime("+" . $value . " months", strtotime($user['expiration_time'])));
                } else if ($postArray['type'] == "reduce_month") {
                    $date = date("Y-m-d H:i:s", strtotime("-" . $value . " months", strtotime($user['expiration_time'])));
                } else if ($postArray['type'] == "add_day") {
                    $date = date("Y-m-d H:i:s", strtotime("+" . $value . " day", strtotime($user['expiration_time'])));
                } else if ($postArray['type'] == "reduce_day") {
                    $date = date("Y-m-d H:i:s", strtotime("-" . $value . " day", strtotime($user['expiration_time'])));
                } else {
                    return json_encode(['code' => 1, 'msg' => "不支持此类型"]);
                }
                $user->expiration_time = $date;
                if (!$user->save()) {
                    return json_encode(['code' => 1, 'msg' => "续费/减扣失败"]);
                } else {
                    return json_encode(['code' => 0, 'msg' => "续费/减扣成功"]);
                }
                break;
            default:
                return json_encode(['code' => 1, 'msg' => '不支持此操作类型']);
        }
    }

    public function info(Request $request)
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
        $validate = new Validate([
            ['uid', 'require', '请先选择商户UID']
        ]);
        if (!$validate->check($getArray)) {
            weuiMsg('info', $validate->getError());
            return;
        }
        $user = User::getByUid($getArray['uid']);
        if (!$user) {
            weuiMsg('info', "抱歉，该商户不存在或已被删除");
            return;
        }

        $cashier_arr = getTemplateList("cashier");
        $this->assign('cashier_arr', $cashier_arr);  //收银台模板列表

        $this->assign('title', "编辑商户");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/user_info');  //进入模板
    }

    public function setInfo(Request $request)
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
            ['uid', 'require', '请选择要编辑的商户UID'],
            ['email', 'require|email', '商户邮箱不能为空|请正确填写商户邮箱'],
            ['account', 'require|length:4,16|alphaNum', '账号不能为空|账号只能4-16个字符|账号只能是字母和数字'],
            ['nickname', 'require|max:16|chsDash', '商户昵名称不能为空|商户昵名称最多设置16个字|商户名称只能是汉字、字母、数字和下划线(_)及破折号(-)'],
            ['qq', 'require|length:6,11|number', 'QQ号码不能为空|请正确填写QQ号码|请正确填写QQ号码'],
            ['weixin', 'alphaDash|max:20|min:6', '请正确填写微信号|请正确填写微信号|请正确填写微信号'],
            ['cashier_template', 'require', '请选择一个收银台模板'],
            ['state', 'number|between:0,1', '请正确选择商户状态|非法的商户状态'],
            ['expiration_time', 'require|date', '请填写商户收款服务能力到期时间|商户收款服务能力到期时间格式不正确']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $user = User::getByUid($postArray['uid']);
        if (!$user) {
            return json_encode(['code' => 1, 'msg' => "该商户不存在或已被删除"]);
        }
        if (!isTemplateName('cashier', $postArray['cashier_template'])) {
            return json_encode(['code' => 1, 'msg' => "不存在此收银台模板"]);
        }
        $temp = User::where(["email" => $postArray['email']])->where("uid", "<>", $user['uid'])->find();
        if ($temp) {
            return json_encode(['code' => 1, 'msg' => '该邮箱已被占用']);
        }
        $temp = User::where(["account" => $postArray['account']])->where("uid", "<>", $user['uid'])->find();
        if ($temp) {
            return json_encode(['code' => 1, 'msg' => '该账号已被占用']);
        }
        if (array_key_exists('password', $postArray)) {
            if ($postArray['password'] != "" && $postArray['password'] != null) {
                $validate = new Validate([
                    ['password', 'require|length:6,16|alphaNum', '商户密码不能为空|商户密码只能6-16个字符|商户密码只能是字母和数字']
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                $postArray['password'] = md5($postArray['password']);
            } else {
                $postArray['password'] = $user['password'];
            }
        } else {
            $postArray['password'] = $user['password'];
        }

        $temp = User::getByUid($postArray['uid']);
        $temp->email = $postArray['email'];
        $temp->account = $postArray['account'];
        $temp->nickname = $postArray['nickname'];
        $temp->qq = $postArray['qq'];
        $temp->weixin = $postArray['weixin'];
        $temp->cashier_template = $postArray['cashier_template'];
        $temp->state = $postArray['state'];
        $temp->expiration_time = $postArray['expiration_time'];
        $temp->password = $postArray['password'];
        if ($temp->save() !== false) {
            return json_encode(['code' => 0, 'msg' => "修改成功"]);
        } else {
            return json_encode(['code' => 1, 'msg' => "修改失败"]);
        }
    }

    public function creation()
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

        $cashier_arr = getTemplateList("cashier");
        $this->assign('cashier_arr', $cashier_arr);  //收银台模板列表
        $this->assign('title', "创建商户");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/user_creation');  //进入模板
    }

    public function addUser(Request $request)
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
            ['email', 'require|email', '商户邮箱不能为空|请正确填写商户邮箱'],
            ['account', 'require|length:4,16|alphaNum', '账号不能为空|账号只能4-16个字符|账号只能是字母和数字'],
            ['password', 'require|length:6,16|alphaNum', '商户密码不能为空|商户密码只能6-16个字符|商户密码只能是字母和数字'],
            ['nickname', 'require|max:16|chsDash', '商户昵名称不能为空|商户昵名称最多设置16个字|商户名称只能是汉字、字母、数字和下划线(_)及破折号(-)'],
            ['qq', 'require|max:11|min:6|number', 'QQ号码不能为空|请正确填写QQ号码|请正确填写QQ号码|请正确填写QQ号码'],
            ['weixin', 'alphaDash|max:20|min:6', '请正确填写微信号|请正确填写微信号|请正确填写微信号'],
            ['cashier_template', 'require', '请选择一个收银台模板'],
            ['state', 'number|between:0,1', '请正确选择商户状态|非法的商户状态'],
            ['expiration_time', 'date', '商户收款服务能力到期时间格式不正确']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if (!isTemplateName('cashier', $postArray['cashier_template'])) {
            return json_encode(['code' => 1, 'msg' => "不存在此收银台模板"]);
        }
        $temp = User::where(["email" => $postArray['email']])->find();
        if ($temp) {
            return json_encode(['code' => 1, 'msg' => '该邮箱已被占用']);
        }
        $temp = User::where(["account" => $postArray['account']])->find();
        if ($temp) {
            return json_encode(['code' => 1, 'msg' => '该账号已被占用']);
        }
        $validate = new Validate([
            ['free_collection_service_days', 'require|number', '必须有赠送天数|赠送天数必须是数字'],
        ]);
        if (!$validate->check($core)) {
            $free_collection_service_days = 0;
        }else{
            $free_collection_service_days = $core['free_collection_service_days'];
        }
        $date = date('Y-m-d H:i:s',strtotime("+".$free_collection_service_days." day"));
        if (array_key_exists('expiration_time', $postArray)) {
            if ($postArray['expiration_time'] == "" or $postArray['expiration_time']== null) {
                $postArray['expiration_time'] = $date;
            }
        }else{
            $postArray['expiration_time'] = $date;
        }

        $pay_list = getPayList();
        foreach ($pay_list as $value) {
            $alias = $value['alias'];
            $json[$alias]=array("switch"=>0,"use_order"=>3);
        }

        $temp = new User;
        $temp->email = $postArray['email'];
        $temp->account = $postArray['account'];
        $temp->nickname = $postArray['nickname'];
        $temp->qq = $postArray['qq'];
        $temp->weixin = $postArray['weixin'];
        $temp->cashier_template = $postArray['cashier_template'];
        $temp->state = $postArray['state'];
        $temp->pay_interface = json_encode($json);
        $temp->creation_time = date('Y-m-d H:i:s');
        $temp->expiration_time = $postArray['expiration_time'];
        $temp->password = md5($postArray['password']);
        if ($temp->save() !== false) {
            $uid = rand(1, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9) . $temp['id'];
            $data = User::get($temp['id']);  //获取指定主键一条数据
            $data->uid = $uid;
            $data->code = md5(createTradeNo().$temp['id']);
            $data->save();
            if (!$data) {
                User::destroy($temp['id']);  //删除指定主键的一条数据
                return json_encode(['code' => 1, 'msg' => "创建失败，请稍后重试！"]);
            }
            return json_encode(['code' => 0, 'msg' => "创建成功"]);
        } else {
            return json_encode(['code' => 1, 'msg' => "创建失败"]);
        }
    }
}
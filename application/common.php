<?php
// +----------------------------------------------------------------------
// | 星益云 [ Remain true to our original aspiration ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018-至今 http://www.96xy.cn/ All rights reserved.
// +----------------------------------------------------------------------
// | 小星QQ：1450839008   QQ交流群：741930243
// +----------------------------------------------------------------------
// | Author: 小星 <1450839008@qq.com>
// +----------------------------------------------------------------------
// | 本系统采用ThinkPHP框架，特别鸣谢（www.thinkphp.cn）
// +----------------------------------------------------------------------
// | PHP是世界上最好的编程语言之一！
// +----------------------------------------------------------------------
use think\Request;
use think\Db;
use think\Validate;
use app\model\model\Login;
use think\Session;
use app\model\model\User;
use app\model\model\Core;
use PHPMailer\PHPMailer;
use PHPMailer\Exception;
use think\Config;
use rsa\Rsa;

//================================================================================================
//=====开源完整版 - 无加密，免授权，无后门
//=====禁止倒卖二开，负责将追究法律责任
//=====星益云版权所有
//=====2021年10月17日13:00
//=====小星（开发者）QQ：1450839008
//================================================================================================

//================================================
//===================核心常量=======================
//================================================
error_reporting(E_ERROR | E_PARSE);  // 异常抛出报错级别
$sistemaInfo = getSistemaInfo();
define('SYSTEM_ROOT', dirname(__FILE__) . '/');
define('ROOT', dirname(SYSTEM_ROOT) . '/');
define('PAY_PATH', EXTEND_PATH . "pay/");
define('XY_SYSTEM_KEY', "3afbc3de91c83bdf1f3a9cbfdfb69c66");
define('VERSION', $sistemaInfo['VERSION']);  //版本号
define('BUILD', $sistemaInfo['BUILD']);  //更新批号
define('ADMIN_BOTTOM', $sistemaInfo['ADMIN_BOTTOM']);  //后台管理底部
date_default_timezone_set("PRC");//设置时区
@header('Content-Type: text/html; charset=UTF-8');
if (PHP_SESSION_ACTIVE != session_status()) {
    session_start();
}
$_SERVER['SERVER_NAME'] = get_domain();
//================================================
//===================检查安装=======================
//================================================
if (!is_file(APP_PATH . 'install/install.lock') && Request::instance()->baseUrl() != "/install") {
    weuiMsg('warn-primary', '您还没有安装程序哦！<br/><br/><br/><a href="/install" class="weui-btn weui-btn_default">立即安装</a>', '未安装', false);
    exit();
} else {
    if (Request::instance()->baseUrl() != "/install") {
        if (!DB::query("SHOW TABLES LIKE 'xy_cashier_core'")) {
            unlink(APP_PATH . 'install/install.lock');
        }
    }
}
//================================================
//===================检查更新=======================
//================================================
if(Request::instance()->baseUrl() != "/update" && Request::instance()->baseUrl() != "/install"){
    if (checkUpdate()) {
        weuiMsg('warn-primary', '系统检查到您的 v'.VERSION.'('.BUILD.') 未更新完成，请完成更新后使用！<br/><br/><br/><a href="/update" class="weui-btn weui-btn_default">去更新</a>', '版本更新', false);
        exit();
    }
}
extension_loaded('openssl') or die(weuiMsg('warn-primary', '', '需要支持openssl扩展', false));
//================================================
//===================核心函数方法====================
//================================================
/**
 * 检查更新
 */
function checkUpdate(){
    $core = Core::where('id', '<>', 0)->column('value1', 'name');
    switch (BUILD){
        case 1019:
            if(!DB::query("SHOW TABLES LIKE 'xy_cashier_fixed_amount'")){
                return true;
            }
            if(!$core['user_theme_data'] || !$core['page_grey']){
                return true;
            }
            break;
        default:
            return false;
    }
    return false;
}
/**
 * 获取当前域名（不带协议头）
 */
function get_domain()
{
    $domain = Request::instance()->domain();
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $domain = substr($domain, 8);
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $domain = substr($domain, 8);
    } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        $domain = substr($domain, 8);
    } else {
        $domain = substr($domain, 7);
    }
    return $domain;
}

/**
 * 获取通知地址及参数
 *
 * @return string
 */
function creat_callback($url, $order, $trade_status)
{
    $key = User::where('uid', $order['uid'])->column('key');  //获取商户api密钥
    $array = array(
        'uid' => $order['uid'],
        'type' => $order['type'],
        'money' => $order['money'],
        'third_trade_no' => $order['third_trade_no'],
        'out_trade_no' => $order['out_trade_no'],
        'api_trade_no' => $order['api_trade_no'],
        'name' => $order['name'],
        'trade_status' => $trade_status,
        'trade_type' => $order['trade_type'],
        'time' => time()
    );
    if ($trade_status == "TRADE_SUCCESS" or $trade_status == "FULL_REFUND") {
        $array['payment_time'] = $order['payment_time'];
    }
    if ($trade_status == "FULL_REFUND") {
        $array['refund_time'] = $order['refund_time'];
    }
    require_once PAY_PATH . "cashier/config.php";
    require_once PAY_PATH . "cashier/lib/CashierFunction.php";
    $config['uid'] = $array['uid'];
    $config['key'] = $key[0];
    $CashierFunction = new CashierFunction;
    $urlstr = $CashierFunction->createUrlStr($array, true, $config, $config['eliminate'], true);
    if (strpos($url, '?')) {
        $url = $url . '&' . $urlstr;
    } else {
        $url = $url . '?' . $urlstr;
    }
    return $url;
}

/**
 * 发送异步通知 并 验证是否通知成功
 *
 * @return bool
 */
function do_notify($url)
{
    $return = curl_get($url);
    if (strpos($return, 'success') !== false || strpos($return, 'SUCCESS') !== false || strpos($return, 'Success') !== false) {
        return true;
    } else {
        return false;
    }
}

/**
 * 获取具体客户端支付类型
 *
 * @return string
 */
function getAccessMode()
{
    if (isMobileClient()) {
        //========手机浏览器
        $client = getClientkeyword();  //获取客户端打开方式
        if ($client == null) {
            //外部浏览器（如：手机浏览器）
            return "external";
        } else {
            //内置浏览器（如：支付宝）
            return "inside";
        }
    } else {
        //========电脑浏览器
        return "pc";
    }
}

/**
 * 是否移动端访问访问
 *
 * @return bool
 */
function isMobileClient()
{
// 如果有HTTP_X_WAP_PROFILE则一定是移动设备
    if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
        return true;
    }

//如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
    if (isset($_SERVER['HTTP_VIA'])) {
//找不到为flase,否则为true
        return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
    }

//判断手机发送的客户端标志
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $clientkeywords = [
            'nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp',
            'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu',
            'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi',
            'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile', 'alipay'
        ];

// 从HTTP_USER_AGENT中查找手机浏览器的关键字
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
            return true;
        }
    }

//协议法，因为有可能不准确，放到最后判断
    if (isset($_SERVER['HTTP_ACCEPT'])) {
// 如果只支持wml并且不支持html那一定是移动设备
// 如果支持wml和html但是wml在html之前则是移动设备
        if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
            return true;
        }
    }

    return false;
}

/**
 * 判断客户端访问标识
 * @return string
 */
function getClientkeyword()
{
    $pay_list = getPayList();
    foreach ($pay_list as $value) {
        if (strpos($_SERVER['HTTP_USER_AGENT'], $value['client_keyword'])) {
            return $value['client_keyword'];
        }
    }
    return null;
}

/**
 * 校验商户账号密码
 * @return array
 */
function isUser($data)
{
    if (!array_key_exists('account', $data)) {
        return ['code' => 1, 'msg' => '请先填写你的账号'];
    } else {
        $account = $data['account'];
    }
    if (!array_key_exists('password', $data)) {
        return ['code' => 1, 'msg' => '请先填写你的密码'];
    } else {
        if (array_key_exists('ismd5', $data)) {
            $password = $data['password'];
        } else {
            $password = md5($data['password']);
        }
    }

    $is_account = User::whereOr(["account" => $account,"uid" => $account,"email" => $account])->find("account");
    if (!$is_account) {
        return ['code' => 1, 'msg' => '商户不存在或已被删除'];
    }
    $user = User::where("password",$password)->where(function ($query) use ($account)  {
        $query->whereOr("account",$account);
        $query->whereOr("uid",$account);
        $query->whereOr("email",$account);
    })->find();
    if(!$user){
        return ['code' => 1, 'msg' => '账号或密码错误'];
    }
    if ($user['state'] != 1) {
        return ['code' => 1, 'msg' => '该用户已被封禁'];
    }
    //====登录记录
    $login = new Login;
    $login->uid = $user['uid'];
    $login->time = date('Y-m-d H:i:s');
    $login->ip = getIp();
    $login->mode = (isMobileClient()) ? 1 : 2;
    if (!$login->save()) {
        return ['code' => 1, 'msg' => '登录失败，请稍后重试'];
    }
    //====登录记录
    $session = hash('ripemd160', $user['account'] . $user['password'] . XY_SYSTEM_KEY);
    $token = $user['uid'] . "\t" . $session;
    Session::set('xy_cashier_login_user', $token);
    return ['code' => 0, 'msg' => '登录成功', "url" => "/user"];
}

/**
 * 获取系统信息
 *
 * @return array
 */
function getSistemaInfo()
{
    $ret = array();
    if ($json = file_get_contents('static/common/json/sistema.json')) {
        if ($array = json_decode($json, true)) {
            $ret = $array;
        }
    }
    if ($ret == array()) {
        weuiMsg('warn-primary', '系统信息文件不存在或已损坏，请联系开发者处理！', '警告', false);
        exit();
    }
    return $ret;
}

/**
 * SQ语句执行
 * @return array
 */
function sqlImplement($path = null, $array)
{
    if ($path == null) {
        return;
    }
    $link = mysqli_connect($array['hostname'], $array['username'], $array['password'], $array['database'], $array['hostport']);
    if (!$link) {
        return [
            "code" => 1,
            "msg" => "【数据库连接失败】" . mysqli_connect_error()
        ];
    }
    if (trim($path) == "" or $path == NULL) {
        return [
            "code" => 1,
            "msg" => "sql文件地址为空！"
        ];
    }
    if (!file_exists($path) or !$sql = file_get_contents($path)) {
        return [
            "code" => 1,
            "msg" => "sql文件不存在！"
        ];
    }
    $sql = explode(';</explode>', $sql);

    DB::query("set sql_mode = ''");
    DB::query("set names utf8");

    $t = 0;
    $e = 0;
    $error = '';
    for ($i = 0; $i < count($sql); $i++) {
        if (trim($sql[$i]) == '' or $sql[$i] == null or $i == 0) {
            ++$t;
            continue;
        }
        if (mysqli_query($link, $sql[$i] . ";")) {
            ++$t;
        } else {
            ++$e;
            $error .= mysqli_error($link) . '<br/>';
        }
    }

    return [
        "code" => 0,
        "s" => count($sql),
        "t" => $t,
        "e" => $e,
        "error" => $error
    ];
}


function checkfunc($f, $m = false)
{
    if (function_exists($f)) {
        return '<span style="color:green;">支持</span>';
    } else {
        if ($m == false) {
            return '<span style="color:black;">不支持</span>';
        } else {
            return '<span style="color:red;">不支持</span>';
        }
    }
}

function checkclass($f, $m = false)
{
    if (class_exists($f)) {
        return '<span style="color:green;">支持</span>';
    } else {
        if ($m == false) {
            return '<span style="color:black;">不支持</span>';
        } else {
            return '<span style="color:red;">不支持</span>';
        }
    }
}

/**
 * 发送请求
 * url：请求地址
 * post：POST数据
 * referer：伪造来源地址
 * cookie：cookie值
 * header：请求头
 * ua：伪造请求浏览器
 * nobaody：请求体
 * addheader：内置请求头 和 自义定请求头 是否合并
 *
 * @return
 */
function curl_get($url, $post = 0, $referer = 0, $cookie = 0, $header = 0, $ua = 0, $nobaody = 0, $addheader = 0)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $httpheader[] = "Accept: */*";
    $httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
    $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
    $httpheader[] = "Connection: close";
    if ($addheader) {
        $httpheader = array_merge($httpheader, $addheader);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    if ($header) {
        curl_setopt($ch, CURLOPT_HEADER, true);
    }
    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if ($referer) {
        if ($referer == 1) {
            curl_setopt($ch, CURLOPT_REFERER, 'http://pay.96xy.cn/');
        } else {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }
    }
    if ($ua) {
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; U; Android 4.0.4; es-mx; HTC_One_X Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0");
    }
    if ($nobaody) {
        curl_setopt($ch, CURLOPT_NOBODY, 1);
    }
    curl_setopt($ch, CURLOPT_ENCODING, "gzip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
}

/**
 * 发送邮件
 * @return array
 */
function send_mail($email, $title, $html)
{
    $core = Core::where('id', '<>', 0)->column('value1', 'name');
    if ($core['email_host'] == "" or $core['email_user'] == "" or $core['email_pass'] == "" or $core['email_port'] == "") {
        return array("code" => 1, "msg" => "邮箱配置待完善，请联系管理处理");
    }
    $port = intval($core['email_port']);

    require_once(PAY_PATH . "../PHPMailer/Exception.php");
    require_once(PAY_PATH . "../PHPMailer/PHPMailer.php");
    require_once(PAY_PATH . "../PHPMailer/SMTP.php");
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 5;
        $mail->isSMTP();
        $mail->Host = $core['email_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $core['email_user'];
        $mail->Password = $core['email_pass'];
        if ($port == 587) $mail->SMTPSecure = 'tls';
        else if ($port >= 465) $mail->SMTPSecure = 'ssl';
        else $mail->SMTPAutoTLS = false;
        $mail->Port = $port;
        $mail->setFrom($core['email_user'], $core['title']);
        $mail->addAddress($email);
        $mail->addReplyTo($core['email_user'], $core['title']);
        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body = $html;
        $mail->AltBody = $html;
        $mail->send();
        return array("code" => 0, "msg" => "发送成功");
    } catch (Exception $e) {
        return array("code" => 0, "msg" => $mail->ErrorInfo);
    }
}

/**
 * 验证商户是否可以收款
 * @return array
 */
function merchantCheck($id, $uid = false)
{
    if ($uid) {
        $user = User::getByUid($id);
    } else {
        $user = User::getById($id);
    }
    if (!$user) {
        return array("code" => 1, "msg" => "商户不存在或被删除");
    }
    if ($user['state'] != 1) {
        return array("code" => 1, "msg" => "该商户已被停封，暂时无法收款");
    }
    $validate = new Validate([
        ['expiration_time', 'require|date', '该商户未开通收款服务能力，请商户立即开通|该商户收款服务能力的到期时间格式错误，请联系管理员处理']
    ]);
    if (!$validate->check($user)) {
        return array("code" => 1, "msg" => $validate->getError());
    }
    if (strtotime($user['expiration_time']) - time() <= 0) {
        return array("code" => 1, "msg" => "该商户的收款服务能力已到期，请商户立即续费");
    }
    return array("code" => 0, "msg" => "正常", "date" => $user['expiration_time']);
}

/**
 * 获取支付接口插件列表
 * @return array
 */
function getPayExtendList()
{
    $arr = getTemplateList("pay_extend");
    $list = array();
    foreach ($arr as $alias) {
        $json = file_get_contents(APP_PATH . '/../extend/pay/' . $alias . '/info.json');
        if ($temp = json_decode($json, true)) {
            $list[$alias] = $temp;
        }
    }
    return $list;
}

/**
 * 获取付款码列表
 *
 * @return array
 */
function getPaymentCodeList($type = null, $field = null)
{
    $json = file_get_contents('static/common/json/qr_config.json');
    $array = json_decode($json, true);
    if ($type != null && $field != null) {
        foreach ($array as $value) {
            if ($value[$field] == $type) {
                return $value;
            }
        }
    }
    return $array;
}

/**
 * 获取QQ昵称
 * @return String
 */
function getQQnickname($qq)
{
    return "QQ：" . $qq;
}

/**
 * 获取IP地址
 * @return String
 */
function getIp()
{
    static $realip;
    if (isset($_SERVER)) {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $realip = $_SERVER["HTTP_CLIENT_IP"];
        } else {
            $realip = $_SERVER["REMOTE_ADDR"];
        }
    } else {
        if (getenv("HTTP_X_FORWARDED_FOR")) {
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("HTTP_CLIENT_IP")) {
            $realip = getenv("HTTP_CLIENT_IP");
        } else {
            $realip = getenv("REMOTE_ADDR");
        }
    }
    return $realip;
}

/**
 * 获取登录用户UID
 * @return string
 */
function getUserUid()
{
    $token = Session::get('xy_cashier_login_user');
    if ($token == null) {
        return 0;
    }
    list($uid, $dense) = explode("\t", $token);
    $user = User::where(["uid" => $uid])->find();
    if (!$user) {
        return 0;
    } else {
        return $uid;
    }
}

/**
 * 验证登录
 * @return boolean
 */
function isLogin($mode = null)
{
    if ($mode == "user") {
        $token = Session::get('xy_cashier_login_user');
        if ($token == null) {
            return false;
        }
        list($uid, $dense) = explode("\t", $token);
        $user = User::where(["uid" => $uid])->find();
        if (!$user) {
            return false;
        }
        $session = hash('ripemd160', $user['account'] . $user['password'] . XY_SYSTEM_KEY);
        if ($session == $dense) {
            return true;
        } else {
            return false;
        }

    } else if ($mode == "admin") {
        $token = Session::get('xy_cashier_login_admin');
        if ($token == null) {
            return false;
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        $session = hash('ripemd160', $core['admin_account'] . $core['admin_password'] . XY_SYSTEM_KEY);
        if ($session == $token) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }

}

/**
 * 判断是http or https
 * @return string
 */
if (!function_exists("is_https")) {
    function is_https()
    {
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        } elseif (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $server['HTTPS'] == '1')) {
            return true;
        } elseif (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && $_SERVER['HTTP_X_CLIENT_SCHEME'] == 'https') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            return true;
        } elseif (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https') {
            return true;
        } elseif (isset($_SERVER['HTTP_EWS_CUSTOME_SCHEME']) && $_SERVER['HTTP_EWS_CUSTOME_SCHEME'] == 'https') {
            return true;
        }
        return false;
    }
}
/**
 * 生成系统订单号
 * @return string
 */
function createTradeNo($prefix = NULL)
{
    if ($prefix == NULL) {
        $core = app\model\model\Core::where('id', '<>', 0)->column('value1', 'name');
        $prefix = $core['order_prefix'];
    }
    return $prefix . date('YmdHis', time()) . substr(microtime(), 2, 6) . sprintf('%03d', rand(0, 999));
}

/**
 * 获取模板列表
 * @return array
 */
function getTemplateList($type = null)
{
    $mb_array = [];
    switch ($type) {
        case 'index':
            //首页
            $name = "index.html";
            $path = APP_PATH . '/../application/index/view/index/';
            $mb_str = opendir($path);  //模板文件夹路径
            break;
        case 'cashier':
            //收银台
            $name = "index.html";
            $path = APP_PATH . '/../application/index/view/cashier/';
            $mb_str = opendir($path);  //模板文件夹路径
            break;
        case 'return':
            //回调页面
            $name = "success.html";
            $path = APP_PATH . '/../application/pay/view/return/';
            $mb_str = opendir($path);  //模板文件夹路径
            break;
        case 'user_login':
            //用户登录页面
            $name = "user.html";
            $path = APP_PATH . '/../application/index/view/login/';
            $mb_str = opendir($path);  //模板文件夹路径
            break;
        case 'admin_login':
            //后台登录页面
            $name = "admin.html";
            $path = APP_PATH . '/../application/index/view/login/';
            $mb_str = opendir($path);  //模板文件夹路径
            break;
        case 'register':
            //注册页面
            $name = "index.html";
            $path = APP_PATH . '/../application/index/view/register/';
            $mb_str = opendir($path);  //模板文件夹路径
            break;
        case 'pay_extend':
            //支付接口插件列表
            $name = "info.json";
            $path = APP_PATH . '/../extend/pay/';
            $mb_str = opendir($path);  //插件文件夹路径
            break;
        case 'home_css':
            //后台css模板
            $name = "";
            $path = APP_PATH . '/../public/static/Xadmin/css/themes/';
            $mb_str = opendir($path);  //css文件夹路径
            break;
        default:
            return $mb_array;
    }
    while (($filename = readdir($mb_str)) !== false) {
        if ($filename != "." && $filename != "..") {
            if ($name == "") {
                $mb_array[] = $filename;
            } else {
                if (file_exists($path . $filename . '/' . $name)) {
                    $mb_array[] = $filename;
                }
            }
        }
    }
    closedir($mb_str);
    $mb_array = array_filter($mb_array);  //删除数组内的空值
    $mb_array = array_unique($mb_array);  //删除数组内的重复值
    return $mb_array;
}

/**
 * 模板名称校验
 * @return boolean
 */
function isTemplateName($type = null, $name = null)
{
    if ($type == null && $name == null) {
        return false;
    }
    $mb_array = getTemplateList($type);
    //校验模板名称是否在列表里
    if (in_array($name, $mb_array)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 获取支付方式列表
 *
 * @return array
 */
function getPayList($type = null, $field = null)
{
    $json = file_get_contents('static/common/json/pay_list.json');
    $array = json_decode($json, true);
    if ($type != null && $field != null) {
        foreach ($array as $value) {
            if ($value[$field] == $type) {
                return $value;
            }
        }
        return array();
    }
    $temp = [];
    foreach ($array as $value) {
        $temp[$value['alias']] = $value;
    }
    return $temp;
}

/**
 * weui-msg信息提示
 * @no return
 * @param string $type 类型
 * @param string $txt 副标题
 * @param string $title 标题
 * @param bool $show_but 是否显示按钮
 * @param string[] $but_data 按钮数据   ["url"=>"__index__","title"=>"首页"]
 */
function weuiMsg($type = 'info', $txt = '', $title = '提示信息', $show_but = true,$but_data = ["url"=>"__index__","title"=>"首页","type"=>"default"])
{
    /*
     * success：成功
     * info：提示
     * warn：普通警告
     * warn-primary：强烈警告
     * waiting：等待
     */

    if ($type != 'success' && $type != 'info' && $type != 'warn' && $type != 'waiting' && $type != 'warn-primary') {
        $type = 'info';
    }
    $request = Request::instance();

    $but_html = "";
    if($show_but){
        if(count($but_data) == count($but_data,1)){
            $but_data = [$but_data];
        }

        foreach ($but_data as $res){
            $but_html .= '<a href="' .str_replace("__index__",$request->domain(),$res['url']). '" class="weui-btn weui-btn_'.($res['type']?:'default').'">'.$res['title'].'</a>';
        }
    }
    exit('
<html class="weui-msg">
<head>
<title>提示</title>
<link href="//res.wx.qq.com/open/libs/weui/2.4.2/weui.min.css" rel="stylesheet">
<meta id="viewport" name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
 <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
    <div class="weui-msg__icon-area">
        <i class="weui-icon-' . ($type == 'warn-primary' ? 'warn' : $type) . ' weui-icon_msg' . ($type == 'warn-primary' ? '' : '-primary') . '"></i>
    </div>
    <div class="weui-msg__text-area">
        <h2 class="weui-msg__title">' . $title . '</h2>
    <div class="ui-ellpisis weui-msg__desc">
        <div class="ui-ellpisis-content">
            <div style="-webkit-line-clamp: 1;">
                <p></p>
            </div>
            <div class="ui-ellpisis-placeholder" style="position: absolute; display: block;"></div>
        </div>
        <!---->
    </div>
    <p class="weui-msg__desc">' . $txt . '</p>
</div>
<div class="weui-msg__opr-area">
    <p class="weui-btn-area">
        ' . $but_html . '
    </p>
</div>
<div class="weui-msg__extra-area">
    <div class="weui-footer"><p class="weui-footer__links"></p></div>
</div>
</body>
</html>
    ');
}

/**
 * 跳转浏览器
 * @no return
 */
function jumpBrowser($siteurl = "", $jump = true)
{
    if ($jump && $siteurl != "") {
        exit(
            '<html>
<head>
    <meta charset="UTF-8">
    <title>使用浏览器打开</title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport">
    <meta content="yes" name="apple-mobile-web-app-capable">
    <meta content="black" name="apple-mobile-web-app-status-bar-style">
    <meta name="format-detection" content="telephone=no">
    <meta content="false" name="twcClient" id="twcClient">
    <meta name="aplus-touch" content="1">
    <style>
        body,html{width:100%;height:100%}
        *{margin:0;padding:0}
        body{background-color:#fff}
        #browser img{
            width:50px;
        }
        #browser{
            margin: 0px 10px;
            text-align:center;
        }
        #contens{
            font-weight: bold;
            color: #2466f4;
            margin:-285px 0px 10px;
            text-align:center;
            font-size:20px;
            margin-bottom: 125px;
        }
        .top-bar-guidance{font-size:15px;color:#fff;height:70%;line-height:1.8;padding-left:20px;padding-top:20px;background:url(/static/common/img/banner.png) center top/contain no-repeat}
        .top-bar-guidance .icon-safari{width:25px;height:25px;vertical-align:middle;margin:0 .2em}
        .app-download-tip{margin:0 auto;width:290px;text-align:center;font-size:15px;color:#2466f4;background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAcAQMAAACak0ePAAAABlBMVEUAAAAdYfh+GakkAAAAAXRSTlMAQObYZgAAAA5JREFUCNdjwA8acEkAAAy4AIE4hQq/AAAAAElFTkSuQmCC) left center/auto 15px repeat-x}
        .app-download-tip .guidance-desc{background-color:#fff;padding:0 5px;overflow: hidden;white-space: nowrap;text-overflow:ellipsis;}
        .app-download-tip .icon-sgd{width:25px;height:25px;vertical-align:middle;margin:0 .2em}
        .btn{display:block;width:214px;height:40px;line-height:40px;margin:18px auto 0 auto;text-align:center;font-size:18px;color:#2466f4;border-radius:20px;border:.5px #2466f4 solid;text-decoration:none}
    </style>
</head>
<body>

<div class="top-bar-guidance">
    <p>点击右上角<img src="/static/common/img/3dian.png" class="icon-safari">在 浏览器 打开</p>
    <p>苹果手机<img src="/static/common/img/iphone.png" class="icon-safari">安卓手机<img src="/static/common/img/android.png" class="icon-safari">↗↗↗</p>
</div>

<div id="contens">
<p><br/><br/></p>
<p>无法跳转请复制网址到浏览器打开</p>
</div>

<div class="app-download-tip">
    <span class="guidance-desc">正在为您跳转浏览器</span>
</div>
<p><br/></p>
<div class="app-download-tip">
    <span class="guidance-desc">点击右上角<img src="/static/common/img/3dian.png" class="icon-sgd"> or 复制网址手动打开</span>
</div>

<script type="text/javascript" src="//lib.baomitu.com/jquery/3.6.0/jquery.min.js"></script>
<script type="text/javascript" src="//lib.baomitu.com/layer/3.1.1/layer.min.js"></script>
<script src="/static/clipboard/clipboard.js"></script>
<a data-clipboard-text="' . $siteurl . '" class="app-download-btn btn"  >复制网址</a>
<a href="#" class="btn" id="jump">跳转浏览器</a>
<script type="text/javascript">new ClipboardJS(".app-download-btn");</script>
<script>
$(".app-download-btn").click(function() {
layer.msg("复制成功", function(){
      //关闭后的操作
});})
$("#jump").click(function (){
    var url = "' . $siteurl . '";
    window.location.href = "mttbrowser://url="+url;
    window.location.href = "ucbrowser://"+url;
    window.location.href = "mibrowser:"+url;
	window.location.href = "baiduboxapp://browse?url="+url;
	window.location.href = "googlechrome://browse?url="+url;
});
document.getElementById("jump").click();
</script>

<body>
</html>');
    }
}

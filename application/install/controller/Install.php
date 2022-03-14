<?php

namespace app\install\controller;

use think\Request;
use think\Controller;
use think\Db;
use think\Route;
use think\Url;
use think\Validate;
use think\Session;


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
class Install extends Controller
{
    //首页
    public function Index(Request $request)
    {
        //================页面载入================
        if (is_file(APP_PATH . 'install/install.lock')) {
            weuiMsg('info', '您已经安装过程序了，无需重复安装！<br/>如需重装系统，请删除 <span style="color:#f00;">根目录/application/install/install.lock</span> 的文件', '已安装', true);
            exit();
        }
        $get = $request->get();
        if (!array_key_exists("do", $get)) {
            $do = "";
        } else {
            $do = trim($get['do']);
        }
        $data = [];
        switch ($do) {
            case "":
                $progress = 0;
                $subtitle = "";
                $data = [
                    "VERSION" => VERSION,
                    "BUILD" => BUILD
                ];
                break;
            case "1":
                $progress = 10;
                $subtitle = "更新日志";
                break;
            case "2":
                $progress = 20;
                $subtitle = "检查环境";

                $data = [
                    'phpversion' => PHP_VERSION,
                    'curl_exec' => checkfunc('curl_exec', true),
                    'php_openssl' => checkfunc('openssl_open', true),
                    'mysql' => "自测",
                    'file_get_contents' => checkfunc('file_get_contents', true),
                    'fsockopen' => checkfunc('fsockopen'),
                    'imagecreate' => checkfunc('imagecreate', true)
                ];
                break;
            case "3":
                $progress = 40;
                $subtitle = "数据库配置";
                break;
            case "4":
                error_reporting(0);//关闭报错
                $progress = 60;
                $subtitle = "保存配置";
                if (!array_key_exists('j', $get)) {
                    $array = $request->post();
                }else{
                    $array = [
                        "hostname" => config('database.hostname'),
                        "database" => config('database.database'),
                        "username" => config('database.username'),
                        "password" => config('database.password'),
                        "hostport" => config('database.hostport')
                    ];
                }
                $validate = new Validate([
                    ['private_key', 'require', '授权私钥不能为空'],
                    ['hostname', 'require', '服务器地址不能为空'],
                    ['database', 'require', '数据库名称不能为空'],
                    ['username', 'require', '数据库用户名不能为空'],
                    ['password', 'require', '数据库密码不能为空'],
                    ['hostport', 'require', '数据库端口不能为空']
                ]);
                if (!$validate->check($array)) {
                    $data = [
                        "code" => 1,
                        "msg" => $validate->getError()
                    ];
                    $this->assign('data', $data);  //输出变量
                    $this->assign('subtitle', $subtitle);  //输出变量
                    $this->assign('progress', $progress);  //输出变量
                    $this->assign('do', $do);  //输出变量
                    return $this->fetch('index/index');  //进入模板
                }
                $link = mysqli_connect(trim($array['hostname']), trim($array['username']), trim($array['password']), trim($array['database']), trim($array['hostport']));
                if(!$link){
                    $data = [
                        "code" => 1,
                        "msg" => mysqli_connect_error()
                    ];
                }else {
                    $database = "<?php
    return [
    // 聚合收银台 - 授权私钥【请联系售卖你授权的人索要私钥，每个授权都有一个自己的授权私钥。请勿泄露自己的私钥，否则平台将（不退款）取消你的授权！】
    'private_key'     => '".trim($array['private_key'])."',
    // 数据库类型
    'type'            => 'mysql',
    // 服务器地址
    'hostname'        => '".trim($array['hostname'])."',
    // 数据库名
    'database'        => '".trim($array['database'])."',
    // 用户名
    'username'        => '".trim($array['username'])."',
    // 密码
    'password'        => '".trim($array['password'])."',
    // 端口
    'hostport'        => '".trim($array['hostport'])."',
    // 连接dsn
    'dsn'             => '',
    // 数据库连接参数
    'params'          => [],
    // 数据库编码默认采用utf8
    'charset'         => 'utf8',
    // 数据库表前缀
    'prefix'          => 'xy_cashier_',
    // 数据库调试模式
    'debug'           => true,
    // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
    'deploy'          => 0,
    // 数据库读写是否分离 主从式有效
    'rw_separate'     => false,
    // 读写分离后 主服务器数量
    'master_num'      => 1,
    // 指定从服务器序号
    'slave_no'        => '',
    // 自动读取主库数据
    'read_master'     => false,
    // 是否严格检查字段是否存在
    'fields_strict'   => true,
    // 数据集返回类型
    'resultset_type'  => 'array',
    // 自动写入时间戳字段
    'auto_timestamp'  => false,
    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',
    // 是否需要进行SQL性能分析
    'sql_explain'     => false
];";
                    if (file_put_contents(ROOT . 'application/database.php', $database)) {
                        $data = [
                            "code" => 0,
                            "msg" => "恭喜你，数据库信息配置成功！"
                        ];
                    }

                    if(mysqli_query($link,"SHOW TABLES LIKE 'xy_cashier_core'")){
                        $data['istab'] = 1;
                    }else{
                        $data['istab'] = 0;
                    }
                }
                break;
            case "5":
                error_reporting(0);//关闭报错
                $progress = 80;
                $subtitle = "导入数据";
                $array = [
                    "hostname" => config('database.hostname'),
                    "database" => config('database.database'),
                    "username" => config('database.username'),
                    "password" => config('database.password'),
                    "hostport" => config('database.hostport')
                ];
                $link = mysqli_connect($array['hostname'], $array['username'], $array['password'], $array['database'], $array['hostport']);
                if(!$link){
                    $data = [
                        "code" => 1,
                        "msg" => "【数据库连接失败】".mysqli_connect_error()
                    ];
                    $this->assign('data', $data);  //输出变量
                    $this->assign('subtitle', $subtitle);  //输出变量
                    $this->assign('progress', $progress);  //输出变量
                    $this->assign('do', $do);  //输出变量
                    return $this->fetch('index/index');  //进入模板
                }
                if (array_key_exists('j', $get)) {
                    $tab_arr = Db::query("SELECT concat('DROP TABLE IF EXISTS ', table_name, ';') FROM information_schema.tables WHERE table_schema = '".$array['database']."'");
                    foreach ($tab_arr as $tab_sql){
                        $tab_sql = $tab_sql["concat('DROP TABLE IF EXISTS ', table_name, ';')"];
                        Db::query($tab_sql);
                    }
                }

                $sql_data = sqlImplement(APP_PATH . "install/install.sql",$array);
                if($sql_data['code']!=0){
                    $data['code'] = 1;
                    $data['msg'] = $sql_data['msg'];
                }else{
                    $data['s'] = $sql_data['s'];
                    $data['t'] = $sql_data['t'];
                    $data['e'] = $sql_data['e'];
                    $data['error'] = $sql_data['error'];
                }
                break;
            case "6":
                file_put_contents(APP_PATH . "install/install.lock",'安装锁【星益云聚合收银台系统】');
                $progress = 100;
                $subtitle = "安装完成";
                break;
            default:

        }
        $this->assign('data', $data);  //输出变量
        $this->assign('subtitle', $subtitle);  //输出变量
        $this->assign('progress', $progress);  //输出变量
        $this->assign('do', $do);  //输出变量
        return $this->fetch('index/index');  //进入模板

        //================页面载入================
    }
}

<?php

namespace app\admin\controller;

use think\Request;
use think\Controller;
use think\Db;
use think\Route;
use think\Url;
use think\Validate;
use ZipArchive;

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
class Update extends Controller
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

        $data = [
            'VERSION'=>VERSION,
            'BUILD' =>BUILD,
        ];
        $this->assign('data', $data);
        $this->assign('title', "检查更新");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/update');  //进入模板
    }
    public function getUpdate()
    {
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] != 0) {
            return json_encode(['code' => 1, 'msg' => $loginCheck['msg']]);
        }

        $core = $loginCheck['data'];
        //======验证登录======

//======检测新版本
        if(!$curl_json = curl_get("http://auth.96xy.cn/api/cashier?kaiyuanban&mode=getupdate&current_build=".BUILD)){
            return json_encode(['code' => 1, 'msg' => "授权中心服务器异常"]);
        }
        if(!$curl_arr = json_decode($curl_json,true)){
            return json_encode(['code' => 1, 'msg' => "解析数据错误"]);
        }
        if($curl_arr['code']!=0){
            return json_encode(['code' => 1, 'msg' => $curl_arr['msg']]);
        }else{
            if($curl_arr['data']['build']==BUILD){
                return json_encode(['code' => 0, 'msg' => "无需更新","update"=>0,'data'=>$curl_arr['data'],"current_build"=>BUILD,"current_version"=>VERSION]);
            }else{
               if($curl_arr['data']['build']>BUILD){
                   //需要更新
                   return json_encode(['code' => 0, 'msg' => "有版本需要更新","update"=>1,'data'=>$curl_arr['data'],"current_build"=>BUILD,"current_version"=>VERSION,"uplist"=>$curl_arr['uplist']]);
               } else if($curl_arr['data']['build']<BUILD){
                   return json_encode(['code' => 1, 'msg' => "您的程序版本有误，请重新下载安装！"]);
               }
            }
        }
//======检查新版本
    }
    //更新
    public function update()
    {
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] != 0) {
            return json_encode(['code' => 1, 'msg' => $loginCheck['msg'],"current_build"=>BUILD,"current_version"=>VERSION]);
        }

        $core = $loginCheck['data'];
        //======验证登录======

        $getupdate = $this->getUpdate();
        if(!$getupdate = json_decode($getupdate,true)){
            return json_encode(['code' => 1, 'msg' => "解析数据错误","current_build"=>BUILD,"current_version"=>VERSION]);
        }
        if($getupdate['code']==0){
            if($getupdate['update']==1){
                if(count($getupdate['uplist'])<1){
                    return json_encode(['code' => 101, 'msg' => "已是最新版本","uplist"=>$getupdate['uplist'],"current_build"=>BUILD,"current_version"=>VERSION]);
                }
                $i=0;
                foreach ($getupdate['uplist'] as $key=>$value){
                    if($i==0){
                        $build = $key;
                    }
                    $i++;
                }
                $arr = $this->up($build);
                if($arr['code']==0){
                    return json_encode(['code' => 0, 'msg' =>$arr['msg'],"upliet"=>$getupdate['uplist'],"current_build"=>BUILD,"current_version"=>VERSION]);
                }else{
                    return json_encode(['code' => 1, 'msg' =>$arr['msg'],"current_build"=>BUILD,"current_version"=>VERSION]);
                }
            }else{
                return json_encode(['code' => 101, 'msg' =>"已是最新版本","upliet"=>$getupdate['uplist'],"current_build"=>BUILD,"current_version"=>VERSION]);
            }
        }else{
            return json_encode(['code' => 1, 'msg' => $getupdate['msg'],"current_build"=>BUILD,"current_version"=>VERSION]);
        }
    }
    private function up($build)
    {
        //更新
        if(!$curl_json = curl_get("http://auth.96xy.cn/api/cashier?kaiyuanban&mode=update&build=".$build)){
            return ['code' => 1, 'msg' => "授权中心服务器异常"];
        }
        if(!$curl_arr = json_decode($curl_json,true)){
            return ['code' => 1, 'msg' => "解析数据错误"];
        }
        if($curl_arr['code']!=0){
            return ['code' => 1, 'msg' => $curl_arr['msg']];
        }else{
            if(trim($curl_arr['url'])==""){
                return ['code' => 1, 'msg' => "无法更新，更新包地址为空，请联系开发者处理！"];
            }
        }
        //更新
        $arr=parse_url($curl_arr['url']);
        $fileName=basename($arr['path']);
        if(substr(strrchr($fileName, '.'), 1)!="zip"){
            return ['code' => 1, 'msg' => "无法更新，更新包不是压缩包，请联系开发者处理！"];
        }else{
            $fileName = createTradeNo("update_").".zip";
        }
        $file=file_get_contents($curl_arr['url']);
        file_put_contents('./'.$fileName,$file);


        $zip_path = ROOT.'public/'.$fileName;
        $zip = new ZipArchive;

        if ($zip->open($zip_path) === TRUE) {  //中文文件名要使用ANSI编码的文件格式
            $zip->extractTo('../');  //提取全部文件
            $zip->close();
            unlink ($zip_path);

            $sql_path = ROOT.'upsql.sql';
            if($curl_arr['is_sql']==1){
                //需要更新数据库
                $array = [
                    "hostname" => config('database.hostname'),
                    "database" => config('database.database'),
                    "username" => config('database.username'),
                    "password" => config('database.password'),
                    "hostport" => config('database.hostport')
                ];
                $arr = sqlImplement($sql_path,$array);
                if($arr['code']!=0){
                    return ['code' => 1, 'msg' => "<b>程序更新完毕！</b><br/>【".$arr['msg']."】<br/>数据库导入失败，请手动导入！<br/>SQL文件位置：根目录/".'upsql.sql'];
                }else{
                    if($arr['s']!=$arr['t']){
                        return ['code' => 1, 'msg' => "<b>程序更新完毕！</b><br/>数据库导入状态：共".$arr['s']."条sql语句，导入成功".$arr['t']."条，导入失败".$arr['e']."条。<br/>SQL文件位置：根目录/".'upsql.sql'];
                    }else{
                        unlink ($sql_path);
                        return ['code' => 0, 'msg' => "更新完成，数据库全部导入成功！"];
                    }
                }
            }

            return ['code' => 0, 'msg' => "更新完成"];
        } else {
            unlink ($zip_path);
            return ['code' => 1, 'msg' => "更新失败"];
        }

    }
}

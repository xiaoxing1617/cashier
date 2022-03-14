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
class Links extends Controller
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

        $count = count($core['links']);
        $this->assign('count', $count);  //链接数量
        $this->assign('title', "友情链接");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/links');  //进入模板
    }

    public function edit(Request $request)
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
        if (!array_key_exists("sum", $postArray)) {
            return json_encode(['code' => 1, 'msg' => '未知的友情链接总数，请重新刷新页面！']);
        }
        $sum = intval($postArray['sum']);
        if ($sum <= 0) {
            return json_encode(['code' => 1, 'msg' => '没有要保存的链接，请先添加一个友情链接！']);
        }
        $arr = [];
        $arr_list = [];
        for ($i = 0; $i < $sum; $i++) {
            if(isset($arr[intval($postArray['px_' . ($i+1)])])){
                return json_encode(['code' => 1, 'msg' => '排序不能重复哦！']);
            }
            $arr[intval($postArray['px_' . ($i+1)])] = array(
                'name' => $postArray['name_' . ($i+1)],
                'href' => $postArray['href_' . ($i+1)]
            );
        }
        ksort($arr);
        //修改
        $temp = new Core;
        $arr = [
            "links" => json_encode(array_values($arr)),
        ];
        $i = 0;
        foreach ($arr as $k => $v) {
            $num = $temp->where('name', $k)->update(['value1' => $v]);
            if ($num == 1) {
                $i++;
            }
        }

        return json_encode(['code' => 0, 'msg' => "保存成功"]);
    }

    public function add(Request $request)
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
        if(!array_key_exists("name",$postArray) or !array_key_exists("href",$postArray)){
            return json_encode(['code' => 1, 'msg' => '网站名称和网站链接不能为空！']);
        }
        $name = trim($postArray['name']);
        $href = trim($postArray['href']);
        if($name=="" or $href==""){
            return json_encode(['code' => 1, 'msg' => '网站名称和网站链接不能为空！']);
        }
        if (isset($core['links'])) {
            $links_json = $core['links'];
            if (!$links_arr = json_decode($links_json, true)) {
                $links_arr = [];
            }
        } else {
            $links_arr = [];
        }
        $links_arr[] = array(
            "name"=>$name,
            "href"=>$href
        );

        //修改
        $temp = new Core;
        $arr = [
            "links" => json_encode($links_arr),
        ];
        $i = 0;
        foreach ($arr as $k => $v) {
            $num = $temp->where('name', $k)->update(['value1' => $v]);
            if ($num == 1) {
                $i++;
            }
        }
        if (count($arr) == $i) {
            return json_encode(['code' => 0, 'msg' => "添加成功"]);
        } else {
            return json_encode(['code' => 1, 'msg' => "添加失败"]);
        }
    }
}
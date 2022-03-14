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
use app\model\model\IllegalRecord;

//引入IllegalRecord模型
class Illegal extends Controller
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


        $mode_list = IllegalRecord::getModeName("", true);

        $this->assign('mode_list', $mode_list);  //违规类型

        $this->assign('title', "违规记录");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/illegal');  //进入模板
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

        if (!array_key_exists("type", $postArray)) {
            $type = "all";
        } else {
            $type = $postArray['type'];
            if (trim($type) == "all") {
                $type = "all";
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
                $sql_data = IllegalRecord::where("id", "<>", "0");
                $sql_count = IllegalRecord::where("id", "<>", "0");
                break;
            case "content":
                $sql_data = IllegalRecord::where("id", "<>", "0")->where("content", 'like', "%{$value}%");
                $sql_count = IllegalRecord::where("id", "<>", "0")->where("content", 'like', "%{$value}%");
                break;
            case "words":
                $sql_data = IllegalRecord::where("id", "<>", "0")->where("words", 'like', "%{$value}%");
                $sql_count = IllegalRecord::where("id", "<>", "0")->where("words", 'like', "%{$value}%");
                break;
            case "uid":
                $sql_data = IllegalRecord::where("id", "<>", "0")->where("uid", $value);
                $sql_count = IllegalRecord::where("id", "<>", "0")->where("uid", $value);
                break;
            default:
                return exit(json_encode(['code' => 1, 'msg' => '不支持该查询类型']));
        }
        if ($type != "all") {
            $count = $sql_count->where("mode", $type)->count();
            $data = $sql_data->where("mode", $type)->order('id desc')->page($page, $num)->select();
        } else {
            $count = $sql_count->count();
            $data = $sql_data->order('id desc')->page($page, $num)->select();
        }

        foreach ($data as $v) {
            $v['mode'] = $v->getModeName();
            $v['source'] = $v->getSourceName();
            $user = User::getByUid($v['uid']);
            if (!$user) {
                $v['user_state'] = -1;
            } else {
                $v['user_state'] = $user['state'];
            }
        }
        return exit(json_encode(['code' => 0, 'msg' => '成功', "data" => $data]));
    }
}
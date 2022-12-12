<?php

namespace app\user\controller;

use think\Request;
use think\Validate;
use think\Controller;
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
use app\model\model\Login;

//引入Login模型
use app\model\model\Service;

//引入Service模型
class Renew extends Controller
{
    public function Index()
    {
        $this->assign('nav_active', "renew");  //nav名称
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
        $s_user = User::getByUid($core['system_uid']);  //收款商户信息

        //=====商户校验=====
        $merchantCheck = merchantCheck($core['system_uid'], true);
        if ($merchantCheck['code'] != 0) {
            weuiMsg('info', $merchantCheck['msg']);
            return;
        }
        //=====商户校验=====
        $accessMode = getAccessMode();
        switch ($accessMode){
            case "inside":
                //=============内置浏览器
                $client = getClientkeyword();  //获取客户端打开方式
                $client_arr = getPayList($client, 'client_keyword');//支付方式信息
                if(!$client_arr['switch']){
                    weuiMsg('info', '系统已关闭【'.$client_arr['client_name'].'】客户端支付方式', '抱歉');
                    return;
                }
                $pay_interface_array = $s_user->getPayInterfaceArray();
                $pay_interface_id = $s_user->getPreUsePayInterfaceId($pay_interface_array, $client_arr['alias']);
                if ($pay_interface_id == 0 or $pay_interface_id == '') {
                    weuiMsg('info', '平台管理员商户未开启【' . $client_arr['client_name'] . '】续费支付方式，请使用其他支付方式续费！', '不支持'.$client_arr['client_name'].'付款');
                    return;
                }
                $this->assign('msg', '您当前在【'.$client_arr['client_name'].'】客户端打开了页面，将使用<span style="color:'.$client_arr['color'].'">'.$client_arr['name'].'</span>发起支付。如需使用其他支付方式，请切换至其他支付平台或在浏览器打开此页！');  //支付信息
                $client_arr['active'] = 1;
                $this->assign('pay_list',  array($client_arr));  //输出变量
                break;
            case ($accessMode=="pc" || $accessMode=="external"):
                //=================电脑访问
                //=================电脑访问
                $pay_list = getPayList();//支付方式信息
                $pay_interface_array = $s_user->getPayInterfaceArray();
                $i=0;
                foreach ($pay_list as $k=>$v){
                    if($v['switch']){
                        $pay_interface_id = $s_user->getPreUsePayInterfaceId($pay_interface_array, $v['alias']);
                        if ($pay_interface_id == 0 or $pay_interface_id == '') {
                            $pay_list[$k]['switch'] = false;
                        }else{
                            if($i==0){
                                $pay_list[$k]['active'] = 1;
                            }
                            $i++;
                        }
                    }
                }
                if ($i <= 0) {
                    weuiMsg('info', '该商户已将所有支付方式全部关闭，请等待商户开启！', '抱歉');
                    return;
                }
                $this->assign('msg', '您当前在浏览器打开，可任意选择支付方式进行续费！');  //支付信息
                $this->assign('pay_list',  $pay_list);  //输出变量
                break;
            default:
                weuiMsg('warn-primary', '非法访问方式');
                return;
        }

        $pay_interface_array = $user->getPayInterfaceArray();
        $merchantCheck = merchantCheck($user['id']);
        if ($merchantCheck['code'] != 0) {
            $user['expiration_time'] = "<span style='color: #f00'>" . $merchantCheck['msg'] . "</span>";
        } else {
            $user['expiration_time'] = "【正常】" . $merchantCheck['date'] . "到期";
        }

        $user['qq_nickname'] = getQQnickname($user['qq']);
        $this->assign('title', "续费服务");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/renew');  //进入模板
    }

    public function collection(Request $request)
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
            ['month', 'require|integer|gt:0', '请先填写续费时长|续费时长只能是正整数|至少续费1个月'],
            ['type', 'require', '请选择支付方式'],
            ['__token__', 'require|token', ""]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if ($postArray['month'] <= 0) {
            return json_encode(['code' => 1, 'msg' => "至少续费1个月"]);
        }
        if ($postArray['month'] > 24) {
            return json_encode(['code' => 1, 'msg' => "最多续费24个月"]);
        }
        $pay_list = getPayList();
        foreach ($pay_list as $value) {
            $pay_array[] = $value['alias'];
        }
        if (!in_array($postArray['type'], $pay_array)) {
            return json_encode(['code' => 1, 'msg' => "不存在此支付方式"]);
        }


        $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
        $collection_cost = $core['collection_cost'];
        $month = $postArray['month'];
        $money = number_format(round(($collection_cost * $month), 2), 2, ".", "");

        if ($money <= 0 or $collection_cost <= 0) {
            $arr = merchantCheck($user['uid'], true);
            if ($arr['code'] == 0) {
                $date = $arr['date'];
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $temp = User::getByUid($user['uid']);
            $temp->expiration_time = date("Y-m-d H:i:s", strtotime("+" . $month . " months", strtotime($date)));
            $temp->save();
            if ($temp->save() !== false) {
                $url = '/user/renew';
                return json_encode(['code' => 0, 'msg' => "续费成功", "url" => $url]);
            } else {
                return json_encode(['code' => 1, 'msg' => "续费失败"]);
            }
        }

        $service_trade_no = createTradeNo();
        $temp = new Service;
        $temp->uid = $user['uid'];
        $temp->service_trade_no = $service_trade_no;
        $temp->money = $money;
        $temp->type = $postArray['type'];
        $temp->mode = "collection";
        $temp->creation_time = date('Y-m-d H:i:s');
        $temp->month = $month;

        if ($temp->save() !== false) {
            $url = $request->domain() . '/submit/?mod=service&service_trade_no=' . $service_trade_no . '&__token__=' . $postArray['__token__'];
            return json_encode(['code' => 0, 'msg' => "正在前往支付", "url" => $url]);
        } else {
            return json_encode(['code' => 1, 'msg' => "续费订单创建失败"]);
        }
    }
}

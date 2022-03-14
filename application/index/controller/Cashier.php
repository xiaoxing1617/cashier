<?php

namespace app\index\controller;

use think\Request;
use think\Controller;
use think\Db;
use think\Route;
use think\Url;

use app\model\model\User;
use app\model\model\Core;

//引入User模型

class Cashier extends Controller
{
    public function cashier(Request $request)
    {
        $getArray = $request->get();
        $routeArray = $request->route();
        if (!array_key_exists('code', $getArray)) {
            if (!array_key_exists('code', $routeArray)) {
                weuiMsg('warn-primary', '请扫描商户收款码进入收银台');
                return;
            } else {
                $code = trim($routeArray['code']);  //商户代码
            }
        } else {
            $code = trim($getArray['code']);  //商户代码
        }
        if ($code == "") {
            weuiMsg('warn-primary', '错误的商户收款码');
            return;
        } else {
            $sql = User::getByCode($code);  //获取商户信息
            if ($sql) {
                if (isTemplateName('cashier', $sql['cashier_template'])) {
                    $template = $sql['cashier_template'];  //收银台模板
                } else {
                    $template = 'default';  //收银台模板
                }
            } else {
                weuiMsg('warn-primary', '商户收款码不存在');
                return;
            }
        }

        //=====商户校验=====
        $merchantCheck = merchantCheck($sql['id']);
        if ($merchantCheck['code'] != 0) {
            weuiMsg('info', $merchantCheck['msg']);
            return;
        }
        //=====商户校验=====
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        $this->assign('title', "收银台");  //输出变量
        $this->assign('core', $core);  //输出变量
        //预选金额
        if(trim($sql['preselection_money'])=="" or $sql['preselection_money']==null){
            $preselection_switch = false;
        }else{
            $sql['preselection_money'] = array_filter(explode("|",$sql['preselection_money']));
            if(count($sql['preselection_money'])<=0){
                $preselection_switch = false;
            }else{
                $preselection_switch = true;
            }
        }
        $accessMode = getAccessMode();
        switch ($accessMode){
            case "inside":
                //=============内置浏览器
                $client = getClientkeyword();  //获取客户端打开方式
                $client_arr = getPayList($client, 'client_keyword');//支付方式信息
                if(!$client_arr['switch']){
                    weuiMsg('info', '系统已关闭【'.$client_arr['client_name'].'】支付方式', '抱歉');
                    return;
                }
                $pay_interface_array = $sql->getPayInterfaceArray();
                $pay_interface_id = $sql->getPreUsePayInterfaceId($pay_interface_array, $client_arr['alias']);
                if ($pay_interface_id == 0 or $pay_interface_id == '') {
                    weuiMsg('info', '该商户未开启【' . $client_arr['client_name'] . '】支付方式，请使用其他支付方式！', '抱歉');
                    return;
                }
                $client_arr['active'] = 1;
                $page_data = [
                    'info' => $sql,//商户信息
                    'pay_list' => array($client_arr),
                    'color'=>$client_arr['color'],
                    'preselection_switch'=>$preselection_switch
                ];  //页面数据
                $this->assign('data', $page_data);  //输出变量
                return $this->fetch('cashier/' . $template . '/index');  //进入模板
                break;
            case ($accessMode=="pc" || $accessMode=="external"):
                //=================电脑访问
                $pay_list = getPayList();//支付方式信息
                $pay_interface_array = $sql->getPayInterfaceArray();
                $i=0;
                foreach ($pay_list as $k=>$v){
                    if($v['switch']){
                        $pay_interface_id = $sql->getPreUsePayInterfaceId($pay_interface_array, $v['alias']);
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
                //电脑打开
                $page_data = [
                    'info' => $sql,//商户信息
                    'pay_list'=>$pay_list,
                    'color'=>"#6e00ff",
                    'preselection_switch'=>$preselection_switch
                ];  //页面数据
                $this->assign('data', $page_data);  //输出变量
                return $this->fetch('cashier/' . $template . '/'.($accessMode=="pc"?"pc":"index"));  //进入模板
                break;
            default:
                weuiMsg('warn-primary', '非法访问方式');
                return;
        }
    }
}
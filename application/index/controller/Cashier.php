<?php

namespace app\index\controller;

use app\model\model\FixedAmount;
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
                weuiMsg('warn-primary', '请扫描商户收款码进入收银台','',false);
                return;
            } else {
                $code = trim($routeArray['code']);  //商户代码
            }
        } else {
            $code = trim($getArray['code']);  //商户代码
        }
        if ($code == "") {
            weuiMsg('warn-primary', '错误的商户收款码','',false);
            return;
        }

        $this->assign('code', $code);  //输出变量

        $is_fixed_amount = false;
        $pay_type_list = [];
        $FixedAmount = FixedAmount::getByCode($code);  //获取固额码信息
        if($FixedAmount){
            $is_fixed_amount = true;
            $sql = User::getByUid($FixedAmount['uid']?$FixedAmount['uid']:"0");  //获取商户信息
            //固额码验证
            if($FixedAmount['money']<1){
                weuiMsg('warn-primary', '收款金额不能小于0.01元','',false);
                return;
            }
            if(time()>=strtotime($FixedAmount['end_time'])){
                weuiMsg('warn-primary', '收款码已过期，请使用最新的收款码进行支付！','',false);
                return;
            }
            $pay_type_list = explode("|",$FixedAmount['pay_type']);
            $pay_type_list = array_unique($pay_type_list);  //去重
            $pay_type_list = array_filter($pay_type_list);  //去空
            if(empty($pay_type_list)){
                weuiMsg('warn-primary', '收款码未设置收款方式限定，请联系商家设置后再支付！','',false);
                return;
            }else{
                $pay_type_name_list = "";
                foreach ($pay_type_list as $item){
                    $tmp_client_arr = getPayList($item, 'alias');//支付方式信息
                    if(empty($tmp_client_arr)){
                        weuiMsg('warn-primary', '收款码设置存在非法支付方式，请联系商家设置后再支付！','',false);
                        return;
                    }
                    $pay_type_name_list .= $tmp_client_arr['client_name']."支付、";
                }
                $pay_type_name_list = trim($pay_type_name_list,"、");
            }
        }else{
            $FixedAmount = [];
            $sql = User::getByCode($code);  //获取商户信息
        }
        if ($sql) {
            if (isTemplateName('cashier', $sql['cashier_template'])) {
                $template = $sql['cashier_template'];  //收银台模板
            } else {
                $template = 'default';  //收银台模板
            }
        } else {
            weuiMsg('warn-primary', '收款码不存在','',false);
            return;
        }


        //=====商户校验=====
        $merchantCheck = merchantCheck($sql['id']);
        if ($merchantCheck['code'] != 0) {
            weuiMsg('info', $merchantCheck['msg'],'',false);
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

        $page_grey = explode("|",$core['page_grey']);
        if(in_array("cashier",$page_grey)){
            //置灰
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
        $accessMode = getAccessMode();
        switch ($accessMode){
            case "inside":
                //=============内置浏览器
                $client = getClientkeyword();  //获取客户端打开方式
                $client_arr = getPayList($client, 'client_keyword');//支付方式信息
                if(!$client_arr['switch']){
                    weuiMsg('info', '系统已关闭【'.$client_arr['client_name'].'】支付方式', '不支持'.$client_arr['client_name'].'付款',false);
                    return;
                }
                $pay_interface_array = $sql->getPayInterfaceArray();
                if($is_fixed_amount) {
                    if (!in_array($client_arr['alias'], $pay_type_list)) {
                        weuiMsg('info', '该收款不支持【' . $client_arr['client_name'] . '】支付方式，请使用：'.$pay_type_name_list, '不支持' . $client_arr['client_name'] . '付款',false);
                        return;
                    }
                }
                $pay_interface_id = $sql->getPreUsePayInterfaceId($pay_interface_array, $client_arr['alias']);
                if ($pay_interface_id == 0 or $pay_interface_id == '') {
                    weuiMsg('info', '该商户未开启【' . $client_arr['client_name'] . '】支付方式，请使用其他支付方式！', '不支持'.$client_arr['client_name'].'付款',false);
                    return;
                }
                $client_arr['active'] = 1;
                $page_data = [
                    'info' => $sql,//商户信息
                    'pay_list' => array($client_arr),
                    'color'=>$client_arr['color'],
                    'preselection_switch'=>$preselection_switch,
                    "is_fixed_amount"=>$is_fixed_amount,  //是否为固额码
                    "fixed_amount"=>$FixedAmount,  //固额码数据
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
                            if($is_fixed_amount) {
                                if(!in_array($v['alias'], $pay_type_list)){
                                    $pay_list[$k]['switch'] = false;
                                    continue;
                                }
                            }
                            if($i==0){
                                $pay_list[$k]['active'] = 1;
                            }
                            $i++;
                        }
                    }
                }
                if ($i <= 0) {
                    weuiMsg('info', '该商户已将所有支付方式全部关闭，请等待商户开启！', '抱歉',false);
                    return;
                }
                //电脑打开
                $page_data = [
                    'info' => $sql,//商户信息
                    'pay_list'=>$pay_list,
                    'color'=>"#6e00ff",
                    'preselection_switch'=>$preselection_switch,
                    "is_fixed_amount"=>$is_fixed_amount,  //是否为固额码
                    "fixed_amount"=>$FixedAmount,  //固额码数据
                ];  //页面数据
                $this->assign('data', $page_data);  //输出变量
                return $this->fetch('cashier/' . $template . '/'.($accessMode=="pc"?"pc":"index"));  //进入模板
                break;
            default:
                weuiMsg('warn-primary', '非法访问方式','',false);
                return;
        }
    }
}

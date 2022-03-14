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

class CashierSet extends Controller
{
    public function Index()
    {
        $this->assign('nav_active', "cashier_set");  //nav名称
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


        $cashier_arr = getTemplateList("cashier");
        $this->assign('cashier_arr', $cashier_arr);  //收银台模板列表
        $this->assign('title', "收银台配置");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        return $this->fetch('default/cashier_set');  //进入模板
    }
    public function setInfo(Request $request){
        //======验证登录======
        $controller = controller('Index');
        $loginCheck = $controller->loginCheck();
        if ($loginCheck['code'] == -1) {
            return json_encode(['code' => 1, 'msg' => $loginCheck['msg'],"url"=>$loginCheck['url']]);
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
            ['cashier_template','require','请选择一个收银台模板'],
            ['payment_mode', 'number|between:1,2', '请正确选择支付商品名称规则|非法的支付商品名称规则']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        if(!isTemplateName('cashier',$postArray['cashier_template'])){
            return json_encode(['code' => 1, 'msg' => "不存在此收银台模板"]);
        }
        if(!array_key_exists("preselection_money",$postArray)){
            return json_encode(['code' => 1, 'msg' => "请传入预选金额参数"]);
        }
        if(trim($postArray['preselection_money'])=="" or $postArray['preselection_money']==NULL){
            $postArray['preselection_money'] = null;
        }else{
            $arr = explode("|",$postArray['preselection_money']);
            $arr = array_filter($arr);
            if(count($arr)>6){
                return json_encode(['code' => 1, 'msg' => "预选金额最多设置6个哦！"]);
            }
            foreach ($arr as $k=>$v){
                $money = number_format(round($v, 2), 2, ".", "");
                if ($money <= 0 || !is_numeric($v) || !preg_match('/^[0-9.]+$/', $v)) {
                    return json_encode(['code' => 1, 'msg' => "预选金额不合法！"]);
                }
                $arr[$k] = $money;
            }
            $postArray['preselection_money'] = implode("|",$arr);
        }
        if(array_key_exists("payment_name",$postArray) && $postArray['payment_mode']=="2"){
            if(trim($postArray['payment_name'])==""){
                return json_encode(['code' => 1, 'msg' => "自义定名称不能为空！"]);
            }
            $payment_name = trim($postArray['payment_name']);
            $arr = explode("|",$postArray['payment_name']);
            $arr = array_filter($arr);
            if(count($arr)<=0){
                return json_encode(['code' => 1, 'msg' => "至少设置一个自义定名称！"]);
            }
            $postArray['payment_name'] = implode("|",$arr);
        }else{
            $payment_name = $user['payment_name'];
        }

        $temp = User::getById($user['id']);
        $temp->preselection_money = $postArray['preselection_money'];
        $temp->cashier_template = $postArray['cashier_template'];
        $temp->payment_mode = $postArray['payment_mode'];
        $temp->payment_name = $payment_name;
        if($temp->save() !== false){
            return json_encode(['code' => 0, 'msg' => "配置成功"]);
        }else {
            return json_encode(['code' => 1, 'msg' => "配置失败"]);
        }

    }
}
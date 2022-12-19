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
class Info extends Controller
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

        $this->assign('home_template_array', getTemplateList('home_css'));  //后台管理css模板
        $arr = Core::getCaptchaSwitch(null,true);
        $core['captcha_switch'] = [];
        foreach ($arr as $v){
            if(Core::getCaptchaSwitch($v)){
                $core['captcha_switch'][$v] = 1;
            }else{
                $core['captcha_switch'][$v] = 0;
            }
        }

        $payment_code_arr = getPaymentCodeList();
        $this->assign('payment_array', $payment_code_arr);  //模板列表
        $this->assign('title', "基础信息");  //标题
        $this->assign('core', $core);  //系统配置
        return $this->fetch('default/info');  //进入模板
    }

    public function setInfo(Request $request)
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
        $validate = new Validate([
            ['title', 'require|length:2,16|chsDash', '请填写网站标题|网站标题只能设置2-16个字|网站标题只能是汉字、字母、数字和下划线(_)及破折号(-)'],
            ['subtitle', 'require|length:2,26|chsDash', '请填写网站小标题|网站小标题只能设置2-26个字|网站小标题只能是汉字、字母、数字和下划线(_)及破折号(-)'],
            ['keyword', 'require|length:2,56', '请填写网站关键字|网站关键字只能设置2-56个字'],
            ['describe', 'require|length:4,350', '填填写网站描述|网站描述只能设置4-350个字'],
            ['qq', 'require|length:6,11|number', '站长QQ不能为空|请正确填写站长QQ|请正确填写站长QQ'],
            ['bottom_info', 'require', '底部信息不能为空'],
            ['register_open', 'number|between:0,1', '请正确选择注册开关|非法的注册开关'],
            ['veloce_qqlogin', 'number|between:0,1', '请正确选择QQ快捷登录开关|QQ快捷登录的注册开关'],
            ['home_template', 'require', '请选择一个后台css样式'],
            ['order_prefix', 'alphaNum|length:2,3', '订单号前缀只能是数字和字母|订单号前缀只能设置2-3个字符'],
            ['collection_cost', 'require', '请填写收款服务能力续费价格'],
            ['system_uid', 'require|number', '续费收款商户UID不能为空|请正确填写续费收款商户UID'],
            ['free_collection_service_days', 'require|integer', '请填写注册赠送天数|注册赠送天数只能是整数'],
            ['demo_open', 'number|between:0,1', '请正确选择演示开关|非法的演示开关'],
            ['page_grey','require|array',"请传入网站置灰项|网站置灰项为非法值"]
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $page_grey = [];
        if(in_array("index",$postArray['page_grey'])){
            $page_grey[] = "index";
        }
        if(in_array("cashier",$postArray['page_grey'])){
            $page_grey[] = "cashier";
        }
        if(in_array("user_manage",$postArray['page_grey'])){
            $page_grey[] = "user_manage";
        }
        $page_grey = implode("|",$page_grey);

        if(!isTemplateName("home_css",$postArray['home_template'])){
            return json_encode(['code' => 1, 'msg' => "该后台css样式不存在！"]);
        }
        if(!array_key_exists("order_prefix",$postArray)){
            $postArray['order_prefix']="";
        }

        $collection_cost = number_format(round($postArray['collection_cost'], 2), 2, ".", "");
        if ($collection_cost <= 0 || !is_numeric($postArray['collection_cost']) || !preg_match('/^[0-9.]+$/', $postArray['collection_cost'])) {
            return json_encode(['code' => 1, 'msg' => "收款服务能力续费价格不合法"]);
        }
        $temp = User::getByUid($postArray['system_uid']);
        if(!$temp) {
            return json_encode(['code' => 1, 'msg' => "续费收款商户UID不存在"]);
        }
        if($postArray['free_collection_service_days']<0){
            return json_encode(['code' => 1, 'msg' => "注册赠送天数不能小于0，不赠送请填0"]);
        }
        //=====Demo演示======
        if ($postArray['demo_open'] == "1") {
            $validate = new Validate([
                ['demo_uid','require|number','请填写演示收款的商户UID|演示收款的商户UID错误'],
                ['demo_pay','require','请选择演示收款码模板']
            ]);
            if (!$validate->check($postArray)) {
                return json_encode(['code' => 1, 'msg' => $validate->getError()]);
            }
            $temp = User::getByUid($postArray['demo_uid']);
            if(!$temp) {
                return json_encode(['code' => 1, 'msg' => "演示收款的商户UID不存在"]);
            }
            $payment_code_list = getPaymentCodeList();
            foreach ($payment_code_list as $value) {
                $payment_code_array[] = $value['alias'];
            }
            if (!in_array($postArray['demo_pay'], $payment_code_array)) {
                return json_encode(['code' => 1, 'msg' => '不存在该收款码模板']);
            }
        }else{
            $postArray['demo_uid'] = $core['demo_uid'];
            $postArray['demo_pay'] = "0";
        }
        //=====验证码图片======
        $validate = new Validate([
            ['captcha_code','require|alphaNum|length:8,64','请填写验证码图片的字符集合|验证码图片的字符集合只能是数字或大小写字母|验证码图片的字符集合必须是8-64位'],
            ['admin_login_switch','require|between:0,1','请选择验证码图片的后台登录开关|验证码图片的后台登录开关选项不存在'],
            ['user_login_switch','require|between:0,1','请选择验证码图片的商户登录开关|验证码图片的商户登录开关选项不存在'],
            ['user_register_switch','require|between:0,1','请选择验证码图片的商户注册开关|验证码图片的商户注册开关选项不存在']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }else{
            $captcha_switch = [];
            if($postArray['admin_login_switch']=='1'){
                array_push($captcha_switch,'admin_login');
            }
            if($postArray['user_login_switch']=='1'){
                array_push($captcha_switch,'user_login');
            }
            if($postArray['user_register_switch']=='1'){
                array_push($captcha_switch,'user_register');
            }
            $captcha_switch = implode("|",$captcha_switch);
        }

        $temp = new Core;
        $arr = [
          "title"  =>$postArray['title'],
            "subtitle"  =>$postArray['subtitle'],
            "keyword"  =>$postArray['keyword'],
            "describe"  =>$postArray['describe'],
            "qq"  =>$postArray['qq'],
            "register_open"  =>$postArray['register_open'],
            "veloce_qqlogin"  =>$postArray['veloce_qqlogin'],
            "collection_cost"=>$collection_cost,
            "system_uid"=>$postArray['system_uid'],
            "free_collection_service_days"=>$postArray['free_collection_service_days'],
            "demo_pay"  =>$postArray['demo_pay'],
            "demo_uid"=>$postArray['demo_uid'],
            "bottom_info"=>$postArray['bottom_info'],
            "home_template"=>$postArray['home_template'],
            "order_prefix"=>$postArray['order_prefix'],
            "captcha_code"=>$postArray['captcha_code'],
            "captcha_switch"=>$captcha_switch,
            "page_grey"=>$page_grey
        ];
        $i=0;
        foreach ($arr as $k=>$v){
            $num = $temp->where('name',$k)->update(['value1' => $v]);
            if($num==1){
                $i++;
            }
        }
        if(count($arr)!=$i){
            return json_encode(['code' => 0, 'msg' => "修改成功"]);
        }else{
            return json_encode(['code' => 0, 'msg' => "成功修改".$i."条"]);
        }
    }
}

<?php

namespace app\pay\controller;

use app\model\model\FixedAmount;
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
use app\model\model\Service;

//引入Service模型
use CashierFunction;
class Submit extends Controller
{
    //创建订单 并 发起支付
    public function Submit(Request $request)
    {
        $getArray = $request->get();
        if (array_key_exists('mod', $getArray)) {
            $mod = $getArray['mod'];
        } else {
            $mod = "cashier";
        }
        $is_fixed_amount = false;
        $pay_type_list = [];
        $pay_type_name_list = "";
        switch ($mod) {
            case "service":
                //=======续费充值收款
                if (!array_key_exists('service_trade_no', $getArray)) {
                    weuiMsg('warn-primary', '服务续费订单号不能为空');
                    return;
                }
                $core = Core::where('id', '<>', 0)->column('value1', 'name');  //获取系统配置
                $service = Service::getByServiceTradeNo($getArray['service_trade_no']);
                if (!$service) {
                    weuiMsg('warn-primary', '服务续费订单号不存在');
                    return;
                }
                $order = Order::getByServiceTradeNo($getArray['service_trade_no']);
                if ($order) {
                    weuiMsg('warn-primary', '请重新发起续费订单');
                    return;
                }
                $sql = User::getByUid($core['system_uid']);
                if (!$sql) {
                    weuiMsg('warn-primary', '管理员配置的收款商户不正确或不存在');
                    return;
                }
                $input['money'] = $service['money'];
                $input['type'] = $service['type'];
                $input['service_trade_no'] = $service['service_trade_no'];
                $validate = new Validate([
                    ['remarks', 'length:0,16|chsDash', '·备注信息不能大于16个字|备注信息只能是汉字、字母、数字和下划线及破折号']
                ]);
                break;
            case "cashier":
                //=======收银台收款
                $input = $request->post();
                if (!array_key_exists('code', $input)) {
                    weuiMsg('warn-primary', '请前往收银台填写金额后再支付','',false);
                    return;
                }
                $code = trim($input['code']);  //商户代码
                if ($code == "") {
                    weuiMsg('warn-primary', '错误的商户收款码','',false);
                    return;
                }
                $sql = User::getByCode($code);  //获取商户信息
                if (!$sql) {
                    weuiMsg('warn-primary', '商户收款码不存在','',false);
                    return;
                }
                $input['service_trade_no'] = null;
                $validate = new Validate([
                    ['__token__', 'require|token', ''],
                    ['remarks', 'length:0,16|chsDash', '·备注信息不能大于16个字|备注信息只能是汉字、字母、数字和下划线及破折号']
                ]);
                break;
            case "api":
                //=======第三方API收款
                if ($request->isGet()) {
                    $input = $request->get();
                } else {
                    weuiMsg('info', "不支持此类型的请求方式",'',false);
                    return;
                }
                require_once PAY_PATH . "cashier/lib/CashierFunction.php";
                $CashierFunction = new CashierFunction;
                if (array_key_exists('uid', $input)) {
                    $sql = User::getByUid($input['uid']);  //获取商户信息
                } else {
                    $sql = [];
                }
                $check = $CashierFunction->check($input, true, $sql);
                if ($check['code'] != 0) {
                    weuiMsg('info', $check['msg'],'',false);
                    return;
                }
                $input['service_trade_no'] = null;
                $validate = new Validate([
                    ['trade_name', 'require|length:1,26', '支付订单名称不能为空|支付订单名称最多26个字'],

                    ['remarks', 'length:0,16|chsDash', '·备注信息不能大于16个字|备注信息只能是汉字、字母、数字和下划线及破折号'],
                    ['notify_url', 'require|url', '请设置异步通知地址|请填写正确的异步通知地址'],
                    ['return_url', 'require|url', '请设置同步跳转地址|请填写正确的同步跳转地址'],
                    ['third_trade_no','require|alphaNum|length:12,64','第三方订单号只能为字母和数字|第三方订单号必须是12-64个字符']
                ]);
                break;
            case "fixed_amount":
                //=======固额码创建
                $is_fixed_amount = true;
                $input = $request->post();
                if (!array_key_exists('code', $input)) {
                    weuiMsg('warn-primary', '请前往收银台填写金额后再支付','',false);
                    return;
                }
                $code = trim($input['code']);  //商户代码
                if ($code == "") {
                    weuiMsg('warn-primary', '错误的商户收款码','',false);
                    return;
                }
                $FixedAmount = FixedAmount::getByCode($code);  //获取固额码信息
                if (!$FixedAmount) {
                    weuiMsg('warn-primary', '固定收款码不存在','',false);
                    return;
                }
                //固额码验证
                if($FixedAmount['money']<1){
                    weuiMsg('warn-primary', '固定收款金额不能小于0.01元','',false);
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

                $sql = User::getByUid($FixedAmount['uid']);  //获取商户信息
                if(!$sql){
                    weuiMsg('warn-primary', '收款商户不存在','',false);
                    return;
                }
                $input['service_trade_no'] = null;
                $input['money'] = $FixedAmount['money'] / 100;
                $validate = new Validate([
                    ['__token__', 'require|token', ''],
                    ['remarks', 'length:0,16|chsDash', '·备注信息不能大于16个字|备注信息只能是汉字、字母、数字和下划线及破折号']
                ]);
                break;
            default:
                weuiMsg('warn-primary', '非法提交类型','',false);
                return;
        }
        if (!$validate->check($input)) {
            weuiMsg('info', $validate->getError(),'',false);
            return;
        }
        //=====商户校验=====
        $merchantCheck = merchantCheck($sql['id']);
        if ($merchantCheck['code'] != 0) {
            weuiMsg('info', $merchantCheck['msg'],'',false);
            return;
        }
        //=====公共支付逻辑====
        //备注信息
        if (array_key_exists('remarks', $input)) {
            $remarks = trim($input['remarks']);
            if ($remarks == '') {
                $remarks = null;
            }
        } else {
            $remarks = null;
        }
        //第三方订单号
        if(array_key_exists('third_trade_no',$input) && $mod=='api'){
            if(Order::getByThirdTradeNo($input['third_trade_no'])){
                weuiMsg('warn-primary', '第三方订单号已重复，请重新发起支付！', '警告',false);
                return;
            }
        }else{
            $input['third_trade_no']=NULL;
        }

        //支付方式 and 支付金额
        if (!array_key_exists('type', $input) or !array_key_exists('money', $input)) {
            weuiMsg('warn-primary', '必须传入支付方式和支付金额', '警告',false);
            return;
        } else {
            $type = trim($input['type']);  //支付方式
            $money = trim($input['money']);  //支付金额
        }
        if ($type == '') {
            weuiMsg('info', '支付方式不能为空','',false);
            return;
        } else {
            $pay_list = getPayList();
            foreach ($pay_list as $value) {
                if ($value['switch']) {
                    $pay_array[] = $value['alias'];
                }
            }
            if (!in_array($type, $pay_array)) {
                weuiMsg('info', '不存在此支付方式','',false);
                return;
            }
        }
        if ($money == '') {
            weuiMsg('info', '支付金额不能为空','',false);
            return;
        } else {
            if (number_format(round($money, 2), 2, ".", "") <= 0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money)) {
                weuiMsg('info', '支付金额不合法','',false);
                return;
            }
        }
        $client_arr = getPayList($type, 'alias');//支付方式信息
        $pay_interface_id = $sql->getPreUsePayInterfaceId([], $client_arr['alias']);
        if ($pay_interface_id == 0 or $pay_interface_id == '') {
            weuiMsg('info', '该商户未开启【' . $client_arr['name'] . '】支付方式', '不支持'.$client_arr['name'].'付款',false);
            return;
        }
        if($is_fixed_amount) {
            if (!in_array($type, $pay_type_list)) {
                weuiMsg('info', '该收款不支持【' . $client_arr['client_name'] . '】支付方式，请使用：'.$pay_type_name_list, '不支持' . $client_arr['client_name'] . '付款',false);
                return;
            }
        }
        $pay = Pay::getById($pay_interface_id);  //获取接口信息
        if (!$pay) {
            weuiMsg('info', '该商户【' . $client_arr['name'] . '】接口不存在或已被删除', '抱歉',false);
            return;
        }
        if ($pay['state'] == "0") {
            weuiMsg('info', '当前接口已关闭', '抱歉',false);
            return;
        }
        if (!$pay->isPayInfo($pay['plug_in'])) {
            weuiMsg('info', '该商户接口信息待完善(' . $pay['plug_in'] . ')', '抱歉',false);
            return;
        }
        if ($pay['uid'] != $sql['uid']) {
            weuiMsg('warn-primary', '当前接口不属于该商户添加的，请联系该商户及时修改', '警告',false);
            return;
        }
        //支付限额
        if (!empty($pay['min_money']) && $pay['min_money'] > 0 && $money < $pay['min_money']) {
            weuiMsg('info', '当前接口每次至少支付' . $pay['min_money'] . "元", '抱歉',true,["url"=>"__index__".(!empty($code)?'/cashier/'.$code:''),"title"=>"返回收银台"]);
            return;
        }
        if (!empty($pay['max_money']) && $pay['max_money'] > 0 && $money > $pay['max_money']) {
            weuiMsg('info', '当前接口每次最多支付' . $pay['max_money'] . "元", '抱歉',true,["url"=>"__index__".(!empty($code)?'/cashier/'.$code:''),"title"=>"返回收银台"]);
        }
        if($mod == "api"){
            $trade_name = $sql->getTradeName($input['trade_name']);
        }else{
            $trade_name = $sql->getTradeName();
        }
        if($trade_name['code']!=0){
            weuiMsg('warn-primary', $trade_name['msg'], '安全拦截',false);
            return;
        }
        //=====插件订单====
        $order = [
            "source" => $mod,
            "service_trade_no" => $input['service_trade_no'],
            "third_trade_no" => $input['third_trade_no'],
            "uid" => $sql['uid'],  //用户ID
            "pid" => $pay['id'],  //支付接口ID
            "out_trade_no" => createTradeNo(),  //系统订单号
            "type" => $pay['type'],  //支付方式
            "money" => number_format(round($money, 2), 2, ".", ""),  //支付金额
            "name" => $trade_name['word'],  //商品名称
            "creation_time" => date('Y-m-d H:i:s'),  //创建时间
            "expiration_time" => date("Y-m-d H:i:s", strtotime("+10 minute")),  //过期时间（10分钟）
            "remarks" => $remarks,  //备注
            "state" => 0,  //支付状态 0未支付
            "ip" => getIp()  //获取IP
        ];
        if($is_fixed_amount) {
            $order['faid'] = $FixedAmount['id'];
        }
        $insert = new Order;
        if ($data = $insert->create($order)) {
            if ($mod == "api" && array_key_exists('notify_url',$input) && array_key_exists('return_url',$input)) {
                $notify_url = $input['notify_url'];  //api指定的异步通知地址
                $return_url = $input['return_url'];  //api指定的同步跳转地址
            }else{
                $notify_url = NULL;
                $return_url = NULL;
            }
            $temp = Order::getById($data['id']);  //获取指定主键一条数据
            $temp->notify_url = $notify_url;  //异步通知地址
            $temp->return_url = $return_url;  //同步通知地址
            if ($temp->save() === false) {
                Order::destroy($data['id']);  //删除指定主键的一条数据
                weuiMsg('info', '同步通知地址或异步通知地址设置失败！', '抱歉',false);
                return;
            }
            //第一次，1分钟，3分钟，10分钟，1小时，2小时，6小时，12小时，最后一次
            $out_trade_no = $data->out_trade_no;
            $url = request()->domain() .
                "/submit/" . $pay['plug_in'] .
                "/?out_trade_no=" . $out_trade_no .
                "&__token__=".request()->instance()->token();
            Header("Location:$url");
            return;
            $this->assign('url', $url);  //输出变量
            return $this->fetch('submit/index');  //进入模板
        } else {
            weuiMsg('warn-primary', '发起支付失败，请稍后重试', '抱歉',false);
            return;
        }
    }

    //判断订单是否已支付
    public function IsOrder(Request $request)
    {
        $method = $request->method();
        if ($method == 'GET') {
            $paraArray = $request->get();
        } else if ($method == 'POST') {
            $paraArray = $request->post();
        } else {
            return '{"code":-1,"msg":"不支持该请求方法"}';
        }
        if (!array_key_exists('out_trade_no', $paraArray)) {
            return '{"code":-1,"msg":"必须传入系统订单号"}';
        } else {
            $out_trade_no = trim($paraArray['out_trade_no']);  //系统订单号
            if ($out_trade_no == '') {
                return '{"code":-1,"msg":"系统订单号不能为空"}';
            }
        }
        $order = Order::getByOutTradeNo($out_trade_no);
        if (!$order) {
            return '{"code":-1,"msg":"该订单号不存在或已被删除"}';
        }
        if ($order['state'] <= 0) {
            return '{"code":-1,"msg":"未支付"}';
        } else {
            return '{"code":1,"msg":"已支付"}';
        }
    }
}

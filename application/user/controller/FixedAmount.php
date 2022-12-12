<?php


namespace app\user\controller;


use app\model\model\Core;
use app\model\model\Order;
use think\Controller;
use think\Request;
use think\Validate;
//引入FixedAmount模型
use app\model\model\FixedAmount as FixedAmountModel;

class FixedAmount extends Controller
{
    public function index(Request $request){
        $this->assign('nav_active', "fixed_amount");  //nav名称
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
        $count = FixedAmountModel::where("uid",$user['uid'])->where("end_time",">=",date("H-m-d H:i:s"))->count()?:0;

        $pay_interface_array = $user->getPayInterfaceArray();
        $pay_types = [];
        foreach ($pay_interface_array as $key => $item){
            if($item['switch']==1){
                $pay_types[$key] = getPayList($key, "alias")['name'];
            }
        }

        $this->assign('count', $count);  //数量
        $this->assign('title', "固额码");  //标题
        $this->assign('core', $core);  //系统配置
        $this->assign('user', $user);  //商户信息
        $this->assign('pay_types', $pay_types);  //支持的支付方式
        return $this->fetch('default/fixed_amount');  //进入模板
    }

    /**
     * 创建固额码
     *
     */
    public function add(Request $request){
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
        $count = FixedAmountModel::where("uid",$user['uid'])->where("end_time",">=",date("H-m-d H:i:s"))->count()?:0;
        if ($count>=100) {
            return json_encode(['code' => 1, 'msg' => '每个用户最多同时可以拥有100个有效未过期的固额码，当前数量为'.$count."个（过期的不计数）"]);
        }
        $prem = $request->post();
        $validate = new Validate([
            ['money','require','收款金额不能为空'],
            ['tips','chsDash|length:0,24','提示信息只能是汉字、大小写字母、数字和下划线（_）与破折号（-）|提示信息最多不能超过24个字'],
            ['end_time','require|dateFormat:Y-m-d H:i:s','截止日期不能为空|截止日期不是合法的日期格式'],
            ['type','require|array','请选择一个支付方式|不是合法的type|']
        ]);
        if (!$validate->check($prem)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $money = $prem['money'];
        $tips = trim($prem['tips']);
        $end_time = trim($prem['end_time']);
        $type = $prem['type'];
        if (number_format(round($money, 2), 2, ".", "") <= 0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money)) {
            return json_encode(['code' => 1, 'msg' => "收款金额不合法"]);
        }
        $money = intval(number_format(round($money, 2), 2, ".", "") * 100);
        //支付限额
        if ($money < 1) {
            return json_encode(['code' => 1, 'msg' => "收款金额不能小于0.01元"]);
        }
        if(strtotime($end_time) - (time() + 120) <= 0){
            return json_encode(['code' => 1, 'msg' => "截止日期最少预留2分钟"]);
        }

        $pay_interface_array = $user->getPayInterfaceArray();
        $pay_types = [];
        foreach ($pay_interface_array as $key => $item){
            if($item['switch']==1){
                $pay_types[$key] = getPayList($key, "alias")['name'];
            }
        }
        foreach ($type as $item){
            if(!isset($pay_types[$item])){
                return json_encode(['code' => 1, 'msg' => "当前选中的【{$item}】支付方式不支持或不存在"]);
            }
        }
        $type = implode("|",$type);
        $data = [
            "uid"=>$user['uid'],
            "money"=>$money,
            "tips"=>$tips,
            "end_time"=>$end_time,
            "pay_type"=>$type,
            "code"=> md5("FixedAmount".createTradeNo() . $user['id'].rand(1000,9999))
        ];
        $insert = new FixedAmountModel;
        if ($res = $insert->create($data)) {
            return json_encode(['code' => 0, 'msg' => "固额码创建成功","id"=>$res['id'],"new_end_time"=>date("Y-m-d H:i:s",time()+300)]);
        }else{
            return json_encode(['code' => 1, 'msg' => "固额码创建失败"]);
        }
    }
    /**
     * 创建记录
     */
    public function getList(Request $request){
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
            ['mode', 'require', '请传入获取类型'],
            ['page', 'number', '页数不正确']
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $num = 5;  //每次显示数量
        $page = $postArray['page'];
        if ($page <= 0) {
            $page = 1;
        }

        switch ($postArray['mode']) {
            case "wei_end_time":
                //未过期的
                $sql_data = FixedAmountModel::alias("f")->where("f.uid", $user['uid'])->where("f.end_time",">=",date("Y-m-d H:i:s"));
                $sql_count = FixedAmountModel::alias("f")->where("f.uid", $user['uid'])->where("f.end_time",">=",date("Y-m-d H:i:s"));
                break;
            case "yi_end_time":
                //已过期的
                $sql_data = FixedAmountModel::alias("f")->where("f.uid", $user['uid'])->where("f.end_time","<=",date("Y-m-d H:i:s"));
                $sql_count = FixedAmountModel::alias("f")->where("f.uid", $user['uid'])->where("f.end_time","<=",date("Y-m-d H:i:s"));
                break;
            default:
                //全部
                $sql_data = FixedAmountModel::alias("f")->where("f.uid", $user['uid']);
                $sql_count = FixedAmountModel::alias("f")->where("f.uid", $user['uid']);
        }
        $count = $sql_count->count()?:0;
        $list = $sql_data
            ->order('f.id desc')
            ->page($page, $num)
            ->select()?:[];

        $total_page = ceil($count / $num);

        $all_count = FixedAmountModel::alias("f")->where("f.uid", $user['uid'])->count()?:0;
        $wei_count = FixedAmountModel::alias("f")->where("f.uid", $user['uid'])->where("f.end_time",">=",date("Y-m-d H:i:s"))->count()?:0;
        $yi_count = FixedAmountModel::alias("f")->where("f.uid", $user['uid'])->where("f.end_time","<=",date("Y-m-d H:i:s"))->count()?:0;

        $pay_arr = getPayList();
        foreach ($list as $k=>$item){
            $types = explode("|",$item['pay_type']);
            $pay_type = [];
            foreach ($types as $type){
                $arr = [];
                if (array_key_exists($type, $pay_arr)) {
                    $arr['name'] = $pay_arr[$type]['name'];
                    $arr['type'] = $type;
                    $arr['icon'] = $pay_arr[$type]['icon'];
                    $arr['color'] = $pay_arr[$type]['color'];
                    $arr['count'] = Order::where("state", "<>", "0")->where("faid",$item['id'])->where("type",$type)->count()?:0;
                } else {
                    $arr['name'] = "未知";
                    $arr['type'] = $type;
                    $arr['icon'] = "unknown.png";
                    $arr['color'] = "#6e00ff";
                    $arr['count'] = 0;
                }
                $pay_type[] = $arr;
            }
            $list[$k]['pay_type'] = $pay_type;
        }
        return json_encode([
            'code' => 0,
            'msg' => "成功",
            "list" => $list,
            "page" => $page,
            "total_page" => $total_page,
            "mode_count" => $count,
            "current_page_count"=>count($list),
            "all_count"=>$all_count,
            "wei_count"=>$wei_count,
            "yi_count"=>$yi_count,
            ]);
    }
    /**
     * 删除
     */
    public function del(Request $request){
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
            ['id', 'require', '请传入固额码ID'],
        ]);
        if (!$validate->check($postArray)) {
            return json_encode(['code' => 1, 'msg' => $validate->getError()]);
        }
        $id = intval($postArray['id']);
        $data = FixedAmountModel::where("uid", $user['uid'])->where("id",$id)->find();

        if(!$data){
            return json_encode(['code' => 1, 'msg' => '该固额码不存在或已被删除']);
        }
        if ($data->delete()) {
            return json_encode(['code' => 0, 'msg' => "删除成功"]);
        } else {
            return json_encode(['code' => 1, 'msg' => "删除失败"]);
        }
    }
}

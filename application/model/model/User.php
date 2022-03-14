<?php
//定义模型命名空间
namespace app\model\model;
//实例化Model类
use think\Model;

//引入Order模型
use app\model\model\Order;
use app\model\model\Pay;
use app\model\model\IllegalRecord;
use app\model\model\Core;

class User extends Model
{
    //表名
    protected $name = 'user';

    //获取用户的支付接口配置
    public function getPayInterfaceArray()
    {
        $pay_interface = $this->pay_interface;
        if(trim($pay_interface)!="" && $pay_interface!=null) {
            if ($arr = json_decode($pay_interface, true)) {
            } else {
                $arr = array();
            }
        }else{
            $arr = array();
        }
        $pay_list = getPayList();
        foreach ($pay_list as $value) {
            if (!array_key_exists($value['alias'], $arr)) {
                $arr[$value['alias']] = ["switch" => 0, "use_order" => 3];
            }else{
                if(!array_key_exists("switch", $arr[$value['alias']])){
                    $arr[$value['alias']]['switch'] = 0;
                }
                if(!array_key_exists("use_order", $arr[$value['alias']])){
                    $arr[$value['alias']]['use_order'] = 3;
                }
            }
        }
        $temp = User::getById($this->id);
        $temp->pay_interface=json_encode($arr);
        $temp->save();
        return $arr;
    }

    //获取用户的支付接口列表
    public function getPayInterfaceIdArray($type)
    {
        //获取可用开启的接口列表
        $ids = Pay::where("uid", $this->uid)  //当前用户uid
        ->where('type', $type)  //接口支付方式
        ->column("id");
        if (!$ids) {
            return array();
        }
        if (count($ids) <= 0 or $ids == "" or !is_array($ids)) {
            return array();
        }
        return $ids;
    }

    //获取应该使用要哪个支付接口ID
    public function getPreUsePayInterfaceId($arr = [], $type = '')
    {
        if ($type == '') {
            return 0;  //接口关闭
        }
        if ($arr == []) {
            $arr = $this->getPayInterfaceArray();
        }
        $obj = $arr[$type];
        //判断有没有【switch】字段
        if (!array_key_exists('switch', $obj)) {
            return 0;  //接口关闭
        } else {
            //判断【switch】是不是合法内容
            if ($obj['switch'] != 0 && $obj['switch'] != 1) {
                return 0;  //接口关闭
            }
        }
        //判断有没有【use_order】字段
        if (!array_key_exists('use_order', $obj)) {
            //没有则使用第一个可用接口
            $use_order = 3;  //使用第一个可用接口
        } else {
            //有则获取
            $use_order = trim($obj['use_order']);
        }
        //判断接口是否关闭
        if ($obj['switch'] == 0) {
            return 0;  //接口关闭
        }
        //获取可用开启的接口列表
        $ids = Pay::where("uid", $this->uid)  //当前用户uid
        ->where('state', "<>", "0")  //状态开启
        ->where('type', $type)  //接口支付方式
        ->column("id");
        if (!$ids) {
            return 0;  //接口关闭
        }
        if (count($ids) <= 0 or $ids == "" or !is_array($ids)) {
            return 0;  //接口关闭
        }
        //只有一个接口可用开启时
        if (count($ids) == 1) {
            return $ids[0];  //直接返回
        }


        //不是只有一个接口时
        switch ($use_order) {
            case 1:
                //按顺序使用
                $sql = Order::where('state', '<>', 0)->order("id desc")->field('pid')
                    ->where('pid', 'in', join(",", $ids))
                    ->find();
                if (!$sql) {
                    return $ids[0];  //使用第一个接口
                } else {
                    $id = $sql['pid'];
                }
                foreach ($ids as $key => $val) {
                    if ($val == $id) {
                        if ((count($ids) - 1) == $key) {
                            return $ids[0];
                        } else {
                            return $ids[$key + 1];
                        }
                    }
                }
                return $ids[0];  //使用第一个接口
                break;
            case 2:
                //随机使用
                return $ids[array_rand($ids, 1)];
                break;
            case 3:
                //使用第一个接口
                return $ids[0];
                break;
            default:
                return 0;  //接口关闭
        }
    }

    //获取状态
    public function getStateName($state = null)
    {
        if ($state == null) {
            $state = $this->state;
        }
        switch ($state) {
            case 0:
                return "停封";
                break;
            case 1:
                return "正常";
                break;
        }
    }

    //获取收银台模板名称
    public function getCashierTemplateName($name = null)
    {
        if ($name == null) {
            $name = $this->cashier_template;
        }
        return $name;
    }

    //获取多个支付接口使用顺序名称
    public function getUseOrderName($arr = null, $type = null,$all=false)
    {
        if($all){
            return array(
                "1"=>"按顺序使用",
                "2"=>"随机使用",
                "3"=>"使用第一个接口"
            );
        }
        if ($type == null) {
            return "";
        }
        if ($arr == null or $arr == array()) {
            $arr = $this->getPayInterfaceArray();
        }
        if ($arr == array()) {
            return "未知";
        }
        $obj = $arr[$type];
        if (!array_key_exists('use_order', $obj)) {
            $use_order = 1;  //按顺序使用
        } else {
            $use_order = trim($obj['use_order']);
        }
        switch ($use_order) {
            case 1:
                return "按顺序使用";
                break;
            case 2:
                return "随机使用";
                break;
            case 3:
                return "使用第一个接口";
                break;
            default:
                return "未知";
        }
    }

    public function getTradeName($self_name = ""){
        if($self_name!=""){
            $mode = "3";
        }else{
            $mode = $this->payment_mode;
        }
        $return = "收银台收款-".time();
        switch ($mode){
            case "1":
                //商户名称
                $return = $this->nickname;
                break;
            case "2":
                //自义定规则
                if($this->payment_name ==NULL or trim($this->payment_name)==""){
                    $return = "收银台收款-".time();
                }else{
                    $arr = explode("|",$this->payment_name);
                    $arr = array_filter($arr);
                    if(count($arr)<=0) {
                        $return =  "收银台收款-".time();
                    }
                    $name = $arr[array_rand($arr)];
                    $name = str_replace("[time]",time(),$name);
                    $name = str_replace("[QQ]",$this->qq,$name);
                    $name = str_replace("[rand4]",rand(0,9).rand(0,9).rand(0,9).rand(0,9),$name);
                    $return = $name;
                }
                break;
            case "3":
                //自义定名称
                $return = $self_name;
                break;
        }
        $core = Core::where('id', '<>', 0)->column('value1', 'name');
        if(isset($core['thinkapi_appcode']) && isset($core['text_review'])){
            $appCode = trim($core['thinkapi_appcode']);
            $text_review = $core['text_review'];
            if($appCode!="" && $text_review=="1"){
                //=====违规词检测======
                $json = curl_get("https://api.topthink.com/website/antispam?appCode=$appCode&content=".urlencode($return));
                if(!$arr=json_decode($json,true)){
                    return array("code"=>1,"msg"=>"文本审核API返回参数无法解析");
                }else {
                    if ($arr['code'] != 0) {
                        return array("code"=>1,"msg"=>"文本审核API接口异常");
                    }
                    if($arr['data'][0]['con_type']==1){
                        //合规

                    }else if($arr['data'][0]['con_type']==2) {
                        //不合规
                        //写库记录
                        $words = implode('、',$arr['data'][0]['result'][0]['words']);
                        $illegal_record = new IllegalRecord();
                        $illegal_record->uid = $this->uid;  //商户UID
                        $illegal_record->mode = $arr['data'][0]['result'][0]['subtype'];  //违规类型
                        $illegal_record->content = $return;  //违规内容
                        $illegal_record->words = $words;  //违规关键字
                        $illegal_record->source = 1;  //拦截来源
                        if (!$illegal_record->save()) {
                            return array("code"=>1,"msg"=>"违规内容记录失败");
                        }

                        return array("code" => 1, "msg" => "当前支付商品名称存在违规内容，" . $arr['data'][0]['result'][0]['msg']."。平台严厉打击一切违法违规行为及活动，情节严重者将移交证据给予公安处理！");
                    }else if($arr['data'][0]['con_type']==3){
                        //疑似不合规
                        return array("code" => 1, "msg" => "当前支付商品名称疑似存在违规内容，". $arr['data'][0]['result'][0]['msg']."。请立即整改！");
                    }else if($arr['data'][0]['con_type']==4){
                        //审核失败
                        return array("code"=>1,"msg"=>"文本审核API接口审核失败");
                    }else{
                        //未知
                        return array("code"=>1,"msg"=>"文本审核API接口未知状态");
                    }
                }
                //=====违规词检测======
            }
        }
        return array("code"=>0,"word"=>mb_substr($return,0,26));
    }
}
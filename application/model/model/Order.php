<?php
//定义模型命名空间
namespace app\model\model;
//实例化Model类
use think\Model;

class Order extends Model
{
    //表名
    protected $name = 'order';

    public function getStateName($state = null,$sw=false)
    {
        $arr = ["0" => "未支付", "1" => "支付完成", "2" => "已退款"];
        if($sw){
            return $arr;
        }
        if ($state == null) {
            $state = $this->state;
        }
        if (!array_key_exists($state, $arr)) {
            return "未知";
        } else {
            return $arr[$state];
        }
    }

    public function getTradeTypeName($type = null,$sw=false)
    {
        $arr = [
            "FACE"=>"当面付",
            "WapPay"=>"手机网站",
            "PagePay"=>"电脑网站",
            "JSAPI"=>"JSAPI",
            "MWEB"=>"H5支付",
            "H5"=>"H5支付",
            "NATIVE"=>"扫码支付",
            "EPAY"=>"易支付",
            "CASHIER"=>"聚合收银台",
        ];
        if($sw){
            return $arr;
        }
        if ($type == null) {
            $type = $this->trade_type;
        }
        if (!array_key_exists($type, $arr)) {
            return "未知";
        } else {
            return $arr[$type];
        }
    }

    public function getSourceName($type = null,$sw=false)
    {
        $arr = ["cashier" => "收银台", "service" => "续费充值", "api" => "API请求", "fixed_amount" => "固额码"];
        if($sw){
            return $arr;
        }
        if ($type == null) {
            $type = $this->source;
        }
        if (!array_key_exists($type, $arr)) {
            return "未知";
        } else {
            return $arr[$type];
        }
    }
}

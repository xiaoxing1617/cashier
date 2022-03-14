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

    public function getTradeTypeName($type = null)
    {
        if ($type == null) {
            $type = $this->trade_type;
        }
        switch ($type) {
            case "FACE":
                $type = "当面付";
                break;
            case "WapPay":
                $type = "手机网站";
                break;
            case "PagePay":
                $type = "电脑网站";
                break;
            case "JSAPI":
                $type = "JSAPI";
                break;
            case "MWEB":
                $type = "H5支付";
                break;
            case "H5":
                $type = "H5支付";
                break;
            case "NATIVE":
                $type = "扫码支付";
                break;
            case "EPAY":
                $type = "易支付";
                break;
            case "CASHIER":
                $type = "聚合收银台";
                break;
            default:
                $type = "未知";
        }
        return $type;
    }

    public function getSourceName($type = null)
    {
        if ($type == null) {
            $type = $this->source;
        }
        switch ($type) {
            case "cashier":
                $type = "收银台";
                break;
            case "service":
                $type = "续费充值";
                break;
            case "api":
                $type = "API请求";
                break;
            default:
                $type = "未知";
        }
        return $type;
    }
}
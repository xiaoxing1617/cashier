<?php
//定义模型命名空间
namespace app\model\model;
//实例化Model类
use think\Model;

class Pay extends Model
{
    //表名
    protected $name = 'pay';

    //判断支付信息是否完整
    public function isPayInfo($type = null)
    {
        if ($type == null) {
            return false;
        }
        switch ($type) {
            case 'cashier':
                //判断聚合收银台接口信息是否完整
                $url = $this->value1;   //聚合收银台接口URL
                $uid = $this->value2;  //聚合收银台商户UID
                $key = $this->value3;  //聚合收银台商户密钥
                if ($url == "" or $url == null or $uid == "" or $uid == null or $key == "" or $key == null) {
                    return false;
                }
                return true;
                break;
            case 'epay':
                //判断易支付接口信息是否完整
                $url = $this->value1;   //易支付接口URL
                $pid = $this->value2;  //易支付商户ID
                $key = $this->value3;  //易支付商户密钥
                if ($url == "" or $url == null or $pid == "" or $pid == null or $key == "" or $key == null) {
                    return false;
                }
                return true;
                break;
            case 'alipay':
                //判断支付宝接口信息是否完整
                $appid = $this->value1;   //应用ID
                $alipay_public_key = $this->value2;  //支付宝公钥
                $merchant_private_key = $this->value3;  //商户密钥
                $ability_to_pay = $this->value4;  //支付能力状态
                if ($appid == '' or $appid == null or $alipay_public_key == null or $alipay_public_key == '' or $merchant_private_key == null or $merchant_private_key == '' or $ability_to_pay == null or $ability_to_pay == '') {
                    return false;
                }
                return true;
                break;
            case 'wxpay':
                //判断微信支付接口信息是否完整
                $app_id = $this->value1;  //公众号ID
                $merchant_id = $this->value2;  //商户ID
                $merchant_key = $this->value3;  //商户密钥
                $app_secret = $this->value4;  //应用公众号secret
                $txt = $this->value5;  //TXT文件名
                $apiclient_cert = $this->value6;  //apiclient_cert
                $apiclient_key = $this->value7;  //apiclient_key
                $ability_to_pay = $this->value8;  //签约产品能力
                if ($app_id == '' or $app_id == null or $merchant_id == '' or $merchant_id == null or $merchant_key == '' or $merchant_key == null or $app_secret == '' or $app_secret == null or $txt == '' or $txt == null or $apiclient_cert == '' or $apiclient_cert == null or $apiclient_key == '' or $apiclient_key == null or $ability_to_pay == '' or $ability_to_pay == null) {
                    return false;
                }
                return true;
            case 'qqpay':
                //判断QQ钱包支付接口信息是否完整
                $app_id = $this->value1;  //商户ID
                $appkey = $this->value2;  //商户密钥
                $userid = $this->value3;  //企业付款ID
                $userpwd = $this->value4;  //企业付款密码
                $sslcert = $this->value5;  //证书cert
                $sslkey = $this->value7;  //证书key
                $ability_to_pay = $this->value6;  //签约产品能力
                if ($app_id == '' or $app_id == null or $appkey == '' or $appkey == null or $userid == '' or $userid == null or $userpwd == '' or $userpwd == null or $sslcert == '' or $sslcert == null or $sslkey == '' or $sslkey == null or $ability_to_pay == '' or $ability_to_pay == null) {
                    return false;
                }
                return true;
                break;
            default:
                return false;
        }
    }
}
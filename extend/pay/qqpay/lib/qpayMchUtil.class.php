<?php
/**
 * qpayMachAPI.php
 * Created by HelloWorld
 * vers: v1.0.0
 * User: Tencent.com
 */

class QpayMchUtil {

    /**
     * 生成随机串
     * @param int $length
     *
     * @return string
     */
    public static function createNoncestr( $length = 32 ) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ ){
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

    /**
     * 将参数转为hash形式
     * @param $params
     * @param $urlencode
     *
     * @return string
     */
    public static function buildQueryStr($params) {
        $arrTmp = array();
        foreach ($params as $k => $v){
            //参数为空不参与签名
            if(isset($v) && $v != '' && $k != 'sign'){
                array_push($arrTmp, "$k=$v");
            }
        }
        return implode('&', $arrTmp);
    }

    /**
     * 获取参数签名
     * @param $params
     *
     * @return string
     */
    public static function getSign($params) {
        //第一步：对参数按照key=value的格式，并按照参数名ASCII字典序排序
        ksort($params);
        $stringA = QpayMchUtil::buildQueryStr($params);
        //第二步：拼接API密钥并md5
        $stringA = $stringA."&key=".QpayMchConf::MCH_KEY;
        $stringA = md5($stringA);
        //转成大写
        $sign = strtoupper($stringA);
        return $sign;
    }

    /**
     * 数组转换成xml字符串
     * @param $arr
     * @return string
     */
    public static function arrayToXml($arr) {
        $xml = "<xml>";
        foreach ($arr as $key => $val){
            if (is_numeric($val)){
                $xml.="<$key>$val</$key>";
            }
            else
                $xml .= "<$key><![CDATA[$val]]></$key>";
        }
        $xml.="</xml>";
        return $xml;
    }

    /**
     * xml转换成数组
     * @param $xml
     * @return array|mixed|object
     */
    public static function xmlToArray($xml) {
        $arr = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $arr;
    }

    /**
     * 通用curl 请求接口。post方式
     * @param     $params
     * @param     $url
     * @param int $timeout
     *
     * @return bool|mixed
     */
    public static function reqByCurlNormalPost($params, $url, $timeout = 10,$fun="") {
        return QpayMchUtil::_reqByCurl($params, $url, $timeout, false,$fun);
    }

    /**
     * 使用ssl证书请求接口。post方式
     * @param     $params
     * @param     $url
     * @param int $timeout
     *
     * @return bool|mixed
     */
    public static function reqByCurlSSLPost($params, $url, $timeout = 10,$fun="") {
        return QpayMchUtil::_reqByCurl($params, $url, $timeout, true,$fun);
    }

    private static function _reqByCurl($params, $url, $timeout = 10, $needSSL = false,$fun="") {
        $ch = curl_init();

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_TIMEOUT, $timeout);

        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);

        curl_setopt($ch,CURLOPT_HEADER,FALSE);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
        //是否使用ssl证书
        if(isset($needSSL) && $needSSL != false){
            $sslCert = QpayMchConf::SSLCERT_PATH;
            $sslKey = QpayMchConf::SSLKEY_PATH;

            $s = QpayMchConf::MCH_ID."_".md5(createTradeNo());
            $apiclient_cert_path = PAY_PATH . "qqpay/cert/".$s."_apiclient_cert.pem";
            $apiclient_key_path = PAY_PATH . "qqpay/cert/".$s."_apiclient_key.pem";;

            if(($file1=fopen ($apiclient_cert_path,"w+")) === FALSE){
                if($fun=="refund"){
                    return ["code" => 1, "msg" => "apiclient_cert证书创建失败，请稍后重试！"];
                }else{
                    return false;
                }
            }
            if(($file2=fopen ($apiclient_key_path,"w+")) === FALSE){
                unlink($apiclient_cert_path);
                if($fun=="refund"){
                    return ["code" => 1, "msg" => "apiclient_key证书创建失败，请稍后重试！"];
                }else{
                    return false;
                }
            }
            fwrite($file1,$sslCert);
            fwrite($file2,$sslKey);
            fclose($file1);
            fclose($file2);


            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT, $apiclient_cert_path);
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY, $apiclient_key_path);
        }
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $params);
        $ret = curl_exec($ch);
        if(isset($needSSL) && $needSSL != false) {
            unlink($apiclient_cert_path);
            unlink($apiclient_key_path);
        }
        if($ret){
            curl_close($ch);
            if($fun=="refund"){
                return array("code"=>0,'data'=>$ret);
            }else{
                return $ret;
            }
        }
        else {
            $error = curl_errno($ch);
            curl_close($ch);
            if($fun=="refund"){
                return array("code"=>1,'msg'=>"curl出错，错误码:$error");
            }else{
                return false;
            }
        }
    }

}

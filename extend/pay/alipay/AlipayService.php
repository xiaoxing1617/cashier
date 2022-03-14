<?php
/**
 * Created by PhpStorm.
 * User: xudong.ding
 * Date: 16/5/19
 * Time: 下午2:09
 */
require_once PAY_PATH.'alipay/lib/AopClient.php';
require_once PAY_PATH.'alipay/lib/AopCertClient.php';
require PAY_PATH.'alipay/config.php';

class AlipayService {

	//支付宝网关地址
	public $gateway_url = "https://openapi.alipay.com/gateway.do";

	//异步通知回调地址
	public $notify_url;

	//同步通知回调地址
	public $return_url;

	//快捷登录回调地址
	public $redirect_uri;

	//签名类型
	public $sign_type;

	//支付宝公钥地址
	public $alipay_public_key;

	//商户私钥地址
	public $private_key;

	//应用id
	public $appid;

	//编码格式
	public $charset = "UTF-8";

	public $token = NULL;

	//返回数据格式
	public $format = "json";

	//签名方式
	public $signtype = "RSA2";

	//支付宝公钥证书路径
	public $alipayCertPath;

	//应用公钥证书路径
	public $appCertPath;

	//支付宝根证书路径
	public $rootCertPath;

	function __construct($alipay_config){
		$this->gateway_url = $alipay_config['gatewayUrl'];
		$this->appid = $alipay_config['app_id'];
		$this->sign_type = $alipay_config['sign_type'];
		$this->private_key = $alipay_config['merchant_private_key'];
		$this->alipay_public_key = $alipay_config['alipay_public_key'];
		$this->charset = $alipay_config['charset'];
		$this->signtype = $alipay_config['sign_type'];

		$this->alipayCertPath = '';
		$this->appCertPath = '';
		$this->rootCertPath = '';

		if(empty($this->appid)||trim($this->appid)==""){
			throw new Exception("appid should not be NULL!");
		}
		if(empty($this->private_key)||trim($this->private_key)==""){
			throw new Exception("private_key should not be NULL!");
		}
		if((empty($this->alipay_public_key)||trim($this->alipay_public_key)=="")&&empty($this->alipayCertPath)){
			throw new Exception("alipay_public_key should not be NULL!");
		}
		if(empty($this->charset)||trim($this->charset)==""){
			throw new Exception("charset should not be NULL!");
		}
		if(empty($this->gateway_url)||trim($this->gateway_url)==""){
			throw new Exception("gateway_url should not be NULL!");
		}
		if(empty($this->sign_type)||trim($this->sign_type)==""){
			throw new Exception("sign_type should not be NULL");
		}

	}

	/**
	 * 使用SDK执行提交页面接口请求
	 * @param unknown $request
	 * @param string $token
	 * @param string $appAuthToken
	 * @return string $$result
	 */
	protected function aopclientRequestExecute($request, $token = NULL, $appAuthToken = NULL) {

		if(!empty($this->alipayCertPath) && !empty($this->appCertPath) && !empty($this->rootCertPath)){
			$aop = new AopCertClient ();
			$aop->gatewayUrl = $this->gateway_url;
			$aop->appId = $this->appid;
			$aop->rsaPrivateKey = $this->private_key;

			//调用getPublicKey从支付宝公钥证书中提取公钥
			$aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipayCertPath);
			//是否校验自动下载的支付宝公钥证书，如果开启校验要保证支付宝根证书在有效期内
			$aop->isCheckAlipayPublicCert = true;
			//调用getCertSN获取证书序列号
			$aop->appCertSN = $aop->getCertSN($this->appCertPath);
			//调用getRootCertSN获取支付宝根证书序列号
			$aop->alipayRootCertSN = $aop->getRootCertSN($this->rootCertPath);
		}else{
			$aop = new AopClient ();
			$aop->gatewayUrl = $this->gateway_url;
			$aop->appId = $this->appid;
			$aop->rsaPrivateKey = $this->private_key;
			$aop->alipayrsaPublicKey = $this->alipay_public_key;
		}
		$aop->signType = $this->sign_type;
		$aop->apiVersion = "1.0";
		$aop->postCharset = $this->charset;
		$aop->format=$this->format;
		// 开启页面信息输出
		$aop->debugInfo=true;
		$result = $aop->execute($request,$token,$appAuthToken);

		//打开后，将url形式请求报文写入log文件
		//$this->writeLog("response: ".var_export($result,true));
		return $result;
	}

	/**
	 * 使用SDK执行提交页面接口请求
	 * @param unknown $request
	 * @param string $token
	 * @param string $appAuthToken
	 * @return string $$result
	 */
	protected function aopclientRequestPageExecute($request, $token = NULL, $appAuthToken = NULL) {

		if(!empty($this->alipayCertPath) && !empty($this->appCertPath) && !empty($this->rootCertPath)){
			$aop = new AopCertClient ();
			$aop->gatewayUrl = $this->gateway_url;
			$aop->appId = $this->appid;
			$aop->rsaPrivateKey = $this->private_key;

			//调用getPublicKey从支付宝公钥证书中提取公钥
			$aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipayCertPath);
			//是否校验自动下载的支付宝公钥证书，如果开启校验要保证支付宝根证书在有效期内
			$aop->isCheckAlipayPublicCert = true;
			//调用getCertSN获取证书序列号
			$aop->appCertSN = $aop->getCertSN($this->appCertPath);
			//调用getRootCertSN获取支付宝根证书序列号
			$aop->alipayRootCertSN = $aop->getRootCertSN($this->rootCertPath);
		}else{
			$aop = new AopClient ();
			$aop->gatewayUrl = $this->gateway_url;
			$aop->appId = $this->appid;
			$aop->rsaPrivateKey = $this->private_key;
			$aop->alipayrsaPublicKey = $this->alipay_public_key;
		}
		$aop->signType = $this->sign_type;
		$aop->apiVersion = "1.0";
		$aop->postCharset = $this->charset;
		$aop->format=$this->format;
		// 开启页面信息输出
		$aop->debugInfo=true;
		$result = $aop->pageExecute($request,$token,$appAuthToken);

		//打开后，将url形式请求报文写入log文件
		//$this->writeLog("response: ".var_export($result,true));
		return $result;
	}

	public function writeLog($text) {
		// $text=iconv("GBK", "UTF-8//IGNORE", $text);
		//$text = characet ( $text );
		//file_put_contents ( PAY_ROOT."inc/log/log.txt", date ( "Y-m-d H:i:s" ) . "  " . $text . "\r\n", FILE_APPEND );
	}

}
<?php
/**
 * 支付宝交易安全防护服务类
 */
require_once PAY_PATH.'alipay/AlipayService.php';
require_once PAY_PATH.'alipay/model/request/AlipaySecurityRiskCustomerriskSendRequest.php';

class AlipaySecurityService extends AlipayService {

	function __construct($alipay_config){
		parent::__construct($alipay_config);
	}

	//向支付宝返回针对风险交易的处理方式
	public function customerriskSend($plat_account, $trade_no, $pid, $process_code) {
		
		$BizContent = array(
			'plat_account' => $plat_account,
			'trade_no' => $trade_no,
			'pid' => $pid,
			'process_code' => $process_code
		);

		$request = new AlipaySecurityRiskCustomerriskSendRequest();
		$request->setBizContent(json_encode($BizContent));

		$response = $this->aopclientRequestExecute($request);
		$response = $response->alipay_security_risk_customerrisk_send_response;
		$result = json_decode(json_encode($response), true);
		
		return $result;

	}

	//通知签名校验
	public function check($arr){
		if(!empty($this->alipayCertPath) && !empty($this->appCertPath) && !empty($this->rootCertPath)){
			$aop = new AopCertClient ();
			$aop->alipayrsaPublicKey = $aop->getPublicKey($this->alipayCertPath);
			$result = $aop->rsaCheckV1($arr, $this->alipayCertPath, $this->signtype);
		}else{
			$aop = new AopClient();
			$aop->alipayrsaPublicKey = $this->alipay_public_key;
			$result = $aop->rsaCheckV1($arr, $this->alipay_public_key, $this->signtype);
		}
		if($result){
			return true;
		}
		return false;
	}

	public function writeLog($text) {
		// $text=iconv("GBK", "UTF-8//IGNORE", $text);
		//$text = characet ( $text );
		file_put_contents ( PAY_ROOT."inc/log/log.txt", date ( "Y-m-d H:i:s" ) . "  " . $text . "\r\n", FILE_APPEND );
	}
}
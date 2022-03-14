<?php
/**
 * 支付宝交易服务类
 */

require_once PAY_PATH.'alipay/AlipayService.php';
require_once PAY_PATH.'alipay/model/request/AlipayTradeCreateRequest.php';
require_once PAY_PATH.'alipay/model/request/AlipayTradePagePayRequest.php';
require_once PAY_PATH.'alipay/model/request/AlipayTradeWapPayRequest.php';
require_once PAY_PATH.'alipay/model/request/AlipayTradePrecreateRequest.php';
require_once PAY_PATH.'alipay/model/request/AlipayTradeQueryRequest.php';
require_once PAY_PATH.'alipay/model/request/AlipayTradeRefundRequest.php';
require_once PAY_PATH.'alipay/model/request/AlipayTradeCloseRequest.php';
require_once PAY_PATH.'alipay/model/request/AlipayTradeFastpayRefundQueryRequest.php';
require_once PAY_PATH.'alipay/model/result/AlipayF2FPrecreateResult.php';
require_once PAY_PATH.'alipay/model/result/AlipayF2FQueryResult.php';
require_once PAY_PATH.'alipay/model/result/AlipayF2FRefundResult.php';
require_once PAY_PATH.'alipay/model/builder/AlipayTradeQueryContentBuilder.php';

class AlipayTradeService extends AlipayService {

	function __construct($alipay_config){
		parent::__construct($alipay_config);

		$this->notify_url = $alipay_config['notify_url'];
		$this->return_url = $alipay_config['return_url'];

	}
	/**
	 * alipay.trade.create
	 * @param $builder 业务参数，使用build中的对象生成。
	 * @return $response 支付宝返回的信息
 	*/
	public function create($req) {
		$bizContent = $req->getBizContent();
		$this->writeLog($bizContent);

		$request = new AlipayTradeCreateRequest();
		$request->setBizContent ( $bizContent );
		$request->setNotifyUrl ( $this->notify_url );

		// 首先调用支付api
		$response = $this->aopclientRequestExecute ( $request );
		$response = $response->alipay_trade_create_response;

		$result = new AlipayF2FPrecreateResult($response);
		if(!empty($response)&&("10000"==$response->code)){
			$result->setTradeStatus("SUCCESS");
		} elseif($this->tradeError($response)){
			$result->setTradeStatus("UNKNOWN");
		} else {
			$result->setTradeStatus("FAILED");
		}
		return $result;
	}

	//当面付2.0预下单(生成二维码)
	public function qrPay($req) {
		$bizContent = $req->getBizContent();
		$this->writeLog($bizContent);

		$request = new AlipayTradePrecreateRequest();
		$request->setBizContent ( $bizContent );
		$request->setNotifyUrl ( $this->notify_url );

		// 首先调用支付api
		$response = $this->aopclientRequestExecute ( $request );
		$response = $response->alipay_trade_precreate_response;

		$result = new AlipayF2FPrecreateResult($response);
		if(!empty($response)&&("10000"==$response->code)){
			$result->setTradeStatus("SUCCESS");
		} elseif($this->tradeError($response)){
			$result->setTradeStatus("UNKNOWN");
		} else {
			$result->setTradeStatus("FAILED");
		}
		return $result;
	}

	/**
	 * alipay.trade.page.pay
	 * @param $builder 业务参数，使用build中的对象生成。
	 * @return $response 支付宝返回的信息
 	*/
	public function pagePay($builder) {
	
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
	
		$request = new AlipayTradePagePayRequest();
	
		$request->setNotifyUrl($this->notify_url);
		$request->setReturnUrl($this->return_url);
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestPageExecute ($request);
		// $response = $response->alipay_trade_wap_pay_response;
		return $response;
	}

	/**
	 * alipay.trade.wap.pay
	 * @param $builder 业务参数，使用build中的对象生成。
	 * @return $response 支付宝返回的信息
 	*/
	public function wapPay($builder) {
	
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
	
		$request = new AlipayTradeWapPayRequest();
	
		$request->setNotifyUrl($this->notify_url);
		$request->setReturnUrl($this->return_url);
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestPageExecute ($request);
		// $response = $response->alipay_trade_wap_pay_response;
		return $response;
	}

	/**
	 * alipay.trade.refund (统一收单交易退款接口)
	 * @param $builder 业务参数，使用build中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	public function refund($req) {
		$bizContent = $req->getBizContent();
		$this->writeLog($bizContent);
		$request = new AlipayTradeRefundRequest();
		$request->setBizContent ( $bizContent );
		$response = $this->aopclientRequestExecute ( $request );

		$response = $response->alipay_trade_refund_response;

		$result = new AlipayF2FRefundResult($response);
		if(!empty($response)&&("10000"==$response->code)){
			$result->setTradeStatus("SUCCESS");
		} elseif ($this->tradeError($response)){
			$result->setTradeStatus("UNKNOWN");
		} else {
			$result->setTradeStatus("FAILED");
		}

		return $result;
	}

	/**
	 * alipay.trade.close (统一收单交易关闭接口)
	 * @param $builder 业务参数，使用build中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	public function close($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
		$request = new AlipayTradeCloseRequest();
		$request->setBizContent ( $biz_content );
	
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_trade_close_response;
		return $response;
	}
	
	/**
	 * 退款查询   alipay.trade.fastpay.refund.query (统一收单交易退款查询)
	 * @param $builder 业务参数，使用build中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	public function refundQuery($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
		$request = new AlipayTradeFastpayRefundQueryRequest();
		$request->setBizContent ( $biz_content );
	
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_trade_fastpay_refund_query_response;
		return $response;
	}


	/**
	 * alipay.trade.query (统一收单线下交易查询)
	 * @param $builder 业务参数，使用build中的对象生成。
	 * @return $response 支付宝返回的信息
 	*/
	public function query($builder) {
		$biz_content = $builder->getBizContent();
		//$this->writeLog($biz_content);
		$request = new AlipayTradeQueryRequest();
		$request->setBizContent ( $biz_content );
		$response = $this->aopclientRequestExecute ( $request );

		return $response->alipay_trade_query_response;
	}

	/**
	 * alipay.trade.query (统一收单线下交易查询)
	 * @param $tradeNo 支付宝交易号
	 * @return $response 支付宝返回的信息
 	*/
	public function orderQuery($tradeNo) {
		$queryContentBuilder = new AlipayTradeQueryContentBuilder();
		$queryContentBuilder->setTradeNo($tradeNo);
		$response = $this->query($queryContentBuilder);
		return $response;
	}

	// 当面付2.0消费查询
	public function queryTradeResult($req){
		$response = $this->query($req);
		$result = new AlipayF2FQueryResult($response);

		if($this->querySuccess($response)){
			// 查询返回该订单交易支付成功
			$result->setTradeStatus("SUCCESS");

		} elseif ($this->tradeError($response)){
			//查询发生异常或无返回，交易状态未知
			$result->setTradeStatus("UNKNOWN");
		} else {
			//其他情况均表明该订单号交易失败
			$result->setTradeStatus("FAILED");
		}
		return $result;

	}

	// 查询返回“支付成功”
	protected function querySuccess($queryResponse){
		return !empty($queryResponse)&&
				$queryResponse->code == "10000"&&
				($queryResponse->trade_status == "TRADE_SUCCESS"||
					$queryResponse->trade_status == "TRADE_FINISHED");
	}

	// 查询返回“交易关闭”
	protected function queryClose($queryResponse){
		return !empty($queryResponse)&&
		$queryResponse->code == "10000"&&
		$queryResponse->trade_status == "TRADE_CLOSED";
	}

	// 交易异常，或发生系统错误
	protected function tradeError($response){
		return empty($response)||
					$response->code == "20000";
	}
	

	/**
	 * alipay.data.dataservice.bill.downloadurl.query (查询对账单下载地址)
	 * @param $builder 业务参数，使用build中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	public function downloadurlQuery($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
		$request = new alipaydatadataservicebilldownloadurlqueryRequest();
		$request->setBizContent ( $biz_content );
	
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_data_dataservice_bill_downloadurl_query_response;
		return $response;
	}

	/**
	 * 验签方法
	 * @param $arr 验签支付宝返回的信息，使用支付宝公钥。
	 * @return boolean
	 */
	public function check($arr){
	    $aop = new AopClient();
	    $aop->alipayrsaPublicKey = $this->alipay_public_key;
	    $result = $aop->rsaCheckV1($arr, $this->alipay_public_key, $this->signtype);

		if($result){
			$queryResponse = $this->orderQuery($arr['trade_no']);
			$result = $this->querySuccess($queryResponse);
		}
		return $result;
	}


}
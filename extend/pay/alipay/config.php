<?php
$config = array (
	//签名方式,默认为RSA2(RSA2048)
	'sign_type' => "RSA2",

	//支付宝公钥
	'alipay_public_key' => $pay_config['alipay_public_key'],

	//商户私钥
	'merchant_private_key' => $pay_config['merchant_private_key'],

	//编码格式
	'charset' => "UTF-8",

	//支付宝网关
	'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

	//应用ID
	'app_id' => $pay_config['appid'],

	//异步通知地址
	'notify_url' => $pay_config['notify_url'],

	//同步通知地址
	'return_url' => $pay_config['return_url']
);
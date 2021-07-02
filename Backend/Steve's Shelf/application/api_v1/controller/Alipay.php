<?php
namespace app\api\controller;

use app\common\service\Config as ConfigService;
use app\common\service\Order as OrderService;

class Alipay extends Common{


	/*
	* 获取订单支付信息
	*/
	public function index(){
		//配置信息
		$ConfigService=new ConfigService();
		$config=$ConfigService->find();
		//订单信息
		$OrderService=new OrderService();
		$order_id=input('get.order_id');
		$order_info=$OrderService->find(['order_id'=>$order_id]);
		// 支付宝合作者身份ID
		$partner = $config['alipay']['appid'];
		// 支付宝账号
		$seller_id = $config['alipay']['user'];
		// 异步通知地址
		$notify_url = urlencode(url('api/pay/notify',"","",true));
		// 订单标题
		$subject = '商品购买-'.$config['base']['title'];
		// 订单金额
		$total =$order_info['price'];
		// 订单详情
		foreach ($order_info->goods as $value) {
			$body.=$value->goods_name."*".$value->num."；";
		}
		//订单ID，开发模式下使用动态ID可重复测试
		if(config('app_debug')){
			$out_trade_no = $order_info['order_id'].'-'.time();
		}else{
			$out_trade_no = $order_info['order_id'].'-'.$order_info['order_no'];
		}
		$parameter = array(
		    'service'        => 'mobile.securitypay.pay',   // 必填，接口名称，固定值
		    'partner'        => $partner,                   // 必填，合作商户号
		    '_input_charset' => 'UTF-8',                    // 必填，参数编码字符集
		    'out_trade_no'   => $out_trade_no,              // 必填，商户网站唯一订单号
		    'subject'        => $subject,                   // 必填，商品名称
		    'payment_type'   => '1',                        // 必填，支付类型
		    'seller_id'      => $seller_id,                 // 必填，卖家支付宝账号
		    'total_fee'      => $total,                     // 必填，总金额，取值范围为[0.01,100000000.00]
		    'body'           => $body,                      // 必填，商品详情
		    'notify_url'     => $notify_url                 // 可选，服务器异步通知页面路径
		 );
		//生成需要签名的订单
		$orderInfo = $this->createLinkstring($parameter);
		//签名
		$sign = $this->rsaSign($orderInfo,$config['alipay']['privatekey']);
		//生成订单
		return $orderInfo.'&sign="'.$sign.'"&sign_type="RSA"';
	}

	/*
	* 支付回调
	*/
	public function notify(){
		\think\Loader::import('wxpay.alipay_notify');
		$alipayNotify  = new \AlipayNotify();
		$verify_result = $alipayNotify->verifyReturn();
		if($verify_result){
			trace("支付回调检测成功",'log');
		}else{
			trace("支付回调检测失败",'log');
		}
	}

	/*
	* 转义签名字符串
	*/
	private function createLinkstring($para) {
	    $arg  = "";
	    while (list ($key, $val) = each ($para)) {
	        $arg.=$key.'="'.$val.'"&';
	    }
	    //去掉最后一个&字符
	    $arg = substr($arg,0,count($arg)-2);
	    //如果存在转义字符，那么去掉转义
	    if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}
	    return $arg;
	}

	/*
	* 生成订单信息
	*/
	function rsaSign($data,$privatekey) {
		//必须按照固定格式
		$privatekeys='-----BEGIN RSA PRIVATE KEY-----
'.$privatekey.'
-----END RSA PRIVATE KEY-----';
	    $res = openssl_pkey_get_private($privatekeys);
	    openssl_sign($data, $sign, $res);
	    openssl_free_key($res);
	    $sign = base64_encode($sign);
	    $sign = urlencode($sign);
	    return $sign;
	}
}
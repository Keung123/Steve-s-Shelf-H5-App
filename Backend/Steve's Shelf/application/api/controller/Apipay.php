<?php
namespace app\api\controller;

use app\common\service\ApiPay as ApiPayService;
use app\common\service\Config as ConfigService;

class Apipay{
    protected $apiPay;
    public function __construct()
    {
        $this->apiPay = new ApiPayService();
    }

    /**
	 * 异步通知（支付宝）
	 */
	public function AliNotify(){
		$data = input('post.');
		file_put_contents('./appAliNotify.txt', date('Y-m-d H:i:s',time()).json_encode($data).'PHP_EOL',FILE_APPEND);
		$result = $this->apiPay->AliNotify($data);
		if($result){
			echo 'success';
		}
		else echo 'fail';
	}

	/**
     * 微信支付异步通知
     */
    public function wxNotify(){
		// $data = $GLOBALS['HTTP_RAW_POST_DATA'];
		$data = file_get_contents("php://input");
		file_put_contents('./appWxNotify.txt', date('Y-m-d H:i:s',time()).json_encode($data).'PHP_EOL',FILE_APPEND);
		file_put_contents('./wx.txt',json_encode($data));
		$result = $this->apiPay->wxNotify($data);
		// return $data;
		echo $result;
    }

	/**
     * 银联支付异步通知
     */
    public function unionNotify(){
        $data = input('post.');
		$result = $this->apiPay->unionNotify($data);
		if(!$result['code']){
			echo $result['msg'];
		}
		else echo $result['data'];
    }
}
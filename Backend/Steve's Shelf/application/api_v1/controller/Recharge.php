<?php
namespace app\api\controller;

use app\common\service\Recharge as RechargeService;
use app\common\service\Config;
class Recharge extends Common{

	/*
	 * 在线充值列表
	 */
	public function reOnline(){
		$rc_service = new RechargeService();
		$list['list'] = $rc_service->rcOnline();
		$ConfigService=new Config();
    	$config=$ConfigService->find();
	 	$return_integral = json_decode($config['return_integral'],true);
		if($return_integral['status'] == 1){
			$list['return_integral'] = 0;
			return $this->json($list);
		}

		$list['return_integral'] = $return_integral['num'];
		return $this->json($list);
	}

	/*
	 * 充值卡列表
	 */
	public function rcList(){
		$rc_service = new RechargeService();
		$list = $rc_service->rcList();
		return $this->json($list);
	}

	/*
	 * 充值
	 */
   	public function rcLine(){
		$rech_uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $rech_uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$rc_type = input('request.rech_type');
		if(!$rc_type){
			return $this->json('', 0, '未知参数');
		}
		$Recharge = new RechargeService();
		$pay_code = input('request.pay_code');//充值方式 wxpay alipay
		$rc_info = [];
		//在线充值
		if($rc_type == 1){			
			 $rc_info['amount'] = input('request.rech_amount');
//			$rc_info['amount'] = 0.01;
			$rc_info['points'] = input('request.points');
		}
		//购买充值卡
		else if($rc_type == 2){
			$rc_info['rc_id'] = input('request.rc_id');
		}
		
		$info = $Recharge->onLine($uid, $rc_type, $rc_info, $pay_code);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data'], 1, '充值成功');
	}

}



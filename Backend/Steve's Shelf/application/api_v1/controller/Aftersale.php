<?php
namespace app\api\controller;

use app\common\service\AfterSale as AsService;
use app\common\service\User as UserService;
class Aftersale extends Common{
	private $uid;
	public function __construct(){
		parent::__construct();
		$this->user = new UserService();
		$this->as = new AsService();
		$user_id = input('request.uid');
		$token = input('request.token');
		if($user_id && $token){
			$uid = $this->getUid($token, $user_id);
			if(!$uid){
				echo json_encode(['data' => [],'status' => 0,'msg' => '未知参数'], JSON_UNESCAPED_UNICODE);
				exit;
			}
			$this->uid = $uid;
		}
		else{
			echo json_encode(['data' => [],'status' => 0,'msg' => '未知参数'], JSON_UNESCAPED_UNICODE);
			exit;
		}
	}

	/*
	 * 售后申请、申请记录列表
	 */
	public function asList(){
		$uid = $this->uid;
		$p = input('request.p');
		$p = $p ? $p : 1;
		$type = input('request.type');
		$list = $this->as->asList($uid, $p, $type);			//1，售后申请列表；2，申请记录列表
		return $this->json($list);
	}

	/*
	 * 申请退换货
	 */
	public function asInfo(){
		$uid = $this->uid;
		$type = input('request.type') ? input('request.type') : 1;		//1，换货；2，退货
		$info = $this->as->getAsInfo($uid, $type);
		return $this->json($info);
	}

	/*
	 * 退换货提交
	 */
	public function asSubmit(){
		$uid = $this->uid;
		$type = input('request.type') ? input('request.type') : 1;
		$data = [
			'as_uid' => $uid,
			'as_order_id' => input('request.orderid'),
			'as_goods_id' => input('request.goodsid'),
			'as_type' => $type,
			'as_reason' => input('request.as_type'),
			'as_user_comm' => input('request.as_content'),
			'as_receiver' => input('request.receiver'),
			'as_phone' => input('request.phone'),
			'as_add_time' => time(),
			'as_stat' => 0
		];

		if(!$data['as_order_id'] || !$data['as_goods_id']){
			return $this->json('', 0, '未知参数');
		}
		//换货
		if($type == 1){
			$data['as_addr'] = input('request.addr');
		}

		if(input('request.as_thumb')){
			$data['as_thumb'] = input('request.as_thumb');
		}
		$result = $this->as->asSubmit($data);
		if(!$result){
			return $this->json('', 0, '申请失败');
		}
		else return $this->json($result, 1, '申请成功');
	}

	/*
	 * 记录详情
	 */
	public function asDetail(){
		$uid = $this->uid;
		$as_id = input('request.as_id');
		$info = $this->as->asDetail($uid, $as_id);
		return $this->json($info);
	}

	/*
	 * 审核进度 
	 */
	public function asLog(){
		$uid = $this->uid;
		$as_id = input('request.as_id');
		$list = $this->as->asLog($uid, $as_id);
		return $this->json($list);
	}
}
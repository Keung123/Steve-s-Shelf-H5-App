<?php
namespace app\api\controller;

use app\common\service\AfterSale as AsService;
use app\common\service\User as UserService;
use app\common\service\Order as OrderService;

class Aftersale extends Common{
	private $uid;
	protected $user;
	protected $as;
	protected $order;
	public function __construct(){
		parent::__construct();
		$this->user = new UserService();
		$this->as = new AsService();
		$this->order = new OrderService();
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

	/**
	 * 售后申请、申请记录列表
	 */
	public function asList()
    {
		$uid = $this->uid;
        $page = input('request.page');
        $page = $page ? $page : 1;
		$type = input('request.type');
		$list = $this->as->asList($uid, $page, $type);			//1，售后申请列表；2，申请记录列表
		return $this->json($list);
	}

	/**
	 * 申请退换货
	 */
	public function asInfo(){
		$uid = $this->uid;
		$type = input('request.type') ? input('request.type') : 1;		//1，换货；2，退货
		$info = $this->as->getAsInfo($uid, $type);
		return $this->json($info);
	}

	/**
	 * 退换货提交
	 */
	public function asSubmit(){
		$uid = $this->uid;
		$type = input('request.type') ? input('request.type') : 1;
		$data = [
			'as_uid' => $uid,
			'as_order_id' => input('request.orderId'),
			'as_goods_id' => input('request.goodsId', ''),
			'as_type' => $type,
			'as_reason' => input('request.as_type'),
			'as_user_comm' => input('request.as_content'),
			'as_receiver' => input('request.receiver'),
			'as_phone' => input('request.phone'),
			'as_add_time' => time(),
			'as_stat' => 0
		];

		if(!$data['as_order_id']){
			return $this->json('', 0, '未知参数');
		}
		//换货
		if($type == 1){
			$data['as_addr'] = input('request.addr');
		}

		$data['as_thumb'] = input('request.as_thumb', '');

		$result = $this->as->asSubmit($data);
		if(!$result){
			return $this->json('', 0, '申请失败');
		}
		else return $this->json($result, 1, '申请成功');
	}

	/**
	 * 申请记录详情
     * @param int uid
     * @param int applyId 售后记录ID
     * @param string token
     * @return json
	 */
	public function asDetail()
    {
		$uid = $this->uid;
		$as_id = input('request.applyId');
		$info = $this->as->asDetail($uid, $as_id);
		return $this->json($info);
	}

    /**
     * 审核进度
     * @param int uid
     * @param int applyId 售后记录ID
     * @param string token
     * @return json
     */
	public function asLog()
    {
		$uid = $this->uid;
		$as_id = input('request.aapplyId');
		$list = $this->as->asLog($uid, $as_id);
		return $this->json($list);
	}

    /**
     * 获取售后原因及进度
     * @param int uid
     * @param string token
     * @param int orderId
     * @return json
     */
    public function getAsData()
    {
        $uid = $this->uid;
        $og_id = input('request.orderId');
        $row = $this->order->getAsData($uid,$og_id);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0, '暂无数据');
    }
}
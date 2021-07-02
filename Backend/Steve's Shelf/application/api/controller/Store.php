<?php
namespace app\api\controller;

use app\common\service\User as UserService;
use app\common\service\Store as StoreService;
class Store extends Common{
	private $uid;
	protected $user;
	protected $store;
	public function __construct(){
		parent::__construct();
		$this->user = new UserService();
		$this->store = new StoreService();
		$user_id = input('request.uid');
		$token = input('request.token');
		if(isset($user_id) && $user_id &&$token){
			$uid = $this->getUid($token, $user_id);
			if(!$uid){
				echo json_encode(['data' => [],'status' => 0,'msg' => '未知参数'], JSON_UNESCAPED_UNICODE);
				exit;
			}
			$this->uid = $uid;
		}
	}

	/**
	 * 店铺首页
	 */
	public function storeHome()
    {
		$uid = $this->uid;
		$p = input('request.page');
		$p = $p ? $p : 1;
		$info = $this->store->storeHome($uid, $p);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
	 * 自己购物明细
	 */
	public function myTotal()
    {
		$uid = $this->uid;
		$month = input('request.month', '');
		$p = input('request.page', 1);
		$info = $this->store->myTotal($uid, $p, $month);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
	 * 子店铺
	 */
	public function childStore()
    {
		$uid = $this->uid;
		$month = input('request.month', '');
		$p = input('request.page', 1);
		$info = $this->store->childStore($uid, $p, $month);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
	 * 子VIP
	 */
	public function childVip()
    {
		$uid = $this->uid;
		$month = input('request.month', '');
		$p = input('request.page', 1);
		$info = $this->store->childVip($uid, $p, $month);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}
	/**
     * 销售利润
     */
	public function saleProfit()
    {
		$uid = $this->uid;
		$month = input('request.month', '');
		$p = input('request.p', 1);
		$info = $this->store->saleProfit($uid, $p, $month);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
     * 团队奖励
     */
	public function teamReward()
    {
		$uid = $this->uid;
		$month = input('request.month', '');
		$p = input('request.page', 1);
		$info = $this->store->teamReward($uid, $p, $month);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
     * 业绩奖励
     */
	public function perforReward()
    {
		$uid = $this->uid;
		$month = input('request.month', '');
		$p = input('request.page', 1);
		$info = $this->store->perforReward($uid, $p, $month);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data'], $info['code'], '获取成功');
	}

	/**
	 * 店铺设置
	 */
	public function storeSetting(){
		$uid = $this->uid;
		$info = $this->store->storeSetting($uid);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);

	}

	/**
	 * 设置信息保存
	 */
	public function settingSave(){
		$uid = $this->uid;
		$data = [
			's_name' => input('request.name'),
			's_intro' => input('request.intro'),
			's_logo' => input('request.logo'),
			's_thumb' => input('request.thumb')
		];
		$result = $this->store->settingSave($uid, $data);
		if(!$result['code']){
			return $this->json('', 0, $result['msg']);
		}
		return $this->json('', 1, '保存成功');
	}

	/**
	 * 店铺销售额明细
	 */
	public function saleRoom()
    {
		$uid = $this->uid;
		$p = input('request.page');
		$p = $p ? $p : 1;
		$month = input('request.month');
		$info = $this->store->saleRoom($uid, $p, $month);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
	 * 店铺销售额奖励规则
	 */	
	public function srRules(){
		$uid = $this->uid;
		$info = $this->store->srRules($uid);
		return $this->json($info['data']);
	}

	/**
	 * 店主的邀请页面--VIP
	 */
	public function inviteCode(){
		$uid = $this->uid;
		$info = $this->store->inviteCode($uid);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json(['invite_code' => $info['data']]);
	}

	/**
	 * 邀请开店
	 */
	public function openStoreGift(){
		$uid = $this->uid;
		$info = $this->store->openStoreGift();
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}
	/**
	 * 赠送店铺
	 */
	public function givingStoreInfo(){
		$goods_id = input('request.goods_id');
		$info =  $this->store->getGoodsInfo($goods_id);
		if(!$info){
			return $this->json('', 0, '此商品不存在！');
		}
		return $this->json($info);
	}
	/**
	 * 赠送店铺 提交
	 */
	public function givingStoreGift(){
	



	
	}
	/**
	 * 业绩管理
	 */
	public function perforManage()
    {
		$uid = $this->uid;
		$p = input('request.page');
		$info = $this->store->perforManage($uid, $p);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
	 * 我的明细
	 */
	public function perforInfo()
    {
		$uid = input('request.uid');
		$p = input('request.page');
		$p = $p ? $p : 1;
		$month = input('request.month');
		$info = $this->store->perforInfo($uid, $p, $month);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
	 * VIP管理
	 */
	public function vipManage()
    {
		$uid = $this->uid;
		$p = input('request.page');
		$mobile = input('request.mobile');
		$p = $p ? $p : 1;
		$info = $this->store->vipManage($uid, $p,$mobile);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
	 * 店铺管理
	 */
	public function storeManage()
    {
		$uid = $this->uid;
		$p = input('request.page');
		$p = $p ? $p : 1;
		$info = $this->store->storeManage($uid, $p);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data']);
	}

	/**
	 * 店铺商品管理
	 */
	public function goodsManage()
    {
		$uid = $this->uid;
		$type = input('request.type');		//1，加入；2，移除
		$goods_id = input('request.goodsId');
		if(!$goods_id){
			return $this->json('', 0, '未知参数');
		}
		$info = $this->store->goodsManage($uid, $goods_id, $type);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json('', 1, '操作成功');
	}

	/**
	 * 随机获取邀请码
	 */
	public function getRandCode(){
		$info = $this->store->getRandCode();
		if(!$info){
			return $this->json('', 0, '获取失败');
		}
		return $this->json(['invite_code' => $info]);
	}
	/**
	 * 分享大礼包
	 */
	public function getGiftBag(){
		$uid = $this->uid;
		$goods_id = input('request.goods_id');
		if(!$goods_id){
			return $this->json('', 0, '未知参数');
		}
		$info = $this->store->getGiftBag($uid, $goods_id);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json('', 1, $info['data']);
	}

	/**
	 * 领取分享大礼包
	 */
	public function shareGiftbag()
    {
		$uid = $this->uid;
		$bag_id = input('request.bag_id');
		if(!$bag_id){
			return $this->json('', 0, '未知参数');
		}
		$result = $this->store->shareGiftbag($uid, $bag_id);
		if(!$result['code']){
			return $this->json('', 0, $result['msg']);
		}
		return $this->json('', 1, '领取成功');
	}

    /**
     * 资金管理接口
     * @return \think\response\Json
     */
    public function fundManagement()
    {
        $uid = $this->uid;
        $result = $this->store->fundManagement($uid);
        return $this->json($result);
    }

    /**
     * 获取店铺直属大礼包，和所有子级大礼包
     * @return \think\response\Json
     */
    public function getAllGifts()
    {
        $uid = $this->uid;
        $result = $this->store->getAllGifts($uid);
        return $this->json($result);
    }
}
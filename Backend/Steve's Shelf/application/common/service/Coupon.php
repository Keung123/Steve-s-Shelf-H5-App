<?php
namespace app\common\service;

use app\common\model\Coupon as CouponModel;
use app\common\model\CouponUsers as CouponUsersModel;
use think\Db;

class Coupon extends Base{
    public $couponModel;
    public $couponUsersModel;

	public function __construct(){
		parent::__construct();
		$this->couponModel = new CouponModel();
        $this->couponUsersModel = new CouponUsersModel();
	}

    /**
     * 用户优惠券列表
     * @param $where ['c_uid => 1', ...]
     * @param string $field
     * @param string $order
     * @param int $start
     * @param int $limit
     * @return mixed
     */
    public function getUserList($where,$field = '*', $order = 'coupon_id desc', $start = 0, $limit = 10)
    {
        $list = $this->couponUsersModel->getList($where,$field, $order, $start, $limit);
        return $list;
    }

    /**
     * 领取优惠券
     * @param $user_id
     * @param $coupon_id
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCoupon($user_id, $coupon_id)
    {
        $coupon_info = $this->couponModel->where(array('coupon_id' => $coupon_id))->find();
        if (!$coupon_info) {
            return -1;
            exit;
        }
        // 判断用户的优惠券
        $where = [
            'coupon_id' => $coupon_id,
            'c_uid' => $user_id
        ];
        $user_coupon_count = $this->couponUsersModel->where($where)->count();

        if($coupon_info['coupon_get_limit'] <= $user_coupon_count){
            return -2;
            exit;
        }

        $coupon_data = array(
            'coupon_id' => $coupon_id,
            'c_uid' => $user_id,
            'c_coupon_type'=>$coupon_info['coupon_type'],
            'add_time' => time(),
            'c_coupon_title' => $coupon_info['coupon_title'],
            'c_coupon_type' => $coupon_info['coupon_type'],
            'c_coupon_price' => $coupon_info['coupon_price'],
            'c_coupon_buy_price' => $coupon_info['coupon_use_limit'],
            'coupon_type_id' => $coupon_info['coupon_type_id'],
            'coupon_aval_time' => $coupon_info['coupon_aval_time'],
            'coupon_stat' => 1,
        );
        $res = $this->couponUsersModel->insert($coupon_data);
      
      $this->couponModel->where(array('coupon_id' => $coupon_id))->setDec('coupon_total',1);
      
        if ($res) {
            return 1;
        } else {
            return 0;
            exit;
        }
    }

	// 获取商品列表
	public function getGoodsList(){
		$data = Db::name('goods')->where('status',0)->select();
		return $data;
    }

	// 获取 活动列表
	public function getActiveList(){
	 	$data = Db::name('active_type')->where('status',0)->select();
		return $data;
    }
    /*
     * 获取优惠券信息
     */
    public function getCouponInfo($c_id)
    {
        return $this->couponUsersModel->where(['c_id' => $c_id])->find();
    }

    /*
     * 获取 优惠券信息
     */
    public function getCouponInfos($id)
    {
        return $this->couponModel->where(['coupon_id' => $id])->value('coupon_thumb');
    }
    /*
     * 获取某优惠券领取数量
     */
    public function getNumer($coupon_id)
    { 
		$map = [
			'coupon_id'=>$coupon_id
		];
		$numb = $this->couponModel->where($map)->select();
		$numb = count($numb);
        return $numb;
    }

}
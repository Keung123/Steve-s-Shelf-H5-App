<?php
namespace app\common\model;

class Order extends Base{
	protected $insert = ['uid','status'=>0,'create_time','update_time'];

	/*
	* 自动完成用户ID
	*/
	protected function setUidAttr(){
		return session('id');
	}

	/*
	*  自动完成创建时间
	*/
	protected function setCreateTimeAttr(){
		return time();
	}

	/*
	* 获取订单状态
	*/
	public function getStatusNameAttr($value,$data){
        $status = [-1=>'已关闭',0=>'待付款',1=>'待发货',2=>'待收货',99=>'已完成'];
        return $status[$data['status']];
	}

	/*
	* 关联订单商品
	*/
	public function goods(){
		return $this->hasMany('OrderGoods');
	}
}
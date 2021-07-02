<?php
namespace app\common\model;

class Withdrawals extends Base{
	
	/*
	* 关联用户
	*/
    public function user(){
        return $this->hasOne('User','id','uid');
    }

	/*
	* 获取状态
	*/
	public function getStatusNameAttr($value,$data){
        $status = [-1=>'拒绝提现',0=>'待审核',1=>'待转账',99=>'已转款'];
        return $status[$data['status']];
	}    
    	
}
<?php
namespace app\common\model;

class PointLog extends Base{

	/*
	* 关联用户
	*/
    public function user(){
        return $this->hasOne('User','id','uid');
    }	

	/*
	* 积分变动类别
	*/
	public function getTypeNameAttr($value,$data){
        $status = [1=>'推广用户产生订单奖励',2=>'积分提现扣除'];
        return $status[$data['type']];
	}    
}
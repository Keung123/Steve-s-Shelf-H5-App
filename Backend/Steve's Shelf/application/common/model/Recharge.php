<?php
namespace app\common\model;

class Recharge extends Base{
	protected $insert = ['rech_create_time'];
	/*
	*  自动完成创建时间
	*/
	protected function setRechCreatetimeAttr(){
		return time();
	}

	/*
	 * 查询时间返回格式化
	 */
	protected function getRechPayTimeAttr($val){
		if($val){
			return date('Y-m-d H:i:s', $val);
		}
		return '';		
	}

	/*
	 * 充值方式
	 */
	protected function getRechTypeAttr($val){
		return $val == 1 ? '在线充值' : '购买充值卡';
	}
}
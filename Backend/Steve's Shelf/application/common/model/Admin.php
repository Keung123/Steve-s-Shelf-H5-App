<?php
namespace app\common\model;

class Admin extends Base{
	protected $insert = ['status'=>'normal','create_time'];

	/*
	*  自动完成创建时间
	*/
	protected function setCreateTimeAttr(){
		return time();
	}
}
<?php
namespace app\common\model;

class Task extends Base{
	protected $insert = ['create_time'];

	/*
	*  自动完成创建时间
	*/
	protected function setCreateTimeAttr(){
		return time();
	}
}
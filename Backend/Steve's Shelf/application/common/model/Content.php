<?php
namespace app\common\model;

class Content extends Base{
	protected $insert = ['admin_id','create_time','update_time'];
	protected $update = ['update_time'];

	/*
	* 自动完成管理员ID
	*/
	protected function setAdminIdAttr(){
		return session('admin_id');
	}


	/*
	*  自动完成创建时间
	*/
	protected function setCreateTimeAttr(){
		return time();
	}

	/*
	*  自动完成更新时间
	*/
	protected function setUpdateTimeAttr(){
		return time();
	}
}
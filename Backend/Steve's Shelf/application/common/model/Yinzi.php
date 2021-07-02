<?php
namespace app\common\model;

class Yinzi extends Base{
	protected $insert = ['yin_add_time'];
	/*
	*  自动完成创建时间
	*/
	protected function setYinAddTimeAttr(){
		return time();
	}
}
<?php
namespace app\common\service;

use app\common\model\Active as ActiveModel;
use think\Db;

class Active extends Base{

	public function __construct(){
		parent::__construct();
		$ActiveModel=new ActiveModel();
		$this->model=$ActiveModel;
	}
	// 获取活动列表
	public function getList($where = array(), $field = '*', $order = 'id desc', $limin = 20){
	    $list = $this->model->field($field)->where($where)->order($order)->limit($limin)->select();
	    return $list;
    }
}
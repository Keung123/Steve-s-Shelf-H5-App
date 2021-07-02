<?php
namespace app\common\service;

use app\common\model\Supplier as SupplierModel;
use think\Db;

class Supplier extends Base{

	public function __construct(){
		parent::__construct();
		$SupplierModel=new SupplierModel();
		$this->model=$SupplierModel;
	}
	// 获取列表
	public function getList($where = array(), $field = '*', $order = 'id desc', $limin = 20){
	    $list = $this->model->field($field)->where($where)->order($order)->limit($limin)->select();
	    return $list;
    }
}
<?php
namespace app\common\service;

use app\common\model\GoodsBrand;
use think\Db;

class GoodscateBrand extends Base{
	// public $model;
	public function __construct(){
		parent::__construct();
        $GoodsBrandModel=new GoodsBrand();
		$this->model=$GoodsBrandModel;
	}

	/*
	 * 获取分类
	 */
	public function getBrand(){
		return $this->model->select();
	}
	/*
     * 获取供应商信息
     */
    public function supplierName($supplierId)
    {
		$map = [
			'id'=>$supplierId
		];
         $res = Db::name('supplier')->find($map);
		 return $res;
    }
}
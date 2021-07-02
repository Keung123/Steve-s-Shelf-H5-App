<?php
namespace app\common\service;

use app\common\model\ServiceCategory as ServiceCategoryModel;

class ServiceCategory extends Base{

	public function __construct(){
		parent::__construct();
		$ServiceCategoryModel=new ServiceCategoryModel();
		$this->model=$ServiceCategoryModel;
	}
}
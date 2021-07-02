<?php
namespace app\common\service;

use app\common\model\ContentCategory as ContentCategoryModel;

class ContentCategory extends Base{

	public function __construct(){
		parent::__construct();
		$ContentCategoryModel=new ContentCategoryModel();
		$this->model=$ContentCategoryModel;
	}
}
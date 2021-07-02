<?php
namespace app\common\service;

use app\common\model\Stock as PositionModel;

class Stock extends Base{
	public function __construct(){
		parent::__construct();
		$PositionModel=new PositionModel();
		$this->model=$PositionModel;
	}	
}
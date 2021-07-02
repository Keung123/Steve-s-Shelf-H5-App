<?php
namespace app\common\service;

use app\common\model\Position as PositionModel;

class Position extends Base{
	public function __construct(){
		parent::__construct();
		$PositionModel=new PositionModel();
		$this->model=$PositionModel;
	}	
}
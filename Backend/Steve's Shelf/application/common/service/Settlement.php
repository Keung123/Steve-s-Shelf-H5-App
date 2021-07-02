<?php
namespace app\common\service;

use app\common\model\Settlement as SettlementModel;

class Settlement extends Base{
	public function __construct(){
		parent::__construct();
        $SettlementModel=new SettlementModel();
		$this->model = $SettlementModel;
	}	
}
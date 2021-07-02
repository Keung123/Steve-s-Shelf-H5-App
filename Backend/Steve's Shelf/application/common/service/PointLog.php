<?php
namespace app\common\service;

use app\common\model\PointLog as PointLogModel;

class PointLog extends Base{

	public function __construct(){
		parent::__construct();
		$PointLogModel=new PointLogModel();
		$this->model=$PointLogModel;
		$this->with='user';
	}
	
	public function select($map=[],$field="*",$order="",$limit="",$join=""){
		$res=parent::select($map,$field,$order,$limit,$join);
		foreach ($res as &$value) {
			$value['type_name']=$value->type_name;
		}
		return $res;
	}	
	
}
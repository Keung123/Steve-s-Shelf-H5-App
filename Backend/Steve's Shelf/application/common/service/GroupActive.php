<?php
namespace app\common\service;

use app\common\model\GroupActive as GroupActiveModel;

class GroupActive extends Base{

	public function __construct(){
		parent::__construct();
		$GroupActiveModel=new GroupActiveModel();
		$this->model=$GroupActiveModel;
	}
}
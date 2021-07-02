<?php
namespace app\common\service;

use app\common\model\Group as GroupModel;
class Group extends Base{

	public function __construct(){
		parent::__construct();
		$GroupModel=new GroupModel();
		$this->model=$GroupModel;
	}
}
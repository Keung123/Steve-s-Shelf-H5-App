<?php
namespace app\common\service;

use app\common\model\Service as ServiceModel;

class Service extends Base{

	public function __construct(){
		parent::__construct();
        $ServiceModel=new ServiceModel();
		$this->model=$ServiceModel;
	}
}
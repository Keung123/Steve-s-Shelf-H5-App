<?php
namespace app\admin\controller;

use think\Controller;
use app\common\service\Admin;
use app\common\service\Config;

class Common extends Controller{

	public function _initialize(){
		//获取开发模式状态
		$this->assign('app_debug',config('app_debug'));
		//获取配置信息
		$ConfigService=new Config();
		$config=$ConfigService->find();
		$this->assign('config',$config);
	}
}
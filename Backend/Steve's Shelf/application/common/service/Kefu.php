<?php
namespace app\common\service;

use app\common\model\Kefu as KefuModel;
use think\Db;

class Kefu extends Base{

	public function __construct(){
		parent::__construct();
        $KefuModel=new KefuModel();
		$this->model=$KefuModel;
	}
	/**
     *  @param $admin_id
     * 获取客服id
     */
    public function getkefu($admin_id){
        $kf_id = Db::name("admin")->where('admin_id',$admin_id)->value("kf_id");
        return $kf_id;
    }
}
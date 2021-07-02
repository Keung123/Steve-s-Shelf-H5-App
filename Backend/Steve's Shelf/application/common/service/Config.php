<?php
namespace app\common\service;

use app\common\model\Config as ConfigModel;
use think\Db;

class Config extends Base{

	public function __construct(){
		parent::__construct();
		$ConfigModel=new ConfigModel();
		$this->model=$ConfigModel;
	}	

	public function set($key,$value){
//		return parent::save(['id'=>1],[$key=>$value]);
		return Db::name('config')->where(['id'=>1])->update([$key=>$value]);
	}
    public function setGuize($data){
        return parent::save(['id'=>1],$data);
    } 
	public function setExpress($data){
        return parent::save(['id'=>2],$data);
    }
	public function find(){
		return parent::find(['id'=>1]);
	}
	
	/*
	 * 获取规则
	 */
	public function getGuize($guize_name){
		if($guize_name == '签到规则'){
			$field = 'qiandao';
		}else if($guize_name == '积分兑换规则'){
			$field = 'jifenduihuan';
		}
		$res = Db::name('config')->value($field);
		return $res;
	}
}
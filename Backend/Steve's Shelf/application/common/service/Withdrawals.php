<?php
namespace app\common\service;

use app\common\model\Withdrawals as WithdrawalsModel;
use think\Db;

class Withdrawals extends Base{
	public function __construct(){
		parent::__construct();
		$WithdrawalsModel=new WithdrawalsModel();
		$this->model=$WithdrawalsModel;
	}
	public function select($map=[],$field="*",$order="",$limit="",$join=""){
		$res=parent::select($map,$field,$order,$limit,$join);
		foreach ($res as &$value) {
			$value['user']=$value->user;
			$value['status_name']=$value->status_name;
		}
		return $res;
	}

	/*
	* 拒绝申请
	*/
	public function refuse($map){
		$list=parent::select($map);
		foreach ($list as $value) {
			$res=$this->refuseDo($value);
			if(!$res){
				return 0;
			}
		}
		return 1;
	}

	/*
	* 拒绝操作执行
	*/
	public function refuseDo($data){
		// 启动事务
		Db::startTrans();
		try{
			//修改记录状态
			Db::name('withdrawals')->where('id',$data['id'])->setField('status',-1);
			//返还用户积分
			Db::name('user')->where('id',$data['uid'])->setInc('point',$data['point']);
			//记录积分日志
			$point_data=[
				'uid'=>$data['uid'],
				'object_id'=>$data['id'],
				'num'=>-$data['point'],
				'create_time'=>time(),
				'type'=>3
			];
			Db::name('point_log')->insert($point_data);
		    // 提交事务
		    Db::commit();	
		    return 1;			
		} catch (\Exception $e) {
			print_r($e);
		    // 回滚事务
		    Db::rollback();
		    return 0;
		}
	}

	/*
	* 通过申请
	*/		
	public function pass($map){
		return parent::save($map,['status'=>1]);
	}	

	/*
	* 转账完成
	*/
	public function transfer($map){
		return parent::save($map,['status'=>99]);
	}
}
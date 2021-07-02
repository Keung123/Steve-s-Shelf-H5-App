<?php
namespace app\common\service;

use app\common\model\Task as TaskModel;
use app\common\model\TaskGoods as TaskGoodsModel;

class Task extends Base{
	public function __construct(){
		parent::__construct();
		$TaskModel=new TaskModel();
		$this->model=$TaskModel;
	}

	public function add($data){
		//查询当天记录
		$map['create_time']=['>',strtotime(date('Y-m-d 0:00:00',time()))];
		$count=$this->count($map);
		$count++;
		$data['task_no']='LS-'.date('Y-m-d').'-'.str_repeat('0',3-strlen($count)).$count;
		$data['create_time']=time();
		return parent::addGetId($data);
	}

	/*
	* 添加记录
	*/
	public function addGoods($data){
		$TaskGoodsModel=new TaskGoodsModel();
		$this->model=$TaskGoodsModel;
		return parent::add($data);
	}

	/*
	* 查询商品记录
	*/
	public function getGoodsList($map=[],$field="*",$order="",$limit="",$join=""){
		$TaskGoodsModel=new TaskGoodsModel();
		$this->model=$TaskGoodsModel;
		$data['total']=parent::count($map);
		$data['rows']=parent::select($map,$field,$order,$limit,$join);	
		return $data;
	}

	/*
	* 查询商品记录详情
	*/
	public function getGoodsInfo($map){
		$TaskGoodsModel=new TaskGoodsModel();
		$this->model=$TaskGoodsModel;
		return parent::find($map);		
	}

	/*
	* 更新商品记录详情
	*/
	public function updateGoods($map,$data){
		$TaskGoodsModel=new TaskGoodsModel();
		$this->model=$TaskGoodsModel;
		return parent::save($map,$data);	
	}

	/*
	* 删除记录
	*/
	public function deleteGoods($map){
		$TaskGoodsModel=new TaskGoodsModel();
		$this->model=$TaskGoodsModel;		
		return parent::delete($map);
	}

	/*
	* 查询任务记录
	*/
	public function getList($map=[],$field="*",$order="",$limit="",$join=""){
		$data['total']=parent::count($map);
		$data['rows']=parent::select($map,$field,$order,$limit,$join);	
		foreach ($data['rows'] as $key => $value) {
			$map=[
				'id'=>['in',$value['task_goods_ids']]
			];
			$goods=$this->getGoodsList($map);
			$value['goods']=$goods['rows'];
		}
		return $data;
	}	

}
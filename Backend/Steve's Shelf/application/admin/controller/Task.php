<?php
namespace app\admin\controller;

use app\common\service\Task as TaskService;
class Task extends Base{

	/*
	* 未处理商品列表
	*/
	public function goods(){

		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');

			$map['status']=0;
			$TaskService=new TaskService();
			$data=$TaskService->getGoodsList($map,'*',$order,$limit);
			return json($data);
		}else{
			return $this->fetch();
		}		
	}

	/*
	* 添加记录
	*/
    public function goodsAdd(){
		if(request()->isAjax()){
			$row=input('post.row/a');

			$TaskService=new TaskService();
			$res=$TaskService->addGoods($row);
			return AjaxReturn($res);
		}else{

			return $this->fetch();
		}
    } 

    /*
    * 修改记录
    */
   	public function goodsEdit(){
    	$TaskService=new TaskService();

		if(request()->isAjax()){
			$row=input('post.row/a');
			$map['id']=input('post.id');

			$res=$TaskService->updateGoods($map,$row);
			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['id']=input('get.ids');
			$row=$TaskService->getGoodsInfo($map);
			$this->assign('row',$row);
			return $this->fetch();
		}   		
   	}

   	/*
   	* 删除
   	*/
   	public function goodsDelete(){
   		$ids=input('get.ids');
		$map['id']=['in',$ids];
		$TaskService=new TaskService();
		$res=$TaskService->deleteGoods($map);
		return AjaxReturn($res);     		
   	}    	

   	/*
   	* 添加任务
   	*/
   	public function add(){
   		$TaskService=new TaskService();
		if(request()->isAjax()){
			$row=input('post.row/a');
			$res=$TaskService->add($row);
			if($res>0){
				//设置记录为已处理
				$data['status']=1;
				$map['id']=['in',$row['task_goods_ids']];
				$TaskService->updateGoods($map,$data);
			}			
			return AjaxReturn($res,['task_id'=>$res]);
		}else{
			//查询记录
			$map['id']=['in',input('ids')];
			$data=$TaskService->getGoodsList($map);
			$this->assign('rows',$data['rows']);
			return $this->fetch();
		}   		
   }

   /*
   * 任务详情
   */
  	public function info(){
  		$page=input('page',1);
  		$pageSize=10;
  		$limit=(($page-1)*$pageSize).",".$pageSize;

  		$TaskService=new TaskService();
  		$map['task_id']=input('get.task_id');
  		$info=$TaskService->find($map);
  		$map=[
  			'id'=>['in',$info['task_goods_ids']]
  		];
  		$goods=$TaskService->getGoodsList($map,"*","",$limit);
  		$this->assign('pageSize',$pageSize);
  		$this->assign('page',$page);
  		$this->assign('totalPage',ceil($goods['total']/$pageSize));
  		$info['goods']=$goods['rows'];
  		$this->assign('info',$info);
  		return $this->fetch();
  	}

  	/*
  	* 任务列表
  	*/
  	public function index(){
		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');

			if(input('get.search')){
				$map['buyer|link_name|link_type|address']=['like','%'.input('get.search').'%'];
			}
			$TaskService=new TaskService();
			$data=$TaskService->getList($map,'*',$order,$limit);
			return json($data);
		}else{
			return $this->fetch();
		}  		
  	}

   	/*
   	* 删除任务
   	*/
   	public function delete(){
   		$ids=input('get.ids');
		$map['task_id']=['in',$ids];
		$TaskService=new TaskService();
		$res=$TaskService->delete($map);
		return AjaxReturn($res);     		
   	}  	
}
<?php
namespace app\admin\controller;

use app\common\service\Stock as PositionService;
use think\Db;

class Stock extends Base{

	/*
	* 广告位列表
	*/
	public function index(){
		$title = trim(input('title'));
		$this->assign('title',$title);
		if(request()->isAjax()){
			//排序
			// $order=input('get.sort')." ".input('get.order');
			$order = 'id asc';
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if(input('title')){
				$map['title']  = ['like','%'.input('title').'%'];	
			} 
			$PositionService=new PositionService();
			$total=$PositionService->count($map);
			$rows=$PositionService->select($map,'*',$order,$limit);
			// print_r(Db::name('position')->getLastsql());
			return json(['total'=>$total,'rows'=>$rows]);
		}
		else{
			return $this->fetch();
		}		
	}

	/*
	* 添加广告位
	*/
	public function add(){
		if(request()->isAjax()){
			$row=input('post.row/a');

			$PositionService=new PositionService();
			


			$res=$PositionService->add($row);

			//添加日志记录
            $id=db('stock')->getLastInsID();
            $this->write_log('添加仓库',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			return $this->fetch();
		}  		
	}

	/*
	* 删除广告位
	*/
	public function delete(){
		$ids=input('get.ids');
		$PositionService=new PositionService();
		$map['id']=['in',$ids];
		$res=$PositionService->delete($map);

        //添加日志记录
        $this->write_log('删除广告位',$ids);

		return AjaxReturn($res);
	}

	/*
	* 编辑广告位
	*/
	
	public function edit(){
    	$PositionService=new PositionService();

		if(request()->isAjax()){
			$row=input('post.row/a');
			$map['id']=input('post.id');

			$res=$PositionService->save($map,$row);

            //添加日志记录
            $this->write_log('编辑广告位',$map['id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['id']=input('get.ids');
			$row=$PositionService->find($map);
			$this->assign('row',$row);
			return $this->fetch();
		}
	}	

	
}

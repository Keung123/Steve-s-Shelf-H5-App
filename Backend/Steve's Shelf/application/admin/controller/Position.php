<?php
namespace app\admin\controller;

use app\common\service\Position as PositionService;
use app\common\service\Adsense as AdsenseService;
use think\Db;

class Position extends Base{

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
            $id=db('position')->getLastInsID();
            $this->write_log('添加广告位',$id);

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

	/*
	* 广告列表
	*/
	public function adsense(){
		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if(input('pid')){
				$map['pid']=input('pid');
			}
			$AdsenseService=new AdsenseService();
			$total=$AdsenseService->count($map);
			$rows=$AdsenseService->select($map,'*',$order,$limit);
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}		
	}

	/*
	* 添加广告
	*/
	public function adsenseAdd(){
		if(request()->isAjax()){
			$row=input('post.row/a');
          
          	if(!empty($row['parame']) && $row['type']==1){
              //判断需要添加的商品是否存在
              $num = Db::name('goods')->where('goods_id',$row['parame'])->where('status','eq',0)->count();
              if (!$num){
                  return AjaxReturn(-2006);
              }
            }
          
          if(!empty($row['parame']) && $row['type']==2){
              //判断需要添加的文章是否存在
              $num = Db::name('content')->where('content_id',$row['parame'])->where('status','eq','normal')->count();
              if (!$num){
                  return AjaxReturn(-2006);
              }
            }

			$AdsenseService=new AdsenseService();
			$res=$AdsenseService->add($row);

            //添加日志记录
            $id=db('adsense')->getLastInsID();
            $this->write_log('添加广告',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			return $this->fetch();
		}
	}

	/*
	* 编辑广告
	*/
	public function adsenseEdit(){
    	$AdsenseService=new AdsenseService();

		if(request()->isAjax()){
			$row=input('post.row/a');
			$map['id']=input('post.id');
            if(!empty($row['parame']) && $row['type']==1){
              //判断需要添加的商品是否存在
              $num = Db::name('goods')->where('goods_id',$row['parame'])->where('status','eq',0)->count();
              if (!$num){
                  return AjaxReturn(-2006);
              }
            }
          
          if(!empty($row['parame']) && $row['type']==2){
              //判断需要添加的文章是否存在
              $num = Db::name('content')->where('content_id',$row['parame'])->where('status','eq','normal')->count();
              if (!$num){
                  return AjaxReturn(-2006);
              }
            }
			$res=$AdsenseService->save($map,$row);

            //添加日志记录
            $this->write_log('编辑广告',$map['id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['id']=input('get.ids');
			$row=$AdsenseService->find($map);
			$this->assign('row',$row);
			return $this->fetch();
		}
	}

	/*
	* 删除广告
	*/
	public function adsenseDelete(){
		$ids=input('get.ids');
		$AdsenseService=new AdsenseService();
		$map['id']=['in',$ids];
		$res=$AdsenseService->delete($map);

        //添加日志记录
        $this->write_log('添加广告位',$ids);

		return AjaxReturn($res);
	}
}

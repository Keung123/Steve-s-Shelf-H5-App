<?php
namespace app\admin\controller\service;

use app\common\service\ServiceCategory;
use app\common\service\Service as ServiceService;
use think\Db;
use app\admin\controller\Base;
class Service extends Base{

    /*
    * 分类管理
    */
    public function category(){

        if(request()->isAjax()){
            //排序
            $order=input('get.sort')." ".input('get.order');

            if(input('get.search')){
                $map['category_name']=['like','%'.input('get.search').'%'];
            }

            $ServiceCategory=new ServiceCategory();
            $total = $ServiceCategory->count($map);
            $rows = $ServiceCategory->select($map,'*',$order);
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            //名称加上分级
            foreach ($rows as &$value) {
                $value['category_name']='|—'.str_repeat('—',$value['level']).$value['category_name'];
                $data[]=$value;
            }
            return json(['total'=>$total,'rows'=>$data]);
        }else{
            return $this->fetch();
        }
    }

    /*
    * 添加分类
    */
    public function categoryAdd(){
        $ServiceCategory=new ServiceCategory();
		if(request()->isAjax()){
			$row=input('post.row/a');
			$res = $ServiceCategory->add($row);

            //添加日志记录
            $id=db('service_category')->getLastInsID();
            $this->write_log('客服消息分类添加',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
            //获取分类列表
            $rows = $ServiceCategory->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
			return $this->fetch();
		}    	
    }

    /*
    * 分类编辑
    */
    public function categoryEdit(){
        $ServiceCategory=new ServiceCategory();

        if(request()->isAjax()){
            $row=input('post.row/a');
            $map['category_id']=input('post.category_id');

            $res = $ServiceCategory->save($map,$row);

            //添加日志记录
            $this->write_log('客服消息分类编辑',$map['category_id']);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['category_id']=input('get.ids');
            $row = $ServiceCategory->find($map);
            $this->assign('row',$row);
            //获取分类列表
            $rows = $ServiceCategory->select();
            //转为树形
            $rows = \util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }

    /*
    * 删除分类
    */
    public function categoryDelete(){
		$ids=input('get.ids');
		$map['category_id']=['in',$ids];


        $ServiceCategory=new ServiceCategory();
        $pid=$ServiceCategory->select($map,'pid');

        //判断一级分类下是否有二级分类
        $array=[];
        foreach($pid as $val){
            $array[]=$val['pid'];
        }
        if(in_array(0,$array)){
            $map['pid']=['eq',0];
            $data=$ServiceCategory->select($map,'category_id');
            $category_id=[];
            foreach($data as $val){
                $category_id[]=$val['category_id'];
            }
            $category_ids=implode(',',$category_id);
            $where['pid']=['in',$category_ids];
            $res1=$ServiceCategory->select($where);
            if($res1){
                return (['code'=>0,'msg'=>'此操作一级分类下二级分类不能删除','data'=>'此操作一级分类下二级分类不能删除']);
            }else{
                $res=$ServiceCategory->delete($map);
                return AjaxReturn($res);
            }
        }

        //查询出二级分类下的客服消息并删除
        $Service= new \app\common\service\Service();
        $status=$Service->delete($map);

        $res=$ServiceCategory->delete($map);
        //添加日志记录
        $this->write_log('客服消息分类删除',$ids);

		return AjaxReturn($res);    	
    }

    /*
    * 内容管理
    */
    public function index(){
    	$ServiceService=new ServiceService();
		$title = trim(input('title'));
		$category_name = trim(input('category_name'));
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('title',$title);
        $this->assign('category_name',$category_name);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if(input('title')){
                $map['title']=['eq',input('title')];
            }
			if(input('category_name')){
				$res = Db::name('service_category')->where('category_name',trim(input('category_name')))->field('category_id')->find();
				if($res){
					$map['category_id']=['eq',$res['category_id']];
				}else{
					return json(['total'=>'','rows'=>'']);
				}
            }
		    $start_time = input('start_time');
            $end_time = input('end_time');
            if ($start_time && $end_time) {
                $map['create_time'] = array('between',strtotime($start_time).','.(strtotime($end_time)+3600*24-1));
            } elseif ($start_time) {
                $map['create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['create_time'] = array('<=', (strtotime($end_time)+3600*24-1));
            }
			$total=$ServiceService->count($map);
			$rows = $ServiceService->select($map,'*',$order,$limit);
			if($rows){
                foreach ($rows as $key=>$val){
                    $ServiceCategory=new ServiceCategory();
                    $map2['category_id'] = $val['category_id'];
                    $category_name = $ServiceCategory->find($map2);
                    $rows[$key]['category_name'] = $category_name['category_name'];
                }
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}    	
    }

    /*
    * 添加内容
    */
    public function add(){
		if(request()->isAjax()){
			$row=input('post.row/a');
            $ServiceService=new ServiceService();
			$res=$ServiceService->add($row);

            //添加日志记录
            $id=db('service')->getLastInsID();
            $this->write_log('添加内容',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			//获取分类列表
            $ServiceCategory=new ServiceCategory();
			$map['status']='normal';
			$rows = $ServiceCategory->select($map);
            //转为树形
            $rows = \util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
			return $this->fetch();
		}
    }  

    /*
    * 内容编辑
    */  
   	public function edit(){
        $ServiceService=new ServiceService();

		if(request()->isAjax()){
			$row=input('post.row/a');

			$map['content_id']=input('post.content_id');
			$res=$ServiceService->save($map,$row);

            //添加日志记录
            $this->write_log('编辑内容',$map['content_id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			//获取分类列表
            $ServiceCategory=new ServiceCategory();
            $map['status']='normal';
            $rows = $ServiceCategory->select($map);
            //转为树形
            $rows = \util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
			//商品详情
			$map=[];
			$map['Content_id']=input('get.ids');
			$row=$ServiceService->find($map);
			$this->assign('row',$row);
			return $this->fetch();
		}   		
   	}

   	/*
   	* 删除内容
   	*/
   	public function delete(){
   		$ids=input('get.ids');
		$map['Content_id']=['in',$ids];
        $ServiceService=new ServiceService();
		$res=$ServiceService->delete($map);

        //添加日志记录
        $this->write_log('删除内容',$ids);

		return AjaxReturn($res);     		
   	}

   	/*
   	*内容操作
   	*/
   	public function multi(){		
   		$action=input('action');
   		$ids=input('get.ids');
		$map['Content_id']=['in',$ids];
        $ServiceService=new ServiceService();
		if(!$action){
			return AjaxReturn(UPDATA_FAIL);   
		}
		$data[$action]=input('params');
		$res=$ServiceService->save($map,$data);

        //添加日志记录
        $this->write_log('内容操作',$ids);

		return AjaxReturn($res);
   	}
}

<?php
namespace app\admin\controller\content;

use app\common\service\ContentCategory;
use app\common\service\Content as ContentService;
use app\admin\controller\Base;
class Content extends Base{

    /*
    * 分类管理
    */
    public function category(){
		$category_name = input('category_name');
		$this->assign('category_name',$category_name);
		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if(input('category_name')){
				$map['category_name']=['like','%'.input('category_name').'%'];
			}		
			$ContentCategory=new ContentCategory();
			$total=$ContentCategory->count($map);
			$rows=$ContentCategory->select($map,'*',$order,$limit);
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}
    }

    /*
    * 添加分类
    */
    public function categoryAdd(){
		if(request()->isAjax()){
			$row=input('post.row/a');
			$row['createtime'] = time();
			$ContentCategory=new ContentCategory();
			$res=$ContentCategory->add($row);

			//添加日志记录
			$id=db('content_category')->getLastInsID();
			$this->write_log('添加内容分类',$id);
			return AjaxReturn($res,getErrorInfo($res));
		}else{
			
			return $this->fetch();
		}    	
    }

    /*
    * 分类编辑
    */
    public function categoryEdit(){
    	$ContentCategory=new ContentCategory();

		if(request()->isAjax()){
			$row=input('post.row/a');
			$map['category_id']=input('post.category_id');

			$res=$ContentCategory->save($map,$row);

            //添加日志记录
            $this->write_log('修改内容分类',$map['category_id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['category_id']=input('get.ids');
			$row=$ContentCategory->find($map);
			$this->assign('row',$row);
			return $this->fetch();
		}	    	
    }

    /*
    * 删除分类
    */
    public function categoryDelete(){
		$ids=input('get.ids');
		$map['category_id']=['in',$ids];

		$ContentCategory=new ContentCategory();
		$res=$ContentCategory->delete($map);

        //添加日志记录
        $this->write_log('删除内容分类',$ids);
		return AjaxReturn($res);    	
    }

    /*
    * 商品管理
    */
    public function index(){
    	$ContentService=new ContentService();

		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');

			$total=$ContentService->count($map);
			$rows=$ContentService->select($map,'*',$order,$limit);
			foreach ($rows as &$v){
                $v['category_name'] = db('content_category')->where('category_id',$v['category_id'])->value('category_name');
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}    	
    }
    /*
    * 内容管理列表 分类
    */
    public function contentList(){
    	$ContentService=new ContentService();
		$category_id  = input('id');
		
		// if(!$category_id){
		// var_dump(request()->isAjax());
		if(request()->isAjax()){
			// print_r(input('get.'));
			// exit(print_r($category_id));
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if(input('categoryId')){
				$map['category_id'] = input('categoryId');
			}
			
			$total=$ContentService->count($map);
			$rows=$ContentService->select($map,'*',$order,$limit);
			//获取分类名称
			$ContentCategory=new ContentCategory();
			$res = $ContentCategory->find($map);
			if($rows){
				foreach($rows as $key=>$val){
					$rows[$key]['category_name'] = $res['category_name'];
				}
			}
			return json(['total'=>$total,'rows'=>$rows]);
		}
		else{
			//获取分类列表
			$ContentCategory=new ContentCategory();

			$where['status']='normal';
			$category = $ContentCategory->select($where);
			$this->assign('category',$category);
			$this->assign('categoryId',$category_id);
			return $this->fetch();
		}    	
    }

    /*
    * 添加商品
    */
    public function add(){
		$category_id  = input('categoryId');
		if(request()->isAjax()){
			$row=input('post.');
			$ContentService=new ContentService();
			$res=$ContentService->add($row);

			//添加日志记录
            $id=db('content')->getLastInsID();
            $this->write_log('分类内容添加',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			
			//获取分类列表
			$ContentCategory=new ContentCategory();
			$category=$ContentCategory->select($map);
			
			$this->assign('category',$category);
			$this->assign('categoryId',$category_id);
			return $this->fetch();
		}
    }  

    /*
    * 商品编辑
    */  
   	public function edit(){
   		$ContentService=new ContentService();

		if(request()->isAjax()){
			$row=input('post.');
			$map['content_id']=$row['content_id'];
			unset($row['content_id']);
			$res=$ContentService->save($map,$row);

			//添加日志记录
            $this->write_log('分类内容编辑',$map['content_id']);

			return AjaxReturn($res); 
		}else{
			//获取分类列表
			$ContentCategory=new ContentCategory();
			$map['status']='normal';
			$category=$ContentCategory->select($map);
			$this->assign('category',$category);
			//商品详情
			$map=[];
			$map['Content_id']=input('get.ids');
			$row=$ContentService->find($map);
			$this->assign('row',$row);
			return $this->fetch();
		}   		
   	}

   	/*
   	* 删除商品
   	*/
   	public function delete(){
   		$ids=input('get.ids');
		$map['Content_id']=['in',$ids];
		$ContentService=new ContentService();
		$res=$ContentService->delete($map);

		//添加日志记录
        $this->write_log('分类内容删除',$ids);
		return AjaxReturn($res);     		
   	}

   	/*
   	* 商品操作
   	*/
   	public function multi(){		
   		$action=input('action');
   		$ids=input('get.ids');
		$map['Content_id']=['in',$ids];
		$ContentService=new ContentService();
		if(!$action){
			return AjaxReturn(UPDATA_FAIL);   
		}
		$data[$action]=input('params');
		$res=$ContentService->save($map,$data);

		//添加日志记录
        $this->write_log('商品操作',$ids);

		return AjaxReturn($res);
   	}
}

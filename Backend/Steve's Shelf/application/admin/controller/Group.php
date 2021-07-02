<?php
namespace app\admin\controller;

use app\common\service\Group as GroupService;

class Group extends Base{

	public function _initialize(){
		parent::_initialize();
		//服务
		$GroupService=new GroupService();
		$this->service=$GroupService;
	}	

	/*
	* 会员组列表
	*/
	public function index(){
		if(request()->isAjax()){
			// 排序
			$order=input('get.sort')." ".input('get.order');
			// limit
			$limit=input('get.offset').",".input('get.limit');

			//查询
			if(input('get.search')){
				$map['admin_id|admin_name|mobile|nickname|email|qq']=['like','%'.input('get.search').'%'];
			}

			$total=$this->service->count($map);
			$rows=$this->service->select($map,'*',$order,$limit);
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}		
	}

	/*
	* 添加会员组
	*/
    public function add(){
		if(request()->isAjax()){
			$row=input('post.row/a');

			$res=$this->service->add($row);

            //添加日志记录
            $id=db('goods')->getLastInsID();
            $this->write_log('添加会员组',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			
			return $this->fetch();
		}
    }

    /*
    * 编辑会员组
    */  
   	public function edit(){

		if(request()->isAjax()){
			$row=input('post.row/a');

			$map['group_id']=input('post.group_id');
			$res=$this->service->save($map,$row);

            //添加日志记录
            $this->write_log('编辑会员组',$map['group_id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['group_id']=input('get.ids');
			$row=$this->service->find($map);
			$this->assign('row',$row);
			return $this->fetch();
		}   		
   	} 

    /*
    * 删除会员组
    */
    public function delete(){
		$ids=input('get.ids');
		$map['group_id']=['in',$ids];

		$res=$this->service->delete($map);

        //添加日志记录
        $this->write_log('删除会员组',$ids);

		return AjaxReturn($res);    	
    }   	   

}
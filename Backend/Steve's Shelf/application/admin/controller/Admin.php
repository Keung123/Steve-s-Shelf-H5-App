<?php
namespace app\admin\controller;

use app\common\service\Admin as AdminService;
use app\common\service\Group as GroupService;
use app\common\service\Kefu as KefuService;
use app\common\service\Supplier;

class Admin extends Base{

	public function _initialize(){
		parent::_initialize();
		//服务
		$AdminService=new AdminService();
		$this->service=$AdminService;
	}	
	/*
	* 会员列表
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
			if($rows){
			    $object=db("auth_group");
                foreach($rows as $key=>$val){
                    $groupname=$object->where("id",$val['group_id'])->value("title");
                    $val['status']=$val['status']=='normal'?'启用':'禁用';
                    $rows[$key]['group_name']=$groupname;
                }
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}		
	}

	/*
	* 添加会员
	*/
    public function add(){
		if(request()->isAjax()){
			$row=input('post.row/a');
			if(empty($row)){
                return ['code'=>0,'msg'=>'请填写相关信息'];
            }

            $res = $this->service->find(['admin_name'=>$row['admin_name']]);
            if($res) return (['code'=>0,'msg'=>' 管理员名称不能重复','data'=>'']);
            if(isset($row['kf_id'])){
                $res = $this->service->find(['kf_id'=>$row['kf_id']]);
                if($res) return (['code'=>0,'msg'=>' 此客服已经绑定过了','data'=>'']);
            }
			$res=$this->service->add($row);
            if(empty($res)) return ['code'=>0,'msg'=>'网络错误，请重试！'];

            $userId = db('admin')->getLastInsID();
            if(isset($row['u_id'])){
                $this->service->addkefu($row['u_id']);
            }

            //日志记录
            $this->write_log("添加后台会员",$userId);

            $insertData=[
                'uid'=>$userId,
                'group_id'=>$row['group_id'],
            ];
			 
            $res = db("auth_group_access")->insert($insertData);
			return AjaxReturn($res,getErrorInfo($res));
		}else{
			//会员组
			//$GroupService=new GroupService();
			//$group=$GroupService->select();
            /*2018831 edit*/
			
			// 供应商
            // $supplierModel = new Supplier();
            // $suppplier=$supplierModel->select();
            $suppplier= db('supplier')->select();
            $supplier_id = db('admin')->where('supplier_id','>',0)->column('supplier_id');
            if(!empty($supplier_id)){
            	$where1['id']=['not in',$supplier_id];
            	$suppplierdata = db('supplier')->where($where1)->select();
            }else{
            	$suppplierdata = db('supplier')->select();
            }
			$this->assign('supplier', $suppplier);
			
			// 客服
			$KefuService=new KefuService();
            $kefu=$KefuService->select();
			$this->assign('kefu', $kefu);

			// 用户
            $user=$this->service->getUser();
			$this->assign('user', $user);
			
            $group=db("auth_group")->field("id as group_id,title as group_name")->select();
            /*2018831 end*/
			$this->assign('group',$group);
			return $this->fetch();
		}
    }

    /*
    * 编辑会员
    */  
   	public function edit(){

		if(request()->isAjax()){
			$row=input('post.row/a');

			$map['admin_id']=input('post.admin_id');
			 if($row['password'] == ''){
				 unset($row['password']);
			 }
			$res=$this->service->save($map,$row);
            if($res){
                $this->service->editkefu($row['u_id'],$map);
            }
			/*2018831 edit*/
            $adminRule=db("auth_group_access")->where("uid",$map['admin_id'])->value("group_id");
            if($adminRule!=$row['group_id']){
               /*  @db("auth_group_access")->where("uid",$map['admin_id'])->update(['group_id'=>$row['group_id']]);   */
				$res = db("auth_group_access")->where("uid",$map['admin_id'])->update(['group_id'=>$row['group_id']]);
				
            }

            //添加日志记录
            $this->write_log("编辑后台会员",$map['admin_id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			//会员组
			//$GroupService=new GroupService();
			//$group=$GroupService->select();
            /*2018831 edit*/
            $group=db("auth_group")->field("id as group_id,title as group_name")->select();
            /*2018831 end*/
			$this->assign('group',$group);	
			// 供应商
            $supplierModel = new Supplier();
            $suppplier=$supplierModel->select();
			$this->assign('supplier', $suppplier);

			// 客服
			$KefuService=new KefuService();
            $kefu=$KefuService->select();
			$this->assign('kefu', $kefu);

            // 用户
            $user=$this->service->getUser();
            $this->assign('user', $user);

			$map['admin_id']=input('get.ids');
			$row = $this->service->find($map);
			
			$this->assign('row',$row);
			return $this->fetch();
		}   		
   	}     
	/*
    * 删除管理员
    */  
	public function delete(){
		$ids=input('get.ids');
        $map['admin_id']=['in',$ids];
        $res=$this->service->delete($map);

        //日志记录
        $this->write_log('删除后台会员',$ids);
        return AjaxReturn($res);
	}
	/*
	* 个人信息
	*/
	public function info(){
		if(request()->isAjax()){
			$row=input('post.row/a');
			$map['admin_id']=session('admin_id');
			$len = strlen($row['password']);
			if($len == 0){
				unset($row['password']);
				$res=$this->service->save($map,$row);
				return AjaxReturn($res,getErrorInfo($res));
			}
			if($len >= 6 && $len <=16){
				$res=$this->service->save($map,$row);
				return AjaxReturn($res,getErrorInfo($res));
			}else{
				return AjaxReturn($res,getErrorInfo(LOGINERROR));
			}

		}else{
			$map['admin_id']=session('admin_id');
			$row=$this->service->find($map);
			$this->assign('row',$row);
			return $this->fetch();
		}		
	}
}
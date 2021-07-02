<?php
namespace app\admin\controller;
use think\Controller;
use think\Request;
use think\Db;

class Auth extends Base
{
   //管理员列表
    function adminList(){
        //获得管理信息
        $lists=db("admin")->alias("a")
            ->join("auth_group_access ga","ga.uid=a.admin_id")
            ->join("auth_group ag","ag.id=ga.group_id")
            //->join("shop s","s.id=ga.shop_id")
            ->field("a.admin_id,a.admin_name as login_time,a.nickname as username,ag.title,a.create_time")
            ->paginate(20);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    //添加管理员
    function addAdmin(){
        if(Request()->isPost()){
            $data=Request()->post();
            if($data['group_id']=='') return json(['status'=>0,'info'=>'请选择职位']);
            if($data['uid']==''||$data['uid']==0) return json(['status'=>0,'info'=>'请选择要分配管理员']);

            if($data['admin_id']){
                //权限
                $saveD=[
                    'group_id'=>$data['group_id']
                ];
                $rs=db("auth_group_access")->where("uid",$data['admin_id'])->update($saveD);
                if($rs) return json(['status'=>1,'info'=>'操作成功！']);
                else return json(['status'=>0,'info'=>'您没有修改任何信息！']);
            }else{
                //进行添加操作
                $isS=db("auth_group_access")->where("uid",$data['uid'])->find();
                if($isS){
                    //进行编辑操
                    if($isS['group_id']==$data['group_id']){
                        return json(['status'=>0,'msg'=>'该管理员已经添加过该权限！']);
                    }else{
                        //添加分组
                        $rs=db("auth_group_access")->where("uid",$data['uid'])->update(['group_id'=>$data['group_id']]);
                    }
                }else{
                    //添加分组
                    $rs=db("auth_group_access")->insert(['uid'=>$data['uid'],'group_id'=>$data['group_id']]);
                }
                $this->write_log('添加用户组明细',$data['uid']);
                if($rs){
                    return json(['status'=>1,'info'=>'操作成功！']);
                }else{
                    return json(['status'=>0,'msg'=>'网络有点忙，请稍后进行操作！']);
                }
            }
        }

        //查询分组
        $rule=db("auth_group")->field("id,title")->select();
        $this->assign('rule_list',$rule);
        //获得用户列表信息
        $userList=db("admin")->where(['admin_id'=>['neq',1]])->field("admin_id,admin_name")->select();
        $this->assign('userList',$userList);
        //获得用户id值
        $admin_id=input("id");
        $adminInfo=db("admin")->alias("a")
            ->join("auth_group_access ag","ag.uid=a.admin_id")
            ->where("admin_id",$admin_id)->find();
        $this->assign('admin_info',$adminInfo);
	  //日志记录
		// $result = $GoodsService->order('goods_id desc')->find();
		 $result = Db::name('admin')->order('admin_id desc')->find();
		$add['uid'] = session('admin_id');
		$add['ip_address'] = request()->ip();
		$add['controller'] = request()->controller();   
		$add['action'] = request()->action();
		$add['remarks'] = '添加角色';
		$add['number'] = $result['admin_id'];
		$add['create_at'] = time(); 
		db('web_log')->insert($add); 
        return $this->fetch();
    }
    //角色列表
    function rulesList(){
        $rule=db("auth_group")->select();
        $this->assign('ruleList',$rule);
        return $this->fetch();
    }
    //添加权限
    function addrules(){
        if(Request()->isPost()){
            $data=Request()->post();
           if($data['id']){
               //进行修改
              $res=db("auth_group")->where("id",$data['id'])->update($data);
              //添加日志记录
              $this->write_log('修改用户组权限',$data['id']);
           }else{
               //进行添加操作
               $res=db("auth_group")->insert($data);
				   //日志记录
				$last_id=db('auth_group')->getLastInsID();
				$this->write_log('添加用户组权限',$last_id);
           }
            if($res) return json(['status'=>1,'info'=>'操作成功！']);
            else return json(['status'=>0,'info'=>'操作失败！']);
			
        }
        $id=input("id");
        $info=db("auth_group")->where("id",$id)->find();
        $info['rules']=explode(",",rtrim($info['rules'],","));
        $this->assign('rules_info',$info);
        //获得权限
        $rules=db('auth_rule')->field('id,pid,title')->select();
        $list = [];
        $topName = [];
        foreach ($rules as $r) {
            $list[$r['id']] = $r;
        }
        foreach ($list as &$item) {
            if ($item['pid'] == 0) {
                $topName[] = &$item;
            } else {
                $list[$item['pid']]['rules_name'][] = $item;
            }
        }
        unset($item);
        $this->assign('rules_list',$topName);
        return $this->fetch();
    }
    //删除操作
    function delAdmin(){
        if(Request()->isAjax()){
            $id=input("id/d");
            $rs=db("auth_group_access")->where("uid",$id)->delete();
			 //日志记录

			$add['uid'] = session('admin_id');
			$add['ip_address'] = request()->ip();
			$add['controller'] = request()->controller();   
			$add['action'] = request()->action();
			$add['remarks'] = '删除用户组明细';
			$add['number'] =  $id;
			$add['create_at'] = time(); 
			db('web_log')->insert($add); 
            if($rs) return json(['status'=>1,'msg'=>'操作成功！']);
            else return json(['status'=>0,'msg' => '操作失败！']);
		
        }
    }
    //删除角色
    function delRule(){
        if(Request()->isAjax()){
            $id=input("id/d");
            //查询该角色是否存在用户有
            $isS=db("auth_group_access")->where("group_id",$id)->find();
            if($isS){
                return json(['status'=>0,'msg'=>'该角色已经分配给管理员，不能进行删除！']);
            }else{
                $rs=db("auth_group")->where("id",$id)->delete();
				  //日志记录
 
				$add['uid'] = session('admin_id');
				$add['ip_address'] = request()->ip();
				$add['controller'] = request()->controller();   
				$add['action'] = request()->action();
				$add['remarks'] = '删除用户组权限';
				$add['number'] =  $id;
				$add['create_at'] = time(); 
				db('web_log')->insert($add); 
                if($rs) return json(['status'=>1,'msg'=>'操作成功！']);
                else return json(['status'=>0,'msg'=>'操作失败！']);
            }
        }
    }

    function addcontroller(){
        if(Request()->isAjax()){
            $data=Request()->post();
            //查询是否添加过
            if($data['id']){
                $isS=db("auth_rule")->where(['name'=>$data['name'],'id'=>['neq',$data['id']]])->value("id");
                if($isS==$data['id']){
                    return json(['status'=>0,'msg'=>'已经添加过了！']);
                }
                //进行更新
                $rs=db("auth_rule")->where("id",$data['id'])->update($data);
                //日志记录
                $this->write_log('修改权限',$data['id']);
            }else{
                $isS=db("auth_rule")->where("name",$data['name'])->value("id");
                if($isS){
                    return json(['status'=>0,'msg'=>'已经添加过了！']);
                }
                $rs=db("auth_rule")->insert($data);
                //日志记录
                $id=db('auth_rule')->getLastInsID();
                $this->write_log('添加权限',$id);
            }
            if($rs) return json(['status'=>1,'msg'=>'操作成功!']);
            else return json(['status'=>0,'msg'=>'修改失败']);
        }
        $id=input("id/d");
        $getOne=db("auth_rule")->where("id",$id)->find();
        $this->assign('getOne',$getOne);
        $name=db("auth_rule")->field('id,title as name')->where('pid', 0)->select();
        $this->assign('nameList',$name);
        return $this->fetch();
    }
    //资源权限列表信息
    function authlist(){
        $where=[];
        $authlist=db("auth_rule")->where($where)->paginate(50);
        $this->assign('authlist',$authlist);
        return $this->fetch();
    }
    //删除操作
    function delAuth(){
        if(Request()->isAjax()){
            $id=input("id/d");
            $rs=db("auth_rule")->where("id",$id)->delete();

            //添加日志记录
            $this->write_log('删除权限',$id);

            if($rs) return json(['status'=>1,'msg'=>'操作成功！']);
            else return  json(['status'=>0,'msg'=>'操作失败！']);
        }
    }
}

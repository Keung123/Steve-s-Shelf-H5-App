<?php
namespace app\common\service;

use app\common\model\Admin as AdminModel;
use think\Db;

class Admin extends Base{

	public function __construct(){
		parent::__construct();
		$AdminModel=new AdminModel();
		$this->model=$AdminModel;
	}

	/*
	* 登录检测
	*/
	public function loginCheck(){
		return session('?admin_id');
	}

	/*
	* 登录
	*/
	public function login($admin_name,$password,$map=[]){
		$AdminModel=new AdminModel();
		$map['admin_name']=$admin_name;
		$info=$AdminModel->where($map)->find();
		if(!$info){
			return USER_EMPTY;
		}
		if($info['password']!=md5('zm_'.md5($password))){
			return PASSWORD_ERROR;
		}
		if(($info['status'] != 1) && ($info['status'] != 'normal')){
			return '您的账号已经被禁用！';
		}

		session('admin_id',$info['admin_id']);
		session('admin_name',$info['admin_name']);
		session('group_id',$info['group_id']);
        session('mobile',$info['mobile']);
        session('nickname',$info['nickname']);
        session('supplier_id',$info['supplier_id']);
		return SUCCESS;
	}

	/*
	* 退出登录
	*/
	public function loginOut(){
		session('admin_id',null);
	}

	/*
	* 修改资料
	*/
	public function save($map,$data){
		if($data['password']){
			$data['password']=md5('zm_'.md5($data['password']));
		}
		return parent::save($map,$data);
	}

	/*
	* 添加用户
	*/
	public function add($data){
		if($data['password']){
			$data['password']=md5('zm_'.md5($data['password']));
		}
		return parent::add($data);
	}

	/*
	* 通过id获取用户信息
	*/
	public function getInfoById($id){
		return $this->model->get($id);
	}
	/*
	* 获取用户
	*/
	public function getUser(){
		 $list =  Db::name('users')->where(array('is_kefu'=>0))->field('user_id,user_name')->select();
		 return $list;
	}
	/*
	* 获取用户
	*/
	public function getUsers(){
		 $list =  Db::name('users')->field('user_id,user_name')->select();
		 return $list;
	}
	/*
	* 修改用户为客服
	*/
	public function addkefu($uid){
            Db::name('users')->where('user_id',$uid)-> update(array('is_kefu'=>1));
	}
	/*
	* 修改用户为客服
	*/
	public function editkefu($uid,$where){
	    $userid =  Db::name('admin')->where($where)-> value('u_id');
	    if($userid != $uid){
            $res = Db::name('users')->where('user_id',$userid)-> find();
            if($res){
                Db::name('users')->where('user_id',$userid)-> update(array('is_kefu'=>0));
            }
            Db::name('users')->where('user_id',$uid)-> update(array('is_kefu'=>1));
        }
	}
}
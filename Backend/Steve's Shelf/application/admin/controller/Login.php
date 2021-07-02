<?php
namespace app\admin\controller;

use think\Controller;
use app\common\service\Admin;

class Login extends Common{

    public function index(){
    	if(request()->isGet()){
    		
    		return $this->fetch();
    	}

    	if(request()->isPost()){
    		$admin_name=input('post.admin_name');
    		if(!$admin_name){
    			return AjaxReturn(USERNAME_NOT);
    		}
    		$password=input('post.password');
    		if(!$password){
    			return AjaxReturn(PASSWORD_NOT);
    		}
//            $captcha = input('post.verfyCode');
//            if(!captcha_check($captcha)) {
//                return AjaxReturn(CAPTCHA_ERROR);
//            }
    		$Admin=new Admin();
    		$res=$Admin->login($admin_name,$password);
            $uid = session('admin_id');
            $add['uid'] = $uid ? $uid: 0;
            $add['ip_address'] = request()->ip();
            $add['create_at'] = time();
            db('login_log')->insert($add);

    		if($res>0){
                if(request()->isAjax()){
                    return AjaxReturn($res,['url'=>url('admin/index/index')]);
                }else{
                    return $this->redirect('admin/index/index');
                }
    		}else{
    			return AjaxReturn($res);
    		}
    	}
    }

    public function out(){
        $Admin=new Admin();
        $Admin->loginOut();  
        return $this->redirect('login/index');
    }
    //错误页面
    public function error(){
        return $this->fetch();
    }
	//默认首页
	 public function homepage(){
        return $this->fetch();
    }
}

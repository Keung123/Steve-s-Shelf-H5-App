<?php
namespace app\admin\controller;

use app\common\service\Admin;
use app\common\library\Menu;

class Base extends Common{

	public function _initialize(){
		//登录检测
		$Admin=new Admin();
		if(!$Admin->loginCheck()){
			return $this->redirect('login/index');
		}
		parent::_initialize();
 //        //权限操作
        $adminId=session("admin_id");
        if ($adminId != 1) {
            $adminRule=db("auth_group_access")->alias("ga")
                ->join("auth_group ag","ag.id=ga.group_id")
                ->field("rules")
                ->where("uid",$adminId)
                ->find();
            $adminRule=explode(",",rtrim($adminRule['rules'],","));
            $menu = Menu::getAuthList($adminRule);
        } else {
            $menu = Menu::getList();
        }
        $this->assign('menus', $menu);
        //获得请求的控制器和方法
        $ctl = Request()->controller();
        $model = Request()->module();
        $act = Request()->action();
        $mca=$model.'/'.strtolower($ctl).'/'.$act;
        $isFind=db("auth_rule")->where("name",$mca)->value("id");
        if(request()->isAjax() || strpos($act,'ajax')!== false||$adminId==1){
			if($adminId==1){
				
			}else if(request()->isAjax()&&($isFind&&!in_array($isFind,$adminRule))||$isFind==null){
				 //页面跳转 
				 // return $this->redirect('admin/Login/error');
				 //弹窗
				exit(json_encode(['code'=>0,'msg'=>'暂无权限！','data'=>'zanwuquanxian']));
			 }

        }else if(($isFind&&!in_array($isFind,$adminRule))|| ($isFind==null && strpos('admin/index', $mca) !== false)){
			 
            //return $this->error("您不有该权限，不能进行该操作！");
           return $this->redirect('admin/Login/error');
        }
	}
    // 日志记录
	public function write_log($remark, $id)
    {
        $add['uid'] = session('admin_id');
        $add['ip_address'] = request()->ip();
        $add['controller'] = request()->controller();
        $add['action'] = request()->action();
        $add['remarks'] = $remark;
        $add['number'] = $id;
        $add['create_at'] = time();
        db('web_log')->insert($add);

    }
}
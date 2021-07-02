<?php
namespace app\admin\controller;

use think\Db;

class Weblog extends Base{

	public function operate(){
		$admin_name = trim(input('admin_name'));
        $start_time = trim(input('start_time'));
        $end_time = trim(input('end_time'));
        $this->assign('admin_name',$admin_name);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
		if(request()->isAjax()){
			$sort = 'wl.id desc';
			$limit = input('get.offset').",".input('get.limit');
			 $map =[];
			if(input('get.search')){
                $map['a.admin_name']=['eq','%'.input('get.search').'%'];
            }
			if(input('admin_name')){
                $map['a.admin_name']=['like','%'.input('admin_name').'%'];
            }
			if(input('start_time')){
				 $start_time = str_replace('+',' ',input('start_time'));
			}
		    if(input('end_time')){
				 $end_time = str_replace('+',' ',input('end_time'));
			}
            if ($start_time && $end_time) {
                $map['wl.create_at'] = array('between',strtotime($start_time).','.(strtotime($end_time)));
            } elseif ($start_time) {
                $map['wl.create_at'] = ['egt',strtotime($start_time)];
            } elseif ($end_time) {
                $map['wl.create_at'] = ['elt',strtotime($end_time)];
            }
			$total = db('web_log')->alias('wl')->join('admin a','a.admin_id=wl.uid','left')->field('wl.*,a.admin_name,a.nickname')->where($map)->count();
			
	        $rows = db('web_log')->alias('wl')->join('admin a','a.admin_id=wl.uid','left')->field('wl.*,a.admin_name,a.nickname')->order($sort)->limit($limit)->where($map)->select();
            foreach ($rows as $key=>$value){
            	 foreach ($value as $k=>$v){
					if($k == 'create_at'){
						$rows[$key][$k] = date('Y-m-d H:i:s', $v);
					}
            	 }
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{			 
			return $this->fetch();
		} 
	}

	public function login(){
		if(request()->isAjax()){
			$sort = 'll.id desc';
			$limit = input('get.offset').",".input('get.limit');
			$total = db('login_log')->count();

	        $rows = db('login_log')->alias('ll')->join('admin a','a.admin_id=ll.uid','left')->field('ll.*,a.admin_name,a.nickname')->order($sort)->limit($limit)->select();
            foreach ($rows as $key=>$value){
            	 foreach ($value as $k=>$v){
					if($k == 'create_at'){
						$rows[$key][$k] = date('Y-m-d H:i:s', $v);
					}
            	 }
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{			 
			return $this->fetch();
		} 
	}

}
<?php
namespace app\admin\controller;

use app\common\service\User as UserService;
use think\Db;
class idauth extends Base{

	public function _initialize(){
		parent::_initialize();
	}

	/*
	 * 申请列表
	 */
	public function index(){
		 $auth_uname = trim(input('auth_uname'));
		 $auth_truename =  trim(input('auth_truename'));
		 $auth_phone= trim(input('auth_phone'));
		 $auth_stat= trim(input('auth_stat'));

		 $this->assign('auth_uname',$auth_uname);
		 $this->assign('auth_truename',$auth_truename);
		 $this->assign('auth_phone',$auth_phone);
		 $this->assign('auth_stat',$auth_stat);
		if(request()->isAjax()){
			$order = 'auth_addtime desc';	
			$limit = input('get.offset').",".input('get.limit');

			if(input('auth_uname')){
				$map['auth_uname'] = ['like','%'.input('auth_uname').'%'];
			}
			if(input('auth_truename')){
				$map['auth_truename'] = ['like','%'.input('auth_truename').'%'];
			}
			if(input('auth_phone')){
			    $map['auth_phone']=['like','%'.$auth_phone.'%'];
            }

            $status=input('auth_stat');
			if($status){
			    $map['auth_stat']= $status;
            } 
			$total = Db::name('idauth')->count();
			$rows = Db::name('idauth')->where($map)->field('*')->order($order)->limit($limit)->select();
			foreach($rows as &$v){
				if($v['auth_addtime']){
					$v['auth_addtime'] = date('Y-m-d', $v['auth_addtime']);
				}
				if($v['auth_checktime']){
					$v['auth_checktime'] = date('Y-m-d H:i:s', $v['auth_checktime']);
				}
				//0,未提交；1，未审核；2，通过；3，未通过
				$sta_arr = array('未审核','未审核','通过','未通过');
			$v['auth_stat'] = $sta_arr[$v['auth_stat']];
			}
			return json(['total' => $total, 'rows' => $rows]);
		}
		else{
			return $this->fetch();
		}
	}

    /*
     * 查看详情
     * */
    public function idauthShow(){
        $map['auth_id']=input('get.auth_id');
        $row = Db::name('idauth')->where($map)->field('*')->find();
		$auth_stat = array('未提交','未审核','通过','未通过');
        if($row){
            $row['auth_addtime'] = date('Y-m-d H:i:s',$row['auth_addtime']);
            $row['auth_checktime'] = date('Y-m-d H:i:s',$row['auth_checktime']);
            $row['auth_stat'] = $auth_stat[$row['auth_stat']];
        }
        $this->assign('row', $row);
        return $this->fetch();
    }

	/*
	 * 审核
	 */
	public function edit(){
		$get_data = input('get.');
		if(isset($get_data['ids'])){
			$row = Db::name('idauth')->where('auth_id', $get_data['ids'])->field('*')->find();
			if($row){
				$row['auth_addtime'] = date('Y-m-d H:i:s',$row['auth_addtime']);
			}
			$this->assign('row', $row);
			return $this->fetch();
		}

		$post_data = input('post.');
		if($post_data){
			$stat = $post_data['stat'];
			$auth_id = $post_data['auth_id'];
			$auth_remark = $post_data['auth_remark'];
			if(!$auth_id){
				return ['code' => 0, 'msg' => '网络错误'];
			}
			$res = Db::name('idauth')->where('auth_id', $auth_id)->update(['auth_stat' => $stat, 'auth_checktime' => time(),'auth_remark'=>$auth_remark]);

			//添加日志记录
            $this->write_log("实名制认证审核",$auth_id);

			if($stat == 2){
				$user_info = Db::name('idauth')->where('auth_id', $auth_id)->field('auth_uid')->find();
				if(!$user_info){
					return ['code' => 0, 'msg' => '用户不存在'];
				}
				Db::name('users')->where('user_id', $user_info['auth_uid'])->setInc('user_points', 20);
				Db::name('users')->where('user_id', $user_info['auth_uid'])->update(['id_auth'=>1]);
				$point_log_insert = [
					'p_uid' => $user_info['auth_uid'],
					'point_num' => 20,
					'point_type' => 3,
					'point_desc' => '实名认证通过奖励',
					'point_add_time' => time(),
				];
				Db::name('points_log')->insert($point_log_insert);
			}else{
				Db::name('users')->where('user_id', $user_info['auth_uid'])->update(['id_auth'=>0]);
			}
			if($res !== false){
				return ['code' => 1, 'msg' => '保存成功'];
			}
		}
	}

	/*
	 * 删除
	 */
	public function listDel(){
		$auth_id = input('get.ids');
		$data = Db::name('idauth')->where(['auth_id' => ['in', $auth_id]])->find();
		Db::name('users')->where('user_id',$data['auth_uid'])->update(['id_auth' => 0]);
		$res = Db::name('idauth')->where(['auth_id' => ['in', $auth_id]])->delete();
		
		// print_r($data);die;
		//添加日志记录
        $this->write_log('实名制认证删除',$auth_id);
		if(!$res){
			return ['code' => 0, 'data' => '删除失败'];
		}
		return ['code' => 1, 'data' => '删除成功'];
	}
}
<?php
namespace app\admin\controller;

use app\common\model\Recharge as RcModel;
use app\common\service\User as UserService;
use think\Db;
class Recharge extends Base{

	public function _initialize(){
		parent::_initialize();
		$rc_model = new RcModel();
		$this->model = $rc_model;
	}

	/*
	 * 充值卡列表
	 */
	public function index(){
		$rc_title = trim(input('rc_title'));
		$this->assign('rc_title',$rc_title);
		if(request()->isAjax()){
			$order = 'rc_id desc';	
			$limit = input('get.offset').",".input('get.limit');
			
			if(input('rc_title')){
				$map['rc_title'] = ['like','%'.input('rc_title').'%'];
			}
            $map['rc_status']=0;
			$total = Db::name('rc_template')->where('rc_status',0)->count();
			$rows = Db::name('rc_template')->where($map)->field('*')->order($order)->select();
			foreach($rows as &$v){
				if($v['rc_s_time']){
					$v['rc_s_time'] = date('Y-m-d', $v['rc_s_time']);
				}
				if($v['rc_add_time']){
					$v['rc_add_time'] = date('Y-m-d H:i:s', $v['rc_add_time']);
				}
			}
			return json(['total' => $total, 'rows' => $rows]);
		}
		else{
			return $this->fetch();
		}
	}

	/*
	 * 增加充值卡
	 */
	public function rcAdd(){
		if(request()->isAjax()){
			$data = input('post.row/a');
			foreach($data as $v){
				if($v < 0){
					return ['code' => 0, 'msg' => '数据格式错误'];
				}
			}
			$data['rc_add_time'] = time();
			$data['rc_s_time'] = strtotime($data['rc_s_time']);
			if($data['rc_s_time'] < time()){
				return ['code' => 0, 'msg' => '生效时间不能小于当前时间'];
			}
			
			$res = Db::name('rc_template')->insert($data);

            //添加日志记录
            $id=db('rc_template')->getLastInsID();
            $this->write_log('添加充值卡',$id);

			if($res){
				return ['code' => 1, 'data' => '保存成功'];
			}
		}
		else return $this->fetch();
	}

	/*
	 * 编辑充值卡
	 */
	public function rcEdit(){
		if(request()->isAjax()){
			$data = input('post.row/a');
			foreach($data as $v){
				if($v < 0){
					return ['code' => 0, 'msg' => '数据格式错误'];
				}
			}
			$data['rc_add_time'] = time();
			$data['rc_s_time'] = strtotime($data['rc_s_time']);
			if($data['rc_s_time'] < time()){
				return ['code' => 0, 'msg' => '生效时间不能小于当前时间'];
			}
			$res = Db::name('rc_template')->where('rc_id', $data['rc_id'])->update($data);

            //添加日志记录
            $this->write_log('编辑充值卡',$data['rc_id']);

			if($res){
				return ['code' => 1, 'data' => '保存成功'];
			}
		}
		else{
			$row = Db::name('rc_template')->where('rc_id', input('get.ids'))->find();
			if($row){
					$row['rc_s_time'] = date('Y-m-d',$row['rc_s_time']);
			}
		
			$this->assign('row', $row);
			return $this->fetch();
		}
	}

	/*
	 * 充值卡删除
	 */
	public function rcDel(){
		$rc_id = input('get.ids');
		$res = Db::name('rc_template')->where(['rc_id' => ['in', $rc_id]])->update(['rc_status'=>1]);

        //添加日志记录
        $this->write_log('充值卡删除',$rc_id);

		if(!$res){
			return ['code' => 0, 'data' => '删除失败'];
		}
		return ['code' => 1, 'data' => '删除成功'];
	}

	/*
	 * 在线充值
	 */
	public function rcOnline(){
		if(request()->isAjax()){
			$limit = input('get.offset').",".input('get.limit');
			if(input('get.search')){
				$map['ro_price']=['eq',input('get.search')];
			}			
			$total = Db::name('rc_online')->count();
			$rows = Db::name('rc_online')->order('ro_id desc')->limit($limit)->where($map)->select();
			if($rows){
				foreach($rows as &$v){
					if($v['ro_add_time']){
						$v['ro_add_time'] = date('Y-m-d H:i:s', $v['ro_add_time']);
					}
				}
			}
			return ['total' => $total, 'rows' => $rows];
		}
		else return $this->fetch();
	}

	/*
	 * 增加充值
	 */
	public function roAdd(){
		if(request()->isAjax()){
			$data = input('post.row/a');
			foreach($data as $v){
				if($v < 0){
					return ['code' => 0, 'msg' => '数据格式错误'];
				}
			}
			$data['ro_add_time'] = time();
			$res = Db::name('rc_online')->insert($data);

            //添加日志记录
            $id=db('rc_online')->getLastInsID();
            $this->write_log('增加充值',$id);

			if($res){
				return ['code' => 1, 'data' => '保存成功'];
			}
		}
		else return $this->fetch();
	}

	/*
	 * 编辑充值
	 */
	public function roEdit(){
		if(request()->isAjax()){
			$data = input('post.row/a');
			foreach($data as $v){
				if($v < 0){
					return ['code' => 0, 'msg' => '数据格式错误'];
				}
			}
			$data['ro_add_time'] = time();
			$res = Db::name('rc_online')->where('ro_id', $data['ro_id'])->update($data);

            //添加日志记录
            $this->write_log('编辑充值',$data['ro_id']);

			if($res){
				return ['code' => 1, 'data' => '保存成功'];
			}
		}
		else{
			$row = Db::name('rc_online')->where('ro_id', input('get.ids'))->find();
			$this->assign('row', $row);
			return $this->fetch();
		}
	}

	/*
	 * 充值删除
	 */
	public function roDel(){
		$ro_id = input('get.ids');
		$res = Db::name('rc_online')->where(['ro_id' => ['in', $ro_id]])->delete();

        //添加日志记录
        $this->write_log('充值删除',$ro_id);

		if(!$res){
			return ['code' => 0, 'data' => '删除失败'];
		}
		return ['code' => 1, 'data' => '删除成功'];
	}

	/*
	 * 充值记录列表
	 */
	public function rcList(){
		$rech_no = trim(input('rech_no'));
		$rech_uname = trim(input('rech_uname'));
        $start_time = trim(input('start_time'));
        $end_time = trim(input('end_time'));

        $this->assign('rech_no',$rech_no);
        $this->assign('rech_uname',$rech_uname);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
		if(request()->isAjax()){
			$order = 'rech_id desc';
			$limit = input('get.offset').",".input('get.limit');
			if(input('rech_no')){
				$map['rech_no'] = ['like','%'.input('rech_no').'%'];
			}
			if(input('rech_uname')){
				$map['rech_uname'] = ['like','%'.input('rech_uname').'%'];
			}
            if(input('start_time')){
                $start_time = str_replace('+',' ',input('start_time'));
            }
            if(input('end_time')){
                $end_time = str_replace('+',' ',input('end_time'));
            }
            if ($start_time && $end_time) {
                $map['rech_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
            } elseif ($start_time) {
                $map['rech_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['rech_create_time'] = array('<=', strtotime($end_time));
            }
            $map['rech_stat']=2;
            $total = $this->model->where('rech_stat',2)->count();
			$rows = $this->model->where($map)->field('rech_id,rech_no,rech_uid,rech_uname,rech_amount,rech_way,rech_pay_time,rech_type')->order($order)->limit($limit)->select();
			$amount=0;
			foreach($rows as $key=>$val){
			    $amount+=$val['rech_amount'];
			    $rows[$key]['amount']=round($amount,2);
			    //充值总金额
                $total_price = Db::name('recharge')->sum('rech_amount');
                $rows[$key]['total_price'] = round($total_price,2);
            }
			return json(['total' => $total, 'rows' => $rows]);
		}
		else{
			return $this->fetch();
		}
	}

	/*
	 * 充值列表删除
	 */
	public function listDel(){
		$rc_id = input('get.ids');
		$res = $this->model->where(['rech_id' => ['in', $rc_id]])->delete();

        //添加日志记录
        $this->write_log('充值列表删除',$rc_id);

		if(!$res){
			return ['code' => 0, 'data' => '删除失败'];
		}
		return ['code' => 1, 'data' => '删除成功'];
	}
}
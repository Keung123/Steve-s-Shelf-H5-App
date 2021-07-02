<?php
namespace app\common\service;

use app\common\model\Yinzi as YinziModel;
use app\common\model\Users as UserModel;
use app\common\model\Coupon as Coupon;
use app\common\service\PointLog as PointLogService;
use app\common\service\Sms as SmsService;
use app\common\model\Goods as GoodsM;
use think\Db;
use think\Request;

class Yinzi extends Base{
	public function __construct(){
		parent::__construct();
		$YinziModel = new YinziModel();
		$this->model = $YinziModel;
	}
	/*
	 *  我的可用元宝 
 	 */
	public function showYinzi($uid,$yin_stat=2){
		$result = $this->model->where(['yin_uid'=>['eq',$uid],'yin_stat'=>['eq',$yin_stat]])->order('yin_die_time asc')->select();
		// $rules = [];
		// $rules = [
		// 	'vip_num' => 10,
		// 	'seller_num' => 10,
		// 	'login_num' => 5,  
		// ];
		// $result['rules'] = $rules;
		return $result;
	}
	/*
	 *  失效元宝 
 	 */
	public function sxYinzi($uid,$yin_stat=4){
		$result = $this->model->where(['yin_uid'=>['eq',$uid],'yin_stat'=>['eq', $yin_stat]])->select();
		// $rules = [
		// 	'vip_num' => 10,
		// 	'seller_num' => 10,
		// 	'login_num' => 5,  
		// ];
		// $result['rules'] = $rules;
		return $result;
	}/*
	 *  失效元宝 
 	 */
	public function sxYinzis($uid,$yin_stat=4){
		$result = $this->model->where(['yin_uid'=>['eq',$uid],'yin_stat'=>['in','4,3']])->select();
		// $rules = [
		// 	'vip_num' => 10,
		// 	'seller_num' => 10,
		// 	'login_num' => 5,  
		// ];
		// $result['rules'] = $rules;
		return $result;
	}
	/*
	 *   删除失效的记录 
 	 */
	public function delYinzi($uid,$yin_stat=4){
		$result = $this->model->where(['yin_uid'=>['eq',$uid],'yin_stat'=>['eq',$yin_stat]])->select();
		if($result){
			foreach($result as $val){
				$data = [
					'y_log_yid' => $val['yin_id'],
					'y_log_uid' => $uid,
					'y_log_desc' => '元宝过期被删除！',
					'y_log_addtime' => time(),
				];
				Db::name('yinzi_log')->insert($data);//元宝变更日志
			}
		}
		$result = $this->model->where(['yin_uid'=>['eq',$uid],'yin_stat'=>['eq',$yin_stat]])->delete();
		return $result;
	}
	/*
	 *   到期判断
 	 */
	public function timeYinzi($uid,$yin_stat=2){
		$result = $this->model->where(['yin_uid'=>['eq',$uid],'yin_stat'=>['eq',$yin_stat]])->select();
		$YinziUpdate =[
			'yin_stat'=>4,
		];
		foreach($result as $val){
			if(time()>$val['yin_die_time']){
				$result = $this->model->where(['yin_id'=>['eq',$val['yin_id']]])->update($YinziUpdate);
				$data = [
					'y_log_yid' => $val['yin_id'],
					'y_log_uid' => $uid,
					'y_log_desc' => '元宝过期',
					'y_log_addtime' => time(),
				];
				if($result){
					Db::name('yinzi_log')->insert($data);//元宝变更日志
				}
			}
		}
		return $result;
	}
	/*
	 *   赠送元宝到期判断 24小时内未领取完，则原路退回，但是红包的有效期时间变成30天 
 	 */
	public function rfYinzi($uid,$yin_stat=5){
		$limitime = 24 * 60 * 60;
		$result = $this->model->where(['yin_uid'=>['eq',$uid],'yin_stat'=>['eq',$yin_stat]])->select();
		$YinziUpdate =[
			'yin_stat'=>2,
		];
		foreach($result as $val){
			if((time()- $val['yin_add_time'])>$limitime){
				$result = $this->model->where(['yin_id'=>['eq',$val['yin_id']]])->update($YinziUpdate);
				$data = [
					'y_log_yid' => $val['yin_id'],
					'y_log_uid' => $uid,
					'y_log_desc' => '分享赠送无人领，退还！',
					'y_log_addtime' => time(),
				];
				if($result){
					Db::name('yinzi_log')->insert($data);//元宝变更日志
				}
			} 
		}
		return $result;
	}
	/*
	 *   元宝分享
 	 */
	public function shaerYinzi($uid,$yin_id){
		$YinziUpdate =[
			'yin_stat'=>5,
			'yin_s_gtime'=>time(),
		];
		$result = $this->model->where(['yin_uid'=>['eq',$uid],'yin_id'=>['eq',$yin_id]])->update($YinziUpdate);
		$data = [
			'y_log_yid' => $yin_id,
			'y_log_uid' => $uid,
			'y_log_desc' => '分享赠送',
			'y_log_addtime' => time(),
		];
		if($result){
			Db::name('yinzi_log')->insert($data);//元宝变更日志
		}
		return $result;
	}

	/*
	 * 新增元宝
	 */
	public function addYinzi($uid, $type, $number = 0){
		if($uid){
			$yinzi_no = $this->createYzNo();
			$no_check = $this->model->where('yin_no', $yinzi_no)->field('yin_id')->find();
			while($no_check){
				$yinzi_no = $this->createYzNo();
				$no_check = $this->model->where('yin_no', $yinzi_no)->field('yin_id')->find();
			}
			$insert_yz = [
				'yin_no' => $yinzi_no,
				'yin_uid' => $uid,
				'yin_amount' => $number,
				'yin_type' => $type,
				'yin_stat' => 2,
				'yin_add_time' => time(),
				'yin_valid_time' => 30,
				'yin_die_time' => time() + 30 * 24 * 3600,
			];
			switch($type){
				case 1 : $insert_yz['yin_desc'] = '邀请VIP注册赠送'; break;
				case 2 : $insert_yz['yin_desc'] = '受邀注册VIP赠送'; break;
				case 4 : $insert_yz['yin_desc'] = '邀请开店赠送'; break;
				case 5 : $insert_yz['yin_desc'] = '受邀开店赠送'; break;
			}
			$this->model->insert($insert_yz);

			$insert_log = [
				'y_log_yid' => $this->model->getLastInsId(),
				'y_log_uid' => $uid,
				'y_log_desc' => $insert_yz['yin_desc'],
				'y_log_addtime' => time()
			];
			Db::name('yinzi_log')->insert($insert_log);
		}
	}

	/*
	 * 生成元宝编号
	 */
	public function createYzNo(){
		return 'YB'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
	}

	/*
	 * 获取邀请码
	 */
	public function getMyCode($uid){
		$user_info = Db::name('users')->where('user_id', $uid)->field('s_invite_code as invite_code')->find();
		return ['invite_code' => $user_info['invite_code']];
	}
	/*
	 * 领取元宝
	 */
	public function getWing($share_id,$now_id,$yin_id){
		$row = Db::name('yinzi')->where('yin_id',$yin_id)->find();
		if($row['yin_uid'] == $now_id){
			return -1;
		}
		if($row['yin_stat'] == 2){
			$share = [
				'yin_stat'=>3,
				'yin_s_gtime'=>time(),
			];
			$res = Db::name('yinzi')->where('yin_id',$row['yin_id'])->update($share);
			
			if(!$res){
				return 0;
			}
			$data =[
				'yin_no'=>$this->createYzNo(),//元宝编号
				'yin_uid'=>$now_id,//会员id
				'yin_amount'=>$row['yin_amount'],//会员id
				'yin_type'=>2,//会员id
				// 'yin_desc'=>'受邀请成为vip', 
				'yin_desc'=>'赠送元宝', 
				'yin_add_time'=>time(),	
				'yin_stat'=>2,	
				'yin_valid_time'=>$row['yin_valid_time'],	
				'yin_die_time'=> ($row['yin_valid_time'] * 24 *3600 + time()),	
			];
			$res =  Db::name('yinzi')->insert($data);
			if($res){
				return 1;
			}
		}
		return  0;
	}
	/*
	 * 获取元宝信息
	 */
	public function getWingInfo($yin_id){
		$row = Db::name('yinzi')->where('yin_id',$yin_id)->field('yin_id,yin_type,yin_amount,yin_add_time,yin_die_time')->find();
		return $row;
	}
}
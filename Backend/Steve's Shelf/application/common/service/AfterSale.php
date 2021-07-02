<?php
namespace app\common\service;

use think\Db;
use app\common\model\AsList as AsModel;
use app\common\model\Order as OrderModel;

class AfterSale extends Base{

	public function __construct(){
		parent::__construct();
		$as_model = new AsModel();
		$this->model = $as_model;
	}

	/*
	 * 售后申请、申请记录
	 */
	public function asList($uid, $p, $type){
		$num = 10;
		$s = ($p - 1) * $num;
		// 售后申请
		if($type == 1){
			$order_model = new OrderModel();
			$where = [
				'order_uid' => $uid,
				'order_status' => 4,
				'order_isdel' => 0,
				// 'order_finish_time' => ['egt', time() - 15 * 24 * 3600],      //收货15天之内
			];
			$avai_order = $order_model->where($where)->field('order_id,order_no,order_create_time,order_finish_time')->limit($s, $num)->order('order_create_time desc')->select();
			if($avai_order){
				foreach($avai_order as &$v){
					if($v['order_finish_time'] + 15 * 24 * 3600 >= time()){
						$v['if_apply'] = 1;
					}
					else{
						$v['if_apply'] = 0;
					}
					$v['order_create_time'] = date('Y-m-d H:i:s', $v['order_create_time']);
					$goods_info = Db::name('order_goods')->where(['og_order_id' => $v['order_id'], 'og_uid' => $uid])->field('og_goods_id,og_goods_name,og_goods_spec_val,og_goods_price,og_goods_num,og_goods_thumb')->select();
					$v['goods'] = $goods_info;				
				}
			}
			return $avai_order;
		}
		// 申请记录
		else{
			$as_info = $this->model->where('as_uid', $uid)->field('as_no,as_order_id,as_add_time')->order('as_add_time desc')->limit($s, $num)->select();
			foreach($as_info as &$v){
				$v['as_add_time'] = date('Y-m-d H:i', $v['as_add_time']);
				$goods_info = $this->model->alias('a')->join('__ORDER_GOODS__ b', 'a.as_goods_id=b.og_goods_id', 'LEFT')->where(['a.as_no' => $v['as_no'], 'b.og_order_id' => $v['as_order_id']])->field('a.as_id,a.as_goods_id,a.as_stat,b.og_goods_thumb,b.og_goods_name,b.og_goods_spec_val,b.og_goods_price,b.og_goods_num')->select();
				$v['goods'] = $goods_info;
			}
			return $as_info;
		}
	}

	/*
	 * 申请退换货
	 */
	public function getAsInfo($uid, $type=1){
		$as_info = Db::name('as_type')->where('status',$type)->field('t_type')->order('t_order desc')->select();
		$field = ($type == 1) ? 'addr_area,addr_cont,addr_receiver,addr_phone' : 'addr_receiver,addr_phone';
		$user_addr = Db::name('addr')->where(['a_uid' => $uid, 'is_default' => 1])->field($field)->find();
		return ['as_info' => $as_info, 'addr_info' => $user_addr];
	}
	/*
	 * 换货申请
	 */
	public function asSubmit($data){
		Db::startTrans();
		try{
			//是否需要生成新订单
			$update['order_status'] = ($data['as_type'] == 1) ? 7 : 6;
			Db::name('order')->where('order_id', $data['as_order_id'])->update($update);
			$result = $this->model->where('as_order_id', $data['as_order_id'])->field('as_id,as_no')->find();
			if($result){
				$as_no = $result['as_no'];
			}
			else{
				$as_no = $this->createAsNo();
			}
			$data['as_no'] = $as_no;
			$data['as_thumb'] = $data['as_thumb'] ?  trim($data['as_thumb'], ',') : '';
            $this->model->insert($data);
			$info = $this->model->where('as_no', $as_no)->field('as_id')->find();
			Db::commit();
			return ['as_id' => $info['as_id']];
		}
		catch(\Exception $e){
			Db::rollback();
			return false;
		}
	}

	/*
	 * 记录详情
	 */
	public function asDetail($uid, $as_id){
		$as_info = $this->model->where('as_id', $as_id)->field('as_no,as_add_time,as_stat,as_check_time,as_rece_time,as_refund_time,as_finish_time,as_user_comm,as_admin_comm')->find();
		$as_info['as_add_time'] = date('Y-m-d H:i:s', $as_info['as_add_time']);
		$as_info['as_check_time'] = $as_info['as_check_time'] ? date('Y-m-d H:i:s', $as_info['as_check_time']) : '';
		$as_info['as_rece_time'] = $as_info['as_rece_time'] ? date('Y-m-d H:i:s', $as_info['as_rece_time']) : ''; 
		$as_info['as_refund_time'] = $as_info['as_refund_time'] ? date('Y-m-d H:i:s', $as_info['as_refund_time']) : '';
		$as_info['as_finish_time'] = $as_info['as_finish_time'] ? date('Y-m-d H:i:s', $as_info['as_finish_time']) : '';
		$as_info['addr'] = '河南省郑州市二七区康桥华城国际';
		$as_info['link_man'] = '合淘商城';
		$as_info['post_no'] = '450000';
		return $as_info;
	}

	/*
	 * 审核进度
	 */
	public function asLog($uid, $as_id){
		$as_info = $this->model->where('as_id', $as_id)->field('as_no,as_add_time,as_stat')->find();
		$as_info['as_add_time'] = date('Y-m-d H:i:s', $as_info['as_add_time']);
		$log = Db::name('as_log')->where('as_id', $as_id)->field('as_log_desc,add_time')->order('add_time desc')->select();
		foreach($log as &$v){
			$v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
		}
		$as_info['log'] = $log;
		return $as_info;
	}

	/*
	 * 创建售后服务单号
	 */
	public function createAsNo(){
		$as_no = 'HTSH'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
		$is_exist = $this->model->where('as_no', $as_no)->field('as_no')->find();
		if($is_exist){
			$this->createAsNo();
		}
		return $as_no;
	}
}
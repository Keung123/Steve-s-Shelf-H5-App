<?php
namespace app\common\service;

use app\common\model\Users as UserModel;
use app\common\service\PointLog as PointLogService;
use app\common\service\Goods as GoodsService;
use app\common\service\SmsAli as SmsService;
use app\common\service\Yinzi as Yinzi;
use app\common\service\Store as Store;
use app\common\model\Goods as GoodsM;
use think\Db;
use getui\Pushs;
use think\Request;

class User extends Base{
	public function __construct(){
		parent::__construct();
		$UserModel = new UserModel();
		$this->model = $UserModel;
	}

	public function add($data){
		if($data['password']){
			$data['user_pwd']=md5('hetao_'.md5($data['password']));
		}
		return parent::add($data);
	}

	public function save($map=[],$data){
		//新密码为空时不更新密码
		if(!$data['password']){
			unset($data['password']);
		}else{
			$data['user_pwd']=md5('hetao_'.md5($data['password']));
		}
		return parent::save($map,$data);
	}

	/*
	 * 会员信息
	 */
	public function userInfo($where, $field=''){
		return $this->model->where($where)->field($field)->find();
	}

	/*
	 * 我的可用优惠券
	 */
	public function avaiCoupon($uid, $all_price, $goods_id, $sku_id, $type){
	    $goods_id = rtrim($goods_id, ',');
		 // 购物车过来的 goods_id 是 购物车id，sku_id 为 car 向前兼容
        $goods_ids = [];
        $goods_type_arr = [];
        if ($sku_id == 'car') {
            $goods_id_arr = Db::name('cart')->field('goods_id')->where(['id' => ['in', $goods_id], 'user_id' => $uid])->select();
            if ($goods_id_arr) {
                //$goods_ids = array_column($goods_id_arr, 'id');
                $goods_ids=Db::name('cart')->field('goods_id')->where(['id' => ['in', $goods_id], 'user_id' => $uid])->column('goods_id');
                $goods_list = Db::name('goods')->where(['goods_id' => ['in', implode(',', $goods_ids)]])->field('goods_id,prom_id,prom_type')->select();
                $goods_type_arr=Db::name('goods')->where(['goods_id' => ['in', implode(',', $goods_ids)]])->field('goods_id,prom_id,prom_type')->column('prom_type');
                //$goods_type_arr = array_column($goods_list, 'prom_type');
            }
        } else {
            $goods_ids = [$goods_id];
            $goods_info = Db::name('goods')->where("goods_id=$goods_id")->field('goods_id,prom_id,prom_type')->find();
            $goods_type_arr = [$goods_info['prom_type']];
        }
		$where = [
			'coupon_aval_time'=>['egt',time()],
			'c_uid' => $uid,
			'coupon_stat' => 1
		];
		$field = 'c_id,coupon_id,c_coupon_title,c_coupon_type,c_coupon_price,c_coupon_buy_price,coupon_type_id,coupon_aval_time,add_time';
		$avai_coupon = Db::name('coupon_users')->where($where)->field($field)->order('add_time desc')->select();
		if($avai_coupon){
			$avai_arr = [];
			$unavai_arr = [];
			foreach($avai_coupon as &$v){
				$couponInfo = Db::name('coupon')->where('coupon_id',$v['coupon_id'])->field('coupon_type,disabled,coupon_thumb')->find();
		 
				$disabled  = json_decode($couponInfo['disabled'],true);
				$category =[];
				foreach($disabled as $val){
					$disabled_name =  Db::name('goods_category')->where('category_id',$val)->value('category_name');
					$category[] = $disabled_name;
				}
				$disabled_value  = implode(',',$category);
				
				$v['disabled_value']  = $disabled_value;
				$v['coupon_thumb']  = $couponInfo['coupon_thumb'];
				 
				$v['s_time'] = date('y.m.d H:i',$v['add_time']);
				$v['e_time'] = date('y.m.d H:i', $v['coupon_aval_time']);
				$time = $v['coupon_aval_time'] - $v['add_time'];
				$day = floor($time/(3600*24));
				$surplus_time = $day;
				$v['surplus_time'] = $surplus_time;				
				unset($v['add_time']);
				unset($v['coupon_aval_time']);
				//商品券
				if($v['c_coupon_type'] == 1){
					$available_value = Db::name('goods')->where('goods_id',$v['coupon_type_id'])->column('goods_name');
					$available_value  = implode(',',$available_value);
					$v['available_value'] = $available_value;
					$v['disabled_value'] = '';

					$v['coupon_type_value'] = '商品券';
					$v['avai'] = '指定商品';		
					if(($v['c_coupon_buy_price'] <= $all_price) && in_array($v['coupon_type_id'], $goods_ids)){
						$avai_arr[] = $v;
					}
					else $unavai_arr[] = $v;
				}
				//专区券
				else if($v['c_coupon_type'] == 2){
					$active_type_name =  Db::name('active_type')->where('id',$v['coupon_type_id'])->value('active_type_name');
					$v['available_value']  = $active_type_name;	
					$v['disabled_value'] = '';

					$v['coupon_type_value'] = '专区券';
					$v['avai'] = '指定活动';		
					if(($v['c_coupon_buy_price'] <= $all_price) && in_array($v['coupon_type_id'], $goods_type_arr)){
						$avai_arr[] = $v;
					}
					else $unavai_arr[] = $v;
				}
				//全场券
				else if($v['c_coupon_type'] == 3){
					$active_type_name =  Db::name('active_type')->where('id',$v['coupon_type_id'])->value('active_type_name');
					$v['coupon_type_id']  = $active_type_name;
					$disabled  = json_decode($v['disabled'],true);
					 $category =[];
					foreach($disabled as $val){
						$disabled_name =  Db::name('goods_category')->where('category_id',$val)->value('category_name');
						$category[] = $disabled_name;
					}
					$disabled_value  = implode(',',$category);
					$v['available_value'] = '';
					$v['disabled_value']  = $disabled_value;	



					$v['coupon_type_value'] = '全场券';
					$v['avai'] = '全平台';
					if($v['c_coupon_buy_price'] <= $all_price){
						$avai_arr[] = $v;
					}
					else $unavai_arr[] = $v;
				}
			}
			if($type == 1){
				return ['avai' => $avai_arr];
			}
			else return ['avai' => $unavai_arr];
		}
		else return false;

		// if($v['coupon_type'] == 1){
		// 		$available_value = Db::name('goods')->where('goods_id',$v['coupon_type_id'])->column('goods_name');
		// 		$available_value  = implode(',',$available_value);
		// 		$v['available_value'] = $available_value;
		// 		$v['disabled_value'] = '';
		// 	}
		// 	if($v['coupon_type'] == 2){
		// 		$active_type_name =  Db::name('active_type')->where('id',$v['coupon_type_id'])->value('active_type_name');
		// 		// $v['coupon_type_id']  = $active_type_name;
		// 		// $disabled  = json_decode($v['disabled'],true);
		// 		//  $category =[];
		// 		// foreach($disabled as $val){
		// 		// 	$disabled_name =  Db::name('goods_category')->where('category_id',$val)->value('category_name');
		// 		// 	$category[] = $disabled_name;
		// 		// }
		// 		// $disabled_value  = implode(',',$category);
		// 		$v['available_value']  = $active_type_name;	
		// 		$v['disabled_value'] = '';
		// 	}
		// 	if($v['coupon_type'] == 3){
		// 		$active_type_name =  Db::name('active_type')->where('id',$v['coupon_type_id'])->value('active_type_name');
		// 		$v['coupon_type_id']  = $active_type_name;
		// 		$disabled  = json_decode($v['disabled'],true);
		// 		 $category =[];
		// 		foreach($disabled as $val){
		// 			$disabled_name =  Db::name('goods_category')->where('category_id',$val)->value('category_name');
		// 			$category[] = $disabled_name;
		// 		}
		// 		$disabled_value  = implode(',',$category);
		// 		$v['available_value'] = '';
		// 		$v['disabled_value']  = $disabled_value;	

		// 	}
	}

	/*
	 * 个人主页
	 */
	public function userCenter($uid){
		$field = 'a.user_id,a.user_name,a.user_mobile,a.user_avat,a.client_id,a.user_sex,a.user_birth,a.user_hobby,a.user_addr,a.is_seller,a.is_kefu,a.user_sign,a.user_account,a.user_wx,a.user_qq,a.user_points,a.id_auth,a.s_invite_code as invite_code,b.t_uid,b.t_p_uid';
		$map = [
			'a.user_id' => $uid
		];

		$user_info = $this->model->alias('a')->where($map)->field($field)->join('__USERS_TREE__ b', 'a.user_id=b.t_uid', 'LEFT')->find();
		if($user_info['is_seller']){
			$store_info = Db::name('store')->field('s_id,s_name,s_thumb,s_grade')->where('s_uid',$user_info['user_id'])->find();
			if($store_info){
				$store_info['s_grade'] = $store_info['s_grade'] == 1 ? '会员店主' : ($store_info['s_grade'] == 2 ? '高级店主' : '旗舰店主');
			}			
			$user_info['store_info'] = $store_info;
		}
		$user_truename = Db::name('idauth')->where('auth_uid',$uid)->value('auth_truename');
		$user_info['user_truename'] = $user_truename?$user_truename:'';
	 
		$map = [
			'touid'=>$uid,
			'looked'=>0,
		];
		$remind_number = Db::name('msg')->where($map)->count();
		$remind_number = $remind_number?:0;
		$where_ms = [
            'user_id'=>$uid,
            'md_type'=>1,
            'md_send_time'=>['<=',time()]
        ];
        $count_ms = Db::name('message_descript')->where($where_ms)->count();
        $count_ms = $count_ms?:0;
		$user_info['remind_number'] = $remind_number + $count_ms;
	 
		//售后联系人
		if($user_info['t_uid'] && $user_info['t_p_uid']){
			$user_info['p_uname'] = Db::name('users')->where('user_id', $user_info['t_p_uid'])->field('user_name')->find()['user_name'];
		}
		else{
			$user_info['p_uname'] = Db::name('store')->alias('a')->join('__USERS__ b','a.s_uid=b.user_id')->field('b.user_name')->where(['a.s_name' => '平台店主'])->find()['user_name'];
		}
		$affili_state = 0;
		$guanKao = Db::name('business_guakao')->alias('a')->join('business b','b.b_id=a.b_id')->where('bg_uid',$uid)->find();
		if($guanKao){
			$affili_state = 1;
		}
		$user_info['affili_state'] = $affili_state;
		return $user_info;
	}

	/*
     * 我的账户
	 */
	public function userAccount($uid){
		$user_info = parent::find(['user_id' => $uid], 'user_account,user_card');
		$field = 'acco_num,acco_type,acco_type_id,acco_desc,acco_time';
		$info = Db::name('account_log')->where('a_uid', $uid)->field($field)->order('acco_time desc')->select();
		$income = 0.00;
		$consume = 0.00;
		$arr = [];
		// 我的充值卡
		$recharge = 0;
		$recharge = Db::name('user_rc')->where(['card_uid' => $uid, 'card_stat' => 1])->count();
		// 我的元宝
		$yinzi = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_stat' => ['lt','3']])->count();
		$arr['rech_num'] = $recharge;
		$arr['yin_num'] = $yinzi;

		if($info){
			foreach($info as $k => $v){				
				if($v['acco_num'] > 0){
					$income += $v['acco_num'];
				}
				else{
					$consume += $v['acco_num'];
				}
			}
		}
		$arr['balance'] = $user_info['user_account'];
		$arr['income'] = $income;
		$arr['consume'] = -$consume;
		return $arr;
	}

	/*
	 * 账户明细
 	 */
	public function accountLog($uid, $type, $p = 1,$month){
		$num = 10;
		$p = $p ? $p : 1;
		$s = ($p - 1) * $num;
		$map['a.a_uid'] = $uid;
		$field = 'a.acco_num,a.acco_type,a.acco_type_id,a.acco_desc,a.acco_time,b.user_name';
        $m_start = $month;
        $m_end = date('Y-m-d', strtotime("$m_start +1 month -1 day"));
		//消费类型：1，收入；2，支出
		$map['a.acco_num'] = ($type == 1) ? ['gt',0] : ['lt',0];
		$list = Db::name('account_log')->alias('a')->join('__USERS__ b', 'a.a_uid=b.user_id', 'LEFT')->where('acco_time', ['egt', strtotime($m_start)], ['elt', strtotime($m_end)])->where($map)->field($field)->order('a.acco_time desc')->limit($s, $num)->select();
		$zon_num = Db::name('account_log')->alias('a')->join('__USERS__ b', 'a.a_uid=b.user_id', 'LEFT')->where('acco_time', ['egt', strtotime($m_start)], ['elt', strtotime($m_end)])->where($map)->field($field)->select();
		$cash_way = array('','支付宝提现','微信提现','银行卡提现');
		$arr = [];
		if($list){
			foreach($list as $k => $v){
				switch($v['acco_type']){
					case 1 : $arr[$k]['type'] = '提现';	break;
					case 2 : $arr[$k]['type'] = '购物';	break;
					case 3 : $arr[$k]['type'] = '充值';	break;
					case 4 : $arr[$k]['type'] = '返利';	break;
					case 5 : $arr[$k]['type'] = '分享赚'; break;
					case 6 : $arr[$k]['type'] = '购买优惠券'; break;
					case 7 : $arr[$k]['type'] = '提现失败'; break;
					case 8 : $arr[$k]['type'] = '店铺升级奖励'; break;
                    case 9 : $arr[$k]['type'] = '促销奖励'; break;
                    case 10 : $arr[$k]['type'] = '后台赠送'; break;
                    case 11 : $arr[$k]['type'] = '订单退货取消返利'; break;
                    case 12 : $arr[$k]['type'] = '订单退款'; break;
				}
				if($v['acco_type'] == 1){	
					if($v['acco_type_id']){
						$cashInfo = Db::name('cash')->where('cash_id',$v['acco_type_id'])->field('cash_way')->find();
						$arr[$k]['cash_way'] = $cash_way[$cashInfo['cash_way']];
					}
				}else if($v['acco_type'] == 7){	
					$cashInfo = Db::name('cash')->where('cash_id',$v['acco_type_id'])->field('cash_comm')->find();
					$arr[$k]['cash_comm'] = '';
					if($cashInfo['cash_comm']){
						$arr[$k]['cash_comm'] = $cashInfo['cash_comm'];	
					}
				}
				$arr[$k]['desc'] = $v['acco_desc'];
				$arr[$k]['user_name'] = $v['user_name'];
				$arr[$k]['num'] = $v['acco_num'];
				$arr[$k]['time'] = date('Y-m-d H:i:s', $v['acco_time']);
			}
		}
		$data['list'] = $arr;
		$data['total'] = count($zon_num);
		return $data;
	}
	 
    /*
     * 签到列表
     */
    public function qiandaolist($uid, $year, $month){
        if (!$uid) {
            return false;
        }
        $year = $year ? $year : date('Y', time());
        $month = $month ? $month : date('m', time());
//        $day =   date('m.d', time());
//        $tom = date("m.d",strtotime("+1 day"));
//		$mon_str = $month.','. ($month- 1);
        $where = array(
            's_uid' => $uid,
            's_year' => $year,
//           's_month' =>['in',$mon_str],
            's_month' => $month
        );
        $list = Db::name('signin_log')->where($where)->order('s_add_time asc')->field('s_day,s_month,s_signin_num,s_Integral')->select();
		
//        $signin_num = $list[0]['s_signin_num'] ? : 0;
	    $signin_num = count($list);
        $score_num = 0;
		$arr = [];
		foreach($list as $key=>$v){
			unset($v['s_signin_num']);
//			$arr['date'][$key] = $v['s_month'].'.'.$v['s_day'];
//			if($v['s_day']<10){
//			 $arr['date'][$key] = $v['s_month'].'.0'.$v['s_day'];
//			}
//			$arr['s_Integral'][$key] = $v['s_Integral'];
            $arr['date'][] = $v['s_day'];
			$score_num += $v['s_Integral'];
		}
//        $weeks = $this->get_weeks(time());
//        $now = array_search($day,$weeks['date']);
//        $data = [];
//
//		foreach($weeks['date'] as $key=>$val){
//			if($key<=$now){
//				$data[$key]['s_Integral'] = '未签到';
//				if(in_array($val,$arr['date'])){
//					$data[$key]['s_Integral'] = '已签到';
//				}
//			}else{
//				$num +=1;
//				$s_Integral = $this->judge($num);
//				$data[$key]['s_Integral'] = '+'.$s_Integral;
//
//				if(!in_array($day,$arr['date'])){
//					$prompt = '今日签到可获得'.$this->judge($signin_num).'积分';
//				}else{
//					$prompt = '明日签到可获得'.$this->judge($signin_num+1).'积分';
//				}
//			}
//			if($day == $val){
//				$data[$key]['date_val'] = '今天';
//			}elseif($tom == $val){
//				$data[$key]['date_val'] = '明天';
//			}else{
//				$data[$key]['date_val'] =  $val;
//			}
//			$data[$key]['date'] =  $val;
//		}
        $data = $arr;
		$prompt = '';
		$sing = Db::name('users')->where('user_id',$uid)->value('sing_remind'); 
		return ['list' => $data, 'signin_num' => $signin_num,'score_num'=>$score_num,'prompt'=>$prompt,'sing_remind'=>$sing];
    }
	/*
     *获取积分值
     */
	  public function judge($signin_num) {
		  $rules = $this->getRules();
		  if (is_array($rules)) {
              foreach($rules as $val){
                  if($signin_num>= trim($val['start_day']) && $signin_num<=trim($val['end_day'])){
                      return $val['integral'];
                  }else if($signin_num>$rules[3]['end_day']){
                      return $rules[3]['integral'];
                  }else if($signin_num<$rules[1]['start_day']){
                      return $rules[1]['integral'];
                  } else {
                      return 10;
                  }
              }
          } else {
		      return 10;
          }
	  }
    /*
     * 是否已签到
     */
    public function is_qiandao($uid) {
        if (!$uid) {
            return false;
        }
        $day = date('j', time());
        $where = array(
            's_uid' => $uid,
            's_year' => date('Y', time()),
            's_month' =>  date('n', time()),
            's_day' =>  $day,
        );
        $res = Db::name('signin_log')->where($where)->find();
        if ($res) {
            return true;
        } else {
            return false;
        }
    }
	/*
     * 获取签到规则
     */
	  public function getRules(){
		//获取签到规则
		$ConfigService=new Config();
		$config=$ConfigService->find();
		$jifen = json_decode($config['jifenduihuan'],true);
		return $jifen;
	}
	               
	/**
	 * 获取一周日期
	 * @param $time 时间戳
	 * @param $format 转换格式
	 */
	function get_weeks($time,$format = "m.d"){
	 
	  $week = date('w',$time);
	  if(empty($week)){
		$week = 7;
	  }
	  $data = [];
	  for($i=0;$i<=6;$i++){
		  $data['date'][] = date($format,strtotime( '+'. $i+1-$week.' days',$time));
		  // $data['week'][] = $weekname[$i];
	  }
	 return $data;
	}
 
	
    /*
     * 签到
     */
    public function qiandao($uid){
        if (!$uid) {
            return false;
        }
        $data = array(
            's_uid' => $uid,
            's_add_time' => time(),
            's_year' => date('Y', time()),
            's_month' =>  date('n', time()),
            's_day' =>  date('j', time()),
//            's_signin_num' => 1,
//            's_Integral' => $s_Integral,
        );
        // 判断今天是否是 1号 按月统计
//        if ($day != 1) {
//            $where = array(
//                's_uid' => $uid,
//                's_year' => date('Y', time()),
//                's_month' =>  date('n', time()),
//                's_day' =>  $day - 1,
//            );
//            $res = Db::name('signin_log')->where($where)->find();
//            if ($res) {
//                $data['s_signin_num'] = $res['s_signin_num'] +1;
//				$s_Integral = $this->judge($data['s_signin_num']);
//				$data['s_Integral'] = $s_Integral;
//                $s_signin_num = $data['s_signin_num'];
//            }
//        }
        $old_time = strtotime("-1 day");
        $where = array(
            's_uid' => $uid,
            's_year' => date('Y', $old_time),
            's_month' =>  date('n', $old_time),
            's_day' =>  date('j', $old_time),
        );
        $res = Db::name('signin_log')->where($where)->find();
        // 按签到30天统计 查询 昨天 是否签到 或者 已经签到 30天
        if (!$res || $res['s_signin_num'] == 30) {
            $data['s_signin_num'] = 1;
            $s_Integral = $this->judge($data['s_signin_num']);
            $data['s_Integral'] = $s_Integral;
        } else {
            $data['s_signin_num'] = $res['s_signin_num'] +1;
            $s_Integral = $this->judge($data['s_signin_num']);
            $data['s_Integral'] = $s_Integral;
        }
        // 签到表
        $res1 = Db::name('signin_log')->insert($data);

        $log_data = array(
            'p_uid' => $uid,
            'point_num' => $s_Integral,
            'point_type' => 7,
            'point_desc' => '签到奖励',
            'point_add_time' => time()
        );
        // 积分日志表
        $res2 = Db::name('points_log')->insert($log_data);
         
        // 用户表
        $res3 = Db::name('users')->where(array('user_id' => $uid))->setInc('user_points', $s_Integral);
        if ($res1 && $res2 && $res3) {
            return true;
        } else {
            return false;
        }
    }
	/*
	 * 奖励积分列表显示
	 */
	public function jl_qiaodao($uid){
		$where = [
			'point_type'=>['eq',7],
            'p_uid' => $uid,
		];
		$res = Db::name('points_log')->where($where)->field('p_log_id,point_num,point_add_time')->select();
		if($res){
			foreach($res as &$val){
			    switch ($val['point_num']) {
                    case 4:
                        $val['s_sigin_num'] = 7;
                        break;
                    case 6:
                        $val['s_sigin_num'] = 15;
                        break;
                    case 8:
                        $val['s_sigin_num'] = 30;
                        break;
                    default:
                        $val['s_sigin_num'] = $val['point_num'];
                        break;
                }
			}
		}
		return $res;
	}
	/*
	 * 我的优惠券
	 */
	public function getCoupon($uid){
		$info = Db::name('coupon_users')->alias('a')->join('__COUPON__ b', 'a.coupon_id=b.coupon_id', 'inner')->field('a.c_id,a.add_time,a.coupon_stat,a.c_coupon_type,a.coupon_aval_time,a.c_coupon_title as coupon_title,a.c_coupon_thumb as coupon_thumb,a.c_coupon_price as coupon_price,a.c_coupon_buy_price as coupon_use_limit,b.coupon_s_time,b.coupon_type,b.coupon_type_id,b.disabled')->where(['a.c_uid' => $uid, 'a.coupon_stat' => 1])->order('a.coupon_stat asc,a.add_time desc')->select();
		$arr = [];
		$coupon_type =array('','商品券','专区券','全场券');
		foreach($info as $v){
			if($v['coupon_aval_time'] < time()){
				unset($v);
				continue;
			}
			if($v['coupon_s_time'] > $v['add_time']){
				$v['s_time'] = date('y.m.d H:i', $v['coupon_s_time']);
				$end_time = $v['coupon_s_time'];
			}
			else{
				$v['s_time'] = date('y.m.d H:i', $v['coupon_s_time']);
				$end_time = $v['add_time'];
			}
			$v['e_time'] = date('y.m.d H:i', $v['coupon_aval_time']);
			if($end_time<time()){
				$end_time = time();
			}
			$time = $v['coupon_aval_time'] - $end_time;
			$day = floor($time/(3600*24));
			$surplus_time = $day;
			$v['surplus_time'] = $surplus_time;
			if($v['coupon_type'] == 1){
				$available_value = Db::name('goods')->where('goods_id',$v['coupon_type_id'])->column('goods_name');
				$available_value  = implode(',',$available_value);
				$v['available_value'] = $available_value;
				$v['disabled_value'] = '';
			}
			if($v['coupon_type'] == 2){
				$active_type_name =  Db::name('active_type')->where('id',$v['coupon_type_id'])->value('active_type_name');
				// $v['coupon_type_id']  = $active_type_name;
				// $disabled  = json_decode($v['disabled'],true);
				//  $category =[];
				// foreach($disabled as $val){
				// 	$disabled_name =  Db::name('goods_category')->where('category_id',$val)->value('category_name');
				// 	$category[] = $disabled_name;
				// }
				// $disabled_value  = implode(',',$category);
				$v['available_value']  = $active_type_name;	
				$v['disabled_value'] = '';
			}
			if($v['coupon_type'] == 3){
				$active_type_name =  Db::name('active_type')->where('id',$v['coupon_type_id'])->value('active_type_name');
				$v['coupon_type_id']  = $active_type_name;
				$disabled  = json_decode($v['disabled'],true);
				 $category =[];
				foreach($disabled as $val){
					$disabled_name =  Db::name('goods_category')->where('category_id',$val)->value('category_name');
					$category[] = $disabled_name;
				}
				$disabled_value  = implode(',',$category);
				$v['available_value'] = '';
				$v['disabled_value']  = $disabled_value;	

			}
			$v['coupon_type_value'] = $coupon_type[$v['c_coupon_type']];
			unset($v['add_time']);
			unset($v['coupon_s_time']);
			unset($v['coupon_aval_time']);
			unset($v['disabled']);
			unset($v['coupon_type_id']);
			$arr[] = $v;
		}
		return $arr;
	}

	/*
	 * 我的优惠券
	 */
	public function getCouponOrder($uid,$type,$goodsids,$price){
		$info = Db::name('coupon_users')
            ->alias('a')
            ->join('__COUPON__ b', 'a.coupon_id=b.coupon_id', 'inner')
            ->field('a.c_id,a.add_time,a.coupon_stat,a.c_coupon_type,a.coupon_aval_time,a.c_coupon_title as coupon_title,a.c_coupon_thumb as coupon_thumb,a.c_coupon_price as coupon_price,a.c_coupon_buy_price as coupon_use_limit,b.coupon_s_time,b.coupon_type,b.coupon_type_id,b.disabled')->where(['a.c_uid' => $uid, 'a.coupon_stat' => 1])
            ->where('c_coupon_buy_price','<',$price)
            ->order('a.coupon_stat asc,a.add_time desc')
            ->select();

		$goods_arr = explode(',',$goodsids);

		$arr = [];
		$coupon_type =array('','商品券','专区券','全场券');
		foreach($info as $v){
			if($v['coupon_aval_time'] < time()){
				unset($v);
				continue;
			}
			if($v['coupon_s_time'] > $v['add_time']){
				$v['s_time'] = date('y.m.d H:i', $v['coupon_s_time']);
				$end_time = $v['coupon_s_time'];
			}
			else{
				$v['s_time'] = date('y.m.d H:i', $v['coupon_s_time']);
				$end_time = $v['add_time'];
			}
			$v['e_time'] = date('y.m.d H:i', $v['coupon_aval_time']);
			if($end_time<time()){
				$end_time = time();
			}
			$time = $v['coupon_aval_time'] - $end_time;
			$day = floor($time/(3600*24));
			$surplus_time = $day+1;
			$v['surplus_time'] = $surplus_time;
			if($v['coupon_type'] == 1){
			    if(in_array($v['coupon_type_id'],$goods_arr)){
                    $available_value = Db::name('goods')->where('goods_id',$v['coupon_type_id'])->column('goods_name');
                    $available_value  = implode(',',$available_value);
                    $v['available_value'] = $available_value;
                    $v['disabled_value'] = '';
                }
			}
			if($v['coupon_type'] == 2){
				$active_type_name =  Db::name('active_type')->where('id',$v['coupon_type_id'])->value('active_type_name');
				// $v['coupon_type_id']  = $active_type_name;
				// $disabled  = json_decode($v['disabled'],true);
				//  $category =[];
				// foreach($disabled as $val){
				// 	$disabled_name =  Db::name('goods_category')->where('category_id',$val)->value('category_name');
				// 	$category[] = $disabled_name;
				// }
				// $disabled_value  = implode(',',$category);
				$v['available_value']  = $active_type_name;
				$v['disabled_value'] = '';
			}
			if($v['coupon_type'] == 3){
				$active_type_name =  Db::name('active_type')->where('id',$v['coupon_type_id'])->value('active_type_name');
				$v['coupon_type_id']  = $active_type_name;
				$disabled  = json_decode($v['disabled'],true);
				 $category =[];
				foreach($disabled as $val){
					$disabled_name =  Db::name('goods_category')->where('category_id',$val)->value('category_name');
					$category[] = $disabled_name;
				}
				$disabled_value  = implode(',',$category);
				$v['available_value'] = '';
				$v['disabled_value']  = $disabled_value;

			}
			$v['coupon_type_value'] = $coupon_type[$v['c_coupon_type']];
			unset($v['add_time']);
			unset($v['coupon_s_time']);
			unset($v['coupon_aval_time']);
			unset($v['disabled']);
			unset($v['coupon_type_id']);
			$arr[] = $v;
		}
		return $arr;
	}

	/*
	 * 我的积分
	 */
	public function userPoints($uid){
		$user_info = parent::find(['user_id' => $uid], 'user_points');
		$field = 'point_num,point_desc,point_add_time';
		$info = Db::name('points_log')->where('p_uid', $uid)->field($field)->order('point_add_time desc')->select();
		$gain = 0;
		$used = 0;
		$arr = [];
		$arr['recent'] = [];
		foreach($info as $k => &$v){
			if($v['point_num'] > 0){
				$gain += $v['point_num'];
				$v['point_num'] = '+'.$v['point_num'];
			}
			else{
				$used += $v['point_num'];
			}
			$v['point_add_time'] = date('Y-m-d H:i:s', $v['point_add_time']);
			//近30天记录
			if($v['point_add_time'] >= date('Y-m-d H:i:s', time() - 30 * 24 * 3600)){
				$arr['recent'][] = $v;
			}
		}
		
		$arr['total'] = $user_info['user_points'];
		$arr['gain'] = $gain;
		$arr['used'] = -$used;
		return $arr;
	}

	/*
	 * 积分明细
	 */
	public function pointsLog($uid, $p, $month,$type){
		$num = 10;
		$s = ((int)$p-1) * $num;
		$field = 'point_num,point_desc,point_add_time,point_type';
		$time = time();
		if($month){
			$time = strtotime($month);
		}
		if($type == 1){
			$where['point_num']=['gt',0];
		}else{
			$where['point_num']=['elt',0];
		}
		
 
		$m_start = date('Y-m', $time);
		$m_end = date('Y-m-d', strtotime("$m_start +1 month -1 day"));
		$info = Db::name('points_log')->where('point_add_time', ['egt', strtotime($m_start)], ['elt', strtotime($m_end)])->where(['p_uid' => $uid])->where($where)->field($field)->order('point_add_time desc')->limit($s, $num)->select();
		$info_page = Db::name('points_log')->where('point_add_time', ['egt', strtotime($m_start)], ['elt', strtotime($m_end)])->where(['p_uid' => $uid])->where($where)->field($field)->order('point_add_time desc')->select();
		$gain = Db::name('points_log')->where('point_add_time', ['egt', strtotime($m_start)], ['elt', strtotime($m_end)])->where(['p_uid' => $uid])->where(array('point_num'=>['gt',0]))->sum('point_num');
 
 
		$used = Db::name('points_log')->where('point_add_time', ['egt', strtotime($m_start)], ['elt', strtotime($m_end)])->where(['p_uid' => $uid])->where(array('point_num'=>['elt',0]))->sum('point_num');
	 
		
		$arr = [];
		$arr['total'] = count($info_page);
		$arr['month'] = $m_start;
		$arr['gain'] = 0;
		$arr['used'] = 0;
		if($info){
			$month = [];
			foreach($info as $k => &$v){
				$v['point_add_time'] = date('Y-m-d H:i:s', $v['point_add_time']);
				if($gain){
					$arr['gain'] = $gain;
				} 
				if($used){
					$arr['used'] = -$used;
				}
				if($v){
					$arr['list'][] = $v;	
				}
							
			}
		}else{
			$arr['list'] = '';
		}
		
		return $arr;
	}

	/*
	 * 我的足迹
	 */
	public function userTrack($uid, $p = 1){
		$num = 10;
		$s = ($p - 1) * $num;
		$this->model = new GoodsM();
		$field = 'a.goods_id,a.goods_name,a.price,a.picture,a.prom_type,a.prom_id,a.introduction as goods_intro,a.stock,a.status,b.track_id,b.track_add_time';
		$map = [
			'b.t_uid' => $uid,
			'b.track_visible' => 1
		];
		$info = $this->model->alias('a')->join('__USERS_TRACK__ b', 'a.goods_id=b.track_goods_id', 'RIGHT')->field($field)->where($map)->order('b.track_add_time desc')->limit($s, $num)->select();
		$infos = $this->model->alias('a')->join('__USERS_TRACK__ b', 'a.goods_id=b.track_goods_id', 'RIGHT')->field($field)->where($map)->select();
		$arr = [];
		$day = [];
		$i = 0;
		$arr['total'] = count($infos);
		if(!$info){
			return 0;
		}
		foreach($info as $k => $v){
			$d_start = date('Y-m-d 00:00:00', $v['track_add_time']);		//一天开始
			$d_end = date('Y-m-d H:i:s', strtotime("$d_start +1 day -1 second"));
			$time_format = date('Y-m-d', $v['track_add_time']);
			if($time_format == date('Y-m-d', time())){
				$track_date = '今天';
			}
			else if($time_format == date('Y-m-d', time() - 24 * 3600)){
				$track_date = '昨天';
			}
			else{
				$track_date = $time_format;
			}
			if($k == 0) $day[0] = $track_date;
			if(!in_array($track_date, $day)){
				$day[] = $track_date;
				$i++;
			}
		 
			$v['price'] = sprintf('%0.2f', $v['price']);
			$v['price'] = floatval($v['price']);	
			// $arr[$i]['day'] = $track_date;
			$arr['list'][] = $v;
		}
		return $arr;
	}

	/*
	 * 足迹编辑
	 */
	public function trackEdit($uid, $tid){
		$res = Db::name('users_track')->where(['track_id' => ['in', $tid], 't_uid' => $uid])->update(['track_visible' => 0]);
		return $res ? 1 : 0;		
	}

	/*
	 * 我的地址
	 */
	// public function userAddr($uid){
	// 	$addr_info = Db::name('addr')->field('addr_id,addr_area,addr_cont,is_default,addr_receiver,addr_phone,post_no')->where(['a_uid' => $uid, 'is_del' => 0])->order('is_default desc,addr_add_time desc')->select();
	// 	return $addr_info;
	// }
  
  
      /**
     * 计算两点地理坐标之间的距离
     * @param Decimal $longitude1 起点经度
     * @param Decimal $latitude1 起点纬度
     * @param Decimal $longitude2 终点经度 
     * @param Decimal $latitude2 终点纬度
     * @param Int   $unit    单位 1:米 2:公里
     * @param Int   $decimal  精度 保留小数位数
     * @return Decimal
     */
    function getDistance($longitude1, $latitude1, $longitude2, $latitude2, $unit=2, $decimal=2){

      $EARTH_RADIUS = 6370.996; // 地球半径系数
      $PI = 3.1415926;

      $radLat1 = $latitude1 * $PI / 180.0;
      $radLat2 = $latitude2 * $PI / 180.0;

      $radLng1 = $longitude1 * $PI / 180.0;
      $radLng2 = $longitude2 * $PI /180.0;

      $a = $radLat1 - $radLat2;
      $b = $radLng1 - $radLng2;

      $distance = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1) * cos($radLat2) * pow(sin($b/2),2)));
      $distance = $distance * $EARTH_RADIUS * 1000;

      if($unit==2){
        $distance = $distance / 1000;
      }

      return round($distance, $decimal);

    }

	/*
	 * 我的地址
	 */
	public function userAddr($uid){
		$addr_info = Db::name('addr')->field('addr_id,addr_province,addr_city,addr_area,addr_cont,is_default,addr_receiver,addr_phone,post_no,lng,lat')->where(['a_uid' => $uid, 'is_del' => 0])->order('is_default desc,addr_add_time desc')->select();
		
      	foreach ($addr_info as $key=>$value){
            if($value['addr_province']){
                $provinceName=$this->getRegion(['region_id'=>$value['addr_province']]);
                $value['province']=$provinceName;
            }
            if($value['addr_city']){
                $cityName=$this->getRegion(['region_id'=>$value['addr_city']]);
                $value['city']=$cityName;
            }
            if($value['addr_area']){
                $areaName=$this->getRegion(['region_id'=>$value['addr_area']]);
                $value['district']=$areaName;
            }
          	//配送仓库和配送费用
            $value['stock_id']=0;
            $value['yunfei']=0;
            $value['distance'] = 0;
          
          	if(!empty($value['lng'])&&!empty($value['lat'])){
          	    
              $stock = Db::name('stock')->where("city",$value['city'])->find();
            
              if($stock){

                  //距离 km
                    $distance = $this->getDistance($value['lng'], $value['lat'], $stock['lng'], $stock['lat'], 2);
                  
                	$value['distance'] = $distance;
                	
				  if($distance<=$stock['start_distance']){
				      
                  	   $value['yunfei']=$stock['start_price'];
                  	
                  }else {
                      
    					$add_distance = bcsub($distance,$stock['start_distance'],2);
    					
                        $add_price = bcmul($add_distance,$stock['add_price'],2);
                        
                        $value['yunfei']= bcadd($stock['start_price'],$add_price,2);
                        
                    
                  }
                  
                  $value['stock_id']=$stock['id'];
                  
              }
              
            }
          	
            $addr_info[$key]=$value;
            
        }
		return $addr_info;
	}

	/*
	 * 地址详情
	 */
	public function addrInfo($uid, $addrid){
		$addressInfo= Db::name('addr')->field('addr_id,addr_province as province,addr_city as city,addr_area as district,addr_cont,is_default,addr_receiver,addr_phone,post_no')->where(['a_uid' => $uid, 'addr_id' => $addrid])->find();
        if($addressInfo['province']){
            $provinceName=$this->getRegion(['region_id'=>$addressInfo['province']]);
        }
        if($addressInfo['city']){
            $cityName=$this->getRegion(['region_id'=>$addressInfo['city']]);
        }
        if($addressInfo['district']){
            $areaName=$this->getRegion(['region_id'=>$addressInfo['district']]);
        }
        $addressInfo['pca_address']=$provinceName." ".$cityName." ".$areaName;
		return $addressInfo;
	}	
	/*
	 * 地址详情
	 */
	public function address($province,$city,$addr_area){
        if($province){
            $provinceName=$this->getRegion(['region_id'=>$province]);
        }
        if($city){
            $cityName=$this->getRegion(['region_id'=>$city]);
        }
        if($addr_area){
            $areaName=$this->getRegion(['region_id'=>$addr_area]);
        }
        $addressInfo['pca_address']=$provinceName." ".$cityName." ".$areaName;
		return $addressInfo;
	}
    /**
     * @param $uid
     * @param array $data
     * @return int
     * 获得省市区
     */
    function getRegion($where){
        $regionName=Db::name("region")->where($where)->value("region_name");
        return $regionName;
    }

	// /*
	//  * 地址详情
	//  */
	// public function addrInfo($uid, $addrid){
	// 	return Db::name('addr')->field('addr_id,addr_area,addr_cont,is_default,addr_receiver,addr_phone,post_no')->where(['a_uid' => $uid, 'addr_id' => $addrid])->find();
	// }

	/*
	 * 地址新增编辑
	 */
	public function addrEdit($uid, $data = []){
		$save = $data;
		//编辑
		if($data['addr_id']){
			Db::startTrans();
			try{
				if($data['is_default']){
					$this->setDefault($uid, 'unset');
				}
				$res = Db::name('addr')->where('addr_id', $data['addr_id'])->update($save);
				Db::commit();
			}
			catch(\Exception $e){
				$res = false;
				Db::rollback();
			}
		}
		//新增
		else{
			Db::startTrans();
			try{
				if($data['is_default']){
					$this->setDefault($uid, 'unset');
				}
				$save['a_uid'] = $uid;
				$res = Db::name('addr')->insert($save);
				Db::commit();
			}
			catch(\Exception $e){
				$res = false;
				Db::rollback();
			}
		}
		return $res!== false ? 1 : 0;
	}

	/*
	 * 设置默认地址
	 */
	public function addrDefault($uid, $addr_id){
		//设置默认
		// if($is_default){
			Db::startTrans();
			try{
				$this->setDefault($uid, 'unset');				
				$res = Db::name('addr')->where('addr_id', $addr_id)->update(['is_default' => 1]);
				Db::commit();
			}
			catch(\Exception $e){
				Db::rollback();
				$res = false;
			}
		// }
		// //取消默认
		// else{
			// $res = Db::name('addr')->where('addr_id', $addr_id)->update(['is_default' => 0]);
		// }
		return $res!== false ? 1 : 0;
	}

	/*
	 * 删除地址 
	 */
	public function addrDel($uid, $addrid){
		$addr_info = Db::name('addr')->where('addr_id', $addrid)->find();
		if($addr_info['is_default']){
			Db::startTrans();
			try{
				$this->setDefault($uid, 'set');
				$res = Db::name('addr')->where('addr_id', $addrid)->update(['is_del' => 1]);
				Db::commit();
			}
			catch(\Exception $e){
				Db::rollback();
				$res = false;
			}
		}
		else{
			$res = Db::name('addr')->where('addr_id', $addrid)->update(['is_del' => 1]);
		}
		return $res!== false ? 1 : 0;
	}

	/*
	 * 默认地址设置
	 */
	private function setDefault($uid, $type){
		if($type == 'unset'){
			$addr_info = Db::name('addr')->where(['a_uid' => $uid, 'is_default' => 1])->field('addr_id')->select();
			if($addr_info){
				foreach($addr_info as $v){
					Db::name('addr')->where('addr_id', $v['addr_id'])->update(['is_default' => 0]);
				}				
			}
		}
		else if($type == 'set'){
			$addr_info = Db::name('addr')->where(['a_uid' => $uid, 'is_default' => 0, 'is_del' => 0])->order('addr_add_time desc')->find();
			if($addr_info){
				Db::name('addr')->where('addr_id', $addr_info['addr_id'])->update(['is_default' => 1]);
			}
		}		
	}

	/*
	 * 我的收藏
 	 */
	public function userFavor($uid, $type, $p = 1){
		$num = 10;
		$s = ($p-1) * $num;
		$this->model = new GoodsM();
		//商品收藏
		if($type == 1){
			$map = [
				'b.f_uid' => $uid,
				'b.favor_type' => $type,
			];
			$list = $this->model->alias('a')->join('__FAVORITE__ b', 'a.goods_id=b.f_goods_id', 'RIGHT')->field('a.goods_name,b.favor_type,a.picture,a.prom_type,a.prom_id,a.price,a.status,b.favor_id,b.f_goods_id')->where($map)->order('b.f_add_time desc')
                //->limit($s, $num)
                ->select();
			if($list){
				foreach($list as $val){
					$val['price'] = sprintf('%0.2f', $val['price']);
					$val['price'] = floatval($val['price']);	
				}
			}
		}
		//素材收藏
		elseif($type >= 2){
			$map = [
				'a.f_uid' => $uid,
				'a.favor_type' => ['>',1],
			];
			$list = Db::name('favorite')->alias('a')->join('__USERS_MATERIAL__ b', 'a.f_goods_id=b.m_id', 'RIGHT')->join('__USERS__ c', 'b.m_uid=c.user_id', 'RIGHT')->field('a.favor_id,a.favor_type,a.f_goods_id,b.m_id,b.mate_content,b.mate_thumb,b.mate_add_time,b.m_goods_id,b.mate_video,c.user_name,c.user_avat')->where($map)->order('a.f_add_time desc')->limit($s, $num)->select();
		}

		foreach($list as $key=>$v){
			if($v['favor_type'] >= 2){
				$goods_info = Db::name('goods')
					->where('goods_id',$v['m_goods_id'])
					->field('goods_name,picture,goods_banner,is_gift,price,vip_price,show_price,prom_type,prom_id,commission')
					->find();
				if($goods_info){
					$goodsService = new goodsService();
					$active_price = $goodsService->getActivePirce($goods_info['price'],$goods_info['prom_type'],$goods_info['prom_id']);
					$commission = $this->getCom();
					//开启 返利
					if($commission['shop_ctrl'] == 1){
						$f_p_rate = $commission['f_s_rate'];
					}else{
						$f_p_rate = 100; 
					}
					$goods_info['dianzhu_price'] = floor($active_price * $goods_info['commission']/ 100 * $f_p_rate)/100;
					$goods_info['dianzhu_price'] = floatval($goods_info['dianzhu_price']);
					$goods_info['vip_price'] = sprintf('%0.2f', $goods_info['vip_price']);
					$goods_info['vip_price'] = floatval($goods_info['vip_price']);	
					$goods_info['price'] = sprintf('%0.2f', $goods_info['price']);
					$goods_info['price'] = floatval($goods_info['price']);	
					$goods_info['show_price'] = sprintf('%0.2f', $goods_info['show_price']);
					$goods_info['show_price'] = floatval($goods_info['show_price']);

                    $list[$key]['goods'] = $goods_info;
				}else{
				    $list[$key]['goods'] = '';
				}
				
			}else{
            //获取sku_id
                $sku_id = Db::name("goods_sku")->where(['goods_id' => $v['f_goods_id']])->group('code')->order('sku_id asc')->find();
                $list[$key]['sku_id'] = $sku_id['sku_id'];

            }
			if(isset($v['mate_thumb']) && $v['mate_thumb']){				
				$list[$key]['mate_thumb'] = explode(',', $v['mate_thumb']);
			}
			if(isset($v['mate_add_time']) && $v['mate_add_time']){
				$list[$key]['mate_add_time'] = date('Y-m-d H:i', $v['mate_add_time']);
			}
		}
		return ['total' => count($list), 'list' => $list];
	}

	/*
	 * 收藏素材删除
	 */
	public function favorDel($uid, $fid){
	    $where['favor_id'] = ['in',$fid];
	    $where['f_uid'] = $uid;
		$res = Db::name('favorite')->where($where)->delete();
		return $res !== false ? 1 : 0;
	}

	/*
	 * 素材列表
	 */
	public function userMaterial($user_id, $p,$cat_id,$type){
		$num = 10;
		$s = ($p - 1) * $num;
		$arr = [];
		//明星专访
		if($type==1){
				$map = [
					'tp_status'=>0,
					'tp_type'=>$type,
				];
				$list = Db::name('topic')
					->alias('a')
					->join('users b','a.tp_user_id=b.user_id')
					->where($map)
					->order('a.tp_addtime desc')
					->field('a.tp_id,a.tp_title,a.tp_banner,a.tp_img,a.tp_video,a.tp_addtime,a.tp_content,a.tp_like,a.tp_type,b.user_name,b.user_avat')
					->limit($s, $num)
					->select();
					$arr['total'] = Db::name('topic')
					->alias('a')
					->join('users b','a.tp_user_id=b.user_id')
					->where($map)->count();
					if($list){
						foreach($list as &$val){
							$val['tp_addtime'] = date('m月d日',$val['tp_addtime']);
							$links = Db::name('like')->where(array('l_type'=>1,'l_topic_id'=>$val['tp_id']))->count();
							$val['like_num'] = $links;
						}
						$list =	$this->getInfo($user_id,$list,$type);
					}
		//话题
		}elseif($type == 2){

			$map = [
					'tp_status'=>0,
					'tp_type'=>$type,
				];
			$list = Db::name('topic')->where($map)->order('tp_addtime desc')->limit($s, $num)->select();
			
				
			$arr['total'] = Db::name('topic')->where($map)->order('tp_addtime desc')->count();
				if($list){
					foreach($list as $key=>$val){
						$uid_arr = Db::name('users_material')->where(array('m_cat_id'=>$val['tp_id'],'m_type'=>2))->limit(0,3)->group('m_uid')->column('m_uid');
						if($uid_arr){
							$uid_str = implode(',',$uid_arr);
							$avat_arr = Db::name('users')->where(array('user_id'=>['in',$uid_str]))->column('user_avat');
							$avat_arr_num = count($avat_arr);
							$list[$key]['avat_list'] = $avat_arr;
							$list[$key]['tp_partake_num']=$avat_arr_num;
						}
					}
					$list =	$this->getInfo($user_id,$list,$type);
				}
		//推荐
		}elseif($type == 3){
			$list = Db::name('users_material')->alias('a')->join('__USERS__ b', 'a.m_uid=b.user_id', 'inner')->field('a.m_id,a.m_goods_id,a.mate_content,a.mate_video,a.mate_thumb,a.mate_add_time,a.mate_zhiding,b.user_name,b.user_avat')->where(['a.mate_status'=>1])->order('a.mate_add_time desc')->limit($s, $num)->select();
			$arr['total'] =Db::name('users_material')->alias('a')->join('__USERS__ b', 'a.m_uid=b.user_id', 'inner')->where(['a.mate_status'=>1])->count();
			if($list){
				foreach($list as &$v){
					$goods = Db::name('goods')->where('goods_id',$v['m_goods_id'])->field('goods_name,price,picture,commission,vip_price,prom_type,prom_id,show_price')->find();
					$v['favorite'] = 0;
					$goodsService = new goodsService();
					$active_price = $goodsService->getActivePirce($goods['price'],$goods['prom_type'],$goods['prom_id']);
					$commission = $this->getCom();
					//开启 返利
					if($commission['shop_ctrl'] == 1){
						$f_p_rate = $commission['f_s_rate'];
					}else{
						$f_p_rate = 100; 
					}
					$goods['dianzhu_price'] = floor($active_price * $goods['commission']/ 100 * $f_p_rate)/100;	
					$goods['dianzhu_price'] = floor($active_price * $goods['commission']/ 100 * $f_p_rate)/100;	
					$goods['dianzhu_price'] = sprintf('%0.2f', $goods['dianzhu_price']);
					$goods['dianzhu_price'] = floatval($goods['dianzhu_price']);	
					$goods['vip_price'] = sprintf('%0.2f', $goods['vip_price']);
					$goods['vip_price'] = floatval($goods['vip_price']);
					$goods['show_price'] = sprintf('%0.2f', $goods['show_price']);
					$goods['show_price'] = floatval($goods['show_price']);
					$goods['price'] = sprintf('%0.2f', $goods['price']);
					$goods['price'] = floatval($goods['price']);
					if($v['mate_thumb']){
					    $v['mate_thumb'] = trim($v['mate_thumb'], ',');
						$v['mate_thumb'] = explode(',', $v['mate_thumb']);
					}
					if($v['mate_add_time']){
						$v['mate_add_time'] = date('Y-m-d H:i', $v['mate_add_time']);
					}

					if(isset($v['vip_price'])){
						$v['vip_price'] = floor($v['price'] * (1- $v['commission'] / 100));
					 
					}
					//是否收藏
					if($user_id){
						$res = Db::name('favorite')->where(['favor_type' => 2, 'f_uid' => $user_id, 'f_goods_id' => $v['m_id']])->find();
						if($res){
							$v['favorite'] = 1;
						}
					}
					if($goods){
						/* $goods['vip_price'] = $goods['price'] * (1- $goods['commission'] / 100); */
						$v['goods_info'] = $goods;
					}else{
						$v['goods_info'] = '';
					}					
				}
			}
		}else{
			$list = Db::name('users_material')->alias('a')->join('__USERS__ b', 'a.m_uid=b.user_id', 'inner')->field('a.m_id,a.m_goods_id,a.mate_content,a.mate_video,a.mate_thumb,a.mate_add_time,a.mate_zhiding,b.user_name,b.user_avat')->where(['a.m_cat_id'=>$cat_id,'a.mate_status'=>1])->order('a.mate_add_time desc')->limit($s, $num)->select();
			$arr['total'] = Db::name('users_material')->alias('a')->join('__USERS__ b', 'a.m_uid=b.user_id', 'inner')->where(['a.m_cat_id'=>$cat_id])->count();
			if($list){
				foreach($list as &$v){
					$goods = Db::name('goods')->where('goods_id',$v['m_goods_id'])->field('goods_name,price,picture,commission,vip_price,prom_id,prom_type,show_price')->find();
					$v['favorite'] = 0;
					$goodsService = new goodsService();
					$active_price = $goodsService->getActivePirce($goods['price'],$goods['prom_type'],$goods['prom_id']);
					$commission = $this->getCom();
					//开启 返利
					if($commission['shop_ctrl'] == 1){
						$f_p_rate = $commission['f_s_rate'];
					}else{
						$f_p_rate = 100; 
					}
					$goods['dianzhu_price'] = floor($active_price * $goods['commission']/ 100 * $f_p_rate)/100;	
					$goods['dianzhu_price'] = sprintf('%0.2f', $goods['dianzhu_price']);
					$goods['dianzhu_price'] = floatval($goods['dianzhu_price']);	$goods['vip_price'] = sprintf('%0.2f', $goods['vip_price']);
					$goods['vip_price'] = floatval($goods['vip_price']);
					$goods['show_price'] = sprintf('%0.2f', $goods['show_price']);
					$goods['show_price'] = floatval($goods['show_price']);
					$goods['price'] = sprintf('%0.2f', $goods['price']);
					$goods['price'] = floatval($goods['price']);
					if($v['mate_thumb']){
                        $v['mate_thumb'] = trim($v['mate_thumb'], ',');
						$v['mate_thumb'] = explode(',', $v['mate_thumb']);
					}

					if($v['mate_add_time']){
						$v['mate_add_time'] = date('Y-m-d H:i', $v['mate_add_time']);
					}
					if($goods){
						/* $goods['vip_price'] = $goods['price'] * (1- $goods['commission'] / 100); */
						$v['goods_info'] = $goods;
					}else{
						$v['goods_info'] = '';
					}	
					//是否收藏
					if($user_id){
						$res = Db::name('favorite')->where(['favor_type' => 2, 'f_uid' => $user_id, 'f_goods_id' => $v['m_id']])->find();
						if($res){
							$v['favorite'] = 1;
						}
					}	
				}
			}
		}

		
		$arr['list'] = $list;		
		return $arr;
	}	
	/*
	 * 点赞收藏
	 */
	public function getInfo($user_id,$list,$type){
		foreach($list as &$v){
			$v['favorite'] = 0;
			$v['like'] = 0;
			//是否收藏
			
			if($user_id){
				if($type == 1){
					$types = $type ==1?3:$type;
					$res = Db::name('favorite')->where(['favor_type' => $types, 'f_uid' => $user_id, 'f_goods_id' => $v['tp_id']])->find();
					//是否点赞
					$reslut = Db::name('like')->where(['l_uid' => $user_id, 'l_topic_id' => $v['tp_id'],'l_type'=>1])->find();
				}else{
					$res = Db::name('favorite')->where(['favor_type' => $type, 'f_uid' => $user_id, 'f_goods_id' => $v['m_id']])->find();
						//是否点赞
					$reslut = Db::name('like')->where(['l_uid' => $user_id, 'l_topic_id' => $v['m_id'],'l_type'=>$type])->find();
				}
				if($res){
					$v['favorite'] = 1;
				}
				if($reslut){
					$v['like'] = 1;
				}
			}	
		}
		return $list;
	}
	/*
	 * 我的素材
	 */
	public function userMate($uid, $p, $type = '',$cat_id = ''){
		$num = 10;
		$s = ($p - 1) * $num;
		$arr = [];
		if(!$type){
			$list = Db::name('users_material')
                ->alias('a')
                ->join('__USERS__ b', 'a.m_uid=b.user_id', 'inner')
                ->field('a.m_id,a.m_goods_id,a.mate_content,a.mate_video,a.mate_thumb,a.mate_add_time,a.mate_zhiding,b.user_name,b.user_avat,a.m_cat_id')
                ->where(['a.m_uid'=>$uid])
                ->order('a.mate_add_time desc')
                ->limit($s, $num)
                ->select();
		} else {
			$list = Db::name('users_material')
                ->alias('a')
                ->join('__USERS__ b', 'a.m_uid=b.user_id', 'inner')
                ->join('__GOODS__ c', 'a.m_goods_id=c.goods_id', 'inner')
                ->field('a.m_id,a.mate_content,a.mate_thumb,a.mate_add_time,a.mate_video,a.mate_zhiding,b.user_name,b.user_avat,c.goods_id,c.goods_name,c.picture,c.price,c.vip_price,c.commission,c.prom_id,c.prom_type,a.m_cat_id')
                ->where(['a.mate_status' => 1, 'c.status' => 0,'a.m_cat_id'=>$cat_id])
                ->order('a.mate_zhiding desc,a.mate_add_time desc')
                ->limit($s, $num)
                ->select();
		}
		$count = 0;
		if (!$type) {
		    $count = Db::name('users_material')
                ->alias('a')
                ->join('__USERS__ b', 'a.m_uid=b.user_id', 'inner')
                ->field('a.m_id,a.m_goods_id,a.mate_content,a.mate_video,a.mate_thumb,a.mate_add_time,a.mate_zhiding,b.user_name,b.user_avat,a.m_cat_id')
                ->where(['a.m_uid'=>$uid])
                ->order('a.mate_add_time desc')
                ->limit($s, $num)
                ->count();
        } else {
            $count = Db::name('users_material')
                ->alias('a')
                ->join('__USERS__ b', 'a.m_uid=b.user_id', 'inner')
                ->join('__GOODS__ c', 'a.m_goods_id=c.goods_id', 'inner')
                ->field('a.m_id,a.mate_content,a.mate_thumb,a.mate_add_time,a.mate_video,a.mate_zhiding,b.user_name,b.user_avat,c.goods_id,c.goods_name,c.picture,c.price,c.vip_price,c.commission,c.prom_id,c.prom_type,a.m_cat_id')
                ->where(['a.mate_status' => 1, 'c.status' => 0,'a.m_cat_id'=>$cat_id])
                ->order('a.mate_zhiding desc,a.mate_add_time desc')
                ->limit($s, $num)
                ->count();
        }

		if($list){
			foreach($list as &$v){
				$v['favorite'] = 0;
				if($v['mate_thumb']){
                    $v['mate_thumb'] = trim($v['mate_thumb'], ',');
					$v['mate_thumb'] = explode(',', $v['mate_thumb']);
				}

				if($v['mate_add_time']){
					$v['mate_add_time'] = date('Y-m-d H:i', $v['mate_add_time']);
				}

				if(isset($v['vip_price'])){
					$v['vip_price'] = $v['price'] * (1- $v['commission'] / 100);
				}
				//是否收藏
				if($uid){
					$res = Db::name('favorite')->where(['favor_type' => 2, 'f_uid' => $uid, 'f_goods_id' => $v['m_id']])->find();
					if($res){
						$v['favorite'] = 1;
					}
				}	
			}
		}
		$arr['count'] = $count;
		$arr['total'] = count($list);
		$arr['list'] = $list;		
		return $arr;
	}
   	
	/*
	 * 素材详情
	 */
	public function mateInfo($uid, $mid, $type){
	    if ($type) {
            $map = [
                'tp_status'=>0,
                'tp_type'=>1,
                'tp_id' => $mid
            ];
            $info = Db::name('topic')
                ->alias('a')
                ->join('users b','a.tp_user_id=b.user_id')
                ->where($map)
                ->field('a.tp_id,a.tp_title,a.tp_banner,a.tp_img,a.tp_video,a.tp_addtime,a.tp_content,a.tp_like,a.tp_type,b.user_name,b.user_avat')
                ->find();
            if($info){
                $info['tp_addtime'] = date('m月d日',$info['tp_addtime']);
                $links = Db::name('like')->where(array('l_type'=>1,'l_topic_id'=>$info['tp_id']))->count();
                $info['like_num'] = $links;
                $v['favorite'] = 0;
                $v['like'] = 0;
                //是否收藏

                if($uid){
                    if($type == 1){
                        $types = $type ==1?3:$type;
                        $res = Db::name('favorite')->where(['favor_type' => $types, 'f_uid' => $user_id, 'f_goods_id' => $v['tp_id']])->find();
                        //是否点赞
                        $reslut = Db::name('like')->where(['l_uid' => $user_id, 'l_topic_id' => $v['tp_id'],'l_type'=>1])->find();
                    }else{
                        $res = Db::name('favorite')->where(['favor_type' => $type, 'f_uid' => $user_id, 'f_goods_id' => $v['m_id']])->find();
                        //是否点赞
                        $reslut = Db::name('like')->where(['l_uid' => $user_id, 'l_topic_id' => $v['m_id'],'l_type'=>$type])->find();
                    }
                    if($res){
                        $v['favorite'] = 1;
                    }
                    if($reslut){
                        $v['like'] = 1;
                    }
                }
            }
            return $info;
        }
		$info =  Db::name('users_material')->alias('a')->join('__GOODS__ b', 'a.m_goods_id=b.goods_id', 'left')->join('__USERS__ c', 'a.m_uid=c.user_id', 'left')->field('a.m_id,a.m_goods_id,a.mate_content,a.mate_thumb,a.mate_add_time,a.mate_video,b.goods_id,b.goods_name,b.picture,b.price,b.show_price,b.vip_price,b.commission,b.prom_id,b.prom_type,c.user_name,c.user_avat')->where(['a.m_id' => $mid])->find();
		if ($info['goods_id']) {
            if($info['prom_type'] && $info['prom_id'] ){
                $goodsService = new goodsService();
                $active_price = $goodsService->getActivePirce($info['price'],$info['prom_type'],$info['prom_id']);
            }else{
                $active_price = $info['price'];
            }
            $commission = $this->getCom();
            //开启 返利
            if($commission['shop_ctrl'] == 1){
                $f_p_rate = $commission['f_s_rate'];
            }else{
                $f_p_rate = 100;
            }
            $info['dianzhu_price'] = floor($active_price * $info['commission']/ 100 * $f_p_rate)/100;
            $info['vip_price'] = $info['price'] * (1 - $info['commission'] / 100);
            //去掉00
            $info['vip_price'] = sprintf('%0.2f', $info['vip_price']);
            $info['vip_price'] = floatval($info['vip_price']);
            $info['price'] = sprintf('%0.2f', $info['price']);
            $info['price'] = floatval($info['price']);
            $info['show_price'] = sprintf('%0.2f', $info['show_price']);
            $info['show_price'] = floatval($info['show_price']);
            $info['vip_price'] = sprintf('%0.2f', $info['vip_price']);
            $info['vip_price'] = floatval($info['vip_price']);
            $info['dianzhu_price'] = sprintf('%0.2f', $info['dianzhu_price']);
            $info['dianzhu_price'] = floatval($info['dianzhu_price']);
        }
		if($info['mate_add_time']){
			$info['mate_add_time'] = date('Y-m-d H:i', $info['mate_add_time']);
		}
		if($info['mate_thumb']){
            $info['mate_thumb'] = trim($info['mate_thumb'], ',');
			$info['mate_thumb'] = explode(',', $info['mate_thumb']);			
		}

		//是否收藏
		$info['favorite'] = 0;
		if($uid){
			$res = Db::name('favorite')->where(['favor_type' => 2, 'f_uid' => $uid, 'f_goods_id' => $mid])->find();
			if($res){
				$info['favorite'] = 1;
			}
		}
		
		return $info;
	}

	/*
	 * 素材编辑或发布
 	 */
	public function mateEdit($uid, $data = []){
		//处理图片
		// if($data['mate_thumb']){
		// 	$l = strlen($data['mate_thumb']);
		// 	if($data['mate_thumb'][$l - 1] == ','){
		// 		$data['mate_thumb'] = substr($data['mate_thumb'], 0, -1);
		// 	}
		// }
		//编辑
		if($data['m_id']){
			$res = Db::name('users_material')->where('m_id', $data['m_id'])->update($data);
		}
		//发布
		else{
			$data['m_uid'] = $uid;
			$res = Db::name('users_material')->insert($data);
		}
		$res !== false ? 1 : 0;
		if($res === false){
			$res  = -1;
		}
		return $res;
	}

	/*
	 * 素材删除
	 */
	public function mateDel($uid, $mid){
		return Db::name('users_material')->where(['m_id' => $mid, 'm_uid' => $uid])->delete();
	}
	/*
	 * 素材置顶按钮
	 */
	public function sucaiTop($map){
		 $res = Db::name('users_material')->where(['m_id' => $map['m_id'], 'm_uid' =>$map['m_uid']])->find();
		  if($res){
			  $data['mate_zhiding'] =   $res['mate_zhiding']==0?1:0;
			  $res = Db::name('users_material')->where(['m_id' => $map['m_id'], 'm_uid' =>$map['m_uid']])->update($data);
		  }
		 return $res;
	}

	/*
	 * 素材-搜索商品
	 */
	public function mateSearch($uid, $goods_name){
		$this->model = new GoodsM();
		// $list = $this->model->alias('a')->join('__STORE_GOODS__ b', 'b.s_g_goodsid=a.goods_id', 'RIGHT')->field('a.goods_name,a.picture,b.s_g_goodsid')->where(['b.s_g_userid' => $uid, 'b.s_g_isdel' => 0, 'a.goods_name' => ['like', '%'.$goods_name.'%']])->order('b.s_g_addtime desc')->select();
		$list = $this->model->field('goods_name,picture,goods_id,vip_price,commission,price,prom_type,prom_id,active_name')->where(['goods_name' => ['like', '%'.$goods_name.'%'], 'status' => 0])->order('create_time desc')->select();
		if($list){
			foreach($list as $key=>$value){
				$goodsService = new goodsService();
				$active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
				$commission = $this->getCom();
				//开启 返利
				if($commission['shop_ctrl'] == 1){
					$f_p_rate = $commission['f_s_rate'];
				}else{
					$f_p_rate = 100; 
				}
				$value['dianzhu_price'] = floor($active_price * $value['commission']/ 100 * $f_p_rate)/100;
			}
		}
		return $list;
	}

	/*
	 * 个人资料页
	 */
	public function myInfo($uid){
		$field = 'user_id,user_mobile,user_name,user_avat,user_sex,user_birth,user_hobby,user_addr,is_seller,user_sign,id_auth,wx,shop_name,is_vip,vip_end_time,user_account';
		$map = [
			'user_id' => $uid
		];
		
		$user_info = $this->model->where($map)->field($field)->find();
		$userIdauth = Db::name('idauth')->where('auth_uid',$uid)->find();
		//0,未提交；1，未审核；2，通过；3，未通过
		if(!$userIdauth){
			$user_info['id_auth'] = 0;
		}  
		if(!$userIdauth['auth_stat']){
			$user_info['id_auth'] = 0;
		}else{
			$user_info['id_auth'] = $userIdauth['auth_stat'];
		}
		if($user_info['is_seller']){
			$store_info = Db::name('store')->field('s_name,s_thumb,s_grade')->where('s_uid',$user_info['user_id'])->find();
			if($store_info){
				$store_info['s_grade'] = $store_info['s_grade'] == 1 ? '会员店' : ($store_info['s_grade'] == 2 ? '高级店铺' : '旗舰店铺');
			}			
			$user_info['store_info'] = $store_info;
		}
		if($user_info['is_vip']==1){
		    if($user_info['vip_end_time']<time()){
                $user_info['is_vip']=2;
            }
            $user_info['vip_end_time']=date('Y-m-d',$user_info['vip_end_time']);
        }
		return $user_info;
	}

	/*
	 * 个人资料修改 
	 */
	public function myInfoEdit($uid, $data = []){
		$data['wx'] = input('get.wx');
		$u_name = $data['user_name'];
		if($u_name){
			$result = $this->model->where(['user_id' => ['neq', $uid] ,'user_name' => $u_name])->find();
			if($result){
				return -1;
			}
		}

		if(!$data['user_avat']){
			unset($data['user_avat']);
		}

		// //店招图
		// if($data['thumb']){
		// 	$user_info = $this->model->where('user_id', $uid)->field('is_seller')->find();
		// 	if($user_info['is_seller']){
		// 		$store_info = Db::name('store')->where('s_uid', $uid)->field('s_id')->find();
		// 		if(!$store_info){
		// 			return -2;
		// 		}
		// 		Db::name('store')->where('s_uid', $uid)->update(['s_thumb' => $data['thumb']]);
		// 	}
		// }
		// unset($data['thumb']);
			// 资料是否完善
		$info = $this->model->where('user_id', $uid)->field('user_sex,user_birth,user_hobby,user_addr')->find();
		$info_complete1 = 1;
		foreach($info as $v){
			if(!$v){
				$info_complete1 = 0;
				break;
			}
		}
		
		$res = $this->model->where('user_id', $uid)->update($data);
		// 资料是否完善
		$info = $this->model->where('user_id', $uid)->field('user_sex,user_birth,user_hobby,user_addr')->find();
		$info_complete = 1;
		foreach($info as $v){
			if(!$v){
				$info_complete = 0;
				break;
			}
		}
		// 奖励积分
//		if((!$info_complete1) && $info_complete){
//			$info_points = 10;
//			$this->model->where('user_id', $uid)->setInc('user_points', $info_points);
//			$points_log_insert = [
//				'p_uid' => $uid,
//				'point_num' => $info_points,
//				'point_type' => 2,
//				'point_desc' => '完善资料奖励积分',
//				'point_add_time' => time()
//			];
//
//			Db::name('points_log')->insert($points_log_insert);
//		}

		if($res !== false){
			return 1;
		}
		else return 0;
	}

	/*
	 * 猜你喜欢
	 */
	public function mayLike($uid, $p = 1){
		$num = 6;
		$p = $p ? $p : 1; 
		$s = ($p - 1) * $num;
		$goods_model = new GoodsM();
		$goods_field = 'goods_id,goods_name,stock,picture,price,show_price,vip_price,volume,sum_sales,commission,prom_type,prom_id,active_name';
		$goods_list = []; 
		//登录用户
		if($uid){
//			$cate_list = $goods_model->alias('a')->join('__USERS_TRACK__ b', 'a.goods_id=b.track_goods_id')->where('b.t_uid', $uid)->field('distinct(a.category_id)')->select();
//
//			foreach($cate_list as $v){
//				$temp = $goods_model->where(['category_id' => $v['category_id'], 'is_gift' => 0, 'status' => 0])->field($goods_field)->order('volume desc,create_time desc')->limit(2)->select();
//				if($temp){
//					foreach($temp as $val){
//						$goods_list[] = $val;
//					}
//				}
//			}
            $goods_list = $goods_model->where('is_recommend',1)->where('status',0)->field($goods_field)->order('goods_id desc')->select();
			if($goods_list){				
				return ['list' => array_slice($goods_list, $s, $num), 'total' =>count($goods_list)];
			}			
		}
		//未登录用户或足迹为空
		if(!$uid || !$goods_list){
			$total = 30;
			if($p * $num > 30){
				return ['list' => [], 'total' => $total];
			}
			//$goods_list = $goods_model->where(['is_gift' => 0, 'status' => 0])->field($goods_field)->order('volume desc,create_time desc')->limit($s, $num)->select();
            $goods_list = $goods_model->where('is_recommend',1)->where('status',0)->field($goods_field)->select();
			$total = $goods_model->where('is_recommend',1)->where('status',0)->count();
			// $goods_list = $goods_model->field($goods_field)->group('prom_type')->select();
			return ['list' => $goods_list, 'total' => $total];
		}
	}
	/*
	 * 猜你喜欢 (首页)
	 */
	public function mayLikes($uid, $p = 1){
		$num = 5;
		$p = $p ? $p : 1; 
		$s = ($p - 1) * $num;
		$goods_model = new GoodsM();
		$goods_field = 'goods_id,goods_name,stock,picture,price,show_price,volume,vip_price,sum_sales,commission,prom_type,prom_id,active_name,goods_banner';
		$goods_list = []; 
	    $goods_list = $goods_model->where('is_like',1)->where('status',0)->field($goods_field)->select();
        $total = $goods_model->where('is_like',1)->where('status',0)->count();
//		$goods_list = $goods_model->where(['is_gift' => 0, 'status' => 0,'goods_banner'=>['neq','']])->field($goods_field)->order('weigh desc')->limit($s, $num)->select();
//		foreach ($goods_list as $key => $value) {
//			if($value['prom_type']==5){
//				$flash_goods = Db::name('flash_goods')->where(['is_end' => 0,'goods_id'=>$value['goods_id']])->find();
//				if($flash_goods){
//					$flash_goods = Db::name('flash_active')->where(['id' => $flash_goods['flash_id']])->find();
//					if($flash_goods['start_time']<time() && time()<$flash_goods['end_time']	){
//
//					}else{
//					unset($goods_list[$key]);
//					}
//				}else{
//					unset($goods_list[$key]);
//				}
//				// $flash_goods = Db::name('flash_goods')->alias('a')->join('goods b','a.goods_id=b.goods_id')->where(['b.status'=>0,'a.flash_id' => $res['id'],'a.is_end' => 0,'a.goods_id'=>$value['goods_id']])->select();
//
//			}
//		}
		// $total =  $goods_model->where(['is_gift' => 0, 'status' => 0,'goods_banner'=>['neq','']])->count();
		return ['list' => $goods_list, 'total' => $total];
	}
	/*
	 * 猜你喜欢 (首页)
	 */
	public function goodsLikes($uid, $p = 1){
		$num = 4;
		$p = $p ? $p : 1; 
		$s = ($p - 1) * $num +300;
		$goods_model = new GoodsM();
		$goods_field = 'goods_id,goods_name,stock,picture,price,show_price,vip_price,sum_sales,commission,prom_type,prom_id,active_name,goods_banner';
		$goods_list = []; 
		
		$goods_list = $goods_model->where(['is_gift' => 0, 'status' => 0])->where('goods_banner',null)->field($goods_field)->order('weigh desc')->limit($s,$num)->select();
		$total = $goods_model->where(['is_gift' => 0, 'status' => 0])->where('goods_banner',null)->count();
		$total -= 300;
		if($total<=0){
			$total = 0;
		}
		return ['list' => $goods_list, 'total' => $total];
	}
	/*
	 * 后台素材管理
	 */
	public function getSucaiList($limit,$map = array())
    {
        $data = [];
        $count = Db::name('users_material')->where($map)->count();
        $list = Db::name('users_material')->alias('a')->join('__USERS__ b', 'a.m_uid=b.user_id', 'left')->field('a.*,b.user_name,b.user_avat')->order('a.mate_add_time desc')->limit($limit)->where($map)->select();
        $data = ['total'=>$count,'rows'=>$list];
        return $data;
    }
	/*
	 * 后台素材管理(审核状态分类)
	 */
	public function getSucaiStatus($map,$limit)
    {
        $data = [];
        $list = Db::name('users_material')->alias('a')->join('__USERS__ b', 'a.m_uid=b.user_id')->field('a.*,b.user_name,b.user_avat')->order('a.mate_add_time desc')->where($map)->limit($limit)->select();
		 $count =  count($list);
        $data = ['total'=>$count,'rows'=>$list];
        return $data;
    }
	/*
	 * 后台素材管理
	 */
	public function getSucaiShow($map)
    {
        $res = Db::name('users_material')->alias('a')->join('__USERS__ b', 'a.m_uid=b.user_id')->field('a.*,b.user_name,b.user_avat')->find($map);
        return $res;
    }
    /*
     * 后台删除素材
     */
    public function sucaiDel($where)
    {
        return Db::name('users_material')->where($where)->delete();
    }
    /*
     * 后台素材 置顶 显示操作
     */
    public function sucaiEdit($where, $data)
    {
        return Db::name('users_material')->where($where)->update($data);
    }
    /*
     * 获取 商品名称
     */
    public function getGoodsName($goods_id)
    {
        return Db::name('goods')->where(['goods_id' => $goods_id])->value('goods_name');
    }
    /*
     * 获取 商品信息
*/
    public function getGoodsInfo($goods_id, $field= '*')
    {
        return Db::name('goods')->field($field)->where(['goods_id' => $goods_id])->find();
    }
	/*
	 * 搜索界面
	 */
	public function getSearch($user_id){
		$list = [];
		// 热门推荐
        // 按照最近一个月的搜索记录查找 搜索
        $old_time = strtotime("-1 month");
        $recomm = Db::name('search') ->field('count(history_id) num,history_key') -> where(['history_add_time' => ['gt', $old_time]])
            ->group('history_key')->order('num desc')->limit(5)->select();
        if (!empty($recomm)) {
            foreach ($recomm as $value) {
                $list['recomm'][] = $value['history_key'];
            }
        } else {
            $list['recomm'] = ['连衣裙', '手机'];
        }
//        $list['recomm'] = ['连衣裙', '手机'];
		if($user_id){
			$history = Db::name('search')->where('history_uid', $user_id)->field('history_id,history_key')->order('history_add_time desc')->select();
			if($history){
				$list['history'] = $history;
			}
		}
		return $list;
	}

	/*
	 * 搜索历史删除
	 */
	public function searchDel($user_id, $key_id){
		if($key_id == 0){
			$res = Db::name('search')->where('history_uid', $user_id)->delete(true);
		}
		else{
			$res = Db::name('search')->where('history_id', $key_id)->delete();
		}
		return ($res !== false) ? 1 : 0;
	}

   	/*
	 * 获取验证码
	 */
	public function getCode($mobile, $type){
		if(!$mobile){
			return ['status' => 0, 'msg' => '手机号不能为空'];
		}
		$user_info = $this->model->where('user_mobile', $mobile)->field('user_id')->find();
		if($type == 1){
			//检测手机号是否注册			
			if($user_info){
				return ['status' => 0, 'msg' => '手机号已注册'];
			}
		}
		//过期时间，开发者模式5秒,生产模式60秒
		$time = config('app_debug') ? 5 : 60;		
		//检测重复获取
		if(cache($mobile.'_code')){
			return ['status' =>0, 'msg'=>cache($mobile.'_code')+$time-time().'秒后重新获取'];
		}
		//检测ip
		$uip = Request::instance()->ip();
		$where = [
			'sms_mobile' => $mobile,
			'sms_uip' => $uip,
			'sms_add_time' => [
				['gt', strtotime(date('Y-m-d 00:00:00',time()))],
				['lt', strtotime(date('Y-m-d 23:59:59',time()))]
			]
		];
		$sms_info = Db::name('sms_log')->where($where)->count();
		if($sms_info >= 15){
			return ['status' => 0, 'msg' => '同一ip一天只能获取15次验证码'];
		}
		$code = mt_rand(100000, 999999);
//		 发送短信
		 $sms = new SmsService();
		 if($type == 1){
		 	$res = $sms->sendRegister($mobile, $code);
		 }
		 elseif($type == 2){
		 	$res = $sms->sendFind($mobile, $code);
		 }
		 elseif($type == 3){
		 	$res = $sms->sendBind($mobile, $code);
		 }
		 elseif($type == 4){
		 	$res = $sms->sendSetPwd($mobile, $code);
		 }elseif($type == 5){
		     $users = Db::name('users') -> where(['user_mobile' => $mobile])->find();
		     if (empty($users)) {
                 return ['status' => -1, 'msg' => '该手机号未注册'];
             }
		 	$res = $sms->sendLogin($mobile,$code);
		 }elseif ($type == 6){
             $users = Db::name('users') -> where(['user_mobile' => $mobile])->find();
             if (empty($users)) {
                 return ['status' => -1, 'msg' => '该手机号未注册'];
             }
             $res = $sms->sendRegister($mobile, $code);
         }

		//验证码写入session
		if(!session_id()){
			session_start();
		}
		session($mobile.'_code', $code);
		//将时间写入缓存
		cache($mobile.'_code', time(), $time);
		//存入数据库
		$insert = [
			'sms_mobile' => $mobile,
			'sms_code' => $code,
			'sms_type' => $type,
			'sms_add_time' => time(),
			'sms_uip' => $uip
		];
		if($type != 1){
			if(!$user_info['user_id']){
                $insert['sms_uid'] = 0;
			} else {
                $insert['sms_uid'] = $user_info['user_id'];
            }

		}
		Db::name('sms_log')->insert($insert);
		return ['status' => 1, 'code' => $code, 'type' => $user_info?1:0];
	}

   /*
	* 检测验证码
	*/	
	public function checkCode($mobile, $code, $type){
		if(!$mobile || !$code){
			return 0;
		}
		$sms_info = Db::name('sms_log')->where(['sms_mobile' => $mobile, 'sms_type' => $type])->order('sms_add_time desc')->find();
		if($sms_info['sms_add_time'] + 10 * 60 < time()){
			return time();
		}
		elseif($sms_info['sms_code'] == $code){
			// session($mobile.'_code', '');
			return 1;
		}
		else{
			return 0;
		}
	}

	/*
	 * 验证邀请码
	 */
	public function checkInviteCode($invite_code){
		$res = Db::name('users')->where('s_invite_code', $invite_code)->field('user_id')->find();
		if(!$res){
			return false;
		}
		else{
			return $res;
		}
	}

   /*
	* 注册用户
	*/
	public function register($mobile, $password, $invite_uid,$clientId='',$app_system,$shop_name){
		$store_service = new Store();
		$time = Time();
		$ip = Request::instance()->ip();
		$user_name = $this->createName($mobile);
		$user_invite_code = $store_service->createInviteCode();
		Db::startTrans();
		try{
			$user_data = [
				'user_name' => $user_name,
				'user_mobile' => $mobile,
				'user_avat' => '/hetaos.png',
				'user_pwd' => md5('hetao_'.md5($password)),
				'user_sex' => 0,
				'user_account' => 0.00,
				'user_points' => 10,
				'is_seller' => 0,
				'user_reg_time' => $time,
				'user_reg_ip' => $ip,
				'user_last_login' => $time,
				'user_last_ip' => $ip,
				's_invite_code' => $user_invite_code?:"",
				'client_id' => $clientId?:"",
                'app_system' => $app_system?:"",
                'shop_name' =>$shop_name?:""
			];
			Db::name('users')->insert($user_data);
		//	parent::add($user_data);
			$uid = Db::name('users')->getLastInsID();

			$token = $this->createToken($uid);
			if(!$token){
				return false;
			}
			Db::name('users')->where('user_id', $uid)->update(['token' => $token]);
			//加入积分日志
			$log_point = [
				'p_uid' => $uid,
				'point_num' => 10,
				'point_type' => 1,
				'point_desc' => '注册赠送',
				'point_add_time' => time()
			];
			Db::name('points_log')->insert($log_point);
			
			if($invite_uid){
				// 店主邀请
				$user_info = Db::name('users')->where('user_id', $invite_uid)->field('is_seller')->find();
				$tree_info = Db::name('users_tree')->where('t_uid', $invite_uid)->field('t_p_uid,t_g_uid')->find();
				$tree_info = array_merge($user_info,$tree_info);
				if($tree_info['t_p_uid']){
						//增加上下级关系
						$tree_data = [
							't_uid' => $uid,
							't_p_uid' => $tree_info['t_p_uid'],
							't_addtime' => time()
						];
						if($tree_info['t_g_uid']){
							$tree_data['t_g_uid'] = $tree_info['t_g_uid'];
						}						
					}else{
						//增加上下级关系
						$tree_data = [
							't_uid' => $uid,
							't_p_uid' =>$invite_uid,
							't_addtime' => time()
						];					
					}

				Db::name('users_tree')->insert($tree_data);

				//分享赠送优惠券
				$coupon_info_zs = Db::name('coupon')->where('coupon_type',3)->where('status',0)->find();
				if($coupon_info_zs){
					$coupon_data = array(
			            'coupon_id' => $coupon_info_zs['coupon_id'],
			            'c_uid' => $invite_uid,
			            'c_coupon_type'=>$coupon_info_zs['coupon_type'],
			            'add_time' => time(),
			            'c_coupon_title' => $coupon_info_zs['coupon_title'],
			            'c_coupon_type' => $coupon_info_zs['coupon_type'],
			            'c_coupon_price' => $coupon_info_zs['coupon_price'],
			            'c_coupon_buy_price' => $coupon_info_zs['coupon_use_limit'],
			            'coupon_type_id' => $coupon_info_zs['coupon_type_id'],
			            'coupon_aval_time' => $coupon_info_zs['coupon_aval_time'],
			            'coupon_stat' => 1,
			        );
			        $res = Db::name('coupon_users')->insert($coupon_data);
				}
              
              
				
				
//				//增加积分
//				Db::name('users')->where('user_id', $invite_uid)->setInc('user_points', 10);
//				$point_insert = [
//					'p_uid' => $invite_uid,
//					'point_num' => 10,
//					'point_type' => 5,
//					'point_desc' => '邀请好友注册VIP',
//					'point_add_time' => time(),
//				];
//				Db::name('points_log')->insert($point_insert);
//				//增加元宝
//				$yinzi = new Yinzi();
//				$yinzi->addYinzi($invite_uid, 1, 5);	//店主
//				$yinzi->addYinzi($uid, 2, 5);	//新会员

				//更换邀请码				
				// $invite_code = $store_service->createInviteCode();
				// Db::name('users')->where('user_id', $invite_uid)->update(['s_invite_code' => $invite_code]);
			}
          
          //赠送新人优惠券
				$coupon_info = Db::name('coupon')->where('coupon_type',2)->find();
				if($coupon_info){
					$coupon_data = array(
			            'coupon_id' => $coupon_info['coupon_id'],
			            'c_uid' => $uid,
			            'c_coupon_type'=>$coupon_info['coupon_type'],
			            'add_time' => time(),
			            'c_coupon_title' => $coupon_info['coupon_title'],
			            'c_coupon_type' => $coupon_info['coupon_type'],
			            'c_coupon_price' => $coupon_info['coupon_price'],
			            'c_coupon_buy_price' => $coupon_info['coupon_use_limit'],
			            'coupon_type_id' => $coupon_info['coupon_type_id'],
			            'coupon_aval_time' => $coupon_info['coupon_aval_time'],
			            'coupon_stat' => 1,
			        );
			        $res = Db::name('coupon_users')->insert($coupon_data);
				}
          
			Db::commit();
			return ['uid' => $uid, 'token' => $token, 'is_seller' => 0];
		}
		catch(\Exception $e){
			Db::rollback();
			return $e->getMessage();
			// return false;
		}
	}

   /*
	* 找回密码
	*/
	public function resetPwd($mobile, $password){
		$map['user_mobile'] = $mobile;
		$data['user_pwd'] = md5('hetao_'.md5($password));
		$res = $this->save($map, $data);
		if($res){
			return SUCCESS;
		}else{
			return REGISTER_ERROR;
		}		
	}

   /*
	* 登录
	*/
	public function login($mobile, $password,$type){
		$info = parent::find(['user_mobile' => $mobile]);
		if(!$info){
			return -1;
		}
		if(empty($info['user_pwd'])){
			return -3;
		}
		if ($type == 1) {
            if($info['user_pwd'] != md5('hetao_'.md5($password))){
                return -2;
            }
        }
		$token = $info['token'];
		if(!$token){
            $token = $this->createToken($info['user_id']);
		}
		$update = [
			'user_last_login' => time(),
			'user_last_ip' => Request::instance()->ip(),
			'user_login_times' => $info['user_login_times'] + 1,
			'token' => $token,
		];
		parent::save(['user_id' => $info['user_id']], $update);
		$user_info = parent::find(['user_id' => $info['user_id']]);
		return $user_info;
	}	
	/*
	* 获取手机  型号
	*/
	public function clientId($mobile, $password,$clientId, $app_system){
		$info = parent::find(['user_mobile' => $mobile]);
		if(!$info){
			return USER_EMPTY;
		}
		if($info['user_pwd'] != md5('hetao_'.md5($password))){
			return PASSWORD_ERROR;
		}
		$user_info = parent::find(['user_id' => $info['user_id']]);
		if($user_info['client_id'] != $clientId){
			//告知 之前登录的退出
			$msg = [
				'content'=>' 您的账号已经在其他设备上登录，非您操作请及时修改密码！',//透传内容
				'title'=>'欢迎登录九州',//通知栏标题
				'text'=>'九州欢迎您！',//通知栏内容
				'curl'=> request()->domain(),//通知栏链接
			];
			 $data=array(
				0=>['client_id'=>$user_info['client_id']],
				'system'=>$app_system,//1为ios
			);
			$Pushs = new Pushs();
			$Pushs->getTypes($msg,$data);
			
			
			$res = Db::name('users')->where('user_id',$info['user_id'])->update(array('client_id'=>$clientId, 'app_system' => $app_system));
			if($res == false){
				return 1;
			}
			return 1;
		}
		return 0;
	}	
	/*
	* 获取手机  型号
	*/
	public function clientIds($mobile,$clientId, $app_system){
		$info = parent::find(['user_mobile' => $mobile]);
		if(!$info){
			return USER_EMPTY;
		}
		 
		$user_info = parent::find(['user_id' => $info['user_id']]);
		if($user_info['client_id'] != $clientId){
			//告知 之前登录的退出
			$msg = [
				'content'=>' 您的账号已经在其他设备上登录，非您操作请及时修改密码！',//透传内容
				'title'=>'欢迎登录九州',//通知栏标题
				'text'=>'九州欢迎您！',//通知栏内容
				'curl'=>request()->domain(),//通知栏链接
			];
			 $data=array(
				0=>['client_id'=>$user_info['client_id']],
				'system'=>$app_system,//1为ios
			);
			$Pushs = new Pushs();
			$Pushs->getTypes($msg,$data);
			
			
			$res = Db::name('users')->where('user_id',$info['user_id'])->update(array('client_id'=>$clientId, 'app_system' => $app_system));
			if($res == false){
				return 1;
			}
			return 1;
		}
		return 0;
	}	

   /*
	* 生成token
	*/
	public function createToken($uid){
		$token = md5(md5($uid).time());
		// $res = parent::save(['user_id' => $uid], ['token' => $token]);
		return $token;
	}

   /*
	* 获取推荐列表
	*/
	public function getRecommendList($uid){
		//获取推荐人
		$map['pid']=$uid;
		$data=parent::paginate($map);
		//获取推广盈利
		$PointLogService=new PointLogService();
		foreach ($data as &$value) {
			$map=[
				'uid'=>$uid,
				'action_uid'=>$value['id']
			];
			$value['point']=$PointLogService->sum($map,'num');
			$value['register_time']=date('Y-m-d');
		}
		return $data;
	}

   /*
	* 提现操作
	*/
	public function withdrawals($uid,$data){
		$arr['uid']=$uid;
		$arr['point']=$data['point'];
		$arr['ali_id']=$data['ali_id'];
		$arr['create_time']=time();
		$arr['status']=0;
		// 启动事务
		Db::startTrans();
		try{
			//记录提现信息
			$id=Db::name('withdrawals')->insertGetId($arr);
			//扣除用户积分
			Db::name('user')->where('id',$uid)->setDec('point',$data['point']);
			//记录积分日志
			$point_data=[
				'uid'=>$uid,
				'object_id'=>$id,
				'num'=>$data['point'],
				'create_time'=>time(),
				'type'=>2
			];
			Db::name('point_log')->insert($point_data);
		    // 提交事务
		    Db::commit();			
			return $id;
		} catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    return ERROR;			
		}
	}

	/*
	* 获取推广员信息
	*/
	public function getParents($uid){
		//一级推广员
		$map['id']=$uid;
		$parent1=parent::find($map);
		if(!$parent1['pid']){
			return false;
		}
		$data=[];
		array_push($data,['level'=>1,'uid'=>$parent1['pid']]);
		//二级推广员
		$map['id']=$parent1['pid'];
		$parent2=parent::find($map);
		if(!$parent2['pid']){
			return $data;
		}
		array_push($data,['level'=>2,'uid'=>$parent2['pid']]);
		//三级推广员
		$map['id']=$parent2['pid'];
		$parent3=parent::find($map);
		if(!$parent3['pid']){
			return $data;
		}
		array_push($data,['level'=>3,'uid'=>$parent3['pid']]);
		return $data;
	}

	/*
	* 修改密码
	*/
	public function changePassword($password_old,$password){
		$map['user_id']=session('id');
		$info=$this->find($map);
		if($info['user_pwd']!=md5('hetao_'.md5($password_old))){
			return PASSWORD_ERROR;
		}
		$data['user_pwd']=$password;
		return $this->save($map,$data);
	}

	/*
	 * 更改账户信息
 	 */
	public function changeAcco($uid, $userUpdate, $logInsert){
		$res = $this->model->where('user_id', $uid)->update($userUpdate);
		if($res){
			$res = Db::name('account_log')->insert($logInsert);
		}
		return $res;
	}
	/*
	 * 实名认证账户信息
 	 */
	public function Auth($uid,$authInsert=[]){
		$result = $this->model->where(['user_id' => $uid,'id_auth' =>1])->find();
		if($result){
			return -1; //已经实名认证 
		}
		/* $userdata = ['id_auth'=>1];
		$res = $this->model->where('user_id', $uid)->update($userdata); *///会员认证信息 改为审核中
		$data = $this->model->where('user_id',$uid)->find(); 
		$auth_id  = Db::name('idauth')->where('auth_uid',$uid)->value('auth_id');
		$authInsert['auth_uname'] = $data['user_name'];//会员昵称 添加 
		if($auth_id){
			$res = Db::name('idauth')->where('auth_id',$auth_id)->update($authInsert);
		}else{
			$res = Db::name('idauth')->insert($authInsert);
		}
		return $res;
	}
	/*
	 * 实名认证账户信息展示
 	 */
	public function showAuth($uid){
		$result = Db::name('idauth')->where('auth_uid',$uid)->find();      
		return $result;
	}
	/*
	 * 用户提现
 	 */
	public function Cash($uid,$data=[],$accountlog=[]){
		//提现金额是否超出账户余额
		$result = $this->model->where(['user_id'=>['eq',$uid],'user_account'=>['egt',$data['cash_amount']]])->find(); 
		$no = $this->createCashNo();
		if(!$result){
			return -1;
		}
		$data['cash_no'] = $no;
	    $data['cash_uname']=$result['user_name'];//会员昵称 添加
		$res = Db::name('cash')->insert($data);
		if($res){
			$cash_id = Db::name('cash')->getLastInsID();
			$accountlog['acco_type_id']=$cash_id;
			$res = $this->model->where(['user_id'=>['eq',$uid]])->setDec('user_account',$data['cash_amount']);//账户余额减少
			Db::name('account_log')->insert($accountlog);//账户明细记录
		}
		return $res;
	}
    /*
     * 生成提现编号
     */
    public function createCashNo(){
        $no = 'CN'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $check = Db::name('cash')->where('cash_no', $no)->field('cash_id')->find();
        while($check){
            $no = $this->createCouponNo();
        }
        return $no;
    }
    /*
     *  提现信息展示
      */
	public function showCash($uid){
		$result = Db::name('cash')->where('cash_uid',$uid)->find();      
		return $result;
	}
	/*
	 *  我的充值卡
 	 */
	public function showCard($uid){
		$row = Db::name('user_rc')->where(['card_uid' => $uid, 'card_stat' => ['neq',2]])->select();
		foreach ($row as $key => $value) {
			if($value['card_end_time']<time()){
				Db::name('user_rc')->where(['card_uid' => $uid, 'card_id' => $value['card_id']])->update(['card_stat'=>4]);	
				$value['card_stat']=4;			
			}
		}
		$row = Db::name('user_rc')->where(['card_uid' => $uid, 'card_stat' => ['neq',2]])->order('card_stat,card_end_time desc')->select();
		return $row;
	}
	/*
	* 修改密码  09c9f99c3fb1dcc08a64a088dcc97a45
	*/
	public function editPassword($uid,$password_old,$password){
		 
		$map['user_id']=$uid;
		$info=$this->find($map);

		if($info['user_pwd']!=md5('hetao_'.md5($password_old))){
			return -1;
		}
       
		$data['user_pwd'] = $password;
		if(!$data['user_pwd']){
			unset($data['user_pwd']);
		}else{
			$data['user_pwd']= md5('hetao_'.md5($data['user_pwd']));
		}
		return $this->save($map,$data);
	}
	/*
	* 意见反馈
	*/
	public function Feedback($data=[]){
		$res = Db::name('opinion')->insert($data); 
		return $data;
	}
	/*
	* 意见反馈
	*/
	public function FeedbackType(){
		$rows = Db::name('opinion_type')->order('op_t_order desc')->select(); 
		return $rows;
	}
	/*
	* 帮助中心 根据权重排序
	*/
	public function helpCenter($category_name='帮助中心'){
		$res = Db::name('content_category')->where('category_name',$category_name)->find(); 
		if($res){
			$res = Db::name('content')->field('content_id,title,create_time,content')->where(['category_id'=>['eq',$res['category_id']],'status'=>['eq','normal']])->order('weigh desc')->select(); 
		}
		return $res;
	}
	/*
	* 中心 根据权重排序
	*/
    public function getCenter($category_name='公告消息'){
        $category_id = Db::name('content_category')->where('category_name',$category_name)->value('category_id');
        $res = Db::name('content')
            ->field('content_id,title,keywords,FROM_UNIXTIME(create_time,"%Y-%m-%d %H:%i:%s") as createtime,picture as img,content')
            ->where('category_id',$category_id)
            ->order('createtime desc,weigh desc')
            ->select();
        return $res;
    }
	/*
	* 内容消息列表
	*/
	public function getContentList($category_name='活动消息'){
		$res = Db::name('content_category')->where('category_name',$category_name)->field('category_id,category_name,description')->find();
		if($res){
			$list = Db::name('content')->field('title,create_time,content,picture,content_id,description')->where(['category_id'=>['eq',$res['category_id']],'status'=>['eq','normal']])->order('weigh desc')->select();
			if($list){
				foreach($list as $key=>$val){
					$list[$key]['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
				}
			}
			$res['list'] = $list;
		}
		return $res;
	}
	/*
	* 内容消息列表
	*/
	public function getMessageList($category_id='24'){
		$res = Db::name('content_category')->where('category_id',$category_id)->field('category_id,category_name,description')->find(); 
		if($res){
			$list = Db::name('content')->field('content_id,title')->where(['category_id'=>['eq',$res['category_id']],'status'=>['eq','normal']])->order('weigh desc')->select(); 
			$res['list'] = $list;
		}
		return $res;
	}	
	/*
	*  客服消息 获取消息分类列表
	*/
	public function getTypeList($category_name='常见问题'){
		$res = Db::name('service_category')->field('category_id,category_name')->where('category_name',$category_name)->find(); 
		if($res){
			$list = Db::name('service_category')->field('category_id,category_name')->where([' pid'=>['eq',$res['category_id']],'status'=>['eq','normal']])->order('weigh desc')->select();
		}
		$res['list'] = $list;
		return $res;
	}
	/*
	*  客服消息 获取消息分类列表
	*/
	public function getCenterList($category_id='0'){
		if($category_id == 0){
			$where=[
			'category_name' => '热门问题',
			];
		}else{
			$where = [
			'category_id' => $category_id,
			];
		}
		$res = Db::name('service_category')->field('category_id,category_name')->where($where)->find(); 
		if($res){
			$list = Db::name('service')->field('content_id,title,content,create_time')->where('category_id',$res['category_id'])->select(); 
		}
		$res['list'] = $list;
		return $res;
	}	
	/*
	*  客服消息 获取消息文章
	*/
	public function getMatter($content_id = '1'){
	 	$map['content_id'] = $content_id;
		Db::name('service')->where($map)->setinc('click');
		$res = Db::name('service')->field('content_id,title,content,create_time')->find($map);
		
		return $res;
	}
	/*
	*  客服消息 获取常见问题列表
	*/
	public function getFamiliar(){
		$res = Db::name('service')->field('content_id,title,create_time')->limit(6)->order('click desc')->select();
		return $res;
	}	
	/*
	* 帮助中心 文章阅读
	*/
	public function helpRead($content_id=''){
		$res = Db::name('content')->field('content,title')->where('content_id',$content_id)->find(); 
		return $res;
	}
	/*
	* 关于我们  电话 版本 邮箱 需要调用（暂未处理）
	*/
	public function aboutUs($category_name='关于我们'){
	    $config_data = Db::name('config')->field('company_name,site_name,site_logo,version,app_email,app_tell,call_us')->find();
		$data = [
			'site_name' => $config_data['site_name'],
			'site_logo' => $config_data['site_logo'],
			'cs_line' => $config_data['app_tell'],
			'cs_email' =>$config_data['app_email'],
			'site_version' =>$config_data['version'],
			'copyright' =>$config_data['company_name'].date('Y', time()),
			'call_us' =>$config_data['call_us'],
		];
		// $res = Db::name('content_category')->where('category_name',$category_name)->find(); 
		// if($res){
		// 	$res = Db::name('content')->field('title,content,keywords,description')->where(['category_id'=>['eq',$res['category_id']],'status'=>['eq','normal']])->limit(1)->order('update_time desc')->select(); 
		// 	$res[0]['email'] = Db::name('config')->value('app_email');
		// 	$res[0]['tell'] = Db::name('config')->value('app_tell');
		// }
		return $data;
	}
	/*
	* 应用设置
	*/
	public function appSetting($uid){
		$result = Db::name('user_setting')->where('uset_uid',$uid)->select();
		$rows = Db::name('app_setting')->where('set_status',0)->select();
		if($rows){
			foreach($rows as $val){
				//有更新的配置插入到用户信息表
				$res = Db::name('user_setting')->where(['uset_uid'=>['eq',$uid],'uset_id'=>['eq',$val['set_id']]])->select();
					if(!$res){
						$data = [
						'uset_uid'=>$uid,
						'uset_id'=>$val['set_id'],
						'uset_name'=>$val['set_name'],
						'uset_status'=>0,
					];
					Db::name('user_setting')->insert($data); //插入到用户设置信息表
				}
			}
		}
		//若配置删除 则更新用户设置信息表
		if($result){
			foreach($result as $value){
				$res = Db::name('app_setting')->where('set_id',$value['uset_id'])->select();
				if(empty($res)){
					Db::name('user_setting')->where('uset_id',$value['uset_id'])->delete();
				}
			}
		}
		$res = Db::name('user_setting')->where('uset_uid',$uid)->select();
		return $res;
	}
    /**
     * 获取版本号
     */
    public function getVersion()
    {
        return Db::name('config')->value('version');
    }
	/*
	* 应用设置修改
	*/
	public function appSetEdit($uid,$uset_id,$status){
		$status = $status == 0 ? 1:0; 
		$rows = Db::name('user_setting')->where('uset_uid',$uid)->select();
		foreach($rows as $val){
			if($uset_id == $val['uset_id']){
				$res = Db::name('user_setting')->where(['uset_uid'=>['eq',$uid],'uset_id'=>['eq',$uset_id]])->update(['uset_status'=>$status]);
			}
		}
		return $res;
	}	

	/*
	 * 用户初始昵称
	 */
	public function createName($mobile){
		$name = '车匙汇'.substr($mobile, -4);
		$check = Db::name('users')->where('user_name', $name)->field('user_id')->find();
		while($check){
			$name = '车匙汇'.mt_rand(1000, 9999);
			$check = Db::name('users')->where('user_name', $name)->field('user_id')->find();
		}
		return $name;
	}

	/*
	 * 账户余额变更
	 */
	public function changeAccount($uid, $type, $num,$order_id=0){
		if($uid){
			Db::startTrans();
			try{
				if($num >= 0){
					Db::name('users')->where('user_id', $uid)->setInc('user_account', $num);
				}
				else{
					Db::name('users')->where('user_id', $uid)->setDec('user_account', abs($num));
				}
				$log_insert = [
					'a_uid' => $uid,
					'acco_num' => $num,
					'acco_type' => $type,
					'acco_time' => time(),
                    'order_id'=>$order_id
				];
				switch($type){
					case 1 : $log_insert['acco_desc'] = '余额提现'; break;
					case 2 : $log_insert['acco_desc'] = '支付了订单'; break;
					case 4 : $log_insert['acco_desc'] = '购物返利'; break;
					case 6 : $log_insert['acco_desc'] = '购买优惠券'; break;
					case 9 : $log_insert['acco_desc'] = '邀请开店返利'; break;
				}
				Db::name('account_log')->insert($log_insert);
				Db::commit();
				return true;
			}
			catch(\Exception $e){
				Db::rollback();
//				var_dump($e->getMessage());die;
				return false;
			}
		}
	}
	/*
	 * 获取在线消息
	 */
	public function getOnline($uid){
		 $user = Db::name('users')->where('user_id',$uid)->field('user_name,user_avat')->find();
		 $res = Db::name('msg')->where('uid',$uid)->select();
		 if($res){
			 foreach($res as $val){
				 $val['user_avat'] = $user['user_avat'];
				 $val['user_name'] = $user['user_name'];
			 }
		 }
		 return $res;
	}
	/*
	 * 发送在线消息
	 */
	public function sendOnline($data){
		$res = Db::name('msg')->insert($data);
		return $res;
	}
	/**
     * 订单商品列表
     * where
     */
	public function getOrderGoodsList($where = [], $limit = 10000)
    {
        $list = Db::name("order_goods")
            ->alias('a')
            ->field('a.og_id,a.og_freight,a.og_goods_id,a.og_goods_spec_val,a.og_goods_price,a.order_goods_ok_time,a.og_supplier_id,a.og_goods_name as goods_name,a.og_remark,a.og_goods_num,a.og_goods_spec_id,a.og_status,b.order_no,b.order_status')
            ->join("__ORDER__ b", "b.order_id = a.og_order_id")
            ->where($where)
            ->limit($limit)
            ->order('a.og_goods_id desc')
            ->select();
        return $list;
    }
    /**
     * 订单商品数量
     * where 按供应商 筛选
     */
    public function getOrderGoodsCount($where = [])
    {
        $count = Db::name("order_goods")
            ->alias('a')
            ->field('a.og_id')
            ->join("__ORDER__ b", "b.order_id = a.og_order_id")
            ->where($where)
            ->order('a.og_goods_id')
            ->count();
        return $count;
    }
    /*
     * 结算单统计
     * $type true 查询 false 统计
     *
     */
    public function getJesuanDan($where=[], $field='*',$limit = '1000', $type = false, $group = [])
    {
//        $sql = Db::name('settlement_val')->alias('a')->field($field)
//            ->join('__ORDER_GOODS__ b', 'a.ordergoods_id = b.og_id')
//            ->where($where)->group($group)->limit($limit);
        $sql = Db::name('settlement')->field($field)->where($where)->limit($limit);
        if ($type) {
            return $sql->select();
        } else {
            return $sql ->count();
        }
    }
    /*
     * 结算单 查看详情
     */
    public function getJesuanDaninfo($where=[], $field='*')
    {
        $og_ids = Db::name('settlement')->where($where)->value('ordergoods_ids');
        $data = Db::name('order_goods')->where(['og_id' => ['in', $og_ids]])->select();
        return $data;
    }
    /*
     * 结算单统计----历史记录
     * $type true 查询 false 统计
     *
     */
    public function getJesuanDanHis($where=[], $field='*',$limit = '1000', $type = false)
    {
        $sql = Db::name('settlement')->field($field)->where($where)->limit($limit);
        if ($type) {
            return $sql->select();
        } else {
            return $sql ->count();
        }
    }
    /**
     * 获取价格
     */
    public function getSkuInfo($sku_id)
    {
        return Db::name('goods_sku')->where(['sku_id' =>$sku_id])->find();
    }

    /**
     * 获取供应商名称
     */
    public function getSupplier($sid)
    {
        return Db::name('supplier')->where(['id' =>$sid])->value('supplier_title');
    }
    /**
     * 获取供应商信息
     */
    public function getSupplierInfo($where, $field = '*')
    {
        return Db::name('supplier')->field($field)->where($where)->find();
    }
    /**
     * 获取供应商列表
     */
    public function getSupplierList($where,$limit)
    {
        return Db::name('supplier')->where($where)->limit($limit)->select();
    }
    /**
     * 获取供应商结算方式
     */
    public function getSupplierJiesuan($where)
    {
        return Db::name('supplier')->where($where)->value('jiesuan');
    }
    /**
     * 获取供应商数量
     */
    public function getSupplierCount($where)
    {
        return Db::name('supplier')->where($where)->count();
    }
	/*
	 * 设置支付密码
	 */
	public function setPaypwd($mobile, $pwd){
		$user_info = $this->model->where('user_mobile', $mobile)->field('user_id')->find();
		if(!$user_info){
			return ['code' => 0, 'msg' => '用户不存在'];
		}

		$pwd = md5('hetao'.md5($pwd).$mobile);

		$result = $this->model->where('user_id', $user_info['user_id'])->update(['user_pay_pwd' => $pwd]);
		if($result !== false){
			return ['code' => 1, 'msg' => '修改成功'];
		}
		else return ['code' => 0, 'msg' => '修改失败'];
	}

	/*
	 * 修改支付密码 
	 */
	public function resetPaypwd($mobile, $old_pwd, $pwd){
		$user_info = $this->model->where('user_mobile', $mobile)->field('user_id,user_pay_pwd')->find();
		if(!$user_info){
			return ['code' => 0, 'msg' => '用户不存在'];
		}

		if(md5('hetao'.md5($old_pwd).$mobile) != $user_info['user_pay_pwd']){
			return ['code' => 0, 'msg' => '原支付密码填写错误'];
		}

		$pwd = md5('hetao'.md5($pwd).$mobile);

		$result = $this->model->where('user_id', $user_info['user_id'])->update(['user_pay_pwd' => $pwd]);
		if($result !== false){
			return ['code' => 1, 'msg' => '修改成功'];
		}
		else return ['code' => 0, 'msg' => '修改失败'];
	}

	/*
	 * 验证支付密码
	 */
	public function checkPayPwd($uid, $pwd){
		$user_info = $this->model->where('user_id', $uid)->field('user_pay_pwd,user_mobile')->find();
		if(md5('hetao'.md5($pwd).$user_info['user_mobile']) != $user_info['user_pay_pwd']){
			return ['code' => 0, 'msg' => '密码错误'];
		}	 
		return ['code' => 1, 'msg' => '密码正确'];
	}

	/* 
	 * 我的充值卡
	 */
	public function myRecharge($uid, $p){
		$where = [
			'card_uid' => $uid,
			'card_stat' => 1
		];

		$num = 10;
		$s = ($p - 1) * $num;
		$list = Db::name('user_rc')->field('card_id,card_no,card_add_time,card_title,card_thumb,card_price,card_balance')->where($where)->order('card_add_time desc')->limit($s, $num)->select();
		foreach($list as &$v){
			// switch($v['card_stat']){
			// 	case 1 : $v['card_stat'] = '未使用'; break;
			// 	case 2 : $v['card_stat'] = '已使用'; break;
			// 	case 3 : $v['card_stat'] = '已转赠'; break;
			// 	case 4 : $v['card_stat'] = '已过期'; break;
			// }
			$v['card_add_time'] = $v['card_add_time'] ? date('Y-m-d', $v['card_add_time']) : '';
		}
		$arr['list'] = $list;
		$arr['total'] = Db::name('user_rc')->where($where)->count();

		return ['code' => 1, 'data' => $arr];
	}

	/* 
	 * 我的元宝
	 */
	public function myYz($uid, $p, $type){
		$where = [
			'yin_uid' => $uid,
		];
		if($type == 1){
			$where['yin_stat'] = 2;
		}
		else{
			$where['yin_stat'] = ['neq', 2];
		}
		$num = 10;
		$s = ($p - 1) * $num;
		$list = Db::name('yinzi')->field('yin_id,yin_no,yin_amount,yin_desc,yin_stat,yin_add_time,yin_die_time')->where($where)->order('yin_die_time asc')->limit($s, $num)->select();
		foreach($list as &$v){
			switch($v['yin_stat']){
				case 1 : $v['yin_stat'] = '未生效'; break;
				case 2 : $v['yin_stat'] = '未使用'; break;
				case 3 : $v['yin_stat'] = '已使用'; break;
				case 4 : $v['yin_stat'] = '已过期'; break;
				case 5 : $v['yin_stat'] = '已赠送'; break;
			}
			$v['yin_add_time'] = $v['yin_add_time'] ? date('Y.m.d H:i', $v['yin_add_time']) : '';
			$v['yin_die_time'] = $v['yin_die_time'] ? date('Y.m.d H:i', $v['yin_die_time']) : '';
		}
		$arr['list'] = $list;
		$arr['total'] = Db::name('yinzi')->where($where)->count();

		return ['code' => 1, 'data' => $arr];
	}
	 /**
     * @param $uid
     * 手机型号
     */
    public function getClient($uid){
        $client_id=Db::name("users")->where('user_id',$uid)->value("client_id");
        return $client_id;
    } 
	/**
     * @param $uid
     * 验证支付密码
     */
    public function isPayPwd($uid){
        $res = Db::name("users")->where('user_id',$uid)->value("user_pay_pwd");
        return $res;
    }
	/**
     * @param $uid
     * 解绑微信
     */
    public function untie($uid,$type=2){
		if($type == 1){
			$res = Db::name("users")->where('user_id',$uid)->update(array('openid'=>0, 'user_qq' => ''));
		}elseif($type == 2){
			$res = Db::name("users")->where('user_id',$uid)->update(array('unionid'=>0, 'user_wx' => ''));
		}
        return $res;
    }
	/**
     * @param $uid
     * 获取上级id
     */
    public function getsuperior($uid){
		$res = Db::name("users_tree")->where('t_uid',$uid)->field('t_p_uid')->find();
		if(!$res){
			return 0;
		}else{
			return $res['t_p_uid'];
		}
	
	}
	/**
     * @param $mobile
     * 检测手机号
     */
    public function checkMobile($mobile){
		$res = Db::name("users")->where('user_mobile',$mobile)->field('is_seller,user_name,user_id,user_mobile,user_avat,user_truename')->find();
		if(!$res){
			return -1;
		}else if($res['is_seller'] == 1){
			return 0;
		}
		if(!$res['user_avat']){
			$res['user_avat'] = '';
		}
		if(!$res['user_truename']){
			$res['user_truename'] = '';
		}
		return $res;
			
	}
     
    /**
     * 检测该用户是否是已经注册的用户
     */
    public function checkPhone($mobile)
    {
        $res = Db::name("users")->where('user_mobile',$mobile)->field('user_id')->find();
        if ($res) {
            return true;
        } else {
            return false;
        }
    }
	/**
     * @param $map $limit
     * 获取素材分类
	 1素材分类2话题分类
     */
    public function mateCat($type){
		$map = ['status'=>'normal',];
		if($type){
			$map = ['type'=>$type];
		}
		$list = Db::name('material_category')->where($map)->field('cat_id,cat_name,type')->select();
		return $list;
	}
	/**
     * @param $map $limit
     * 获取素材分类
     */
    public function getSucaiCat($map,$limit){
		$data = [];
        $list = Db::name('material_category')->where($map)->limit($limit)-> select();
		$count = Db::name('material_category')->where($map)->count();
        $data = ['total'=>$count,'rows'=>$list];
        return $data;
	}
	/**
     * @param $type $topicId
     *  点赞
     */
    public function giveLike($uid,$topicId,$type){
		$data=[
			'l_uid'=>$uid,
			'l_type'=>$type,
			'l_topic_id'=>$topicId,
		];
		$result = Db::name('like')->where($data)->find();
		if($result){
			return -1;
		}
		$data['l_add_time'] = time();
		$res = Db::name('like')->insert($data);
		$res = 1;
		if($res){
			//话题
			if($type==2){
				$res = Db::name('users_material')->where(array('m_id'=>$topicId,'m_type'=>2))->setInc('m_like');				
			//明星专访
			}else if($type==1){
				$res = Db::name('topic')->where('tp_id',$topicId)->setInc('tp_like');
			}
			return $res;
		}else{
			return 0;
		}
		
	}
    /*
    * 获取验证码
    */
    public function sendMsg($mobile, $name, $money, $type){
        if(!$mobile){
            return ['status' => 0, 'msg' => '手机号不能为空'];
        }
        // 发送短信
        $sms = new SmsService();
        if ($type) {
            $sms->sendTxcgMsg($mobile, $name, $money);
        } else {
            $sms->sendTxsbMsg($mobile, $name, $money);
        }
    }
	/*
    * 商品券商品
	*coupon_type_id 商品券为商品id，专区卷为活动专区id
    */
    public function goodsCoupon($coupon_id,$p){
		$coupon = Db::name('coupon_users')->where('c_id',$coupon_id)->field('coupon_id,c_coupon_type,coupon_type_id')->find();
		if(!$coupon){
			return 0; 
		}
		//优惠券类型：1，商品券；2，专区券；3，全场券
		$active_id = $coupon['coupon_type_id'];
		$disabled = $coupon['disabled'];//不可用分类id
		if($coupon['c_coupon_type'] == 1){
			$where = [
				'status'=>0,
				'goods_id'=>$active_id,
			];
		}else if($coupon['c_coupon_type'] == 2){
			if($active_id){
			$where = [
				'status'=>0,
				'prom_type'=>$active_id,
			];
			}
		}elseif($coupon['c_coupon_type'] == 3){
			if($disabled){
				$where = [
					'status'=>0,
					'category_id'=>['not in',$disabled],
				];
			}else{
			$where = [
					'status'=>0,
				];	
			}
		}
		$list = $this->getgoodsCoupon($where,$p);
		return $list;
	}
	/*
    * 获取商品
    */
    public function getgoodsCoupon($where,$p){
		$num = 20;
		$p = $p ? $p : 1;
		$s = ($p - 1) * $num;
		$goodsInfo = Db::name('goods')
			->field('goods_id,goods_name,category_id,price,stock,picture,volume,status,recommend,show_price,commission,prom_type,prom_id,goods_banner,active_name,active_state')
			->where($where)
			->limit($s,$num)
			->select();
		foreach($goodsInfo as $key=>$val){
			$goodsInfo[$key]['dianzhu_price'] = floor($val['price'] * $val['commission'])/ 100;
			 //$GoodsModel=new GoodsService();
			//$label_title = $GoodsModel->getActive_label($val['prom_type']);
			$active_info = Db::name('active_type')->where(array('id' => $val['prom_type']))->find();
				if($active_info){
					$goodsInfo[$key]['active_name']= $active_info['active_type_name'];
				}
		}
	
		return $goodsInfo;
	}
	/*
    * 获取客服信息
    */
    public function getKfInfo($kefu_id){
		$info = Db::name('kefu')
			->alias('a')
			->join('admin b','a.kefu_id = b.kf_id', 'left')
			->field('a.*,b.admin_id,b.kf_id,b.u_id')
			->where('a.kefu_id',$kefu_id)
			->find();
		return $info;
	}
	/*
    * 修改用户客服
    */
    public function editInfo($user_id,$is_kefu=0){
		$where=[
			'user_id'=>$user_id
		];
		$data = [
			'is_kefu'=>$is_kefu
		];
		$res = Db::name('users')->where($where)->update($data);
		return $res;
	}	
	/*
    * 签到提醒
    */
    public function singRemind($uid,$client_id='',$app_system=''){
		$userInfo = Db::name('users')
			->where('user_id',$uid)
			->field('client_id,app_system,sing_remind')
			->find();
		if($client_id){
			$userInfo['client_id'] = $client_id;
		}
		if($app_system){
			$userInfo['$app_system'] = $app_system;
		}
		if($userInfo['sing_remind']==0){
			$s_year = date('Y');
			$s_month = date('m');
			$s_day = date('d');
			$where=[
				's_year'=>$s_year,
				's_month'=>$s_month,
				's_day'=>$s_day,
				's_uid'=>$uid,
			];
			$user_log = Db::name('signin_log')
				->where($where)
				->field('s_signin_num')
				->find();
			if(!$user_log){
				$where=[
					's_year'=>$s_year,
					's_month'=>$s_month,
					's_day'=>$s_day-1,
					's_uid'=>$uid,
				];
				$s_signin_num = Db::name('signin_log')->where($where)->value('s_signin_num');
					
				$s_signin_num = $s_signin_num?$s_signin_num:0;
				$s_Integral = $this->judge($s_signin_num);
				$msg = [
				'content'=>'签到提醒!今日签到可获得'.$s_Integral.'积分',//透传内容
				'title'=>'签到提醒',//通知栏标题
				'text'=>'签到提醒!今日签到可获得'.$s_Integral.'积分',//通知栏内容
				];
				$clientids=array(
					['client_id'=>$userInfo['client_id']],
					'system'=>$userInfo['app_system'],//1为ios
				);
				$Pushs = new Pushs();
				$res = $Pushs->getTypes($msg,$clientids);
				return $res;
			}
		}
		return 0;
		
	}
	public function tuisong($clientids, $title, $content)
    {
        $msg = [
            'content'=>$content,//透传内容
            'title'=>$title,//通知栏标题
            'text'=>$content,//通知栏内容
//            'curl'=>request()->domain(),//通知栏链接
        ];
//        $clientids=array(
//            ['client_id'=>$clientId],
//            'system'=>2,//1为ios
//        );
        $Pushs = new Pushs();
        $Pushs->getTypes($msg,$clientids);
    }
	public function orderRing($uid){
		//0，待付款；2，待收货；
		$where=[
			'order_uid'=>$uid,
			'order_status'=>0,
			'after_status'=>0,
			'order_isdel'=>0
		];
		$data = [];
		$data['obligation'] = Db::name('order')->where($where)->count();//0，待付款
		$where3 = '(order_uid='.$uid.') and (after_status = 0) and (order_isdel=0) and  ((order_status=2) or (order_status=1) ) ';
		$data['receiving'] = Db::name('order')->where($where3)->count();//0，待收货；
		$where2['after_state_status']=['neq',0];
		$where2['og_uid'] =$uid;
		$where2['audit_status'] = ['in','1,3,4'];
		$data['aftersales'] = Db::name('sh_info')->where($where2)->count();//售后
		$map = [
			'order_uid'=>$uid,
			'is_commented'=>0,
			'order_status'=>3,
			'order_isdel'=>0,
		];
		$data['evaluate'] = Db::name('order')->where($map)->count();//待评价
		return $data;
		
	}
	//获取银行名称
	public function  cashbank(){
		$data =array('中国建设银行','中国工商银行','中国农业银行','中国银行','交通银行','招商银行','中信实业银行','上海浦东发展银行','民生银行','光大银行','广东发展银行','兴业银行');
		return $data;
		
	}

	/*
    * 版本升级提醒
    */
    public function updateNotice(){
    	$data = Db::name('users')->field('user_id,user_mobile')->select();
    	$mobile_arr = [];
    	$mobile_str = '';
    	if(empty($data)){
    		return ['status' => 0, 'msg' => '手机号不能为空'];
        	die;
    	}

		foreach ($data as $key => $v) {
			if(!empty($v['user_mobile']) && strlen($v['user_mobile'])==11){
				$mobile_arr[] = $v['user_mobile'];
				//赠送元宝
				$this->giveYinzi($v['user_id'],5);
				$this->giveYinzi($v['user_id'],5);
				$this->giveYinzi($v['user_id'],5);
				$this->giveYinzi($v['user_id'],5);
			}
		}
		if(!empty($mobile_arr)){
			$mobile_str = implode(',', $mobile_arr);
	        // 发送短信
	        $sms = new SmsService();
	        $rs = $sms->sendVerUpdate($mobile_str);	      
        	return ['status' => 1, 'msg' => $rs];
		}else{
			return ['status' => 1, 'msg' => '没有可用手机号'];
        	die;
		}
    	
    	
    }

    //后台赠送元宝
    public function giveYinzi($uid,$num)
    {
    	$data['yin_no'] = 'YB'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
		$data['yin_uid'] = $uid;
		$data['yin_amount'] = $num;
		$data['yin_type'] = 7;
		$data['yin_desc'] = '九州官方赠送';
		$data['yin_stat'] = 2;
		$data['status'] = 1;
		$data['yin_add_time'] = time();
		$data['yin_valid_time'] = 30;
		$data['yin_die_time'] = time()+$row['yin_valid_time']*24*3600;
		$res = Db::name('yinzi')->insert($data);
		$y_log_yid = Db::name('yinzi')->getLastInsID();
		$yz_data = [];
		$yz_data['y_log_yid'] = $y_log_yid;
		$yz_data['y_log_uid'] = $uid;
		$yz_data['y_log_desc'] = '九州官方赠送';
		$yz_data['y_log_addtime'] = time();
		$result = Db::name('yinzi_log')->insert($yz_data);
    }
}

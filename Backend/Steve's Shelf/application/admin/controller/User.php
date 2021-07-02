<?php
namespace app\admin\controller;

use app\common\model\Users as UserModel;
use app\common\service\Settlement;
use app\common\service\User as UserService;
use app\common\service\Goods as GoodsService;
use app\common\service\PointLog as PointLogService;
use app\common\service\Withdrawals as WithdrawalsService;
use app\common\service\Store as StoreService;
use app\common\service\Kefu as KefuService;
use app\common\service\Admin as AdminService;
use app\common\service\Recharge as RechargeService;
 
use think\Db;
use getui\Pushs;

class User extends Base{
	 
	public $baseurl = '';
	public function _initialize(){
		parent::_initialize();
		$this->baseurl = request()->domain();
		//模型
		$UserModel=new UserModel();
		$this->model=$UserModel;
		//服务
		$UserService=new UserService();
		$this->service=$UserService;
		$StoreService=new StoreService();
		$this->Store=$StoreService;
	}

	public function index(){
		// return json(['data' => request()->isAjax()]);
		$user_name = trim(input('user_name'));
		$user_mobile=trim(input('user_mobile'));
		$start_time=trim(input('start_time'));
		$end_time=trim(input('end_time'));
		$type=trim(input('type'));

		$this->assign('type',$type);
		$this->assign('user_name',$user_name);
        $this->assign('user_mobile',$user_mobile);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
		if(request()->isAjax()){
			// 排序
			// $order=input('get.sort')." ".input('get.order');
			$order = 'user_id desc';
			// exit(print($order));
			// limit
			$limit=input('get.offset').",".input('get.limit');

			//查询

			if(input('user_name')){
				$map['user_name']=['like','%'.input('user_name').'%'];	
			}
			if(input('user_mobile')){
			    $map['user_mobile']=['like','%'.$user_mobile.'%'];
            }
            if(input('start_time')){
			    $start_time=str_replace('+',' ',input('start_time'));
            }
			//1:余额倒叙 2:积分倒叙 3vip倒叙
			if(input('type')){
			    $type=input('type');
				if($type == 1){
					$order = 'user_account desc';
				}else if($type == 2){
					$order = 'user_points desc';
				}
                else if($type == 3){
                    $order = 'is_vip desc';
                }
            }
            if(input('end_time')){
                $end_time=str_replace('+',' ',input('end_time'));
            }

            if($start_time && $end_time){
                $map['user_reg_time']=['between',strtotime($start_time).','.strtotime($end_time)];
            }elseif($start_time){
                $map['user_reg_time']=['>=',strtotime($start_time)];
            }elseif($end_time){
                $map['user_reg_time']=['<=',strtotime($end_time)];
            }
			$total = $this->service->count($map);
			$rows = $this->service->select($map,'*',$order,$limit);
			$sum_card=0;
			if($rows){
				foreach($rows as $key=>$val){
				   $yin_amount = Db::name('yinzi')->where('yin_uid',$val['user_id'])->sum('yin_amount');
				   $coupon_amount = Db::name('coupon_users')->where('c_uid',$val['user_id'])->count();
				   if($val['is_seller']==0){
				   		$val['s_grade']='VIP会员';
				   }else{
				   		$s_grade = Db::name('store')->where('s_uid',$val['user_id'])->field('s_grade')->find();
				   		if($s_grade['s_grade'] == 3){
				   			$val['s_grade']='旗舰店主';
				   		}elseif($s_grade['s_grade'] == 2){
				   			$val['s_grade']='高级店主';
				   		}elseif($s_grade['s_grade'] == 1){
				   			$val['s_grade']='会员店主';
				   		}
				   }
				   //当前页充值卡余额
				    $sum_card+=$val['user_card'];
				    $rows[$key]['sum_card']=round($sum_card,2);
				   //全部充值卡总金额
                    $where['status']=['eq',0];
                    $total_card=db('users')->where($where)->sum('user_card');
                    $rows[$key]['total_card']=round($total_card,2);
                    //全部元宝个数
                    // $map1['yin_stat']=['eq',2];
                    $sum_yunbao=db('yinzi')->sum('yin_amount');
                    $rows[$key]['sum_yunbao']=(int)$sum_yunbao;

                    //查询出实名认证通过的用户身份证号
                    $auth_stat=db('idauth')->where('auth_uid',$val['user_id'])->value('auth_id_no');
                    if($auth_stat){
                        $val['id_no']=$auth_stat;
                    }
                    $val['is_vip'] =  $val['is_vip']==1?'会员':'非会员';
                    $val['vip_end_time']= ($val['vip_end_time']>0)?date('Y-m-d',$val['vip_end_time']):0;
                    $val['user_reg_time']=date('Y-m-d H:i:s',$val['user_reg_time']);
				    $val['is_kefu'] =  $val['is_kefu']==0?'否':'客服';
				    $val['yin_amount'] =  $yin_amount;
				    $val['coupon_amount'] =  $coupon_amount;
				}
			}
			return json(['total'=>$total,'rows'=>$rows]);
		}
		else{
			// $order = 'user_id asc';	
			// $row = $this->service->select('', 'user_id,user_name,user_mobile,user_avat,user_points,user_reg_time', 'user_id asc', '0,20');
			// $this->assign('row', $row);
			// $this->assign('row',$row);
			// return json(['rows' => $row]);
			return $this->fetch();
		}
	}
	/*
	*用户信息详情
	*/
	public function userInfo(){
		$map['user_id']= input('get.user_id');
		$row = $this->service->find($map);
		if($row){
			$yin_amount = Db::name('yinzi')->where('yin_uid',$row['user_id'])->sum('yin_amount');
			$row['yin_amount'] =  $yin_amount;
			$coupon = Db::name('coupon_users')->where('c_uid',$row['user_id'])->select();
			$idauthInfo = Db::name('idauth')->where('auth_uid',$row['user_id'])->find();
			if($idauthInfo){
				$row['user_truename'] =  $idauthInfo['auth_truename'];
				$row['user_id_no'] =  $idauthInfo['auth_id_no'];
			}
			$coupon =  count($coupon);
			$row['coupon'] =  $coupon;
		}
		$this->assign('row',$row);
		return $this->fetch();
	}/*
	*用户赠送
	*/
	public function userGive(){
		if(request()->isAjax()){
			$map['user_id'] = input('post.user_id');
			$row = input('post.row/a');
			//1:元宝; 2 优惠券; 3:充值卡
			$add['uid'] = session('admin_id');
			$admin = Db::name('admin')->where('admin_id',$add['uid'])->field('admin_name')->find();
			if($admin){
				$admin_name = $admin['admin_name'];
			}
			$data= [];
			if($row['type'] == 1){
				$data['yin_no'] =$this->createYzNo();
				$data['yin_uid'] = $map['user_id'];
				$data['yin_amount'] = $row['yin_amount'];
				$data['yin_type'] = 7;
				$data['yin_desc'] = $row['yin_desc'];
				$data['yin_stat'] = 2;
				$data['status'] = 1;
				$data['yin_add_time'] = time();
				$data['yin_valid_time'] = $row['yin_valid_time'];
				$data['yin_die_time'] = time()+$row['yin_valid_time']*24*3600;
				$res = Db::name('yinzi')->insert($data);
				$y_log_yid = Db::name('yinzi')->getLastInsID();
				$yz_data = [];
				$yz_data['y_log_yid'] = $y_log_yid;
				$yz_data['y_log_uid'] = $map['user_id'];
				$yz_data['y_log_desc'] = '后台赠送 赠送人为'.$admin_name.'管理员id'.$add['uid'];
				$yz_data['y_log_addtime'] = time();
				$result = Db::name('yinzi_log')->insert($yz_data);
				//操作日志
				$add['uid'] = session('admin_id');
				$add['ip_address'] = request()->ip();
				$add['controller'] = request()->controller();   
				$add['action'] = request()->action();
				$add['remarks'] = '赠送元宝';
				$add['number'] = $y_log_yid;
				$add['create_at'] = time(); 
				db('web_log')->insert($add); 
				
			}else if($row['type'] == 2){
				$c_no = $this->createCouponNo();
				$coupon = Db::name('coupon')->where('coupon_id',$row['coupon_id'])->find();
				if(!$coupon){
					return 0;
				}
				$data['coupon_id'] = $row['coupon_id'];
				$data['c_uid'] = $map['user_id'];
				$data['add_time'] =  time();
				$data['coupon_stat'] = 1;
				$data['c_coupon_title'] = $coupon['coupon_title'];
				$data['c_coupon_type'] = $coupon['coupon_type'];
				$data['c_coupon_price'] = $coupon['coupon_price'];
				$data['c_coupon_buy_price'] = $coupon['coupon_use_limit'];
				$data['coupon_type_id'] = $coupon['coupon_type_id'];
				$data['coupon_aval_time'] = $coupon['coupon_aval_time'];
				$data['c_coupon_thumb'] = $coupon['coupon_thumb'];
				$data['c_no'] =  $c_no;//优惠券编号（购买时填入）
				$res = Db::name('coupon_users')->insert($data);	

				//操作日志
				$coupon_id = Db::name('coupon_users')->getLastInsID();
				$add['uid'] = session('admin_id');
				$add['ip_address'] = request()->ip();
				$add['controller'] = request()->controller();   
				$add['action'] = request()->action();
				$add['remarks'] = '赠送优惠券';
				$add['number'] = $coupon_id;
				$add['create_at'] = time(); 
				db('web_log')->insert($add); 
			}else if($row['type'] == 3){
				$card = Db::name('rc_template')->where('rc_id',$row['card_t_id'])->find();
				if(!$card){
					return 0;
				}
				$Recharge = new RechargeService();
				$card_no = $Recharge->cardNo();
				$data['card_t_id'] = $row['card_t_id'];
				$data['card_uid'] = $map['user_id'];
				$data['card_no'] = $card_no;//充值卡编号
				$data['card_stat'] = 1;
				$data['card_add_time'] =  time();
				$data['card_title'] = $card['rc_title'];
				$data['card_thumb'] = $card['rc_thumb'];
				$data['card_price'] = $card['rc_price'];
				$data['card_end_time'] = $card['rc_s_time'] + $card['rc_aval_time'] * 24 * 3600;//充值卡到期时间
				$data['card_balance'] = $card['rc_price'];//充值卡余额
				
				$res = Db::name('user_rc')->insert($data);	
				// if($res){
				// 	Db::name('users')->where('user_id',$map['user_id'])->setInc('user_card',$card['rc_price']);
				// }
				
				//操作日志
				$card_id = Db::name('user_rc')->getLastInsID();
				$add['uid'] = session('admin_id');
				$add['ip_address'] = request()->ip();
				$add['controller'] = request()->controller();   
				$add['action'] = request()->action();
				$add['remarks'] = '赠送充值卡';
				$add['number'] = $card_id;
				$add['create_at'] = time(); 
				db('web_log')->insert($add); 
			}
			return AjaxReturn($res,getErrorInfo($res));

		}else{
			$map['user_id']= input('get.user_id');
			$row = $this->service->find($map);
			if($row){
				$yin_amount = Db::name('yinzi')->where('yin_uid',$row['user_id'])->count('yin_amount');
				$row['yin_amount'] =  $yin_amount;
				$coupon = Db::name('coupon_users')->where('c_uid',$row['user_id'])->select();
				$coupon =  count($coupon);
				$row['coupon'] =  $coupon;
			}
			//优惠券 
			$where = [
				'status' => 0,
				'coupon_aval_time' =>[['>=',time()]]
			];
			$coupon_list = Db::name('coupon')->where($where)->select();
			$this->assign('coupon_list',$coupon_list);
			
			
			//充值卡
			$card_list = Db::name('rc_template')->where('rc_status',0)->select();
			$this->assign('card_list',$card_list);
			
			$this->assign('row',$row);
			return $this->fetch();
		}
		
	}
    /*
	*获取关系
	*/
    public function getTree($uid){ 
		$arr= array('t_uid','t_p_uid','t_g_uid');
		for($i=0;$i<=1;$i++){
			 $res = Db::name('users_tree')->where( $arr[$i] ,$uid)->value($arr[$i+1] );	 
			if($res){
				return $res;
			}
		}
	
	}
	/*
    * 关系变更
	 * */
    public function relationshipChange(){
        $map['user_id']= input('user_id');
        $this->assign('user_id',$map['user_id']);
        if(request()->isAjax()){
            // 修改层级关系、
            $pid = input('post.pid');
            $map['user_id']= input('user_id');
            if(!empty($pid)){
                Db::startTrans();
                try{
                    $uid_info = db('users_tree')->where(['t_uid' => $map['user_id']])->find();

                    // 已有层级关系
                    if ($uid_info && ($uid_info['t_p_uid'] != $pid) && $pid > 0 ) {
                        // 查上级
                        $pid_info = db('users_tree')->where(['t_uid' => $pid])->find();
                        if (!empty($pid_info)) {
                            $data = [
                                't_p_uid' => $pid,
                                't_g_uid' => $pid_info['t_p_uid']
                            ];
                            $res=db('users_tree')->where(['t_uid' => $map['user_id']])->update($data);
                        }
                        else {
                            // 没有上级
                            $data = [
                                't_p_uid' => $pid,
                                't_g_uid' => 0,
                            ];
                            $res=db('users_tree')->where(['t_uid' => $map['user_id']])->find();
                            if($res){
                                $res=db('users_tree')->where(['t_uid' => $map['user_id']])->update($data);
                            }else{
                                $data['t_uid'] = $map['user_id'];
                                $res=db('users_tree')->insert($data);
                            }
                        }
//                    return AjaxReturn($res,getErrorInfo($res));
                    }
                    // 无层级关系
                    else{
                        $insert = [
                            't_uid' => $map['user_id'],
                            't_p_uid' => $pid,
                            't_addtime' => time(),
                        ];
                        $p_user_tree = Db::name('users_tree')->where(['t_uid' => $pid])->field('t_p_uid')->find();
                        if($p_user_tree){
                            $insert['t_g_uid'] = $p_user_tree['t_p_uid'];
                        }
                        $res = Db::name('users_tree')->insert($insert);
//                    return AjaxReturn($res,getErrorInfo($res));
                    }

                    //若该变更人已经发展了下级，改变下级的层级关系
                    $child_id = db('users_tree')->where(['t_p_uid' => $map['user_id']])->column('t_uid');
                    if($child_id){
                        $now_p_uid = db('users_tree')->where(['t_uid' => $uid_info['t_uid']])->value('t_p_uid');
                        $dataChild = [
                            't_g_uid'=>$now_p_uid
                        ];
                        db('users_tree')->where(['t_uid'=>['in',$child_id]])->update($dataChild);
                    }
                    //更改大礼包归属
                    $gift_p_id = db('gift_log')->where(['log_uid'=>$map['user_id']])->value('log_p_uid');
                    if($gift_p_id && $gift_p_id!=$pid){
                        db('gift_log')->where(['log_uid'=>$map['user_id']])->update(['log_p_uid'=>$pid]);
                    }

                    // 提交事务
                    Db::commit();
                    return AjaxReturn($res,getErrorInfo($res));
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return (['code'=>0,'msg'=>'修改失败']);
                }

            }
            else{
                return (['code'=>0,'msg'=>'修改失败']);
            }
        }else{
            $uid  = input('user_id');
            if($uid){

                $oldUid = $this->getTree($uid);
                if($oldUid){
                    $oldInfo =db::name('users')->alias('a')->join('store b','a.user_id = b.s_uid')->where('user_id',$oldUid)->field('a.user_name,a.user_mobile,a.user_avat,b.s_better_time,b.s_comm_time,b.s_grade')->find();
                }
                $grade_arr = array('会员店','高级店铺','旗舰店铺');
                if($oldInfo){
                    $oldInfo['s_better_time'] = date('Y-m-d H:i',$oldInfo['s_better_time']);
                    $oldInfo['s_comm_time'] = date('Y-m-d H:i',$oldInfo['s_comm_time']);
                    $oldInfo['s_grade'] =$grade_arr[$oldInfo['s_grade']];
                    $this->assign('oldInfo',$oldInfo);
                }
            }

            return $this->fetch();
        }
    }

    /*
	 * 根据手机号判断会员是否存在
	 * */
	public function checkName(){
    	    $phone= input('get.phone');
    	    $map['user_mobile']=['eq',$phone];
            $res = db('users')->alias('a')->join('__STORE__ b','a.user_id=b.s_uid','LEFT')->field('a.user_id,a.user_name,a.user_mobile,a.user_reg_time,a.user_avat,b.s_grade,b.s_comm_time,b.s_better_time,b.s_best_time')->where(['a.user_mobile' => $phone])->find();
            if(empty($res)){
                    return json(['code'=>0]);
			}
			 
			if(empty($res['s_grade'])){
				
        	        return json(['code'=>1,'row'=>$res]);
			}else{
                    $res['user_reg_time']=date('Y-m-d H:i:s',$res['user_reg_time']);
                    $res['s_comm_time']=date('Y-m-d H:i:s',$res['s_comm_time']);
                    $res['s_better_time']=date('Y-m-d H:i:s',$res['s_better_time']);
                    $res['s_best_time']=date('Y-m-d H:i:s',$res['s_best_time']);
        	        return json(['code'=>2,'row'=>$res]);
        }
    }
	/*
     * 生成优惠券编号
     */
    public function createCouponNo(){
        $no = 'YH'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $check = Db::name('coupon_users')->where('c_no', $no)->field('c_id')->find();
        while($check){
            $no = $this->createCouponNo();
        }
        return $no;
    } 
    /**
     * 获取账户明细
     */
    public function getUserAccountLog(){
 		$user_id = input('user_id');
		$end_time = input('end_time');
		$start_time = input('start_time');
		$user_name = input('user_name');
		$this->assign('user_id',$user_id);
		$this->assign('user_name',$user_name);
		$this->assign('start_time',$start_time);
		$this->assign('end_time',$end_time);
		if(request()->isAjax()){
			$map ='';
			$uid = input('user_id');
			if(input('user_name')){
				$map['user_name'] = trim(input('user_name'));
			}
			$start_time = input('start_time');
			$end_time = input('end_time');
            if($start_time && $end_time){
                $map['yin_add_time']=['between',strtotime($start_time).','.strtotime($end_time)];
            }elseif($start_time){
                $map['yin_add_time']=['>=',strtotime($start_time)];
            }elseif($end_time){
                $map['yin_add_time']=['<=',strtotime($end_time)];
            }
            $map['a.a_uid'] = $uid;
            $field = 'a.a_log_id,a.acco_num,a.acco_type,a.acco_type_id,a.acco_desc,a.acco_time,b.user_name';
			$account_log = Db::name('account_log')->alias('a')->join('__USERS__ b', 'a.a_uid=b.user_id', 'LEFT')->where($map)->field($field)->order('a.acco_time desc')->limit($s, $num)->select();
			$zon_num = Db::name('account_log')->alias('a')->join('__USERS__ b', 'a.a_uid=b.user_id', 'LEFT')->where($map)->field($field)->select();

			$cash_way = array('','支付宝提现','微信提现','银行卡提现');
			if($account_log){
				foreach($account_log as &$v){
					switch($v['acco_type']){
						case 1 : $v['acco_type'] = '提现';	break;
						case 2 : $v['acco_type'] = '购物';	break;
						case 3 : $v['acco_type'] = '充值';	break;
						case 4 : $v['acco_type'] = '返利';	break;
						case 5 : $v['acco_type'] = '分享赚'; break;
						case 6 : $v['acco_type'] = '购买优惠券'; break;
						case 7 : $v['acco_type'] = '提现失败'; break;
						case 8 : $v['acco_type'] = '店铺升级奖励'; break;
	                    case 9 : $v['acco_type'] = '促销奖励'; break;
	                    case 10 : $v['acco_type'] = '后台赠送'; break;
	                    case 11 : $v['acco_type'] = '订单退货取消返利'; break;
	                    case 12 : $v['acco_type'] = '订单退款'; break;
					}
					if($v['acco_type'] == 1){	
						if($v['acco_type_id']){
							$cashInfo = Db::name('cash')->where('cash_id',$v['acco_type_id'])->field('cash_way')->find();
							$v['cash_way'] = $cash_way[$cashInfo['cash_way']];
						}
					}else if($v['acco_type'] == 7){	
						$cashInfo = Db::name('cash')->where('cash_id',$v['acco_type_id'])->field('cash_comm')->find();
						$v['cash_comm'] = '';
						if($cashInfo['cash_comm']){
							$v['cash_comm'] = $cashInfo['cash_comm'];	
						}
					}
					$v['acco_time'] = date('Y-m-d H:i:s', $v['acco_time']);
				}
			}
			$total = count($zon_num);
			return json(['total'=>$total,'rows'=>$account_log]);
		}else{
			
		 return $this->fetch();
		}
    }
	/*
	 *@params $user_id
     * 获取用户元宝列表
     */	
	 public function getUserWing(){
		 $user_id = input('user_id');
		 $end_time = input('end_time');
		 $start_time = input('start_time');
		 $yin_no = input('yin_no');
		 $this->assign('user_id',$user_id);
		 $this->assign('yin_no',$yin_no);
		 $this->assign('start_time',$start_time);
		 $this->assign('end_time',$end_time);
		 if(request()->isAjax()){
			$map ='';
			$uid = input('user_id');
			if(input('yin_no')){
				$map['yin_no'] = trim(input('yin_no'));
			}
			$start_time = input('start_time');
			$end_time = input('end_time');
            if($start_time && $end_time){
                $map['yin_add_time']=['between',strtotime($start_time).','.strtotime($end_time)];
            }elseif($start_time){
                $map['yin_add_time']=['>=',strtotime($start_time)];
            }elseif($end_time){
                $map['yin_add_time']=['<=',strtotime($end_time)];
            }
			$limit=input('get.offset').",".input('get.limit');
			$wing_list = Db::name('yinzi')->where(array('yin_uid'=>$uid))->where($map)->limit($limit)->select();
			$yin_type = array('','店主邀请VIP','受邀请成为vip','分享','店主邀请店主','受邀请成为店主','连续签到奖励','后台赠送');
			$yin_stat = array('','未生效','未使用','已使用','已过期','已赠送');
			if($wing_list){
				foreach($wing_list as $key=>$val){
					$wing_list[$key]['yin_type'] = $yin_type[$val['yin_type']];
					$wing_list[$key]['yin_die_time'] = date('Y-m-d H:i',$val['yin_die_time']);
					$wing_list[$key]['yin_add_time'] = date('Y-m-d H:i',$val['yin_add_time']);
					$wing_list[$key]['yin_stat'] = $yin_stat[$val['yin_stat']];
				}
			}
			$total = Db::name('yinzi')->where(array('yin_uid'=>$uid))->where($map)->count(); 
			return json(['total'=>$total,'rows'=>$wing_list]);
		}else{
			
		 return $this->fetch();
		}
	 }
	 /*
	 *@params $user_id
     * 获取用户充值卡列表
     */	
	 public function getUserCard(){
		$user_id = input('user_id');
		$end_time = input('end_time');
		$start_time = input('start_time');
		$card_no = input('card_no');
		$this->assign('user_id',$user_id);
		$this->assign('card_no',$card_no);
		$this->assign('start_time',$start_time);
		$this->assign('end_time',$end_time);
		if(request()->isAjax()){
			 $uid = input('user_id');
			 if(input('card_no')){
				$map['card_no'] = trim(input('card_no'));
			}
			$start_time = input('start_time');
			$end_time = input('end_time');
            if($start_time && $end_time){
                $map['card_add_time']=['between',strtotime($start_time).','.strtotime($end_time)];
            }elseif($start_time){
                $map['card_add_time']=['>=',strtotime($start_time)];
            }elseif($end_time){
                $map['card_add_time']=['<=',strtotime($end_time)];
            }
			 $card_list = Db::name('user_rc')->where(array('card_uid'=>$uid))->where($map)->field('card_id,card_no,card_stat,card_add_time,card_price,card_title,card_end_time,card_balance')->select();
 
			 //1，未使用；2，已使用；3，已转赠；4，已过期
			 $card_arr = array('未使用','已使用','已转赠','已过期');
			 foreach($card_list as $key=>$val){
				 $val['card_stat'] = $card_arr[$val['card_stat']];
				 $card_list[$key]['card_add_time'] = date('Y-m-d H:i',$val['card_add_time']);
				 $card_list[$key]['card_end_time'] = date('Y-m-d H:i',$val['card_end_time']);
			 }
			$total =Db::name('user_rc')->where(array('card_uid'=>$uid,'card_stat'=>1))->count();
			return json(['total'=>$total,'rows'=>$card_list]);
		}else{
			return $this->fetch();	
		}
	 }
	 /*
	 *@params $user_id
     * 获取用户优惠券列表
     */	
	 public function getUserCoupon(){
		$user_id = input('user_id');
		$end_time = input('end_time');
		$start_time = input('start_time');
		$c_coupon_title = input('c_coupon_title');
		$this->assign('user_id',$user_id);
		$this->assign('c_coupon_title',$c_coupon_title);
		$this->assign('start_time',$start_time);
		$this->assign('end_time',$end_time);
		if(request()->isAjax()){
			$uid = input('user_id');
			 if(input('c_coupon_title')){
				$map['c_coupon_title'] = ['like','%'.input('c_coupon_title').'%'];
			}
			$start_time = input('start_time');
			$end_time = input('end_time');
            if($start_time && $end_time){
                $map['add_time']=['between',strtotime($start_time).','.strtotime($end_time)];
            }elseif($start_time){
                $map['add_time']=['>=',strtotime($start_time)];
            }elseif($end_time){
                $map['add_time']=['<=',strtotime($end_time)];
            }

			 $coupon_list = Db::name('coupon_users')->where('c_uid',$uid)->where($map)->field('c_id,coupon_id,c_coupon_title,add_time,coupon_stat,c_coupon_type,c_coupon_type,c_coupon_price,c_coupon_buy_price,coupon_aval_time,c_no')->select();
			 //代金券类型:1，商品券；2，专区券；3，全场券
			 $type_arr = array('','商品券','专区券','全场券');
			 //优惠券状态：1，未使用；2，已使用；3，已过期；4，已转赠
			 $status_arr = array('','未使用','已使用','已过期','已转赠');
			 foreach($coupon_list as &$val){
				 $val['c_coupon_type'] = $type_arr[$val['c_coupon_type']];
				 $val['coupon_stat'] = $status_arr[$val['coupon_stat']];
				 $val['add_time'] = date('Y-m-d H:i',$val['add_time']);
				 $val['coupon_aval_time'] =  date('Y-m-d H:i',$status_arr[$val['coupon_aval_time']]);
			 }
			$total =Db::name('user_rc')->where(array('card_uid'=>$uid))->count();
			return json(['total'=>$total,'rows'=>$coupon_list]);
		}else{
			return $this->fetch();	
		}
	 }
    /*
     * 添加会员
     * */
	 
	public function add(){
		if(request()->isAjax()){
			$row=input('post.row/a');	
			if($row){
				if(!$row['user_mobile']){
					 return (['code' => 0, 'msg' => '请填写手机号！', 'data' => '请填写手机号！']);
				}
				$result = db('users')->where('user_mobile',$row['user_mobile'])->find();
				if($result){
					 return (['code' => 0, 'msg' => '此手机号已经注册过！', 'data' => '此手机号已经注册过！']);
				}
			}
			$row ['user_pwd'] = md5('hetao_'.md5($row['password']));
			$row['s_invite_code'] = $this->Store->createInviteCode();
			$row['user_avat'] = '';
			$row['user_reg_time'] = time();
			$res=$this->service->add($row);
			if($res){
				$id=db('users')->getLastInsID();
				$tree_data =[
					't_uid'=>$id,
					't_p_uid'=>1,//合陶官方
					't_addtime'=>time(),
				];
				$res = Db::name('users_tree')->insert($tree_data);
			}
            //添加日志记录
            $id=db('users')->getLastInsID();
            $this->write_log('添加会员',$id);
            // 修改层级关系、
            $pid = input('post.pid');
            if (!empty($pid)) {
                // 查上级
                $pid_info = db('users_tree')->where(['t_uid' => $pid])->find();
                if (!empty($pid_info)) {
                    $data = [
                        't_uid' => $id,
                        't_p_uid' => $pid,
                        't_g_uid' => $pid_info['t_p_uid'],
                        't_addtime' => time()
                    ];
                } else {
                    // 没有上级
                    $data = [
                        't_uid' => $id,
                        't_p_uid' => $pid,
                        't_g_uid' => 0,
                        't_addtime' => time()
                    ];
                }
                db('users_tree')->insert($data);
            }
			return AjaxReturn($res,getErrorInfo($res));
		}else{
            $user_list = db('users')->field('user_id, user_name')->where(['is_seller' => 1, 'status' => 0])->select();
            $this->assign('user_list', $user_list);
			return $this->fetch();
		}
	}
    /*
     * 编辑会员
     * */
	public function edit(){
		if(request()->isAjax()){
			$row = input('post.row/a');
			$map['user_id'] = input('post.user_id');
			if ($row['user_pwd']){
			    $len = strlen($row['user_pwd']);
			    if ($len<6 ||$len>12){
                    return AjaxReturn(-2007,getErrorInfo(-2007));
                }
                /*if (!preg_match('/^{6,12}$/',$row['user_pwd'])){
                    return AjaxReturn(-2007,getErrorInfo(-2007));
                }*/
            }
			$row ['user_pwd'] = md5('hetao_'.md5($row['user_pwd']));
			$res=$this->service->save($map,$row);
            //添加日志记录
            $this->write_log('编辑会员',$map['user_id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['user_id']= input('get.ids');
			$row=$this->service->find($map);
			$user_list = db('users')->field('user_id, user_name')->where(['is_seller' => 1, 'status' => 0])->select();
			$this->assign('user_list', $user_list);
			$this->assign('row',$row);
			return $this->fetch();
		}
	}
    /*
     * 删除会员
     * */
	public function delete(){
		$ids=input('get.ids');
		$map['user_id']=['in',$ids];
        $status=$this->service->select($map,'status');
        $where=[];
        foreach ($status as $val){
            $where[]=$val['status'];
        }
        $data=array_count_values($where);
        $num=explode(',',$ids);
        if($data[0]==count($num)){
            $res=Db::name('users')->where($map)->update(array('status'=>1));
        }elseif($data[1]==count($num)){
            $res=Db::name('users')->where($map)->update(array('status'=>0));
        }else {
            return (['code' => 0, 'msg' => '此操作错误不能执行', 'data' => '此操作错误不能执行']);
        }
        //添加日志记录
        $this->write_log('删除会员',$ids);
		return AjaxReturn($res);
	}
	/*
	* 设置
	*/
	public function multi(){
		$action=input('action');
   		$ids=input('get.ids/a');
		$array = implode(',',$ids);
		if(!$action){
			return AjaxReturn(UPDATA_FAIL);
		}
		if ($action == 'jiesuan') {
            $map['user_id']=['in',$array];
            $res = $this->jieSuan($ids);
            return AjaxReturn($res);
        }
		$map['user_id']=['in',$array];
		$data[$action] = input('params');
		$res= Db::name('users')->where($map)->update($data);

        //添加日志记录
        $this->write_log('设置客服',$ids);

		return AjaxReturn($res);
	}/*
	* 设置
	*/
	public function multis(){
		$action=input('action');
   		$ids=input('get.ids/a');
		$array = implode(',',$ids);
		if(!$action){
			return AjaxReturn(UPDATA_FAIL);
		}
		if ($action == 'jiesuan') {
            $map['user_id']=['in',$array];
            $res = $this->jieSuan($ids);
            if ($res) {
                return AjaxReturn($res);
            } else {
                return AjaxReturn(JS_FALSE);
            }

        }
		$map['user_id']=['in',$array];
		$data[$action] = input('params');
		$res= Db::name('users')->where($map)->update($data);

        //添加日志记录
        $this->write_log('设置客服',$ids);

		return AjaxReturn($res);
	}

	public function point(){
		if(request()->isAjax()){
			// 排序
			$order=input('get.sort')." ".input('get.order');
			// limit
			$limit=input('get.offset').",".input('get.limit');

			//查询
			if(input('get.uid')){
				$map['uid']=input('get.uid');
			}

			$PointLogService=new PointLogService();
			$total=$PointLogService->count($map);
			$rows=$PointLogService->select($map,'*',$order,$limit);
			return json(['total'=>$total,'rows'=>$rows]);
		}else{

			return $this->fetch();
		}
	}

	/*
	* 提现列表
	*/
	public function withdrawals(){
		if(request()->isAjax()){
			// 排序
			$order=input('get.sort')." ".input('get.order');
			// limit
			$limit=input('get.offset').",".input('get.limit');

			//查询
			if(input('get.uid')){
				$map['uid']=input('get.uid');
			}

			$WithdrawalsService=new WithdrawalsService();
			$total=$WithdrawalsService->count($map);
			$rows=$WithdrawalsService->select($map,'*',$order,$limit);
			return json(['total'=>$total,'rows'=>$rows]);
		}else{

			return $this->fetch();
		}
	}

	/*
	* 拒绝申请
	*/
	public function withdrawalsMulti(){
		$action=input('action');
   		$ids=input('ids');
		$map['id']=['in',$ids];
		$WithdrawalsService=new WithdrawalsService();
		switch ($action) {
			//拒绝
			case 'refuse':
				$res=$WithdrawalsService->refuse($map);
				break;
			case 'pass':
				$res=$WithdrawalsService->pass($map);
				break;
			case 'transfer':
				$res=$WithdrawalsService->transfer($map);
				break;
		}
		return AjaxReturn($res);
	}
	 /*
     *是否为客服
     */
	 public function customerbf(){
         $uid = input("kefu_id");
         if(!$uid){
             $uid  = 6;
         }
         $user_id = input('user_id');

         //客服id
         $this->assign('kefu_id',$uid);
         $map['user_id'] = $uid;

         $row = Db::name('users')->where($map)->field('user_id,user_name,user_avat')->find();
         //客服信息
         $this->assign('row',$row);
		 $res = db("msg_list")->where("touid",$uid)->find();
		 if(!$res){
			 return $this->fetch();
		 }
		 //消息类型:0普通聊天，1客服聊天
         $list = db("msg_list")->where("uid=" . $uid . " or touid=" . $uid)->where('genre',1)->order("date desc")->limit(100)->select();
         foreach ($list as $k => $v) {
             if ($v['uid'] == $uid) {
                 $id = $v['touid'];
             } else {
                 $id = $v['uid'];
             }
             $list[$k]['u'] = db("users")->where("user_id=" . $id)->field("user_id,user_name,user_avat")->find();
			 $map = [
				'uid'=>$id,
				'touid'=>$uid,
				'status'=>0,
			 ];
			 $number =  Db::name("msg_list")->where($map)->select();
			 $number = count($number);
			 $list[$k]['u']['number'] =  $number ;

             // $list[$k]['u']['user_avat'] = $this->baseurl . $list[$k]['u']['user_avat'];
             $list[$k]['date'] = date('Y-m-d H:i:s', $v['date']);
         }
         $this->assign('lists', $list);
 //改变未读信息状态
			if($user_id){
				$where = [
				 'uid'=>$user_id,
				 'touid'=>$uid,
				 'status'=>0
				];
				$data = [
					'status'=>1
				];
				$list = db("msg_list")->where($where)->select();
				foreach($list as &$val){
					 db("msg_list")->where('id',$val['id'])->update($data);
				}
			}
         $this->assign('user_id', $user_id);
		 if($user_id){
			$readList = $this->getKefumessage($id, $user_id);
			$this->assign('readList',$readList);
		 }


         $this->display();
         return $this->fetch();
	}
	/*
	*是否为客服
     */
	 public function customer(){
         $kefu_id = input("kefu_id");
         $user_id = input('user_id');

         //客服id
         $this->assign('kefu_id',$kefu_id);
         $map['kefu_id'] = $kefu_id;
         $row = Db::name('kefu')->where($map)->field('kefu_id,kefu_name,kefu_avat')->find();
         //客服信息
		 // print_r($row);
         $this->assign('row',$row);
		 $res = db("msg_list")->where("kf_id",$kefu_id)->find();
		 if(!$res){
			 return $this->fetch();
		 }
		 //消息类型:0普通聊天，1客服聊天
		 $list = Db::query('select distinct uid from ht_msg_list where kf_id='.$kefu_id.' and genre=1 and uid>0 order by date desc limit 0,100' );
         foreach ($list as $k => $v) {
             /*if ($v['touid'] == 0) { //发送给客服的
				$id = $v['uid'];
             } else {                //客服发送给用户的
                 $id = $v['touid'];
             }*/
			 $list[$k]['u'] = db("users")->where("user_id=" .$v['uid'])->field("user_id,user_name,user_avat")->find();
			 /*
			 $map2 = [
				'uid'=>$v['uid'],
				'kf_id'=>$v['kf_id'],
				'status'=>0
			 ];
			 // return( $map2);
			 $number =  Db::name("msg_list")->where($map2)->count();//未读消息数量
//			 $number = count($number);
			 $list[$k]['u']['number'] =  $number;*/
			 //未读数量
			 $number = 0;
			 $number = Db::name('msg')->where(['uid'=>$v['uid'],'kf_id'=>$kefu_id,'looked'=>0])->count();
			 if(!empty($user_id) && $user_id==$v['uid']){
			 	$number = 0;
			 }
			 // $number =  Db::name("msg")->where($map2)->count();//未读消息数量
//			 $number = count($number);
			 $list[$k]['u']['number'] =  $number;

             // $list[$k]['u']['user_avat'] = $this->baseurl . $list[$k]['u']['user_avat'];
             // $list[$k]['date'] = date('Y-m-d H:i:s', $v['date']);
         }
         // var_dump($list);die;
         $this->assign('lists', $list);
         //改变未读信息状态
         if($user_id){
             $where_1 = [
                 'uid'=>$user_id,
                 'kf_id'=>$kefu_id,
                 'status'=>0
                ];
             $data = ['status'=>1];
             $list_ids = db("msg_list")->where($where_1)->value('id');
             if(!empty($list_ids)){
             	if(count($list_ids)>1){
             		$list_ids = implode(',',$list_ids);
             	}
                 db("msg_list")->where(['id'=>['in',$list_ids]])->update($data);
             }
           /* foreach($list as &$val){
                 db("msg_list")->where('id',$val['id'])->update($data);
            }*/
         }
         $this->assign('user_id', $user_id);
         if($user_id){
             $readList = $this->getKefumessage($kefu_id, $user_id);
             $this->assign('readList',$readList);
         }
         $this->display();
         return $this->fetch();
	}
    /**
     *获取聊天记录消息
     */
    public function getKefumessage($kefu_id, $touid)
    {
        $where =  "(kf_id=" . $kefu_id . " and touid=" . $touid . ") or (kf_id=" . $kefu_id . " and uid=" . $touid . ")";
        $list = Db::name("msg")->where($where)->order("id asc")->limit( 100)->select();
        if(empty($list)) return $list;
        $kefuInfo = db("kefu")->where('kefu_id',$kefu_id)->field('kefu_id,kefu_name,kefu_avat')->find();
        //修改阅读状态
        db('msg')->where(['looked'=>0])->where($where)->update(['looked'=>1]);
        foreach ($list as $k => $v) {
            if ($v['uid'] == 0) {
				  $list[$k]['u']['user_name'] = $kefuInfo['kefu_name'];
				  $list[$k]['u']['user_avat'] = $kefuInfo['kefu_avat'];
				  $list[$k]['u']['kefu_id'] = $kefuInfo['kefu_id'];
            } else {
                 $list[$k]['u'] = db("users")->where("user_id=" . $v['uid'])->field("user_id,user_name,user_avat")->find();
            }
			if($v['type'] == 2){
				 $list[$k]['content'] = '<img style="max-height:200px;" src="'.$v['content'].'"/>';
			} elseif ($v['type'] == 4){
			   $content = ltrim($v['content'], '/');
                $list[$k]['content'] = '<img src="/'.$content.'"/>';
            }
            // if ($v['looked'] == 0 && $touid == $v['touid']) {
            /*if ($v['looked'] == 0) {
                db("msg")->where("id=" . $v['id'])->setField("looked", 1);
            }*/
        }
        return $list;
    }
    /**
     *获取聊天记录消息  新修改
     */
    public function getKefumessages($where)
    {
        $list = Db::name("msg")->where($where)->order("id asc")->limit(50)->select();
        foreach ($list as $k => $v){
           if ($v['genre'] == 1){ 
				if($v['uid'] == 0){
					$result = db("kefu")->where('kefu_id',$v['kf_id'])->field('kefu_id,kefu_name,kefu_avat')->find();
					$list[$k]['u']['user_name'] = $result['kefu_name'];
					$list[$k]['u']['user_avat'] = $result['kefu_avat'];
					$list[$k]['u']['kefu_id'] = $result['kefu_id'];
				}else{
					 $list[$k]['u'] = db("users")->where("user_id=" . $v['uid'])->field("user_id,user_name,user_avat")->find();
				}
				
            }else if($v['genre'] == 2) {
				if($v['uid'] == 0){
					$result = db("admin")->where('admin_id',$v['kf_id'])->field('admin_id,nickname,avatar')->find();
					$list[$k]['u']['user_name'] = $result['nickname'];
					$list[$k]['u']['user_avat'] = $result['avatar'];
					$list[$k]['u']['kefu_id'] = $result['admin_id'];
				}else{
					 $list[$k]['u'] = db("users")->where("user_id=" . $v['uid'])->field("user_id,user_name,user_avat")->find();
				}
                
            }else if($v['genre'] == 3) {
				if($v['uid'] == 0){
					$result = db("kefu")->where('kefu_id',$v['kf_id'])->field('kefu_id,kefu_name,kefu_avat')->find();
					$list[$k]['u']['user_name'] = $result['kefu_name'];
					$list[$k]['u']['user_avat'] = $result['kefu_avat'];
					$list[$k]['u']['kefu_id'] = $result['kefu_id']; 
				}else{
					$result = db("admin")->where('admin_id',$v['uid'])->field('admin_id,nickname,avatar')->find();
					$list[$k]['u']['user_name'] = $result['nickname'];
					$list[$k]['u']['user_avat'] = $result['avatar'];
					$list[$k]['u']['kefu_id'] = $result['admin_id'];
				}
			
            } 
			if($v['type'] == 2){
				 $list[$k]['content'] = '<img src="'.$v['content'].'"/>';
			} elseif ($v['type'] == 4){
			   $content = ltrim($v['content'], '/');
                $list[$k]['content'] = '<img src="/'.$content.'"/>';
            }
            if ($v['looked'] == 0) {
                if ($uid == $v['touid']) {
                    db("msg")->where("id=" . $v['id'])->setField("looked", 1);
                }
            }
        }
        return $list;
    }
	/*
     *是否为客服
     */
	 public function newsList(){
	    $map['admin_id']=session('admin_id');
		$row= Db::name('admin')->find($map);
		$this->assign('row',$row);
		return $this->fetch();
	}

	//发送消息
    function sendMeg(){
        $touid = input("touid");
        $data['kf_id'] = input("kf_id");
        $content = input("content");
        $data['touid'] = $touid;
        $data['uid'] = 0;
        $type = input("type");
		//消息类型
		if(!input("genre")){
			  $genre = 1;
		}
        $genre = input("genre");
        $data['content'] = $content;
        $data['type'] = $type;
        $data['date'] = time();
		//普通聊天
		if(!$genre){
			 if ($uid == $data['touid']) {
				$ret['code'] = 1;
				$ret['msg'] = '不可以与自己聊天哦！';
				return json($ret);
			}
		}
        $do = db("msg")->insert($data);
        if($type==2 || $type==4){
        	 $content = '[图片]';
        }
		
        if ($do) {
			//推送
			$msg = [
				'content'=>$content,//透传内容
				'title'=>'您有一条客服信息！',//通知栏标题
				'text'=>$content,//通知栏内容
				'curl'=> request()->domain(),//通知栏链接
			];
			$clientId = $this->service->getClient($data['touid']);
			if($clientId){
				$data2=array(
				0=>['client_id'=>$clientId],
				'system'=>2,//1为ios
				);
				$Pushs = new Pushs();
				$Pushs->getTypes($msg,$data2);
			}
			// $where2 =  "(kf_id=" . $data['kf_id'] . " and touid=" .  $data['touid'] . ") or (kf_id=" . $data['kf_id'] . " and uid=" . $data['touid'] . ")";
			$where2 =  "kf_id=".$data['kf_id']." and touid=".$data['touid'];
            $ch = db("msg_list")->where($where2)->find();
            if ($ch) {
                $da['id'] = $ch['id'];
                $da['content'] = $content;
                $da['date'] = time();
                $da['status'] = 0;
                db("msg_list")->update($da);
            } else {
                $da['uid'] = $data['uid'];
                $da['touid'] = $data['touid'];
                $da['kf_id'] = $data['kf_id'];
                $da['content'] = $content;
                $da['status'] = 0;
                $da['date'] = time();
                db("msg_list")->insert($da);
            }

            $ret['code'] = 0;
            $ret['msg'] = 'ok';
            return json($ret);
        }

        $ret['code'] = 1;
        $ret['msg'] = 'error';
        return json($ret);
    }
    //发送消息 新修改
    function sendMegs(){
        $touid = input("touid");
        $group_id = input("group_id");
        $type = input("type");
        $cat = input("cat");

        //供应商
        if($group_id == 3){
            $data['kf_id'] =  $touid;
            $data['uid'] =  input("kf_id");
            $data['touid'] =   0;
            //供应商 客服聊天
            if($cat == 2){
                $genre = 1;
            }else if($cat == 4){
                //财务 供应商聊天
                $genre = 2;
            }
        //客服
        }elseif ( $group_id == 9){
            $data['uid'] =  0;
            $genre = 1;
            if($cat == 4){
                //财务客服聊天
                $data['kf_id'] = input("kf_id");
                $data['touid'] =   $touid;
                $genre = 3;
            }else if($cat == 3){
                //客服供应商 聊天
                $data['kf_id'] = input("kf_id");
                $genre = 1;
                $data['touid'] =  $touid;
            }
        //财务
        }elseif ( $group_id == 13){
            if($cat == 2 ){
                //财务客服聊天
                $data['touid'] =   0;
                $data['uid'] =  input("kf_id");
                $data['kf_id'] =  $touid;
                $genre = 3;
            }else{
                //财务供应商聊天
                $data['kf_id'] = input("kf_id");
                $data['uid'] =  0;
                $data['touid'] = $touid;
                $genre =2;
            }
        }
	
        $content = input("content");
        $type = input("type");
        $data['content'] = $content;
        $data['type'] = $type;
        $data['date'] = time();
        $data['genre'] = $genre;
		
        $do = db("msg")->insertGetId($data);
        
        if ($do) {
			//推送
			$msg = [
				'content'=>$content,//透传内容
				'title'=>'您有一条客服信息！',//通知栏标题
				'text'=>$content,//通知栏内容
				'curl'=> request()->domain(),//通知栏链接
			];
			$clientId = $this->service->getClient($data['touid']);
			if($clientId){
				$data2=array(
				0=>['client_id'=>$clientId],
				'system'=>2,//1为ios
				);
				$Pushs = new Pushs();
				$Pushs->getTypes($msg,$data2);
			}
            //更新数据
            //供应商
            if($group_id == 3){
			    //供应商 与 客服聊天  genre = 1
                $where2 =  "(kf_id=" . $data['kf_id'] . " and touid=" .  $data['uid'] . ") or (kf_id=" . $data['kf_id'] . " and uid=" . $data['uid'] . ") and (genre = 1) ";
                //供应商 与 财务聊天 genre = 2
			    if($cat == 4){
                    $where2 =  "(kf_id=" . $data['kf_id'] . " and touid=" .  $data['uid'] . ") or (kf_id=" . $data['kf_id'] . " and uid=" . $data['uid'] . " ) and (genre = 2) ";

                }
              $this->checkdata($data,$where2,$type,$genre);
            //财务
            }else if($group_id == 13){
                //财务 与 客服聊天 genre = 3
                $where2 =  "(kf_id=" . $data['kf_id'] . " and touid=" .  $data['uid'] . ") or (kf_id=" . $data['kf_id'] . " and uid=" . $data['uid'] . ") and (genre = 3)";
                //供应商 与 财务聊天 genre = 2
                if($cat == 3){
                    $where2 =  "(kf_id=" . $data['kf_id'] . " and touid=" .  $data['touid'] . ") or (kf_id=" . $data['kf_id'] . " and uid=" . $data['touid'] . ") and (genre = 2)";
                }
 
                $res =  $this->checkdata($data,$where2,$type,$genre);
		 
            }else{
                //客服与供应商  genre = 1
                $where2 =  "(kf_id=" . $data['kf_id'] . " and touid=" .  $data['touid'] . ") or (kf_id=" . $data['kf_id'] . " and uid=" . $data['touid'] . ")  and (genre = 1) ";
                //客服与财务  genre = 3
                if($cat == 4){
                    $where2 =  "(kf_id=" . $data['kf_id'] . " and touid=" .  $data['touid'] . ") or (kf_id=" . $data['kf_id'] . " and uid=" . $data['touid'] . ") and (genre = 3) ";
                }
                $this->checkdata($data,$where2,$type,$genre);
            }
			 
            $ret['code'] = 0;
            $ret['msg'] = 'ok';
            return json($ret);
        }

        $ret['code'] = 1;
        $ret['msg'] = 'error';
        return json($ret);
    }

 /*
 *是否更新数据
 */
public function  checkdata($data,$where2,$type,$genre){
    $ch = db("msg_list")->where($where2)->find();
    $content = $data['content'];
    if ($type == 2) {
        $content = '[图片]';
    }
    if ($type == 3) {
        $content = '[语音]';
    }
    if ($ch) {
        $da['id'] = $ch['id'];
        $da['content'] = $content;
        $da['date'] = time();
        db("msg_list")->update($da);
    } else {
        $da['uid'] = $data['uid'];
        $da['touid'] = $data['touid'];
        $da['kf_id'] = $data['kf_id'];
        $da['content'] = $content;
        $da['genre'] = $genre;
        $da['status'] = 0;
        $da['date'] = time();
        db("msg_list")->insert($da);
    }
}
	/*
     *是否为客服 20180910 18:11
     */
	/* public function kflist(){
		if(request()->isAjax()){

			$order = 'user_id asc';

			$limit=input('get.offset').",".input('get.limit');

			//查询
			$map['is_kefu']=['=',1];

			if(input('get.search')){
				$map['user_name']=['like','%'.input('get.search').'%'];
			}

			$total = $this->service->count($map);
			$rows = $this->service->select($map,'*',$order,$limit);
			if($rows){
				foreach($rows as $val){
				   $val['is_kefu'] =  $val['is_kefu']==0?'否':'客服';
				}
			}

			return json(['total'=>$total,'rows'=>$rows]);
		}
		else{

			return $this->fetch();
		}
	}
	*/
	/*
     *客服列表
     */
	public function management()
    {
        $where['admin_id']=session('admin_id');
        // $where['admin_id']=13;//客服
        // $where['admin_id']=14;//财务
        // $where['admin_id']=3;//供应商
        $userInfo = Db::name('admin')->find($where);
        $group_id = $userInfo['group_id'];
        $type = input('type');
        $user_id = input('user_id');
        $user_avat1 = Db("users")->where("user_id",$user_id)->field("user_avat")->find();
        $this->assign('user_avat1', $user_avat1);
        $kefu_id = input('kefu_id');
        $type = empty( $type)?1:$type;
        $types  = $type;
        //客服 列表
        if($type == 2){
            $lists = Db::name('kefu')->select();
            if($lists){
                foreach ($lists as $key=>$val){
                    $lists[$key]['u']['user_id'] = $val['kefu_id'];
                    $lists[$key]['u']['user_name'] = $val['kefu_name'];
                    $lists[$key]['u']['user_avat'] = $val['kefu_avat'];
                }
                $kefu_id = input('user_id');
                $user_id = input('kefu_id');
                //改变未读信息状态
                if($user_id){
                    $where = [
                        'uid'=>$user_id,
                        'kf_id'=>$kefu_id,
                        'status'=>0
                    ];
                    $data = [
                        'status'=>1
                    ];
                    $list = db("msg_list")->where($where)->select();
                    foreach($list as &$val){
                        db("msg_list")->where('id',$val['id'])->update($data);
                    }
                }
                $this->assign('user_id', $kefu_id);
                if($user_id){
                    //客服供应商聊天
                    $where =  "(kf_id=" . $kefu_id . " and touid=" . $user_id . ") or (kf_id=" . $kefu_id . " and uid=" . $user_id . ") and (genre = 1)";
                    //客服财务聊天
                    if($group_id == 13){
                         // $where =  "(kf_id=" . $kefu_id . " and touid=" . $user_id . ") or (kf_id=" . $kefu_id . " and uid=" . $user_id . ") and (genre = 3)";
                    }
                    $readList = $this->getKefumessages($where);
                }
            }
        //供应商
       }else if($type == 3){
            $supplier_id = Db::name('admin')->where('group_id',3)->column('supplier_id');
            // echo "<pre/>";
            // var_dump($supplier_id);die;
            $supplier_id = array_unique($supplier_id);
            foreach ($supplier_id as $key=>$val){
                $userDetails = Db::name('users')
                ->alias('a')
			->Join('admin b','a.user_id=b.supplier_id')
                    ->where('a.user_id',$val)
                    ->field('a.user_id,b.nickname as user_name,a.user_avat')
                    ->find();

                if($userDetails){
                    $lists[$key]['u'] = $userDetails;
                }
            }
            

            $kefu_id = input('user_id');
            $user_id = input('kefu_id');
            $this->assign('user_id', $kefu_id);
            if($user_id){
                //客服供应商聊天
                $where =  "(kf_id=" . $user_id . " and touid=" . $kefu_id . ") or (kf_id=" . $user_id . " and uid=" . $kefu_id . ") and (genre = 1)";
                //供应商财务聊天
                if($group_id == 13){
                    $where =  "(kf_id=" . $user_id . " and touid=" . $kefu_id . ") or (kf_id=" . $user_id . " and uid=" . $kefu_id . ") and (genre = 2)";
                }
                $readList = $this->getKefumessages($where);
            }
        //财务
        }else if($type == 4){

            $financial = Db::name('admin')
                ->where('group_id',13)
                ->field('admin_id,nickname,avatar')
                ->select();
            if($financial){
                foreach ($financial as $key=>$val){
                    $lists[$key]['u']['user_id'] = $val['admin_id'];
                    $lists[$key]['u']['user_name'] = $val['nickname'];
                    $lists[$key]['u']['user_avat'] = $val['avatar'];
                }
            }
            $kefu_id = input('user_id');
            $user_id = input('kefu_id');
            $this->assign('user_id', $kefu_id);
            if($user_id){
                //财务客服聊天
                $where =  "(kf_id=" . $user_id . " and touid=" . $kefu_id . ") or (kf_id=" . $user_id . " and uid=" . $kefu_id . ") and (genre = 3)";
                //供应商财务聊天
                if($group_id == 3){
                    $where =  "(kf_id=" . $kefu_id . " and touid=" . $user_id . ") or (kf_id=" . $kefu_id . " and uid=" . $user_id . ") and (genre = 2)";
                }
				 
                $readList = $this->getKefumessages($where);
            }
        //聊天历史
        }else{
            //客服
           if($group_id == 9){
               $kefu_id = $userInfo['kf_id'];
               $where =  "(kf_id=" . $kefu_id .") and ((genre = 1) or (genre = 3)) ";
               $res = db("msg_list")->where($where)->find();
               if(!$res){
                   return $this->fetch();
               }
               //0普通聊天，1客服聊天 ,2财务供应商聊天 3 财务客服聊天
               $list = db("msg_list")->where($where)->where('kf_id',$kefu_id)->order("date desc")->limit(100)->select();
               foreach ($list as $k => $v) {
                   if($v['genre'] == 1){
                       if ($v['touid'] == 0) {
                           $id = $v['uid'];
                       } else {
                           $id = $v['touid'];
                       }
                       $lists[$k]['u'] = db("users")->where("user_id=" .$id)->field("user_id,user_name,user_avat")->find();
                       $lists[$k]['u']['genre'] = 1;
                   }else {
                       if ($v['touid'] == 0) {
                           $id = $v['uid'];
                       } else {
                           $id = $v['touid'];
                       }
                       $caiwu = db("admin")->where("admin_id=" .$id)->field("admin_id,nickname,avatar")->find();   
					   $lists[$k]['u']['user_id'] = $caiwu['admin_id'];
                       $lists[$k]['u']['user_name'] = $caiwu['nickname'];
                       $lists[$k]['u']['user_avat'] = $caiwu['avatar'];
                       $lists[$k]['u']['genre'] = 3;
                   }
				   //未
				   if($v['uid'] != 0){
						  $map2 = [
						   'uid'=>$v['uid'],
						   'kf_id'=>$v['kf_id'],
						   'status'=>0,
						   'genre'=>$v['genre'],
					   ];
					   $number =  Db::name("msg")->where($map2)->count();
					   $lists[$k]['u']['number'] =  $number ;
				   }
                   $lists[$k]['date'] = date('Y-m-d H:i:s', $v['date']); 
				 
               }
               $this->assign('user_id', $user_id);
               if($user_id){
                   //0普通聊天，1客服聊天 ,2财务供应商聊天 3 财务客服聊天
                   $genre =  input('genre');
				   	//改变阅读状态
				   $where = [
                        'uid'=>$user_id,
                        'kf_id'=>$kefu_id,
                        'status'=>0,
                        'genre'=>$genre
                    ];
                    $data = [
                        'status'=>1
                    ];
                    $list = Db::name("msg")->where($where)->select();
                    foreach($list as &$val){
                        db("msg")->where('id',$val['id'])->update($data);
                    }
                   $types =  $genre == 1?3:4;
                   //供应商客服聊天
                   $where =  "(kf_id=" . $kefu_id. " and touid=" . $user_id . ") or (kf_id=" .$kefu_id  . " and uid=" . $user_id . ") and (genre = 2)";
                   if($genre == 3){
                       //财务客服聊天
                       $where =  "(kf_id=" .  $kefu_id. " and touid=" . $user_id . ") or (kf_id=" .$kefu_id  . " and uid=" . $user_id . ") and (genre = 3)";
                   }
                   $readList = $this->getKefumessages($where);
               }
           //财务
           }else if($group_id == 13){
               $cw_id = $userInfo['admin_id'];
               $where =  "(kf_id=" . $cw_id ." or uid=" . $cw_id ." or touid=" . $cw_id ." ) and ((genre = 2) or (genre = 3)) ";
               $res = db("msg_list")->where( $where)->find();
               if(!$res){
                   return $this->fetch();
               }
               $list = db("msg_list")->where($where)->order("date desc")->limit(100)->select();

               foreach ($list as $k => $v) {
                   //2财务供应商聊天 3 财务客服聊天
                   if( $v['genre'] == 2){
                       $kefu = db("users")->where("user_id=" . $v['touid'])->field("user_id,user_name,user_avat")->find();
                       $lists[$k]['u']['user_id'] = $kefu['user_id'];
                       $lists[$k]['u']['user_name'] = $kefu['user_name'];
                       $lists[$k]['u']['user_avat'] = $kefu['user_avat'];
                       $lists[$k]['u']['genre'] = 2;
					    if($v['uid'] != 0){
							$map2 = [
							   'uid'=>$v['uid'],
							   'kf_id'=>$v['kf_id'],
							   'status'=>0,
							   'genre'=>$v['genre'],
						   ];
						}
                   }else{
                       $kefu = db("kefu")->where("kefu_id=" . $v['kf_id'])->field("kefu_id,kefu_name,kefu_avat")->find();
                       $lists[$k]['u']['user_id'] = $kefu['kefu_id'];
                       $lists[$k]['u']['user_name'] = $kefu['kefu_name'];
                       $lists[$k]['u']['user_avat'] = $kefu['kefu_avat'];
                       $lists[$k]['u']['genre'] = 3;
					    if($v['touid'] != 0){
						  $map2 = [
						   'uid'=>$v['uid'],
						   'kf_id'=>$v['kf_id'],
						   'status'=>0,
						   'genre'=>$v['genre'],
							];
						}
                   }
                 //未
				 
				   if($map2){
					   $number =  Db::name("msg")->where($map2)->count();
					   $lists[$k]['u']['number'] =  $number ;
				   }
               }

               $this->assign('user_id', $user_id);
               if($user_id){
                   //0普通聊天，1客服聊天 ,2财务供应商聊天 3 财务客服聊天
                   $genre =  input('genre');
                   $types =  $genre == 2?3:2;
                   //财务客服聊天
                   $where =  "(kf_id=" .  $user_id. " and touid=" . $kefu_id . ") or (kf_id=" .$user_id  . " and uid=" . $kefu_id . ") and (genre = 3)";
                   if($genre == 2){
                       //供应商财务聊天
                           $where =  "(kf_id=" . $kefu_id. " and touid=" . $user_id . ") or (kf_id=" .$kefu_id  . " and uid=" . $user_id . ") and (genre = 2)";
                   }
                   $readList = $this->getKefumessages($where);

                }

            //供应商
           }else if($group_id == 3){
               $supplier_id = $userInfo['supplier_id'];
               $where =  "( touid=" . $supplier_id . ") or  ( uid=" . $supplier_id . ") and (genre !=0)";
               $res = db("msg_list")->where( $where)->find();
               if(!$res){
                   return $this->fetch();
               }
               $list = db("msg_list")->where($where)->order("date desc")->limit(100)->select();
               //0普通聊天，1客服聊天 ,2财务供应商聊天 3 财务客服聊天
               foreach ($list as $k => $v) {
                   if( $v['genre'] == 1){
                      $kefu = db("kefu")->where("kefu_id=" . $v['kf_id'])->field("kefu_id,kefu_name,kefu_avat")->find();
                      $lists[$k]['u']['user_id'] = $kefu['kefu_id'];
                      $lists[$k]['u']['user_name'] = $kefu['kefu_name'];
                      $lists[$k]['u']['user_avat'] = $kefu['kefu_avat'];
                      $lists[$k]['u']['genre'] = 1;
                   }else{
                       $caiwu = db("admin")->where("admin_id=" . $v['kf_id'])->field("admin_id,nickname,avatar")->find();
                       $lists[$k]['u']['user_id'] = $caiwu['admin_id'];
                       $lists[$k]['u']['user_name'] = $caiwu['nickname'];
                       $lists[$k]['u']['user_avat'] = $caiwu['avatar'];
                       $lists[$k]['u']['genre'] = 2;
                   }
                  /*  $map2 = [
                       'uid' => $v['uid'],
                       'kf_id' => $v['kf_id'],
                       'status' => 0,
                   ];
                   $number = Db::name("msg_list")->where($map2)->select();
                   $number = count($number);
                   $lists[$k]['u']['number'] = $number;
                   $lists[$k]['date'] = date('Y-m-d H:i:s', $v['date']); */
               }
               $this->assign('user_id', $user_id);
               if($user_id){
                   //0普通聊天，1客服聊天 ,2财务供应商聊天 3 财务客服聊天
                  $genre =  input('genre');
                  $types =  $genre == 1? 2:4;
				   $where =  "(kf_id=" . $user_id. " and touid=" . $kefu_id . ") or (kf_id=" .$user_id  . " and uid=" . $kefu_id . ") and (genre = 1)";
                  if($genre == 2){
                      //供应商财务聊天
					$where =  "(kf_id=" . $user_id. " and touid=" . $kefu_id . ") or (kf_id=" .$user_id  . " and uid=" . $kefu_id . ") and (genre = 2)";
                   }
 
                   $readList = $this->getKefumessages($where);
               }

           }
        }

        $userMsg =  $this->getUser($userInfo);
        $this->assign('userInfo',$userMsg);
        $this->assign('lists',$lists);
        $this->assign('type',$type);
        $this->assign('types',$types);
        $this->assign('readList',$readList);
        $this->assign('group_id',$group_id);
        return $this->fetch();
    }

    /*
     *消息内容展示
    */
	public function getUser($userInfo){
        $group_id = $userInfo['group_id'];
	 
        //客服
        if($group_id == 9){
            $kefu_id = $userInfo['kf_id'];
            //客服id
			
            $this->assign('kefu_id',$kefu_id);
            $map['kefu_id'] = $kefu_id;

            $row = Db::name('kefu')->where($map)->field('kefu_id,kefu_name,kefu_avat')->find();

            if($row){
                $userInfo['kefu_id'] = $row['kefu_id'];
                $userInfo['kefu_name'] = $row['kefu_name'];
                $userInfo['kefu_avat'] = $row['kefu_avat'];
            }
            //财务
        }else if($group_id == 13){
            $kefu_id = $userInfo['admin_id'];
            //客服id
            $this->assign('kefu_id',$kefu_id);
            $map['admin_id'] = $kefu_id;

            $row = Db::name('admin')->where($map)->field('admin_id,nickname,avatar')->find();
            if($row){
                $userInfo['kefu_id'] = $row['admin_id'];
                $userInfo['kefu_name'] = $row['nickname'];
                $userInfo['kefu_avat'] = $row['avatar'];
            }
            //供应商
        }else if($group_id == 3){
            $supplier_id = $userInfo['supplier_id'];
            $supplier = Db::name('users')
                ->where('user_id', $supplier_id)
                ->field('user_id')
                ->find();
            if($supplier){
                $userInfo['kefu_id'] = $supplier['user_id'];
                $userInfo['kefu_name'] = $supplier['user_name'];
                $userInfo['kefu_avat'] = $supplier['user_avat'];
            }
        }
        return $userInfo;
    }
	/*
     *客服列表
    */
	public function kflist(){
		if(request()->isAjax()){
			$KefuService=new KefuService();
			$order = 'kefu_id asc';
            $map = [];
            $limit = '';
//			$limit=input('get.offset').",".input('get.limit');
//
//			if(input('get.search')){
//				$map['kefu_name']=['like','%'.input('get.search').'%'];
//			}
 			// 供应商订单
			if(session('group_id') == 9){
				$uid = session('admin_id');
				$kf_id =$KefuService->getkefu($uid);
				if($kf_id){
					$map['kefu_id'] = $kf_id;
				}
			}
			$total = $KefuService->count($map);
			$rows = $KefuService->select($map,'*',$order,$limit);
			if($rows){
                foreach ($rows as $val){
                    $val['status'] = $val['status'] == 0?'正常':'退出';
                }
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}
		else{

			return $this->fetch();
		}
	}
	/*
     *客服添加
    */
	public function kfadd(){
		$AdminService=new AdminService();
		 if(request()->isAjax()){
			$row = input('post.row/a');
			$admin_id = input('admin_id',0);
			$map['kefu_id'] = $row['kefu_id'];
			$u_id = input('u_id',0);
			/*if(!($u_id || $admin_id)){
				return json(['status'=>0,'msg'=>'参数错误 请填写完整数据！']);
			}*/
			$map2['admin_id'] = $admin_id;
			$KefuService=new KefuService();
			$res = $KefuService->add($row);
			$kefu_id = Db::name('kefu')->getLastInsID();
			if(!empty($res) && $u_id!=0){
				$AdminService->save($map2,array('kf_id'=>$kefu_id,'u_id'=>$u_id));  
				$this->service->editInfo($u_id,1);
			}
             $this->write_log('增加客服人员',$kefu_id);
             return AjaxReturn($res,getErrorInfo($res));
			//添加日志
		 }else{
			$kefu_id = input('get.ids');
			$row = $this->service->getKfInfo($kefu_id);
			// 用户
			$user=$AdminService->getUser();
			$this->assign('user', $user);
			
			// 客服组
			$map = [
				'group_id'=>9,
				'kf_id'=>['eq',0],
			];
            $admin=$AdminService->select($map);
			$this->assign('admin', $admin);
			

			$this->assign('row',$row);
			return $this->fetch();
		}
	}
	/*
     *客服修改
    */
	public function kfedit(){
		$AdminService=new AdminService();
		$KefuSer= new KefuService();
		$start_time = input('start_time');
        if(request()->isAjax()){
			$row = input('post.row/a');
			$map['kefu_id'] = $row['kefu_id'];
			$u_id = input('u_id');
			$admin_id = input('admin_id');
			$KefuService=new KefuService();
			$rs = $KefuService->save($map,$row);
			$map1['kf_id'] = $row['kefu_id'];
			$map3['admin_id'] = $admin_id;
			//判断选择的管理员是否已经关联了客服
            $is_kf_ad = Db::name('admin')->where(['admin_id'=>$admin_id,'kf_id'=>['neq',$row['kefu_id']]])->find();
            if(!empty($is_kf_ad) && $is_kf_ad['kf_id']!=0){
                return ['code'=>0,'msg'=>'该管理员已有对应的客服，请重新选择！'];
            }
            //判断用户是否有对应的客服
            $is_kf_user = Db::name('admin')->where(['u_id'=>$u_id,'kf_id'=>['neq',$row['kefu_id']]])->find();
            if(!empty($is_kf_user) && $is_kf_user['u_id']!=0){
                return ['code'=>0,'msg'=>'选择的用户已有对应客服，请重新选择'];
            }
            //修改客服
            $res = $AdminService->save($map3,array('kf_id'=>$row['kefu_id'],'u_id'=>$u_id));
//			$res = $AdminService->find($map1);
		/*	if(empty($res)){
                $AdminService->save($map3,array('kf_id'=>$row['kefu_id'],'u_id'=>$u_id));
                $this->service->editInfo($u_id,1);
                return ['code'=>1,'msg'=>'修改成功'];
            }*/
           /* if($res['admin_id'] != $admin_id){
                //判断选择的这个管理员是否已经对应了客服了；如果是，则拒绝修改


                $map2['admin_id'] = $res['admin_id'];
                if($res['u_id'] != $u_id){

                    $AdminService->save($map3,array('kf_id'=>$row['kefu_id'],'u_id'=>$u_id));
                    $this->service->editInfo($res['u_id']);
                    $this->service->editInfo($u_id,1);
                }else{
//						$AdminService->save($map2,array('kf_id'=>0));
                    $AdminService->save($map3,array('kf_id'=>$row['kefu_id']));
                }
            }else{
                if($res['u_id'] != $u_id){
                    $is_kf_user = $AdminService->find(['u_id'=>$u_id]);
                    if($is_kf_user['u_id']!=0){
                        return ['code'=>0,'msg'=>'修改失败','data'=>'选择的用户已经有对应客服了'];
                    }else{
                        $AdminService->save($map3,array('u_id'=>$u_id));
                        $this->service->editInfo($res['u_id']);
                        $this->service->editInfo($u_id,1);
                    }

                }
            }

*/
			return AjaxReturn($res,getErrorInfo($res));
			//添加日志
            $this->write_log('客服修改',$map['category_id']);
		}else{
			$kefu_id = input('get.ids');

			$row = $this->service->getKfInfo($kefu_id);
			// 用户
			$user=$AdminService->getUsers();
			$this->assign('user', $user);
			// 客服组
			$map = [
				'group_id'=>9,
			];
            $admin=$AdminService->select($map);
			$this->assign('admin', $admin);
			$this->assign('row',$row);
			return $this->fetch();
		}
		
	}
	/*
     *客服按钮
     */
	public function isKefu(){
		$map['user_id'] = input('get.uid');
		$data = $this->service->find($map);
		$is_kefu = $data['is_kefu']==0?'1':'0';
		$row ['is_kefu'] = $is_kefu;
		$res=$this->service->save($map,$row);
		return AjaxReturn($res);
	}
	/*
	 * 财务统计
	 */
	public function caiwu()
    {
        if (session('admin_id') == 2) {
            if(request()->isAjax()){
                $phone = session('mobile');
                if (!empty($phone)) {
                    $supplier_id = Db::name('supplier')->where(array('supplier_phone' => $phone))->value('id');
                    if (!empty($supplier_id)) {
                        $goods_map['supplier_id'] = $supplier_id;
                        $goodslists = Db::name('goods')->field('goods_id')->where($goods_map)->select();
                        if ($goodslists) {
                            $ids = [];
                            foreach ($goodslists as $val) {
                                $ids[] = $val['goods_id'];
                            }
                            $order_wheres = [];
                            $order_wheres['og_goods_id'] = ['in', implode(',', $ids)];
                            $total =  Db::name('order_goods')->where($order_wheres)->group('og_goods_id')->count();
                            $order_goods_list = Db::name('order_goods')->where($order_wheres)->group('og_goods_id')->select();
                            if ($order_goods_list) {
                                foreach ($order_goods_list as &$value) {
                                    $goods_info = Db::name('goods')->where(['goods_id' => $value['og_goods_id']])->field('stock,volume')->find();
                                    $value['stock'] = $goods_info['stock'];
                                    $value['volume'] = $goods_info['volume'];
                                }
                                return json(['total'=>$total,'rows'=>$order_goods_list]);
                            } else {
                                return json(['total'=>0,'rows'=>[]]);
                            }
                        } else {
                            return json(['total'=>0,'rows'=>[]]);
                        }
                    } else {
                        return json(['total'=>0,'rows'=>[]]);
                    }
                } else {
                    return json(['total'=>0,'rows'=>[]]);
                }
            }
            else{
                return $this->fetch("gongying");
            }


            exit;
        }
        $Order=db('order');
        //订单
        $map['order_status'] = 4;
        $order=$Order->where($map)->count();
        $this->assign('order',$order);
        //总收入
        $price=$Order->where('order_status',4)->sum('order_pay_price');
        $total_list = $Order->where($map)->select();
        $total_yingli = 0;
        foreach ($total_list as $value) {
            $total_yingli += $this->getYingli($value['order_id']);
        }
        // 总盈利
        $this->assign('total_yingli',$total_yingli?number_format($total_yingli, 2):0);
        $this->assign('price',number_format($price, 2));
        //获取一周订单图表数据
        $start=strtotime(date('Y-m-d',strtotime("-7 days")));
        $end=time();
        $map['order_create_time']=['BETWEEN',[$start,$end]];
        $list=$Order->where($map)->select();
        $DateData=[];

        foreach ($list as $value) {
            $DateData[date('Y-m-d',$value['order_create_time'])]['createdata'][]=$value;
            if($value['order_status']>0){
                $DateData[date('Y-m-d',$value['order_create_time'])]['paydata'][]=$value;
            }
        }

        $OrderData=[];
        for ($i=$start; $i <=$end ; $i=$i+86400) {
            $date=date('Y-m-d',$i);
            $OrderData['column'][]=$date;
            $OrderData['createdata'][]=count($DateData[$date]['createdata']);
            $OrderData['paydata'][]=count($DateData[$date]['paydata']);
        }
        $this->assign('OrderData',json_encode($OrderData));
        //今日订单
        $today_where = [
            'order_create_time' => ['>=', strtotime(date("Y-m-d"))],
            'order_status' => 4
        ];
        $OrderToday=$Order->where($today_where)->count();
        $this->assign('OrderToday',$OrderToday);
        // 今日收入
        $OrderTodayPrice=$Order->where($today_where)->sum('order_pay_price');
        $this->assign('OrderTodayPrice',$OrderTodayPrice?number_format($OrderTodayPrice, 2):0);
        // 今日盈利
        $OrderTodayList=$Order->where($today_where)->select();
        $orderTodayYingli = 0;
        if (!empty($OrderTodayList)) {
            foreach ($OrderTodayList as $val) {
                $orderTodayYingli += $this->getYingli($val['order_id']);
            }
        }
        $this->assign('orderTodayYingli',$orderTodayYingli?number_format($orderTodayYingli, 2):0);

        //未处理订单
        $OrderPost=$Order->where('order_status',1)->count();
        $this->assign('OrderPost',$OrderPost);
        //近一周订单
        $this->assign('OrderWeek',count($list));
        // 近一周订单金额
        $map['order_status'] = 4;
        $OrderWeekPrice=$Order->where($map)->sum('order_pay_price');
        $this->assign('OrderWeekPrice',$OrderWeekPrice?  number_format($OrderWeekPrice, 2):0);
        // 近一周订单盈利
        $OrderWeeklist=$Order->where($map)->select();
        $orderWeekYingli = 0;
        if (!empty($OrderWeeklist)) {
            foreach ($OrderWeeklist as $val) {
                $orderWeekYingli += $this->getYingli($val['order_id']);
            }
        }
        $this->assign('orderWeekYingli',$orderWeekYingli?number_format($orderWeekYingli, 2):0);
        //近一月订单
        $start=strtotime(date('Y-m-d',strtotime("-30 days")));
        $map['order_create_time']=['BETWEEN',[$start,$end]];
        $OrderMonth=$Order->where($map)->count();
        $this->assign('OrderMonth',$OrderMonth);
        // 近一月订单金额
        $map['order_status'] = 4;
        $OrderMonthPrice=$Order->where($map)->sum('order_pay_price');
        $this->assign('OrderMonthPrice',number_format($OrderMonthPrice, 2));
        // 近一月订单盈利
        $OrderMonthlist=$Order->where($map)->select();
        $orderMonthYingli = 0;
        if (!empty($OrderMonthlist)) {
            foreach ($OrderMonthlist as $val) {
                $orderMonthYingli += $this->getYingli($val['order_id']);
            }
        }
        $this->assign('orderMonthYingli',$orderMonthYingli?number_format($orderMonthYingli, 2):0);
        return $this->fetch();
    }
    /*
     * 根据订单号 获取盈利
     */
    public function getYingli($order_id)
    {
        $order_pay = Db::name('order')->where(['order_id' => $order_id])->value("order_pay_price");
        $order_goods = Db::name('order_goods')->where(['og_order_id' => $order_id])->select();
        $price = [];
        $cost_price = [];
        foreach ($order_goods as $val) {
            $goods_sku = Db::name('goods_sku')->where(['sku_id' => $val['og_goods_spec_id']])->find();
            $price[] = $goods_sku['price'];
            $cost_price[] = $goods_sku['cost_price'];
        }
        $total = 0;
        // 订单支付价 减去 成本
        $total = $order_pay - array_sum($cost_price);
        return $total;
    }
    /*
     * 供应商列表
     */
    public function gongYingShang()
    {
//         $start_time = input('start_time',  date('Y-m-d', strtotime(date('Y-m-01'), time())));
//         $end_time = input('end_time',  date('Y-m-d', time()));
//         $this->assign('start_time', $start_time);
//         $this->assign('end_time', $end_time);
        $supplier_title = input('supplier_title', '');
        $supplier_name = input('supplier_name', '');
        $supplier_phone = input('supplier_phone', '');
        $this->assign('supplier_title', $supplier_title);
        $this->assign('supplier_name', $supplier_name);
        $this->assign('supplier_phone', $supplier_phone);
        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');

            $map = [];
            $start_time = input('start_time');
            $end_time = input('end_time');
            if($supplier_title){
                $map['supplier_title']=['like','%'.$supplier_title.'%'];
            }
            if($supplier_name){
                $map['supplier_name']=['like','%'.$supplier_name.'%'];
            }
            if($supplier_phone){
                $map['supplier_phone']=['like','%'.$supplier_phone.'%'];
            }
            $where = [];
            if ($start_time && $end_time) {
                $where['b.order_create_time'] = array('between',strtotime($start_time).','.(strtotime($end_time.' 23:59:59')));
            } elseif ($start_time) {
                $where['b.order_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $where['b.order_create_time'] = array('<=', (strtotime($end_time.' 23:59:59')));
            }
            $where['b.order_status'] = 4;
//            $where['a.og_status'] = ['neq', 1];
            $where['a.og_order_status'] = 4;
            // 选择供应商
            // 供应商权限
            if (session('group_id') == 3) {
                $map['id'] = session('supplier_id');
            }
            $userModel = new UserService();
//            $total = $userModel->getSupplierCount($map);
            $gongyinglist = $userModel->getSupplierList($map);
			 
            // 获取 供应商 总价，及数量
            foreach ($gongyinglist as $key => &$val) {
                $where['a.og_supplier_id'] = $val['id'];

                $ordergoodslist = $userModel->getOrderGoodsList($where);
                $price = 0;
                $number = 0;
                $order_num_arr = [];
                if (!empty($ordergoodslist)) {
                    foreach ($ordergoodslist as $item) {
                        $sku_info = $userModel->getSkuInfo($item['og_goods_spec_id']);
                        $price += $item['og_freight'];
                        if (!empty($sku_info)) {
                            $price += $sku_info['cost_price']*$item['og_goods_num'];
                        }
                        $order_num_arr[] = $item['order_no'];
                        $number += $item['og_goods_num'];

                    }
                }
                $order_num_arr = array_unique($order_num_arr);
                $order_num = count($order_num_arr);
                $val['order_num'] = $order_num;
                $val['price'] = $price;
                $val['number'] = $number;
                if ($number == 0) {
                    unset($gongyinglist[$key]);
                }
            }
            if (!empty($gongyinglist)) {
                $gongyinglists = array_slice($gongyinglist,input('get.offset'), input('get.limit'));
            }
            return json(['total'=>count($gongyinglist),'rows'=>$gongyinglists]);
        }else{

            return $this->fetch();
        }
    }
    /*
     * 供应商结算商品列表
     */
    public function orderGoodsList()
    {
        // 供应商权限
        if (session('group_id') == 3) {
            $ids = session('supplier_id');
        } else {
            $ids = input('ids');
        }

        $this->assign('ids', $ids);
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $guige = input('guige');
        $this->assign('guige', $guige);
        $goods_name = input('goods_name');
        $this->assign('goods_name', $goods_name);
        $goods_id = input('goods_id');
        $this->assign('goods_id', $goods_id);
        $og_status = input('og_status', 'all');
        $this->assign('og_status', $og_status);
		 
        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');

            $map = [];
            // 供应商权限
            if (session('group_id') == 3) {
                $map['a.og_supplier_id']=['eq', session('supplier_id')];
            } else {
                if(input("ids")){
                    $map['a.og_supplier_id']=['eq', input("ids")];
                }
            }
            if(input("ids")){
                $map['a.og_supplier_id']=['eq', input("ids")];
            }
            if(input('og_status') != 'all'){
                $map['a.og_status']=['eq', input("og_status")];
            }
            if(input("goods_id")){
                $map['a.og_goods_id']=['eq', input("goods_id")];
            }
            if(input('goods_name')){
                $map['a.og_goods_name']=['like','%'.input('goods_name').'%'];
            }
            if(input('guige')){
                $map['a.og_goods_spec_val']=['like','%'.input('guige').'%'];
            }
			
            $start_time = input('start_time');
            $end_time = input('end_time');
            if ($start_time && $end_time) {
                $map['b.order_create_time'] = array('between',strtotime($start_time).','.(strtotime($end_time.' 23:59:59')));
            } elseif ($start_time) {
                $map['b.order_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['b.order_create_time'] = array('<=', (strtotime($end_time.' 23:59:59')));
            }
            $map['b.order_status'] = 4;
//             $map['a.og_status'] = ['neq', 1];
            $map['a.og_order_status'] = 4;
            $userModel = new UserService();
            $total = $userModel->getOrderGoodsCount($map);
            $data = $userModel->getOrderGoodsList($map, $limit);
            $total_price = 0;
            $status_arr = ['未结算', '已结算', '暂不处理'];
            if (!empty($data)) {
                foreach ($data as &$val) {
                    $skuinfo = $userModel->getSkuInfo($val['og_goods_spec_id']);
                    $val['price'] = $skuinfo['cost_price'];
                    $val['sku_name'] = $skuinfo['sku_name'];
                    $val['supplier_title'] = $userModel->getSupplier($val['og_supplier_id']);
                    $total_price += $val['price'] * $val['og_goods_num'];
                    $val['og_status'] = $status_arr[$val['og_status']];
                }
            }
            return json(['total'=>$total,'rows'=>$data, 'total_price' => $total_price]);
        }else{
            // 选择供应商
            $userModel = new UserService();
            $gongyinglist = $userModel->getSupplierList();
            $this->assign('gongyinglist', $gongyinglist);
            return $this->fetch();
        }
    }
    /**
     * 结算
     */
    public function jieSuan($ids)
    {
        $end_time =time() - 3600*24*15; // 排除 15天 退换货时间
        $map = [
            'a.og_id' => ['in', implode(',', $ids)]
        ];
        $userModel = new UserService();
        $data = $userModel->getOrderGoodsList($map);
        $settlement_data = [];
        $num = 0;
        $og_ids_total= [];
        if (!empty($data)) {
            $supper_info = array_column($data, 'og_supplier_id');

            $supper_info = array_unique($supper_info);
            foreach ($supper_info as $k => $v) {
                $total_price = 0;
                $og_ids = [];
                foreach ($data as $val) {
                    if ($val['order_goods_ok_time'] > $end_time || $val['og_status'] == 1 || $val['og_status'] == 2) {
                        // 过滤时间 在 售后 范围的订单
                        $num ++;
                        continue;
                    }

                    if ($v == $val['og_supplier_id']) {
                        $skuinfo = $userModel->getSkuInfo($val['og_goods_spec_id']);
                        $price = $skuinfo['cost_price'];
                        $total_price += $price * $val['og_goods_num'];
                        $total_price += $val['og_freight'];
                        $og_ids[] = $val['og_id'];
                        $og_ids_total[] = $val['og_id'];
                    }

                }
                $settlement_data[$k] = [
                    'supplier_id' => $v,
                    'ordergoods_ids' => $og_ids ? implode(',', $og_ids) : '',
                    'total_price' => $total_price,
                    'applicant_id' => session('admin_id'),
                    'applicant' => session('admin_name'),
                    'add_time' => time()
                ];
            }

        }
        if ($num == count($data)) {
            return false;
        }
        $res = Db::name('settlement')->insertAll($settlement_data);

        //添加日志记录
        $id=db('settlement')->getLastInsID();
        $this->write_log('供应商结算',$id);

        if ($res) {
            // 修改 商品订单表状态 改为 已结算
            Db::name('order_goods')->where(['og_id' => ['in', implode(',',$og_ids_total)]])->update(['og_status' => 1]);

            return true;
        } else {
            return false;
        }

    }
    /*
     * 结算申请记录
     */
    public function reckoning()
    {
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $status = input('status', 'all');
        $this->assign('status', $status);
        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');

            $map = '';
            // 供应商权限
            if (session('group_id') == 3) {
                $supp_id = session('supplier_id');
                $map['supplier_id'] = $supp_id;
            }

            if(input('status') != 'all'){
                $map['status']=['eq', input("status", 0)];
            }
            $start_time = input('start_time');
            $end_time = input('end_time');
            if ($start_time && $end_time) {
                $map['add_time'] = array('between',strtotime($start_time).','.(strtotime($end_time.' 23:59:59')));
            } elseif ($start_time) {
                $map['add_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['add_time'] = array('<=', (strtotime($end_time.' 23:59:59')));
            }
           $SettlementModel = new Settlement();
           $total = $SettlementModel->count($map);
           $data =$SettlementModel->select($map, "*", "id desc", $limit);
            $userModel = new UserService();
			$page_price = 0;
           if (!empty($data)) {
               $status_arr = ['未审核','审核通过','审核失败'];
               $jiesuan_arr = ['','周结','月结'];
               $total_price = Db::name('settlement')->where($map)->sum('total_price');
               foreach ($data as $key=>$val) {
                   $val['supplier_name'] = $userModel->getSupplier($val['supplier_id']);
                   $val['end_time'] = date('Y-m-d H:i', $val['end_time']);
                   $val['add_time'] = date('Y-m-d H:i', $val['add_time']);
                   $val['examine_time'] =$val['examine_time']?date('Y-m-d H:i:s', $val['examine_time']) : '';
                   $val['status'] = $status_arr[$val['status']];
                   $jiesuan_type = $userModel->getSupplierJiesuan(['id' => $val['supplier_id']]);
                   $val['jiesuan'] = $jiesuan_arr[$jiesuan_type];
					//总计销售额
					$page_price += $val['total_price'];
					$data[$key]['pageprice'] = round($page_price,2);
					$data[$key]['totalprice'] = round($total_price,2);
               }
           }
           return json(['total'=>$total,'rows'=>$data]);
        }else{
            return $this->fetch();
        }
    }
    /*
     * 结账单 查看详情
     */
    public function showReckoning()
    {
        $ids = input('ids/d');
        if (empty($ids)) {
            return $this->error('参数错误');
        }
        $this->assign('ids', $ids);
        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');

            $id = input('ids/d');
            if (empty($id)) {
                return $this->error('参数错误');
            }
            $map = [];
            $map['id'] = $id;
            $SettlementModel = new Settlement();
            $info =$SettlementModel->find($map);
            $userModel = new UserService();
            $where = [];
            $where['a.og_id'] = ['in', $info['ordergoods_ids']];
            $total = $userModel->getOrderGoodsCount($where);
            $data = $userModel->getOrderGoodsList($where, $limit);
            $status_arr = ['未结算', '已结算', '暂不处理'];
            if (!empty($data)) {
                foreach ($data as &$val) {
                    $skuinfo = $userModel->getSkuInfo($val['og_goods_spec_id']);
                    $val['price'] = $skuinfo['cost_price'];
                    $val['sku_name'] = $skuinfo['sku_name'];
                    $val['supplier_title'] = $userModel->getSupplier($val['og_supplier_id']);
                    $val['og_status'] = $status_arr[$val['og_status']];
                }
            }
            return json(['total'=>$total,'rows'=>$data]);
        }else{
            return $this->fetch();
        }

    }
    /*
     * 审核结账单
     */
    public function shenhereckoning()
    {
		$SettlementModel = new Settlement();

		if(request()->isAjax()){
			$row=input('post.row/a');
			$map['id']=input('post.id');
			$row['auditor_id'] = session('admin_id');
			$row['examine_time'] = time();

			$adminInfo = Db::name('admin')->where('admin_id',$row['auditor_id'])->find();
			$row['auditor'] = $adminInfo['admin_name'];
			$res=$SettlementModel->save($map,$row);

            //添加日志记录
            $this->write_log('审核结账单',$row['auditor_id']);

			 if($res !==false) return json(['status'=>1,'msg'=>'操作成功!']);
            else return json(['status'=>0,'msg'=>'网络有点忙，请稍后进行操作！']);
		}else{
			$map['id']=input('get.ids');
			$row=$SettlementModel->find($map);
			$userModel = new UserService();
			if($row){
				$row['supplier_id'] =$userModel->getSupplier($row['supplier_id']);
				$row['start_time']  = date('Y-m-d H:i:s',$row['start_time']);
				$row['end_time']  = date('Y-m-d H:i:s',$row['end_time']);
			}
			$this->assign('row',$row);
			return $this->fetch();
		}
    }
    /*
     * 未结算订单
     */
    public function dingdan()
    {
        $order_id = input('order_id');
        $this->assign('order_id', $order_id);
        $supper_name = input('supper_name');
        $this->assign('supper_name', $supper_name);

        $og_status = input('og_status', 'all');
        $this->assign('og_status', $og_status);
        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');

            $map = [];
            $order_id = input('order_id');
            $supper_name = input('supper_name');
            if ($order_id) {
                $map['b.order_no'] = $order_id;
            }
            $userModel = new UserService();
            if ($supper_name) {
                $supp_info = $userModel->getSupplierInfo(['supplier_title' => ['like', "%$supper_name%"]],'id');
                $map['a.og_supplier_id']=['eq', $supp_info['id']];
            }
            // 供应商权限
            if (session('group_id') == 3) {
                $map['a.og_supplier_id']=['eq', session('supplier_id')];
            }
            if($og_status != 'all'){
                if ($og_status == 3) {
                    // 未到结算日期的商品
                    $map['a.order_goods_ok_time']=['>', time()-3600*24*15]; // 15天售后
                    $map['a.og_status'] = ['=', 0];
                } elseif ($og_status == 4) {
                    // 结算日期的商品
//                    $map['a.og_order_status']=['=', 4];// 交易完成
                    $map['a.order_goods_ok_time']=['<', time()-3600*24*15]; // 15天售后
                    $map['a.og_status'] = ['=', 0];
                }  else {
                    $map['a.og_status']=['eq', $og_status];
                }
            } else {
                $map['a.og_status'] = ['neq', 1];
            }

            $map['b.order_status'] = 4;
            $map['a.og_order_status'] = 4;
            $total = $userModel->getOrderGoodsCount($map);
            $data = $userModel->getOrderGoodsList($map, $limit);
            $total_price = 0;
            $status_arr = ['未结算', '已结算', '暂不处理'];
            $jiesuan_arr = ['','周结','月结'];
            if (!empty($data)) {
                foreach ($data as &$val) {
                    $skuinfo = $userModel->getSkuInfo($val['og_goods_spec_id']);
                    $val['price'] = $skuinfo['cost_price'];
                    $val['sku_name'] = $skuinfo['sku_name'];
                    $supplier_info = $userModel->getSupplierInfo(['id' => $val['og_supplier_id']], 'supplier_title,supplier_phone');
                    $val['supplier_title'] = $supplier_info['supplier_title'];
                    $val['supplier_phone'] = $supplier_info['supplier_phone'];
                    $total_price += $val['price'] * $val['og_goods_num'];
                    $val['og_status'] = $status_arr[$val['og_status']];
                    // 获取商品 货号 和编号
                    $goods_info = $userModel->getgoodsInfo($val['og_goods_id'], 'goods_numbers,cargo_numbers');
                    $val['cargo_numbers'] = $goods_info['cargo_numbers'];
                    $val['goods_numbers'] = $goods_info['goods_numbers'];
                    // 结算方式
                    $jiesuan_type = $userModel->getSupplierJiesuan(['id' => $val['og_supplier_id']]);
                    $val['jiesuan'] = $jiesuan_arr[$jiesuan_type];
                }
            }
            return json(['total'=>$total,'rows'=>$data, 'total_price' => $total_price]);
        }else{
            // 选择供应商
            $userModel = new UserService();
            $gongyinglist = $userModel->getSupplierList();
            $this->assign('gongyinglist', $gongyinglist);
            return $this->fetch();
        }
    }
    /*
     * 订单商品处理
     * 暂不处理
     */
    public function chuliOrderGoods()
    {
        $og_id = input('ids');
        if(request()->isAjax()){

            $og_id = input('og_id');
            $og_remark = input('remark');
            if (empty($og_id) || empty($og_remark)) {
                return $this->error('参数错误');
            }
            $res = Db::name('order_goods')->where(['og_id' => ['in', $og_id]])->update(['og_status' => 2, 'og_remark' => $og_remark]);

            //添加日志记录
            $this->write_log('订单暂不处理',$og_id);

            return AjaxReturn($res);
        }else{
		 
            $this->assign('og_id',$og_id);
            return $this->fetch();
        }

    }
    /*
     * 订单商品处理
     * 取消暂不处理
     */
    public function chuliwanOrderGoods()
    {
        $og_id = input('ids');
        if (empty($og_id)) {
            return $this->error('参数错误');
        }
        $res = Db::name('order_goods')->where(['og_id' => $og_id])->update(['og_status' => 0, 'og_remark' => '']);

        //添加日志记录
        $this->write_log('订单取消暂不处理', $og_id);
		return AjaxReturn($res);
    }
    // 结算单 待确认
    public function jiesuandan(){

        $supplier_title = input('supplier_title');
        $supplier_name = input('supplier_name');
        $this->assign('supplier_title', $supplier_title);
        $supplier_phone = input('supplier_phone');
        $this->assign('supplier_name', $supplier_name);
        $this->assign('supplier_phone', $supplier_phone);
        if(request()->isAjax()){
        	//echo "string";die;
            // limit
            $limit=input('get.offset').",".input('get.limit');

            $map = [];
            $userModel = new UserService();
            if ($supplier_name || $supplier_phone || $supplier_title) {
                $supp_where = [];
                if ($supplier_name) {
                    $supp_where['supplier_name'] = ['like', "%$supplier_name%"];
                }
                if ($supplier_phone) {
                    $supp_where['supplier_phone'] = ['like', "%$supplier_phone%"];
                }
                if ($supplier_title) {
                    $supp_where['supplier_title'] = ['like', "%$supplier_title%"];
                }
                $supp_info = $userModel->getSupplierList($supp_where,'100');
                if ($supp_info) {
                    $supp_ids = array_column($supp_info, 'id');
                    $map['supplier_id']=['in', implode(',', $supp_ids)];
                } else {
                    // 查不到数据
                    $map['supplier_id'] = -1;
                }

            }
            // 供应商权限
            if (session('group_id') == 3) {
                $map['supplier_id']=['eq', session('supplier_id')];
            }

            $map['status'] = 1;

            $total = $userModel->getJesuanDan($map);
            $data = $userModel->getJesuanDan($map, '*', $limit, true, 'a.supplier_id');
            $status_arr = ['','待确认', '已确认', '已结算'];
            $jiesuan_arr = ['','周结','月结'];
            $total_price = 0;
            if (!empty($data)) {
                foreach ($data as &$val) {
                    $supplier_info = $userModel->getSupplierInfo(['id' => $val['supplier_id']], 'supplier_title,supplier_phone');
                    $val['supplier_title'] = $supplier_info['supplier_title'];
                    $val['supplier_phone'] = $supplier_info['supplier_phone'];
                    $total_price += $val['total_price'];
                    $val['status'] = $status_arr[$val['status']];
                    // 结算方式
                    $jiesuan_type = $userModel->getSupplierJiesuan(['id' => $val['supplier_id']]);
                    $val['jiesuan'] = $jiesuan_arr[$jiesuan_type];
                    $val['status'] = '待确认';
                }
            }
            return json(['total'=>$total,'rows'=>$data, 'total_price' => $total_price]);
        }else{
            return $this->fetch();
        }
    }
    /*
     * 结算单
     * 历史记录
     */
    public function jiesuandanhis()
    {
        $supplier_title = input('supplier_title');
        $supplier_name = input('supplier_name');
        $this->assign('supplier_title', $supplier_title);
        $supplier_phone = input('supplier_phone');
        $this->assign('supplier_name', $supplier_name);
        $this->assign('supplier_phone', $supplier_phone);

        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');

            $map = [];
            $supper_name = input('supper_name');
            $userModel = new UserService();
            if ($supplier_name || $supplier_phone || $supplier_title) {
                $supp_where = [];
                if ($supplier_name) {
                    $supp_where['supplier_name'] = ['like', "%$supplier_name%"];
                }
                if ($supplier_phone) {
                    $supp_where['supplier_phone'] = ['like', "%$supplier_phone%"];
                }
                if ($supplier_title) {
                    $supp_where['supplier_title'] = ['like', "%$supplier_title%"];
                }
                $supp_info = $userModel->getSupplierList($supp_where,'100');
                if ($supp_info) {
                    $supp_ids = array_column($supp_info, 'id');
                    $map['supplier_id']=['in', implode(',', $supp_ids)];
                } else {
                    // 查不到数据
                    $map['supplier_id'] = -1;
                }

            }
            // 供应商权限
            if (session('group_id') == 3) {
                $map['supplier_id']=['eq', session('supplier_id')];
            }


            $map['status'] = ['>', 1];

            $total = $userModel->getJesuanDanHis($map);
            $data = $userModel->getJesuanDanHis($map, '*', $limit, true);
            $status_arr = ['','待确认', '已确认', '已结算'];
            $jiesuan_arr = ['','周结','月结'];
            $total_price = 0;
            if (!empty($data)) {
                foreach ($data as &$val) {
                    $supplier_info = $userModel->getSupplierInfo(['id' => $val['supplier_id']], 'supplier_title,supplier_phone');
                    $val['supplier_title'] = $supplier_info['supplier_title'];
                    $val['supplier_phone'] = $supplier_info['supplier_phone'];
                    $total_price += $val['price'];
                    $val['status'] = $status_arr[$val['status']];
                    // 结算方式
                    $jiesuan_type = $userModel->getSupplierJiesuan(['id' => $val['supplier_id']]);
                    $val['jiesuan'] = $jiesuan_arr[$jiesuan_type];
                    $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                    $val['confirm_time'] = $val['confirm_time']? date('Y-m-d H:i:s', $val['confirm_time']) : '';
                    $val['jiesuan_time'] = $val['jiesuan_time'] ? date('Y-m-d H:i:s', $val['jiesuan_time']): '';

                }
            }
            return json(['total'=>$total,'rows'=>$data, 'total_price' => $total_price]);
        }else{
            return $this->fetch();
        }
    }
    /*
     * 结算单 详情
     */
    public function dingdanInfo()
    {
        $supper_id = input('supper_id');
        $this->assign('supper_id', $supper_id);
        $sett_id = input('sett_id');
        $this->assign('sett_id', $sett_id);
        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');

            $map = [];
            if ($supper_id) {
                $map['supplier_id']=$supper_id;
//                $map['status'] = 1;
            }
            if ($sett_id) {
                $map['id']=$sett_id;
//                $map['status'] = 1;
            }
            // 供应商权限
            if (session('group_id') == 3) {
                $map['supplier_id']=['eq', session('supplier_id')];
            }


            $userModel = new UserService();
//            $total = $userModel->getJesuanDan($map);
            $data = $userModel->getJesuanDaninfo($map,'*', $limit, true);
            $total_price = 0;
            $status_arr = ['可以结算', '已结算', '暂不处理'];
            $jiesuan_arr = ['','周结','月结'];
            if (!empty($data)) {
                foreach ($data as &$val) {
                    $skuinfo = $userModel->getSkuInfo($val['og_goods_spec_id']);
                    $val['price'] = $skuinfo['cost_price'];
                    $val['sku_name'] = $skuinfo['sku_name'];
                    $supplier_info = $userModel->getSupplierInfo(['id' => $val['og_supplier_id']], 'supplier_title,supplier_phone');
                    $val['supplier_title'] = $supplier_info['supplier_title'];
                    $val['supplier_phone'] = $supplier_info['supplier_phone'];
                    $total_price += $val['price'] * $val['og_goods_num'];
                    $val['sv_status'] = $status_arr[$val['sv_status']];
                    // 获取商品 货号 和编号
                    $goods_info = $userModel->getgoodsInfo($val['og_goods_id'], 'goods_numbers,cargo_numbers');
                    $val['cargo_numbers'] = $goods_info['cargo_numbers'];
                    $val['goods_numbers'] = $goods_info['goods_numbers'];
                    // 结算方式
                    $jiesuan_type = $userModel->getSupplierJiesuan(['id' => $val['og_supplier_id']]);
                    $val['jiesuan'] = $jiesuan_arr[$jiesuan_type];
                    // 获取订单 单号
                    $val['order_no'] = Db::name('order')->where(['order_id' => $val['og_order_id']])->value('order_no');
                }
            }
            return json(['total'=>count($data),'rows'=>$data, 'total_price' => $total_price]);
        }else{
            return $this->fetch();
        }
    }
    /*
     * 供应商确认
     */
    public function gysqueren()
    {
        $sett_id = (int)input('sett_id');
        if (empty($sett_id)) {
            $this->error('参数错误');
        }
        $where = [
            'id' => $sett_id,
            'status' => 1
        ];
        $data = [
            'status' => 2,
            'confirm_time' => time()
        ];
        // 验证权限
        if (session('group_id') == 3) {
            $where['supplier_id']=['eq', session('supplier_id')];
        }

        $res = Db::name('settlement')->where($where)->update($data);

        $this->write_log('供应商确认,名称：'.session('admin_name'),$sett_id);
        return AjaxReturn($res);
    }
    /*
     * 财务打款
     */
    public function cwDakuan()
    {
        $sett_id = input('sett_id');
        if (empty($sett_id)) {
            $this->error('参数出错误');
        }
        $data = [
            'auditor_id' => session('admin_id'),
            'auditor' => session('admin_name'),
            'status' => 3,
            'jiesuan_time' => time()
        ];
        $res = Db::name('settlement')->where(['id' => $sett_id])->update($data);
        $this->write_log('财务打款,名称：'.session('admin_name'),$sett_id);
        return AjaxReturn($res);
    }
    /*
     * 财务统计
     */
    public function tongji(){
        $stime_1 = input('stime_1');
        $etime_1 = input('etime_1');
        $this->assign('stime_1', $stime_1);
        $this->assign('etime_1', $etime_1);
        $month_s = date('Y-m');
        $month_e = date('Y-m-d', strtotime("$month_s +1 month -1 day"));
        //$stime_1 = $stime_1 ? strtotime($stime_1) : strtotime($month_s);
        $etime_1 = $etime_1 ? strtotime($etime_1) : strtotime($month_e);
        $stime_1 = $stime_1 ? strtotime($stime_1) : time();

        //求出总共天数
        $t = date('t',$stime_1);

        /* 饼状图 数据 商品成本、平台获利，返利利润、抵扣金额
         * 1、查出 订单所有交易成功 的数据
         * 2、根据订单商品查成本、抵扣金额、
         * 3、查出返利利润
         * 4、根据订单总金额 - 商品成本 - 抵扣金额 - 返利利润  = 平台盈利
         **/
        $user_model = new UserService();
//        $where = [
//        	'a.order_finish_time' => [
//        		['egt', $stime_1],
//        		['lt', $etime_1],
//        	],
//        ];
        for($d = 1; $d <= $t; $d++ ){
            $data_xx[] = date('Y.m.'.$d, $stime_1);
            $y_s_time = strtotime(date('Y-m-'.$d.' 00:00:00', $stime_1));
            $y_e_time = strtotime(date('Y-m-'.$d.' 23:59:59', $stime_1));
            //找出当天的订单数据
            $where=[
                'a.order_finish_time' => [
                    ['egt', $y_s_time],
                    ['elt', $y_e_time],
                ],
            ];
            $order_list = Db::name('order')->alias('a')->join('__COMMISSION__ b', 'a.order_id=b.commi_order_id','left')->where($where)->field('a.order_id,a.order_all_price,a.order_pay_price,a.order_coupon_id,a.yz_id,b.goods_profit,a.order_pay_points')->select();
            $all_price = 0.00;			// 总金额
            $cost_price = 0.00;			// 成本
            $discount = 0.00;			// 抵扣
            $commission = 0.00;			// 返利
            $profit = 0.00;				// 盈利

            foreach($order_list as $v){
                // 总金额
                $all_price += $v['order_pay_price'];
                // 成本
                $order_goods = Db::name('order_goods')->alias('a')
                    //->join('__GOODS_SKU__ b', 'a.og_goods_id=b.goods_id')
                    ->join('__GOODS_SKU__ b', 'a.og_goods_spec_id=b.sku_id')
                    ->field('a.og_goods_num,b.cost_price')
                    ->field('a.og_goods_num')
                    ->where(['a.og_order_id' => $v['order_id']])->select();

                foreach($order_goods as $var){
                    $cost_price += $var['og_goods_num'] * $var['cost_price'];
                }
                // 抵扣
                $discount+= $v['order_pay_points'];
                /*if($v['order_coupon_id']){
                    $discount += Db::name('coupon_users')->where(['c_id' => $v['order_coupon_id']])->value('c_coupon_price');
                }
                if($v['yz_id']){
                    $discount += Db::name('yinzi')->where('yin_id', $v['yz_id'])->value('yin_amount');
                }*/
                // 返利
                $commission += $v['goods_profit'];
            }

            $all[] = round($all_price,2);
            $cost[] =round($cost_price,2);
            $discounts[] =round($discount,2);
            $commissions[] =round($commission,2);
            $aaa = round($all_price,2)-round($cost_price,2)-round($discount,2)-round($commission,2);

            /*if ($aaa<0){
                $aaa = 0;
            }*/
            $profit_price[] = $aaa;
        }

        $this->assign('all', json_encode($all));
        $this->assign('cost', json_encode($cost));
        $this->assign('discounts', json_encode($discounts));
        $this->assign('commissions', json_encode($commissions));
        $this->assign('profit', json_encode($profit_price));
        $this->assign('data_xx', json_encode($data_xx));
        //$order_list = Db::name('order')->alias('a')->join('__COMMISSION__ b', 'a.order_id=b.commi_order_id','left')->where($where)->field('a.order_id,a.order_all_price,a.order_pay_price,a.order_coupon_id,a.yz_id,b.goods_profit,a.order_pay_points')->select();



//        $data_stat = [
//        	'cost' => round($cost_price, 2),
//        	'discount' => round($discount, 2),
//        	'commission' => round($commission, 2),
//        ];

//        $data_stat['profit'] = $all_price - $data_stat['cost'] - $data_stat['discount'] - $data_stat['commission'];
//        $this->assign('data_stat', json_encode($data_stat));
        
        /* 波浪图 销售利润 */
        $stime_2 = input('stime_2');
        $etime_2 = input('etime_2');
        $this->assign('stime_2', $stime_2);
        $this->assign('etime_2', $etime_2);
        if($stime_2 && $etime_2){
        	$s_month = $stime_2;
        	$now_month = $etime_2;
        }
        else if($stime_2 && !$etime_2){
        	$s_month = $stime_2;
        	$now_month = date('Y-m');
        	$a = strtotime($s_month);
        }
        else if(!$stime_2 && $etime_2){        
        	$now_month = $etime_2;
        	$s_month = date('Y-m', strtotime("$now_month -5 month"));
        }
        else{
        	$now_month = date('Y-m');
        	//$s_month = date('Y-m', strtotime("$now_month -5 month"));
        	$s_month = date('Y-m', time());
        	$a = time();
        }
        $data_x = [];
        $data_y = [];
        $y_val = [];

        //求出总共天数
        $tt = date('t',$a);

        //  for($time = $s_month; $time <= $now_month; $time = date('Y-m',strtotime("$time +1 month"))){
        for($time = 1; $time <= $tt; $time++ ){
        	$data_x[] = date('Y.m.'.$time, $a);
            //$data_x[] = date('Y.m.d', time()+$time*24*3600);
        	//$data_x[] = date('Y-m-'.$time, time());
        	//$y_s_time = strtotime($time);
        	//$y_e_time = strtotime("$time +1 month -1 second");
            $y_s_time = strtotime(date('Y-m-'.$time.' 00:00:00', $a));
            $y_e_time = strtotime(date('Y-m-'.$time.' 23:59:59', $a));
        	$val = Db::name('order')->where(['order_finish_time' => [['egt', $y_s_time], ['elt', $y_e_time]]])->sum('order_pay_price');
        	$y_val[] = round($val, 2);
        }
        // $y_dis = $this->yDataFormat(max($y_val))['val'] / 6;
        // for($i = 0; $i<=6 ; $i++){
        // 	$data_y[] = $i * $y_dis;
        // }
        $this->assign('data_x', json_encode($data_x));
        // $this->assign('data_y', json_encode($data_y));
        $this->assign('y_val', json_encode($y_val));
        return $this->fetch();
    }

    /* 
     * 数值处理
     */
    public function yDataFormat($val){
    	$val = round($val, 0);
    	$l = strlen($val);
    	$n = pow(10, $l - 1);
    	$val = $val / $n;
    	$val = ceil($val) * $n;
    	$val = (int)$val;
    	do{	
    		if(!is_int($val / 6)){    			
    			$val += $n;
    			$stat = true;
    		}
    		else $stat = false;
    	}
    	while($stat);
    	return ['val' => $val];
    }


	/*
	 * 素材管理
	 */
	public function sucai()
    {
		$user_name = trim(input('user_name'));
		$goods_name = trim(input('goods_name'));
		$m_cat_id = trim(input('m_cat_id'));
		$mate_status = trim(input('mate_status'));
		$m_uid=input('m_uid');
		  $this->assign('m_uid', $m_uid);
        $this->assign('user_name', $user_name);
        $this->assign('goods_name', $goods_name);
        $this->assign('m_cat_id', $m_cat_id);
        $this->assign('mate_status', $mate_status);
        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');

            //查询
			//            if(input('get.uid')){
			//                $map['uid']=input('get.uid');
			//            }
			$map = '';

			if(input('user_name')){
			
				$map1['user_name']=['like','%'.input('user_name').'%'];
				$id_arr = Db::name('users')->where($map1)->column('user_id');
				if($id_arr ){
					$id_str = implode(',',$id_arr);
					$map['m_uid']=['in',$id_str];
				}else if(input('user_name') == '合陶官方'){
					$map['m_uid']=['eq',0];
				}
			}  
			if(input('m_cat_id')){
				$map['m_cat_id']=['eq',input('m_cat_id')];
			}	
			if(input('mate_status')=='all'){
				$map['mate_status']=['in','0,1'];
			}else if(input('mate_status')!=null){
				$map['mate_status']=['eq',input('mate_status')];
			}
			if(input('goods_name')){
				$where['goods_name']=['like','%'.input('goods_name').'%'];
				$goodsId_arr = Db::name('goods')->where($where)->column('goods_id');
				$goodsId_str = implode(',',$goodsId_arr);
				if($goodsId_str){
					$map['m_goods_id']=['in',$goodsId_str];
				}else{
					 return json('');
				}
			}

            $userModel = new UserService();
            $data = $userModel->getSucaiList($limit,$map);
            $zhiding_arr = ['否','是'];
            $status_arr = ['否', '是'];
            if ($data['total'] > 0) {
                foreach ($data['rows'] as &$val) {
                    $val['goods_name'] = $userModel->getGoodsName($val['m_goods_id']);
                    $val['mate_add_time'] = date('Y-m-d H:i:s', $val['mate_add_time']);
                    $val['mate_status'] = $status_arr[$val['mate_status']];
                    $val['mate_zhiding'] = $zhiding_arr[$val['mate_zhiding']];
                }
            }
            return json($data);
        }else{
			
			$mat_cat = Db('material_category')->where(array('status'=>'normal','type'=>1))->select();
			$this->assign('mat_cat',$mat_cat);
            return $this->fetch();
        }
    }
	/*
	 * 素材审核管理
	 */
	public function checkList()
    {
		$mate_status = input('id');
        if(request()->isAjax()){

            // limit
            $limit=input('get.offset').",".input('get.limit');
			//0待审核；1审核通过 2审核失败
			$map['mate_status']= input('id');
            $userModel = new UserService();
            $data = $userModel->getSucaiStatus($map,$limit);
            $zhiding_arr = ['否','是'];
            $status_arr = ['否', '是'];
            if ($data['total'] > 0) {
                foreach ($data['rows'] as &$val) {
                    $val['goods_name'] = $userModel->getGoodsName($val['m_goods_id']);
                    $val['mate_add_time'] = date('Y-m-d H:i:s', $val['mate_add_time']);
                    $val['mate_status'] = $status_arr[$val['mate_status']];
                    $val['mate_zhiding'] = $zhiding_arr[$val['mate_zhiding']];
                }
            }
			 //日志记录
		$add['uid'] = session('admin_id');
		$add['ip_address'] = request()->ip();
		$add['controller'] = request()->controller();
		$add['action'] = request()->action();
		$add['remarks'] = '审核管理';
		$add['number'] =  $map['mate_status'];
		$add['create_at'] = time();
			return json($data);
        }else{
			 $this->assign('mate_status',$mate_status);
            return $this->fetch();
        }
    }
    /*
     * 素材显示隐藏 置顶取消置顶
     */
    public function sucaiMulti()
    {
        $action = input('action');
        $ids = input('ids/a');
        $status = input('params');
        $userModel = new UserService();
        $res = $userModel->sucaiEdit(['m_id' => ['in', $ids]], [$action => $status]);
		 //日志记录
		$add['uid'] = session('admin_id');
		$add['ip_address'] = request()->ip();
		$add['controller'] = request()->controller();
		$add['action'] = request()->action();
		$add['remarks'] = '置顶状态改变';
		$add['number'] = $ids;
		$add['create_at'] = time();
		db('web_log')->insert($add);
		if($res!==false){
			 return AjaxReturn(true);
		}
        return AjaxReturn($res);

    }
	/*
     *  置顶按钮
     */
    public function sucaiTop()
    {
        $map['m_id'] = input('get.id');
        $map['m_uid'] = input('get.uid');
		$userModel = new UserService();
		$res = $userModel->sucaiTop($map);
		 //日志记录
		$add['uid'] = session('admin_id');
		$add['ip_address'] = request()->ip();
		$add['controller'] = request()->controller();
		$add['action'] = request()->action();
		$add['remarks'] = '素材置顶';
		$add['number'] =  $map['m_id'];
		$add['create_at'] = time();
		db('web_log')->insert($add);
		return AjaxReturn($res);

    }

    /*
    * 素材添加
    * */
    public function sucaiAdd(){
            if(request()->isAjax()){
                    $row=input('post.row/a');
					//var_dump($row);die;
                    if($row){
						  $row['m_uid'] = $row['m_uid']?$row['m_uid']:1;  
                            $row['mate_add_time']=time();

                             $res=db('users_material')->insertGetId($row);
                        }
           
         
            //添加日志记录
            $id=db('topic')->getLastInsID();
            $this->write_log('素材添加',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
			//查询出素材分类
			$map['type']=['eq',1];
			$map['pid']=['eq',0];
		
			$category=db('material_category')->where($map)->select();
		  
			$this->assign('material',$category);
			//获取分类列表
			$rows=db('goods_category')->where('pid',0)->select();
			//转为树形
			$rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
			$this->assign('goodCategory',$rows);
			return $this->fetch();
        }
    }
	/*
    * 素材修改
    * 
	*/
    public function sucaiedit(){
		  if(request()->isAjax()){
			$row=input('post.row/a');
			$map['m_id'] = input('post.m_id');
			$row['mate_add_time'] = strtotime($row['mate_add_time']);
            $res = db('users_material')->where($map)->update($row);
			if($res===false){
				 //添加日志记录
				$this->write_log('素材修改',$map['m_id']);
			}
            return AjaxReturn($res,getErrorInfo($res));
        }else{
			$map['category_id'] = input('get.ids');
			$userModel = new UserService();
			$row = $userModel->getSucaiShow($map);
			if($row){
				$map['goods_id']=$row['m_goods_id'];
				$goods_name = Db::name('goods')->field('goods_name')->find($map);
				$cat_name = Db::name('material_category')->where('cat_id',$row['m_cat_id'])->field('cat_name')->find();
				if($goods_name){
					$row['goods_name'] = $goods_name['goods_name'];
					$row['cat_name'] = $cat_name['cat_name'];
				}
			}
			$this->assign('row',$row);
			return $this->fetch();
        }
	}
    /*
     * 查询用户名是否存在
     * */
    public function nameCheck(){
            $name = input('get.name');
            $UserService= new UserService();
            $map['user_name']=['eq',$name];
            $res= $UserService->find($map,'user_id');
            if($res){
                    return json(['rows'=>$res]);
        }else{
                    return json(0);
        }
    }

	 /*
     * 素材显示
     */
    public function sucaiShow()
    {
		$map['category_id'] = input('get.ids');
		$userModel = new UserService();
		$row = $userModel->getSucaiShow($map);
		$status_arr = ['不加精', '加精'];
		if($row){
			$row['mate_status'] =$status_arr[$row['mate_status']];
			$map['goods_id']=$row['m_goods_id'];
			$goods_name = Db::name('goods')->field('goods_name')->find($map);
			$cat_name = Db::name('material_category')->where('cat_id',$row['m_cat_id'])->field('cat_name')->find();
			if($goods_name){
				$row['goods_name'] = $goods_name['goods_name'];
				$row['cat_name'] = $cat_name['cat_name'];
			}
		}
		$this->assign('row',$row);
		return $this->fetch();
    }
    /*
     * 后台素材删除
     */
    public function sucaiDel()
    {
        $ids=input('get.ids');
        $userModel = new UserService();
        $map['m_id']=['in',$ids];
        $res=$userModel->sucaiDel($map);

        //添加日志记录
        $this->write_log('后台素材删除',$ids);

        return AjaxReturn($res);
    }
	/*
	 * 素材分类管理
	*/
	public function sucaiCat()
    {
		$cat_name = trim(input('cat_name'));
		$type = trim(input('type'));
		if(request()->isAjax()){
			//排序
			 // limit
            $limit=input('get.offset').",".input('get.limit');
			$order=input('get.weigh')." ".input('get.weigh');

			if(input('cat_name')){
				$map['cat_name']=['like','%'.input('cat_name').'%'];
			}
			//type: 1素材分类2话题分类
			$map['type'] = input('type');
			$userModel = new UserService();
			$list = $userModel->getSucaiCat($map,$limit);
			 
			return json($list);
		}else{
			$this->assign('cat_name',$cat_name);	
			$this->assign('type',$type);
			return $this->fetch();
		}
	}
	/*
	 * 素材分类添加
	 */
	public function sucaicatAdd()
    {
		$type = trim(input('type'));
		if(request()->isAjax()){
			$row = input('post.row/a');
			$res=db('material_category')->insert($row);

			//添加日志记录
            $id=db('material_category')->getLastInsID();
            $this->write_log('素材分类添加',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$this->assign('type',$type);	
			return $this->fetch();
		}    		
	
	}
	/*
	 * 素材分类修改
	 */
	public function sucaicatEdit()
    {
		if(request()->isAjax()){
			$row = input('post.row/a');
			$map['cat_id']=input('post.cat_id');
			$res=db('material_category')->where($map)->update($row);
			//添加日志记录
            $id=db('material_category')->getLastInsID();
            $this->write_log('素材分类修改',$id);
			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['cat_id']=input('get.ids');
			$rows=db('material_category')->where($map)->find();
		 
			$this->assign('rows',$rows);	
			return $this->fetch();
		}    		
	}
 
	/*
	*今日注册
	*/
	public function regToday(){
				// return json(['data' => request()->isAjax()]);
				if(request()->isAjax()){
					// 排序
					// $order=input('get.sort')." ".input('get.order');
					$order = 'user_id asc';
					// exit(print($order));
					// limit
					$limit=input('get.offset').",".input('get.limit');

					//查询
					if(input('get.search')){
						$map['user_id|user_name|user_mobile']=['like','%'.input('get.search').'%'];
					}
					//当天凌晨
					$ling = strtotime(date('Y-m-d', time()));
					//当天十二点
					$end  = $ling + 24 * 60 * 60;
					$map['user_reg_time'] = ['egt',$ling];
					$map['status']=['eq',0];
					$total = $this->service->count($map);
					$rows = $this->service->select($map,'*',$order,$limit);
					if($rows){
						foreach($rows as $val){
						   $val['is_kefu'] =  $val['is_kefu']==0?'否':'客服';
						}
					}

					return json(['total'=>$total,'rows'=>$rows]);
				}
				else{
					// $order = 'user_id asc';
					// $row = $this->service->select('', 'user_id,user_name,user_mobile,user_avat,user_points,user_reg_time', 'user_id asc', '0,20');
					// $this->assign('row', $row);
					// $this->assign('row',$row);
					// return json(['rows' => $row]);
					return $this->fetch();
				}
			}
				/*
	*今日注册
	*/
	public function logToday(){
				// return json(['data' => request()->isAjax()]);
				if(request()->isAjax()){
					// 排序
					// $order=input('get.sort')." ".input('get.order');
					$order = 'user_id asc';
					// exit(print($order));
					// limit
					$limit=input('get.offset').",".input('get.limit');

					//查询
					if(input('get.search')){
						$map['user_id|user_name|user_mobile']=['like','%'.input('get.search').'%'];
					}
					//当天凌晨
					$ling = strtotime(date('Y-m-d', time()));
					//当天十二点
					$map['user_last_login'] = ['egt',$ling];
					$map['status']=['eq',0];
					$total = $this->service->count($map);
					$rows = $this->service->select($map,'*',$order,$limit);
					if($rows){
						foreach($rows as $val){
						   $val['is_kefu'] =  $val['is_kefu']==0?'否':'客服';
						}
					}

					return json(['total'=>$total,'rows'=>$rows]);
				}
				else{
					// $order = 'user_id asc';
					// $row = $this->service->select('', 'user_id,user_name,user_mobile,user_avat,user_points,user_reg_time', 'user_id asc', '0,20');
					// $this->assign('row', $row);
					// $this->assign('row',$row);
					// return json(['rows' => $row]);
					return $this->fetch();
				}
			}
			
	/*
	*搜索引擎 
	*/
	public function yinying(){
		$user_id_no = trim(input('user_id_no'));//身份证号
		$user_mobile = trim(input('user_mobile'));
		$user_truename = trim(input('user_truename'));
        $this->assign('user_id_no',$user_id_no);
        $this->assign('user_truename',$user_truename);
        $this->assign('user_mobile',$user_mobile);
		$start_amount = trim(input('start_amount'));//结算金额，开始
		$end_amount = trim(input('end_amount'));//结算金额，结束
//		$status = trim(input('status'));
//		$type = trim(input('type'));//按什么查,//5:总金额；4:总金额；3:季度奖励；2:业绩奖励；1:全部
        $csclename = trim(input('csclename'));//周期 2018-10
        $this->assign('csclename',$csclename);
//        $this->assign('type',$type);
//        $this->assign('status',$status);
        $s_time = '';
        $e_time = '';
        if(empty($csclename)){
            $start_time = trim(input('start_time'));//奖励时间，开始
            $end_time = trim(input('end_time'));//奖励时间，开始
            $s_time = $start_time;
            $e_time = $end_time;
            $this->assign('start_time',$start_time);
            $this->assign('end_time',$end_time);
        }
        $this->assign('start_amount',$start_amount);
        $this->assign('end_amount',$end_amount);

		if(request()->isAjax()){
		    $map = [];
			//排序
			$limit=input('get.offset').",".input('get.limit');
			if($user_truename){
				$map['user_truename']=['like','%'.$user_truename.'%'];
			}	
			if($user_mobile){
				$map['user_mobile']=['eq',$user_mobile];
			}
			if($user_id_no){
				$map['user_id_no']=['eq',$user_id_no];
			}
			if(!$map){
				return '';
			}
 			if ($csclename) {
			    // 验证时间格式
                $time_arr = explode('-', $csclename);//array(2018,10);
                if (count($time_arr) == 2 && $time_arr[1] < 13 && $time_arr[1] > 0) {
                    $csclenames = $csclename.'-01';//2018-10-01
                    // 结算周期  结算格式 ：12月份 = 11/26-12/25
                    $cycle_start_time = strtotime(date("Y-m-26",strtotime("$csclenames -1 month")));//1537891200  2018-09-26 00:00:00
					//周期和时间段是或者的关系   20181229 y
					/*if($start_time){
						$start_time = $cycle_start_time<$start_time?$cycle_start_time:$start_time;
					}else{
						$start_time = $cycle_start_time;
					}*/
                    $cycle_end_time = strtotime(date("Y-m-25 23:59:59",strtotime($csclenames)));//1540483199 2018-10-25 23:59:59 当前月的25日23时59分59秒
                    $start_time = $cycle_start_time;
                    $end_time = $cycle_end_time;//时间戳
					/*if($end_time){
						$end_time = $cycle_end_time>$end_time?$cycle_end_time:$end_time;
					}else{
						$end_time = $cycle_end_time;
					}*/
                }
            }else{
                //2018-09-30+00:00:00
                $start_time = strtotime(implode(' ',explode('+',$start_time)));
                $end_time = strtotime(implode(' ',explode('+',$end_time)));
            }

            if(empty($start_time) || empty($end_time)){
                return '';
            }
            $map['is_seller'] = 1;
			// $userInfo = Db::name('users')->where($map)->where('is_kefu',0)->field('user_id,user_name,user_truename,user_mobile,is_seller')->limit($limit)->select();
			$userInfo = Db::name('users')->where($map)->field('user_id,user_name,user_truename,user_mobile')->limit($limit)->select();
			//店主
			//店铺等级：1：会员店；2，高级店铺；3，旗舰店铺
            $list = [];
			if($userInfo){
                $list = $userInfo;
			    foreach($userInfo as $key=>$val){
					$storeinfo = $this->getStore($val['user_id']);//获取店铺信息
					if(empty($storeinfo)){
                        unset($userInfo[$key]);
					    unset($list[$key]);
                        continue;
                    }
                    $list[$key]['shop_name'] = $storeinfo['shop_name'];
                    $list[$key]['user_level'] = $storeinfo['user_level'];
//					$val = $this->getReward($val,$type,$start_time,$end_time);
                    //获取奖励（市场培训、店铺分享、社群销售、社群服务费）
                    $bonus = $this->getBonus($val['user_id'],$start_time,$end_time);
                    //所有下级的大礼包
                    $list[$key]['usergift'] = $this->getUserGifts($val['user_id'],$start_time,$end_time);
                    //自购物、个人销售利润
                    $self_shop= $this->selfShoppings($val['user_id'], $start_time,$end_time);
                    //VIP购物、VIP销售利润
                    $vip_shop = $this->vipShoppings($val['user_id'], $start_time,$end_time);

                    $list[$key]['shop_award'] = 0;//产品销售
                    $list[$key]['promotion'] = 0;//实体店铺奖励
                    $list[$key]['product_recommend'] = 0;//产品推荐权

                    //销售额 \自有产品
                    $allSale = $this->allSale($val['user_id'],$start_time,$end_time);


//					$data = $this->Store->getStoreSaleRooms($val['user_id'],$start_time,$end_time);

//					$userInfo[$key] = $val;
					 $list[$key]['csclename'] = $csclename;
                    $list[$key]['start_time'] = $s_time;
                    $list[$key]['end_time'] = $e_time;

//					$userInfo[$key]['sales'] = $data['total'] - $userInfo[$key]['usergift'];
					 
//					$userInfo[$key]['total'] = $val['award']+$val['quarterly']+$val['annual']+$val['shop_award']+$val['promotion'];
                    $list[$key] = array_merge($list[$key],$bonus,$self_shop,$vip_shop,$allSale);
						
					/*$row = $this->screen($start_amount,$end_amount,$type,$val,$userInfo[$key]['total']);
					if(!$row){
						unset($userInfo[$key]);
					}*/
				}
		
			}
//			$userInfo = array_merge($userInfo);
			/*$total = Db::name('users')->where($map)->where('is_kefu',0)->count();
			if($type){
				$total = count($userInfo);
			}*/
            $total = count($list);
			return json(['total'=>$total,'rows'=>$list]);
		}else{
		    // 生成周期
		    $arr = [];
		    $s = 2018;
		    for ($i = 0; $i<3; $i++ ) {
		        for ($a = 1; $a< 13; $a ++) {
		            if ($a < 10) {
		                $a = '0'.$a;
                    }
		            $arr[] = $s+$i.'-'.$a;
                }
            }
            $this->assign('cycle', $arr);
			return $this->fetch();
		}
	}

    /*
	 * 自己购物
	 */
    public function selfShoppings($uid, $s_time,$e_time){
        $my_total = 0.00;		  		// 自己购物
        $my_commi = 0;                  //自己购物获得的佣金
        $where_1 = [
            'commi_uid'=>$uid,
            'uid_role'=>['>', 1]
        ];
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_comm_time')->find();
        if($store_info['s_comm_time'] > $s_time){
            $s_time = $store_info['s_comm_time'];
        }
        // 按月统计
        $where_1['commi_add_time'] = [
            ['>=', $s_time],
            ['<', $e_time]
        ];
        // 自己购物
        $my_total = Db::name('commission')->where($where_1)->sum('commi_order_price');
        $my_commi = Db::name('commission')->where($where_1)->sum('commi_price');
        $data = [
            'self_sales'=>$my_total?sprintf('%0.2f',$my_total):0,
            'self_sales_profit'=>$my_commi?sprintf('%0.2f',$my_commi):0
        ];
        return $data;
    }

    /*
	 * VIP购物
	 */
    public function vipShoppings($uid, $s_time,$e_time){
        $vip_total = 0.00;		// 子vip
        $vip_commi = 0;
        $where = [
            'a.t_p_uid'=>$uid,
            'b.is_seller'=>0
        ];
        $vip_uids = Db::name('users_tree')->alias('a')->join('__USERS__ b','a.t_uid=b.user_id')->where($where)->column('b.user_id');
        if(!empty($vip_uids)){
            $map = [
                'commi_uid'=>['in',$vip_uids],
                'uid_role'=>1
            ];
            $store_info = Db::name('store')->where('s_uid', $uid)->field('s_comm_time')->find();
            if($store_info['s_comm_time'] > $s_time){
                $s_time = $store_info['s_comm_time'];
            }
            $map['commi_add_time'] = [
                ['>=', $s_time],
                ['<', $e_time]
            ];
            $vip_total = Db::name('commission')->where($map)->sum('commi_order_price');
            $vip_commi = Db::name('commission')->where($map)->sum('commi_price');
        }
        $data = [
            'vip_sales'=>$vip_total?sprintf('%0.2f',$vip_total):0,
            'vip_sales_profit'=>$vip_commi?sprintf('%0.2f',$vip_commi):0
        ];
        return $data;
    }

    /**
     * 获取奖励
     * @param $uid   用户id
     * @param $start_time  开始时间
     * @param $end_time    结束时间
     * @return array
     */
    public function getBonus($uid,$start_time,$end_time)
    {
        $where = [];
        $shop_share = 0;
        $market_train = 0;
        $group_sale = 0;
        $sales_profit = 0;
        $market_exp = 0;
        $where['user_id'] = $uid;
        $w_time = $this->w_time('add_time',$start_time,$end_time);
        if($w_time){
            $where = array_merge($where,$w_time);
        }
        $list = Db::name('bonus')->where($where)->field('type,price')->select();
        if($list){
            foreach ($list as $v) {
                switch ($v['type']){
                    case 1:
                        $shop_share += $v['price'];
                        break;
                    case 2:
                        $market_train += $v['price'];
                        break;
                    case 3:
                        $group_sale += $v['price'];
                        break;
                    case 4:
                        $sales_profit += $v['price'];
                        break;
                    case 5:
                        $market_exp += $v['price'];
                        break;
                }
            }
        }
        $data = [
            'shop_share'=>$shop_share?sprintf('%0.2f',$shop_share):0,//店铺分享
            'market_train'=>$market_train?sprintf('%0.2f',$market_train):0,//培训费
            'group_sale'=>$group_sale?sprintf('%0.2f',$group_sale):0,//.社群销售奖励
            'sales_profit'=>$sales_profit?sprintf('%0.2f',$sales_profit):0,//社群服务费奖励
            'market_exp'=>$market_exp?sprintf('%0.2f',$market_exp):0
        ];

        return $data;
    }

    //该店铺下面所有子类的销售额,不包含大礼包
    public function allSale($uid,$start_time,$end_time)
    {
        $data = [
            'sales'=>0,
            'goods_sales'=>0
        ];
        $goods_service = new GoodsService();
        $child_user_ids = $goods_service->getAllChild($uid);//[1,2]
        $user_ids = [];
        if($child_user_ids){
            $user_ids = $child_user_ids;
        }
        $user_ids[] = $uid;
        $where = [
            'commi_uid'=>['in',$user_ids],
           'is_settle'=>['neq',2],
            'commi_add_time'=>[['>=', $start_time], ['<', $end_time]]
        ];
        //总销售
        $price = Db::name('commission')->where($where)->sum('commi_order_price');

        //自有产品
        $where_s = [
            's_uid'=>['in',$user_ids],
            'status'=>0,
            'sg_addtime'=>[['>=', $start_time], ['<', $end_time]]
        ];
        $self_price = Db::name('sg_sale')->where($where_s)->sum('price');
        $data['sales'] = $price?sprintf('%0.2f',$price):0;
        $data['goods_sales'] = $self_price?sprintf('%0.2f',$self_price):0;
        return $data;
    }

	//获取大礼包	
	public function getUserGifts($uid,$start_time,$end_time){
        $goods_service = new GoodsService();
        $child_user_ids = $goods_service->getAllChild($uid);
        $user_ids = [];
        if($child_user_ids){
            $user_ids = $child_user_ids;
        }
        $user_ids[] = $uid;
        $where = [
            'log_uid'=>['in', $user_ids]
        ];
		/*//我的大礼包 和 我的2级 下级
		$sql = "SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile,b.is_seller FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid UNION SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile,b.is_seller FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_g_uid=$uid ORDER BY add_time desc";
		$tree_info = Db::name('users_tree')->query($sql);
		$user_ids = [$uid];
		foreach($tree_info as $v){
			if($v['is_seller']==1){
				$user_ids[] = $v['user_id'];
			}
		}*/
		/*if($start_time){
			$where['log_add_time'] = ['egt',$start_time];
		}else if($end_time){
			$where['log_add_time'] = ['elt',$end_time];
		}else if($start_time&&$end_time){
			$where['log_add_time']= ['between',''.$start_time.','.$end_time.''];
		}*/
        $w_time = $this->w_time('log_add_time',$start_time,$end_time);
        if($w_time){
            $where = array_merge($where,$w_time);
        }
		$log_order_price = Db::name('gift_log')->where($where)->sum('log_order_price');
        $log_order_price = $log_order_price?sprintf('%0.2f',$log_order_price):0;
        return $log_order_price;
	
	}
    public function w_time($condition,$start_time,$end_time)
    {
        $where = [];
        if($start_time && $end_time ){
            $where[$condition]= ['between',''.$start_time.','.$end_time.''];
            $where[$condition]= ['between',''.$start_time.','.$end_time.''];
        }else if($start_time){
            $where[$condition]= ['>=',$start_time];
        }else if($end_time){
            $where[$condition]= ['<=',$end_time];
        }
       return $where;
    }
		//获取大礼包	
	public function getUserGift($uid,$start_time,$end_time){
		$where['log_add_time']= ['between',''.$start_time.','.$end_time.''];
		$uid_arr = $this->getUserTree($uid);
		$gift_prices = 0.00;
		foreach($uid_arr as $val){
			$map['log_uid'] = $val;
			$gift_price = Db::name('gift_log')->where($map)->value('log_order_price');
			if($gift_price){
				$gift_prices += $gift_price;
			}
			
		}
		return $gift_prices;
	}
	
	//获取下级 uid  
	public function  getUserTree($uid){
		//三级下的 二级 
		$t_p_uid = Db::name('users_tree')->where('t_g_uid',$uid)->column('t_p_uid');
		if($t_p_uid){
			$t_p_uid = implode(',',$t_p_uid);
			//二级下的 一级 
			$one_uid = Db::name('users_tree')->where(array('t_p_uid'=>['in',$t_p_uid]))->column('t_p_uid');
		}
		//二级下的 一级 
		$t_uid = Db::name('users_tree')->where('t_p_uid',$uid)->column('t_uid');
		$uids = array($uid);
		if($t_uid || $one_uid){
			$uids = array_merge($t_uid,$one_uid,$uids);
		}
		return $uids;
	}	
	
	//店铺等级
	public function  getStore($uid){
		$level  = array('VIP会员','会员店主','高级店铺主','旗舰店铺主');
        $storeInfo = Db::name('store')->where('s_uid',$uid)->field('s_name,s_grade')->find();
        $data = [];
        if($storeInfo){
            $data['shop_name'] =  $storeInfo['s_name'];
            $data['user_level'] =  $level[$storeInfo['s_grade']];
        }

		return $data;
	}
	//店铺等级
	//5:总金额；4:直接奖励；3:季度奖励；2:业绩奖励；1:全部	//账户变更类型：1，提现；2，购物；3，充值；4，返利；5，分享；6，买购物券； 7，提现失败；8，店铺升级奖励；9，促销奖励
	public function  getReward($data,$type='',$start_time='',$end_time=''){
		$data['award'] = 0;
		$data['promotion'] = 0;
		$data['shop_award'] = 0;
		$data['quarterly'] = 0;
		$data['annual'] = 0;
		if($type == 5 ){
			if($start_time && $end_time ){
				$where['acco_time']= ['between',''.$start_time.','.$end_time.''];
				$map['reward_time']= ['between',''.$start_time.','.$end_time.''];
				
			}else if($start_time){
				$where['acco_time']= ['>=',$start_time];
				$map['reward_time']= ['>=',$start_time];
			}else if($end_time){
				$where['acco_time']= ['<=',$end_time];
				$map['reward_time']= ['<=',$end_time];
			}// 4 9 8 
			$data['award'] = $this->getaward($where,4,$data['user_id']);
			$data['promotion'] = $this->getaward($where,9,$data['user_id']);
			$data['shop_award'] = $this->getaward($where,8,$data['user_id']);
			$data['quarterly'] = $this->getawardTime($map,1,$data['user_id']);
			$data['annual'] = $this->getawardTime($map,2,$data['user_id']);
		}else if($type == 4){
			if($start_time && $end_time ){
				$where['acco_time']= ['between',''.$start_time.','.$end_time.''];
				$where['acco_time']= ['between',''.$start_time.','.$end_time.''];
			}else if($start_time){
				$where['acco_time']= ['>=',$start_time];
			}else if($end_time){
				$where['acco_time']= ['<=',$end_time];
			}
			$data['award'] = $this->getaward($where,4,$data['user_id']);
			$data['promotion'] = $this->getaward('',9,$data['user_id']);
			$data['shop_award'] = $this->getaward('',8,$data['user_id']);
		}else if($type == 1){
			if($start_time && $end_time ){
				$where['acco_time']= ['between',''.$start_time.','.$end_time.''];
				$map['reward_time']= ['between',''.$start_time.','.$end_time.''];
			}else if($start_time){
				$where['acco_time']= ['>=',$start_time];
				$map['reward_time']= ['>=',$start_time];
			}else if($end_time){
				$where['acco_time']= ['<=',$end_time];
				$map['reward_time']= ['<=',$end_time];
			}// 4 9 8 
			$data['award'] = $this->getaward($where,4,$data['user_id']);
			$data['promotion'] = $this->getaward($where,9,$data['user_id']);
			$data['shop_award'] = $this->getaward($where,8,$data['user_id']);
			$data['quarterly'] = $this->getawardTime($map,1,$data['user_id']);
			$data['annual'] = $this->getawardTime($map,2,$data['user_id']);
		}else{
			$data['quarterly'] = $this->getawardTime('',1,$data['user_id']);
			$data['annual'] = $this->getawardTime('',2,$data['user_id']);
			if($type == 3){
				if($start_time && $end_time ){
				$map['reward_time']= ['between',''.$start_time.','.$end_time.''];
				}else if($start_time){
					$map['reward_time']= ['>=',$start_time];
				}else if($end_time){
					$map['reward_time']= ['<=',$end_time];
				}// 4 9 8 
				$data['quarterly'] = $this->getawardTime($map,1,$data['user_id']);
				$data['annual'] = $this->getawardTime($map,2,$data['user_id']);
			}
			$account_log = Db::name('account_log')
				->where(array('a_uid'=>$data['user_id'],'ac_status'=>1))
				->where($where)
				->field('acco_num,acco_type')
				->select();
			if($account_log){
				foreach($account_log as &$val){
					if($val['acco_type'] == 4){
						$data['award'] += $val['acco_num'];	
					}else if($val['acco_type'] == 9){
						$data['promotion'] += $val['acco_num'];
					}else if($val['acco_type'] == 8){
						$data['shop_award'] += $val['acco_num'];
					}
				}
			}
		}
		 
		$year = mktime(0,0,0,1,1,date("Y"));
		$quarter = mktime(0,0,0,3,1,date("Y"));
		$next_year = mktime(0,0,0,1,1,date("Y")+1);
		$map2['reward_time'] = ['between',''.$year.','.$next_year.''];
		$reward = Db::name('reward')->where('reward_uid',$data['user_id'])->where($map2)->field('reward_num,reward_time,reward_stat')->select();
		if($reward){
			foreach($account_log as &$val){
				if($val['reward_time']<=$quarter){
					$data['quarterly'] += $val['reward_num']; 
				}
				$data['annual']+= $val['reward_num'];
			}
		}
		return $data;
	}
	/*
	*搜索引擎奖励 
	*/
	public function getawardTime($where,$type,$user_id){
		$year = mktime(0,0,0,1,1,date("Y"));
		$quarter = mktime(0,0,0,3,1,date("Y"));
		$next_year = mktime(0,0,0,1,1,date("Y")+1);
		$map2['reward_time'] = ['between',''.$year.','.$next_year.''];
		$reward = Db::name('reward')->where('reward_uid',$user_id)->where($map2)->field('reward_num,reward_time,reward_stat')->select();
        $quarterly = 0;
        $annual = 0;
		if($reward){
			foreach($reward as &$val){
				if($val['reward_time']<=$quarter){
					$quarterly += $val['reward_num']; 
				}
				$annual+= $val['reward_num'];
			}
		}	
		if($type==1){
			$reward = Db::name('reward')->where('reward_uid',$user_id)->where($where)->field('reward_num,reward_time,reward_stat')->select();
			$quarterly = 0;
			if($reward){
				foreach($reward as &$val){
					$quarterly += $val['reward_num']; 
				}
			}	
			return $quarterly;
		}elseif($type==2){
			$reward = Db::name('reward')->where('reward_uid',$user_id)->where($where)->field('reward_num,reward_time,reward_stat')->select();
			$annual = 0;
			if($reward){
				foreach($reward as &$val){
					$annual += $val['reward_num']; 
				}
			}	
			return $annual;
		}
		
	}/*
	*搜索引擎奖励 
	*/
	public function getaward($where,$type,$user_id){
		$account_log = Db::name('account_log')
		->where(array('a_uid'=>$user_id,'ac_status'=>1))
		->where($where)
		->field('acco_num,acco_type')
		->select();
		$award = 0;
		$promotion = 0;
		$shop_award = 0;
		if($account_log){
			foreach($account_log as &$val){
//账户变更类型：1，提现；2，购物；3，充值；4，返利；5，分享；6，买购物券； 7，提现失败；8，店铺升级奖励；9，促销奖励
				if($val['acco_type'] == $type){  
					$award += $val['acco_num'];	
				}else if($val['acco_type'] == $type){  
					$promotion += $val['acco_num'];
				}else if($val['acco_type'] == $type){  
					$shop_award += $val['acco_num'];
				}
			}
		}
		if($type == 9){
			return $promotion;
		}elseif($type == 8){
			return $shop_award;
		}elseif($type == 8){
			return $shop_award;
		}else{
			return $award;
		}
	}
	/*
	*搜索引擎  下级
	*/
	public function engineShow(){
		$user_id = trim(input('user_id'));
		$start_amount = trim(input('start_amount'));
		$end_amount = trim(input('end_amount'));
		$start_time = trim(input('start_time'));
		$end_time = trim(input('end_time'));
		$status = trim(input('status'));
        $cycle = trim(input('cycle'));
//		$type = trim(input('type'));
		$csclename = trim(input('csclename'));
        $this->assign('cycle',$cycle);
        $this->assign('status',$status);
        $this->assign('start_amount',$start_amount);
        $this->assign('end_amount',$end_amount);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('user_id',$user_id);
        $this->assign('csclename',$csclename);
		
		 
		if(request()->isAjax()){
			//排序
			$limit=input('get.offset').",".input('get.limit');
            if ($csclename) {
                // 验证时间格式
                $time_arr = explode('-', $csclename);//array(2018,10);
                if (count($time_arr) == 2 && $time_arr[1] < 13 && $time_arr[1] > 0) {
                    $csclenames = $csclename.'-01';//2018-10-01
                    $start_time = strtotime(date("Y-m-26",strtotime("$csclenames -1 month")));//1537891200  2018-09-26 00:00:00
                    $end_time = strtotime(date("Y-m-25 23:59:59",strtotime($csclenames)));//1540483199 2018-10-25 23:59:59 当前月的25日23时59分59秒
                }
            }else{
                //2018-09-30+00:00:00
                $start_time = strtotime(implode(' ',explode('+',$start_time)));
                $end_time = strtotime(implode(' ',explode('+',$end_time)));
            }
//            $start_time = trim(input('start_time'));
//            $end_time = trim(input('end_time'));

            if(empty($start_time) || empty($end_time)){
                return '';
            }
            $uid_arr = Db::name('users_tree')->where('t_p_uid',$user_id)->column('t_uid');
			$map['user_id'] =  ['in',$uid_arr];
            $map['is_seller'] = 1;
			$userInfo = Db::name('users')
				->where($map)
				->field('user_id,user_name,user_truename,user_mobile')
				->limit($limit)
				->select();
			//店主
			//店铺等级：1：会员店；2，高级店铺；3，旗舰店铺
            if($userInfo){
                $list = $userInfo;
                foreach($userInfo as $key=>$val){
                    $storeinfo = $this->getStore($val['user_id']);//获取店铺信息
                    if(empty($storeinfo)){
                        unset($list[$key]);
                        continue;
                    }
                    //所有下级的大礼包
                    $list[$key]['usergift'] = $this->getUserGifts($val['user_id'],$start_time,$end_time);
                    $list[$key]['shop_name'] = $storeinfo['shop_name'];
                    $list[$key]['user_level'] = $storeinfo['user_level'];
//					$val = $this->getReward($val,$type,$start_time,$end_time);
                    //获取奖励（市场培训、店铺分享、社群销售、社群服务费）
                    $bonus = $this->getBonus($val['user_id'],$start_time,$end_time);

                            //自购物、个人销售利润
                    $self_shop= $this->selfShoppings($val['user_id'], $start_time,$end_time);
                    //VIP购物、VIP销售利润
                    $vip_shop = $this->vipShoppings($val['user_id'], $start_time,$end_time);

                    $list[$key]['shop_award'] = 0;//产品销售
                    $list[$key]['promotion'] = 0;//实体店铺奖励
                    $list[$key]['product_recommend'] = 0;//产品推荐权
                    //社群数
                    $list[$key]['group_num'] = $this->getGroupNum($val['user_id'],$user_id);
                    //销售额 \自有产品
                    $allSale = $this->allSale($val['user_id'],$start_time,$end_time);
                    $list[$key]['csclename'] = $csclename;
                    $list[$key] = array_merge($list[$key],$bonus,$self_shop,$vip_shop,$allSale);
                }

            }
            $lists = array_values($list);
            $total = count($lists);
            return json(['total'=>$total,'rows'=>$lists]);
		}else{
            // 生成周期
            $arr = [];
            $s = 2018;
            for ($i = 0; $i<3; $i++ ) {
                for ($a = 1; $a< 13; $a ++) {
                    if ($a < 10) {
                        $a = '0'.$a;
                    }
                    $arr[] = $s+$i.'-'.$a;
                }
            }
            $this->assign('cycle', $arr);
			return $this->fetch();
		}
	}
    /**
     * 获取社群数
     * @param $uid
     * @param $p_uid
     * @return int
     */
    public function getGroupNum($uid,$p_uid)
    {
        $group_num = 0;
        $flag = true;
        while($flag){
            $t_p_uid = Db::name('users_tree')->where('t_uid',$uid)->value('t_p_uid');
            $uid = $t_p_uid;
            $group_num++;
            if($t_p_uid==$p_uid){
                $flag = false;
            }
        }
        return $group_num;
    }
	/*
	*搜索筛选
	*///5:总金额；4:直接奖励；3:季度奖励；2:年度奖励；1:全部

	public function screen($start_amount,$end_amount,$type,$data,$total){
		 
		if($type == 5){
			if($start_amount && $end_amount){
				if($total>=$start_amount&&$total<=$end_amount){
					return 1;
				}else{
					return 0;
				}
			}else if($start_amount){
				if($total>=$start_amount){
					return 1;
				}else{
					return 0;
				}
			}else if($end_amount){
				if($total<=$end_amount){
					return 1;
				}else{
					return 0;
				}
			}
		}else if($type == 4){
			if($start_amount && $end_amount){
				if(($data['award']>=$start_amount&&$data['award']<=$end_amount)){
					return 1;
				}else{
					return 0;
				}
			}else if($start_amount){
				if($data['award']>=$start_amount){
					return 1;
				}else{
					return 0;
				}
			}else if($end_amount){
				if($data['award']<=$end_amount){
					return 1;
				}else{
					return 0;
				}
			}
		}else if($type == 3){
			if($start_amount && $end_amount){
				if(($data['quarterly']>=$start_amount&&$data['quarterly']<=$end_amount)){
					return 1;
				}else{
					return 0;
				}
			}else if($start_amount){
				if($data['quarterly']>=$start_amount){
					return 1;
				}else{
					return 0;
				}
			}else if($end_amount){
				if($data['quarterly']<=$end_amount){
					return 1;
				}else{
					return 0;
				}
			}
		}else if($type == 2){
			if($start_amount && $end_amount){
				if(($data['annual']>=$start_amount&&$data['annual']<=$end_amount)){
					return 1;
				}else{
					return 0;
				}
			}else if($start_amount){
				if($data['annual']>=$start_amount){
					return 1;
				}else{
					return 0;
				}
			}else if($end_amount){
				if($data['annual']<=$end_amount){
					return 1;
				}else{
					return 0;
				}
			}
		}else if($type == 1){
			if($start_amount && $end_amount){
				if(($total>=$start_amount&&$total<=$end_amount)&&($data['award']>=$start_amount&&$data['award']<=$end_amount)&&($data['quarterly']>=$start_amount&&$data['quarterly']<=$end_amount)&&($data['annual']>=$start_amount&&$data['annual']<=$end_amount)){
					return 1;
				}else{
					return 0;
				}
			}else if($start_amount){
				if(($total>=$start_amount)&&($data['award']>=$start_amount)&&($data['quarterly']>=$start_amount)&&($data['annual']>=$start_amount)){
					return 1;
				}else{
					return 0;
				}
			}else if($end_amount){
				if(($total<=$end_amount)&&($data['award']<=$end_amount)&&($data['quarterly']<=$end_amount)&&($data['annual']<=$end_amount)){
					return 1;
				}else{
					return 0;
				}
			}		
		}
		return 1;
	}
	/*
	 * 生成元宝编号
	 */
	public function createYzNo(){
		return 'YB'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
	}

	/*
	 * 获取用户新消息
	 */
	public function getNewMessage()
    {
        $touid = input("touid");
        $kf_id = input("kf_id");
        // $id = input("id");
        //有没有新消息
        $where = [
            // 'id'=>['>',$id],
            'kf_id'=>$kf_id,
            'uid'=>$touid,
            'looked'=>0
        ];
        $res = db("msg")->where($where)->field('id,content,type')->select();
        
        if($res){
            $user_avat = db('users')->where('user_id',$touid)->value('user_avat');
            //变为已读
            $ids = array_column($res,'id');
            $ids = implode(',', $ids);
            $r = db('msg')->where(['id'=>['in',$ids]])->update(['looked'=>1]);
            
        }
        $num = $this->getMsgNum($kf_id);
        return ['status'=>1,'data'=>json_encode(['list'=>$res,'user_avat'=>$user_avat,'num'=>$num])];
    }

    /**
    * 获取当前有多少未读消息
    */
    public function getMsgNum($kf_id)
    {
    	//找当前有多少消息未读
    	$res = Db::query('select count(uid) as num,uid from ht_msg where looked=0 and kf_id='.$kf_id.' and uid>0 group by uid');
    	if($res){
    		foreach ($res as &$v) {
	    		$userInfo = Db::name('users')->where(['user_id'=>$v['uid']])->field('user_id,user_name,user_avat')->find();
	    		$v['user_id'] = $userInfo['user_id'];
	    		$v['user_name'] = $userInfo['user_name'];
	    		$v['user_avat'] = $userInfo['user_avat'];
	    	}
    	}
    	
        return $res;
    }


    public function chongzhi(){
        if(request()->isAjax()){
            $row = input('post.row/a');
            $map['user_id'] = input('post.user_id');
            if ($row['user_account']){
                $this->model->where($map)->setInc('user_account',$row['user_account']);
            }
            //添加日志记录
            $this->write_log('编辑会员',$map['user_id']);
            //添加财务日志
            $data = [];
            $data['a_uid'] = $map['user_id'];
            $data['acco_num'] = $row['user_account'];
            $data['acco_type'] = 3;
            $data['acco_desc'] = '后台管理员充值';
            $data['acco_time'] = time();
            $data['order_id'] = 0;
            $data['ac_status'] = 1;
            $res = Db::name('account_log')->insert($data);
            return AjaxReturn(true);
        }else{
            $map['user_id']= input('get.user_id');
            $row = $this->service->find($map);
            $this->assign('row', $row);
            return $this->fetch();
        }
    }

    public function huiyuan(){
        if(request()->isAjax()){
            $row = input('post.row/a');
            $map['user_id'] = input('post.user_id');
            if ($row['user_account']){
                $uyser = $this->service->find($map);
                $updata['is_vip']=1;
                if($uyser['vip_end_time']>time()){
                    $updata['vip_end_time']= strtotime("+{$row['user_account']} month",$uyser['vip_end_time']);
                }
                else {
                    $updata['vip_end_time']= strtotime("+{$row['user_account']} month",time());
                }
                $this->model->where($map)->update($updata);
            }
            return AjaxReturn(true);
        }else{
            $map['user_id']= input('get.user_id');
            $row = $this->service->find($map);
            $row['is_vip'] = $row['is_vip']?'是':'否';
            $row['vip_end_time'] = $row['vip_end_time']? date('Y-m-d',$row['vip_end_time']):0;
            $this->assign('row', $row);
            return $this->fetch();
        }
    }

}
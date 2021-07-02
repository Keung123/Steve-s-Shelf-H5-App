<?php
namespace app\common\service;

use app\common\model\Users as UserModel;
use app\common\service\User as UserService;
use think\Db;
use think\Request;

class Login extends Base{
	
	/*
	 * 三方登录
	 */
	public function apiLogin($user_id, $clientId = '', $app_system= 2){
		if($user_id){
			$user_service = new UserService();
//			$token = $user_service->createToken($user_id);
//			if(!$token){
//				return false;
//			}
			$info = $user_service->userInfo(['user_id' => $user_id], 'user_id,user_mobile,user_login_times,is_seller,token,shop_name');
			$data['user_last_login'] = time();
			$data['user_last_ip'] = Request::instance()->ip();
			$data['user_login_times'] = $info['user_login_times'] + 1;
			$data['token'] = $info['token'];
			$data['client_id'] = $clientId;
			$data['app_system'] = $app_system;

			Db::name('users')->where('user_id', $user_id)->update($data);
			return ['uid' => $info['user_id'], 'user_mobile' => $info['user_mobile'], 'token' => $info['token'],'is_seller' => $info['is_seller'] ? : 0, 'shop_name' => $info['shop_name']];
		}
	}

	/*
	 * 三方注册
	 */
	public function apiRegister($type, $api_id, $data){
		if($api_id && $data){
			$user_service = new UserService();
			$token = $user_service->createToken($api_id);
			if(!$token){
				return false;
			}
			$where = [];

			$data['is_seller'] = 0;
			$data['user_account'] = 0.00;
			$data['user_card'] = 0.00;
			$data['user_points'] = 0;
			$data['user_reg_time'] = time();
			$data['user_reg_ip'] = Request::instance()->ip();
			$data['user_last_login'] = time();
			$data['user_last_ip'] = Request::instance()->ip();
			$data['user_login_times'] = 1;
			$data['token'] = $token;
			if($type == 'weixin'){
				$data['unionid'] = $where['unionid'] = $api_id;
			}
			else if($type == 'qq'){
				$data['openid'] = $where['openid'] = $api_id;
			}

			Db::startTrans();
			try{
				$res = Db::name('users')->insert($data);
				$user_info = $user_service->userInfo($where, 'user_id as uid,user_mobile,token,is_seller');
				// 增加上级
//				$official_store = Db::name('store')->alias('a')->join('__USERS__ b', 'a.s_uid=b.user_id')->where(['s_id' => 1])->field('b.user_id')->find();
//				if($official_store){
//					$insert = [
//						't_uid' => $user_info['uid'],
//						't_p_uid' => $official_store['user_id'],
//						't_addtime' => time(),
//					];
//					Db::name('users_tree')->insert($insert);
//				}
				Db::commit();				
				return $user_info;
			}	
			catch(\Exception $e){
				Db::rollback();
				return false;
			}
			
		}
		else return false;
	}

	/*
	 * 绑定手机
	 * 1130 公众号登陆 不需要设置密码
	 */
	public function mobileBind($uid, $mobile, $pwd = '', $invite_uid = '',$shop_name){
		$user_model = new UserModel();
		$user_info = $user_model->where('user_mobile', $mobile)->field('user_id as uid, token, is_seller')->find();
		$api_info = $user_model->where('user_id', $uid)->field('unionid,openid,token,is_seller,user_name')->find();
		//手机号已注册，以手机号注册为主
		if($user_info){
			Db::startTrans();
			try{
			    if ($pwd) {
                    $update = [
                        'user_pwd' =>md5('hetao_'.md5($pwd)),
                    ];
                } else {
			        $update = [];
                }

				if($api_info['unionid']){
					$update['unionid'] = $api_info['unionid'];
                    $update['user_wx'] = $api_info['user_name'];
				}
				if($api_info['openid']){
					$update['openid'] = $api_info['openid'];
                    $update['user_qq'] = $api_info['user_name'];
				}
				$update['shop_name'] = $shop_name;
				$user_model->where('user_id', $user_info['uid'])->update($update);
				$user_model->where('user_id', $uid)->delete();
				Db::commit();
				return $user_info;
			}
			catch(\Exception $e){
				Db::rollback();
				return false;
			}
		}
		//手机号未注册，以三方为主 随机获取 店主上级
		else{
            $store_model = new Store();
            $user_invite_code = $store_model->createInviteCode();
			$update = [
				'user_mobile' => $mobile,
				'user_pwd' => $pwd ? md5('hetao_'.md5($pwd)) : '',
                's_invite_code' => $user_invite_code,
                'shop_name' =>$shop_name,
			];
            // 随机获取一个 店主邀请码

//            $invite_code =  $store_model->getRandCode();


			$res = $user_model->where('user_id', $uid)->update($update);
            if($invite_uid){
                // 店主邀请
                $user_info = Db::name('users')->where('user_id', $invite_uid)->field('is_seller')->find();
                $tree_info = Db::name('users_tree')->where('t_uid', $invite_uid)->field('t_p_uid,t_g_uid')->find();
                $tree_info = array_merge($user_info,$tree_info);

                if($tree_info['is_seller']){
                    $tree_data = [
                        't_uid' => $uid,
                        't_p_uid' => $invite_uid,
                        't_addtime' => time(),
                    ];
                    if($tree_info['t_p_uid']){
                        $tree_data['t_g_uid'] = $tree_info['t_p_uid'];
                    }
                }
                // vip邀请
                else{
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
                }
                Db::name('users_tree')->insert($tree_data);

//                //增加积分
//                Db::name('users')->where('user_id', $invite_uid)->setInc('user_points', 10);
//                $point_insert = [
//                    'p_uid' => $invite_uid,
//                    'point_num' => 10,
//                    'point_type' => 5,
//                    'point_desc' => '邀请好友注册VIP',
//                    'point_add_time' => time(),
//                ];
//                Db::name('points_log')->insert($point_insert);
//                //增加元宝
//                $yinzi = new Yinzi();
//                $yinzi->addYinzi($invite_uid, 1, 5);	//店主
//                $yinzi->addYinzi($uid, 2, 5);	//新会员

                //更换邀请码
                // $invite_code = $store_service->createInviteCode();
                // Db::name('users')->where('user_id', $invite_uid)->update(['s_invite_code' => $invite_code]);
            }
			if($res){
				return ['uid' => $uid, 'token' => $api_info['token'], 'is_seller' => $api_info['is_seller'] ? :0];
			}
			else return false;
		}
	}
}



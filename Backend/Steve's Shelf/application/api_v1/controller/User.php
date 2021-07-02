<?php
namespace app\api\controller;

use app\common\service\Coupon;
use app\common\service\User as UserService;
use app\common\model\Config as ConfigModel;
use getui\Pushs;
use think\Db;
class User extends Common{

   /*
	* 登录
	*/
	public function login(){
		$mobile = trim(input('request.mobile'));
		if(!$mobile){
			return $this->json("", 0, '手机号不能为空');
		}
		$password = input('request.pwd');
		if(!$password){
			return $this->json("", 0, '密码不能为空');
		}
		$clientId = input('clientId');
        $app_system = input('app_system', 'Android');
        if ($app_system == 'Android') {
            $app_system = 2;
        } else {
            $app_system = 1;
        }
		$user = new UserService();
		$res = $user->login($mobile, $password);
		if($res == -1){
			return $this->json('', -1, '账号不存在');
		} elseif ($res == -2) {
            return $this->json('', 0, '密码错误');
        }elseif($res == -3){
        	return $this->json('',-3,'您还没有设置密码');
        }
		$where = [
			'user_mobile'=>$mobile,
			'user_pwd'=>$password,
		];
		  //手机型号
		$result = $user->clientId($mobile, $password,$clientId, $app_system);
		
		if(!$result){
			//推送
			$msg = [
				'content'=>'合陶家欢迎您！',//透传内容
				'title'=>'登陆提醒',//通知栏标题
				'text'=>'合陶家欢迎您！',//通知栏内容
				'curl'=> request()->domain(),//通知栏链接
			];
			 $data=array(
				0=>['client_id'=>$clientId],
			/* 	1=>['client_id'=>'67ae018749b47812e6d7d73024ccb0f7'],
				2=>['client_id'=>'1e170fd89af0e838d132e09592d4f3c1'], */
				'system'=>$app_system,//1为ios
			);
			$Pushs = new Pushs();
			$Pushs->getTypes($msg,$data);
		}  
		return $this->json(['uid' => $res['user_id'], 'token' => $res['token'],'is_kefu' => $res['is_kefu'],'is_seller' => $res['is_seller'] ? : 0]);
	}
	/*
	* 登录
	*/
	public function mobileLogin(){
		$user = new UserService();
		$mobile = trim(input('request.mobile'));
		$row = Db::name('users')->where('user_mobile',$mobile)->field('user_mobile')->find();
		if(!$row){
				return $this->json('', -1, '此手机号未注册');
		}
		//检测验证码
		$code = input('request.code');
		if(!$code){
			return $this->json('', 0, '验证码不能为空');
		}
		$stat = $user->checkCode($mobile, $code, 1);
		//验证
		if(!$stat){
			return $this->json("", 0, '验证码不正确');
		}

		if($stat == -1){
			return $this->json('', 0, '验证码已过期');
		}
		
		$where = [
			'user_mobile'=>$mobile,		
		];
		$clientId = input('clientId');
        $app_system = input('app_system', 'Android');
        if ($app_system == 'Android') {
            $app_system = 2;
        } else {
            $app_system = 1;
        }
		  //手机型号
		$result = $user->clientIds($mobile, $clientId, $app_system);
		
		if(!$result){
			//推送
			$msg = [
				'content'=>'合陶家欢迎您！',//透传内容
				'title'=>'登陆提示',//通知栏标题
				'text'=>'合陶家欢迎您！',//通知栏内容
//				'curl'=> request()->domain(),//通知栏链接
			];
			 $data=array(
				0=>['client_id'=>$clientId],
				'system'=>$app_system,//1为ios
			);
			$Pushs = new Pushs();
			$Pushs->getTypes($msg,$data);
		}  
		$res = Db::name('users')->where('user_mobile',$mobile)->field('user_id,token,is_kefu,is_seller')->find();
		return $this->json(['uid' => $res['user_id'], 'token' => $res['token'],'is_kefu' => $res['is_kefu'],'is_seller' => $res['is_seller'] ? : 0]);
	}
	/*
	 * 获取后台推送设置
	 */
	public function pushNOtice(){
		$config_model = new ConfigModel;
		$infos = $config_model->where(1)->field('app')->find();
		$notice = trim($infos['app']['notice']);
		if(!$notice){
			return $this->json('', 0, '获取失败');
		}
		return $this->json('', 1, $notice);
	}
	/*
	 * 获取三方登录方式
	 */
	public function apiLogin(){
		$config_model = new ConfigModel;
		$info = $config_model->where(1)->field('base')->find();
		$list = [];
		if($info['base']){
            $list = json_decode($info, true);
		}
		return $this->json($list['base']['api_login']);
	}

	/*
	 * 获取三方支付方式
	 */
	public function apiPay(){
		$config_model = new ConfigModel;
		$info = $config_model->where(1)->field('base')->find();
		
		$list = [];
		if($info['base']){
			$list = json_decode($info, true);
			$list = $list['base'];
			$row  = '';
			if($list['api_pay']){
				$row  = $list['api_pay'];
			} 
		}
		return $this->json($list['api_pay']);
	}
	/*
	 * 解绑微信
	 */
	public function untiewchat(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		//1：qq;2：微信
		$type = input('request.type');
		$user = new UserService();
		$res = $user->untie($uid,$type);
		if(!$res){
			return $this->json('', 0, '解绑失败');
		}
		return $this->json('', 1, '解绑成功');
	}
	/*
	 * 更换手机号
	 */
	public function replaceMobile(){
		$user = new UserService();
		$mobile = trim(input('request.mobile'));
		$code = trim(input('request.code'));
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		 
		//检测手机号是否注册
		$user_info = $user->find(['user_mobile' => $mobile]);
		if($user_info){
			return $this->json("", 0, '手机号已注册!不能更换！');
		}
		
		$res = Db::name('users')->where('user_id',$uid)->update(['user_mobile' => $mobile]);
		if($res){
			$this->json('', 0, '更换失败');
		}
		return $this->json('', 1, '更换成功');
	}

   /*
	* 会员注册
	*/
	public function register(){
		$user = new UserService();
		$mobile = trim(input('request.mobile'));
		if(!$mobile){
			return $this->json("", 0, '手机号不能为空');
		}
		//检测手机号是否注册
		$user_info = $user->find(['user_mobile' => $mobile]);
		if($user_info){
			return $this->json("", 0, '手机号已注册，请登录');
		}
		//检测验证码
		$code = input('request.code');
		if(!$code){
			return $this->json('', 0, '验证码不能为空');
		}
		$password = input('request.pwd');
		if(!$password){
			return $this->json("", 0, '密码不能为空');
		}

		$stat = $user->checkCode($mobile, $code, 1);
		//验证
		if(!$stat){
			return $this->json("", 0, '验证码不正确');
		}

		if($stat == -1){
			return $this->json('', 0, '验证码已过期');
		}

		//验证邀请码
//		$invite_code = input('request.invite_code');
//		if(!$invite_code){
//			return $this->json('', 0, '邀请码不能为空');
//		}
//		$invite_info = $user->checkInviteCode($invite_code);
//
//		if(!$invite_info){
//			// $invite_info = Db::name('store')->where('s_name', '平台店主')->field('s_uid')->find();
//			return $this->json('', 0, '未匹配到邀请人');
//		}
        $invite_info['user_id'] = 0;
		$clientId = input('clientId');
        $app_system = input('app_system', 'Android');
        if ($app_system == 'Android') {
            $app_system = 2;
        } else {
            $app_system = 1;
        }
		$res = $user->register($mobile, $password, $invite_info['user_id'],$clientId,$app_system);
	 
		if($res){
			return $this->json($res);
		}
		else{
			return $this->json('', 0, '注册失败');
		}
	}

   /*  
	* 找回密码
	*/
	public function resetPwd(){
		$user = new UserService();
		$mobile = trim(input('request.mobile'));
		if(!$mobile){
			return $this->json("", 0, '手机号不能为空');
		}
		//检测手机号是否注册
		$user_info = $user->find(['user_mobile' => $mobile]);
		if(!$user_info){
			return $this->json("", 0, '手机号未注册');
		}
		//检测验证码
		$code = input('request.code');
		if(!$code){
			return $this->json('', 0, '验证码不能为空');
		}
		$password = input('request.pwd');
		if(!$password){
			return $this->json("", 0, '密码不能为空');
		}

		$stat = $user->checkCode($mobile, $code, 2);
		//验证
		if(!$stat){
			return $this->json("", 0, '验证码不正确');
		}

		if($stat === -1){
			return $this->json('', 0, '验证码已过期');
		}		

		$res = $user->resetPwd($mobile, $password);
		if($res > 0){
			return $this->json(['mobile' => $mobile]);
		}
		else{
			return $this->json('', 0, getErrorInfo($res));
		}
	}

   /*
	* 获取验证码
	*/
	public function getCode(){
		$mobile = input('request.mobile');
		if(!$mobile){
			return $this->json("", 0, '手机号不能为空');
		}
		//发送类型：1，注册；2，找回密码；3，绑定手机号；4，设置支付密码 5，登录确认
		$type = input('request.type');
		$user = new UserService();
		$res= $user->getCode($mobile, $type);
		if($res['status'] == 1){
			if(config('app_debug')){
//				return $this->json($res['code']);
                return json(['data'=>$res['code'],'status'=>1,'type'=>$res['type']]);
			}
			else{
//				return $this->json("", 1, '发送成功');
                return json(['data'=>'','status'=>1,'type'=>$res['type'],'msg' => '发送成功']);
			}
		}else if ($res['status'] == -1) {
            return $this->json("", -1, $res['msg']);
        }
		else{
			return $this->json('', 0, $res['msg']);
		}
	}

	/*
	 * 个人主页
	 */
	public function userCenter(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$user_info = $user->userCenter($uid);
		if(!$user_info){
			return $this->json('', 0, '用户不存在');
		}

		return $this->json($user_info);
	}
	/*
	 * 个人主页2（个推专用）
	 */
	public function userCenters(){
		$uid = input('request.uid');
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$user_info = $user->userCenter($uid);
		if(!$user_info){
			return $this->json('', 0, '用户不存在');
		}

		return $this->json($user_info);
	}

	/*
	 * 我的账户
	 */
	public function userAccount(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$account_info = $user->userAccount($uid);

		return $this->json($account_info);
	}

	/*
	 * 账户明细
	 */
	public function accountLog(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$type = input('request.type');
		if(!$type){
			return $this->json('', 0, '未知参数');
		}
		$p = input('request.p');
		$user = new UserService();
		$account_info = $user->accountLog($uid, $type, $p);

		return $this->json($account_info);
	}

	/*
	 * 我的优惠券
	 */
	public function userCoupon(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$coupon_info = $user->getCoupon($uid);
		if(!$coupon_info){
			return $this->json('', 0, '无优惠券');
		}
		return $this->json($coupon_info);
	}
	/*
	 * 优惠券可用于的商品
	 */
	public function goodsCoupon(){
		$user = new UserService();
		$p = input('request.p',1);
		$coupon_id = input('request.coupon_id');	
		$coupon_goods = $user->goodsCoupon($coupon_id,$p);
		if(!$coupon_goods){
			return $this->json('', 0, '无优惠券商品');
		}
		return $this->json($coupon_goods);
	}
	/*
	 * 我的积分
	 */
	public function userPoints(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$points_info = $user->userPoints($uid);
		return $this->json($points_info);
	}

	/*
	 * 积分明细
	 */
	public function pointsLog(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$type = input('request.type');
		$uid = $this->getUid($token, $user_id);
		
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$p = input('request.p');
		$p = $p ? $p : 1;
		$month = input('request.month');
		$user = new UserService();
		$points_log = $user->pointsLog($uid, $p, $month,$type);
		return $this->json($points_log);
	}

	/*
	 * 我的足迹
	 */
	public function userTrack(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$p = input('request.p');
		$user = new UserService();
		$track_info = $user->userTrack($uid, $p);
		if($track_info){
			return $this->json($track_info);
		}
		return $this->json('', 0, '暂无数据');
	}

	/*
	 * 足迹编辑
	 */
	public function trackEdit(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$tid = input('request.tid');
		if(!$uid || !$tid){
			return $this->json('', 0, '未知参数');
		}
		
		$user = new UserService();
		$res = $user->trackEdit($uid, $tid);
		if($res){
			return $this->json('', 1, '删除成功');
		}
		else return $this->json('', 0, '删除失败');
	}

	/*
	 * 我的地址
	 */
	public function userAddr(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$addr_info = $user->userAddr($uid);
		return $this->json($addr_info);
	}

	/*
	 * 地址详情
	 */
	public function addrInfo(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$addr_id = input('request.addrid');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$addr_info = $user->addrInfo($uid, $addr_id);
		return $this->json($addr_info);
	}

	/*
	 * 地址新增或编辑
	 */
	public function addrEdit(){

		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$data = [
			'addr_province' => input('request.province'),
			'addr_city' => input('request.city'),
			'addr_area' => input('request.district'),
			'addr_cont' => input('request.cont'),
			'post_no' => input('request.postno'),
			'is_default' => input('request.is_default'),
			'addr_receiver' => input('request.receiver'),
			'addr_phone' => input('request.phone'),
			'addr_id' => input('request.addrid') ? input('request.addrid') : 0,
			'addr_add_time' => time(),
		];
		if(!$uid || !$data){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res = $user->addrEdit($uid, $data);
		return $this->json($res);
		if($res){
			return $this->json('', 1, '保存成功');
		}
		else return $this->json('', 0, '保存失败');
	}

	/*
	 * 设置默认地址
	 */
	public function setDefault(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$addr_id = input('request.addrid');
		// $is_default = input('request.is_default');
		if(!$uid || !$addr_id){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res = $user->addrDefault($uid, $addr_id);
		if($res){
			return $this->json('', 1, '设置成功');
		}
		else return $this->json('', 0, '设置失败');
	}

	/*
	 * 地址删除
	 */
	public function addrDel(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$addr_id = input('request.addrid');
		if(!$uid || !$addr_id){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res = $user->addrDel($uid, $addr_id);
		if($res){
			return $this->json('', 1, '删除成功');
		}
		else return $this->json('', 0, '删除失败');
	}

	/*
	 * 我的收藏
 	 */
	public function userFavor(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$type = input('request.type');
		$p = input('request.p') ? input('request.p') : 1;
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$favorites = $user->userFavor($uid, $type, $p);
		return $this->json($favorites);	
	}

	/*
	 * 收藏素材删除
	 */
	public function favorDel(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$favor_id = input('request.fid');
		if(!$uid || !$favor_id){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res = $user->favorDel($uid, $favor_id);
		if($res){
			return $this->json('', 1, '删除成功');
		}
		else return $this->json('', 0, '删除失败');
	}

	/*
	 * 我的素材
	 */
	public function userMate(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$p = input('request.p');
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$mate_info = $user->userMate($uid, $p);	
		if($mate_info){
			return $this->json($mate_info);
		}
		return $this->json('', 0, '暂无数据');
		return $this->json($mate_info);
	}

	/*
	 * 素材详情
	 */
	public function mateInfo(){
		$user_id = input('request.uid');
		$type = input('type', '');
        if($user_id){
			$token = input('request.token');
			$user_id = $this->getUid($token, $user_id);
			if(!$user_id){
				return $this->json('', 0, '未知参数');
			}
		}
		
		$mate_id = input('request.mateid');
		
		$user = new UserService();
		$mate_info = $user->mateInfo($user_id, $mate_id, $type);
		return $this->json($mate_info);
	}

	/*
	 * 素材编辑
	 */
	public function mateEdit(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
	 
		$data = [
			'm_goods_id' => input('request.goods_id',0),
			'mate_content' => input('request.content'),
			'mate_add_time' => time(),
			'mate_thumb' => input('request.thumb'),
			'mate_video' => input('request.mate_video'),
			'm_cat_id' => input('request.cat_id'),
			'm_id' => input('request.m_id') ? input('request.m_id') : 0,
			'm_type' => input('request.type') ? input('request.type') : 0
		];

	/* 	if($data['mate_thumb']){
		    $niuyun_model = new Niuyun();
			$img_upload = $niuyun_model->qiniu_upload($data['mate_thumb']);
           $img_upload = $this->imgBaseUpload($data['mate_thumb']);
			if(!$img_upload['code']){
				return $this->json('', 0, $img_upload['msg']);
			}
			$data['mate_thumb'] = $img_upload['data'];
		} */

		if(!$uid || !$data){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res = $user->mateEdit($uid, $data);
		if($res){
			//话题参与量增加
			if($data['type'] == 2){
				Db::name('topic')->where('tp_id',$data['m_cat_id'])->setInc('tp_partake_num');
			}
			return $this->json('', 1, '保存成功');
		}
		else if($res  == -1){
			return $this->json('', 0, '未提交新内容');
		}
		return $this->json('', 0, '保存失败');
	}
	
	/*
	 * 素材分类
	 
	 */
	public function mateCat(){
		$type = input('request.type');
		$user = new UserService();
		$list = $user->mateCat($type);
		if($list){
			return $this->json($list);
		}
		else return $this->json('', 0, '暂无数据');
	}

	/*
	 * 素材删除
 	 */
	public function mateDel(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$mate_id = input('request.mid');
		if(!$uid || !$mate_id){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res = $user->mateDel($uid, $mate_id);
		return $this->json($res);
		if($res){
			return $this->json('', 1, '删除成功');
		}
		else return $this->json('', 0, '删除失败');
	}

	/*
	 * 素材-搜索商品
 	 */
	public function mateSearch($uid){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$goods_name = input('request.goods_name');
		$user = new UserService();
		$goods_info = $user->mateSearch($uid, $goods_name);	
		if($goods_info){
			return $this->json($goods_info);
		}else{
			return $this->json('', 0, '暂无数据');
		}
		
	}

	/*
	 * 个人资料页
	 */
	public function myInfo(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$info = $user_service->myInfo($uid);
		return $this->json($info);
	}

	/*
	 * 个人资料修改
	 */
	public function myInfoEdit(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$data = [
			'user_name' => input('request.user_name', ''),
			'user_avat' => input('request.user_avat'),
			'user_sex' => input('request.user_sex',0),
			'user_birth' => input('request.user_birth'),
			'user_hobby' => input('request.user_hobby'),
			'user_addr' => input('request.user_addr'),
			'user_sign' => input('request.user_sign'),
		];

		$user_service = new UserService();
		$result = $user_service->myInfoEdit($uid, $data);

		if($result == -1){
			return $this->json('', 0, '昵称已被占用');
		}
		// else if($result == -2){
		// 	return $this->json('', 0, '店铺不存在');
		// }
		else if(!$result){
			return $this->json('', 0, '保存失败');
		}
		else return $this->json('', 1, '保存成功');
	}

	/*
	 * 实名认证人提交页面
	 */
	 public function myInfoAuth(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		//实名认证表添加新数据	
		$authInsert = [
		    'auth_uid' => input('request.uid'),//会员id
			'auth_truename' => input('request.auth_truename'),//真实姓名
		    'auth_id_no' => input('request.auth_id_no'),//身份证号     
			'auth_phone' => input('request.auth_phone'),//联系方式
			'auth_id_front' => input('request.auth_id_front'),//身份证人像照
			'auth_id_back' => input('request.auth_id_back'), //身份证国徽照
			'auth_stat'=>1,
			'auth_id_people' =>  '',//手持身份证照
			'auth_addtime' => time(),//auth_addtime  申请时间 
		];
		$user_service = new UserService();
		$result = $user_service->Auth($uid,$authInsert);
 
		if($result == -1){
			return $this->json('', -1, '已经实名认证');
		}else if($result == 0){
			return $this->json('', 0, '实名认证资料提交失败');
		}
	    return $this->json('', 1, '实名认证资料提交成功');
	}
	/*
	 * 实名认证展示页面
	 */
	 public function myInfoAuthshow(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->showAuth($uid);
		if($result == 0){
			return $this->json('', 0, '获取失败');
		}

	    return $this->json($result);
	}
	/*
	 * 用户提现
	 */
	 public function myInfoCash(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$data = [
		    'cash_uid' => input('request.uid'),//会员id
		    'cash_amount' => abs(input('request.cash_amount')),//提现金额     
			'cash_appli' => input('request.cash_appli'),//申请人姓名
			'cash_way' => input('request.cash_way'),//提现方式：1，支付宝提现；2，微信提现；3，银行卡提现
			'cash_stat' => 1,//处理状态：1，未处理；2，支付完成；3，申请未通过
			'cash_addtime' => time(),// 申请时间 
		];
		//账户明细
		$accountlog = [
		    'a_uid' => input('request.uid'),//会员id
		    'acco_num' => (0 - abs(input('request.cash_amount'))),//账户变化总额 为负值    
			'acco_type' => 1,//账户变更类型：1，提现；2，购物；3，充值；4，返利；5，分享；6，买购物券 
			'acco_desc' => '提现',//账户变更详情
			'acco_time' => time(),// 日志创建时间 
		];
		$cash_way  = input('request.cash_way');
		if($cash_way == 1 ){
			$data['cash_ali_name'] = input('request.cash_account');//支付宝昵称
			$data['cash_ali_no'] = input('request.cash_account_no');//支付宝账号
		}else if($cash_way == 2){
			$data['cash_wx_no'] = input('request.cash_account_no');//微信账号
		}else if($cash_way == 3){
			$data['cash_bank'] = input('request.cash_account'); //提现银行
			$data['cash_bank_no'] = input('request.cash_account_no');//银行卡号
		}
		$user_service = new UserService();
		$result = $user_service->Cash($uid,$data,$accountlog);
		if($result == -1){
			return $this->json('', -1, '超出提现余额');
		}else if($result == 0){
			 return $this->json('', 0, '提交失败');
		} 
	   return $this->json('', 1, '提交成功');
	}
	/*
	 * 实用户提现展示页面
	 */
	 public function myInfoCashShow(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->showCash($uid);
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 * 我的充值卡
	 */
	 public function myInfoCardShow(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->showCard($uid);
		if($result == 0){
			return $this->json('', 0, '获取失败');
		}
		foreach ($result as &$val) {
            $val['card_add_time'] = date('Y-m-d H:i', $val['card_add_time']);
		    $val['card_end_time'] = date('Y-m-d H:i', $val['card_end_time']);
        }
	    return $this->json($result);
	}
	/*
	 * 设置 修改密码
	 */
	 public function myPasswdEdit(){
		$user_id = input('request.uid'); 	
		$token = input('request.token');
		$password_old = input('request.password_old');
		$password = input('request.password');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->editPassword($uid,$password_old,$password); 
		if($result == -1){
			return $this->json('', -1, '原始密码错误');
		}else if($result == 0){
			return $this->json('', 0, '修改失败');
		} 
	    return $this->json('',1,'修改成功');
	}
	/*
	 * 设置 意见反馈
	 */
	 public function myFeedback(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$data =[
			'op_uid'  => $uid,
			'op_type'  => input('request.op_type'),//反馈类型
			'op_content' => input('request.op_content'),//反馈内容
			'op_contact'  =>  input('request.op_contact'),//会员联系方式
			'op_add_time'  => time(),//反馈时间		
		];
		$user_service = new UserService();
		$result = $user_service->Feedback($data); 
		if($result == 0){
			return $this->json('', 0, '提交失败');
		} 
	    return $this->json($result);
	}
	/*
	 * 设置 意见反馈类型
	 */
	 public function myFeedtype(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->FeedbackType(); 
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 * 设置 帮助中心
	 */
	 public function myHelp(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->helpCenter(); 
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 * 设置 帮助中心
	 */
	 public function myHelpRead(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$content_id = input('request.content_id');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->helpRead($content_id); 
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 * 设置 关于我们
	 */
	 public function myAbout(){
		// $user_id = input('request.uid');
		// $token = input('request.token');
		// $uid = $this->getUid($token, $user_id);
		// if(!$uid){
		// 	return $this->json('', 0, '未知参数');
		// }
		$user_service = new UserService();
		$result = $user_service->aboutUs(); 
		 
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 * 应用设置 
	 */
	 public function mySetting(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->appSetting($uid);
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 * 应用设置 状态修改 
	 */
	 public function mySetEdit(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uset_id = input('request.id');
		$status = input('request.status');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->appSetEdit($uid,$uset_id,$status);
		if($result == 0){
			return $this->json('', 0, '修改失败');
		} 
	    return $this->json('',1,'修改成功');
	}
	/*
	 *  消息  活动消息
	 */
	 public function activity_News(){
		$user_service = new UserService();
		$result = $user_service->getCenter('活动消息'); 
		foreach($result as &$val){
			$val['create_time'] = date('Y-m-d H:i',$val['create_time']);
		}
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 *  消息分类列表
	 */
	public function NewsTypeList(){
		$category_name = trim(input('request.name'));
		$user_service = new UserService();
		$result = $user_service->getTypeList($category_name='常见问题'); 
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	
	/*
	 *  消息分类列表
	 */
	public function contentList(){
		$category_name = trim(input('request.name'));
		$user_service = new UserService();
		$result = $user_service->getContentList($category_name); 
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	
	/*
	 *  客服自动回复消息
	 */
	public function replyMessage(){
		$user_service = new UserService();
		$result = $user_service->getMessageList(); 
		if($result == 0){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
		
	}
	/*
	 *  客服消息  活动消息
	 */
	public function NewsList(){
		$category_id = input('request.category_id');
		$user_service = new UserService();
		$result = $user_service->getCenterList($category_id); 
		foreach($result['list'] as &$val){
			$val['create_time'] = date('Y-m-d H:i',$val['create_time']);
		}
		if(!$result){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 *  客服消息  活动消息
	 */
	public function NewsMatter(){
		$content_id = input('request.content_id');
		$user_service = new UserService();
		$result = $user_service->getMatter($content_id); 
		if(!$result){
			return $this->json('', 0, '获取失败');
		} 
		$result['create_time'] = date('Y-m-d H:i',$result['create_time']); 
	    return $this->json($result);
	}
	/*
	 *  客服消息  常见问题列表
	 */
	public function NewsFamiliar(){
		$user_service = new UserService();
		$result = $user_service->getFamiliar(); 
		foreach($result as $key=>$val){
			$result[$key]['title'] = $key + 1 .'.'.$val['title'];
			$val['create_time'] = date('Y-m-d H:i',$val['create_time']);
		}
		if(!$result){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 *  客服消息  在线客服
	 */
	public function NewsOnline(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_service = new UserService();
		$result = $user_service->getOnline($uid);
		if(!$result){
			return $this->json('', 0, '获取失败');
		} 
	    return $this->json($result);
	}
	/*
	 *  客服消息  在线客服
	 */
	public function sendOnline(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$touid = input('request.touid');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$content = input('request.content');//内容
		$type = input('request.type');//1文字 2图片 3语音
		$data = [
			'genre' => 1,
			'content' => $content,
			'type' => $type,
			'uid' => $uid,
			'touid' => $touid,
		];
		$user_service = new UserService();
		$res = $user_service->sendOnline($data);
		if(!$res){
			return $this->json('', 0, '获取失败');
		} 
	   return $this->json('', 1, '发送成功');
	}
	/*
	 * 优惠券获取成功
	 */
	public function CouponInfo()
    {
        $c_id = input('c_id');
		if(!$c_id){
            return $this->json('', 0, '未知参数');
        }
        $couponmodel = new Coupon();
		$couponInfo = $couponmodel->CouponInfo($c_id);
		if($couponInfo){
			$add_time = $couponInfo['add_time'];
			if($couponInfo['add_time']<time()){
				$add_time = time();
			}
			$day = $couponInfo['coupon_aval_time'] - $add_time;
			$couponInfo['day'] = floor($day/(3600*24));
			$couponInfo['coupon_aval_time'] = date('y/m/d H:i',$couponInfo['coupon_aval_time']);
			$couponInfo['add_time'] = date('y/m/d H:i',$couponInfo['add_time']);
			
			
		    return $this->json($couponInfo);
        } else {
            return $this->json([], 0, "获取优惠券失败");
        }
	}
	/*
	 * 转增优惠券详情
	 */
	public function giveCouponInfo()
    {
        $user_id = input('user_id');
        $c_id = input('c_id');
        $couponmodel = new Coupon();
        $info = $couponmodel->getCouponInfo($c_id);
        if ($info['c_uid'] == $user_id) {
            $info['coupon_aval_time'] = date('Y.m.d', $info['add_time']+$info['coupon_aval_time']*3600*24);
            $info['add_time'] = date('Y.m.d', $info['add_time']);
            $info['coupon_thumb'] = $couponmodel->getCouponInfos($info['coupon_id']);
            return $this->json($info, 1);
        } else {
            return $this->json([], 0, "获取优惠券失败");
        }

    }
    /*
     * 领取转增优惠券
     */
    public function receiveCoupon()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $c_id = input('c_id');
        $to_uid = input('to_uid');
        // 检测优惠券 是否已领
        $couponmodel = new Coupon();
        $res =$couponmodel->is_yongyou($c_id, $to_uid);
        if (!$res) {
            return $this->json('', 0, '该优惠券已被其他用户领取');
        }
		if($to_uid == $user_id ){
			 return $this->json('', 0, '不能领取自己分享的优惠券！');
		}
        $res = $couponmodel->saveCouponStatus($c_id);
        if (!$res) {
            return $this->json('', 0, '转增失败');
        }
        $info = $couponmodel->getCouponInfo($c_id);
        if (!empty($info)) {
            unset($info['c_id']);
            $info['c_uid'] = $uid;
            $info['coupon_stat'] = 1;
            $res = $couponmodel->addCoupon($info);
            if (!empty($res)) {
				 $c_id = $couponmodel->getCouponID();
                return $this->json($c_id, 1, '领取成功');
            }
        }
        return $this->json('', 0, '转增失败');
    }

    /*
     * 设置支付密码
     */
    public function setPayPwd(){
    	$code = trim(input('request.code'));
    	if(!$code){
    		return $this->json('', 0, '验证码不能为空');
    	}

    	$mobile = input('request.mobile');
    	$user = new UserService();
    	$stat = $user->checkCode($mobile, $code, 4);
		//验证
		if(!$stat){
			return $this->json('', 0, '验证码不正确');
		}
		if($stat == -1){
			return $this->json('', 0, '验证码已过期');
		}

    	$pwd = input('request.pwd');
    	if(!$pwd){
    		return ['code' => 0, 'msg' => '支付密码不能为空'];
    	}
    	$result = $user->setPaypwd($mobile, $pwd);
    	return $this->json('', $result['code'], $result['msg']);
    }
	/*
     * 是否设置支付密码
     */
    public function isPayPwd(){
		$user = new UserService();
    	$user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
    	$res = $user->isPayPwd($uid);
		if(!$res){
			return $this->json('', 0, '未设置支付密码');
		}
    	return $this->json('', 1, '设置支付密码');
    }

    /*
     * 修改支付密码
     */
    public function resetPayPwd(){
    	$code = trim(input('request.code'));
    	if(!$code){
    		return $this->json('', 0, '验证码不能为空');
    	}

    	$mobile = input('request.mobile');
    	$user = new UserService();
    	$stat = $user->checkCode($mobile, $code, 4);
		//验证
		if(!$stat){
			return $this->json('', 0, '验证码不正确');
		}
		if($stat == -1){
			return $this->json('', 0, '验证码已过期');
		}

		$old_pwd = input('request.old_pwd');
    	$pwd = input('request.pwd');
    	if(!$pwd){
    		return $this->json('', 0, '支付密码不能为空');
    	}
    	$result = $user->resetPaypwd($mobile, $old_pwd, $pwd);
    	return $this->json('', $result['code'], $result['msg']);
    }

    /*
     * 验证支付密码
     */
    public function checkPayPwd(){
    	$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}

		$pwd = input('request.pwd');
		$user = new UserService();
		$result = $user->checkPayPwd($uid, $pwd);
		return $this->json('', $result['code'], $result['msg']);
    }

    /*
     * 是否设置支付密码
     */
    public function hasPayPwd(){
    	$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}

		$info = Db::name('users')->where('user_id', $uid)->field('user_pay_pwd')->find();
		if(!$info['user_pay_pwd']){
			return $this->json('', -1, '未设置支付密码');
		}
		return $this->json('', 1, '已设置支付密码');
    }

    /*
     * 我的充值卡
     */
    public function myRcharge(){
    	$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$type = input('request.type', 1);
		$p = input('request.p', 1);
		$user = new UserService();
		$list = $user->myRecharge($uid, $p, $type);
		if(!$list['code']){
			return $this->json('', 0, '获取失败');
		}
		else return $this->json($list['data']);
    }

    /*
     * 我的元宝
     */
    public function myYz(){
    	$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$type = input('request.type');
		$p = input('request.p', 1);
		$user = new UserService();
		$list = $user->myYz($uid, $p, $type);
		if(!$list['code']){
			return $this->json('', 0, '获取失败');
		}
		else return $this->json($list['data']);
    }
	/*
     * 获取上级id
     */
    public function getSuperior(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$sup_id  = $user->getSuperior($uid);
		if(!$sup_id){
			return $this->json('', 0, '获取失败');
		}
		else return $this->json($sup_id);
	}
	
	/*
     * 检验手机号 是否存在
     */
    public function checkMobile(){
		$mobile = input('request.mobile');
		if(!$mobile){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res  = $user->checkMobile($mobile);
		if($res == '-1'){
			return $this->json('', -1, '用户不存在！');
		}
		else if($res == 0){
			return $this->json('', 0, '已经是店主！');
			
		}else{
		 
			return  json(['status'=> 1,'msg'=>'该用户是VIP！','data'=>$res]);
		} 
		
	}
	/*
     * 点赞
     */
    public function giveLike(){
		$user_id = input('request.uid');
		$type = input('request.type');
		$topicId = input('request.topicId');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res  = $user->giveLike($uid,$topicId,$type);
		if($res==-1){
			return $this->json('', -1, '已经点赞了！');
		}else if($res==1){
			return  $this->json('', 1, '点赞成功');
		}else{
			return  $this->json('', 0, '点赞失败');
		} 
		
	}

    /*
     * 新手课堂
     */
    public function newClass()
    {
        $category_id = db('content_category')->where('category_name','新手课堂')->value('category_id');
        if($category_id){
            $list = db('content')->where(['category_id'=>$category_id,'status'=>'normal'])->field('content_id,title,description,picture,content')->order('weigh desc')->select();
        }else{
            $list = [];
        }
        if (!empty($list)) {
            return  $this->json($list);
        } else {
            return  $this->json([], 0);
        }
    }

    public function classDetail()
    {
        $content_id = input('request.content_id');
        $content = db('content')->where(['content_id'=>$content_id,'status'=>'normal'])->field('content,title')->find();
        if (!empty($content)) {
            return  $this->json($content);
        } else {
            return  $this->json([], 0);
        }
    }
	/* 
	*合陶技术测试
	*/
	public function myComment(){
		$uid = input('request.user_id');
		$user_info = db('users')->where('user_id',$uid)->field('user_name,user_avat')->find();
		$order_id = input('request.order_id');
		$list = db('order_remark')->where(['or_uid'=>$uid,'or_order_id'=>$order_id])->select();
		if (!empty($user_info) && !empty($list)) {

			foreach ($list as &$v){
				$v['or_thumb'] = explode(',', $v['or_thumb']);
				$v['or_add_time'] = date('Y-m-d H:i:s', $v['or_add_time']);
				$v['goods'] = [];
				$goods = db('goods')->where(['goods_id'=>$v['or_goods_id']])->field('goods_name,picture,price')->find();
				if(!empty($goods)){
					$v['goods'] = $goods;
				}
			}
			$data = [
				'user'=>$user_info,
				'list'=>$list
			];
			return  $this->json($data);
		} else {
			return  $this->json([], 0);
		}
	}
	
	/* 
	*订单提醒
	*/
	public function orderRing(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user = new UserService();
		$res  = $user->orderRing($uid);
		if($res){
			return $this->json($res, 1, '获取成功');
		}else{
			return  $this->json('', 0, '获取失败');
		} 
		
	}
	/* 
	* 新用户
	*/
	public function isNew(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$res = Db::name('order')->where(['order_uid' => $uid, 'order_status' => ['neq', 5]])->find();
		if($res){
			return $this->json('', 0, '您已经是老用户了');
		}else{
			return  $this->json('', 1, '不是老用户');
		} 
		
	}
	
	/* 
	*  获取银行名称
	*/
	public function cashbank(){
		$user = new UserService();
		$res  = $user->cashbank();
		if($res){
			return $this->json($res, 1, '获取成功');
		}else{
			return  $this->json('', 0, '获取失败');
		} 
		
	}

	/*
	*
	*/
	public function cashHisroty()
	{
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		$page = input('request.page',1);
		$size = 20;
		$start = ($page-1)*$size;
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$res = Db::name('cash')->where('cash_uid',$uid)->field('cash_id,cash_no,cash_amount,cash_addtime,cash_way,cash_stat,cash_comm')->order('cash_addtime desc')->limit($start,$size)->select();
		if($res){
			foreach ($res as &$v) {
				$v['cash_addtime'] = date('Y-m-d H:i:s',$v['cash_addtime']);
			}
		}
		return $this->json($res, 1, '获取成功');
	}

	/**
     * 获取消息列表
     */
	public function getMessage()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $page = input('request.page',1);
        $size = 10;
        $start = ($page-1)*$size;
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $where = [
            'mp_address'=>0,
            'mp_send_time'=>['<=',time()],
            'mp_type'=>1,
        ];
        $is_seller = Db::name('users')->where(['user_id'=>$uid])->value('is_seller');
        if($is_seller){
            $s_grade = Db::name('store')->where(['s_uid'=>$uid])->value('s_grade');
            if(!$s_grade) return $this->json('', 0, '未知参数');
            $where['mp_name'] = ['in',[0,5,$s_grade+1]];
        }else{
            //vip
            $where['mp_name'] = ['in',[0,1]];
        }
        $total = Db::name('message_push')->where($where)->count();
        $messagePush = Db::name('message_push')->where($where)->limit($start,$size)->order('mp_add_time desc')->field('mp_id,mp_content,mp_add_time')->select();
        if($messagePush){
            $mp_id = array_column($messagePush,'mp_id');
            $md_where = [
                'user_id'=>$uid,
                'mp_id'=>['in',$mp_id],
                'md_type'=>1
            ];
            $nread_mp_id = Db::name('message_descript')->where($md_where)->column('mp_id');
            foreach ($messagePush as &$one){
                $one['mp_add_time'] = date('Y-m-d H:i',$one['mp_add_time']);
                $one['is_read'] = 1;//已读
                if(empty($nread_mp_id)) continue;
                if(in_array ( $one['mp_id'] ,  $nread_mp_id )){
                    $one['is_read'] = 0;//未读
                }
            }
            //改为已读
            Db::name('message_descript')->where(['mp_id'=>['in',$nread_mp_id]])->update(['md_type'=>2]);
        }
        return $this->json(['message'=>$messagePush,'total'=>$total], 1, '获取成功');
    }

    public function isNewMessage()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $where = [
            'user_id'=>$uid,
            'md_type'=>1,
            'md_send_time'=>['<=',time()]
        ];
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $count = Db::name('message_descript')->where($where)->count();
        return $this->json($count, 1, '获取成功');
    }
    //充值卡记录
    public function rcLog(){
    	$user_id = input('request.uid');
    	$user_rc_id = input('request.card_id');
    	$token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $where = [
        	'uid' => $user_id,
        	'user_rc_id' => $user_rc_id
        ];
        $rcLog = Db::name('rc_log')->where($where)->order('time desc')->field('time,price,use_type')->select();
        foreach ($rcLog as &$value) {
        	if($value['use_type'] == 1){
        		$value['use_type'] = '返还充值卡';
				$value['price'] = "+".$value['price'];
        	}else{
        		$value['use_type'] = '支付了订单';
        		$value['price'] = "-".$value['price'];
        	}
        	$value['time'] = date('Y-m-d H:i', $value['time']);
        }
		return $this->json($rcLog, 1, '获取成功');
    }
}

 
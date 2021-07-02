<?php
namespace app\api\controller;

use app\common\service\User as UserService;
use app\common\service\Login as LoginService;
use app\common\model\Config as ConfigModel;
use app\common\model\Users as UserModel;
use think\Db;

class Login extends Common{

   /*
	* 三方登录
	*/
	public function apiLogin(){
		$user_service = new UserService();
		$login_service = new LoginService();
		$type = input('request.type');
		$where = [];
		$api_id = '';
		if($type == 'weixin'){
			$unionid = input('request.unionid');
			if(!$unionid){
				return $this->json('', 0, '未知参数');
			}
			$data = [
				'user_avat' => input('request.headimgurl'),
				'user_sex' => (int)input('request.sex') - 1,
				'user_name' => input('request.nickname'),
                'user_wx' => input('request.nickname'),
				'user_addr' => input('request.province').' '.input('request.city'),
			];
			$where['unionid'] = $api_id = $unionid;
		}
		else if($type == 'qq'){
			$openid = input('request.openid');
			if(!$openid){
				return $this->json('', 0, '未知参数');
			}
			$data = [
				'user_avat' => input('request.figureurl_qq_1'),
				'user_sex' => input('request.gender') == '男' ? 0 : 1,
				'user_name' => input('request.nickname'),
                'user_qq' => input('request.nickname'),
			];
			$where['openid'] = $api_id = $openid;
		}
		if(!$where){
			return $this->json('', 0, '未知参数');
		}
		$user_id = $user_service->userInfo($where, 'user_id');
        $clientId = input('clientId');
        $app_system = input('app_system', 'Android');
        if ($app_system == 'Android') {
            $app_system = 2;
        } else {
            $app_system = 1;
        }
		//登录
		if($user_id){
			$res = $login_service->apiLogin($user_id['user_id'], $clientId, $app_system);
		}
		//注册
		else{
            $data['client_id'] = $clientId;
            $data['app_system'] = $app_system;
			$res = $login_service->apiRegister($type, $api_id, $data);
		}

		if(!$res){
			return $this->json('', 0, '登录失败');
		}
		else return $this->json($res, 1, '登录成功');
	}

	/*
	 * 绑定手机号
	 */
	public function mobileBind(){
		$uid = $this->getUid(input('request.token'), input('request.uid'));
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$mobile = input('request.mobile');
		if(!$mobile){
			return $this->json('', 0, '手机号不能为空');
		}
		$code = input('request.code');
		if(!$code){
			return $this->json('', 0, '验证码不能为空');
		}

		$user_service = new UserService();

		$status = $user_service->checkCode($mobile, $code, 3);
		//验证
		if(!$status){
			return $this->json('', 0, '验证码不正确');
		}

		if($status == -1){
			return $this->json('', 0, '验证码已过期');
		}

		$pwd = input('request.pwd', '');
//		if(!$pwd){
//			return $this->json('', 0, '密码不能为空');
//		}
        //验证邀请码
        $invite_code = input('request.invite_code');
        $user_info = Db::name('users')->where('user_mobile', $mobile)->field('user_id as uid, token, is_seller')->find();
        if(!$user_info && !$invite_code){
            return $this->json('', 0, '邀请码不能为空');
        }

        $invite_info = $user_service->checkInviteCode($invite_code);
        if (!$invite_info && !$user_info) {
            return $this->json('', 0, '邀请码错误');
        }
		$login_service = new LoginService();
		$result = $login_service->mobileBind($uid, $mobile, $pwd, $invite_info['user_id']);
		if(!$result){
			return $this->json('', 0, '绑定失败');
		}
		else return $this->json($result, 1, '绑定成功');
	}
    /*
     *  公众号绑定手机号
     */
    public function jsApimobileBind(){
        $uid = $this->getUid(input('request.token'), input('request.uid'));
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $mobile = input('request.mobile');
        if(!$mobile){
            return $this->json('', 0, '手机号不能为空');
        }
        $code = input('request.code');
        if(!$code){
            return $this->json('', 0, '验证码不能为空');
        }

        $user_service = new UserService();

        $status = $user_service->checkCode($mobile, $code, 3);
        //验证
        if(!$status){
            return $this->json('', 0, '验证码不正确');
        }

        if($status == -1){
            return $this->json('', 0, '验证码已过期');
        }

//        $pwd = input('request.pwd');
//        if(!$pwd){
//            return $this->json('', 0, '密码不能为空');
//        }
        // 默认 为空
        $pwd = '';
        //验证邀请码
        $invite_code = input('request.invite_code');
        $user_info = Db::name('users')->where('user_mobile', $mobile)->field('user_id as uid, token, is_seller')->find();
        if(!$user_info && !$invite_code){
            return $this->json('', 0, '邀请码不能为空');
        }

        $invite_info = $user_service->checkInviteCode($invite_code);
        if (!$invite_info) {
            return $this->json('', 0, '邀请码错误');
        }
        $login_service = new LoginService();
        $result = $login_service->mobileBind($uid, $mobile, $pwd, $invite_info['user_id']);
        if(!$result){
            return $this->json('', 0, '绑定失败');
        } else {
            session('uid', $result['uid']);
            session('token', $result['token']);
            return $this->json($result, 1, '绑定成功');
        }
    }

}
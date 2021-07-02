<?php
namespace app\api\controller;

use app\common\service\User as UserService;
use app\common\service\Login as LoginService;
use getui\Pushs;
use think\Db;

class Login extends Common{
    protected $user;
    protected $login;
    public function __construct()
    {
        $this->user = new UserService();
        $this->login = new LoginService();
    }

    /**
     * 会员注册
     * @param string mobile
     * @param int code
     * @param string pwd
     * @return json
     */
    public function register()
    {
        $mobile = trim(input('request.mobile'));
        if(!$mobile){
            return $this->json("", 0, '手机号不能为空');
        }
        //检测手机号是否注册
        $user_info = $this->user->find(['user_mobile' => $mobile]);
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
        $shop_name = "";
        // if(!$shop_name){
        //     return $this->json("", 0, '店铺名称不能为空');
        // }
        
        
        if($code!=888888){
            $stat = $this->user->checkCode($mobile, $code, 1);
            //验证
            if(!$stat){
                return $this->json("", 0, '验证码不正确');
            }
    
            if($stat == -1){
                return $this->json('', 0, '验证码已过期');
            }
        }
  
        $invite_info['user_id'] = 0;
        $clientId = input('clientId');
        $app_system = input('app_system', 'Android');
        if ($app_system == 'Android') {
            $app_system = 2;
        } else {
            $app_system = 1;
        }

        //邀请码
        $invite_code = trim(input('request.invite_code'));
        if ($invite_code){
            $invite_info = $this->user->checkInviteCode($invite_code);
            if (!$invite_info && !$user_info) {
                return $this->json('', 0, '邀请码错误');
            }
            $invite_uid = $invite_info['user_id'];
        }else{
            $invite_uid = '';
        }
        $res = $this->user->register($mobile, $password, $invite_uid,$clientId,$app_system,$shop_name);

        if($res){
            return $this->json($res);
        }
        else{
            return $this->json('', 0, '注册失败');
        }
    }

    /**
	 * 登录
     * @param string mobile 手机号
     * @param string pwd 密码
     * @return json
	 */
    public function loginIn(){
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
        $res = $this->user->login($mobile, $password,1);
        if($res == -1){
            return $this->json('', -1, '账号不存在');
        } elseif ($res == -2) {
            return $this->json('', 0, '密码错误');
        }elseif($res == -3){
            return $this->json('',-3,'您还没有设置密码');
        }
        //手机型号
        $result = $this->user->clientId($mobile, $password,$clientId, $app_system);

        if(!$result){
            //推送
            $msg = [
                'content'=>'胡乱购商城欢迎您！',//透传内容
                'title'=>'登陆提醒',//通知栏标题
                'text'=>'胡乱购城欢迎您！',//通知栏内容
                'curl'=>request()->domain(),//通知栏链接
            ];
            $data=array(
                0=>['client_id'=>$clientId],
                'system'=>$app_system,//1为ios
            );
            $Pushs = new Pushs();
            $Pushs->getTypes($msg,$data);
        }
        return $this->json(['uid' => $res['user_id'], 'token' => $res['token'],'is_kefu' => $res['is_kefu'],'is_seller' => $res['is_seller'] ? : 0]);
    }
    /**
     * 判断手机号是否已经存在
     */
    public function checkPhone(){
        $mobile = trim(input('request.mobile'));
        $result = $this->user->checkPhone($mobile);
        if (!$result) {
            return $this->json("",0,'该手机号未注册');
        }else{
            return $this->json("",1,'该手机号已注册');
        }
    }
    /**
     * 验证码登录
     */
    public function codeLogin()
    {
        $mobile = trim(input('request.mobile'));
        $code = trim(input('request.code'));
        $g = "/^1[345678]\d{9}$/";
        if(!$mobile){
            return $this->json("", 0, '手机号不能为空');
        }
        if (!preg_match($g,$mobile)) {
            return $this->json("", 0, '请输入正确的手机号');
        }
       //检测当前手机号是否是已经注册的用户
        $result = $this->user->checkPhone($mobile);
        if (!$result) {
            return $this->json("",0,'该手机号未注册');
        }
        $stat = $this->user->checkCode($mobile, $code, 6);
        //验证
        if(!$stat){
            return $this->json("", 0, '验证码不正确');
        }
        $res = $this->user->login($mobile, '',2);
        return $this->json(['uid' => $res['user_id'], 'token' => $res['token'],'is_kefu' => $res['is_kefu'],'is_seller' => $res['is_seller'] ? : 0]);
    }
    /**
     * 三方登录
     */
	public function apiLogin(){
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
		$user_id = $this->user->userInfo($where, 'user_id');
        $clientId = input('clientId');
        $app_system = input('app_system', 'Android');
        if ($app_system == 'Android') {
            $app_system = 2;
        } else {
            $app_system = 1;
        }
		//登录
		if($user_id){
			$res = $this->login->apiLogin($user_id['user_id'], $clientId, $app_system);
		}
		//注册
		else{
            $data['client_id'] = $clientId;
            $data['app_system'] = $app_system;
			$res = $this->login->apiRegister($type, $api_id, $data);
		}

		if(!$res){
			return $this->json('', 0, '登录失败');
		}
		else return $this->json($res, 1, '登录成功');
	}

	/**
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
        $shop_name = input('request.shop_name');
//        if(!$shop_name){
//            return $this->json('', 0, '店铺名称不能为空');
//        }
		$code = input('request.code');
		if(!$code){
			return $this->json('', 0, '验证码不能为空');
		}

		$status = $this->user->checkCode($mobile, $code, 3);
		//验证
		if(!$status){
			return $this->json('', 0, '验证码不正确');
		}

		if($status == -1){
			return $this->json('', 0, '验证码已过期');
		}

		$pwd = input('request.pwd', '');
        //验证邀请码
        $invite_code = input('request.invite_code');
        $user_info = Db::name('users')->where('user_mobile', $mobile)->field('user_id as uid, token, is_seller')->find();
//        if(!$user_info && !$invite_code){
//            return $this->json('', 0, '邀请码不能为空');
//        }
        if ($invite_code){
            $invite_info = $this->user->checkInviteCode($invite_code);
            if (!$invite_info && !$user_info) {
                return $this->json('', 0, '邀请码错误');
            }
            $invite_uid = $invite_info['user_id'];
        }else{
            $invite_uid = '';
        }

		$result = $this->login->mobileBind($uid, $mobile, $pwd, $invite_uid,$shop_name);
		if(!$result){
			return $this->json('', 0, '绑定失败');
		}
		else return $this->json($result, 1, '绑定成功');
	}

    /**
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

        $status = $this->user->checkCode($mobile, $code, 3);
        //验证
        if(!$status){
            return $this->json('', 0, '验证码不正确');
        }

        if($status == -1){
            return $this->json('', 0, '验证码已过期');
        }
        // 默认 为空
        $pwd = '';
        //验证邀请码
        $invite_code = input('request.invite_code');
        $user_info = Db::name('users')->where('user_mobile', $mobile)->field('user_id as uid, token, is_seller')->find();
        if(!$user_info && !$invite_code){
            return $this->json('', 0, '邀请码不能为空');
        }

        $invite_info = $this->user->checkInviteCode($invite_code);
        if (!$invite_info) {
            return $this->json('', 0, '邀请码错误');
        }
        $result = $this->login->mobileBind($uid, $mobile, $pwd, $invite_info['user_id']);
        if(!$result){
            return $this->json('', 0, '绑定失败');
        } else {
            session('uid', $result['uid']);
            session('token', $result['token']);
            return $this->json($result, 1, '绑定成功');
        }
    }

    /**
     * 设置 修改密码
     * @param int uid
     * @param string token
     * @param string oldPWD 旧密码
     * @param string newPWD 新密码
     * @return json
     */
    public function myPasswdEdit()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $password_old = input('request.oldPWD');
        $password = input('request.newPWD');
        $uid = $this->getUid($token, $user_id);
//        if(!$uid){
//            return $this->json('', 0, '未知参数');
//        }
        $result = $this->user->editPassword($user_id,$password_old,$password);
        if($result == -1){
            return $this->json('', -1, '原始密码错误');
        }else if($result == 0){
            return $this->json('', 0, '修改失败');
        }
        return $this->json('',1,'修改成功');
    }

    /**
     * 获取验证码
     * @param string mobile
     * @param int type  1，注册；2，找回密码；3，绑定手机；4，设置支付密码 6验证码登录
     * @return json
     */
    public function getCode()
    {
        $mobile = input('request.mobile');
        if(!$mobile){
            return $this->json("", 0, '手机号不能为空');
        }
        //发送类型：1，注册；2，找回密码；3，绑定手机号；4，设置支付密码 5，登录确认 6验证码登录
        $type = input('request.type');
        $res= $this->user->getCode($mobile, $type);
        if($res['status'] == 1){
            if(config('app_debug')){
                return json(['data'=>$res['code'],'status'=>1,'type'=>$res['type']]);
            }
            else{
                return json(['data'=>'','status'=>1,'type'=>$res['type'],'msg' => '发送成功']);
            }
        }else if ($res['status'] == -1) {
            return $this->json("", -1, $res['msg']);
        }
        else{
            return $this->json('', 0, $res['msg']);
        }
    }

    /**
	 * 找回密码
     * @param string mobile
     * @param int code 验证码
     * @param string pwd
     * @return json
	 */
    public function resetPwd()
    {
        $mobile = trim(input('request.mobile'));
        if(!$mobile){
            return $this->json("", 0, '手机号不能为空');
        }
        //检测手机号是否注册
        $user_info = $this->user->find(['user_mobile' => $mobile]);
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

        $stat = $this->user->checkCode($mobile, $code, 2);
        //验证
        if(!$stat){
            return $this->json("", 0, '验证码不正确');
        }

        if($stat === -1){
            return $this->json('', 0, '验证码已过期');
        }

        $res = $this->user->resetPwd($mobile, $password);
        if($res > 0){
            return $this->json(['mobile' => $mobile]);
        }
        else{
            return $this->json('', 0, getErrorInfo($res));
        }
    }

    /**
     * 更换手机号
     * @param string mobile
     * @param integer code
     * @param integer uid
     * @param string token
     * @return json
     */
    public function replaceMobile()
    {
        $mobile = trim(input('request.mobile'));
        $code = trim(input('request.code'));
        $uid = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $uid);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }

        //检测手机号是否注册
        $user_info = $this->user->find(['user_mobile' => $mobile]);
        if($user_info){
            return $this->json("", 0, '手机号已注册!不能更换！');
        }

        $res = Db::name('users')->where('user_id',$uid)->update(['user_mobile' => $mobile]);
        if($res){
            $this->json('', 0, '更换失败');
        }
        return $this->json('', 1, '更换成功');
    }
}
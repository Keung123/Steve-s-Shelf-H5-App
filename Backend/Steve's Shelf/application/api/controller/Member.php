<?php
namespace app\api\controller;

use app\common\service\User as UserService;
use QRcode\QRcode;
class Member extends Common{
    protected $user;
	public function __construct()
    {
		parent::__construct();
		$this->user=new UserService();
		if(!input('token')){
			echo json_encode(['status'=>-1,'msg'=>'请登录']);
			exit();
		}
		$userinfo=$this->user->find(['token'=>input('token')]);
		if(!$userinfo){
			echo json_encode(['status'=>-1,'msg'=>'请登录']);
			exit();
		}
		session('id',$userinfo['id']);
		session('user_name',$userinfo['user_name']);
		$this->userinfo=$userinfo;
	}

	public function index()
    {
		//用户信息
		$data['userinfo']=$this->userinfo;
		//分享链接
		$data['share']='http://'.$_SERVER['SERVER_NAME'].url('wap/login/share',['mobile'=>$this->userinfo['mobile']],'html');
		return $this->json($data);
	}

	public function share()
    {
		$userinfo=$this->userinfo;
		$url='http://'.$_SERVER['SERVER_NAME'].url('wap/login/share',['mobile'=>$userinfo['mobile']],'html');
		$errorCorrectionLevel = "H"; // 纠错级别：L、M、Q、H  
		$matrixPointSize = "4"; // 点的大小：1到10  
		QRcode::png($url, false, $errorCorrectionLevel, $matrixPointSize);
		exit();
	}	

	public function recommend()
    {
		//获取推荐列表
		$data=$this->user->getRecommendList(session('id'));
		return $this->json($data);
	}

	/**
	* 提现
	*/
	public function withdrawals()
    {
		//检测积分是否足够
		$point=input('post.point');
		if($point<0||$point>$this->userinfo['point']){
			return $this->json("",0,"积分不足，无法提现");
		}
		$ali_id=input('post.ali_id');
		if(!$ali_id){
			return $this->json("",0,"请填写收款支付宝账号");
		}
		$data['point']=$point;
		$data['ali_id']=$ali_id;
		$res=$this->user->withdrawals(session('id'),$data);
		if($res>0){
			return $this->json($res,1,'提现申请已提交，请等待管理员审核');
		}else{
			return $this->json("",0,getErrorInfo($res));
		}
	}

	/**
	* 修改密码
	*/
	public function changePassword()
    {
		$password_old=input('post.password_old');
		$password=input('post.password');
		$res=$this->user->changePassword($password_old,$password);
		if($res>0){
			return $this->json("",1,"修改成功");
		}else{
			return $this->json("",0,getErrorInfo($res));
		}	
	}	
}
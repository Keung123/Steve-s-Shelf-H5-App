<?php
namespace app\api\controller;

use app\common\service\Coupon;
use app\common\service\User as UserService;
use app\common\model\Config as ConfigModel;
use getui\Pushs;
use think\Db;
class User extends Common{
    protected $user;
    public function __construct()
    {
        $this->user = new UserService();
    }

    public function alidata(){
        $json = '{"gmt_create":"2019-11-20 16:06:15","charset":"utf-8","seller_email":"fengsl_0706@163.com","subject":"\u5145\u503c \u8d26\u6237\u4f59\u989d","sign":"FTdeFi5Up598QDv\/euFF7vueRXKiiHA6VynCrxuY0z21k+0wv4tld36ll\/W2sVZZt7hiKJBYjD8gSOHn7pPsGfO3EapzWExfXIVpDid9M7nIugnM8\/mHG8faB1qCcGVzdrJW6INMlJRi071\/rL\/ASL+ATZu\/BxgTB\/lA3mUQCMu+CXVVjE0V9BpcB5JmMAaID6DGOXorXM083uEMuuv0BGGQnODTobsVxXYRDD\/GSG56WD+8oeD4igdVuqVBQDqSRK2xb9D+i90f9BsaRlVQLlGYzelBrVq7G1c7n8d\/2bgCSnGEWvBSHgPKPS6Nqne3QKZEM+eq5BaFdEsYM\/PDlg==","buyer_id":"2088602046674346","invoice_amount":"1.00","notify_id":"2019112000222160616074340500562009","fund_bill_list":"[{\"amount\":\"1.00\",\"fundChannel\":\"PCREDIT\"}]","notify_type":"trade_status_sync","trade_status":"TRADE_SUCCESS","receipt_amount":"1.00","app_id":"2019093067899878","buyer_pay_amount":"1.00","sign_type":"RSA2","seller_id":"2088631419370709","gmt_payment":"2019-11-20 16:06:16","notify_time":"2019-11-20 16:06:16","version":"1.0","out_trade_no":"RC2019112049514910","total_amount":"1.00","trade_no":"2019112022001474340567735764","auth_app_id":"2019093067899878","buyer_logon_id":"180****2338","point_amount":"0.00"}';


        $data = json_decode($json,true);
        print_r($data);exit;


    }

    public function usercard(){
        $uid = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $uid);

        $re_data = [];
        $where = [];
        $where['status']=1;

        $where['is_vip']= array('in','1,2');

        if($uid){
            $user_info = Db::name('users')->field('user_id,is_vip,vip_end_time')->where('user_id',$uid)->find();
            if(!$user_info){
                return $this->json('', 0, '用户不存在');
            }
            if($user_info['is_vip']==1){
                $where['is_vip']= array('in','1,2');
            }
            else {
                $where['is_vip']= array('in','0,2');
            }
        }




        $card = Db::name('card')->where($where)->select();

        $re_data['card'] = $card;
        $re_data['card_info'] = Db::name('content')->where('title','会员卡介绍')->find();
        return $this->json($re_data);
    }

    //判断当前用户是否是vip
    public function is_vip(){
        $uid = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $uid);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $user_info = Db::name('users')->field('user_id,is_vip,vip_end_time')->where('user_id',$uid)->find();
        if(!$user_info){
            return $this->json('', 0, '用户不存在');
        }

        if($user_info['is_vip']==1){
            if($user_info['vip_end_time']<time()){
                $user_info['is_vip']=0;
            }
        }
        return $this->json($user_info);
    }

    /**
     * 登录
     */
	public function mobileLogin()
    {
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
		$stat = $this->user->checkCode($mobile, $code, 1);
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
		$result = $this->user->clientIds($mobile, $clientId, $app_system);
		if(!$result){
			//推送
			$msg = [
				'content'=>'胡乱购欢迎您！',//透传内容
				'title'=>'登陆提示',//通知栏标题
				'text'=>'胡乱购欢迎您！',//通知栏内容
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
	/**
	 * 获取后台推送设置
	 */
	public function pushNOtice()
    {
		$config_model = new ConfigModel;
		$infos = $config_model->where(1)->field('app')->find();
		$notice = trim($infos['app']['notice']);
		if(!$notice){
			return $this->json('', 0, '获取失败');
		}
		return $this->json('', 1, $notice);
	}
	/**
	 * 解绑微信
	 */
	public function untiewchat(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		//1：qq;2：微信
		$type = input('request.type');
		$res = $this->user->untie($uid,$type);
		if(!$res){
			return $this->json('', 0, '解绑失败');
		}
		return $this->json('', 1, '解绑成功');
	}
	/**
	 * 个人主页
	 */
	public function userCenter(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_info = $this->user->userCenter($uid);
		if(!$user_info){
			return $this->json('', 0, '用户不存在');
		}
		return $this->json($this->user_info);
	}
	/**
	 * 个人主页2（个推专用）
	 */
	public function userCenters(){
		$uid = input('request.uid');
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$user_info = $this->user->userCenter($uid);
		if(!$user_info){
			return $this->json('', 0, '用户不存在');
		}

		return $this->json($this->user_info);
	}

	/**
	 * 我的账户
	 */
	public function userAccount(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$account_info = $this->user->userAccount($uid);
		return $this->json($account_info);
	}

	/**
	 * 账户明细
	 */
	public function accountLog(){
		$uid = input('request.uid');
		$token = input('request.token');
		$month = input('request.month');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$type = input('request.type');
		if(!$type){
			return $this->json('', 0, '未知参数');
		}
		$p = input('request.p');
		$account_info = $this->user->accountLog($uid, $type, $p,$month);

		return $this->json($account_info);
	}

	/**
	 * 我的优惠券
	 */
	public function userCoupon(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$coupon_info = $this->user->getCoupon($uid);
		if(!$coupon_info){
			return $this->json('', 0, '无优惠券');
		}
		return $this->json($coupon_info);
	}

	/**
	 * 我的优惠券
	 */
	public function orderCoupon(){
		$uid = input('request.uid');
		$token = input('request.token');
        $type = input('request.type');
        $goodsids = input('request.goodsids');
        $price = input('request.payprice');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$coupon_info = $this->user->getCouponOrder($uid,$type,$goodsids,$price);
		if(!$coupon_info){
			return $this->json('', 0, '无优惠券');
		}
		return $this->json($coupon_info);
	}
	/**
	 * 我的足迹
     * @param integer uid
     * @param string token
     * @param integer page
     * @return json
	 */
	public function userTrack()
    {
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$p = input('request.page');
		$track_info = $this->user->userTrack($uid, $p);
		if($track_info){
			return $this->json($track_info);
		}
		return $this->json('', 0, '暂无数据');
	}

	/**
	 * 足迹编辑
     * @param integer uid
     * @param string token
     * @param string tid 足迹id,多个id逗号分隔
     * @return json
	 */
	public function trackEdit()
    {
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		$tid = input('request.tid');
		if(!$uid || !$tid){
			return $this->json('', 0, '未知参数');
		}
		$res = $this->user->trackEdit($uid, $tid);
		if($res){
			return $this->json('', 1, '删除成功');
		}
		else return $this->json('', 0, '删除失败');
	}

	/**
	 * 我的收藏
     * @param integer uid
     * @param string token
     * @param integer page
     * @param integer type 1商品 2 素材
     * @return json
 	 */
	public function userFavor()
    {
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		$type = input('request.type');
		$p = input('request.page') ? input('request.page') : 1;
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$favorites = $this->user->userFavor($uid, $type, $p);
		return $this->json($favorites);	
	}

	/**
	 * 收藏删除
     * @param int uid
     * @param string token
     * @param int fid 收藏id
     * @return json
	 */
	public function favorDel()
    {
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		$favor_id = input('request.fid');
		if(!$uid || !$favor_id){
			return $this->json('', 0, '未知参数');
		}
		$res = $this->user->favorDel($uid, $favor_id);
		if($res){
			return $this->json('', 1, '删除成功');
		}
		else return $this->json('', 0, '删除失败');
	}

	/**
	 * 个人资料页
	 */
	public function myInfo(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$info = $this->user->myInfo($uid);
		return $this->json($info);
	}

	/**
	 * 个人资料修改
	 */
	public function myInfoEdit(){
		$uid = input('request.uid');
		$token = input('request.token');
//		$uid = $this->getUid($token, $uid);
//		if(!$uid){
//			return $this->json('', 0, '未知参数');
//		}
		$data = [
			'user_name' => input('request.user_name'),
			'user_avat' => input('request.user_avat'),
			'user_sex' => input('request.user_sex'),
			'user_birth' => input('request.user_birth'),
			'user_hobby' => input('request.user_hobby'),
			'user_addr' => input('request.user_addr'),
			'user_sign' => input('request.user_sign'),
			'shop_name' => input('request.shop_name'),
		];
		$result = $this->user->myInfoEdit($uid, $data);

		if($result == -1){
			return $this->json('', 0, '昵称已被占用');
		}
		else if(!$result){
			return $this->json('', 0, '保存失败');
		}
		else return $this->json('', 1, '保存成功');
	}

	/**
	 * 实名认证人提交页面
	 */
	 public function myInfoAuth(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
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
		$result = $this->user->Auth($uid,$authInsert);
 
		if($result == -1){
			return $this->json('', -1, '已经实名认证');
		}else if($result == 0){
			return $this->json('', 0, '实名认证资料提交失败');
		}
	    return $this->json('', 1, '实名认证资料提交成功');
	}
	/**
	 * 实名认证展示页面
	 */
	 public function myInfoAuthshow(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$result = $this->user->showAuth($uid);
		if($result == 0){
			return $this->json('', 0, '获取失败');
		}

	    return $this->json($result);
	}
	/**
	 * 优惠券获取成功
	 */
	public function CouponInfo()
    {
        $c_id = input('c_id');
		if(!$c_id){
            return $this->json('', 0, '未知参数');
        }
        $couponmodel = new Coupon();
		$couponInfo = $couponmodel->getCouponInfo($c_id);
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
	/**
     * 获取上级id
     */
    public function getSuperior(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$sup_id  = $this->user->getSuperior($uid);
		if(!$sup_id){
			return $this->json('', 0, '获取失败');
		}
		else return $this->json($sup_id);
	}

	/**
	 * 新用户
	 */
	public function isNew(){
		$uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $uid);
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
}

 
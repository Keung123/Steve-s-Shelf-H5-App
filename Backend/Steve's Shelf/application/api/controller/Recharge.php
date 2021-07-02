<?php
namespace app\api\controller;

use app\common\service\Recharge as RechargeService;
use app\common\service\Config;
use app\common\service\User as UserService;
use think\Db;

class Recharge extends Common{
    protected $config;
    protected $recharge;
    protected $user;
    public function __construct()
    {
        $this->config = new Config();
        $this->recharge = new RechargeService();
        $this->user = new UserService();
    }

    /**
     * 我的充值卡
     * @param integer uid
     * @param string json
     * @return json
     */
    public function myInfoCardShow()
    {
        $uid = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $uid);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $result = $this->user->showCard($uid);
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        foreach ($result as &$val) {
            $val['card_add_time'] = date('Y-m-d H:i', $val['card_add_time']);
            $val['card_end_time'] = date('Y-m-d H:i', $val['card_end_time']);
        }
        return $this->json($result);
    }

    /**
     * 我的充值卡(不确定)
     */
    public function myRcharge()
    {
        $uid = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $uid);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $type = input('request.type', 1);
        $p = input('request.page', 1);
        $list = $this->user->myRecharge($uid, $p, $type);
        if(!$list['code']){
            return $this->json('', 0, '获取失败');
        }
        else return $this->json($list['data']);
    }

    /**
	 * 在线充值列表
     * @param integer id
     * @param double amount 充值金额
     * @param integer points 赠送积分
     * @return json
	 */
	public function reOnline()
    {
		$list['list'] = $this->recharge->rcOnline();
    	$config=$this->config->find();
	 	$return_integral = json_decode($config['return_integral'],true);
		if($return_integral['status'] == 1){
			$list['return_integral'] = 0;
			return $this->json($list);
		}

		$list['return_integral'] = $return_integral['num'];
		return $this->json($list);
	}

	/**
	 * 充值卡列表
	 */
	public function rcList(){
		$list = $this->recharge->rcList();
		return $this->json($list);
	}

	/**
	 * 充值
     * @param integer uid
     * @param string token
     * @param string payCode 充值方式：alipay，支付宝支付；wxpay，微信支付；unionpay，银联代付；otherpay，他人代付
     * @param integer type 1，在线充值；2，购买充值卡
     * @param double amount 充值金额
     * @param integer points 赠送积分
     * @param integer id 充值卡id
     * @return json
	 */
   	public function rcLine(){
		$rech_uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $rech_uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$rc_type = input('request.type');
		if(!$rc_type){
			return $this->json('', 0, '未知参数');
		}
		$pay_code = input('request.payCode');//充值方式 wxpay alipay
		$rc_info = [];
		//在线充值
		if($rc_type == 1){			
			 $rc_info['amount'] = input('request.amount');
			$rc_info['points'] = input('request.points');
		}
		//购买充值卡
		else if($rc_type == 2){
			$rc_info['rc_id'] = input('request.id');
		}
		else if($rc_type==3){
            $rc_info['card_id'] = input('request.card_id');
        }
		
		$info = $this->recharge->onLine($uid, $rc_type, $rc_info, $pay_code);
		if(!$info['code']){
			return $this->json('', 0, $info['msg']);
		}
		return $this->json($info['data'], 1, '充值成功');
	}

    /**
     * 充值记录
     */
    public function rcLog()
    {
        $this->user_id = input('request.uid');
        $this->user_rc_id = input('request.card_id');
        $token = input('request.token');
        $uid = $this->getUid($token, $this->user_id);
        $where = [
            'uid' => $this->user_id,
            'user_rc_id' => $this->user_rc_id
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



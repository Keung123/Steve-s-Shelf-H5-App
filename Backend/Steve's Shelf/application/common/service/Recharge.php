<?php
namespace app\common\service;

use app\common\model\Recharge as RechargeModel;
use app\common\model\Users as UserModel;
use app\common\service\PointLog as PointLogService;
use app\common\service\ApiPay as Apipay;
use think\Db;
use think\Request;

class Recharge extends Base{
	public function __construct(){
		parent::__construct();
		$RechargeModel = new RechargeModel();
		$this->model = $RechargeModel;
	}

	/*
	 * 在线充值
	 */
	public function rcOnline(){
		$list = Db::name('rc_online')->field('ro_id,ro_price,ro_points')->order('ro_add_time desc')->select();
		return $list;

	}

	/*
	 * 充值卡列表
	 */
	public function rcList(){
		$list = Db::name('rc_template')->where('rc_status',0)->field('rc_id,rc_title,rc_price,rc_buy_price')->order('rc_add_time desc')->select();
		return $list;
	}

	/*
	 *  充值
 	 */
	public function onLine($uid, $rc_type, $rc_info, $pay_code){
		$apipay = new Apipay();
		$user_info = Db::name('users')->where('user_id', $uid)->find();
		$no = $this->cardLogNo();
		//在线充值
		if($rc_type == 1){
			$insert = [
				'rech_no' => $no,
				'rech_uid' => $uid,
				'rech_uname' => $user_info['user_name'],
				'rech_amount' => $rc_info['amount'],
				'rech_way' => $pay_code,
				'rech_stat' => 1,
				'rech_create_time' => time(),
				'rech_type' => $rc_type,
			];
			if(!empty($rc_info['points'])){
                $insert['rech_points'] = $rc_info['points'];
//                Db::name('users')->where('user_id',$uid)->setInc('user_points',$rc_info['points']);
            }
            $this->model->insert($insert);
			$amount = $rc_info['amount'];
			$desc = '充值 账户余额';
		}
		//购买充值卡
		else if($rc_type == 2){
			$rc_card_info = Db::name('rc_template')->where('rc_id', $rc_info['rc_id'])->field('rc_id,rc_title,rc_price,rc_buy_price')->find();
			// $rc_card_info['rc_price'] = 0.01;
			// $rc_card_info['rc_buy_price'] = 0.01;
			$insert = [
				'rech_no' => $no,
				'rech_uid' => $uid,
				'rech_uname' => $user_info['user_name'],
				'rech_amount' => $rc_card_info['rc_buy_price'],
				'rech_way' => $pay_code,
				'rech_stat' => 1,
				'rech_create_time' => time(),
				'rech_type' => $rc_type,
				'rc_t_id' => $rc_info['rc_id'],
			];
			$this->model->insert($insert);

			$amount = $rc_card_info['rc_buy_price'];
			if(!$rc_card_info['rc_title']){
				$rc_card_info['rc_title'] = '充值卡';
			}
			$desc = '购买 '.$rc_card_info['rc_title'];
		}
        //充值会员时长
        else if($rc_type == 3){
            $rc_card_info = Db::name('card')->where('id', $rc_info['card_id'])->find();
            // $rc_card_info['rc_price'] = 0.01;
//             $rc_card_info['price'] = 0.01;
            $insert = [
                'rech_no' => $no,
                'rech_uid' => $uid,
                'rech_uname' => $user_info['user_name'],
                'rech_amount' => $rc_card_info['price'],
                'rech_way' => $pay_code,
                'rech_stat' => 1,
                'rech_create_time' => time(),
                'rech_type' => $rc_type,
                'card_id' => $rc_info['card_id'],
            ];
            $this->model->insert($insert);

            $amount = $rc_card_info['price'];
            $desc = '购买 '.$rc_card_info['name'].'会员';
        }

		switch($pay_code){
    		//支付宝支付
    		case 'alipay' :
    			$data = $apipay->Alipay($no, $amount, $desc);
    		break;
    		//微信支付
    		case 'wxpay' :
    			$amount *= 100;        			
    			$data = $apipay->WxPay($no, $amount, $desc);
    		break;
    		//银联支付
    		case 'unionpay' :
    			$amount *= 100;
    			$data = $apipay->UnionPay($no, $amount);
    		break;
            case 'account' :
                if($user_info['user_account']>$amount){
                    //扣除余额
                    Db::name('users')->where('user_id',$user_info['user_id'])->setDec('user_account',$amount);
                    //走支付回调
                    $apipay = new Apipay();
                    $apipay->rcOrderHandle($no, 'account');
                    $data['code'] = 1;
                    $data['msg'] = '支付成功';
                }
                else {
                    $data['code'] = 0;
                    $data['msg'] = '余额不足';
                }
                break;
    	}
		
		if(!$data['code']){
			return ['code' => 0, 'msg' => $data['msg']];
		}
		else return ['code' => 1, 'data' => $data['data']];
	}

	/*
	 *  充值记录编号
 	 */
	public function cardLogNo(){
		 // $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l','m', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y','z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L','M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y','Z', '0');
		$no = 'RC'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
		$check = $this->model->where('rech_no', $no)->field('rech_id')->find();
		while($check){
			$no = $this->cardLogNo();
		}
		return $no;
	}

	/*
	 *  充值卡编号
 	 */
	public function cardNo(){
		$no = 'CD'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
		$check = Db::name('user_rc')->where('card_no', $no)->field('card_id')->find();
		while($check){
			$no = $this->cardNo();
		}
		return $no;
	}
}
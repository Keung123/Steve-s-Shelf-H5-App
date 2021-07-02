<?php

namespace app\api\controller;

use app\common\service\ApiPay as ApiPay;
use app\common\service\Coupon;
use app\common\service\User as UserService;
use think\Db;
use think\Request;

class Discount extends Common{
    protected $couponModel;
    protected $user;
    public function __construct()
    {
        $this->couponModel = new Coupon();
        $this->user = new UserService();
    }

    /**
     * 券列表
     * @param int type  1商品卷2专区卷3全程卷
     * @param string token
     * @param int uid
     * @return json
     */
    public function getCouponlist(Request $req)
    {
        $type = strtolower($req->param('type'));
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $user_id = $this->getUid($token, $user_id);
        if (in_array($type,array(1,2,3))) {
            $where['coupon_type'] = $type;
        }
        $where['coupon_aval_time'] = ['gt', time()];
        $list = $this->couponModel->getList($where);
        if ($list) {
            foreach ($list as &$val) {
                $val['coupon_aval_time'] = date('Y.m.d', $val['coupon_aval_time']);
                if ($user_id) {
                    $val['is_yongyou'] = $this->couponModel->is_yongyou($val['coupon_id'], $user_id);
                } else {
                    $val['is_yongyou'] = 0;
                }
            }
        }
        return $this->json($list);
    }

    /**
     * 领券
     * @param int couponId
     * @param int string token
     * @param int uid
     * @return Json
     */
    public function getCoupon()
    {
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $coupon_id = trim(input('couponId'));
        $user_id = $this->getUid($token, $user_id);
        if (!$user_id) {
            return $this->json([], 0, '未知参数');
        }
        $res = $this->couponModel->getCoupon($user_id, $coupon_id);
        if ($res == 1) {
            return $this->json([], 1, '领取成功');
        } elseif($res == -1) {
            return $this->json([], 0, '没有优惠券信息');
        } elseif($res == -2) {
            return $this->json([], 0, '余额不足');
        }
        else if($res == -5){
            return $this->json('', 0, '已达到每人限领张数');
        }
        else {
            return $this->json([], 0, '领取失败');
        }
    }

    /**
     * 领取转增优惠券
     * @param int uid
     * @param string token
     * @param int couponId 优惠券ID
     * @param int fromUid 发送优惠券人ID
     * @return Json
     */
    public function receiveCoupon()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $c_id = input('request.couponId');
        $to_uid = input('request.fromUid');
        // 检测优惠券 是否已领
        $res =$this->couponModel->is_yongyou($c_id, $to_uid);
        if (!$res) {
            return $this->json('', 0, '该优惠券已被其他用户领取');
        }
        if($to_uid == $user_id ){
            return $this->json('', 0, '不能领取自己分享的优惠券！');
        }
        $res = $this->couponModel->saveCouponStatus($c_id);
        if (!$res) {
            return $this->json('', 0, '转增失败');
        }
        $info = $this->couponModel->getCouponInfo($c_id);
        if (!empty($info)) {
            unset($info['c_id']);
            $info['c_uid'] = $uid;
            $info['coupon_stat'] = 1;
            $res = $this->couponModel->addCoupon($info);
            if (!empty($res)) {
                $c_id = $this->couponModel->getCouponID();
                return $this->json($c_id, 1, '领取成功');
            }
        }
        return $this->json('', 0, '转增失败');
    }

    /**
     * 转增优惠券详情
     * @param int uid
     * @param int couponId
     * @return Json
     */
    public function giveCouponInfo()
    {
        $user_id = input('request.uid');
        $c_id = input('request.couponId');
        $info = $this->couponModel->getCouponInfo($c_id);
        if ($info['c_uid'] == $user_id) {
            $info['coupon_aval_time'] = date('Y.m.d', $info['add_time']+$info['coupon_aval_time']*3600*24);
            $info['add_time'] = date('Y.m.d', $info['add_time']);
            $info['coupon_thumb'] = $this->couponModel->getCouponInfos($info['coupon_id']);
            return $this->json($info, 1);
        } else {
            return $this->json([], 0, "获取优惠券失败");
        }

    }

    /**
     * 优惠券购买接口
     * @param int uid
     * @param string token
     * @param int couponId 优惠券模板id
     * @param string payCode 支付方式：alipay、wxpay、unionpay、balance
     * @return json
     */
    public function buyCoupon(){
        $uid = $this->getUid(input('request.token'), input('request.uid'));
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }

        $coupon_id = input('request.couponId');
        //优惠券模板信息
        $coupon_t_info = Db::name('coupon')->where('coupon_id', $coupon_id)->field('coupon_title,coupon_thumb,coupon_price,coupon_buy_price,coupon_use_limit,coupon_get_limit,coupon_type,coupon_type_id,coupon_total,coupon_s_time,coupon_aval_time,coupon_stat')->find();
        if(!$coupon_t_info || $coupon_t_info['coupon_stat']){
            return $this->json('', 0, '优惠券不存在');
        }
        if($coupon_t_info['coupon_total'] == 0){
            return $this->json('', 0, '优惠券剩余不足');
        }
        $my_coupon = Db::name('coupon_users')->where(['coupon_id' => $coupon_id, 'c_uid' => $uid, 'coupon_stat' => ['neq', 5]])->count();
        if($coupon_t_info['coupon_get_limit'] && $my_coupon >= $coupon_t_info['coupon_get_limit']){
            return $this->json('', 0, '此优惠券每人限领'.$coupon_t_info['coupon_get_limit'].'张');
        }
        $c_no = $this->createCouponNo();
        try{
            $coupon_user_data = [
                'coupon_id' => $coupon_id,
                'c_uid' => $uid,
                'coupon_stat' => 5,
                'c_coupon_title' => $coupon_t_info['coupon_title'],
                'c_coupon_type' => $coupon_t_info['coupon_type'],
                'c_coupon_price' => $coupon_t_info['coupon_price'],
                'c_coupon_buy_price' => $coupon_t_info['coupon_use_limit'],
                'coupon_type_id' => $coupon_t_info['coupon_type_id'],
                'coupon_aval_time' => $coupon_t_info['coupon_aval_time'],
                'c_no' => $c_no,
            ];
            Db::name('coupon_users')->insert($coupon_user_data);
            Db::name('coupon')->where('coupon_id', $coupon_id)->setDec('coupon_total', 1);
            Db::commit();
        }
        catch(\Exception $e){
            Db::rollback();
            return $this->json('', 0, '购买失败');
        }
        $apipay = new ApiPay();
        $pay_code = input('request.payCode');
        if($pay_code == 'balance'){
            $user_info = Db::name('users')->where('user_id', $uid)->field('user_account')->find();
            if($user_info['user_account'] < $coupon_t_info['coupon_buy_price']){
                return $this->json('', 0, '账户余额不足');
            }
            $result = $this->user->changeAccount($uid, 6, -$coupon_t_info['coupon_buy_price']);
            if($result){
                $coupon_info = Db::name('coupon_users')->where(['c_no' => $c_no, 'coupon_stat' => 5])->field('c_id')->find();
                if($coupon_info){
                    Db::name('coupon_users')->where('c_id', $coupon_info['c_id'])->update(['add_time' => time(), 'coupon_stat' => 1]);
                }
                return $this->json('', 1, '余额支付成功');
            }
            else{
                return $this->json('', 0, '余额支付失败');
            }
        }
        else{
            switch($pay_code){
                //支付宝支付
                case 'alipay' :
                    $data = $apipay->Alipay($c_no, $coupon_t_info['coupon_buy_price'], '购买 '.$coupon_t_info['coupon_title']);
                    break;
                //微信支付
                case 'wxpay' :
                    $coupon_t_info['coupon_buy_price'] *= 100;
                    $data = $apipay->WxPay($c_no, $coupon_t_info['coupon_buy_price'], '合陶家-'.$coupon_t_info['coupon_title']);
                    break;
                //银联支付
                case 'unionpay' :
                    $coupon_t_info['coupon_buy_price'] *= 100;
                    $data = $apipay->UnionPay($c_no, $coupon_t_info['coupon_buy_price']);
                    break;
            }

            if(!$data['code']){
                return $this->json('', 0, $data['msg']);
            }
            return $this->json($data['data']);
        }
    }

    /**
     * 商品优惠券
     * @param int p 分页
     * @param int coupon_id 优惠券id
     * @return json
     */
    public function goodsCoupon(){
        $p = input('request.p',1);
        $coupon_id = input('request.coupon_id');
        $coupon_goods = $this->user->goodsCoupon($coupon_id,$p);
        if(!$coupon_goods){
            return $this->json('', 0, '无优惠券商品');
        }
        return $this->json($coupon_goods);
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
}
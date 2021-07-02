<?php

namespace app\api\controller;

use app\common\service\Coupon as CouponService;
use think\Db;
use think\Request;

class Coupon extends Common{
    protected $couponService;
    protected $user_id;

    public function __construct()
    {
        parent::__construct();
        $this->couponService = new CouponService();

        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $this->user_id = $this->getUid($token, $user_id);
        if (!$user_id) {
            return $this->json([], 0, '未知参数');
        }
    }

    /**
     * 获取优惠券列表
     */
    public function getUserList(Request $req)
    {
        $type = strtolower($req->param('type'));
        $where = [];
        $where['c_uid'] = $this->user_id;

        if ($type && in_array($type, array(1, 2, 3))) {
            $where['c_coupon_type'] = $type;
        }

        $list = $this->couponService->couponUsersModel->getList($where);

        if ($list) {
            foreach ($list as &$val) {
                $val['coupon_aval_time'] = $val['coupon_aval_time'] ? date('Y-m-d', $val['coupon_aval_time']) : '';
                $val['add_time'] = $val['add_time'] ? date('Y-m-d', $val['add_time']) : '';
                $val['update_time'] = $val['update_time'] ? date('Y-m-d', $val['update_time']) : '';
                if($val['coupon_aval_time'] && $val['coupon_aval_time'] < time()){
                    $val['coupon_stat'] = 3;
                }
            }
        }

        if($list){
            return $this->json($list, 1, '获取成功');
        }else{
            return $this->json([], 0, '获取失败');
        }
    }

    /**
     * 领取优惠券
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
        $res = $this->couponService->getCoupon($user_id, $coupon_id);
        if ($res == 1) {
            return $this->json([], 1, '领取成功');
        } elseif($res == -1) {
            return $this->json([], -1, '没有优惠券信息');
        }
        else if($res == -2){
            return $this->json('', -2, '已达到每人限领张数');
        }
        else {
            return $this->json([], 0, '领取失败');
        }
    }

    /**
     * 操作优惠券 (使用/退回)
     */
    public function operateCoupon()
    {
        $c_id = trim(input('c_id'));
        $status = trim(input('status'));

        $res = $this->couponService->couponUsersModel->changeStatus($c_id, $this->user_id, $status);
        if($res){
            return $this->json([], 1, '操作成功');
        }else{
            return $this->json([], 0, '操作失败');
        }
    }


    /**
     * 获取商品可用优惠券
     */
    public function couponInfo()
    {
        $c_coupon_type = trim(input('coupon_type'));
        $coupon_type_id = trim(input('coupon_type_id'));

        $uid = $this->user_id;
        $start = trim(input('start')) ? trim(input('start')) : 0;
        $limit = trim(input('limit')) ? trim(input('limit')) : 0;
        $order = trim(input('order')) ? trim(input('order')) : 'coupon_id desc';
        $status = 1;

        $res = $this->couponService->couponUsersModel->goodsCoupon($c_coupon_type, $coupon_type_id, $uid, $start, $limit, $status, $order);

        if($res){
            return $this->json($res, 1, '获取成功');
        }else{
            return $this->json([], 0, '获取失败');
        }

    }

    /**
     * 获取首页可用优惠券
     */
    public function couponIndex()
    {
        
        $start = trim(input('start')) ? trim(input('start')) : 0;
        $limit = trim(input('limit')) ? trim(input('limit')) : 2;
        $order = trim(input('order')) ? trim(input('order')) : 'coupon_id desc';
        

        $list = Db::name('coupon')->where('status',0)->where('type',1)->where("coupon_s_time",'<',time())->order($order)->limit($start, $limit)->select();

        foreach ($list as $key => &$value) {
            $value['coupon_aval_time'] = date("Y-m-d",$value['coupon_aval_time']);
        }
        
        if($list){
            return $this->json($list, 1, '获取成功');
        }else{
            return $this->json([], 0, '获取失败');
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
}
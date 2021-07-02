<?php

namespace app\api\controller;

use app\common\service\Goods as GoodsService;

class Giftbag extends Common{
    protected $goods;
    public function __construct()
    {
        $this->goods = new GoodsService();
    }
    /**
     * 获取大礼包 商品
     * request method  GET
     * @return Json
     */
    public function getLibao()
    {
        $list = $this->goods->getLibao();
        return $this->json($list);
    }
     /**
      * 获取大礼包 商品详情
      * request method GET
      * @param int goods_id
      * @return Json
      */
    public function getLibaoInfo()
    {
        $goods_id = input('goods_id/d');
        if (empty($goods_id)) {
            return $this->json([], 0, '参数错误');
        }
        $list = $this->goods->getLibaoInfo($goods_id);
        return $this->json($list);
    }

    /**
     * 赠送大礼包支付
     * request method GET
     * @param int uid
     * @param int orderid
     * @param int mobile
     * @param int givid
     * @param string token
     * @return Json
     */
    public function giftPay()
    {
        $user_id = input('request.uid');
        $uid = $this->getUid($token, $user_id);
        $order_id = input('request.orderid');
        if(!$uid || !$order_id){
            return $this->json('', 1, '未知参数');
        }
        $pay_code = input('request.paycode');
        $result = $this->order->orderPay($uid, $order_id, $pay_code);
        if(!$result){
            return $this->json('', 0, '订单支付失败');
        }
        else if($result == -1){
            return $this->json('', -1, '可用余额不足');
        }
        else if($result == -2){
            return $this->json('', -1, '对方已经是店主');
        }
        else{
            return $this->json($result);
        }
    }
}
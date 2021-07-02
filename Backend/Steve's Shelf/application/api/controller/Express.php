<?php

namespace app\api\controller;

use app\common\service\Config;
use app\common\service\Order;
use think\Db;

class Express extends Common {
    protected $config;
    protected $order;
    public function __construct()
    {
        $this->config = new Config();
        $this->order = new Order();
    }

    /**
     * 物流公司列表
     * @param string field 公司识别
     * @param string value 公司名称
     * @return json
     */
    public function expressList()
    {
        $config=$this->config->find();
        $express = json_decode($config['express'], true);
        $express =$express['express'];
        if($express){
            return $this->json($express, 1,'获取成功');
        }else{
            return $this->json($express, 0,'暂无数据');
        }

    }

    /**
     * 客服退货物流 提交
     * @param int uid
     * @param string token
     * @param int orderId
     * @param client_post_no
     * @param client_post_type
     * @return json
     */
    public function orderClient()
    {
        $uid = input('request.uid');
        $og_id = input('request.orderId');
        $client_post_no = input('request.client_post_no');
        $client_post_type = input('request.client_post_type');
        if(!$uid || !$og_id||!$client_post_no||!$client_post_type){
            return $this->json('', 0, '未知参数');
        }
        $res = $this->order->orderClient($uid,$og_id,$client_post_no,$client_post_type);
        if($res!=false){
            return $this->json($res, 1,'提交成功');
        }else{
            return $this->json('', 0,'提交失败');
        }
    }

    /**
     * 客服退货物流 详情列表
     * @param int uid
     * @param string token
     * @param int og_id 订单商品id
     * @return json
     */
    public function expressInfo()
    {
        $uid = input('request.uid');
        $og_id = input('request.orderId');
        if(!$uid || !$og_id){
            return $this->json('', 0, '未知参数');
        }
        $res = $this->order->postClient($uid,$og_id);
        if($res!=false){
            return $this->json($res, 1,'获取成功');
        }else{
            return $this->json('', 0,'获取失败');
        }

    }

    /**
     * 用户提交寄回货物物流单号
     * @param int uid
     * @param string token
     * @param int orderId 订单号
     * @param int express_no 单号
     * @param string express_type 物流
     * @return json
     */
    public function subUserPost()
    {
        $og_id = input('request.orderId');
        $client_post_no = input('request.express_no');
        $client_post_type = input('request.express_type');
        if(empty($og_id) || empty($og_id) || empty($og_id)){
            return $this->json('', 0, '暂无数据');
        }
        $res = Db::name('sh_info')->where(['og_id'=>$og_id])->update(['client_post_no'=>$client_post_no,'client_post_type'=>$client_post_type]);
        if($res){
            return $this->json('', 1, '提交成功');
        }else{
            return $this->json('', 0, '暂无数据');
        }
    }
}
<?php

namespace app\api\controller;

use app\common\service\GoodsCategory as Cate;
use app\common\service\Goods as GoodsService;

class Classify extends Common{
    protected $cate;
    protected $goods;
    public function __construct()
    {
        $this->cate = new Cate();
        $this->goods = new GoodsService();
    }
    /**
     * 全部分类
     */
    public function allCate()
    {
        $list = $this->cate->allCate();
        return $this->json($list);
    }

    /**
     * 分类商品
     * @param int uid 未登录传空
     * @param string token
     * @param int cateid 分页id
     * @param int page 页数
     * @param int price 价格排序, 升序1,降序0 , 未选择不传
     * @param string brandid 品牌筛选,多个以逗号拼接
     * @param int sv 销量排序,选中传1,未选中不传
     * @param int news 最新排序,选中传1,未选中不传
     * @return json
     */
    public function cateGoods(){
        $user_id = input('request.uid');
        if($user_id){
            $uid = $this->getUid(input('request.token'), $user_id);
            if(!$uid){
                return $this->json('', 0, '未知参数');
            }
        } else {
            $uid = 0;
        }
        $order['order_sv'] = input('request.sv');
        $order['order_new'] = input('request.newest');
        $order['order_price'] = input('request.price');
        $where['goods_brand'] = input('request.brandid');
        $p = input('request.page');
        $p = $p ? $p : 1;
        $cate_id = input('request.cateid');   //全部分类商品传入一级id
        $list = $this->goods->getListByCate($cate_id, $uid, $p, $where, $order);
        return $this->json($list);
    }
}
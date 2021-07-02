<?php

namespace app\api\controller;

use app\common\service\Goods as GoodsService;

class Brand extends Common {
    protected $goods;
    public function __construct()
    {
        $this->goods = new GoodsService();
    }

    /**
     * 品牌推荐
     * @param int page 分页
     * @return json
     */
    public function brandPush()
    {
        $page = input('request.page');
        $list = $this->goods->brandTui($limit = 10,$page);
        return $this->json($list);
    }

    /**
     * 分类商品筛选
     * @param int cateId 分类ID
     * @return json
     */
    public function brandSelect()
    {
        $cate_id = input('request.cateid');
        $list = $this->goods->brandSelect($cate_id);
        return $this->json($list);
    }

    /**
     * 品牌详情
     * @param int brandId 品牌ID
     * @param int uid 店主ID
     * @param string token
     * @return json
     */
    public function brandDetails()
    {
        $brandid = input('request.id');
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token,$user_id);
        $list = $this->goods->brandGoods($brandid);
        if(!$list){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($list['list']  as &$value) {
                $res = $this->goods->getstore($uid, $value['goods_id']);
                $active_price = $this->goods->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
                $commission = $this->getCom();
                //开启 返利
                if($commission['shop_ctrl'] == 1){
                    $f_p_rate = $commission['f_s_rate'];
                }else{
                    $f_p_rate = 100;
                }
                $value['dianzhu_price'] = floor($active_price * $value['commission']/ 100 * $f_p_rate)/100;
                $value['price'] = floatval($value['price']);
                $value['dianzhu_price'] = floatval($value['dianzhu_price']);
                $value['price'] = sprintf('%0.2f', $value['price']);
                $value['price'] = floatval($value['price']);
                $value['show_price'] = sprintf('%0.2f', $value['show_price']);
                $value['show_price'] = floatval($value['show_price']);
                $value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
                $value['vip_price'] = floatval($value['vip_price']);
                $value['is_put'] = $res;
                $value['price'] = floatval($value['price']);
            }
        }
        return $this->json($list);
    }
}
<?php


namespace app\common\model;

use think\Db;

class Usedlist extends Base
{
    /**
     *  获取用户购买,关注,收藏数最大的商品
     * @param int $uid 用户
     */
    public function maxGoodsDetails($uid)
    {
        $data = array();
        //获取用户黑名单列表
        $blacklist = Db::name('goods_blacklist')->field('goods_id')->where('uid = '.$uid)->select();
        $blacklist = array_column($blacklist,'goods_id');
        $blacklist = implode(',',$blacklist);
        if ($blacklist) {
            $where1 = "where og_goods_id not in (".$blacklist.") and og_order_status = 4 and og_uid = ".$uid;
            $where2 = "where f_goods_id not in (".$blacklist.") and favor_type = 1 and f_uid = ".$uid;
            $where3['a.is_recom'] = array('eq',1);
            $where3['a.is_show'] = array('eq',0);
            $where3['c.goods_id'] = array('not in',$blacklist);
        } else {
            $where1 = "where og_order_status = 4 and og_uid = ".$uid;
            $where2 = "where favor_type = 1 and f_uid = ".$uid;
            $where3['a.is_recom'] = array('eq',1);
            $where3['a.is_show'] = array('eq',0);
        }
        //获取用户购买次数最多的商品
        $goods_id_one = Db::query("select id,max(num) from (select og_goods_id as id ,count(og_id) as num from ht_order_goods ".$where1." group by og_goods_id) as result");
        if (!empty($goods_id_one[0]['id'])) {
            $res_one = $this->getGoodsDetails($goods_id_one[0]['id']);
        }
        //获取用户收藏数最大的商品详情
        $goods_id_two = Db::query("select id,max(num) from (select f_goods_id as id ,count(favor_id) as num from ht_favorite ".$where2." group by f_goods_id) as result");
        if (!empty($goods_id_two[0]['id'])) {
            $res_two = $this->getGoodsDetails($goods_id_two[0]['id']);
        }
        //获取推荐商品详情
        $res_three = Db::name('brand a')
            ->field('c.goods_id,c.goods_name,c.picture,c.price,c.show_price')
            ->join('goods_category b','a.cat_id = b.category_id')
            ->join('goods c','b.category_id = c.category_id')
            ->where($where3)
            ->find();
        if ($res_one) {
            $data[] = $res_one;
        }
        if ($res_two) {
            $data[] = $res_two;
        }
        if ($res_three) {
            $data[] = $res_three;
        }
        return $data;
    }

    /**
     * 获取商品详情
     */
    public function getGoodsDetails($id)
    {
        $goods_details = Db::name('goods')
            ->field('goods_id,goods_name,picture,price,show_price')
            ->where('goods_id = '.$id)
            ->where('status = 0')
            ->find();

        return $goods_details;
    }

    /**
     * 添加黑名单
     */
    public function add($uid,$ids)
    {
        $ids = explode(',',$ids);
        foreach ($ids as $v){
            $data['uid'] = $uid;
            $data['goods_id'] = (int)$v;
            $data['createtime'] = time();
            $res = Db::name('goods_blacklist')->insert($data);
        }
        if (!$res) {
            Db::rollback();
            return false;
        }
        Db::commit();
        return true;
    }
}
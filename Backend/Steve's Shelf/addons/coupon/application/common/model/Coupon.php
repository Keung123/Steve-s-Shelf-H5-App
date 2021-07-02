<?php
namespace app\common\model;

class Coupon extends Base{
	public $coupon_type = [
        1 => '商品券',
        2 => '专区券',
        3 => '全场券',
    ];

    /**
     * 优惠券配置列表
     * @param $where
     * @param string $field
     * @param string $order
     * @param int $start
     * @param int $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getList($where,$field = '*', $order = 'coupon_id desc', $start = 0, $limit = 10){
        $list = $this->model->field($field)->where($where)->order($order)->limit($start, $limit)->select();
        return $list;
    }
}
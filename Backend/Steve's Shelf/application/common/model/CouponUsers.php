<?php
namespace app\common\model;

class CouponUsers extends Base{
    public $name = 'coupon_users';

    public function __construct($data = [])
    {
        parent::__construct($data);
    }

    public $coupon_type = [
        1 => '商品券',
        2 => '专区券',
        3 => '全场券',
    ];

    public $status = [
        1 => '未使用',
        2 => '已使用',
        3 => '已过期',
    ];

    /**
     * 用户优惠券列表
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
    public function getList($where,$field = '*', $order = 'coupon_id desc', $start = 0, $limit = 10)
    {
        $list = $this->field($field)->where($where)->order($order)->limit($start, $limit)->select();
        return $list;
    }

    /**
     * 更改优惠券状态
     * @param string $c_id
     * @param string $coupon_stat
     * @return boolean
     */
    public function changeStatus($c_id, $uid, $coupon_stat)
    {
        $data = [];
        $data['coupon_stat'] = $coupon_stat;

        // 使用
        if($coupon_stat == 2){
            $data['update_time'] = time();
        }

        // 退回
        if($coupon_stat == 1){
            $data['update_time'] = '';
        }

        return $this->where(['c_id' => $c_id, 'c_uid' => $uid])->update($data);
    }

    /**
     * 根据类型获取可用优惠券
     * @param $c_coupon_type
     * @param $coupon_type_id
     * @param $uid
     * @param int $start
     * @param int $limit
     * @param int $status
     * @param string $order
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function goodsCoupon($c_coupon_type, $coupon_type_id, $uid, $start = 0, $limit = 10, $status = 1, $order = 'coupon_id desc')
    {
        $where = ['c_uid' => $uid];

        if($c_coupon_type){
            $where['c_coupon_type'] = $c_coupon_type;
        }
        if($coupon_type_id){
            $where['coupon_type_id'] = $coupon_type_id;
        }
        if($status){
            $where['coupon_stat'] = $status;
        }

        $lists = $this->where($where)
            ->limit($start, $limit)
            ->order($order)
            ->select();

        foreach ($lists as &$val){
            $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
            $val['coupon_aval_time'] = $val['coupon_aval_time'] ? date('Y-m-d H:i:s', $val['coupon_aval_time']) : '';
            $val['add_time'] = $val['add_time'] ? date('Y-m-d H:i:s', $val['add_time']) : '';
            $val['update_time'] = $val['update_time'] ? date('Y-m-d H:i:s', $val['update_time']) : '';
            if($val['coupon_aval_time'] && $val['coupon_aval_time'] < time()){
                $val['coupon_stat'] = 3;
            }
        }

        return $lists;
    }
}
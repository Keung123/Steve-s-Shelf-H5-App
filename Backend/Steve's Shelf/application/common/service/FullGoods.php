<?php
namespace app\common\service;

use app\common\model\FullGoods as FullGoodsModel;
use think\Db;

class FullGoods extends Base{

	public function __construct(){
		parent::__construct();
		$FullGoodsModel=new FullGoodsModel();
		$this->model=$FullGoodsModel;
	}
    // 获取商品列表
    public function getList($where = array(), $field = '*', $order = 'id desc', $limin = 20){
        $list = $this->model->field($field)->where($where)->order($order)->limit($limin)->select();
        return $list;
    }
    // 获取商品信息
    public function getGoodsImg($goods_id) {
        $goods_info = Db::name('goods')->where(array('goods_id' => $goods_id))->find();
        return $goods_info;
    }
    // 获取团购活动信息
    public function getActiveInfo($active_id)
    {
        $active_info = Db::name('group_active')->where(['id' => $active_id])->find();
        return $active_info;
    }
    // 获取团购活动信息
    public function getActiveList()
    {
        $active_list = Db::name('group_active')->where(['status' => 0])->select();
        return $active_list;
    }
}
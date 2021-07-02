<?php
namespace app\common\service;

use app\common\model\FlashGoods as FlashModel;
use think\Db;

class FlashGoods extends Base{

	public function __construct(){
		parent::__construct();
		$FlashModelModel=new FlashModel();
		$this->model=$FlashModelModel;
	}
	// 获取商品列表
	public function getList($where = array(), $field = '*', $order = 'id desc', $limin = 20){
	    $list = $this->model->field($field)->where($where)->order($order)->limit($limin)->select();
	    return $list;
    }
	// 获取商品列表
	public function getLists($where = array(), $limit = 20, $field = '*', $order = 'a.id desc'){
		$list = Db::name('flash_active')->alias('a')->join('flash_goods b','a.id=b.flash_id')->where($where)->order($order)->limit($limit)->select();
	    // $list = $this->model->field($field)->where($where)->order($order)->limit($limit)->select();
	    return $list;
    }
    public function getLists1($where = array(), $limit = 20, $field = '*', $order = 'a.id desc'){
		$list = Db::name('flash_active')->
		alias('a')->
		join('flash_goods b','a.id=b.flash_id')->
		join('goods c','b.goods_id=c.goods_id')->where(['c.prom_type'=>5])->
		where($where)->
		order($order)->
		limit($limit)->
		select();
	    // $list = $this->model->field($field)->where($where)->order($order)->limit($limit)->select();
	    return $list;
    }
    // 获取商品信息
    public function getGoodsImg($goods_id) {
        $goods_info = Db::name('goods')->where(array('goods_id' => $goods_id))->find();
        return $goods_info;
    }
    // 获取秒杀活动信息
    public function getActiveInfo($active_id)
    {
        $active_info = Db::name('flash_active')->where(['id' => $active_id])->find();
        return $active_info;
    }
    // 获取秒杀活动信息
    public function getActiveList()
    {
        $active_list = Db::name('flash_active')->where(['status' => 0])->select();
        return $active_list;
    } 
	// 删除秒杀活商品判断 0:未结束 1：结束
    public function judgems($ids)
    {
       	$idarr = explode(',',$ids);
		if(count($idarr)>0){
			foreach($idarr as $val){
				$res = Db::name('flash_goods')->where(['id' =>$val,'is_end'=>1])->find();
				if($res){
					return 0;
				}
				return 1;
			}
		}
		return 0;
    }
}












<?php
namespace app\common\service;

use app\common\model\ActiveType as ActiveTypeModel;
use think\Db;

class ActiveType extends Base{

	public function __construct(){
		parent::__construct();
		$ActiveTypeModel=new ActiveTypeModel();
		$this->model=$ActiveTypeModel;
	}
	// 获取活动列表
	public function getList($where = array(), $field = '*', $order = 'id desc', $limin = 20){
	    $list = $this->model->field($field)->where($where)->order($order)->limit($limin)->select();
	    return $list;
    }
    // 获取商品图片
    public function getGoodsImg($goods_id) {
        $goods_info = Db::name('goods')->where(array('goods_id' => $goods_id))->find();
        return $goods_info['picture'];
    }
    // 获取活动类型
    public function getActive()
    {
        return Db::name('active')->where(array('status' => 0))->select();
    }
    // 获取活动名称
    public function getActive_title($id)
    {
        $active_info = Db::name('active')->where(array('id' => $id))->find();
        return $active_info['active_title'];
    } 
	// 获取活动类型名称
    public function getActive_name($id)
    {
        $active_info = Db::name('active_type')->where(array('id' => $id))->find();
        return $active_info['active_type_name'];
    }
	// 获取活动标签
    public function getActive_label($id)
    {
        $active_info = Db::name('active_type')->where(array('id' => $id))->find();
        return $active_info['label_title'];
    }
	// 获取活动类型名称
    public function getActive_banner($id)
    {
        $active_info = Db::name('active_type')->where(array('id' => $id))->find();
        return $active_info['active_banner'];
    }
	// 获取 活动时间段
    public function getPreselltime($where)
    {
 
        $list = Db::name('active_type')->field('id, active_type_name')->where($where)->select();
        return $list;
    }
	// 获取 活动下是否有商品
    public function judge($active_ids)
    {
		foreach($active_ids as $val ){   
			$res = Db::name('active_goods')->where('active_type_id',$val)->find();
			if($res){
				return 0;
			}
		}
        return 1;
    }
	
	 
	 
	 
	 
	 
	 
	 
}
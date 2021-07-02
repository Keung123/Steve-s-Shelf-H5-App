<?php
namespace app\common\service;

use app\common\model\Bargain as BargainModel;
use think\Db;

class Bargain extends Base{

	public function __construct(){
		parent::__construct();
		$BargainModel=new BargainModel();
		$this->model=$BargainModel;
	}
	// 获取商品列表
	public function getList($where = array(), $field = '*', $order = 'id desc', $limin = 20){
	    $list = $this->model->field($field)->where($where)->order($order)->limit($limin)->select();
	    return $list;
    }
    // 获取商品图片
    public function getGoodsImg($goods_id) {
        $goods_info = Db::name('goods')->where(array('goods_id' => $goods_id))->find();
        return $goods_info['picture'];
    }
	// 获取团购活动信息
    public function getActiveInfo($active_id)
    {
        $active_info = Db::name('group_active')->where(['id' => $active_id])->find();
        return $active_info;
    }
    /*
     * 删除活动商品判断 0:未结束 1：结束
     * $ids 商品ID  array
     * $tableName   表名
     * $stock   要查询的字段名
     * time 2018/10/17
     * */
    public function judgems($ids,$tableName,$stock){
        $where = array();
        $where['id'] = array('in', $ids);
        $status=db($tableName)->field($stock)->where($where)->select();
        $array=[];
        foreach($status as $val){
            $array[]=$val[$stock];
        }
        if(in_array(0,$array)){
            return 1;
        }
    }
}
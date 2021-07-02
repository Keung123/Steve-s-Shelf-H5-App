<?php
namespace app\common\service;

use app\common\model\ActiveGoods as ActiveGoodsModel;
use think\Db;

class ActiveGoods extends Base{

	public function __construct(){
		parent::__construct();
		$ActiveGoodsModel=new ActiveGoodsModel();
		$this->model=$ActiveGoodsModel;
	}
	// 获取商品列表
	public function getList($where = array(), $field = '*', $order = 'id desc', $limin = 20){
	    $list = $this->model->field($field)->where($where)->order($order)->limit($limin)->select();
	    return $list;
    }
	// 获取商品列表
	public function getListh($where = array(), $field = '*', $order = 'sort desc', $limin = 20){
	    $list = Db::name('active_goods')->alias('a')->join('active_type b','b.id=a.active_type_id')->join('goods c','a.goods_id = c.goods_id')->field('a.*,b.active_type_name,c.goods_name')->where($where)->order($order)->limit($limin)->select();
	    return $list;
    }
	// 获取商品列表
	public function getLisths($where = array(), $field = '*', $order = 'sort desc', $limin = ''){
	    $list = Db::name('active_goods')->alias('a')->join('active_type b','b.id=a.active_type_id')->join('goods c','a.goods_id = c.goods_id')->field('a.*,b.active_type_name,c.goods_name')->where($where)->order($order)->limit($limin)->select();
	    return $list;
    }
    // 获取商品
    public function getGoodsinfo($goods_id) {
        $goods_info = Db::name('goods')->where(array('goods_id' => $goods_id))->find();
        return $goods_info;
    }
    // 获取进行中活动列表
    public function getActive() {
        $active_list = Db::name('active_type')->where(array('status' => 0, 'id' => ['gt', 8] ))->order('weigh desc')->select();
        return $active_list;
    }
    // 获取 活动信息
    public function getActiveinfo($id) {
	    $where = [
	        'id' => $id,
            'status' => 0,
            'start_time' => ['lt', time()],
            'end_time' => ['gt', time()]
        ];
        $active_info = Db::name('active_type')->where($where)->find();
        return $active_info;
    } 
	// 列表获取 活动信息
    public function getActiveinfos($id) {
	    $where = [
	        'id' => $id,
            'status' => 0,
        ];
        $active_info = Db::name('active_type')->where($where)->find();
        return $active_info;
    }
    // 获取 秒杀活动时间段
    public function getMiaoshatime()
    {
        // 昨天开始
        $zuo = strtotime(date("Y-m-d",strtotime("-1 day")));
        // 明天 结束时间
        $ming = strtotime(date("Y-m-d",strtotime("+2 day"))) -1;
        $where = [
            'status' => 0,
            'start_time' => ['between', $zuo.','.$ming]
        ];
        $list = Db::name('flash_active')->field('id, start_time,end_time')->where($where)->select();
        return $list;
    }
    // 获取 秒杀活动时间段
    public function getMiaoshatimes()
    {
        // 今天凌晨开始
        $zuo = strtotime(date("Y-m-d",time()));
        // 今天十二点结束时间
        $ming = strtotime(date("Y-m-d 23:59:59",time()));
        $where = [
            'status' => 0,
            'start_time' => ['between', $zuo.','.$ming]
        ];
        $list = Db::name('flash_active')->field('id, start_time,end_time')->where($where)->order('start_time','start_time')->select();
        return $list;
    }
	// 获取 活动规则
    public function getActiveRuler($active_id)
    {
		 $res = Db::name('active_type')->field('id, active_type_name,rules_title,rules_content')->where('id',$active_id)->find();
        return $res;
    }
}
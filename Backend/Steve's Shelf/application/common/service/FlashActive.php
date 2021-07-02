<?php
namespace app\common\service;

use app\common\model\FlashActive as FlashActiveModel;
use think\Db;

class FlashActive extends Base{

	public function __construct(){
		parent::__construct();
		$FlashActiveModel=new FlashActiveModel();
		$this->model=$FlashActiveModel;
	}
	// 获取秒杀活动下是否有商品 有返回0 无返回1 可以删除
    public function getGoodsinfo($flash_ids)
    {
		foreach($flash_ids as $val){
			 $res = Db::name('flash_goods')->where(['flash_id' => $val])->find();
			 if($res){
				 return 0;
			 } 
		}
        return 1;
    }
}
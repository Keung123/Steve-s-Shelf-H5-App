<?php
namespace app\common\model;

class GoodsSku extends Base{
	protected $insert = ['create_time'];
	protected $update = ['update_time'];
    protected $type = [
        'attr_value_array'      =>  'array',
    ];		

	/*
	*  自动完成创建时间
	*/
	protected function setCreateTimeAttr(){
		return time();
	}

	/*
	*  自动完成更新时间
	*/
	protected function setUpdateTimeAttr(){
		return time();
	}

	/*
	* 关联商品
	*/
    public function goods(){
        return $this->hasOne('Goods','goods_id','goods_id');
    }		
}
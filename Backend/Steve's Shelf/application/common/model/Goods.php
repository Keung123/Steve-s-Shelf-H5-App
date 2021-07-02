<?php
namespace app\common\model;

class Goods extends Base{
	protected $insert = ['admin_id','create_time','update_time'];
	protected $update = ['update_time'];
    protected $type = [
        'spec'      =>  'array',
        'recommend' =>  'array',
        'spec_array'=>  'array'
    ];	


    /*
    * 关联sku
    */
    protected function sku(){
        return $this->hasMany('GoodsSku','goods_id');
    }	    	

	/*
	* 自动完成管理员ID
	*/
	protected function setAdminIdAttr(){
		return session('admin_id');
	}

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
	* 自动处理图片字段
	*/
	protected function getImagesAttr($value){
		return explode(',', $value);
	}
}
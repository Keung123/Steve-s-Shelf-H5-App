<?php
namespace app\common\model;

class GoodsSpec extends Base{

    public function values(){
        return $this->hasMany('GoodsSpecValue','spec_id');
    }	
}
<?php
namespace app\common\model;

class GoodsSubject extends Base{

    public function values(){
        return $this->hasMany('GoodsSubjectValue','subject_id');
    }	
}
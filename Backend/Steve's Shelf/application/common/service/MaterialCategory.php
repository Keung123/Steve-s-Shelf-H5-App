<?php
/**
 * Created by PhpStorm.
 * User: benbenkeji
 * Date: 2018/10/22
 * Time: 10:17
 */

namespace app\common\service;


class MaterialCategory extends  Base{

    public function __construct(){
        $MaterialModel= new \app\common\model\MaterialCategory();
        $this->model= $MaterialModel;
    }

    /*
     * 判断分类是否有子级
     */
    public function jdugeCategory($ids)
    {
        $idarr = explode(',',$ids);
        if(count($idarr)>0){
            foreach($idarr as $val){
                $map['pid']=['eq',$val];
                $map['type']=['eq',2];
                $res =db('material_category')->where($map)->find();
                if(!$res){
                    return 0;
                }
                return $res;
            }
        }
        return 1;
    }
}
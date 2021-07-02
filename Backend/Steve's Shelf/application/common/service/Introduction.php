<?php

namespace app\common\service;

use think\Db;

class Introduction extends Base
{

    /**
     * 获取引导页详情
     */
    public function details()
    {
        $data = Db::name('introduction')->field('id,img,weigh,createtime')->order('weigh desc')->select();
        return $data;
    }

    /**
     * 引导页图片添加
     */
    public function addImg($params)
    {
        $imgs = explode(',',$params['img']);
       
        Db::startTrans();
        foreach ($imgs as $v) {
             //获取当前图片的最大权限值
            $max = Db::name('introduction')->max('weigh');
            $map['img'] = $v;
            $map['createtime'] = time();
            $map['weigh'] = $max + 1;
            $res = Db::name('introduction')->insert($map);
        }
        if ($res) {
            Db::commit();
            return true;
        }
        Db::rollback();
        return false;
    }
}
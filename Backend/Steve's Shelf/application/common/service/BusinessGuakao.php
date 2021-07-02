<?php
/**
 * Created by PhpStorm.
 * User: benbenkeji
 * Date: 2018/10/31
 * Time: 14:03
 */

namespace app\common\service;


class BusinessGuakao extends Base
{
    public  function __construct(){
        $BusinessGuakaoModel =  new \app\common\model\BusinessGuakao();
        $this->model = $BusinessGuakaoModel;
    }
}
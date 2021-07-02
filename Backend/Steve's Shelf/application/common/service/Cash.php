<?php
/**
 * Created by PhpStorm.
 * Date: 2018/11/3
 * Time: 12:22
 */

namespace app\common\service;


class Cash extends Base
{
    public function __construct(){
        $CashModel = new \app\common\model\Cash();
        $this->model = $CashModel;
    }
}
<?php
namespace app\common\service;

use app\common\model\Menu as MenuModel;

class Menu extends Base{
    public function __construct(){
        parent::__construct();
        $ServiceMenuModel= new MenuModel();
        $this->model=$ServiceMenuModel;
    }
    /**
     * 获取列表
     */
    public function getList($where = array(), $field = '*', $order = 'sort desc', $limin = 20){
        $list = $this->model->field($field)->where($where)->order($order)->limit($limin)->select();
        return $list;
    }
}

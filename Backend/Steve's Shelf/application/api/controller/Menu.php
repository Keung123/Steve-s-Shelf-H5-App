<?php


namespace app\api\controller;

use app\common\service\Menu as MenuService;

class Menu extends Common
{
    protected $model;

    public function __construct()
    {
        $this->model = new MenuService();
    }

    /**
     * 九宫格列表
     */
    public function getMenus()
    {
        $rows = $this->model->getList('','*');
        return $this->json($rows);
    }
}
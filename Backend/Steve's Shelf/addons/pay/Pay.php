<?php

namespace addons\pay;

use app\common\library\Menu;
use think\Addons;

/**
 * 插件
 */
class Pay extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        $menu = [
            [
                'name' => 'pay',
                'title' => '支付管理',
                'icon' => 'fa fa-wrench',
                'sublist' => [
                    [
                        'name' => 'pay.pay/index',
                        'title' => '拼团列表',
                        'icon' => 'fa fa-list',
                    ],
                ]
            ]
        ];
        Menu::create($menu);
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        Menu::delete('pay');
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {

        return true;
    }

    /**
     * 插件配置
     * @return bool
     */
    public function config()
    {
        return true;
    }

}

<?php

namespace addons\coupon;

use app\common\library\Menu;
use think\Addons;

/**
 * 插件
 */
class Coupon extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        $menu = [
            [
                'name' => 'coupon',
                'title' => '优惠券管理',
                'icon' => 'fa fa-wrench',
                'sublist' => [
                    [
                        'name' => 'coupon.coupon/index',
                        'title' => '种类列表',
                        'icon' => 'fa fa-list',
                    ],
                    [
                        'name' => 'coupon.coupon/userList',
                        'title' => '用户列表',
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
        Menu::delete('coupon');
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

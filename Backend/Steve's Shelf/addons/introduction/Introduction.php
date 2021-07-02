<?php

namespace addons\introduction;

use app\common\library\Menu;
use think\Addons;

/**
 * 插件
 */
class Introduction extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        $menu = [
            [
                'name' => 'introduction',
                'title' => '引导页管理',
                'icon' => 'fa fa-map-marker',
                'sublist' => [
                    [
                        'name' => 'introduction.introduction/index',
                        'title' => '展示列表',
                        'icon' => 'fa fa-map-marker',
                    ]
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
        Menu::delete('introduction');
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

}

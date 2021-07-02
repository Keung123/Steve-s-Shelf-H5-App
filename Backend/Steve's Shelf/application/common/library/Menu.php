<?php
namespace app\common\library;

use app\admin\model\AuthRule;
use think\Collection;

class Menu
{
    public static function create($menus, $pid = 0)
    {
        if (empty($pid)) {
            $pid = 0;
            Menu::delete($menus[0]['name']);
        }
        if (!is_numeric($pid)) {
            $pid = self::getName($pid);
        }
        foreach ($menus as $k=>$item) {
            $data = [
                "name" => $item['name'],
                "title" => $item['title'],
                "icon" => $item['icon'],
                "condition" => "",
                "pid" => $pid,
                "is_menu" => 1,
                "status" => 1
            ];
            $menu = AuthRule::create($data);
            if ($item['sublist'] && $menu) {
                self::create($item['sublist'], $menu->id);
            }
        }
    }

    public static function enable($name)
    {
        $menu = AuthRule::getByName($name);
        $menu->status = 1;
        $menu->save();
    }

    public static function disable($name)
    {
        $menu = AuthRule::getByName($name);
        $menu->status = 0;
        $menu->save();
    }

    public static function delete($name)
    {
        $ids = self::getIds($name);
        AuthRule::destroy($ids);
    }

    public static function getIds($name, $ids = [])
    {
        $parent = AuthRule::getByName($name);
        if (!$parent) {
            return false;
        }
        $ids = $parent->id;
        $child = AuthRule::where('pid', $parent->id)->column('id');
        if ($child) {
            $child[] = $ids;
            $ids = $child;
        }
        return $ids;
    }

    public static function getList()
    {
        $list = AuthRule::where('status', 1)->where('is_menu',1)->field('id,pid,name,icon,title')->order('id','asc')->select();
        $menus = [];
        $list = collection($list)->toArray();
        foreach ($list as $item) {
            if ($item['pid'] == 0){
                $menus[$item['id']] = $item;
            } else {
                if ($menus[$item['pid']]) {
                    $menus[$item['pid']]['child'][] = $item;
                }
            }
        }
        return array_values($menus);
    }

    public static function getAuthList($url_ids = [])
    {
        $list = AuthRule::where('status', 1)->where('id','in', $url_ids)->where('is_menu',1)->field('id,pid,name,icon,title')->order('id','asc')->select();
        $menus = [];
        $list = collection($list)->toArray();
        foreach ($list as $item) {
            if ($item['pid'] == 0){
                $menus[$item['id']] = $item;
            } else {
                if ($menus[$item['pid']]) {
                    $menus[$item['pid']]['child'][] = $item;
                }
            }
        }
        return array_values($menus);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/4/11 11:44
 */
namespace app\admin\controller;

use app\common\model\Menu;
use think\Db;

class Addons extends Base
{
//   插件列表
    public function index()
    {
        //初始化数据
        if ($this->request->isAjax()) {
            $addons = get_addon_list();
            return ['total'=>count($addons),'rows'=>$addons];
        }
        return $this->fetch();
    }

//  离线插件安装
    public function add()
    {
        $file = $this->request->file('file');
        $fname = $file->getInfo('name');
        $dir = str_replace('.zip','', $fname);
        $dest_dir = ADDON_PATH . $dir;
        if (empty($file)) {
            $this->result('', -1, '文件不能为空');
        }
        $info = $file->move(ADDON_PATH, $dir);
        if ($info) {
            $zip = new \ZipArchive();
            $filepath = ADDON_PATH . $dir.'.zip';
            if ($zip->open($filepath)) {
                $zip->extractTo(ADDON_PATH);
                $zip->close();
            }
            @unlink($filepath);
            $dirs = scandir($dest_dir);
            if ($dirs) {
                foreach ($dirs as $d) {
                    if ($d == 'application' || $d == 'public') {
                        rcopy($dest_dir . DS . $d, ROOT_PATH .$d);
                    }
                }
            }
            $this->result('', 1, '加载成功', 'json');
        } else {
            $this->result('', 0, '加载失败', 'json');
        }
    }

//    插件安装
    public function install()
    {
        $addon_name = $this->request->post('addon_name');
        $addon_class = get_addon_class($addon_name);
        $addon = new $addon_class();

        if ($addon->install()) {
            $sql = ADDON_PATH . $addon_name . DS . 'install.sql';
            $lines = file($sql);
            $temp = '';
            foreach ($lines as $line) {
                if (substr($line, 0, 2) == '--' || $line == '') {
                    continue;
                }
                $temp .= $line;
                if (substr(trim($temp),0,2)=='/*' && substr(trim($temp), -2, 2) == '*/') {
                    $temp = '';
                }
                if (!empty(trim($temp)) && substr(trim($temp), -1, 1) == ';') {
                    //前缀替换
                    $temp = str_replace("\r\n", '', $temp);
                    Db::execute($temp);
                    $temp = '';
                }
            }
            $this->result($sql, 1, '安装成功', 'json');
        } else {
            $this->result('',0, '安装失败', 'json');
        }
    }

//    插件开启关闭
    public function enable()
    {
        $addon_name = $this->request->post('addon_name');
        $addon_class = get_addon_class($addon_name);
        $addon = new $addon_class();
        $config = get_addon_info($addon_name);
        if ($config['state'] != 1) {
            $rs = $addon->enable();
            $msg = '开启成功';
            set_addon_info($addon_name, ['state' => 1]);
        } else {
            $rs = $addon->disable();
            $msg = '关闭成功';
            set_addon_info($addon_name, ['state' => 0]);
        }
        if ($rs) {
            // 写入配置信息
            $this->result('', 1, $msg, 'json');
        } else {
            $this->result('',0, '操作失败', 'json');
        }
    }

//    插件卸载删除
    public function uninstall()
    {
        $addon_name = $this->request->post('addon_name');
        $addon_class = get_addon_class($addon_name);
        $addon = new $addon_class();
        if ($addon->uninstall()) {
            //移除application
            $app_path = ROOT_PATH . 'application' . DS . 'admin' . DS .'controller' . DS . $addon_name;
            if (is_dir($app_path)) {
                rmdirs($app_path);
            }
            //移除application_view
            $view_path = ROOT_PATH . 'application' . DS . 'admin' . DS .'view' . DS . $addon_name;
            if (is_dir($view_path)) {
                rmdirs($view_path);
            }
            //移除api
            $api_file = ROOT_PATH . 'application' . DS . 'api' . DS .'controller' . DS . ucfirst($addon_name) .'.php';
            if (file_exists($api_file)) {
                unlink($api_file);
            }
            // 移除common
            $model_file = ROOT_PATH . 'application' . DS . 'common' . DS .'model' . DS . ucfirst($addon_name) . '.php';
            if (file_exists($model_file)) {
                unlink($model_file);
            }
            $service_file = ROOT_PATH . 'application' . DS . 'common' . DS .'service' . DS . ucfirst($addon_name) . '.php';
            if (file_exists($service_file)) {
                unlink($service_file);
            }
            //移除public
            $pub_path = ROOT_PATH . 'public/static/assets/js/backend' .DS. $addon_name;
            if (is_dir($pub_path)) {
                rmdirs($pub_path);
            }
            //移除插件
            rmdirs(ADDON_PATH . $addon_name);
            $this->result('', 1, '卸载成功', 'json');
        } else {
            $this->result('',0, '卸载失败', 'json');
        }
    }
}
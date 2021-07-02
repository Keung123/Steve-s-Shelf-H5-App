<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/5/22 17:32
 */

namespace app\admin\controller;


class Custom extends Base
{
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = db('custom')->paginate(10);
            return json(['total'=> $list->total(), 'rows'=>$list->items()]);
        }
        return $this->fetch();
    }

    public function addModule()
    {
        if ($this->request->isAjax()) {
            $data = input('post.');
            if (!empty($data['actions'])) {
                $data['actions'] = implode(',',array_keys($data['actions']));
            }
            $data['createtime'] = date('Y-m-d H:i:s');
            $gid = db('custom')->insertGetId($data);
            if ($gid) {
                $this->result($gid, 1, '新增成功');
            } else {
                $this->result('', 0,'新增失败');
            }
        }
       return $this->fetch();
    }

    public function addTable()
    {
        if ($this->request->isAjax()) {
            $cid = input('cid');
            if (empty($cid)) {
                $this->result('', 1, '缺少参数');
            }
            $data = input('post.');
            $fields = array_values($data['fields']);
            if (empty($fields)) {
                $this->result('', 1, '缺少数据');
            }
            $update = [
                'sql' => json_encode($fields)
            ];
            $rs = db('custom')->where('id', $cid)->update($update);
            if ($rs) {
                $this->result($cid, 1, '添加成功');
            } else {
                $this->result('', 0, '添加失败');
            }
        }
        return $this->fetch();
    }

    public function addExtra()
    {
        return $this->fetch();
    }

    public function edit($ids)
    {
        if ($this->request->isAjax()) {
            $data = input('post.');
            $step = input('step', 0);
            if (!empty($data['actions'])) {
                $data['actions'] = implode(',',array_keys($data['actions']));
            }
            if (array_key_exists('fields', $data)) {
                $fields = array_values($data['fields']);
                $data['sql'] = json_encode($fields);
                unset($data['fields']);
            }
            if ($step == 1 && empty($data['sql'])) {
                $data['sql'] = '';
            }
            $data['updatetime'] = date('Y-m-d H:i:s');
            $rs = db('custom')->where('id', $ids)->update($data);
            if ($rs) {
                $this->result('', 1, '修改成功');
            } else {
                $this->result('', 0, '修改失败');
            }
        }
        $step = input('step', 0);
        $cinfo = db('custom')->find($ids);
        $cinfo['actions'] = explode(',', $cinfo['actions']);
        $cinfo['fields'] = json_decode($cinfo['sql'], 1);
        $this->assign('cinfo', $cinfo);

        if ($step == 0) {
            return $this->fetch('edit_module');
        } else {
            return $this->fetch('edit_table');
        }
    }

    public function delete()
    {
        $cid = input('ids');
        $rs = db('custom')->delete($cid);
        if ($rs) {
            $this->result('', 1, '成功删除');
        } else {
            $this->result('', 0, '删除失败');
        }
    }
}
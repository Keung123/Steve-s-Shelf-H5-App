<?php
/**
 * Created by PhpStorm.
 * User: benbenkeji
 * Date: 2018/10/15
 * Time: 14:46
 */
namespace app\admin\controller;

use app\common\service\Sen;
use think\Db;
use think\Request;

class Sensitive extends Base{

    /*
     * 敏感词列表
     * */

    public function index(){
       $sst_name=trim(input('sst_name'));
       $sst_status=trim(input('sst_status'));
       $this->assign('sst_name',$sst_name);
       $this->assign('sst_status',$sst_status);

       if(\request()->isAjax()){

           //排序
           $order='id desc';
           $limit=input('get.offset').",".input('get.limit');
           if(input('sst_name')){
               $where['sst_name']=['like','%'.$sst_name.'%'];
           }
           $status=input('sst_status');
           $where['sst_status']= ['eq',1];
           $sensitive=new Sen();
           $total=$sensitive->count($where);
           $rows=$sensitive->select($where,'*',$order, $limit);

           foreach ($rows as $val) {
               $val['sst_status']=$val['sst_status']==1?'启用':'停用';
               $val['add_time']=date('Y-m-d',$val['add_time']);
           }
           return json(['total'=>$total,'rows'=>$rows]);
       }else{
           return $this->fetch();
       }
    }

    /*
     * 敏感词添加
     * */

    public function add(){
        if(request()->isAjax()){
            $row = input('post.row/a');
            if($row){
                $map['sst_name']=trim($row['sst_name']);
                $map['add_time']=time();
                $map['sst_status']=trim($row['sst_status']);
            }
            $sensitive= new Sen();
            $res=$sensitive->add($map);
            //添加日志记录
            $id=db('sensitive')->getLastInsID();
            $this->write_log('敏感词添加',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            return $this->fetch();
        }
    }
    /*
     * 敏感词修改
     * */
    public function edit(){
        $sensitive=new Sen();
        if(Request()->isAjax()){
            $row=input('post.row/a');
            $map['id']=input('post.id');
            $res=$sensitive->save($map,$row);
            //添加日志记录
            $this->write_log('敏感词修改',$map['id']);
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $row=$sensitive->find($map);
            $this->assign('row',$row);
            return $this->fetch();
        }
    }
    /*
     * 敏感词删除
     * */

    public function delete()
    {
        $ids=input('get.ids');
        $map['id']=['in',$ids];
        $res=db('sensitive')->where($map)->update(['sst_status'=>0]);
        //添加日志
        $this->write_log('敏感词删除',$ids);

        return AjaxReturn($res);
    }
    /*
     * 导入敏感词
     */
    public function daoru()
    {
        $str = file_get_contents("./mingan.txt");
        $arr = explode("\r\n", $str);
        if (is_array($arr) && $arr) {
            $data = [];
            foreach ($arr as $key => $val) {
                $data[$key]['sst_name'] = $val;
                $data[$key]['add_time']=time();
                $data[$key]['sst_status']=1;
                if ($key%100 == 1) {
                    Db::name('sensitive')->insertAll($data);
                    $data =[];
                }
            }
            Db::name('sensitive')->insertAll($data);
        }
    }
}
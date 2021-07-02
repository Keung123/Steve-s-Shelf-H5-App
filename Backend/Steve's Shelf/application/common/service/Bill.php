<?php
/**
 * Created by PhpStorm.
 * User: benbenkeji
 * Date: 2018/11/1
 * Time: 11:20
 */

namespace app\common\service;


class Bill extends Base
{
    public function __construct(){
        $BillModel = new \app\common\model\Bill();
        $this->model = $BillModel;
    }

    public function getOneBill($id)
    {
        $res = db('bill')->alias('a')
            ->join('__USERS__ c','a.user_id=c.user_id','LEFT')
            ->field('a.*,c.user_name,c.user_mobile')->find($id);
        return $res;
    }

    public function getBills($where,$sort,$limit)
    {
        $rows = db('bill')->alias('a')
            ->join('__USERS__ c','a.user_id=c.user_id','LEFT')
            ->field('a.*,c.user_name,c.user_mobile')
            ->where($where)
            ->order($sort)->limit($limit)->select();
        $total = $this->model->count();

        $data = [
            'list'=>$rows,
            'total'=>$total
        ];
        return $data;
    }

    public function getHistorys($uid,$p)
    {
        $limit = ' '.($p-1)*10 .',10 ';
        $total = $this->model->where(['user_id'=>$uid])->count();
        if(empty($total)){
            return 0;
        }
        $list = $this->model->where(['user_id'=>$uid])->order('id desc')->limit($limit)->field('b_balance,b_img,add_time,sh_status,sh_relation')->select();
        if($list){
            for($i=0;$i<count($list);$i++){
                $list[$i]['add_time'] = date('Y-m-d H:i:s',$list[$i]['add_time']);
                if(!empty($list[$i]['b_img'])){
                    $b_img = trim($list[$i]['b_img'],',');
                    $list[$i]['b_img'] = explode(',',$b_img);
                }

                switch ($list[$i]['sh_status']){
                    case 0:
                        $list[$i]['sh_status']='发票异常';
                        break;
                    case 1:
                        $list[$i]['sh_status']='发票正常';
                        break;
                    case 2:
                        $list[$i]['sh_status']='待审核';
                        break;
                }
                $list[$i]['b_balance'] = floatval($list[$i]['b_balance']);
            }
        }
        $data = [
            'list'=>$list,
            'total'=>$total
        ];
        return $data;
    }
}
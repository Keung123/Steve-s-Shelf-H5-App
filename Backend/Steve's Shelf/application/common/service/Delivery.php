<?php


namespace app\common\service;


use think\Db;

class Delivery extends Base
{
    /**
     * 统计业绩
     */
    public function collect($where)
    {
        $where2 = '';
        if (!empty($where['user_name'])) {
            //$map['a.remark'] = array('like',$where['user_name']);
            $map['nickname'] = array('like','%'.$where['user_name'].'%');
        }
        if (!empty($where['start_time'])) {
            //$map['a.createtime'] = array('>=',strtotime($where['start_time']));
            $where2['order_create_time'][] = array('>=',strtotime($where['start_time']));
        }
        if (!empty($where['end_time'])) {
           // $map['a.createtime'] = array('<=',strtotime($where['end_time']));
            $where2['order_create_time'][] = array('<=',strtotime($where['end_time']));
        }

        //找出登录会员所属的组
        $admin_id = session('admin_id');
        $group_id = Db::name('admin')->where('admin_id',$admin_id)->value('group_id');
//        if ($group_id==16 ||$group_id ==17){//销售人员
//            $map['a.user_id'] = $admin_id;
//        }

        if ($group_id==16 ||$group_id ==17){//销售人员
            // $map['a.user_id'] = $admin_id;
            //$map['b.post_id'] = $admin_id;
            $map['admin_id'] = $admin_id;
        }else{
           // $map['b.post_id']=['gt',0];
           // $map['c.group_id'] = 17;
            $map['group_id'] = 17;
        }

        if ((!empty($where['user_name']) &&!empty($where['start_time'])) || (!empty($where['user_name']) && !empty($where['end_time']))){
//            $data = Db::name('delivery a')
//                ->field('count(a.id) as num,a.remark,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order b','a.order_id = b.order_id')
//                ->join('admin c','a.user_id = c.admin_id')
//                ->where($map)
//                ->group('a.user_id')
//                ->select();
//            $data = Db::name('order b')
//                ->field('count(a.id) as num,a.remark,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('delivery a','a.order_id = b.order_id')
//                ->join('admin c','a.user_id = c.admin_id')
//                ->where($map)
//                ->group('b.post_id')
//                ->select();
//            $data = Db::name('admin c')
//                //  ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order a','a.post_id = c.admin_id','left')
//                ->join('delivery b','a.order_id = b.order_id','left')
//                ->field('count(b.id) as num,b.remark,cast(sum(a.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,c.admin_id as user_id')
//                ->where($map)
//                // ->whereTime('createtime','month')
//                // ->group('a.sender_id')
//                ->select();
            $data = Db::name('admin')->where($map)->select();
        } else if (!empty($where['user_name'])) {
//            $data = Db::name('delivery a')
//                ->field('count(a.id) as num,a.remark,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order b','a.order_id = b.order_id')
//                ->join('admin c','a.user_id = c.admin_id')
//                ->where($map)
//                ->whereTime('createtime','month')
//                ->group('a.user_id')
//                ->select();
//            $data = Db::name('order b')
//                ->field('count(a.id) as num,a.remark,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('delivery a','a.order_id = b.order_id')
//                ->join('admin c','a.user_id = c.admin_id')
//                ->where($map)
//                ->whereTime('a.createtime','month')
//                ->group('b.post_id')
//                ->select();
//            $data = Db::name('admin c')
//                //  ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order a','a.post_id = c.admin_id','left')
//                ->join('delivery b','a.order_id = b.order_id','left')
//                ->field('count(b.id) as num,b.remark,cast(sum(a.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,c.admin_id as user_id')
//                ->where($map)
//                 ->whereTime('createtime','month')
//                // ->group('a.sender_id')
//                ->select();
            $data = Db::name('admin')->where($map)->select();
        } else {
//            $data = Db::name('delivery a')
//                ->field('count(a.id) as num,a.remark,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order b','a.order_id = b.order_id')
//                ->join('admin c','a.user_id = c.admin_id')
//                ->where($map)
//                ->whereTime('createtime','month')
//                ->group('a.user_id')
//                ->select();
//            $data = Db::name('order b')
//                ->field('count(a.id) as num,a.remark,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('delivery a','a.order_id = b.order_id','left')
//                ->join('admin c','b.post_id = c.admin_id')
//                ->where($map)
//              //  ->whereTime('a.createtime','month')
//                ->group('b.post_id')
//                ->select();
//            $data = Db::name('admin c')
//                //  ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order a','a.post_id = c.admin_id','left')
//                ->join('delivery b','a.order_id = b.order_id','left')
//                ->field('count(b.id) as num,b.remark,cast(sum(a.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,c.admin_id as user_id')
//                ->where($map)
//                // ->whereTime('createtime','month')
//                // ->group('a.sender_id')
//                ->select();
            $data = Db::name('admin')->where($map)->select();
        }
        foreach ($data as &$v){
            $v['allMoney'] = round(Db::name('order')->where('post_id',$v['admin_id'])->where($where2)->sum('order_pay_price'),2);
           // $v['num'] = Db::name('delivery')->where('user_id',$v['admin_id'])->count();
            $v['num'] = Db::name('order')->where('post_id',$v['admin_id'])->where($where2)->count();
            $v['user_id'] = $v['admin_id'];
        }
        $data = [
            'rows' => $data,
            'total' => count($data),
        ];
        return $data;
    }
}
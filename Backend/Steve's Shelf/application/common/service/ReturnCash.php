<?php


namespace app\common\service;


use think\Db;

class ReturnCash extends Base
{
    /**
     * 返现操作
     */
    public function handleCash($data)
    {
        //$map['user_id'] = session('admin_id');
        $order_info = Db('order')->where('order_id',$data['order_id'])->find();
        $map['user_id'] = $order_info['sender_id'];
        $map['order_id'] = $data['order_id'];
        $map['cash'] = $data['cash'];
        $map['remark'] = $data['remark'];
        $map['createtime'] = $_SERVER['REQUEST_TIME'];
        Db::startTrans();
        $res = Db::name('return_cash')->insert($map);
        //记录订单是否进行返现操作
        $where['order_id'] = array('eq',$data['order_id']);
        $go = Db::name('order')->where($where)->update(['is_cash' => 1]);
        //增加用户账户余额
        $vip_id = Db::name('order')->field('order_uid')->where('order_id = '.$data['order_id'])->find();
        $money = Db::name('users')->where('user_id = '.$vip_id['order_uid'])->setInc('user_account',$data['cash']);
        //添加账户进账日志
        $this->accountLog($vip_id['order_uid'],$data['cash'],$data['order_id']);
        if ($res && $go && $money) {
            Db::commit();
            return true;
        }
        Db::rollback();
        return false;
    }
    /*
         *售后退货申请完成 明细记录
        */
    public function accountLog($uid,$acco_num,$orderId = ''){

        $log_insert = [
            'a_uid' => $uid,
            'acco_num' => $acco_num ? $acco_num : 0,
            'acco_type' => 4,
            'acco_desc' => '订单返利',
            'acco_time' => time(),
            'order_id'=>$orderId
        ];
        Db::name('account_log')->insert($log_insert);

    }
    /**
     * 操作返现详情页
     */
    public function getCashDetails($id)
    {
        $data = Db::name('return_cash a')
            ->field('a.cash,a.remark,a.createtime,b.order_no,b.order_all_price,b.order_pay_price,c.user_name,c.user_mobile,d.nickname')
            ->join('order b','a.order_id = b.order_id')
            ->join('users c','b.order_uid = c.user_id')
            ->join('admin d','a.user_id = d.admin_id')
            ->where('a.order_id = '.$id)
            ->find();
        $data['createtime'] = date('Y-m-d H:i:s',$data['createtime']);
        return $data;
    }

    /**
     * 统计业绩
     */
    public function collect($where)
    {
        $where2 = '';
        $where3 = '';
        if (!empty($where['user_name'])) {
           // $map['c.nickname'] = array('like','%'.$where['user_name'].'%');
            $map['nickname'] = array('like','%'.$where['user_name'].'%');
        }
        if (!empty($where['start_time'])) {
            $where2['order_create_time'][] = array('>=',strtotime($where['start_time']));
            $where3['createtime'][] = array('>=',strtotime($where['start_time']));
        }
        if (!empty($where['end_time'])) {
            $where2['order_create_time'][] = array('<=',strtotime($where['end_time']));
            $where3['createtime'][] = array('<=',strtotime($where['end_time']));
        }

        //找出登录会员所属的组
        $admin_id = session('admin_id');
        $group_id = Db::name('admin')->where('admin_id',$admin_id)->value('group_id');
        if ($group_id==16 ||$group_id ==17){//销售人员
           // $map['a.user_id'] = $admin_id;
           // $map['a.sender_id'] = $admin_id;
            $map['admin_id'] = $admin_id;
        }else{
           // $map['a.sender_id']=['gt',0];
            $map['group_id'] = 16;
        }

        if ((!empty($where['user_name']) &&!empty($where['start_time'])) || (!empty($where['user_name']) && !empty($where['end_time']))){
//            $data = Db::name('return_cash a')
//                ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order b','a.order_id = b.order_id')
//                ->join('admin c','a.user_id = c.admin_id')
//                ->where($map)
//                ->group('a.user_id')
//                ->select();


           // $data = Db::name('order a')
//            $data = Db::name('admin c')
//                //  ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order a','a.sender_id = c.admin_id','left')
//                ->join('return_cash b','a.order_id = b.order_id','left')
//                ->field('count(b.id) as num,b.remark,sum(b.cash) as total,cast(sum(a.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,c.admin_id as user_id')
//                ->where($map)
//                // ->whereTime('createtime','month')
//                // ->group('a.sender_id')
//                ->select();
            $data = Db::name('admin')->where($map)->select();
        } else if (!empty($where['user_name'])) {
//            $data = Db::name('return_cash a')
//                ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order b','a.order_id = b.order_id')
//                ->join('admin c','a.user_id = c.admin_id')
//                ->where($map)
//                ->whereTime('createtime','month')
//                ->group('a.user_id')
//                ->select();
           // $data = Db::name('order a').
//            $data = Db::name('admin c')
//                //  ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//
//                ->join('order a','a.sender_id = c.admin_id','left')
//                ->join('return_cash b','a.order_id = b.order_id','left')
//                ->field('count(b.id) as num,b.remark,sum(b.cash) as total,cast(sum(a.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,c.admin_id as user_id')
//                ->where($map)
//                 ->whereTime('createtime','month')
//                // ->group('a.sender_id')
//                ->select();
            $data = Db::name('admin')->where($map)->select();
        } else {
//            $data = Db::name('return_cash a')
//                ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//                ->join('order b','a.order_id = b.order_id')
//                ->join('admin c','a.user_id = c.admin_id')
//                ->where($map)
//                ->whereTime('createtime','month')
//                ->group('a.user_id')
//                ->select();
           $data = Db::name('admin')->where($map)->select();
//            $data = Db::name('admin c')
//                //  ->field('count(a.id) as num,a.remark,sum(a.cash) as total,cast(sum(b.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,a.user_id')
//
//                ->join('order a','a.sender_id = c.admin_id','left')
//                ->join('return_cash b','a.order_id = b.order_id','left')
//                ->field('count(b.id) as num,b.remark,sum(b.cash) as total,cast(sum(a.order_pay_price) as decimal(16,2)) as allMoney,c.nickname,c.admin_id as user_id')
//                ->where($map)
//               // ->whereTime('createtime','month')
//               // ->group('a.sender_id')
//                ->select();
        }
        foreach ($data as &$v){
            $v['allMoney'] = round(Db::name('order')->where('sender_id',$v['admin_id'])->where($where2)->sum('order_pay_price'),2);
            $v['num'] = Db::name('return_cash')->where('user_id',$v['admin_id'])->where($where3)->count();
            $v['total'] =  round(Db::name('return_cash')->where('user_id',$v['admin_id'])->where($where3)->sum('cash'),2);
            $v['user_id'] = $v['admin_id'];
        }
        $data = [
            'rows' => $data,
            'total' => count($data),
        ];
       return $data;
    }
}
<?php

namespace app\api\controller;

use think\Db;

class Group extends Common{
    /**
     *  开团前判断
     * request method GET
     * @param int uid
     * @param string token
     * @param string team_id
     * @return json
     */
    public function CheckedTeam()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $team_id = input('request.team_id');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $where = [
            'user_id' => $uid,//用户id
            'team_id' => $team_id,//开团id
            'end_time'=>['>',time()],
            'status' => ['eq',1],//开团状态不等于失败
        ];
        $res = Db::name("team_found")->where($where)->find();
        if($res){
            return $this->json('', -1, '已经发起开团');
        }
        $users = Db::name("users")->where(['user_id'=>$uid])->field("user_name")->find();
        //开团商品是否存在
        $team = Db::name("team_activity")->where(['id'=>['eq',$team_id],'status'=>['eq',0]])->field('time_limit,need_num,team_price,goods_id')->find();

        if(!$team){
            return $this->json('', 0, '开团商品不存在');
        }
        return $this->json('', 1, '可以开团');
    }

    /**
     * 发起开团
     * request method GET
     * @param int uid
     * @param string token
     * @param team_id int 拼团ID
     * @param order_id int 支付成功订单ID
     * @return json
     */
    public function openTeam()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $team_id = input('request.team_id');
        $order_id = input('request.order_id');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $where = [
            'user_id' => $uid,//用户id
            'team_id' => $team_id,//开团id
            'end_time'=>['>',time()],
            'status'=>1
        ];
        $res = Db::name("team_found")->where($where)->find();
        if($res){
            return $this->json('', -1, '已经发起开团');
        }
        $users = Db::name("users")->where(['user_id'=>$uid])->field("user_name")->find();
        //开团商品是否存在
        $team = Db::name("team_activity")->where(['id'=>['eq',$team_id],'status'=>['eq',0]])->field('time_limit,need_num,team_price,goods_id')->find();
        //商品限制 商品被开团的次数
        $num = Db::name("team_found")->where('team_id',$team_id)->select();
        $num =  count($num);
        //活动限制
        $nums = Db::name("active_type")->where('id',3)->field('limit_num')->find();
        if($num >= $nums){
            return $this->json('', -2, '此商品到达开团上限');
        }
        if(!$team){
            return $this->json('', 0, '未知参数');
        }
        $goods = Db::name("goods")->where(['goods_id'=>$team['goods_id']])->field('vip_price,show_price,price')->find();
        if(!$team['time_limit']){
            $team['time_limit'] = 2;
        }
        $data = [
            'user_id' => $uid,
            'team_id' => $team_id,
            'nick_name' => $users['user_name'],
            'order_id' => $order_id,//支付成功 订单id
            'start_time' => time(),//成团时间
            'end_time' => time()+ ($team['time_limit']*60),//结束时间
            'joins' => 1,//已经参团人数
            'need' =>  $team['need_num'],//需要人数
            'goods_price' =>  $goods['price'],//拼团价格
            'price' =>  $team['team_price'],//拼团价格
            'status' => 1,//0:待开团 1：已开团 2：拼团成功 3:拼团失败

        ];
        $res = Db::name("team_found")->insert($data);
        //插入到参团表
        $found_id = Db::name("team_found")->getLastInsID();
        if($res){
            $data = [
                'user_id' => $uid,//用户id
                'team_id' => $team_id,//拼团id
                'nick_name' => $users['user_name'],
                'order_id' => $order_id,//订单支付成功 id
                'join_time' => time(),//参团时间
                'found_id' => $found_id, //开团id值
                'found_user_id' => $uid,//开团用户id值
                'status' => 1,//1：拼单成 2：成团成功 3：成团失败
            ];
            $res = Db::name("team_follow")->insert($data);
        }

        if(!$res){
            return $this->json('', 0, '开团失败');
        }
        return $this->json('', 1, '开团成功');
    }

    /**
     *  参团前判断
     *  request method GET
     * @param int uid
     * @param string token
     * @param found_id
     * @return Json
     */
    public function CheckedFollow()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $found_id = input('request.found_id');//开团id
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $where = [
            'id' => $found_id,//开团id
            'status' => 1,//开团0:待开团 1：已开团 2：拼团成功 3:拼团失败
        ];
        $res = Db::name("team_found")->where($where)->find();

        $wheres = [
            'user_id' => $user_id,//用户id
            'found_id' => $found_id ,//开团id值
        ];
        $result = Db::name("team_follow")->where($wheres)->find();
        if($result){
            return $this->json('', -4, '已经参加过了');
        }

        if(!$res){
            return $this->json('', -3, '此团不存在或者已经结束');
        }
        if($res['joins']>=$res['need']){
            return $this->json('', -1, '开团人数已经完成');
        }
        //不能参加自己发起的团
        if($uid == $res['user_id']){
            return $this->json('', -2, '不能参加自己发起的团');
        }
        return $this->json('', 1, '可以参团');
    }

    /**
     *  参团
     * request method GET
     * @param int uid
     * @param string token
     * @param int id 开团id
     * @param int order_id 支付成功id
     * @return Json
     */
    public function joinTeam()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $found_id = input('request.id');//开团id
        $order_id = input('request.order_id');//开团id
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $where = [
            'id' => $found_id,//开团id
            'status' => 1,//开团0:待开团 1：已开团 2：拼团成功 3:拼团失败
        ];
        $res = Db::name("team_found")->where($where)->find();

        $wheres = [
            'user_id' => $user_id,//用户id
            'found_id' => $found_id ,//开团id值
        ];
        $result = Db::name("team_follow")->where($wheres)->find();
        if($result){
            return $this->json('', -4, '已经参加过了');
        }

        if(!$res){
            return $this->json('', -3, '此团不存在或者已经结束');
        }
        if($res['joins']>=$res['need']){
            return $this->json('', -1, '开团人数已经完成');
        }
        //不能参加自己发起的团
        if($uid == $res['user_id']){
            return $this->json('', -2, '不能参加自己发起的团');
        }
        $users = Db::name("users")->where(['user_id'=>$uid])->field("user_name")->find();
        $data = [
            'user_id' => $uid,//用户id
            'team_id' => $res['team_id'],//拼团id
            'nick_name' => $users['user_name'],
            'order_id' => $order_id,//订单支付成功 id
            'join_time' => time(),//参团时间
            'found_id' => $found_id, //开团id值
            'found_user_id' => $res['user_id'],//开团用户id值
            'status' => 1,//1：拼单成 2：成团成功 3：成团失败

        ];
        $res = Db::name("team_follow")->insert($data);
        Db::name('team_found')->where(array('id' => $found_id))->setInc('joins',1);
        //参团人数达标 未超时 开团成功
        $row =  Db::name('team_found')->where(array('id' => $found_id))->find();
        if($row['joins']>=$row['need']){
            $data = [
                'status'=>2
            ];
            Db::name('team_found')->where(array('id' => $found_id))->update($data);
          Db::name('team_follow')->where('found_id', $found_id)->update($data);
        }
        if(!$res){
            return $this->json('', 0, '参团失败');
        }
        return $this->json('', 1, '参团成功');
    }

    /**
     * description:开团详情 （商品共有几个拼团信息）
     * request method GET
     * @param int goods_id 商品id
     * @return Json
     */
    public function teamDetails()
    {
        $goods_id = input('request.goods_id');//商品id

        $list = Db::name("team_activity")->where(['goods_id'=>['eq',$goods_id]])->field('id,team_price,need_num,sku_id')->select();

        $goods = Db::name("goods")->where(['goods_id'=>['eq',$goods_id]])->field('price,vip_price,show_price')->find();

        if(!$list){
            return $this->json('', 0, '暂无开团信息');
        }
        foreach($list as $key=>$val){
            //获取sku价格
            $sku_info= Db::name('goods_sku')->where('sku_id',$val['sku_id'])->find();
            $number = 0;
            $where =[
                'team_id'=>['eq',$val['id']],
                'status'=>['eq',1],//只查询有效团人数
            ];
            $rows = Db::name('team_found')->where($where)->field('joins,status')->select();
            foreach($rows as $value ){
                $number += $value['joins'];
            }
            $list[$key]['joins'] = $number;
            if ($sku_info){
                $list[$key]['price'] =  $sku_info['price'];
            }else{
                $list[$key]['price'] =  $goods['price'];
            }

            $list[$key]['vip_price'] =  $goods['vip_price'];
            if ($sku_info){
                $list[$key]['show_price'] =  $sku_info['show_price'];
            }else{
                $list[$key]['show_price'] =  $goods['show_price'];
            }

            $list[$key]['active_price'] =  $val['team_price'];
        }
        return json(['data'=>$list,'status'=>1,'msg'=>'获取成功']);
    }
    /**
     *  开团信息
     * request method GET
     * @param int id 开团id
     * @return Json
     */
    public function teamInfo()
    {
        $team_id = input('request.id');//拼团id
        $list = Db::name("team_found")->where(['team_id'=>['eq',$team_id],'status'=>1])->select();
        if(!$list){
            return $this->json('', 0, '暂无开团信息');
        }
        $list = Db::name("team_found")->where(['team_id'=>['eq',$team_id],'status'=>1])->select();
        if(!$list){
            return $this->json('', 0, '暂无开团信息');
        }
        $number = 0;
        foreach($list as $key=>$val){
            $res = Db::name('users')->where(['user_id'=>['eq',$val['user_id']]])->field('user_avat')->find();
            $team = Db::name('team_activity')->where(['id'=>['eq',$val['team_id']]])->field('goods_id,goods_id,price_reduce,price_type,team_price')->find();

            $goods = Db::name('goods')->where(['goods_id'=>['eq',$team['goods_id']]])->field('price,vip_price,show_price')->find();

            $number += $val['joins'];
            $list[$key]['user_avat'] = $res['user_avat'];
            $list[$key]['vip_price'] =  $goods['vip_price'];
            $list[$key]['price'] =  $goods['price'];
            //0:减价 1：折扣
            if($team['price_type'] == 0 ){
                $active_price = $goods['price'] - $team['price_reduce'];
            }else{
                $active_price = ($goods['price'] * $team['price_reduce'])/100;
            }
            $list[$key]['start_time'] = date('Y-m-d H:i',$val['start_time']);
            $list[$key]['end_time'] = date('Y-m-d H:i',$val['end_time']) ;
            //减价过大
            if($active_price<0){
                $active_price = 0;
            }
            $list[$key]['active_price'] =  $active_price;//商品活动价格
        }
        return json(['data'=>$list,'status'=>1,'msg'=>'获取成功','number'=>$number]);
    }
}
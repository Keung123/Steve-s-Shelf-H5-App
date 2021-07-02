<?php

namespace app\api\controller;

use app\common\service\Cart as CartService;
use getui\Pushs;
use think\Db;
use think\response\Json;

class Bargain extends Common{
    protected $GoodModel;
    protected $CartModel;
    public function __construct(){
        parent::__construct();
        $this->CartModel=new CartService();
    }
    /**
     *  创建砍价
     * 1、判断 砍价商品是否 已发起砍价-》已发起砍价 则直接 进入砍价详情页面
     * 2、没有发起砍价 -》 创建砍价-》自己先砍
     * request method GET
     * @param int uid
     * @param string token
     * @param int bargain_id 砍价id
     * @return Json
     */
    function openBargain(){
        $user_id = input('request.uid');
        $token = input('request.token');
        $bargain_id = input('request.bargain_id');//砍价表id
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }

        $bargain = Db::name("bargain")->where(['id'=>['eq',$bargain_id],'status'=>['eq',0]])->field('id,join_number,goods_price,goods_name,goods_id,time_limit,end_price,sku_id,goods_number')->find();
        $shen_num=Db::name("active_type")->where(['id'=>4,'status'=>['eq',0]])->find();
        $cart=Db::name("cart")->where(['user_id'=>$user_id,'goods_id'=>$bargain['goods_id']])->find();
        $order=Db::name("order_goods")->where(['og_uid'=>$user_id,'og_goods_id'=>$bargain['goods_id'],'og_acti_id'=>4])->column('og_goods_num');
        $order_num=array_sum($order);
        if($order_num+$cart['num']>=$shen_num['limit_num']){
            return $this->json('',-1, '砍价商品已到购买上限');
        }
        if($order)
            //砍价商品是否存在
            if($bargain['goods_number']<=0){
                return $this->json('',-1, '库存不足');
            }

        $where = [
            'user_id' => $uid,//用户id
            'bargain_id' => $bargain_id,//砍价表id
            'status' => 0
        ];
        //开始
        $res = Db::name("bargain_user")->where($where)->find();
        if($res && $cart){
            return $this->json($res, 1, '已发起过砍价');

        }
        // 如果 砍价表存在 购物车 没有数据 则是 数据异常  修改砍价状态 并且 重新发起砍价
        if ($res && !$cart) {
            Db::name("bargain_user")->where(['id' => $res['id']])->update(['status' => 3]);
        }
        // 砍价 已完成 或 超时自动结束 可以重新 发起砍价
        //已完成
        $ress=Db::name("bargain_user")->where(['user_id'=>$uid,'bargain_id'=>$bargain_id,'status'=>1])->order('id desc')->find();
        //砍价完成
        if($ress){
            //订单表查询
            $order_goods=Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->field('a.og_order_id')->where(['a.og_acti_id'=>4,'a.og_uid'=>$uid,'a.og_goods_id'=>$bargain['goods_id'],'b.order_status'=>['eq',0]])->find();
            if($order_goods){
                return $this->json($order_goods, -2, '订单商品未支付');
            }
//            //购物车表查询
            $cart=Db::name('cart')->where(['prom_id'=>4,'user_id'=>$uid,'goods_id'=>$bargain['goods_id']])->find();
            if($cart){
                $cart_info = Db::name("bargain_user")->where(['id' => $cart['active_id']])->find();
                return $this->json($cart_info, 1, '已发起过砍价');
            }
        }
        $users = Db::name('users')->where(['user_id'=>$uid])->field('user_name,user_avat')->find();

        //头像不能为空
        if(!$users['user_avat']){
            $users['user_avat'] = 0;
        }
        if(!$users['user_name']){
            $users['user_name'] = 0;
        }
        //砍价随机数
        $sku = Db::name('goods_sku')->where(['sku_id'=>$bargain['sku_id']])->find();
        $kan_price = ($sku['price'] - $bargain['end_price']);
        $bargain_price = $this->randMoney($kan_price,$bargain['join_number']);
        $bargain_price =  json_encode($bargain_price);

        // 添加 失败 回滚
        Db::startTrans();
        $data = [
            'user_id' => $uid,
            'bargain_id' => $bargain_id,
            'nick_name' => $users['user_name'],//昵称
            'head_img' => $users['user_avat'],//头像
            'start_time' => time(),//砍价开始时间
            'end_time' => time()+ (24*60*60),//结束时间
            'continue_price' =>  abs($bargain['goods_price'] - $bargain['end_price']),//还需要砍价
            'has_price' =>  0,//已经砍价
            'status' => 0,//0:开始砍价 1:完成 2：结束砍价
            'share_number' => 0,//分享人数
            'bargain_price' =>$bargain_price,//砍价随机数组

        ];
        $res = Db::name('bargain_user')->insert($data);
        if(!$res){
            Db::rollback();
            return $this->json('', 0, '开砍失败');
        }
        $id = Db::name('bargain_user')->getLastInsID();
        //加入购物车
        $result = $this->CartModel->addData($bargain['sku_id'],$bargain['goods_id'], $user_id, $num=1, $type=4,0,$id);
        if(!$result){
            Db::rollback();
            return $this->json('', -1, '开砍加购物车失败');
        }
        if ($res && $result) {
            Db::commit();
        }
        //自己先砍 一刀
        $this->joinkanjiia($uid,$id,$bargain_id);
        $info = Db::name('bargain_user')->where(['id' => $id])->find();
        return $this->json($info, 1, '开砍成功');
    }

    /**
     *  砍价随机数
     */
    public function randMoney($sum,$count)
    {
        $arr = [];
        $hes = 0;
        $hess =0;
        for ($i=0;$i<$count;$i++){
            $rand =rand(1,1000);
            $arr[]=$rand;
            $hes+=$rand;
        }
        $arr2 =[];
        foreach ($arr as $key=>$value){
            $round = round(($value/$hes)*$sum,2);
            $arr2[] =$round;
            $hess+=$round;
        }
        if($sum !=round($hess,2)){
            $hesss =round($sum-$hess,2);
            $arr2[0]=$arr2[0]+$hesss;
        }
        return $arr2;
    }

    /**
     *  先砍一刀
     */
    public function joinkanjiia($uid,$bargain_user_id,$bargain_id)
    {
        $where = [
            'user_id' => $uid,//用户id
            'bargain_user_id' => $bargain_user_id,//开砍价表id
        ];
        //用户表
        $users = Db::name('users')->where(['user_id'=>$uid])->field('user_name,user_avat')->find();
        //开砍表
        $bargain_user = Db::name('bargain_user')->where(['id'=>$bargain_user_id, 'bargain_id' => $bargain_id])
            ->field('end_time,has_price,continue_price,share_number,share_money_cutprice,order_id,status,bargain_price')
            ->order('id desc')
            ->find();
        //砍价表
        $result = Db::name('bargain')->where(['id'=>$bargain_id])->field('join_number,goods_id')->find();
        $number = $result['join_number'];//总人数
        //帮好友砍掉的价格
        $bargain_price =  json_decode($bargain_user ['bargain_price']);
        $dis_price = array_shift($bargain_price);
        $bargain_price =  json_encode($bargain_price);
        Db::name('bargain_user')->where(['id'=>$bargain_user_id])->update(array('bargain_price'=>$bargain_price));
        $share_number = $bargain_user['share_number'];//已经砍人数
        $num  =  $number - $share_number;//剩余人数
        $money  = $bargain_user['continue_price'];//还需要 砍 钱数
        if(!$users['user_avat']){
            $users['user_avat'] = '';
        }
        if(!$users['user_name']){
            $users['user_name'] = '';
        }
        $data = [
            'user_id' => $uid,
            'bargain_id' => $bargain_id,//砍价表id
            'bargain_user_id' => $bargain_user_id,//开砍价表id
            'nick_name' => $users['user_name'],//昵称
            'head_img' => $users['user_avat'],//头像
            'join_time' => time(),//参加时间
            'dis_price' => $dis_price,//帮好友砍掉的价格
            'status' => 1,//1：成功 0：失败
        ];

        if($bargain_user['continue_price']<$data['dis_price']){
            $has_price = $bargain_user['continue_price'];
        }else{
            $has_price = $data['dis_price'];
        }
        $updata = [
            'has_price' => $bargain_user['has_price'] + $has_price,
            'continue_price' => $bargain_user['continue_price'] - $has_price,
            'share_number'=> $bargain_user['share_number'] + 1,
        ];
        //超时
        if($bargain_user['end_time'] < time()){
            $updata ['status']  = 2;
        }
        //价格砍完 人数到了
        if(($updata['continue_price'] <= 0)||($updata['share_number']>=$number)){
            $updata ['status'] = 1;
        }
        $res = Db::name('bargain_user')->where(['id'=>$bargain_user_id])->update($updata);
        if($res){
            $res = Db::name('bargain_follow')->insert($data);
        }
        //规则属性表
        $sku = Db::name('goods_sku')->where(['sku_id'=>$result['sku_id']])->field('sku_name')->find();
        //修改购物车 商品价格
        $where = [
            'prom_id'=>4,
            'user_id'=>$uid,
            'goods_id'=>$result['goods_id'],
        ];
        Db::name('cart')->where($where)->setDec('price',$dis_price);
    }

    /**
     *  好友帮砍
     * request method GET
     * @param int uid
     * @param string token
     * @param int id
     * @param int bargain_id 砍价id
     * @return Json
     */
    public function joinBargain(){
        $user_id = input('request.uid');
        $token = input('request.token');
        $bargain_user_id = input('request.id');//开砍价表id
        $bargain_id = input('request.bargain_id');//砍价表id
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $bargain_user_info = Db::name("bargain_user")->where(['id'=>$bargain_user_id])->find();
        if (empty($bargain_user_info)) {
            return $this->json('', -2, '砍价不存在');
        }

        if($bargain_user_info['status'] == 1 || $bargain_user_info['status'] == 2){
            return $this->json('', -2, '砍价已完成');
        }
        $where = [
            'user_id' => $uid,//用户id
            'bargain_user_id' => $bargain_user_id,//开砍价表id
        ];

        $res = Db::name("bargain_follow")->where($where)->find();
        if($res){
            return $this->json('', -1, '已经砍过了');
        }
        //用户表
        $users = Db::name('users')->where(['user_id'=>$uid])->field('user_name,user_avat')->find();
        //开砍表
        $bargain_user = Db::name('bargain_user')->where(['id'=>$bargain_user_id])->field('end_time,has_price,continue_price,share_number,share_money_cutprice,order_id,status,bargain_price,user_id')->find();
        //砍价表
        $result = Db::name('bargain')->where(['id'=>$bargain_id])->field('join_number,goods_id,sku_id')->find();
        $number = $result['join_number'];//总人数
        //帮好友砍掉的价格
        $bargain_price =  json_decode($bargain_user ['bargain_price']);
        $dis_price = array_shift($bargain_price);
        $bargain_price =  json_encode($bargain_price);
        Db::name('bargain_user')->where(['id'=>$bargain_user_id])->update(array('bargain_price'=>$bargain_price));

        if(!$bargain_user){
            return $this->json('', 0, '未知参数');
        }

        if($bargain_user['share_number']>=$number){
            return $this->json('', -2, '人数已经达标不能砍价');
        }

        $share_number = $bargain_user['share_number'];//已经砍人数
        $num  =  $number - $share_number;//剩余人数

        $money  = $bargain_user['continue_price'];//还需要 砍 钱数
        if(!$users['user_avat']){
            $users['user_avat'] = '';
        }
        if(!$users['user_name']){
            $users['user_name'] = '';
        }
        $data = [
            'user_id' => $uid,
            'bargain_id' => $bargain_id,//砍价表id
            'bargain_user_id' => $bargain_user_id,//开砍价表id
            'nick_name' => $users['user_name'],//昵称
            'head_img' => $users['user_avat'],//头像
            'join_time' => time(),//参加时间
            'dis_price' => $dis_price,//帮好友砍掉的价格
            'status' => 1,//1：成功 0：失败
        ];

        if($bargain_user['continue_price']<$data['dis_price']){
            $has_price = $bargain_user['continue_price'];
        }else{
            $has_price = $data['dis_price'];
        }
        $updata = [
            'has_price' => $bargain_user['has_price'] + $has_price,
            'continue_price' => $bargain_user['continue_price'] - $has_price,
            'share_number'=> $bargain_user['share_number'] + 1,
        ];
        //超时
        if($bargain_user['end_time'] < time()){
            $updata ['status']  = 2;
        }
        //价格砍完 人数到了
        if(($updata['continue_price'] <= 0)||($updata['share_number']>=$number)){
            $updata ['status'] = 1;
        }

        $res = Db::name('bargain_user')->where(['id'=>$bargain_user_id])->update($updata);
        if($res){
            $res = Db::name('bargain_follow')->insert($data);
        }
        //规则属性表
        $sku = Db::name('goods_sku')->where(['sku_id'=>$result['sku_id']])->field('sku_name,price')->find();
        //修改购物车 商品价格
        $where = [
            'prom_id'=>4,
            'user_id'=>$bargain_user['user_id'],
            'goods_id'=>$result['goods_id'],
        ];
        Db::name('cart')->where($where)->setDec('price',$dis_price);
        if(!$res){
            return $this->json('', 0, '砍价失败');
        } else {
            $usersInfo = Db::name('users')->where('user_id',$bargain_user_info['user_id'])->field('client_id,app_system')->find();
            if($usersInfo){
                if ($bargain_user_info['user_id'] != $uid) {
                    $msg = [
                        'content'=>$users['user_name'].' 已帮您砍价',//透传内容
                        'title'=>'砍价通知',//通知栏标题
                        'text'=>$users['user_name'].' 已帮您砍价',//通知栏内容
                    ];
                    $clientids=array(
                        ['client_id'=>$usersInfo['client_id']],
                        'system'=>$usersInfo['app_system'],
                    );
                    $Pushs = new Pushs();
                    $Pushs->getTypes($msg,$clientids);
                }
                // 砍价 完成 发送通知
                if ($updata ['status'] == 1) {
                    $msg = [
                        'content'=>'砍价已完成，请尽快购买',//透传内容
                        'title'=>'砍价通知',//通知栏标题
                        'text'=>' 砍价已完成，请尽快购买',//通知栏内容
                    ];
                    $clientids=array(
                        ['client_id'=>$usersInfo['client_id']],
                        'system'=>$usersInfo['app_system'],
                    );
                    $Pushs = new Pushs();
                    $Pushs->getTypes($msg,$clientids);
                }
            }

            return $this->json('', 1, '砍价成功');
        }
    }
    /**
     *  砍价详情
     * request method GET
     * @param int uid
     * @param string token
     * @param int bargain_id
     * @return Json
     */
    public function bargainInfo(){
//		$user_id = input('request.uid');
//		$token = input('request.token');
        $bargain_id = input('bargain_id');//砍价表id
        //开砍表
        $bargain_user = Db::name('bargain_user')->where(['id'=>$bargain_id, 'status' => ['in', '0,1']])->find();
        if(!$bargain_user){
            return $this->json('', 0, '该次砍价已失效，请重新发起砍价');
        }
        //砍价表
        $bargain = Db::name('bargain')->where(['id'=>$bargain_user['bargain_id'],'status'=>0])->find();

        //商品表
        $goods = Db::name('goods')->where(['goods_id'=>$bargain['goods_id']])->field('price,vip_price,show_price,picture,goods_name')->find();
        //规则属性表
        $sku = Db::name('goods_sku')->where(['sku_id'=>$bargain['sku_id']])->field('sku_name')->find();
        //用户表
        $users = Db::name('users')->where(['user_id'=>$bargain_user['user_id']])->field('user_name,user_avat')->find();
        //帮砍价表
        $where = [
            'bargain_user_id'=>$bargain_user['id'],
            'bargain_id'=>$bargain_user['bargain_id'],
            'status'=>1,
        ];
        $list = Db::name('bargain_follow')->where($where)->field('user_id,join_time,nick_name,head_img,dis_price')->order('id desc')->select();

        if($list){
            foreach($list as $key=>$val){

                $list[$key]['join_time'] = date('Y-m-d H:i:s',$val['join_time']);
            }
        }
        // 查询购物车id
        $cart_where  = [
            'goods_id' => $bargain['goods_id'],
            'user_id' => $bargain_user['user_id'],
            'active_id' => $bargain_user['id']
        ];
        $cart_id = Db::name('cart')->where($cart_where)->find();
        if(!$cart_id){
            return $this->json('', 0, '该次砍价已失效，请重新发起砍价');
        }
        $data =[
            'user_name' => $users['user_name'],//用户名
            'user_avat' => $users['user_avat'],//用户头像
            // 'goods_price' => $bargain['goods_price'],//原价
            'price' => $bargain['goods_price'],//原价
            'show_price' => $goods['show_price'],//展示价格
            'vip_price' => $goods['vip_price'],//vip价格
            'end_price' => $bargain['end_price'],//最低砍价
            'has_price' => $bargain_user['has_price'],//已经砍价
            'now_price' => ($bargain['goods_price']-$bargain_user['has_price']),//现价
            'goods_name' => $goods['goods_name'],//名称
            'picture' => $goods['picture'],//图片
            'goods_id' => $bargain['goods_id'],
            'cart_id' => $cart_id['id'],
            'sku_id' => $bargain['sku_id'],
            'continue_price' =>  (abs($bargain['goods_price'] - $bargain['end_price'])-$bargain_user['has_price']),//还需砍价
            'sku_name' => $sku['sku_name'],//规格
            'bargain_id' => $bargain_user['bargain_id'],//砍价表 id
            'id' => $bargain_user['id'],//用户开砍 id
            'list' => $list,//帮砍好友列表
        ];
        return $this->json($data);
    }
}
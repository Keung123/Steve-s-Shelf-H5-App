<?php


namespace app\admin\controller;


use app\common\service\Config;
use app\common\service\Goods;
use app\common\service\Order as OrderService;
use app\common\service\User;
use getui\Pushs;
use app\common\service\Delivery as DeliveryService;
use think\Db;
use think\Request;

class Delivery extends Base
{
    protected $delvery;

    public function __construct(DeliveryService $delivery)
    {
        parent::__construct();
        $this->delivery = $delivery;
    }

    /**
     * 可配送订单列表
     */
    public function index()
    {
        $OrderService=new OrderService();
        //超过7天自动收货
        $time=7*24*3600;
        $maps['order_create_time']=['<',time()-$time];
        $maps['order_status']=2;
        //3，待评价；4，已完成
        //$OrderService->save($maps,['order_status'=>4]);
        $OrderInfo = $OrderService->select($maps);

        if($OrderInfo){
            $os = new OrderService();
            foreach ($OrderInfo as $v){
                $os->postConfirm($v['order_uid'], $v['order_id']);
            }
        }

        $order_no = trim(input('order_no'));
        $order_status = input('order_status');
        $og_goods_name = input('og_goods_name');
        $phone = input('phone');
        $start_time = input('start_time');
        $end_time = input('end_time');

        $this->assign('order_no',$order_no);
        $this->assign('og_goods_name',$og_goods_name);
        $this->assign('phone',$phone);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        if(request()->isAjax()){
            $map=[];
            //排序
            $order="og_id desc";
            //limit
            $limit=input('get.offset').",".input('get.limit');

            //搜索
            if(input('get.search')){
                $map['order_no']=['eq',input('get.search')];
            }
            if(input('order_no')){
                $map['order_no'] = ['eq',trim(input('order_no'))];
            }
            if(input('phone')){
                $map['phone'] = ['eq',trim(input('phone'))];
            }
            if(input('start_time')){
                $start_time = str_replace('+',' ',input('start_time'));
            }
            if(input('end_time')){
                $end_time = str_replace('+',' ',input('end_time'));
            }
            $map_status = 1;
            if($map_status){
                switch($map_status){
                    case 1 : $map['order_status'] = ['eq', 1]; break;
                    case 2 : $map['order_status'] = ['eq', 2]; break;
                    case 3 : $map['order_status'] = ['eq', 4]; break;
                    case 4 : $map['order_status'] = ['eq', 5]; break;
                }
            }
            if($map_status == 1){
                $map['a.og_order_status'] = 1;
            }else if($map_status == 2){
                $map['a.og_order_status'] = 2;
            }

            $this->assign('order_status', $map_status);
            if ($start_time && $end_time) {
                $map['order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
            } elseif ($start_time) {
                $map['order_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['order_create_time'] = array('<=', strtotime($end_time));
            }
            //订单状态
            if($order_status){
                $map['a.og_order_status']=$order_status;
                if($order_status == '11269'){
                    $map['a.og_order_status'] = ['eq', 0];
                }
            }
            if($order_status==1){
                $og_ids = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where(['a.og_order_status'=>1])->column('a.og_id');
                //查一下订单中是否有已完成售后
                if($og_ids){
                    $sh_og_ids = Db::name('sh_info')->where(['og_id' => ['in',$og_ids],'audit_status'=>['>',4]])->column('og_id');
                    if($sh_og_ids){
                        $og_ids = array_diff($og_ids, $sh_og_ids);
                    }
                    $map['a.og_id']=['in',$og_ids];
                }
            }
            //找出登录会员所属的组
            $admin_id = session('admin_id');
            $group_id = Db::name('admin')->where('admin_id',$admin_id)->value('group_id');
            if ($group_id==16){//销售人员
                $map['b.sender_id'] = $admin_id;
            }elseif($group_id ==17){ //配送人员
                $map['b.post_id'] = $admin_id;
            }
            list($rows,$total)  = $OrderService->getOrderinfos($map,$order,$limit);
            $user_model = new User();
            $goods_model = new Goods();
            // 状态0，待付款；1，待发货；2，待收货；3，待评价；4，已完成；5，已取消；6，申请退货；7，申请换货；'
            $status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货');
            if ($rows) {
                $page_price = 0;
                foreach ($rows as $key=>$val) {
                    // 判断该商品是否为 售后商品
                    $sh_info = Db::name('sh_info')->where(['og_id' => $val['og_id'], 'supplier_status' => ['eq', 2]])->find();
                    if ($sh_info) {
                        $rows[$key]['sh_status'] = 1;
                    } else {
                        $rows[$key]['sh_status'] = 0;
                    }
                    $rows[$key]['addr_phone'] = $val['phone'];
                    $rows[$key]['addr_receiver'] = $val['consigee'];

                    $rows[$key]['order_create_time'] = date('Y-m-d H:i:s', $val['order_create_time']);
                    $rows[$key]['order_type']  =  $val['order_type'] == 0? '普通订单':'积分兑换订单';
                    $rows[$key]['pick_status']  =  $val['pick_status'] == 0? '否':'是';
                    $goods_list = $OrderService->getOrderGoodsinfo($val['og_id']);
                    $goods_name_array =  $goods_list['og_goods_name'];
                    $supplier_str = $goods_list['og_supplier_id'];
                    $supplier_name = $OrderService->getSupplierName($supplier_str);

                    $rows[$key]['goods_name'] = implode(',',$goods_name_array);
                    $rows[$key]['supplier_name'] = implode(',',$supplier_name);
                    $page_price += $val['order_pay_price'];
                    $rows[$key]['page_price'] = round($page_price,2);
                    // 总计销售额
                    $total_price = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where($map)->order($order)->limit($limit)->field('b.*,a.*')->sum('order_pay_price');
                    $rows[$key]['total_price'] = round($total_price,2);
                    //订单商品 发货 订单未发货
                    if(($val['order_status'] == 1)&&($val['og_order_status']==2)){
                        $rows[$key]['order_status'] = 2;
                        $val['order_status'] = 2;
                    }
                    //是否返现处理
                    if ($val['is_cash'] == 0) {
                        $rows[$key]['is_cash'] = '否';
                    } else {
                        $rows[$key]['is_cash'] = '是';
                    }
                    $rows[$key]['status_names'] =$status_arr[$val['og_order_status']];
                }

            }

            return json(['total'=>$total,'rows'=>$rows]);
        }else{
            $this->assign('order_status',$order_status);
            return $this->fetch();
        }
    }

    /**
     * 发货
     */
    public function post()
    {
        $OrderService=new OrderService();
        if(request()->isPost()){
            $map1['order_id']=input('post.order_id');

            if(input('post.og_supplier_id')){
                $map2['og_supplier_id']=input('post.og_supplier_id');
            }
            $map2['og_order_id']=input('post.order_id');
            $map2['og_id']=input('post.og_id');

            $order_good =[
                'post_type'=>input('post.post_type'),
                'og_order_status'=>2,
                'og_delivery_time'=>time(),
                'order_goods_ok_time'=>time(),
            ];
            $res= Db::name('order_goods')->where($map2)->update($order_good);

            $where =[
                'og_order_status'=>['neq',2],
                'og_order_id'=>input('post.order_id'),
            ];

            $row = Db::name('order_goods')->where('og_order_id',input('post.order_id'))->select();
            $result=1;
            if($row){
                foreach( $row as $val){
                    if ($val['og_id'] == $map2['og_id']) {
                        continue;
                    }
                    $sh_info = Db::name('sh_info')->where(['og_id' => $val['og_id']])->find();
                    if ($sh_info) {
                        if ($sh_info['status'] == 3 || $sh_info['supplier_status'] == 3 || $sh_info['financial_status'] == 3) {
                            if($val['og_order_status'] ==1){
                                $result	= 0;
                            }
                        }
                    } else {
                        if($val['og_order_status'] ==1){
                            $result	= 0;
                        }
                    }

                }
            }
            if($result){
                $datas['order_status']=2;
                $datas['post_status']=2;
                $datas['post_type']=input('post.post_type');
                $datas['delivery_time'] = time();
                $datas['order_finish_time']=time();
                $res=$OrderService->save($map1,$datas);
            }

            //日志记录
            $add['uid'] = session('admin_id');
            $add['ip_address'] = request()->ip();
            $add['controller'] = request()->controller();
            $add['action'] = request()->action();
            $add['remarks'] = '发货';
            $add['number'] = input('post.order_id');
            $add['create_at'] = time();
            db('web_log')->insert($add);

            //配送人员记录统计
            $data['user_id'] = session('admin_id');
            $data['order_id'] = $map1['order_id'];
            $data['remark'] = input('request.post_no');
            $data['createtime'] = $_SERVER['REQUEST_TIME'];
            $res3 = Db::name('delivery')->insert($data);
            if ($res && $res3)
            //发货通知
            if($res){
                $order_goods  = Db::name('order_goods')->where($map2)->field('og_goods_name,og_uid')->find();
                if($order_goods){
                    $usersInfo = Db::name('users')->where('user_id',$order_goods['og_uid'])->field('client_id,app_system')->find();
                    if($usersInfo){
                        $msg = [
                            'content'=>$order_goods['og_goods_name'].'已经发货!',//透传内容
                            'title'=>'发货提醒',//通知栏标题
                            'text'=>$order_goods['og_goods_name'].'已经发货!',//通知栏内容
                        ];
                        $clientids=array(
                            ['client_id'=>$usersInfo['client_id']],
                            'system'=>$usersInfo['app_system'],
                        );
                        $Pushs = new Pushs();
                        $Pushs->getTypes($msg,$clientids);
                    }

                }
            }
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $where['og_order_id']=input('get.order_id');
            $where['og_supplier_id']=input('get.og_supplier_id');
            $where['og_id']=input('get.og_id');

            $orderInfo = Db::name('order_goods')->where($where)->field('og_order_id,og_supplier_id')->find();

            //获取订单详情
            $map['order_id'] = $orderInfo['og_order_id'];
            $row=$OrderService->find($map);

            $user_model = new User();
            $status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货');
            $addr_info = $user_model->address($row['pro_name'],$row['city_name'],$row['area']);
            $row['addr_phone'] = $row['phone'];
            $row['og_id'] = $where['og_id'];


            $row['status_names'] =$status_arr[$row['order_status']];
            $row['create_time'] = date('Y-m-d H:i:s', $row['order_create_time']);
            $goods_list =$OrderService->getOrderGoods($row['order_id']);

            $row['goods_list'] =  $goods_list;
            $this->assign('row',$row);

            //获取配送方式
            $ConfigService=new Config();
            $config=$ConfigService->find();
            $postType=$config['shop']['postType'];
            $express=$config['express'];
            $express = json_decode($config['express'], true);
            $express =$express['express'];

            $this->assign('og_supplier_id',$orderInfo['og_supplier_id']);
            $this->assign('postType',$postType);
            $this->assign('express',$express);
            return $this->fetch();
        }

    }

    /**
     * 配送业绩统计
     */
    public function collect()
    {
        $user_name = input('user_name');
        $start_time = input('start_time');
        $end_time = input('end_time');

        $this->assign('user_name',$user_name);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);

        if (Request()->isAjax()){
            if(input('user_name')){
                $user_name = trim(input('user_name'));
            }
            if(input('start_time')){
                $start_time = str_replace('+',' ',input('start_time'));
            }
            if(input('end_time')){
                $end_time = str_replace('+',' ',input('end_time'));
            }
            $where = [
                'user_name' => $user_name,
                'start_time' => $start_time,
                'end_time' => $end_time,
            ];
            $res = $this->delivery->collect($where);
            return json($res);
        }
        return $this->fetch();
    }
    /**
     * 可配送订单列表
     */
    public function saleslist()
    {
        $OrderService=new OrderService();
        //超过7天自动收货
        $time=7*24*3600;
        $maps['order_create_time']=['<',time()-$time];
        $maps['order_status']=2;
        //3，待评价；4，已完成
        //$OrderService->save($maps,['order_status'=>4]);
        $OrderInfo = $OrderService->select($maps);

        if($OrderInfo){
            $os = new OrderService();
            foreach ($OrderInfo as $v){
                $os->postConfirm($v['order_uid'], $v['order_id']);
            }
        }

        $order_no = trim(input('order_no'));
        $order_status = input('order_status');
        $og_goods_name = input('og_goods_name');
        $phone = input('phone');
        $start_time = input('start_time');
        $end_time = input('end_time');
        $post_id = input('user_id');
        $this->assign('order_no',$order_no);
        $this->assign('og_goods_name',$og_goods_name);
        $this->assign('phone',$phone);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('post_id', $post_id);
        if(request()->isAjax()){
            $map=[];
            //排序
            $order="og_id desc";
            //limit
            $limit=input('get.offset').",".input('get.limit');

            //搜索
            if(input('get.search')){
                $map['order_no']=['eq',input('get.search')];
            }
            if(input('order_no')){
                $map['order_no'] = ['eq',trim(input('order_no'))];
            }
            if(input('phone')){
                $map['phone'] = ['eq',trim(input('phone'))];
            }
            if(input('start_time')){
                $start_time = str_replace('+',' ',input('start_time'));
            }
            if(input('end_time')){
                $end_time = str_replace('+',' ',input('end_time'));
            }
            $map_status = 1;
//            if($map_status){
//                switch($map_status){
//                    case 1 : $map['order_status'] = ['eq', 1]; break;
//                    case 2 : $map['order_status'] = ['eq', 2]; break;
//                    case 3 : $map['order_status'] = ['eq', 4]; break;
//                    case 4 : $map['order_status'] = ['eq', 5]; break;
//                }
//            }
//            if($map_status == 1){
//                $map['a.og_order_status'] = 1;
//            }else if($map_status == 2){
//                $map['a.og_order_status'] = 2;
//            }

            $this->assign('order_status', $map_status);
            if ($start_time && $end_time) {
                $map['order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
            } elseif ($start_time) {
                $map['order_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['order_create_time'] = array('<=', strtotime($end_time));
            }
            //订单状态
//            if($order_status){
//                $map['a.og_order_status']=$order_status;
//                if($order_status == '11269'){
//                    $map['a.og_order_status'] = ['eq', 0];
//                }
//            }
//            if($order_status==1){
//                $og_ids = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where(['a.og_order_status'=>1])->column('a.og_id');
//                //查一下订单中是否有已完成售后
//                if($og_ids){
//                    $sh_og_ids = Db::name('sh_info')->where(['og_id' => ['in',$og_ids],'audit_status'=>['>',4]])->column('og_id');
//                    if($sh_og_ids){
//                        $og_ids = array_diff($og_ids, $sh_og_ids);
//                    }
//                    $map['a.og_id']=['in',$og_ids];
//                }
//            }
            //找出登录会员所属的组
            $admin_id = session('admin_id');
            $group_id = Db::name('admin')->where('admin_id',$admin_id)->value('group_id');
            $map['b.post_id'] = $post_id;

            list($rows,$total)  = $OrderService->getOrderinfos($map,$order,$limit);
            $user_model = new User();
            $goods_model = new Goods();
            // 状态0，待付款；1，待发货；2，待收货；3，待评价；4，已完成；5，已取消；6，申请退货；7，申请换货；'
            $status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货');
            if ($rows) {
                $page_price = 0;
                foreach ($rows as $key=>$val) {
                    // 判断该商品是否为 售后商品
                    $sh_info = Db::name('sh_info')->where(['og_id' => $val['og_id'], 'supplier_status' => ['eq', 2]])->find();
                    if ($sh_info) {
                        $rows[$key]['sh_status'] = 1;
                    } else {
                        $rows[$key]['sh_status'] = 0;
                    }
                    $rows[$key]['addr_phone'] = $val['phone'];
                    $rows[$key]['addr_receiver'] = $val['consigee'];

                    $rows[$key]['order_create_time'] = date('Y-m-d H:i:s', $val['order_create_time']);
                    $rows[$key]['order_type']  =  $val['order_type'] == 0? '普通订单':'积分兑换订单';
                    $rows[$key]['pick_status']  =  $val['pick_status'] == 0? '否':'是';
                    $goods_list = $OrderService->getOrderGoodsinfo($val['og_id']);
                    $goods_name_array =  $goods_list['og_goods_name'];
                    $supplier_str = $goods_list['og_supplier_id'];
                    $supplier_name = $OrderService->getSupplierName($supplier_str);

                    $rows[$key]['goods_name'] = implode(',',$goods_name_array);
                    $rows[$key]['supplier_name'] = implode(',',$supplier_name);
                    $page_price += $val['order_pay_price'];
                    $rows[$key]['page_price'] = round($page_price,2);
                    // 总计销售额
                    $total_price = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where($map)->order($order)->limit($limit)->field('b.*,a.*')->sum('order_pay_price');
                    $rows[$key]['total_price'] = round($total_price,2);
                    //订单商品 发货 订单未发货
                    if(($val['order_status'] == 1)&&($val['og_order_status']==2)){
                        $rows[$key]['order_status'] = 2;
                        $val['order_status'] = 2;
                    }
                    //是否返现处理
                    if ($val['is_cash'] == 0) {
                        $rows[$key]['is_cash'] = '否';
                    } else {
                        $rows[$key]['is_cash'] = '是';
                    }
                    $rows[$key]['status_names'] =$status_arr[$val['og_order_status']];
                }

            }

            return json(['total'=>$total,'rows'=>$rows]);
        }else{
            $this->assign('order_status',$order_status);
            return $this->fetch();
        }
    }
}
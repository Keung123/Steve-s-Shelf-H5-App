<?php


namespace app\admin\controller;

use app\common\service\Goods;
use app\common\service\Order as OrderService;
use app\common\service\User;
use think\Db;
use app\common\service\ReturnCash;
use think\Request;

class Sales extends Base
{
    protected $cash;

    public function __construct(ReturnCash $cash)
    {
        parent::__construct();
        $this->cash = $cash;
    }

    /**
     * 销售管理订单列表
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
            $map_status = input('request.order_status');
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
            $map['b.order_status'] = ['not in','0,5,6'];

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
     * 操作返现
     */
    public function editCash()
    {
        if (Request()->isAjax()) {
            $row = input('post.row/a');
            $res = $this->cash->handleCash($row);
            return AjaxReturn($res,getErrorInfo($res));
        } else {
            $order_id=input('get.order_id');
            $this->assign('order_id',$order_id);
            return $this->fetch();
        }
    }

    /**
     * 操作返现详情
     */
    public function getDetails()
    {
        $order_id = input('get.order_id');
        $data = $this->cash->getCashDetails($order_id);
        $this->assign('row',$data);
        return $this->fetch();
    }

    /**
     * 业绩统计
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
            $res = $this->cash->collect($where);
            return json($res);
        }
        return $this->fetch();
    }

    /**
     * 查看对应配送员下的订单列表
     */
    public function saleslist(){
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
        $sender_id = input('user_id');
        $this->assign('order_no',$order_no);
        $this->assign('og_goods_name',$og_goods_name);
        $this->assign('phone',$phone);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('sender_id', $sender_id);
        if(request()->isAjax()){
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
            $map_status = input('request.order_status');
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
                $map['b.order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
            } elseif ($start_time) {
                $map['b.order_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['b.order_create_time'] = array('<=', strtotime($end_time));
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
            $map['b.order_status'] = ['not in','0,5,6'];

            //找出登录会员所属的组
            $admin_id = session('admin_id');
            $group_id = Db::name('admin')->where('admin_id',$admin_id)->value('group_id');
            $map['b.sender_id'] = $sender_id;

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
<?php
namespace app\admin\controller;

use app\common\model\Store as StoreModel;
use app\common\service\User as UserService;
use app\common\service\Store as StoreService;
use think\Db;

use Qiniu\Storage\ResumeUploader;
use think\config;
//引入七牛云的相关文件
use Qiniu\Auth as Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Qiniu\Config as qiniuConfig;


class Store extends Base{

	public function _initialize(){
		parent::_initialize();
		$store_model = new StoreModel();
		$this->model = $store_model;
		$store_service = new StoreService();
		$this->service = $store_service;
	}

	/*
	 * 店铺管理首页
	 */
	public function index(){
		$s_name = trim(input('s_name'));
		$start_time = trim(input('start_time'));
		$end_time = trim(input('end_time'));
		$user_mobile = trim(input('user_mobile'));
		$s_grade = trim(input('s_grade'));

		$this->assign('s_name',$s_name);
		$this->assign('start_time',$start_time);
		$this->assign('end_time',$end_time);
		$this->assign('user_mobile',$user_mobile);
		$this->assign('s_grade',$s_grade);
		if(request()->isAjax()){
            $start_time = input('start_time');
            $end_time = input('end_time');
            $s_grade =  input('s_grade');
			if(input('s_name')){
				$map['a.s_name']  = ['like','%'.input('s_name').'%'];	
			}
            if(input('start_time')){
                $start_time=str_replace('+',' ',input('start_time'));
            }
            if(input('end_time')){
                $end_time=str_replace('+',' ',input('end_time'));
            }
			if($start_time && $end_time){
			    $map['a.s_comm_time'] = ['between',strtotime($start_time).','.strtotime($end_time)];
            }elseif($start_time){
			    $map['a.s_comm_time'] = ['>=',strtotime($start_time)];
            }elseif($end_time){
                $map['a.s_comm_time'] = ['<=',strtotime($end_time)];
            }
            if(input('user_mobile')){
                $map['b.user_mobile'] = ['eq',$user_mobile];
            }
            if($s_grade==null || $s_grade=='all'){
                $map['a.s_grade'] = ['neq',0];
            }elseif($s_grade==1){
                $map['a.s_grade'] = ['eq',$s_grade];
            }elseif($s_grade==2){
                $map['a.s_grade'] = ['eq',$s_grade];
            }elseif($s_grade==3){
                $map['a.s_grade'] = ['eq',$s_grade];
            }
            $limit=input('get.offset').",".input('get.limit');
			$store_list = $this->model->alias('a')->join('__USERS__ b', 'a.s_uid=b.user_id')->field('a.s_id,a.s_uid,a.s_name,a.s_comm_time,a.s_grade,b.user_name,b.user_mobile,b.s_invite_code as invite_code')->order('a.s_comm_time desc')->where($map)->limit($limit)->select();
			/* print_r($this->model->getLastsql());
			return;  */  
			
			$total =  $this->model->alias('a')->join('__USERS__ b', 'a.s_uid=b.user_id')->where($map)->count();
			
            $key_1 = $key_2 = [];
			foreach($store_list as &$v){
				$v['s_comm_time'] = $v['s_comm_time'] ? date('Y-m-d', $v['s_comm_time']) : '';
				// $s_id = $v['s_id'];
                $store_info = $this->model->where('s_id', $v['s_id'])->field('s_uid')->find();
                // 店铺销售额
                $v['saleroom'] = $this->service->getStoreSaleRoom($store_info['s_uid'])['total'];
			
                // 店铺总收入
                $v['store_total'] = $this->service->getStoreTotal($store_info['s_uid'])['total'];
                // $info = $this->service->saleRoom($store_info['s_uid'], 1, '');
                // $v['saleroom'] = $info['data']['saleroom'];
				$v['s_grade'] = ($v['s_grade'] == 1 ? '会员店' : ($v['s_grade'] == 2 ? '高级店' : '旗舰店'));
                $key_1[] = $v['saleroom'];
                $key_2[] = $v['store_total'];
			}

            if(input('sort') == 'saleroom' && input('order') == 'asc'){
                array_multisort($key_1, SORT_ASC, SORT_NUMERIC, $store_list);
            }
            if(input('sort') == 'saleroom' && input('order') == 'desc'){
                array_multisort($key_1, SORT_DESC, SORT_NUMERIC, $store_list);                
            }            

            if(input('sort') == 'store_total' && input('order') == 'asc'){
                array_multisort($key_2, SORT_ASC, SORT_NUMERIC, $store_list);                
            }
            if(input('sort') == 'store_total' && input('order') == 'desc'){
                array_multisort($key_2, SORT_DESC, SORT_NUMERIC, $store_list);                
            }
			// $order = 'rc_id desc';
			// $map['rc_title'] = ['like','%'.input('get.search').'%'];
		/* 	$total = $this->model->count();
			$total = $this->storeTotal($map,input('sort'),input('order')); */
		 
			return json(['total' => $total, 'rows' => $store_list]);
		}
		else{
			return $this->fetch();
		}
	}
	public function storeTotal($map,$sort,$order){
		$store_list = $this->model->alias('a')->join('__USERS__ b', 'a.s_uid=b.user_id')->field('a.s_id,a.s_uid,a.s_name,a.s_comm_time,a.s_grade,b.user_name,b.user_mobile,b.s_invite_code as invite_code')->order(
		'a.s_comm_time desc')->where($map)->select();
            $key_1 = $key_2 = [];
			foreach($store_list as &$v){
				$v['s_comm_time'] = $v['s_comm_time'] ? date('Y-m-d', $v['s_comm_time']) : '';
				$s_id = $v['s_id'];
                $store_info = $this->model->where('s_id', $s_id)->field('s_uid')->find();
                // 店铺销售额
                $v['saleroom'] = $this->service->getStoreSaleRoom($store_info['s_uid'])['total'];
                // 店铺总收入
                $v['store_total'] = $this->service->getStoreTotal($store_info['s_uid'])['total'];
                // $info = $this->service->saleRoom($store_info['s_uid'], 1, '');
                // $v['saleroom'] = $info['data']['saleroom'];
				$v['s_grade'] = ($v['s_grade'] == 1 ? '会员店' : ($v['s_grade'] == 2 ? '高级店' : '旗舰店'));
                $key_1[] = $v['saleroom'];
                $key_2[] = $v['store_total'];
			}

            if($sort == 'saleroom' && $order == 'asc'){
                array_multisort($key_1, SORT_ASC, SORT_NUMERIC, $store_list);
            }
            if($sort == 'saleroom' && $order == 'desc'){
                array_multisort($key_1, SORT_DESC, SORT_NUMERIC, $store_list);                
            }            

            if($sort == 'store_total' && $order == 'asc'){
                array_multisort($key_2, SORT_ASC, SORT_NUMERIC, $store_list);                
            }
            if($sort == 'store_total' && $order == 'desc'){
                array_multisort($key_2, SORT_DESC, SORT_NUMERIC, $store_list);                
            }
			$total =  count($store_list);
			return $total;
	}
	/*
	 * 销售额
	 */
	/*public function storeSr(){
		if(request()->isAjax()){			
			$s_id = input('post.s_id');
			$data = [];
			$page = '';
			if($s_id){
				$store_info = $this->model->where('s_id', $s_id)->field('s_uid')->find();
				$info = $this->service->saleRoom($store_info['s_uid'], 1, '');
				$data = $info['data'];
				$total = $data['total'];
				$page = $this->pageHandel('storeSr', 1, $total);
			}
			$this->assign('s_id', $s_id);
			$this->assign('page', $page);
			$this->assign('info', $info['data']);
			return $this->fetch();
		}
	}*/

	/*
	 * 查看详情
	 * */
	public function storeCha(){
        $s_id = input('get.s_id');
        $map['s_id'] = ['eq',$s_id];
        $store_list = $this->model->alias('a')->where($map)->join('__USERS__ b', 'a.s_uid=b.user_id','LEFT')->field('a.s_id,a.s_uid,a.s_name,a.s_grade,a.s_logo,a.s_thumb,a.s_comm_time,b.user_name,b.user_mobile,b.s_invite_code')->find();
        $store_list['s_grade'] = ($store_list['s_grade'] == 1 ? '会员店' : ($store_list['s_grade'] == 2 ? '高级店' : '旗舰店'));
        $store_list['s_comm_time'] = $store_list['s_comm_time']?date('Y-m-d H:i:s',$store_list['s_comm_time']):'';
        $uid = db('store')->where('s_id',$s_id)->value('s_uid');
        // 店铺销售额
        $store_saleroom = $this->service->getStoreSaleRoom($uid);
        // 店铺总收入
        $store_total = $this->service->getStoreTotal($uid);
        $this->assign('saleroom', $store_saleroom);
        $this->assign('store_total', $store_total);
        $this->assign('row',$store_list);
        return $this->fetch();
    }

    /*
     * 自己购物
     * */
    public function selfShop(){
        $s_id = trim(input('s_id'));
        $start_time = trim(input('start_time'));
        $end_time = trim(input('end_time'));

        $this->assign('s_id', $s_id);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        // if(request()->isAjax()){
            $start_time = input('start_time');
            $end_time = input('end_time');
            if(input('start_time')){
                $start_time=str_replace('+',' ',input('start_time'));
            }
            if(input('end_time')){
                $end_time=str_replace('+',' ',input('end_time'));
            }
            if ($start_time && $end_time) {
                $map['b.order_create_time'] = array('between',strtotime($start_time).','.(strtotime($end_time)));
            } elseif ($start_time) {
                $map['b.order_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['b.order_create_time'] = array('<=', (strtotime($end_time)));
            }
            $uid = db('store')->where('s_id',$s_id)->value('s_uid');
            $map['a.commi_uid'] = ['eq',$uid];
            $map['b.order_status'] = ['eq',4];
            $list = Db::name('commission')->alias('a')->join('__ORDER__ b', 'a.commi_order_id=b.order_id')->field('a.commi_order_price,b.order_id,b.order_no,b.order_create_time,b.order_all_price,b.order_pay_price,b.order_status,b.order_coupon_id,b.yz_id')->where($map)->order('a.commi_add_time')->select();
            //大礼包
            unset($map['a.commi_uid']);

            $where_2 = '(a.log_uid='.$uid .' and a.log_type =0) or (a.log_uid='.$uid .' and a.log_type =2) or (a.log_p_uid='.$uid .' and a.log_type =1)';
            $list2 = Db::name('gift_log')->alias('a')->join('__ORDER__ b', 'a.log_order_id=b.order_id')->field('a.log_order_price as commi_order_price,b.order_id,b.order_no,b.order_create_time,b.order_all_price,b.order_pay_price,b.order_status,b.order_coupon_id,b.yz_id')->where($map)->where($where_2)->order('a.log_add_time')->select();
            if(!empty($list) && !empty($list2)){
                $list = array_merge($list, $list2);
            }else if(empty($list)){
                $list = $list2;
            }
            $total = 0.00;
            foreach($list as $key=>&$v){
                if($v['order_create_time']){
                    $v['order_create_time'] = date('Y-m-d H:i', $v['order_create_time']);
                }
                // 抵扣金额
                $discount = 0.00;
                // 优惠券抵扣
                if($v['order_coupon_id']){
                    $discount += Db::name('coupon_users')->where('c_id', $v['order_coupon_id'])->value('c_coupon_price');
                }
                // 元宝抵扣
                if($v['yz_id']){
                    $discount += Db::name('yinzi')->where('yin_id', $v['yz_id'])->value('yin_amount');
                }
                // $v['kou'] = $v['order_all_price']-$v['order_pay_price'];
                $v['kou'] = $discount;
                $total += $v['commi_order_price'];
                $list[$key]['total_price'] = round($total,2);
            }
            $this->assign('total_price', $total);
        if(request()->isAjax()){
            return json(['rows'=>$list]);
        }else{
            return $this->fetch();
        }
    }

    /*
     *子店铺销售
     * */
    public function childShop(){
        $s_id = trim(input('s_id'));
        $start_time = trim(input('start_time'));
        $end_time = trim(input('end_time'));

        $this->assign('s_id', $s_id);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        // if(request()->isAjax()){
        $map = '';
        if($start_time){
            $start_time=str_replace('+',' ',input('start_time'));
			$start_time = strtotime($start_time);
            $map .= " AND b.s_comm_time>=$start_time";
        }
        if($end_time){
            $end_time=str_replace('+',' ',input('end_time'));
			$end_time = strtotime($end_time);
            $map .= " AND b.s_comm_time<=$end_time";
        }

        $uid =$this->model->where('s_id',$s_id)->value('s_uid');
        $list = Db::name('commission')->query("SELECT a.commi_uid as child_uid,b.s_grade,b.s_name,b.s_comm_time,c.user_name FROM ht_commission as a LEFT JOIN ht_store as b on a.commi_uid=b.s_uid left join ht_users as c on a.commi_uid=c.user_id WHERE a.commi_p_uid=$uid AND a.p_uid_role>1 AND a.uid_role>1 $map UNION SELECT a.commi_uid as child_uid,b.s_grade,b.s_name,b.s_comm_time,c.user_name FROM ht_commission as a LEFT JOIN ht_store as b on a.commi_uid=b.s_uid left join ht_users as c on a.commi_uid=c.user_id WHERE a.commi_g_uid=$uid and a.g_uid_role>1 AND a.uid_role>1 $map UNION SELECT a.commi_p_uid as child_uid,b.s_grade,b.s_name,b.s_comm_time,c.user_name FROM ht_commission as a LEFT JOIN ht_store as b on a.commi_p_uid=b.s_uid left join ht_users as c on a.commi_uid=c.user_id WHERE a.commi_g_uid=$uid and a.g_uid_role>1 AND a.p_uid_role>1 $map UNION select a.log_uid as child_uid,b.s_grade,b.s_name,b.s_comm_time,c.user_name from ht_gift_log as a left join ht_store as b on a.log_uid=b.s_uid left join ht_users as c on a.log_uid=c.user_id where a.log_uid in (SELECT a.t_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_uid=b.s_uid where a.t_p_uid=$uid UNION select a.t_p_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_p_uid=b.s_uid where a.t_g_uid=$uid UNION select a.t_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_uid=b.s_uid where a.t_g_uid=$uid) and a.log_type!=1 $map ORDER BY s_comm_time desc");
		
        $tmp_arr = [];
        $total = 0.00;
        $uid_arr = [];
        foreach($list as $k => $v){
			 $list[$k]['s_comm_time'] = date('Y-m-d H:i',$v['s_comm_time']);
            if(!in_array($v['child_uid'], $uid_arr)){
                $list[$k]['s_grade'] = ($v['s_grade'] == 1 ? '会员店主' : ($v['s_grade'] == 2 ? '高级店主' : '旗舰店主'));
                $data = $this->service->getStoreSaleRoom($v['child_uid']);
                $list[$k]['sale_total'] = $data['total'];
                $total += $data['total'];    
                $uid_arr[] = $v['child_uid'];
                $tmp_arr[] = $list[$k];
            }    
          
        }

        $this->assign('total_price', $total);

        if(request()->isAjax()){
            return json(['rows' => $tmp_arr]);
        }else{
            return $this->fetch();
        }
    }

    /*
     * 子VIP购物
     * */
    public function childVip(){
        $s_id = trim(input('s_id'));
        $start_time = input('start_time');
        $end_time = input('end_time');

        $this->assign('s_id', $s_id);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        // if (request()->isAjax()) {            
            $map = '';
            if ($start_time) {
                $start_time = str_replace('+', ' ', $start_time);
                $map .= ' and c.order_create_time>='.strtotime($start_time);
            }
            if ($end_time) {
                $end_time = str_replace('+', ' ', $end_time);
                $map .= ' and c.order_create_time<='.strtotime($end_time);
            }

            $uid =$this->model->where('s_id',$s_id)->value('s_uid');
            // $store_info = $this->service->getStoreInfo($uid);
            $sql = 'select a.commi_order_price,c.order_id,c.order_no,d.user_name,d.user_mobile,c.order_create_time,c.order_all_price,c.order_pay_price,c.order_coupon_id,c.yz_id from ht_commission as a inner join (select commi_uid from ht_commission where commi_p_uid='.$uid.' and uid_role=1 union select commi_uid from ht_commission where commi_g_uid='.$uid.' and uid_role=1) as b on a.commi_uid=b.commi_uid inner join ht_order as c on a.commi_order_id=c.order_id inner join ht_users as d on c.order_uid=d.user_id where a.uid_role=1 and ((a.commi_p_uid='.$uid.' and a.p_uid_role>1) or (a.commi_g_uid='.$uid.' and a.g_uid_role>1)) '.$map.' order by c.order_create_time desc';
            $list = Db::name()->query($sql);
            $total = 0.00;
            foreach($list as &$v){
                $v['order_create_time'] = $v['order_create_time'] ? date('Y年m月', $v['order_create_time']) : '';
                // 抵扣金额
                $discount = 0.00;
                // 优惠券抵扣
                if($v['order_coupon_id']){
                    $discount += Db::name('coupon_users')->where('c_id', $v['order_coupon_id'])->value('c_coupon_price');
                }
                // 元宝抵扣
                if($v['yz_id']){
                    $discount += Db::name('yinzi')->where('yin_id', $v['yz_id'])->value('yin_amount');
                }
                $v['discount'] = $discount;
                $total += $v['commi_order_price'];
            }
        $this->assign('total_price', $total);
        if (request()->isAjax()) {
            return json(['rows'=>$list]);
        }else{
            return $this->fetch();
        }
    }

    /*
     *销售利润
     * */
    public function saleGain(){
        $s_id = trim(input('s_id'));
        $start_time = trim(input('start_time'));
        $end_time = trim(input('end_time'));

        $this->assign('s_id', $s_id);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        // if(request()->isAjax()){
        $uid = db('store')->where('s_id',$s_id)->value('s_uid');
        // 自己购物
        $map_1 = 'a.commi_uid='.$uid.' and a.uid_role>1';
        // 下级vip购物 * 50%
        $map_2 = 'a.commi_p_uid='.$uid.' and a.uid_role=1';

            if($start_time){
                $start_time=str_replace('+',' ',input('start_time'));
                $map_1 .= ' and b.order_create_time>='.strtotime($start_time); 
                $map_2 .= ' and b.order_create_time>='.strtotime($start_time); 
            }
            if($end_time){
                $end_time=str_replace('+',' ',input('end_time'));
                $map_1 .= ' and b.order_create_time<='.strtotime($end_time);
                $map_2 .= ' and b.order_create_time<='.strtotime($end_time);
            }
            $list = Db::name('commission')->query("SELECT a.commi_uid as list_uid,a.commi_order_price,a.goods_profit,b.order_id,b.order_no,b.order_create_time from ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id where $map_1 UNION SELECT a.commi_uid as list_uid,a.commi_order_price,a.goods_profit,b.order_id,b.order_no,b.order_create_time from ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id where $map_2 order by order_create_time desc");

            $list_total = 0.00;
            foreach($list as &$v){                
                $user_info = Db::name('users')->where('user_id', $v['list_uid'])->find('user_name,user_mobile');
                $v['user_name'] = ($uid == $v['list_uid']) ? '我自己' : $user_info['user_name'];
                $v['user_mobile'] = $user_info['user_mobile'];
                $v['order_create_time'] = $v['order_create_time'] ? date('Y-m-d', $v['order_create_time']) : '';
                $list_total += ($v['goods_profit']);
            }
            
            // $map['a.commi_uid'] = ['eq',$uid];
            // $list = db('commission')->alias('a')
            //     ->join('__ORDER__ b', 'a.commi_order_id=b.order_id')
            //     ->join('__USERS__ c','b.order_uid=c.user_id')
            //     ->field('a.commi_order_price,a.goods_profit,b.order_id,b.order_no,b.order_create_time,c.user_name,c.user_mobile')
            //     ->where($map)->order('a.commi_add_time')->select();
            // $sql = "SELECT a.t_uid,b.user_id,b.user_name,b.user_mobile FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid UNION SELECT a.t_uid,b.user_id,b.user_name,b.user_mobile FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_g_uid=$uid";
            // $tree_info = Db::name('users_tree')->query($sql);
            // $data_info=[];
            // foreach ($list as $val){
            //     $val['user_name'] = '我自己';
            //     $val['order_create_time']= $val['order_create_time']?date('Y-m-d H:i:s',$val['order_create_time']):'';
            //     $data_info [] =$val;
            // }
            // if ($start_time && $end_time) {
            //     $where['a.order_create_time'] = array('between',strtotime($start_time).','.(strtotime($end_time)));
            // } elseif ($start_time) {
            //     $where['a.order_create_time'] = array('>=',strtotime($start_time));
            // } elseif ($end_time) {
            //     $where['a.order_create_time'] = array('<=', (strtotime($end_time)));
            // }
            // $dataArray = [];
            // foreach ($tree_info as $item) {
            //     $where['a.order_uid'] = ['eq',$item['user_id']];
            //     $info = db('order')->alias('a')
            //         ->where($where)
            //         ->join('__COMMISSION__ b','a.order_id=b.commi_order_id')
            //         ->join('__USERS__ c','a.order_uid=c.user_id')
            //         ->field('b.commi_order_price,b.goods_profit,a.order_id,a.order_no,a.order_create_time,c.user_name,c.user_mobile')
            //         ->select();
            //     if($info){
            //         foreach ($info as $key=>$val){
            //             $val['order_create_time']= $val['order_create_time']?date('Y-m-d H:i:s',$val['order_create_time']):'';
            //             $dataArray[] = $val;
            //         }
            //     }
            // }
            // $arr = array_merge($dataArray,$data_info);
            // $total = 0.00;
            // foreach ($arr as $key=>$val){
            //     $total += $val['commi_order_price'];
            //     $arr[$key]['sumPrice'] =$total;
            // }
        // $this->assign('total_price', sprintf('%0.2f' ,$list_total));
        $this->assign('total_price', $list_total);
        if(request()->isAjax()){
            return json(['rows' => $list]);
        }else{
            return $this->fetch();
        }
    }

    /*
     * 团队奖励
     * */
    public function teamReward(){
        $s_id = trim(input('s_id'));
        $start_time = trim(input('start_time'));
        $end_time = trim(input('end_time'));

        $this->assign('s_id', $s_id);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $store_info = Db::name('store')->where('s_id', $s_id)->field('s_uid')->find();
        $uid = $store_info['s_uid'];
        if(request()->isAjax()){
            $type = input('type', 'one');
            // 提成奖励
            if($type == 'one'){
                $start_time = input('start_time');
                $end_time = input('end_time');
                if(input('start_time')){
                    $start_time=str_replace('+',' ',input('start_time'));
                }
                if(input('end_time')){
                    $end_time=str_replace('+',' ',input('end_time'));
                }
                $map = '';
                if ($start_time && $end_time) {
                    $map .= ' and b.order_create_time>='.strtotime($start_time).' and b.order_create_time<='.strtotime($end_time);
                } elseif ($start_time){
                    $map .= ' and b.order_create_time>='.strtotime($start_time);
                } elseif ($end_time) {
                    $map .= ' and b.order_create_time<='.strtotime($end_time);
                }                
                // 子店铺
                $child_seller = Db::name('commission')->query("SELECT a.commi_uid as child_uid,a.commi_order_price,b.order_id,b.order_commi_price,b.order_create_time,a.goods_profit,a.commi_p_price as commi_price FROM ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id WHERE a.commi_p_uid=$uid AND a.p_uid_role>1 AND a.uid_role>1 $map UNION SELECT a.commi_p_uid as child_uid,a.commi_order_price,b.order_id,b.order_commi_price,b.order_create_time,a.goods_profit,a.commi_g_price as commi_price FROM ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id WHERE a.commi_g_uid=$uid and a.g_uid_role>1 AND a.p_uid_role>1 $map");            
                foreach($child_seller as &$v){
                    $user_info = Db::name('users')->where(['user_id' => $v['child_uid']])->field('user_name')->find();
                    $v['user_name'] = $user_info['user_name'];                    
                    $v['time'] = $v['order_create_time'];
                    $v['order_create_time'] = $v['order_create_time'] ? date('Y-m-d', $v['order_create_time']) : '';
                    // 返利比例
                    $v['commi_rate'] = (round($v['goods_profit'] / $v['commi_order_price'], 2) * 100).'%';
                    // 提成比例
                    $v['profit_rate'] = (round($v['commi_price'] / $v['goods_profit'], 2) * 100).'%'; 
                }
                // 下下级店铺
                $g_child_seller = Db::name('commission')->query("SELECT a.commi_uid as child_uid,a.commi_order_price,b.order_id,b.order_commi_price,b.order_create_time,a.goods_profit,a.commi_p_price as commi_price FROM ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id WHERE commi_g_uid=$uid and g_uid_role>1 AND uid_role>1 $map");
                if($g_child_seller){
                    foreach($g_child_seller as &$v){
                        $user_info = Db::name('users')->where(['user_id' => $v['child_uid']])->field('user_name')->find();
                        $v['user_name'] = $user_info['user_name'];
                        
                        $v['user_name'] = $user_info['user_name'];
                        $v['time'] = $v['order_create_time'];
                        $v['order_create_time'] = $v['order_create_time'] ? date('Y-m-d', $v['order_create_time']) : '';
                        // 返利比例
                        $v['commi_rate'] = (round($v['goods_profit'] / $v['commi_order_price'], 2) * 100).'%';
                        // 提成比例
                        $v['profit_rate'] = (round($v['commi_price'] / $v['goods_profit'], 2) * 100).'%';
                    }
                }
        
                $tmp_arr = array_merge($child_seller, $g_child_seller);
                $key = [];
                foreach($tmp_arr as &$v){
                    $key[] = $v['time'];
                    unset($v['time']);
                }

                array_multisort($key, SORT_DESC, SORT_NUMERIC, $tmp_arr);
                return json(['rows' => $tmp_arr]);
            }
            // 月度奖励
            else if($type == 'two'){
                $list = Db::name('reward')->field('reward_num,reward_stat,reward_time')->where(['reward_uid' => $uid])->order('reward_time desc')->select();
                foreach($list as &$v){
                    $v['reward_time'] = $v['reward_time'] ? date('Y年m月', $v['reward_time']) : '';
                }
                return json(['rows' => $list]);
            }
        }else{
            return $this->fetch();
        }
    }

    /*
     * 业绩奖励
     */
    public function perforReward(){
        $s_id = input('s_id');
        $start_time = trim(input('start_time'));
        $end_time = trim(input('end_time'));

        $this->assign('s_id', $s_id);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $store_info = Db::name('store')->where('s_id', $s_id)->field('s_uid')->find();
        $uid = $store_info['s_uid'];
        $total = 0;
        $map = ['reward_uid' => $uid];
        if($start_time){
            $start_time = str_replace('+',' ',$start_time);
            $map['reward_time'][] = ['egt', strtotime($start_time)];
        }
        if($end_time){
            $end_time = str_replace('+',' ',$end_time);
            $map['reward_time'][] = ['elt', strtotime($end_time)];
        }
        $list = Db::name('reward')->where($map)->field('reward_num,reward_stat,reward_time')->order('reward_time desc')->select();
        foreach($list as &$v){
            $v['reward_time'] = $v['reward_time'] ? date('Y年m月', $v['reward_time']) : '';
            $v['gived_money'] = $v['reward_stat'] ? $v['reward_num'] : 0;
            $v['remark'] = '';
            $total += $v['reward_num'];
        }
        $this->assign('total_price', $total);
        if(request()->isAjax()){
            return json(array('rows' => $list));
        }
        else return $this->fetch();
    }   

	/*
	 * 业绩管理
 	 */
	public function perforManage(){
		// if(request()->isAjax()){
			$s_id = input('get.s_id');
			if(!$s_id){
				$this->error('未知参数');
			}
			$user_info = $this->model->where('s_id', $s_id)->field('s_uid')->find();
			$perfor_info = $this->service->perforManage($user_info['s_uid'], 1);
			if(!$perfor_info['code']){
				$this->error($perfor_info['msg']);
			}
			$this->assign('info', $perfor_info['data']['list']);
			return $this->fetch();
	}

	/*
	 * 业绩明细
	 */
	public function storePerfor(){
		if(request()->isAjax()){		
			$uid = input('post.uid');
			$data = [];
			if($uid){
				$info = $this->service->perforInfo($store_info['s_uid'], 1, '');
				$data = $info['data'];				
			}
			$this->assign('info', $info['data']);
			return $this->fetch();
		}		
	}

	/*
	 * VIP管理
	 */
	public function storeVip(){
		if(request()->isAjax()){		
			$s_id = input('post.s_id');
			$data = [];
			if($s_id){
				$store_info = Db::name('store')->where('s_id', $s_id)->field('s_uid')->find();
				$info = $this->service->vipManage($store_info['s_uid'], 1);
				$data = $info['data'];				
			}
			$this->assign('info', $info['data']);
			return $this->fetch();
		}
	}

	/*
	 * 处理分页
	 */
	private function pageHandel($url, $num, $total){
		//总页数
		$all_page = ceil($total / $num);
		// return $url;
		$page_url = '第';
		for($i = 1; $i <= $all_page; $i++){
			$page_url = $page_url.'<a class="my-page" data-url="'.$url.'" data-page="'.$i.'">'.$i.'</a>';
		}
		return $page_url.'页';
	}

    /*
     * 增加店铺
     */
    public function addStore(){
        if(Request()->isAjax()){
            $data = input('post.');
            $files = $_FILES;
            if($data){
                $insert = [];
                Db::startTrans();
                try{
                    $user_info = Db::name('users')->where(['user_mobile' => $data['user_mobile'], 'is_seller' => 0])->field('user_id')->find();
                    if(!$user_info){
                        return json(['code' => 0, 'msg' => '会员不存在']);
                    }
                    Db::name('users')->where(['user_id' => $user_info['user_id']])->update(['is_seller' => 1, 'is_kefu' => 1]);
                    if(($s_logo = $files['s_logo']) && $files['s_logo']['tmp_name'] ){
                        if($s_logo['error']){
                            return json(['code' => '0', 'msg' => '文件上传失败']);
                        }
                        $s_logo_res = $this->uploadServerImg($s_logo);
                        if(!$s_logo_res['code']){
                            return json(['code' => '0', 'msg' => $s_logo_res['msg']]);
                        }
                        $insert['s_logo'] = $s_logo_res['img'];                        
                    }
                    if(($s_thumb = $files['s_thumb'])  && $files['s_thumb']['tmp_name']){
                        if($s_thumb['error']){
                            return json(['code' => '0', 'msg' => '文件上传失败']);
                        }
                        $s_thumb_res = $this->uploadServerImg($s_thumb);
                        if(!$s_thumb_res['code']){
                            return json(['code' => '0', 'msg' => $s_thumb_res['msg']]);
                        }
                        $insert['s_thumb'] = $s_thumb_res['img'];                        
                    }

                    $insert['s_name'] = $data['s_name'];
                    $insert['s_uid'] = $user_info['user_id'];
                    if($data['s_intro']) $insert['s_intro'] = $data['s_intro'];
                    $insert['s_comm_time'] = time();
                    Db::name('store')->insert($insert);
                    Db::commit();
                    return json(['code' => 1, 'msg' => '添加成功']);
                }
                catch(\Execption $e){
                    Db::rollback();
                    return json(['code' => 0, 'msg' => '添加失败']);
                }
            }            
        }
        else return $this->fetch();
    }

    /*
     * 验证店铺名
     */
    public function checkStoreName($name = ''){
        $store_name = $name ? : trim(input('name'));
        if(!$store_name){
            return json(['code' => 0, 'msg' => '请输入店铺名']);
        }

        $check = Db::name('store')->where(['s_name' => $store_name])->field('s_id')->find();
        if($check){
            return json(['code' => 0, 'msg' => '店铺名已存在']);
        }
        return json(['code' => 1]);
    }
    /*
     * 验证手机号
     */
    public function checkMobile(){
        $mobile = trim(input('mobile'));
        if(!$mobile || !preg_match('/1[23456789][0-9]{9}$/', $mobile)){
            return json(['code' => 0, 'msg' => '请输入正确的手机号']);
        }

        $check = Db::name('users')->where(['user_mobile' => $mobile])->field('user_id,is_seller')->find();
        if(!$check){
            return json(['code' => 0, 'msg' => '该手机号未注册，请注册后再添加']);
        }
        else if($check['is_seller']){
            return json(['code' => 0, 'msg' => '该用户已是店主，请勿重复添加']);
        }
        else return json(['code' => 1]);
    }

    /*
     * 上传图片到三方服务器
     */
    public function uploadServerImg($file){
        require_once EXTEND_PATH.'Qiniu/autoload.php';
        $filePath = $file['tmp_name'];
        $path_info = pathinfo($file['name']);
        $ext = $path_info['extension'];
        // 上传到七牛后保存的文件名
        $key = substr(md5($filePath), 0, 5) . date('YmdHis') . rand(0, 9999) . '.' . $ext;
        $accessKey = config('ACCESSKEY');
        $secretKey = config('SECRETKEY');
        // 构建鉴权对象
        $auth = new Auth($accessKey, $secretKey);
        // 要上传的空间
        $bucket = config('BUCKET');
        $token = $auth->uploadToken($bucket);
        // 初始化 UploadManager 对象并进行文件的上传
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 putFile 方法进行文件的上传
        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        if ($err !== null) {
            return ['code' => 0, 'msg' => '上传失败'];
        } else {
            //返回图片的完整URL
            $url = config('QINIUHOST');
            return ['code' => 1, 'img' => $url.$ret['key']];
        }
    }

}
<?php
namespace app\api\controller;
//header('content-type=text/html;charset=utf8');
use app\common\service\GoodsCategory;
use app\common\service\Goods as GoodsService;
use app\common\service\Order as OrderService;
use app\common\service\User as UserService;
use app\common\service\Config;
use app\common\service\Cart as CartService;
use think\Db;

class Order extends Common{
	private $uid;
	public function __construct(){
		parent::__construct();
		$this->user = new UserService();
		$this->order = new OrderService();
		$user_id = input('request.uid');
		$token = input('request.token');
		if($user_id && $token){
			$uid = $this->getUid($token, $user_id);
			if(!$uid){
				echo json_encode(['data' => [],'status' => 0,'msg' => '未知参数'], JSON_UNESCAPED_UNICODE);
				exit;
			}
			$this->uid = $uid;
		}
	}	

	/*
	 * 创建订单
	 */
	public function orderCreate(){
		$uid = $this->uid;
		$goods_id = input('request.goods_id');
		$sku_id = input('request.sku_id');
		// $cart_id = input('request.cart_id');
		$goods_num = input('request.goods_num');
		$addr_id = input('request.addr_id');
		$points = input('request.points') ? input('request.points') : 0;
		$giving_id = input('request.giving_id') ? input('request.giving_id') : 0;
		$coupon_id = input('request.coupon_id') ? input('request.coupon_id') : 0;
		$invoice = [
			'need_invoice' => input('request.invoice_need'),
			// 'invoice_type' => input('request.invoice_type'),
			'invoice_header' => input('request.invoice_header'),
			'invoice_com' => input('request.invoice_com'),
			'invoice_com_tax' => input('request.invoice_com_tax'),
		];
		$all_price = input('request.all_price');
		$pay_price = input('request.pay_price');
		$freight = input('request.freight');
		$store_id = input('request.s_id');
		$activity_id = input('request.acti_id');
		$dis_total = input('request.youhui', 0);
		$rc_id = input('request.rc_id');
		$rc_amount = input('request.rc_amount');
		$yz_id = input('request.yz_id');
		$yushou_id = input('yushouid');
		$pick_status = input('pick_status', 0);
		$order_status = input('order_status', '');
 		$res = $this->order->orderCreate($uid, $goods_id, $goods_num, $addr_id, $invoice, $all_price, $pay_price, $freight, $sku_id, $store_id, $dis_total, $points, $coupon_id, $activity_id, $rc_amount, $rc_id, $yz_id,$giving_id, $yushou_id,$pick_status);
	 
		if($res == -1){
			return $this->json('', 0, '商品已下架或不存在');
		}
		else if($res == -2){
			return $this->json('', 0, '商品库存不足');
		}
		else if($res == -4){
			return $this->json('', 0, '活动不存在');
		}
		else if($res == -5){
			return $this->json('', 0, '您已经是店主');	
		}else if($res == -6){
		    if ($order_status == 'libao') {
                $where1 = [
                    'order_uid'=>$uid,
                    'order_status'=>0,
                ];
                $orderInfo = db('order')
                    ->where($where1)
                    ->field('order_id')
                    ->find();
                return $this->json($orderInfo, 2, '您已经存在大礼包订单了');
            } else {
                return $this->json('', 0, '您已经存在大礼包订单了');
            }

		}else if($res == -7){
			  return $this->json('', 0, '您已经是老用户了');
        }else if($res == -8){
            return $this->json('', 0, '超出购买限制');
        }
        else if($res == -9){
        	return $this->json('', 0, '库存不足,拼团无效');
        }
        else if($res == -10){
        	return $this->json('', 0, '该商品已经参加过拼团了');
        }else if($res == -11){
        	
        	return $this->json('', 0, '超出每人限购数量');
        }
		else if(!$res){
			return $this->json('', 0, '订单提交失败');
		}

		return $this->json($res);
	}

	/*
     * 可用优惠券
     */
    public function avaiCoupon(){
    	$uid = $this->uid;
    	$all_price = input('request.all_price');
    	$type = input('request.type');
    	// $goods_service = new GoodsService();
    	// $goods_info = $goods_service->getInfoById($goods_id, 'price,vip_price');
    	$goods_id = input('request.goods_id');
    	$sku_id = input('request.sku_id');
    	$user_service = new UserService();
    	$list = $user_service->avaiCoupon($uid, $all_price, $goods_id, $sku_id, $type);
    	if($list){
    		return $this->json($list);
    	}
    	else return $this->json('', 0, '无优惠券');
    }

	/*
	 * 我的订单
	 */
	public function orderList(){
		$uid = $this->uid;
		$p = input('request.p', 1);
		$type = input('request.type');
		$search = trim(input('request.search'));
		$is_seller = input('request.is_seller') ? input('request.is_seller'): 0;
		$list = $this->order->orderList($uid, $p, $type, $is_seller,$search);
		if ($p == 1) {
            if(!$list['list']){
                return $this->json('', -1, '无列表');
            }
        }

		return $this->json($list);
	}

	/*
	 * 订单详情
	 */
	public function orderDetails(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		$order_service = new OrderService();
		$info = $order_service->orderDetails($uid, $order_id);
		return $this->json($info);
	}

	/*
	 * 取消订单
	 */
	public function orderCancle(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		$res = $this->order->orderCancle($uid, $order_id);
		if(!$res){
			return $this->json('', 0, '取消订单失败');
		}
		else return $this->json('', 1, '取消订单成功');
	}

	/*
	 * 付款页面
	 */
	public function payWay(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		if(!$uid || !$order_id){
			return $this->json('', 0, '未知参数');
		}
		$order_service = new OrderService();
		$list = $order_service->payWay($uid, $order_id);
		if($list == -1){
			return $this->json('', 0, '用户不存在');
		}
		else if($list == -2){
			return $this->json('', 0, '订单不存在');
		}
		return $this->json($list);
	}
	/*
	 * 赠送大礼包支付
 	 */
	public function giftPay(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		if(!$uid || !$order_id){
			return $this->json('', 1, '未知参数');
		}
		$pay_code = input('request.paycode');
		$result = $this->order->orderPay($uid, $order_id, $pay_code);
		// file_put_contents('unionpay.html', $result);
		if(!$result){
			return $this->json('', 0, '订单支付失败');
		}
		else if($result == -1){
			return $this->json('', -1, '可用余额不足');
		}
		else if($result == -2){
			// $res = $this->order->giving($order_id, $uid);			
			return $this->json('', -1, '对方已经是店主');
		}
		else{
			return $this->json($result);
		}
	}
	/*
	 * 立即付款
 	 */
	public function orderPay(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		if(!$uid || !$order_id){
			return $this->json('', 1, '未知参数');
		}
		$pay_code = input('request.paycode');
		$result = $this->order->orderPay($uid, $order_id, $pay_code);
		// file_put_contents('unionpay.html', $result);
		
		if(!$result){
			return $this->json('', 0, '订单支付失败');
		}
		else if($result == -1){
			return $this->json('', -1, '可用余额不足');
		}
		else if($result == -2){
			return $this->json('', 0, '对方已经是店主');	
		} else if ($result == -3){
            return $this->json('', 0, '新人专享一人仅可参与一次哦～');
        }
		else{
			return $this->json($result);
		}
		// if($result){
		// 	//大礼包 开店
		// 	$res = $this->order->buyStory($order_id,$uid);
		// 	if(!$res['code']){
		// 		return $this->json([], 0, $res['msg']);
		// 	}
		// 	return $this->json($result);
		// }
		// else{
		// 	return $this->json('', 0, '订单支付失败');
		// }
	}

	/*
	 * 查看物流
	 */
	public function postInfo(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		$list = $this->order->postInfo($uid, $order_id);
		return $this->json($list);
	}	
	/*
	 * 查看多个物流
	 */
	public function postInfos(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		$list = $this->order->postInfos($uid, $order_id);
		
		return $this->json($list);
	}
	/*
	 * 查看多个物流
	 */
	public function postFlow(){
		$order_id = input('request.orderid');
		$og_supplier_id = input('request.og_supplier_id');
		$list = $this->order->postFlow($order_id,$og_supplier_id);
		return $this->json($list);
	}

	/*
	 * 确认收货
	 */
	public function postConfirm(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		$res = $this->order->postConfirm($uid, $order_id);

		if(!$res){
			return $this->json('', 0, '确认收货失败');
		}
		else return $this->json('', 1, '确认收货成功');
	}

	/*
	 * 删除订单
	 */
	public function orderDel(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		$is_seller = input('request.is_seller') ? : 0;
		$res = $this->order->orderDel($uid, $order_id, $is_seller);
		if(!$res){
			return $this->json('', 0, '删除订单失败');
		}
		else return $this->json('', 1, '删除订单成功');
	}

	/*
	 * 订单评价商品列表
	 */
	public function goodsCommList(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		$list = $this->order->goodsCommList($uid, $order_id);
		if(!$list){
			return $this->json('', 0, '订单不存在');
		}
		return $this->json($list);
	}

	/*
	 * 订单评价
	 */
	public function goodsComment(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		// $goods_id = 
		// $result = $this->order->goodsComment($uid, $order_id, $goos_id, $);
		return $result;
	}


	/*
	* 获取配送方式
	*/
	public function getPostType(){
		//获取配置信息
		$ConfigService=new Config();
		$config=$ConfigService->find();
        $data = [];
        $express=$config['express'];
        $express = json_decode($express, true);
        $exp = $express['express'];
		foreach ($exp['value'] as $k => $v) {
			$data[] = ['value'=>$v,'field'=>$exp['field'][$k]];
		}
		return $this->json($data);
	}

	/* 
	* 测试
	*/
	public function test(){
		// return $this->json(HT_ROOT);
		$OrderService=new OrderService();
		// $res=$OrderService->paySuccess(2);
		$order_info = [];
		$res = $OrderService->goodsCommission($order_info);
		// $res = Db::name('ali_test')->insert(['res' => 'test']);
		return $this->json(['res' => $res]);
	}

	/*
	 * 创建大礼包订单
	 */
	public function giftOrder(){
		$uid = $this->uid;
		$goods_id = input('request.goods_id');
		$sku_id = input('request.sku_id');
		$addr_id = input('request.addr_id');
		$bag_id = input('request.bag_id');
		if(!$bag_id){
			return $this->json('', 0, '未知参数');
		}
		$invoice = [
			'need_invoice' => input('request.invoice_need'),
			'invoice_header' => input('request.invoice_header'),
			'invoice_com' => input('request.invoice_com'),	
			'invoice_com_tax' => input('request.invoice_com_tax'),
		];
		$freight = input('request.freight');
 		$res = $this->order->giftOrder($uid, $goods_id, $addr_id, $invoice, $freight, $sku_id, $bag_id);
 		if(!$res['code']){
 			return $this->json('', 0, $res['msg']);
 		}
 		return $this->json('', 1, '订单创建成功');
	}
	/*
	 *  获取售后商品列表 $type 1 售后申请记录 列表
	 */
	 public function asList(){

	    $type = input('request.type');
		$uid = $this->uid;
		$p = input('p', 1);
		if( $type == 1){
            $list = $this->order->getasRecord($uid, $p);
        }else{
            $list = $this->order->getasList($uid);
        }
		if($list){
 			return $this->json($list, 1,'获取成功！');
 		}
 		return $this->json('', 0, '暂无数据');
		
	 }
    /*
    *  获取售后申请记录
    */
    public function asRecord(){
        $uid = $this->uid;
        $list = $this->order->getasRecord($uid);
        if($list){
            return $this->json($list, 1,'获取成功！');
        }
        return $this->json('', 0, '暂无数据');
    }
    /*
   *  获取售后申请详情
   */
    public function asInfo(){
        $uid = $this->uid;
        $og_id = input('request.og_id');
        $row = $this->order->getasInfo($og_id);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0, '暂无数据');
    }
   /*
   *  获取售后申请提交
   * 1，申请退货；2，申请换货
	status 1:订单取消  2 提交售后
   */
    public function asSubmit(){
        $uid = $this->uid;
        $type = input('request.type');
        $og_id = input('request.og_id');
        $status = input('request.status');
        $phone = input('request.phone');
        $consigee = input('request.consigee');//收件人
        $audit_address = input('request.audit_address');//收件人
		$new_address = '';
		if($phone&&$consigee&&$audit_address){
			$new_address = [
				'phone'=>$phone,
				'consigee'=>$consigee,
				'audit_address'=>$audit_address,
			];
			$new_address = json_encode($new_address);
		} 
        if($og_id){
            $res  = $this->order->asJudge($og_id);
            if($res){
                return $this->json('', 0,'已提交售后，不能再次提交！');
            }
        }
        $audit_no = $this->order->createAsNo();
        $data = [
            'after_state_status'=> $type,//售后在状态 1，申请退货；2，申请换货 3 仅退款
            'audit_reason'=> input('request.audit_reason'),//售后原因
            'audit_issue'=> input('request.audit_issue'),//售后问题描述
            'audit_thumb'=> input('request.audit_thumb'),//用户凭证
            'audit_address'=> $new_address,//换货寄回地址
            'audit_no'=> $audit_no,//售后单号
            'apply_time'=> time(),//售后申请时间
            'audit_status'=> 1,//售后进度状态：0，未提交，1，待审核；2，取消申请；3，已通过；4，售后已收货；5，已完成；6，未通过 7，已经超时
        ];
        $res  = $this->order->asSubmit($og_id,$data,$status);
        if($res){
			$user_name = $this->order->getUsername($uid);
			$data = [
                'as_id' => $og_id,//售后id
                'agent_type' => 4,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户:,5:总管理员;
                'agent_id' => $uid,//经办人id;
                'agent_name' =>$user_name,//经办人名称
                'as_log_desc' => '用户提交售后',//日志内容
				'agent_note'=>'用户提交售后',
				'add_time'=>time(),
                'as_status' => 1,//售后进度状态：0，待审核；1，申请审核；2：审核中；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
            ];
            $this->order->writelog($data);
            return $this->json($og_id, 1,'提交成功！');
        }
        return $this->json('', 0,'提交失败！');
    }
    /*
     *  获取售后进度信息
     */
    public function  schedule(){
        $uid = $this->uid;
        $og_id = input('request.og_id');
        $type_stu = input('request.type_stu');
        $row = $this->order->schedule($og_id, $type_stu);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0,'获取失败！');
    }
    /*
    *  获取售后人 联系方式
    */
    public function  asRelation(){
        $order_id = input('request.order_id');
        $row = $this->order->asRelation($order_id);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0,'获取失败！');
    }
	/*
	*自提不
   	*/
   	public function pcik(){		
		$row  = $this->getPick();
		 if($row){
            return $this->json($row, 1,'获取成功！');
        }
		return $this->json('', 0,'获取失败！');
	}

	/*
	*客新增功能 售后取消
   	*/
   	public function afterundo(){	
		$uid = $this->uid;
		$og_id = input('request.og_id');
		if(!$uid || !$og_id){
			return $this->json('', 0, '未知参数');
		}
		$res = $this->order->afterundo($og_id);
		if($res){
            return $this->json($res, 1,'取消成功！');
        }
		return $this->json('', 0,'取消失败！');
	
	}
	/*
	*购买限制
   	*/
   	public function orderLimit(){	
		$uid = $this->uid;
		$goods_id = input('request.goods_id');
		$prom_type = input('request.prom_type');
		$prom_id = input('request.prom_id');
		$number = input('request.number');
		
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$res = $this->order->orderLimit($uid,$goods_id,$prom_type,$prom_id,$number);
		
		if($res){
            return $this->json($res, 1,'可以购买');
        }else{
			if($prom_type == 5){
				$goods_where = [
					'goods_id' => $goods_id,
				];
				$flash_goods_limit = Db::name('flash_goods')->where($goods_where)->value('buy_limit');
				$goods_limit = $flash_goods_limit?$flash_goods_limit:'1';
				$goods_limit = $this->getBig($goods_limit);
				if($goods_limit){
					return $this->json([], 0,'秒杀商品一人只可购买'.$goods_limit.'件!');
				}
			}
			return $this->json($res, 0,'超出购买限制');
		}
	}
	
	 /**
     *  数字转化
     */
	public function getBig($num){
		$chiNum = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
		$chiUni = array('','十', '百', '千', '万', '亿', '十', '百', '千');
		$chiStr = '';
		$num_str = (string)$num;
		$count = strlen($num_str);
		$last_flag = true; 
		$zero_flag = true;  
		$temp_num = null; 
		if($count == 2){
			$temp_num = $num_str[0];
			$chiStr = $temp_num == 1 ? $chiUni[1] : $chiNum[$temp_num].$chiUni[1];
			$temp_num = $num_str[1];
			$chiStr .= $temp_num == 0 ? '' : $chiNum[$temp_num];
		}else if($count > 2){
			 $index = 0;
			for ($i=$count-1; $i >= 0 ; $i--) {
				$temp_num = $num_str[$i];         //获取的个位数
				if ($temp_num == 0) {
					if (!$zero_flag && !$last_flag ) {
						$chiStr = $chiNum[$temp_num]. $chiStr;
						$last_flag = true;
					}
				}else{
					$chiStr = $chiNum[$temp_num].$chiUni[$index%9] .$chiStr;
					 $zero_flag = false;
					$last_flag = false;
				}
				$index ++;
			}
		}else{
			$chiStr = $chiNum[$num_str[0]];     
		}
		return $chiStr;

	}
	/*
	*物流公司列表
   	*/
   	public function orderExpress(){	
		$ConfigService=new Config();
		$config=$ConfigService->find();		
		$postType=$config['shop']['postType'];
		$express=$config['express'];
		$express = json_decode($config['express'], true); 
		$express =$express['express']; 
		if($express){
            return $this->json($express, 1,'获取成功');
        }else{
			return $this->json($express, 0,'暂无数据');
		}
	
	}
	
	/*
	*客服退货物流 提交
   	*/
   	public function orderClient(){	
		$uid = $this->uid;
		$og_id = input('request.og_id');
		$client_post_no = input('request.client_post_no');
		$client_post_type = input('request.client_post_type');
		if(!$uid || !$og_id||!$client_post_no||!$client_post_type){
			return $this->json('', 0, '未知参数');
		}
		$res = $this->order->orderClient($uid,$og_id,$client_post_no,$client_post_type);
		if($res!=false){
            return $this->json($res, 1,'提交成功');
        }else{
			return $this->json('', 0,'提交失败');
		}
	}
	/*
	*客服退货物流 详情列表
   	*/
   	public function postClient(){	
		$uid = $this->uid;
		$og_id = input('request.og_id');
		if(!$uid || !$og_id){
			return $this->json('', 0, '未知参数');
		}
		$res = $this->order->postClient($uid,$og_id);
		if($res!=false){
            return $this->json($res, 1,'获取成功');
        }else{
			return $this->json('', 0,'获取失败');
		}
	
	}

	/**
     * 获取售后原因及进度
     */
	public function getAsData()
    {
        $uid = $this->uid;
        $og_id = input('request.og_id');
        $row = $this->order->getAsData($uid,$og_id);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0, '暂无数据');
    }

    /**
    *用户确认收到退回货物
    */
    public function userReciveGoods()
    {
    	$uid = $this->uid;
        $og_id = input('request.og_id');
        $res = Db::name('sh_info')->where(['og_uid'=>$uid,'og_id'=>$og_id])->update(['client_status'=>1,'audit_status'=>5, 'og_order_status' => 4]);
        if($res){
            return $this->json($res, 1,'成功！');
        }
        return $this->json('', 0, '暂无数据');
    }

    /**
     * 用户提交寄回货物物流单号
     */
    public function subUserPost()
    {
        $og_id = input('request.og_id');
        $client_post_no = input('request.express_no');
        $client_post_type = input('request.express_type');
        if(empty($og_id) || empty($og_id) || empty($og_id)){
            return $this->json('', 0, '暂无数据');
        }
        $res = Db::name('sh_info')->where(['og_id'=>$og_id])->update(['client_post_no'=>$client_post_no,'client_post_type'=>$client_post_type]);
        if($res){
            return $this->json('', 1, '提交成功');
        }else{
            return $this->json('', 0, '暂无数据');
        }

    }
    /**
     * 重新购买加入购物车
     */
    public function againBuy(){
        $order_id = (int)input('order_id', 0);
        if ($order_id < 1) {
            return $this->json([], 0,'重新购买失败');
        }
        $order_list = Db::name('order')
            ->where(['a.order_id' => $order_id])
            ->alias('a')
            ->join('order_goods b','a.order_id=b.og_order_id')
            ->field('a.order_uid,b.og_goods_id,b.og_goods_spec_id,b.og_goods_num,b.og_acti_id')
            ->select();
        $CartModel=new CartService();
        if ($order_list) {
            foreach ($order_list as $value) {
                $CartModel->addData($value['og_goods_spec_id'],
                    $value['og_goods_id'], $value['order_uid'], $value['og_goods_num'], $value['og_acti_id']);
            }
            return $this->json([], 1,'添加购物车成功！');
        }
        return $this->json([], 0,'重新购买失败');
    }
	
	/**
     * 用户完成售后
    */
    public function afterSaled()
    {
		$uid = $this->uid;
        $og_id = input('request.og_id');
        $row = $this->order->afterSaled($uid,$og_id);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0, '暂无数据');
	}
}
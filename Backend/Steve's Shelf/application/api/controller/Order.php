<?php
namespace app\api\controller;

use app\admin\controller\Intergral;
use app\common\service\Order as OrderService;
use app\common\service\User as UserService;
use app\common\service\Config;
use app\common\service\Cart as CartService;
use think\Db;

class Order extends Common {
	protected $uid;
    protected $order;
    protected $user;
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

	/**
	 * 创建订单
	 */
	public function orderCreate()
    {

        $config = Db::name('config')->field('full_gift,standard,setjifen') ->find();
		$uid = $this->uid;
		$goods_id = input('request.goods_id');
		$sku_id = input('request.sku_id');
		$goods_num = input('request.goods_num');
		$addr_id = input('request.addr_id');
		$points = input('request.points') ? input('request.points') : 0;
		$giving_id = input('request.giving_id') ? input('request.giving_id') : 0;
		$coupon_id = input('request.coupon_id') ? input('request.coupon_id') : 0;
		$invoice = [
			'need_invoice' => input('request.invoice_need'),
			'invoice_header' => input('request.invoice_header'),
			'invoice_com' => input('request.invoice_com'),
			'invoice_com_tax' => input('request.invoice_com_tax'),
		];
		$all_price = input('request.all_price');
		$pay_price = input('request.pay_price');
		if($config['standard']>$all_price){
            return $this->json('', 0, '商品价格不再配送范围内');
        }
		$freight = input('request.freight');
      //增加仓库和配送距离
      $stock_id = input('request.stock_id');
		$distance = input('request.distance');
		$store_id = input('request.s_id');
		$activity_id = input('request.acti_id');
		$dis_total = input('request.youhui', 0);
		$rc_id = input('request.rc_id');
		$rc_amount = input('request.rc_amount');
		$yz_id = input('request.yz_id');
		$yushou_id = input('yushouid');
		$pick_status = input('pick_status', 0);
		$order_status = input('order_status', '');
        $order_remark = input('order_remark', '');
        $order_gift = input('order_gift','');
	 	$res = $this->order->orderCreate($uid, $goods_id, $goods_num, $addr_id, $invoice, $all_price, $pay_price, $freight, $sku_id, $store_id, $dis_total, $points, $coupon_id, $activity_id, $rc_amount, $rc_id, $yz_id,$giving_id, $yushou_id,$pick_status,$order_remark,$order_gift,$distance,$stock_id);
	 	
	 		// return $this->json('-3', 0, '123456');
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

	/**
     * 可用优惠券
     * @param integer uid
     * @param string token
     * @param double allPrice
     * @param integer type 1可用 2 不可用
     * @param integer goodsId 商品Id
     * @ruleId integer sku_id
     * @return json
     */
    public function avaiCoupon()
    {
    	$uid = $this->uid;
    	$all_price = input('request.allPrice');
    	$type = input('request.type');
    	$goods_id = input('request.goodsId');
    	$sku_id = input('request.sku_id');
    	$list = $this->user->avaiCoupon($uid, $all_price, $goods_id, $sku_id, $type);
    	if($list){
    		return $this->json($list);
    	}
    	else return $this->json('', 0, '无优惠券');
    }

	/**
	 * description:我的订单
     * @param int uid
     * @param string token
     * @param int type int ;1，全部；2，待付款；3，待收货；4，已完成；5，已取消
     * @param int page 页数
     * @param int isSeller 店铺订单管理传1,其他情况不传
     * @return json
	 */
	public function orderList()
    {
		//$uid = $this->uid;
		$uid = input('uid');
		$p = input('request.page', 1);
		$type = input('request.type');
		$search = trim(input('request.search'));
		$is_seller = input('request.isSeller') ? input('request.isSeller'): 0;
		$list = $this->order->orderList($uid, $p, $type, $is_seller,$search);
		if ($p == 1) {
            if(!$list['list']){
                return $this->json('', -1, '无列表');
            }
        }
		return $this->json($list);
	}

	/**
	 * description:订单详情
     * @param int uid
     * @param string token
     * @param int orderId 订单id
     * @return json
	 */
	public function orderDetails(){
		$uid = $this->uid;
		$order_id = input('request.orderId');
		$info = $this->order->orderDetails($uid, $order_id);
		return $this->json($info);
	}
  
  
	/**
	 * description:分销返利
     * @param int uid
     * @param string token
     * @param int orderId 订单id
     * @return json
	 */
	public function setfanli(){
		$uid = $this->uid;
		$order_id = input('request.orderId');
		$info = $this->order->goodsCommissionNew($uid, $order_id);
		return $this->json($info);
	}

	/**
	 * description:取消订单
     * @param int uid
     * @param string token
     * @param orderId
     * @return json
	 */
	public function orderCancle(){
		$uid = $this->uid;
		$order_id = input('request.orderId');
		$res = $this->order->orderCancle($uid, $order_id);
		if(!$res){
			return $this->json('', 0, '取消订单失败');
		}
		else return $this->json('', 1, '取消订单成功');
	}

	/**
	 * 付款页面
     * @param integer uid
     * @param string token
     * @param integer orderId
     * @return json
	 */
	public function payWay()
    {
		$uid = $this->uid;
		$order_id = input('request.orderId');
		if(!$uid || !$order_id){
			return $this->json('', 0, '未知参数');
		}
		$list = $this->order->payWay($uid, $order_id);
		if($list == -1){
			return $this->json('', 0, '用户不存在');
		}
		else if($list == -2){
			return $this->json('', 0, '订单不存在');
		}
		return $this->json($list);
	}
	/**
	 * 立即付款
     * @param integer uid
     * @param string token
     * @param integer orderId
     * @param string payCode 余额：balance;微信：wxpay;阿里：alipay;银联：unionpay;他人：trpay
     * @return json
 	 */
	public function orderPay()
    {
		$uid = $this->uid;
		$order_id = input('request.orderId');
		if(!$uid || !$order_id){
			return $this->json('', 1, '未知参数');
		}
		$pay_code = input('request.payCode');
		$result = $this->order->orderPay($uid, $order_id, $pay_code);
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
	}

	/**
	 * description:查看物流
     * @param int uid
     * @param string uid
     * @param int orderId
     * @return json
	 */
	public function postInfo()
    {
		$uid = $this->uid;
		$order_id = input('request.orderId');
		$list = $this->order->postInfo($uid, $order_id);
		return $this->json($list);
	}	
	/**
	 * 查看多个物流
	 */
	public function postInfos(){
		$uid = $this->uid;
		$order_id = input('request.orderid');
		$list = $this->order->postInfos($uid, $order_id);
		
		return $this->json($list);
	}
	/**
	 * 查看多个物流
	 */
	public function postFlow(){
		$order_id = input('request.orderid');
		$og_supplier_id = input('request.og_supplier_id');
		$list = $this->order->postFlow($order_id,$og_supplier_id);
		return $this->json($list);
	}

	/**
	 * description:确认收货
     * @param int uid
     * @param string token
     * @param int orderId
     * @return json
	 */
	public function confirmOrder()
    {
		$uid = $this->uid;
		$order_id = input('request.orderId');
		$res = $this->order->postConfirm($uid, $order_id);
		if(!$res){
			return $this->json('', 0, '确认收货失败');
		} else {
            return $this->json('', 1, '确认收货成功');
        }
	}

	/**
	 * description:删除订单
     * @param int uid
     * @param string token
     * @param int orderId 订单id
     * @param int isSeller 店铺订单管理时传1，其他可以不传该字段
     * @return json
	 */
	public function orderDel()
    {
		$uid = $this->uid;
		$order_id = input('request.orderId');
		$is_seller = input('request.isSeller') ? : 0;
		$res = $this->order->orderDel($uid, $order_id, $is_seller);
		if(!$res){
			return $this->json('', 0, '删除订单失败');
		} else {
            return $this->json('', 1, '删除订单成功');
        }
	}

	/**
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

	/**
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

	/**
	 * 创建大礼包订单
	 */
	public function giftOrder()
    {
		$uid = $this->uid;
		$goods_id = input('request.goodsId');
		$sku_id = input('request.sku_id');
		$addr_id = input('request.addrId');
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

	/**
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

    /**
     *  获取售后申请记录
     */
    public function asRecord()
    {
        $uid = $this->uid;
        $list = $this->order->getasRecord($uid);
        if($list){
            return $this->json($list, 1,'获取成功！');
        }
        return $this->json('', 0, '暂无数据');
    }

    public function asType()
    {
        $uid = $this->uid;
        $type = input('request.type') ? input('request.type') : 1;		//1，换货；2，退货
        $info = $this->order->getasType($uid, $type);
        return $this->json($info);
    }
     /**
      *  获取售后申请详情
      */
    public function asInfo()
    {
        $orderId = input('request.orderId');
        $row = $this->order->getasInfo($orderId);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0, '暂无数据');
    }
   /**
    *  获取售后申请提交
    * 1，申请退货；2，申请换货
	*status 1:订单取消  2 提交售后
    */
    public function asSubmit()
    {
        $uid = $this->uid;
        $type = input('request.type');
        $og_id = input('request.orderId');
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
            if(!$res){
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

        $res  = $this->order->asSubmit($og_id,$data);
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

    /**
     *  获取售后进度信息
     */
    public function  schedule()
    {
        $uid = $this->uid;
        $og_id = input('request.orderId');
        $type_stu = input('request.type_stu');
        $row = $this->order->schedule($og_id, $type_stu);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0,'获取失败！');
    }
    /**
     * 获取售后人 联系方式
     */
    public function  asRelation()
    {
        $order_id = input('request.orderId');
        $row = $this->order->asRelation($order_id);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0,'获取失败！');
    }
	/**
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

	/**
	 * 购买限制
     * @param Intergral uid
     * @param string token
     * @param integer activeId 活动商品id
     * @param integer number 活动
     * @param integer goodsId 商品id
     * @param integer activeType 团购
     * @return json
   	 */
   	public function orderLimit()
    {
		$uid = $this->uid;
		$goods_id = input('request.goodsId');
		$prom_type = input('request.activeType');
		$prom_id = input('request.activeId');
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
      * 数字转化
      */
	public function getBig($num)
    {
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
    /**
    *用户确认收到退回货物
    */
    public function userReciveGoods()
    {
    	$uid = $this->uid;
        $og_id = input('request.orderId');
        $res = Db::name('sh_info')->where(['og_uid'=>$uid,'og_id'=>$og_id])->update(['client_status'=>1,'audit_status'=>5, 'og_order_status' => 4]);
        if($res){
            return $this->json($res, 1,'成功！');
        }
        return $this->json('', 0, '暂无数据');
    }
	/**
     * 用户完成售后
    */
    public function afterSaled()
    {
		$uid = $this->uid;
        $og_id = input('request.orderId');
        $row = $this->order->afterSaled($uid,$og_id);
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0, '暂无数据');
	}
    /**
     * description:积分兑换生成订单信息
     * @param string token
     * @param int uid 用户id
     * @param int goodsId 商品id
     * @param int sku_id 属性id
     * @param int number 购买数量
     * @param int addrId 收货地址id
     * @param int is_invoice 0 不开发票 1开发票
     * @param int invoice_header 1个人2企业
     * @param string invoice_com 公司名称
     * @param string invoice_com_tax 税号
     * @param string remark 备注
     * @return json
     */
    public function pointsOrder()
    {
        if(Request()->post()){
            $data=Request()->post();
            $this->checkEmploy($data,['token','uid','goodsId','number','addrId','is_invoice']);
            //判断用户是否存在
            if(!$this->getUid(Request()->post('token'),Request()->post("uid"))){
                return json(['status'=>-1,'msg'=>'非法操作！','data'=>[]]);exit;
            }
            //获得用户信息
            $userId=Request()->post("uid/d");
            $userInfo=Db::name("users")->where(['user_id'=>$userId])
                ->field("user_id,user_points")->find();
            //获得商品信息
            $goodsId=Request()->post("goodsId/d");
            $goodsNumber=Request()->post("number/d");
            $goodsIntegral=$this->goodsIntegral(['goods_id'=>$goodsId,'stock'=>['gt',0],'status'=>0]);
            $totalIntegral=$goodsNumber*$goodsIntegral['exchange_integral'];
            if($userInfo['user_points']<$totalIntegral) return json(['status'=>-1,'msg'=>'您的积分不足！','data'=>[]]);
            if($totalIntegral<=0) return json(['status'=>-1,'msg'=>'积分为零非法操作！']);
            $stock=$goodsIntegral['stock'];
            $specAttrId='';
            if(Request()->post("sku_id/d")){
                $specSpec= $this->goodsSpecValue(['sku_id'=>Request()->post("sku_id/d"),'goods_id'=>$goodsId]);
                $stock=$specSpec['stock'];
                $specAttrId=$specSpec['specValue'];
            }
            if($stock<$goodsNumber){
                return json(['status'=>-1,'msg'=>'库存不足！','data'=>[]]);
            }
            //查询收货地址信息
            $addressId=Request()->post("addrId/d");
            $address=Db::name("addr")->where(['addr_id'=>$addressId,'a_uid'=>$userId])->field("addr_province,addr_city,addr_area,addr_cont,addr_receiver,addr_phone")->find();
            //进行添加订单
            if($goodsIntegral['freight']>0){
                $orderStatus=0;
                $payTime=time();
                $status=1;
            }else{
                $orderStatus=1;
                $payTime=time();
                $status=0;
            }
            $data=[
                'order_no'=>'HT'.date('ymdhis').rand(1000,9999),
                'order_uid'=>$userId,
                'order_addrid'=>$addressId,
                'order_all_price'=>sprintf("%.2f",$totalIntegral),
                'order_pay_price'=>$goodsIntegral['freight'],
                'order_freight'=>$goodsIntegral['freight'],
                'order_status'=>$orderStatus,
                'pay_status'=>$orderStatus,
                'order_create_time'=>time(),
                'order_pay_time'=>$payTime,
                'order_type'=>1,
                'pro_name'=>$address['addr_province'],
                'city_name'=>$address['addr_city'],
                'area'=>$address['addr_area'],
                'address'=>$address['addr_cont'],
                'consigee'=>$address['addr_receiver'],
                'phone'=>$address['addr_phone'],
                'need_invoice'=>Request()->post("is_invoice/d"),
                'order_pay_code'=>'积分支付',
                'order_pay_points'=>sprintf("%.2f",$totalIntegral),
            ];
            if(Request()->post("is_invoice/d")==1){
                $data['invoice_header']=Request()->post("invoice_header/d");
                if($data['invoice_header']==2){
                    $data['invoice_com']=Request()->post("invoice_com");
                    $data['invoice_com_tax']=Request()->post("invoice_com_tax");
                }
                $data['invoice_type']=Request()->post("invoice_type/d");
            }
            //进行插入操作
            $rs=Db::name("order")->insertGetId($data);
            //插入商品详情表
            $insertGoods=[
                'og_order_id'=>$rs,
                'og_uid'=>$userId,
                'og_goods_id'=>$goodsIntegral['goods_id'],
                'og_goods_name'=>$goodsIntegral['goods_name'],
                'og_goods_spec_id'=>Request()->post("sku_id/d"),
                'og_goods_spec_val'=>$specAttrId,
                'og_goods_num'=>$goodsNumber,
                'og_goods_price'=>$goodsIntegral['exchange_integral'],
                'og_goods_pay_price'=>$goodsIntegral['exchange_integral'],
                'og_acti_id'=>0,
                'og_goods_thumb'=>$goodsIntegral['picture'],
                'og_supplier_id'=>$goodsIntegral['supplier_id']

            ];
            //扣除积分
            $userPoints['user_points']=sprintf("%.2f",$userInfo['user_points']-$totalIntegral);
            //积分明细信息插入
            $integralInsert=[
                'p_uid'=>$userId,
                'point_type'=>-1,
                'point_num'=>"-".$totalIntegral,
                'point_desc'=>'兑换商品消费'.$totalIntegral.'积分',
                'point_add_time'=>time()
            ];
            //订单日志表
            $orderLog=[
                'o_log_orderid'=>$rs,
                'o_log_role'=>$userInfo['user_id'],
                'o_log_desc'=>'创建订单',
                'o_log_addtime'=>time()
            ];
            Db::startTrans();
            try{
                Db::table("ht_order_goods")->insertGetId($insertGoods);
                Db::table('ht_users')->where(['user_id'=>$userId])->update($userPoints);
                Db::table('ht_points_log')->insert($integralInsert);
                Db::table('ht_order_log')->insert($orderLog);
                // 提交事务
                Db::commit();
                return json(['status'=>$status,'msg'=>'提交成功！','data'=>['order_sn'=>$data['order_no'],'pay_money'=>$data['order_pay_price'],'order_id'=>$rs]]);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json(['status'=>-1,'msg'=>'操作失败！']);
            }
            //订单详情表中插入数据
        }
        return json(['status'=>-1,'msg'=>'非法操作！','data'=>'']);
    }

    /**
     * 订单提醒
     * @param int uid
     * @param string token
     * @return json
     */
    public function orderRing()
    {
        $this->user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $this->user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $res  = $this->user->orderRing($uid);
        if($res){
            return $this->json($res, 1, '获取成功');
        }else{
            return  $this->json('', 0, '获取失败');
        }

    }
}
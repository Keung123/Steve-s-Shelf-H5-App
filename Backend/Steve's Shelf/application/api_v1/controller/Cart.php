<?php
namespace app\api\controller;

use app\common\service\Cart as CartService;
use app\common\service\Coupon;
use app\common\service\User as UserService;
use app\common\service\Order as OrderService;
use app\common\service\Goods as GoodsService;
use think\Db;

class Cart extends Common{
    public $uid;
    public function __construct(){
        parent::__construct();
        $token = input('token');
        $uid = input('uid');
        if(!$token || !$uid){
            $ajaxReturn = json_encode(array(['data'=>[],'status'=>0,'msg'=>'参数错误']));
            echo $ajaxReturn;
            exit;
        }
        $user_id = $this->getUid($token, $uid);
        if ($user_id) {
            $this->uid = $user_id;
        } else {
            $ajaxReturn = json_encode(array(['data'=>[],'status'=>-1,'msg'=>'参数错误']));
            echo $ajaxReturn;
            exit;
        }
    }

    /* 
    * 购物车详情
    */
    public function info(){
        $CartModel=new CartService();
        $map['user_id']=$this->uid;
        $list=$CartModel->select($map,'','id desc');
        $data = [];
        $time = [];
        if ($list) {
            foreach ($list as &$val) {
                if($val['prom_id']!=0){
                    $res1 = Db::name('active_type')->where('id',$val['prom_id'])->select();
                    if(!$res1){
                        //购物车删除
                        $CartModel->cartDel($val['id'],$map['user_id']);
                    }
                }
                $res2 = Db::name('goods')->where('goods_id',$val['goods_id'])->select();
                if(!$res2){
                    //购物车删除
                    $CartModel->cartDel($val['id'],$map['user_id']);
                }
            }
            $list=$CartModel->select($map,'','id desc');
            foreach ($list as &$val) {
                $goods_info = $CartModel->getGoodsinfo($val['goods_id']);
                $goods_skuInfo = Db::name('goods_sku')->where(['sku_id' => $val['item_id']])->field('price,image')->find();
				$price = $goods_skuInfo['price'];
                $val['image'] =$goods_skuInfo['image'];  
				$val['picture'] =$goods_info['picture'];
                $val['status'] =$goods_info['status'];
                $val['goods_name'] =$goods_info['goods_name'];
                $val['bargain_price'] = $val['price'];
                $val['price'] = $price;
                $val['vip_price'] =$goods_info['vip_price'];
                $val['show_price'] =$goods_info['show_price'];
                $val['bargain_id'] = $val['active_id'];
				$user = new UserService();
				$val['active_price'] = $this->ActivePrices($val['prom_id'],$val['price'],$val['goods_id']);
				if($val['prom_id'] == 4){
				    // 判断 砍价商品是否到期 过期 自动删除
                    $bar_where = [
                        'end_time' => ['<', time()],
                        'user_id' => $this->uid,
                        'id' => $val['active_id']
                    ];
                    $bargain_info = Db::name('bargain_user')->where($bar_where)->find();
                    if ($bargain_info) {
                        //购物车删除
                        $CartModel->cartDel($val['id'],$val['user_id']);
                        unset($val);
                        // 设置该次 砍价 已过期
                        Db::name('bargain_user')->where(['id' => $bargain_info['id']])->update(['status' => 2]);
                        // 跳过本次循环
                        continue;
                    } else {
                        $val['active_price'] =  $val['bargain_price'];
                        $val['price'] =  $val['bargain_price'];
                        $data[$val['prom_id']][] = $val;
                    }

				}elseif ($val['prom_id'] == 5) {
                    // 判断 该商品是否过期 ， 过期直接删除
                    // 不限时间，如果 后台删除 该商品的活动 则将 购物车列表中将该商品的活动标识取消
//                    $res = $CartModel->getMiaoshainfo($val['goods_id']);
//                    if ($res) {
//                        $time = $res;
//                        $data[$val['prom_id']][] = $val;
//                    }
					$res = $CartModel->getMiaoshainfos($val['goods_id']);
					if($res){
						$time = $res;
					}
                    $data[$val['prom_id']][] = $val;
					$val['miaosha'] = $time;
                }else if($val['prom_id'] == 1){
					// 判断 团购 商品是否过期 ， 过期直接删除
					 $res = $CartModel->getTuangouinfo($val['goods_id']);
					 if($res){
						$time = $res;
                        $data[$val['prom_id']][] = $val; 
					 }else{
						 //购物车删除
						 $CartModel->cartDel($val['id'],$map['user_id']);
					 }
					
				}else if($val['prom_id'] == 6||$val['prom_id'] == 7||$val['prom_id'] == 8){
					// 判断 过期直接删除 6:满199减100;7:满99元3件;8:满2件9折
					 $res = $CartModel->getFullinfo($val['goods_id'],$val['prom_id']);
					 if($res){
                        $data[$val['prom_id']][] = $val; 
					 }else{
						 //购物车删除
						 // $CartModel->cartDel($val['id'],$map['user_id']);
					 }
					
				}else {
                    $data[$val['prom_id']][] = $val;
                }

                $val['active_id'] =$goods_info['prom_id'];
				$val['active_price'] = sprintf('%0.2f', $val['active_price']);
				$val['active_price'] = floatval($val['active_price']);
				$val['vip_price'] = sprintf('%0.2f', $val['vip_price']);
				$val['vip_price'] = floatval($val['vip_price']);
				$val['show_price'] = sprintf('%0.2f', $val['show_price']);
				$val['show_price'] = floatval($val['show_price']);
				$val['price'] = sprintf('%0.2f', $val['price']);
				$val['price'] = floatval($val['price']);
                // $data[$val['prom_id']][] = $val;
            }
            $i=0;
            $datas = [];
            foreach ($data as $key => $val) {
                $datas[$i]['list']=$val;
                $acti_name = $CartModel->getActiName($key);
                $datas[$i]['active_type_name'] = $acti_name;
                $datas[$i]['active_type_id'] = $key;
				if($key == 5){
					$datas[$i]['miaosha'] = $time;
				}else if($key == 1){
					$datas[$i]['tuangou'] = $time;
				}
               
                $i++;
            }
            return $this->json($datas);
        } else {
            return $this->json([], 0, '购物车为空');
        }
    }

    // 获取活动商品价格  （活动id,原价,活动商品表id）
    public function ActivePrices($active_id,$price,$goods_id)
    {
        if($active_id>=9){
            //自定义活动
            $active = Db::name('active_type')->field('active_type,active_type_val')->where('id',$active_id)->find();
            if($active){
                if($active['active_type'] == 1 ){
                    $active_price = $price - $active['active_type_val'];
                }elseif($active['active_type'] == 2){
                    $active_price = ($price * $active['active_type_val'])/100;
                } else {
                    $active_price = $price;
                }
                //减价过大
                if($active_price<0){
                    $active_price = 0;
                }
            }
        }else if($active_id ==1||$active_id ==3||$active_id ==5){

            if($active_id == 1){
                //团购
                $table = 'group_goods';
            }else if($active_id == 3){
                //拼团
                $table = 'team_activity';
            }else if($active_id == 5){
                //秒杀
                $table = 'flash_goods';
            }
            $groups = Db::name($table)->field('price_type,price_reduce')->where('goods_id',$goods_id)->find();
            if($groups){
                if($groups['price_type'] == 0 ){
                    $active_price = $price - $groups['price_reduce'];
                }else{
                    $active_price = ($price * $groups['price_reduce'])/100;
                }
                //减价过大
                if($active_price<0){
                    $active_price = 0;
                }
            }
        }else if($active_id == 2){
            //预售
            $groups = Db::name('goods_activity')->field('deposit')->where('goods_id',$goods_id)->find();
            $active_price = $groups['deposit'];
        }else if($active_id ==6||$active_id ==7||$active_id ==8||$active_id ==4||$active_id ==0){
            //6:满199减100;7:满99元3件;8:满2件9折;4:砍价活动价为原价
            $active_price = $price;
        }
        return $active_price;

    }

    public function getinfo()
    {
        $CartModel=new CartService();
        $map['user_id']=$this->uid;
        $list=$CartModel->select($map);
        return $this->json($list);
    }
    /** 
     * 添加商品到购物车
     */
    public function cartAdd()
    {
        $CartModel=new CartService();
        $goods_id = input('goods_id');
        $sku_id = input('sku_id');
        $uid = $this->uid;
        $num = input('num', 1);
        $type = input('prom_id', 0); // 活动id        
        $s_id = input('s_id', 0);
		if($type == 12){
			//新人专享 只能买一次
			$where = [
				'user_id' => ['eq', $uid]
			];
			$res = Db::name('cart')->where($where)->find();
			if($res){
				 return $this->json([],-3,'新人专享一人仅可参与一次哦～');
			}
			$res = Db::name('order')->where(['order_uid' => $uid, 'order_status' => ['neq', 5]])->find();
			if($res){
				 return $this->json([],-4,'新人专享一人仅可参与一次哦～');
			}
		}
        if($type > 8){
            //购物车商品数量
            //$res = Db::name('cart')->where(['user_id'=>$uid,'goods_id'=>$goods_id])->find();
            //获取活动表中的数据
            $avtive_goods= Db::name('active_goods')->where(['goods_id'=>$goods_id,'active_type_id'=>$type])->find();

            if($avtive_goods){
            //判断库存是否充足
            if($num>$avtive_goods['goods_num']){
                return $this->json([],0,'库存不足');
            }
            }
            
        }
        if($type==1){
            $group_goods= Db::name('group_goods')->where(['goods_id'=>$goods_id])->find();
            if($group_goods){
                if($group_goods['goods_number']<$num){
                    return $this->json([],0,'超出库存限制');
                }
            }

            $cart=Db::name('cart')->where(['goods_id'=>$goods_id,'user_id'=>$uid])->find();
            if($group_goods['goods_number']<$num+$cart['num']){
                    return $this->json([],0,'库存不足');
                }
            $order_goods=Db::name('order_goods')->where(['og_goods_id'=>$goods_id,'og_uid'=>$uid,'og_order_status'=>5])->find();
            if($group_goods['buy_limit']>0){
                 if($num+$order_goods['og_goods_num']+$cart['num']>$group_goods['buy_limit']){
                return $this->json([],0,'超出每人限购数量');
            } 
            }
          
        }

        if($type==0){

             $sku_goods= Db::name('goods_sku')->where(['sku_id'=>$sku_id])->find();
             $cart_num=Db::name('cart')->where(['user_id'=>$uid,'goods_id'=>$goods_id])->find();
             if($num>$sku_goods['stock']-$cart_num['num'])
             {
                return $this->json([],0,'库存不足');
             } 
        }
       
        if ($num < 1) {
            return $this->json([], 0,'数量最少1个');
        }

        $res = $CartModel->addData($sku_id, $goods_id, $uid, $num, $type, $s_id);
		$goods_limit = '一';
		if($type == 5){
			$goods_where = [
				'goods_id' => $goods_id,
			];
			$flash_goods_limit = Db::name('flash_goods')->where($goods_where)->value('buy_limit');
			$goods_limit = $flash_goods_limit?$flash_goods_limit:'1';
			$goods_limit = $this->getBig($goods_limit);
		}
	
        if ($res == -1){
            return $this->json([], 0,'该商品没有活动');
        } else if ($res == -2) {
            return $this->json([], 0,'该活动没有找到该商品');
        } else if ($res == -3) {
            return $this->json([], 0,'库存不足');
        } else if ($res == -4) {
            return $this->json([], 0,'秒杀商品一人只可购买'.$goods_limit.'件!');
		}else if ($res == 1) {
            return $this->json([], 1,'添加成功');
        } else {
            return $this->json([], 0,'添加失败');
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

    /**
     * 删除购物车
     */
    public function cartDelete()
    {
        $cart_ids = input('cart_ids');
        $cart_ids = rtrim($cart_ids,',');
        $CartModel=new CartService();
        $uid = $this->uid;
        $res = $CartModel->cartDel($cart_ids, $uid, true);
        if ($res) {
            return $this->json([], 1,'删除成功');
        } else {
            return $this->json([], 0,'删除失败');
        }
    }
    /**
     * 设置购物车商品数量
     */
    public function cartSetnum()
    {
        $cart_id = input('cart_id');
        $num = input('num');
        $uid = $this->uid;
        if ($num < 1 || !$uid || !$cart_id) {
            return $this->json([], 0,'参数错误');
        }
        $CartModel=new CartService();
        $res = $CartModel->cartSetnum($cart_id, $num, $uid);
        if ($res) {
            return $this->json([], 1,'设置成功');
        } else {
            return $this->json([], 0,'设置失败');
        }
    }

    /**
     * 填写订单页
     */
    public function getCartList()
    {
        $cart_ids = input('cart_ids');
        $cart_ids = rtrim($cart_ids, ',');
        if (!$cart_ids) {
            return $this->json([], 0,'参数错误');
        }
        $uid = $this->uid;
        $CartModel=new CartService();
        $goods_model = new GoodsService();
        $list = $CartModel->getCartList($cart_ids, $uid);
        $addr = $CartModel->getAddr($uid);
		//商品总价
        $yunfei = 0;
		$gross_price=0;
        if ($list) {
            foreach ($list as $key=>$val) {
                $goodsinfo = $CartModel->getGoodsinfo($val['goods_id']);
                $list[$key]['picture'] =$goodsinfo['picture'];
                $list[$key]['goods_name'] =$goodsinfo['goods_name'];
				$gross_prices = $val['price'] * $val['num'];
				$gross_price += $gross_prices;
				$yunfei += $goods_model->getYunfei($val['goods_id'], $val['item_id'], $addr['province_id'], $val['num']);
            }
            $data['list'] = $list;
            $total_arr = $CartModel->getCartPrice($cart_ids, $uid);
            $data['price'] =$total_arr['total'];
            $data['gross_price'] =$gross_price;
            
            // foreach ($list as $key => $value) {
               
            //   // $youhui_01[]= $value['youhui']*$value['num'];  
            //     $youhui_01= $list['youhui']*$value['num'];
            // }
            // $data['youhui']=array_sum($youhui_01);
            $data['youhui']=$total_arr['youhui'];
            $data['is_coupon'] = $CartModel->is_coupon($uid)?1:0;
            // 充值卡
            $recharge = Db::name('user_rc')->where(['card_uid' => $uid, 'card_stat' => 1])->count();
            // 元宝
            $yz = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_stat' => 2])->count();
            // $yz = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_stat' => 2])->select();
            $data['rc'] = $recharge ? 1 : 0;
            $data['yz'] = $yz ? 1 : 0;
            $data['yunfei'] = $yunfei;
            $data['addr'] = $addr;
            return $this->json($data);
        } else {
            return $this->json([], 0,'获取失败');
        }
    }
    /**
     * 提交订单表
     */
    public function setOrder()
    {
        // 购物车id
        $cart_ids = input('cart_ids');
        // 地址id
        $addr = input('addr_id');
        // 用户id
        $uid = $this->uid;
        // 优惠券id
        $coupon_id = input('coupon_id');
        // 发票信息
        $need_invoice = input('need_invoice');
        $invoice_header =input('invoice_header');
        $invoice_com =input('invoice_com');
        $invoice_com_tax =input('invoice_com_tax');
        $invoice_type =input('invoice_type');   // 发票类型
        //商品金额
        $all_price = input('all_price');
        // 用户备注
        $order_remark =input('order_remark');

        $CartModel=new CartService();
        // 判断 活动商品数量
        $limit_goods = $CartModel->cart_limit($cart_ids);
        if ($limit_goods == -1) {
            return $this->json([], 0, '该活动已停止');
        } elseif ($limit_goods[0] == -2) {
            return $this->json([], 0, $limit_goods[1].'活动商品超出购买限制');
        } elseif ($limit_goods == -3) {
            return $this->json([], 0, '秒杀商品未到购买时间');
        }
		// 判断订单商品数量
		$limit_order = $CartModel->corder_limit($cart_ids);
		if ($limit_order[0] == -2) {
			return $this->json([], 0, $limit_order[1].'活动商品超出购买限制');
        }
        // 减去库存
        $res = $CartModel->setStock($cart_ids);
        if ($res['status'] != 1) {
            $goods_info = $CartModel -> getGoodsinfo($res['goods_id']);
            $goods_name = mb_substr($goods_info['goods_name'],0,10,'utf-8');
            return $this->json([], 0, $goods_name.'商品库存不足');
        }
        // 计算运费
        $cart_goods_list = Db::name('cart')->where(['id' => ['in', $cart_ids]])->select();
        $goods_model = new GoodsService();
        $addr_info = Db::name('addr')->where(['addr_id' => $addr])->find();
        $yunfei = 0;
        foreach ($cart_goods_list as $val) {
            $yunfei += $goods_model->getYunfei($val['goods_id'], $val['item_id'], $addr_info['addr_province'], $val['num']);
        }

        // 获取购物车 总价格
        $total_arr = $CartModel->getCartPrice($cart_ids, $uid);
        $total_youhui = $total_arr['youhui'];
//        $total_price = $total_arr['total'] + $yunfei;
        $total_price = $total_arr['total'];
        //计算未使用充值卡、元宝的金额
        $order_commi_price = $total_arr['total'];
        // 实付金额
        $total_pay_price = $total_arr['total'] + $yunfei;
        // 使用的积分
        $order_pay_points =input('order_pay_points', 0);
        if (!$cart_ids) {
            return $this->json([], 0,'参数错误');
        }
        $user = new UserService();
        $userinfo = $user->userInfo(array('user_id' => $uid));
        if ($order_pay_points) {
            $jifen_info = config('setjifen');
            if ($jifen_info['status'] == 0) {
                if ($order_pay_points > $userinfo['user_points']) {
                    $this->json([], 0,'积分不足');
                } else {
                    // 用户减去积分
                    $CartModel->setDecjifen($order_pay_points, $uid);
                    // 积分比率
                    $jifen_bilv = $jifen_info['number']?$jifen_info['number']:0;
                    if ($jifen_bilv) {
                        $jifen_price = round($order_pay_points/$jifen_bilv, 2);
                        $total_pay_price = $total_pay_price - $jifen_price;
                    }
                }
            }

        }


        // 查询优惠券
        if ($coupon_id) {
            $res = $CartModel->getCoupon($coupon_id, $uid);
            if (!$res) {
                return  $this->json([], 0,'优惠券错误');
            } else {
                $coupon_model = new Coupon();
                $coupon_model->saveStatus($coupon_id);
                $total_pay_price -= $res['c_coupon_price'];
//                $order_commi_price += $res['c_coupon_price'];
            }
        }

        
        //使用充值卡
        $rc_id = input('request.rc_id');
        $rc_amount = input('request.rc_amount');
        if($rc_id && $rc_amount){
            $rc_info = Db::name('user_rc')->where(['card_id' => $rc_id, 'card_uid' => $uid])->field('card_no,card_balance')->find();
            if ($rc_info) {
                if($rc_info['card_balance'] > $rc_amount){
                    Db::name('user_rc')->where(['card_id' => $rc_id, 'card_uid' => $uid])->setDec('card_balance' ,  $rc_amount);
                    //充值卡记录
                    $OrderService = new OrderService();
                    $OrderService->add_rc_log($uid,$rc_id,$rc_amount,0);
                }
                else{
                    Db::name('user_rc')->where(['card_id' => $rc_id, 'card_uid' => $uid])->update(['card_stat' => 2, 'card_use_time' => time()]);
                }
                $total_pay_price -= $rc_amount;
//               $order_commi_price += $rc_amount;
            }
        }
        //使用元宝
        $yz_id = input('request.yz_id');
        if($yz_id){
            Db::name('yinzi')->where('yin_id', $yz_id)->update(['yin_stat' => 3, 'yin_use_time' => time()]);
            $yz_log = [
                'y_log_yid' => $yz_id,
                'y_log_uid' => $uid,
                'y_log_desc' => '购买商品抵扣',
                'y_log_addtime' => time(),
            ];
            Db::name('yinzi_log')->insert($yz_log);
            $yz_info = Db::name('yinzi')->where('yin_id', $yz_id)->field('yin_amount')->find(); 
//           $order_commi_price += $yz_info['yin_amount'];
			$total_pay_price -= $yz_info['yin_amount'];	
        }


        // 生成订单
        $order_pay_no = 'JZ'.$uid.mt_rand(1000,9999).time();
        $data = array(
            'order_no' => $order_pay_no,
            'order_uid' => $uid,
            'order_addrid' => $addr,
            'order_remark' => $order_remark,
            'order_all_price' => $all_price,
            'order_pay_price' => $total_pay_price,
            'order_freight' => $yunfei,
            'order_coupon_id' => $coupon_id,
            'order_status' => 0,
            'need_invoice' => $need_invoice,
            'order_create_time' => time(),
            'order_pay_points' => $order_pay_points,
            'invoice_type' => $invoice_type,
            'order_discount' => $total_youhui,
            'order_commi_price' => $order_commi_price,
            'pro_name' => $addr_info['addr_province'],
            'city_name' => $addr_info['addr_city'],
            'area' => $addr_info['addr_area'],
            'address' => $addr_info['addr_cont'],
            'phone' => $addr_info['addr_phone'],
            'consigee' => $addr_info['addr_receiver'],
        );

        if($yz_id) $data['yz_id'] = $yz_id;
        if($rc_id) $data['rc_id'] = $rc_id;
        if($rc_amount) $data['rc_amount'] = $rc_amount;
		
        $cart_info = Db::name('cart')->where(['id' => ['in', $cart_ids]])->field('store_id')->find();
        if($cart_info['store_id']){
            $data['order_storeid'] = $cart_info['store_id'];
        }
		
        if ($need_invoice) {
            $data['invoice_header'] = $invoice_header;
            $data['invoice_com'] = $invoice_com;
            $data['invoice_com_tax'] = $invoice_com_tax;
        }
        $order_id = $CartModel->setOrder($data);
        $order_info = Db::name('order')->where('order_id',$order_id)->find();
        /*// 处理返利
        $os = new OrderService();
        $res = $os->goodsCommission($order_info);*/
        //大礼包购物车筛选
        $bag_where = [];
        $cart_id_arr = explode(',', $cart_ids);
        foreach($cart_id_arr as $v){
            if($v){
                $bag_where[] = $v;
            }
        }
        // $bag_info = Db::name('store_gift_bag')->where(['bag_cart_id' => ['in', implode(',', $bag_where)]])->field('bag_id')->find();
        $bag_info = Db::name('store_bag_log')->where(['log_cart_id' => ['in', implode(',', $bag_where)]])->field('log_id')->find();
        // return $this->json(['data' => $bag_info, 'sql' => Db::name('store_gift_bag')->getLastSql()]);
        if($bag_info){
            // Db::name('store_gift_bag')->where('bag_id', $bag_info['bag_id'])->update(['bag_order_id' => $order_id]);
            Db::name('store_bag_log')->where('log_id', $bag_info['log_id'])->update(['log_order_id' => $order_id]);
        }
		
        $list = $CartModel->getCartList($cart_ids, $uid);
		if(!$list){
			 return $this->json(0, 0,'提交订单失败');
		}
        $list = collection($list)->toArray();
        foreach($list as $v){
            $order_goods_data[$v['prom_id']][] = $v;
        }


        ################################################################
        // 处理 返利商品
        $list = $this->setCartData($order_goods_data);
        // 三维数组 转 二维
        $og_list = [];
        $key = 0;
        foreach ($list as $value) {
            foreach ($value as $v) {
                $og_list[$key] = $v;
                $key ++;
            }
        }
        ##################################################################
        foreach ($og_list as $k => $val) {
            if ($yunfei > 0) {
                $goods_yunfei = $goods_model->getYunfei($val['goods_id'], $val['item_id'], $addr_info['addr_province'], $val['num']);
            }
            $order_goods_arr = array(
                'og_order_id' => $order_id,
                'og_store_id' => $val['store_id'],
                'og_uid' => $uid,
                'og_goods_id' => $val['goods_id'],
                'og_goods_spec_id' => $val['item_id'],
                'og_goods_spec_val' => $val['attr_value'],
                'og_goods_num' => $val['num'],
                'og_goods_price' => $val['price'],
                'og_goods_pay_price' => $val['og_goods_pay_price'] ? $val['og_goods_pay_price'] : $val['price'] * $val['num'],
                'og_acti_id' => $val['prom_id'],
                'og_acti' => 0,
                'og_add_time' => time(),
                'order_commi_price' => $val['og_goods_pay_price'] ? $val['og_goods_pay_price'] : $val['price'] * $val['num'],
                'og_freight' => $goods_yunfei,
                'og_goods_thumb'=>$val['image'],
            );
            // 砍价商品 立即提交时 把 砍价 状态修改
            if ($val['prom_id'] == 4) {
                Db::name('bargain_user')->where(['id' => $val['active_id']])->update(['status' => 1]);
                // 砍价实际支付价格 使用 购物车价格
                $order_goods_arr['og_goods_pay_price'] = Db::name('cart')->where(['id' => $val['id']])->value('price');
            }
            // 团购价格
            if ($val['prom_id'] == 1) {
                $sku_info = Db::name('goods_sku')->where(array('sku_id' => $val['item_id']))->find();
                $active_goods_info = Db::name('group_goods')->where(['goods_id' => $val['goods_id']])->find();
                if ($active_goods_info['price_type'] == 0) {
                    $order_goods_arr['og_goods_pay_price'] = ( $sku_info['price'] - $active_goods_info['price_reduce']) * $val['num'];
                } else {
                    $order_goods_arr['og_goods_pay_price'] = $sku_info['price'] * $active_goods_info['price_reduce'] / 100 * $val['num'];
                }
            }
            // 自定义价格
            if ($val['prom_id'] > 8) {
                $sku_info = Db::name('goods_sku')->where(array('sku_id' => $val['item_id']))->find();
                $act_info = $CartModel->getCustomtActive($val['prom_id']);
                if ($act_info['active_type'] == 1) {
                    $order_goods_arr['og_goods_pay_price'] = ($sku_info['price'] - $act_info['active_type_val']) * $val['num'];
                } elseif ($act_info['active_type'] == 2) {
                    $order_goods_arr['og_goods_pay_price'] = $sku_info['price'] * $act_info['active_type_val'] / 100 * $val['num'];
                } else {
                    $order_goods_arr['og_goods_pay_price'] = $sku_info['price'] * $val['num'];
                }
            }
            // 秒杀价格
            if ($val['prom_id'] == 5) {
                $sku_info = Db::name('goods_sku')->where(array('sku_id' => $val['item_id']))->find();
                $flash_goods_info = Db::name('flash_goods')->where(['goods_id' => $val['goods_id']])->find();

                if ($flash_goods_info['price_type'] == 0) {
                    $order_goods_arr['og_goods_pay_price'] = ($sku_info['price'] - $flash_goods_info['price_reduce'] ) * $val['num'];
                } else {
                    $order_goods_arr['og_goods_pay_price'] = $sku_info['price'] * $flash_goods_info['price_reduce'] / 100 * $val['num'];
                }
            }
            $goods_info = $CartModel->getGoodsinfo($val['goods_id']);
            $order_goods_arr['og_goods_name'] = $goods_info['goods_name'];
//            $order_goods_arr['og_goods_thumb'] = $goods_info['picture'];
            $order_goods_arr['og_supplier_id'] = $goods_info['supplier_id'];
            $order_goods_arr['og_acti'] = $CartModel->getActiName($val['prom_id']);
            $CartModel->addOrderGoods($order_goods_arr);
        }

        // 已购买商品删除购物车
        $CartModel->cartDel($cart_ids, $uid);
        return $this->json(['order_id' => $order_id], 1,'提交订单成功');
    }

    /**
     * 购物车 数据处理
     * @result data 返回 处理好的数据
     */
    public function setCartData($order_goods_data)
    {
        $order_goods_arr = [];
        if ($order_goods_data) {
            foreach ($order_goods_data as $key =>$value) {
                // 满199减100活动
                if ($key == 6) {
                    $prices = [];
                    foreach ($value as $vals) {
                        if ($vals['num'] ==1) {
                            $prices[] = $vals['price'];
                        } else {
                            for ($i=0; $i< $vals['num']; $i++) {
                                $prices[] = $vals['price'];
                            }
                        }
                    }
                    $total = array_sum($prices);

                    if ($total > 199) {
                        $pay_total =  $total - 100;
                        foreach ($value as $k => $vals) {
                            $pay_price = floor($vals['price'] * $vals['num'] / $total * $pay_total * 100) / 100 ;
                            $value[$k]['og_goods_pay_price'] = $pay_price;
                        }
                    }

                } elseif ($key == 7){ // 99元三件活动
                    $prices = [];
                    $count = 0;
                    // 重组 数组
                    $kt_array = [];
                    /*foreach ($value as $vals) {
                        if ($vals['num'] ==1) {
                            $prices[] = $vals['price'];
                            $kt_array[] = $vals;
                        } else {
                            $num = $vals['num'];
                            for ($i=0; $i< $num; $i++) {
                                $prices[] = $vals['price'];
                                $vals['num'] = 1;
                                $kt_array[] = $vals;
                            }
                        }
                    }*/

                    // 数组排序价格 低的在前面
                    $last_names = array_column($value,'price');
                    array_multisort($last_names,SORT_ASC,$value);
                    $temp_arr = [];
                    foreach ($value as $vals) {
                        if ($vals['num'] ==1) {
                            $prices[] = $vals['price'];
                            $kt_array[] = $vals;
                            $temp_arr[] = $vals['price'];
                        } else {
                            $num = $vals['num'];
                            for ($i=0; $i< $num; $i++) {
                                $prices[] = $vals['price'];
                                $temp_arr[] = $vals['price'];
                            }
                            $kt_array[] = $vals;
                        }
                    }
                    $count = count($temp_arr);
                    $value = $kt_array;
                    if ($count == 3) {
                        $total = array_sum($prices);
                        $pay_total = 99;
                        foreach ($value as $k => $vals) {
//                            $pay_price = floor($vals['price'] / $total * $pay_total * 100) / 100 ;
                            $pay_price = floor($vals['num'] * $vals['price'] / $total * $pay_total * 100) / 100 ;
                            $value[$k]['og_goods_pay_price'] = $pay_price;
                        }
                    } elseif ($count > 3) {
//                        $pay_toatal_you = array_slice($prices, 0, 3);
                        $pay_toatal_you = array_slice($temp_arr, 0, 3);
                        $total = array_sum($pay_toatal_you);
                        $pay_total = 99;
                        /*foreach ($value as $k => $vals) {
                            if ($k < 3) {
                                // 前 三个 按照 99 元处理
                                $pay_price = floor($vals['price'] / $total * $pay_total * 100) / 100 ;
                                $value[$k]['og_goods_pay_price'] = $pay_price;
                            }
                        }*/
                        //第一种：最低价商品为3件或3件以上
                        if($value[0]['num']>=3){
                            $value[0]['og_goods_pay_price'] = ($value[0]['num']-3) * $value[0]['price'] + 99;
                            foreach ($value as $k => $vals){
                                if($k==0) continue;
                                $value[$k]['og_goods_pay_price'] = $vals['num'] * $vals['price'];
                            }
                        }
                        //第二种情况，最低价商品为2件情况
                        if($value[0]['num']==2){
                            $value[0]['og_goods_pay_price'] = floor(2*$value[0]['price'] / $total * 99 * 100) / 100 ;
                            $value[1]['og_goods_pay_price'] = 99 - $value[0]['og_goods_pay_price'] + ($value[1]['num']-1)*$value[1]['price'];
                            foreach ($value as $k => $vals){
                                if($k<2) continue;
                                $value[$k]['og_goods_pay_price'] = $vals['num'] * $vals['price'];
                            }
                        }
                        //第三种情况，最低价商品为1件情况 ,第二低价商品为2件及以上
                        if($value[0]['num']==1 && $value[1]['num']>=2){
                            $value[0]['og_goods_pay_price'] = floor(1*$value[0]['price'] / $total * 99 * 100) / 100 ;
                            $value[1]['og_goods_pay_price'] = 99 - $value[0]['og_goods_pay_price'] + ($value[1]['num']-2)*$value[1]['price'];
                            foreach ($value as $k => $vals){
                                if($k<2) continue;
                                $value[$k]['og_goods_pay_price'] = $vals['num'] * $vals['price'];
                            }
                        }
                        //第四种情况，最低价商品为1件情况 ,第二低价商品为1件
                        if($value[0]['num']==1 && $value[1]['num']==1){
                            $value[0]['og_goods_pay_price'] = floor(1*$value[0]['price'] / $total * 99 * 100) / 100 ;
                            $value[1]['og_goods_pay_price'] = floor(1*$value[1]['price'] / $total * 99 * 100) / 100 ;
                            $value[2]['og_goods_pay_price'] = 99 - $value[0]['og_goods_pay_price']- $value[1]['og_goods_pay_price'] + ($value[2]['num']-1)*$value[2]['price'];
                            foreach ($value as $k => $vals){
                                if($k<3) continue;
                                $value[$k]['og_goods_pay_price'] = $vals['num'] * $vals['price'];
                            }
                        }


                    }
                } elseif ($key == 8) { // 满2件打9折活动
                    $prices = [];
                    $count = 0;
                    foreach ($value as $vals) {
                        if ($vals['num'] ==1) {
                            $prices[] = $vals['price'];
                        } else {
                            for ($i=0; $i< $vals['num']; $i++) {
                                $prices[] = $vals['price'];
                            }
                        }
                        $count +=$vals['num'] ;
                    }
                    $total = array_sum($prices);
                    $pay_total = $total * 0.9;
                    if ($count >= 2) {
                        foreach ($value as $k => $vals) {
                            $pay_price = floor($vals['price'] * $vals['num'] / $total * $pay_total * 100) / 100;
                            $value[$k]['og_goods_pay_price'] = $pay_price;
                        }
                    }
                }
                $order_goods_arr[] = $value;
            }
        }
        return $order_goods_arr;
    }

    /*
     * 领取大礼包加入购物车
     */
    public function giftBagCart(){
        $uid = $this->uid;
        $goods_id = input('request.goods_id');
        $sku_id = input('request.sku_id');
        $bag_id = input('request.bag_id');
        if(!$bag_id){
            return $this->json('', 0, '未知参数');
        }
        $cart_service = new CartService();
        $result = $cart_service->giftBagCart($uid, $goods_id, $sku_id, $bag_id);
        if(!$result['code']){
            return $this->json('', 0, $result['msg']);
        }
        return $this->json('', 1, '加入购物车成功');
    }

    /*
     * 大礼包领取接口（新）
     */
    public function giftBag(){
        $uid = $this->uid;
		if(!$uid){
            return $this->json('', -1, '未知参数');
        }
        $share_uid = input('request.share_uid');
        $goods_id = input('request.goods_id');
        $sku_id = input('request.sku_id');
        $cart_service = new CartService();
        $result = $cart_service->giftBag($uid, $share_uid, $goods_id, $sku_id);
        if(!$result['code']){
            return $this->json('', 0, $result['msg']);
        }
        return $this->json('', 1, '加入购物车成功');
    }
}
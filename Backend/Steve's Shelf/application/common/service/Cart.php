<?php
namespace app\common\service;

use app\common\model\Cart as CartModel;
use think\Db;

class Cart extends Base{

	public function __construct(){
		parent::__construct();
		$CartModel=new CartModel();
		$this->model=$CartModel;
	}

    /**
     * 获取商品信息
     */
	public function getGoodsinfo($goods_id) {
	    $goods_info = Db::name('goods')->where(array('goods_id' => $goods_id))->find();
	    return $goods_info;
    }
    /**
     * 获取活动信息
     */
	public function getProminfo($prom_id) {
        $prom_info = Db::name('active_type')->where(array(''))->find();
    }
    /**
     * 根据规则获取价格
     */
    public function getPrice($goods_id, $guize1, $guize2 = null, $guize3= null) {
        $where = array(
            'goods_id' => $goods_id,
        );
        $goods_info = $this->getGoodsinfo($goods_id);
        $spec_info = json_decode($goods_info['spec'], true);
        $keys = array_keys($spec_info);
        if ($guize3 && $guize2 && $guize1) {
            $where['attr_value'] = $keys[0].":".$guize1.";".$keys[1].":".$guize2.";".$keys[2].":".$guize3;
        }else if ($guize2 && $guize1) {
            $where['attr_value'] = $keys[0].":".$guize1.";".$keys[1].":".$guize2;
        } else {
            $where['attr_value'] = $keys[0].":".$guize1;
        }
        $goods_sku_info = Db::name('goods_sku')->field('goods_id,price,sku_id,sku_name,stock,image')->where($where)->find();
        if ($goods_sku_info) {
			$goods = Db::name('goods')->field('prom_type,goods_id,prom_id')->where('goods_id',$goods_sku_info['goods_id'])->find();
			if($goods['prom_type'] == 1){
				//团购
				$goods_sku_info['activity_price'] = $this->calculate('group_goods',$goods_sku_info,$goods['prom_id']);
			}else if($goods['prom_type'] == 3){
				//拼团
				$goods_sku_info['activity_price'] = $this->calculate('team_activity',$goods_sku_info,$goods['prom_id']);	
			}else if($goods['prom_type'] == 5){
				//秒杀
				$goods_sku_info['activity_price'] = $this->calculate('flash_goods',$goods_sku_info,$goods['prom_id']);
                $goods_sku_info['limit_price']=db('flash_goods')->where(['goods_id'=>$goods_id])->value('limit_price');
                 
			}else if($goods['prom_type']>=9){
				//自定义活动
				$active = Db::name('active_type')->field('active_type,active_type_val')->where('id',$goods['prom_type'])->find();
				if($active){
					if($active['active_type'] == 1 ){
						$goods_sku_info['activity_price'] = $goods_sku_info['price'] - $active['active_type_val'];
					}else if ($active['active_type'] == 2 ){
						$goods_sku_info['activity_price'] = ($goods_sku_info['price'] * $active['active_type_val'])/100;	
					}
					//减价过大
					if($goods_sku_info['price']<0){
						$goods_sku_info['activity_price'] = 0;
					}	
				}	
			}
			if ($goods_sku_info['price'] > 0) {
			    $goods_sku_info['show_price'] = $goods_info['show_price'];
                $commission = $this->getCom();
                //开启 返利
                if($commission['shop_ctrl'] == 1){
                    $f_p_rate = $commission['f_s_rate'];
                }else{
                    $f_p_rate = 100;
                }
                $goods_sku_info['dianzhu_price'] = floor($goods_sku_info['price'] * $goods_info['commission'] * $f_p_rate/100)/100;
            } else {
                $goods_sku_info['show_price'] = $goods_info['show_price'];
                $goods_sku_info['dianzhu_price'] = 0;
            }
				$goods_sku_info['price'] = floatval($goods_sku_info['price']);
				$goods_sku_info['dianzhu_price'] = floatval($goods_sku_info['dianzhu_price']);
                if($goods['prom_type'] == 5 && empty($goods_info['commission'])){
                    $goods_sku_info['dianzhu_price'] = 0.01;
                }
				$goods_sku_info['price'] = sprintf('%0.2f', $goods_sku_info['price']);
				$goods_sku_info['price'] = floatval($goods_sku_info['price']);	
				$goods_sku_info['show_price'] = sprintf('%0.2f', $goods_sku_info['show_price']);
				$goods_sku_info['show_price'] = floatval($goods_sku_info['show_price']);	
				$goods_sku_info['vip_price'] = sprintf('%0.2f', $goods_sku_info['vip_price']);
				$goods_sku_info['vip_price'] = floatval($goods_sku_info['vip_price']);	
            return $goods_sku_info;
        } else {
            return [];
        }
    }
	/**
     *减价 折扣计算
     */
	public function calculate($table,$goods_sku_info,$prom_id,$keyid='id'){	
		$where = [
			$keyid => $prom_id
		];
		//0:减价 1：折扣
		$groups = Db::name($table)->field('price_type,price_reduce')->where($where)->find();
		if($groups){
			if($groups['price_type'] == 0 ){
				$goods_sku_info['price'] = $goods_sku_info['price'] - $groups['price_reduce'];
			}else{	
				$goods_sku_info['price'] = ($goods_sku_info['price'] * $groups['price_reduce'])/100;	
			}
			//减价过大
			if($goods_sku_info['price']<0){
				$goods_sku_info['price'] = 0;
			}	
		}	
		return $goods_sku_info['price'];
	}
	
    /**
     * 添加购物车
     */
    public function addData($sku_id, $goods_id, $uid, $num, $prom_id = 0, $s_id = 0,$active_id = 0) {
        $where = [];
        $where['item_id'] = $sku_id;
        $where['goods_id'] = $goods_id;
        $where['user_id'] = $uid;
        $where['prom_id'] = $prom_id;
        $res = $this->model->where($where)->find();

        if ($res) {

			if ($prom_id == 5) {
				$goods_where = [
					'goods_id' => $goods_id,
					'goods_number' => ['gt', 0],
					'is_end' => 0
				];
				// 秒杀商品 未到时间
				$flash_goods_info = Db::name('flash_goods')->where($goods_where)->find();
				if (!$flash_goods_info) {
					return -2;
				}
				$cart_num = Db::name('cart')->where(array('user_id'=>$uid,'goods_id'=>$goods_id))->find();
				$cart_num =$num+$cart_num['num'];
                if($num>=$flash_goods_info['buy_limit']){
                    return -4;
                }
				if($flash_goods_info['buy_limit']<$cart_num){
					return -4;
				}
				$map = [
					'a.og_goods_id'=>$goods_id,
					'a.og_uid'=>$uid,
					'b.order_status'=>['neq',5],
				];
				
				$order_number = Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->where($map)->sum('og_goods_num');
				$order_number  +=  $num;
				if($flash_goods_info['buy_limit']<$order_number){
					return -4;
				}
			}
            return $this->model->where(array('id' => $res['id']))->setInc('num', $num);
        }
        $sku_info = Db::name('goods_sku')->where(array('sku_id' => $sku_id))->find();
        if ($sku_info['stock'] <= 0) {
            return -3;
        }
        $price = 0;
        $show_price = 0;
        if ($prom_id == 5) {
            $goods_where = [
                'goods_id' => $goods_id,
                'goods_number' => ['gt', 0],
                'is_end' => 0
            ];
            // 秒杀商品 未到时间
            $flash_goods_info = Db::name('flash_goods')->where($goods_where)->find();
            if (!$flash_goods_info) {
                return -2;
            }
			$cart_num = Db::name('cart')->where(array('user_id'=>$uid,'goods_id'=>$goods_id))->count();
			$cart_num += $num;
			if($flash_goods_info['buy_limit']<$cart_num){
				return -4;
			}
			$map = [
				'a.og_goods_id'=>$goods_id,
				'a.og_uid'=>$uid,
				'b.order_status'=>['neq',5],
			];
			
			$order_number = Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->where($map)->count();
			$order_number  +=  $num;
			if($flash_goods_info['buy_limit']<$order_number){
				return -4;
			}

            $price = $flash_goods_info['limit_price'];
			$show_price = $price;
        } elseif($prom_id > 8){
            $act_info = $this->getCustomtActive($prom_id);
            if ($act_info['active_type'] == 1) {
                $price = $sku_info['price'] - $act_info['active_type_val'];
            } elseif ($act_info['active_type'] == 2) {
                $price = $sku_info['price'] * $act_info['active_type_val'] / 100;
            } else {
                $price = $sku_info['price'];
            }
            $show_price = $price;
        } elseif($prom_id == 1) {
            $active_where = [
                'goods_id' => $goods_id,
            ];
            $active_goods_info = Db::name('group_goods')->where($active_where)->find();
            $price=$active_goods_info['price'];
            $show_price = $price;
            if (!$active_goods_info) {
                // 该活动没有找到商品
                return -1;
            }
//            if ($active_goods_info['goods_number'] <= $active_goods_info['order_number'] || $active_goods_info['goods_number'] < 1) {
//                return -4;// 库存不足
//            }
            if ($active_goods_info['start_time'] > time() || $active_goods_info['end_time'] < time()) {
                return -2;// 该商品未到活动时间
            }
//            $price = $active_goods_info['group_price'];

            $sku_info = Db::name('goods_sku')->where(array('sku_id' => $sku_id))->find();
            if ($active_goods_info['price_type'] == 0) {
                $price = $sku_info['price'] - $active_goods_info['price_reduce'];
            } else {
                $price = $sku_info['price'] * $active_goods_info['price_reduce'] / 100;
            }
            $show_price = $price;

        } else {
            $price = $sku_info['price'];
            $show_price = $sku_info['show_price'];
        }

//        $goods_info =  Db::name('goods')->where(array('goods_id' => $goods_id))->find();
//        $commission = $goods_info['commission'];
//        $vip_price = round($price * (100 - $commission)/100, 2);
//        $shop_price = $vip_price;  
        $data = array(
            'user_id' => $uid,
            'goods_id' => $goods_id,
            'item_id' => $sku_id,
            'attr_value' => $sku_info['sku_name'],
            'price' => $price,
            'shop_price' => $show_price,
//            'member_price' => $vip_price,
//            'prom_type' => $type,
            'num' => $num,
            'prom_id' => $prom_id,
            'store_id' => $s_id,
            'active_id' => $active_id,
        );
        return $this->model->insert($data);
    }
    /**
     * 获取自定义活动信息
     */
    public function getCustomtActive($act_id)
    {
        return Db::name('active_type')->where(['id' => $act_id])->find();
    }
    /**
     * 删除购物车
     * type true 手动删除 购物车
     */
    public function cartDel($cart_ids, $uid, $type = false) {
        $where = array();
        $where['id'] = array('in', $cart_ids);
        $where['user_id'] = $uid;
        // 先查出 该商品是否为 砍价商品 砍价商品 修改砍价表 状态
        $bargain_list = $this->model->where($where)->where(['prom_id' => 4])->select();
        if ($bargain_list) {
            $bargain_list = collection($bargain_list)->toArray();
            $bar_ids = array_column($bargain_list, 'active_id');
            if ($type) {
                $bar_where = ['id' => ['in', implode($bar_ids)]];
            } else {
                $bar_where = ['id' => ['in', implode($bar_ids)], 'status' => 0];
            }
            Db::name('bargain_user')->where($bar_where)->update(['status' => 2]);

        }
        $res = $this->model->where($where)->delete();

        return $res;
    }
    /**
     * 设置购物车 商品数量
     */
    public function cartSetnum($cart_id, $num, $uid)
    {
        $cart_info = $this->model->where(array('id' => $cart_id, 'user_id' => $uid))->find();
        if($cart_info['prom_id']!=0){
            //判断活动商品的限购数量
            //秒杀
            $goods_info = [];
            if($cart_info['prom_id']==5){
                $goods_info = Db::name('flash_goods')->where(['goods_id' => $cart_info['goods_id']])->find();
            }

            if(!empty($goods_info)){
                if($num>$goods_info['buy_limit']){
                    $this->model->where(array('id' => $cart_id, 'user_id' => $uid))->update(array('num' => $goods_info['buy_limit']));
                    return [0,'当前活动商品最大限购'.$goods_info['buy_limit']];
                }
                else {
                    $this->model->where(array('id' => $cart_id, 'user_id' => $uid))->update(array('num' => $num));
                    return [1,'成功'];
                }
            }
            return [0,'该活动商品失效'];
        }
        else {
            $this->model->where(array('id' => $cart_id, 'user_id' => $uid))->update(array('num' => $num));
            return [1,'成功'];
        }

    }
    /**
     * 预处理订单页
     */
    public function getCartList($cart_ids, $uid)
    {
        $where = array();
        $where['id'] = array('in', $cart_ids);
        $where['user_id'] = $uid;
        $list = $this->model->where($where)->select();
		if($list){
			//商品规格价格 
			foreach($list as $key=>$val){
				$cart = Db::name('cart')->alias('a')
                    ->join('ht_goods b','a.goods_id=b.goods_id')
                    ->join('ht_goods_sku c','a.item_id=c.sku_id')->where('id',$val['id'])->field('a.user_id,a.store_id,a.price,a.num,b.prom_id,c.price,b.picture as image')->find();
                // 2019 0116 修改
				if($cart){
                    $list[$key]['price'] = $cart['price'];
                    $list[$key]['image'] = $cart['image'];
                }

				//                //普通商品
//                if($cart['prom_id']==0){
//
//                    $list[$key]['youhui']=0;
//                }
//                //团购优惠
//                if($cart['prom_id']==1){
//                    $group_goods=Db::name('group_goods')->where('goods_id',$cart['goods_id'])->find();
//                    $list[$key]['youhui']=$group_goods['price_reduce'];
//                }
//                //预售优惠
//                if($cart['prom_id']==2){
//                    $goods_activity=Db::name('goods_activity')->where('goods_id',$cart['goods_id'])->find();
//                    $list[$key]['youhui']=$goods_activity['deposit_use'];
//                }
//                //拼团
//                 if($cart['prom_id']==3){
//                    $team_activity=Db::name('team_activity')->where('goods_id',$cart['goods_id'])->find();
//                    $list[$key]['youhui']=$team_activity['price_reduce'];
//                }
//                //砍价
//                 if($cart['prom_id']==4){
//                    $bargain=Db::name('bargain')->where('goods_id',$cart['goods_id'])->find();
//                    $list[$key]['youhui']=$bargain['price_reduce'];
//                }
//                //抢购/秒杀//
//                 if($cart['prom_id']==5){
//                    $flash_goods=Db::name('flash_goods')->where('goods_id',$cart['goods_id'])->find();
//                    $list[$key]['youhui']=$flash_goods['price_reduce'];
//                }
//                //满199减100 99元3件 满2件打九折
//                 if($cart['prom_id']==6 || $cart['prom_id']==7 || $cart['prom_id']==8){
//                    $full_goods=Db::name('full_goods')->where('goods_id',$cart['goods_id'])->find();
//                    $list[$key]['youhui']=$full_goods['price_reduce'];
//                }
//                //活动商品
//                if($cart['prom_id']>8){
//                     $active_goods=Db::name('active_goods')->where('goods_id',$cart['goods_id'])->find();
//                     $goods=Db::name('goods')->where('goods_id',$cart['goods_id'])->find();
//                      $list[$key]['youhui']=$goods['price']-$active_goods['goods_price'];
//                }
//				if($cart){
//					$list[$key]['price'] = $cart['price'];
//				}
			}
 
		}
	
        return $list;
    }

    /**
     * 获取默认地址
     */
    public function getAddr($uid)
    {
        $address =Db::name('addr')->where(['a_uid' => $uid, 'is_del' => 0])->field('addr_id,addr_province,addr_city,addr_area,addr_cont,addr_receiver,addr_phone,post_no')->order('is_default desc,addr_add_time desc')->find();
        if ($address) {
            if($address['addr_province']){
                $address['province_id'] = $address['addr_province'];
                $address['addr_province'] = $this->getRegion(['region_id' => $address['addr_province']]);
            }
            if($address['addr_city']){
                $address['addr_city'] = $this->getRegion(['region_id' => $address['addr_city']]);
            }
            if($address['addr_area']){
                $address['addr_area'] = $this->getRegion(['region_id' => $address['addr_area']]);
            }
            $address['addr_area'] = $address['addr_province'].' '.$address['addr_city'].' '.$address['addr_area'];
        }
        return $address;
    }
    /*
     * 获得地区名称
     */
    public function getRegion($where){
        $regionName=Db::name("region")->where($where)->value("region_name");
        return $regionName;
    }
    // 验证优惠券
    public function getCoupon($coupon_id, $uid)
    {
        return Db::name('coupon_users')->where(array('c_id' => $coupon_id, 'c_uid' => $uid, 'coupon_stat' => 1))->find();

    }
    // 判断是否拥有优惠价
    public function is_coupon($uid)
    {
        return Db::name('coupon_users')->where(array('c_uid' => $uid, 'coupon_stat' => 1))->find();

    }

    /**
     * 扣除 用户积分
     */
    public function setDecjifen($jifen, $uid)
    {
        return Db::name('users')->where(array('user_id' => $uid))->setDec('user_points',$jifen);
    }
    /**
     * 获取购物总价格
     */
    public function getCartPrice($cart_ids, $uid)
    {

        $user_info = Db::name('users')->field('user_id,is_vip,vip_end_time')->where('user_id',$uid)->find();

        if($user_info['is_vip']==1){
            if($user_info['vip_end_time']<time()){
                $user_info['is_vip']=0;
            }
        }

        $where = array();
        $where['id'] = array('in', $cart_ids);
        $where['user_id'] = $uid;
        $list = $this->model->where($where)->select();
        // 总价
        $total = 0;
        // 优惠价
        $youhui = 0;
        $points = 0;
        if (!empty($list)) {
            $data = [];
            foreach ($list as $val) {
                $data[$val['prom_id']][] = $val;
            }
            foreach ($data as $key =>$value) {
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
                    $price = array_sum($prices);
                    if ($price < 199) {
                        $total = $total + $price;
                    } else {
                        $youhui += 100;
                        $total = $total + $price - 100;
                    }
                } elseif ($key == 7){ // 99元三件活动
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
                    if ($count < 3) {
                        $price = array_sum($prices);
                        $total = $total + $price;
                    } elseif ($count == 3) {
                        $total = $total + 99;
                        $youhui += array_sum($prices) - 99;
                    } else {
                        sort($prices);
                        $pricess = array_slice($prices, 3);
                        $price = array_sum($pricess);
                        $total = $total + 99 + $price;
                        $youhui += array_sum($prices) - $price - 99;
                    }
                }elseif ($key == 8){ // 满2件打9折活动
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
                    $price = array_sum($prices);
                    if ($count < 2) {
                        $total = $total + $price;
                    } else {
                        $total = $total + ceil($price * 9 * 10)/100;
                        $youhui +=  ceil($price * 10)/100;
                    }
                } elseif ($key == 4){//砍价
					$prices = [];
                    foreach ($value as $vals) {
                        if ($vals['num'] ==1) {
                            $prices[] = $vals['price'];
                        } else {
                            for ($i=0; $i< $vals['num']; $i++) {
                                $prices[$i] = $vals['price'];
                            }
                        }
						$cart = Db::name('cart')->where('id',$vals['id'])->field('item_id,price')->find(); 
						if($cart){
							$sku = Db::name('goods_sku')->where('sku_id',$cart['item_id'])->field('price')->find(); 
							 $yhprice = $sku['price'] - $cart['price'];
							 $youhui += $yhprice;
						}
                    }
                    $price1 = array_sum($prices);
                    $total = $total + $price1;
				
					
				}elseif ($key >8){
					$prices = [];
                    foreach ($value as $vals) {
                        if ($vals['num'] ==1) {
                            $prices[] = $vals['price'];
                        } else {
                            for ($i=0; $i< $vals['num']; $i++) {
                                $prices[] = $vals['price'];
                            }
                        }
						$cart = Db::name('cart')->where('id',$vals['id'])->field('item_id,price')->find(); 
						if($cart){
							$sku = Db::name('goods_sku')->where('sku_id',$cart['item_id'])->field('price')->find(); 
							 $yhprice = ($sku['price'] - $cart['price'])* $vals['num'];
							 $youhui += $yhprice;
						}
                    }
                    $price1 = array_sum($prices);
                    $total = $total + $price1;
					

				} else {
                    $prices = [];
                    foreach ($value as $vals) {
                        if ($vals['num'] ==1) {
                            if($user_info['is_vip']==1){
                                $prices[] = $vals['shop_price'];
                                
                            }
                            else {
                                $prices[] = $vals['price'];
                            }
                        } else {
                            for ($i=0; $i< $vals['num']; $i++) {
                                if($user_info['is_vip']==1){
                                    $prices[] = $vals['shop_price'];
                                }
                                else {
                                    $prices[] = $vals['price'];
                                }
                            }
                        }
						$cart = Db::name('cart')->where('id',$vals['id'])->field('item_id,price,shop_price')->find();
						if($cart){
							$sku = Db::name('goods_sku')->where('sku_id',$cart['item_id'])->field('price,integral')->find();
							 $yhprice = $sku['price'] - $cart['price'];
							 $youhui += $yhprice;
                            $points+=$sku['integral'];
						}else{
						    $goods_info = Db::name("goods")->where('goods_is',$vals['goods_id'])->find();
                            $points+=$goods_info['exchange_integral'];
                        }

                    }
                    $price1 = array_sum($prices);
                    $total = $total + $price1;
                }
            }
        }
        $data['total'] = $total;
        $data['youhui'] = $youhui;
        $data['points'] = $points;
        return $data;
    }

    /**
     * 获取活动名称
     */
    public function getActiName($acti_id)
    {
        $acti_info = Db::name('active_type')->where(array('id' => $acti_id))->find();
        return $acti_info['active_type_name'];
    }
    /**
     * 提交订单表
     */
    public function setOrder($data)
    {
        return Db::name('order')->insertGetId($data);
    }
    /**
     * 添加 订单商品表
     */
    public function addOrderGoods($data)
    {
        Db::name('order_goods')->insert($data);
    }
    /**
     * 判断 秒杀商品是否过期，如已过期 直接删除 返回 false 正常 返回过期时间
     * 不用了
     */
    public function getMiaoshainfo($goods_id)
    {
        $goods_info = Db::name('flash_goods')->where(['goods_id' => $goods_id])->find();
        if (!$goods_info) {
            return false;
        }
        $miaosha_where = [
            'status' => 0,
            'id' => $goods_info['flash_id']
        ];
        $miaosha_info = Db::name('flash_active')->where($miaosha_where)->find();
        if (!$miaosha_info) {
            return false;
        }
        $hour = date('H', time());
        if ($miaosha_info['start_time'] <= $hour && $miaosha_info['end_time'] > $hour) {
//            $date_time = strtotime(date('Y-m-d '.$miaosha_info['end_time'].":00", time()));
//            $miao = $date_time-time();
//            return $this->secsToStr(14252);
            $data = [
                'start_time' => $miaosha_info['start_time'].":00",
                'end_time' => $miaosha_info['end_time'].":00"
            ];
            return $data;
        } else {
            return false;
        }

    }
	/**
     * 判断 秒杀商品 返回 时间 2181224
     */
    public function getMiaoshainfos($goods_id)
    {
        $goods_info = Db::name('flash_goods')->where(['goods_id' => $goods_id])->find();
        if (!$goods_info) {
            return false;
        }
        $miaosha_where = [
            'status' => 0,
            'id' => $goods_info['flash_id']
        ];
        $miaosha_info = Db::name('flash_active')->where($miaosha_where)->find();
        if (!$miaosha_info) {
            return false;
        }
        $time = time();
		$data = [
             'start_time' => date('m月d日 H:i',$miaosha_info['start_time']),
             'end_time' => date('m月d日 H:i',$miaosha_info['end_time']),
           ];

        $data['buy_limit']=$goods_info['buy_limit'];
        if ($miaosha_info['start_time'] >= $time) {
           $data['status'] = -1;//暂未开始
        }else if($miaosha_info['end_time'] <= $hour) {
           $data['status'] = 0;//已经结束
        }else{
			$data['status'] = 1;//可以进行
		}
		return $data;
    }
    // 时长
    public function secsToStr($secs) {
        if($secs){
            $r = '';
            $hours=floor($secs/3600);
            $secs=$secs%3600;
            if ($hours) {
                $r=$hours.'小时';
            }
            $fenzhong = 0;
            $miao = 0;
            if($secs>0){
                $fenzhong=floor($secs/60);
                $secs=$secs%60;
                if($fenzhong) {
                    $r.=$fenzhong.'分钟';
                }
            }
            if ($secs > 0) {
                $r.=$secs.'秒';
            }
        } else {
            $r = '';
        }
        return $r;
    }
	
	/**
     * 判断 团购商品是否过期，如已过期 直接删除 返回 false 正常 返回过期时间
     */
	public function getTuangouinfo($goods_id){
		 $where = [
            'status' => 0,
            'id' =>1
        ];
        $res = Db::name('active_type')->where($where)->find();
		if(!(($res['start_time']<=time())&&($res['end_time']>=time()))){
			return false;
		}
		$goods_info = Db::name('group_goods')->where(['goods_id' =>$goods_id])->find();
        if (!$goods_info) {
            return false;
        }
		if($goods_info['start_time']<=time()&&$goods_info['end_time']>=time()){
			$data = [
                'start_time' => $goods_info['start_time'],
                'end_time' => $goods_info['end_time']
            ];
			return $data;
		}
		return false;
	}
	/**
     * 判断 团购商品是否过期，如已过期 直接删除 返回 false 正常  
	 * 6:满199减100;7:满99元3件;8:满2件9折
     */
	public function getFullinfo($goods_id,$act_type){
		 $where = [
            'status' => 0,
            'id' =>$act_type
        ];
        $res = Db::name('active_type')->where($where)->find();
		if(!($res['start_time']<=time() && $res['end_time']>=time())){
			return false;
		}
		 $where = [
            'is_end' => 0,
            'goods_id' =>$goods_id,
			'act_type'=>$act_type
        ];
		$goods_info = Db::name('full_goods')->where($where)->find();
        if (!$goods_info) {
            return false;
        }
		return true;
	}
	
	/**
     * 提交订单减去库存
     * $cart_ids string 购物车id
     */
	public function setStock($cart_ids)
    {
        $list = $this->model->where(['id' => ['in', $cart_ids]])->select();
        if (!empty($list)) {
            Db::startTrans();
            foreach ($list as $val) {
                if ($val['prom_id'] > 0) {
                    if ($val['prom_id'] == 1) { // 团购
                        $group_goods_info = Db::name('group_goods')->where(['goods_id' => $val['goods_id']])->find();
                        if ($group_goods_info['goods_number'] - $group_goods_info['order_number'] < $val['num']) {
                            Db::rollback();
                            return ['status' => -1, 'goods_id' =>$val['goods_id']]; // 活动库存不足
                        } else {
                            Db::name('group_goods')->where(['goods_id' => $val['goods_id']])->setInc('order_number', $val['num']);
                            Db::name('group_goods')->where(['goods_id' => $val['goods_id']])->setDec('goods_number', $val['num']);
                        }
                    } elseif ($val['prom_id'] == 4) {
						$sku_id = $this->getStockku($val['item_id']);
                        if ($sku_id - $group_goods_info['order_number'] < $val['num']) {
                            Db::rollback();
                            return ['status' => -1, 'goods_id' =>$val['goods_id']]; // 活动库存不足
                        } else {
                            Db::name('bargain')->where(['goods_id' => $val['goods_id']])->setInc('order_number', $val['num']);
                            Db::name('bargain')->where(['goods_id' => $val['goods_id']])->setDec('goods_number', $val['num']);
                        }
                    } elseif ($val['prom_id'] == 5) {
                        $group_goods_info = Db::name('flash_goods')->where(['goods_id' => $val['goods_id']])->find();
                        if ($group_goods_info['goods_number'] - $group_goods_info['order_number'] < $val['num']) {
                            Db::rollback();
                            return ['status' => -1, 'goods_id' =>$val['goods_id']]; // 活动库存不足
                        } else {
                            Db::name('flash_goods')->where(['goods_id' => $val['goods_id']])->setInc('order_number', $val['num']);
                            Db::name('flash_goods')->where(['goods_id' => $val['goods_id']])->setDec('goods_number', $val['num']);
                        }
                    } elseif ($val['prom_id'] == 6  || $val['prom_id'] == 7 || $val['prom_id'] == 8) {
                        $group_goods_info = Db::name('full_goods')->where(['goods_id' => $val['goods_id']])->find();
                        if ($group_goods_info['goods_number'] < $val['num']) {
                            Db::rollback();
                            return ['status' => -1, 'goods_id' =>$val['goods_id']]; // 活动库存不足
                        } else {
                            Db::name('full_goods')->where(['goods_id' => $val['goods_id']])->setInc('order_number', $val['num']);
                            Db::name('full_goods')->where(['goods_id' => $val['goods_id']])->setDec('goods_number', $val['num']);
                        }
                    } else {
                        $group_goods_info = Db::name('active_goods')->where(['goods_id' => $val['goods_id']])->find();
                        if ($group_goods_info['goods_num'] - $group_goods_info['order_number'] < $val['num']) {
                            Db::rollback();
                            return ['status' => -1, 'goods_id' =>$val['goods_id']]; // 活动库存不足
                        } else {
                            Db::name('active_goods')->where(['goods_id' => $val['goods_id']])->setInc('order_number', $val['num']);
                            Db::name('active_goods')->where(['goods_id' => $val['goods_id']])->setDec('goods_num', $val['num']);
                        }
                    }
                }
                // 普通商品库存减少
                $goods_sku_info = Db::name('goods_sku')->where(['sku_id' => $val['item_id']])->find();
                if($goods_sku_info){
                    if ($goods_sku_info['stock'] < $val['num']) {
                        Db::rollback();
                        return ['status' => -3,'goods_id' =>$val['goods_id']]; // 商品库存不足
                    } else {
                        Db::name('goods_sku')->where(['sku_id' => $val['item_id']])->setDec('stock', $val['num']);
                    }
                }
                else {
                    $goods_info = Db::name('goods')->where(['goods_id' => $val['goods_id']])->find();
                    if ($goods_info['stock'] < $val['num']) {
                        Db::rollback();
                        return ['status' => -2, 'goods_id' =>$val['goods_id']]; // 商品库存不足
                    } else {
                        Db::name('goods')->where(['goods_id' => $val['goods_id']])->setDec('stock', $val['num']);
                    }
                }



            }
            Db::commit();
        }
        return ['status' => 1,'cart_id' => $val['id']];
    }
    public function cart_limit($cart_ids)
    {
        $list = $this->model->where(['id' => ['in', $cart_ids]])->select();
        if (!empty($list)) {
            foreach ($list as $value) {
                if ($value['prom_id'] > 0) {
                    $res = $this->goods_limit($value['user_id'], $value['goods_id'], $value['prom_id'], $value['num']);
                    if ($res == -1 ) {
                        return $res;
                    }
                    if( $res[0] == -2){
                        return $res;
                    }
                }
                if ($value['prom_id'] == 5) {
                    $is_pay = $this->is_pay($value['goods_id']);
                    if (!$is_pay) {
                        return -3;
                    }
                }
            }
        }
        return 1;
    }
	/**
     * 判断商品是否超过购买限制
     */
	public function corder_limit($cart_ids)
    {
        $status=[];
        $list = $this->model->where(['id' => ['in', $cart_ids]])->select();
        if($list){
			  foreach($list as $val){
				if($val['prom_id'] == 5){
					$flash_goods_info = Db::name('flash_goods')->where('goods_id',$val['goods_id'])->find();
					if($flash_goods_info){
						$map = [
							'a.og_goods_id'=>$val['goods_id'],
							'a.og_uid'=>$val['user_id'],
							'b.order_status'=>['neq',5],
							'b.order_isdel'=>['eq',0],
						];
						$order_number = Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->where($map)->count();
						$order_number  +=  $val['num'];
                        if($flash_goods_info['buy_limit']==0){
                            return 1;
                        }
						if($flash_goods_info['buy_limit']< $order_number){
       //                      $goods_name = mb_substr($flash_goods_info['goods_name'],0,10,'utf-8');
							// $status=['0'=>'-2','goods_name'=>$goods_name];
                            return -2;
						}
					}
				}else if($val['prom_id'] == 1){

					$buy_limit = Db::name('group_goods')->where(array('goods_id'=>$val['goods_id']))->find();
					if($buy_limit){
						$map = [
							'a.og_goods_id'=>$val['goods_id'],
							'a.og_uid'=>$val['user_id'],
							'b.order_status'=>['neq',5],
						];
						$order_number = Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->where($map)->count();
						$order_number  +=  $val['num'];
                        if($buy_limit['buy_limit']==0){
                            return 1;
                        }
						if($buy_limit['buy_limit']<$order_number){
							$goods_name = mb_substr($buy_limit['goods_name'],0,15,'utf-8');
                            $data=['-2',$goods_name];
                            return $data;
						}						
					}
				}
			  }
			  
		  }
		  return 1;
	}
	/**
     * 判断商品是否超过购买限制
     */
	public function goods_limit($uid, $goods_id, $prom_id, $num)
    {
        if (empty($prom_id)) {
            return 1;
        }
        // 查出该活动的限制数量
        $active_where = [];
        $active_where['id'] = $prom_id;
        $active_where['start_time'] = ['lt', time()];
        $active_where['end_time'] = ['gt', time()];
        $active_where['status'] = 0;
        if($prom_id==1){
            $group_goods=Db::name('group_goods')->where(['goods_id' => $goods_id])->find();
             if (empty($group_goods)) {
            return -1;
        }
        // 查出订单表 该商品的数量
        $order_goods_list = Db::name('order_goods')->where(['og_uid' => $uid,'og_goods_id' => $goods_id, 'og_acti_id' => $prom_id, 'og_add_time' => ['gt', $active_info['start_time']]])->select();
        if (!empty($order_goods_list)) {
            $goods_num = 0;
            foreach ($order_goods_list as $val) {
                // 先查询订单状态
                $order_info = Db::name('order')->where(['order_id' => $val['og_order_id']])->find();
                // 取消订单 不算 数量
                if ($order_info['order_status'] != 5) {
                    $goods_num += $val['og_goods_num'];
                }
            }
            if ($group_goods['buy_limit'] - $goods_num < $num) {
                $goods_name = mb_substr($group_goods['goods_name'],0,15,'utf-8');
                $data=['-2',$goods_name];
                return $data;
            }
        } else {
            // 没有下过订单
            $data=['-1'];
            return  $data;
        }
        }
        $active_info = Db::name('active_type')->where(['id' => $prom_id])->find();
        if (empty($active_info)) {
            return -1;
        }
        // 查出订单表 该商品的数量
        $order_goods_list = Db::name('order_goods')->where(['og_uid' => $uid,'og_goods_id' => $goods_id, 'og_acti_id' => $prom_id, 'og_add_time' => ['gt', $active_info['start_time']]])->select();
        if (!empty($order_goods_list)) {
            $goods_num = 0;
            foreach ($order_goods_list as $val) {
                // 先查询订单状态
                $order_info = Db::name('order')->where(['order_id' => $val['og_order_id']])->find();
                // 取消订单 不算 数量
                if ($order_info['order_status'] != 5) {
                    $goods_num += $val['og_goods_num'];
                }
            }
            if ($active_info['limit_num'] - $goods_num < $num && $active_info['limit_num']!=0) {
                $goods_name = mb_substr($order_goods_list['og_goods_name'],0,15,'utf-8');
                $data=['-2',$goods_name];
                return $data;
            }
        } else {
            // 没有下过订单
            return 1;
        }
    }
    /*
     * 判断 秒杀 商品是否可买
     */
    public function is_pay($goods_id)
    {
        $where = [
            'a.goods_id' => $goods_id,
            'a.is_end' => 0,
            'b.status' => 0,
            'b.start_time' => ['<', time()]
        ];
        $info = Db::name('flash_goods')->alias('a')
            ->join('__FLASH_ACTIVE__ b', 'a.flash_id=b.id')
            ->where($where)
            ->field('a.flash_id')
            ->find();
        if ($info) {
            return true;
        } else {
            return false;
        }

    }
	
    /*
     * 领取大礼包加入购物车
     */
    public function giftBagCart($uid, $goods_id, $sku_id, $bag_id){
        $user_info = Db::name('users')->where('user_id', $uid)->field('user_name,is_seller')->find();
        if($user_info['is_seller']){
            return ['code' => 0, 'msg' => '您已经是店主'];
        }
        // 大礼包信息
        $bag_info = Db::name('store_gift_bag')->where(['bag_id' => $bag_id])->field('bag_id')->find();
        if(!$bag_info){
            return ['code' => 0, 'msg' => '未找到礼包信息'];
        }

        // 领取记录
        $log_info = Db::name('store_bag_log')->where(['log_bag_id' => $bag_id, 'log_uid' => $uid])->field('log_id')->find();
        if($log_info){
            return ['code' => 0, 'msg' => '您已领取该礼包'];
        }

        $goods_info = Db::name('goods_sku')->alias('a')->join('__GOODS__ b', 'a.goods_id=b.goods_id')->where(['a.sku_id' => $sku_id, 'a.goods_id' => $goods_id])->field('a.price,a.stock,a.sku_name,b.goods_name,b.picture,b.status')->find();
        if($goods_info['status'] != 0){
            return ['code' => 0, 'msg' => '商品已下架'];
        }
        if(!$goods_info['stock']){
            return ['code' => 0, 'msg' => '商品库存不足'];
        }
        
        // if($bag_info['bag_invite_uid'] == $uid){
        //     return ['code' => 0, 'msg' => '您已领取该礼包'];
        // }
        // if($bag_info['bag_buy_stat'] != 0){
        //     return ['code' => 0, 'msg' => '大礼包已被领取'];
        // }

        $insert = [
            'user_id' => $uid,
            'goods_id' => $goods_id,
            'item_id' => $sku_id,
            'attr_value' => $goods_info['sku_name'],
            'price' => $goods_info['price'],
            'prom_type' => 0,
            'prom_id' => 0,
            'num' => 1
        ];
        $res = Db::name('cart')->insert($insert);
        $cart_id = Db::name('cart')->getLastInsId();
        //更新大礼包信息
        // Db::name('store_gift_bag')->where('bag_id', $bag_id)->update(['bag_uid' => $uid, 'bag_cart_id' => $cart_id, 'bag_buy_stat' => 1]);
        $log_insert = [
            'log_bag_id' => $bag_id,
            'log_uid' => $uid,
            'log_get_time' => time(),
            'log_cart_id' => $cart_id
        ];
        Db::name('store_bag_log')->insert($log_insert);
        if($res !== false){
            return ['code' => 1, 'msg' => '加入购物车成功'];
        }
        else return ['code' => 0, 'msg' => '加入购物车失败'];
    }
	
	/*
     * 大礼包领取接口（新）
     */
    public function giftBag($uid, $share_uid, $goods_id, $sku_id){
        $user_info = Db::name('users')->where('user_id', $uid)->field('user_name,is_seller')->find();
        if($user_info['is_seller']){
            return ['code' => 0, 'msg' => '您已经是店主'];
        }

        // 领取记录
        $log_info = Db::name('store_bag_log')->where(['goods_id' => $goods_id, 'log_uid' => $uid])->field('log_id')->find();
        if($log_info){
            return ['code' => 0, 'msg' => '您已领取该礼包'];
        }

        $goods_info = Db::name('goods_sku')->alias('a')->join('__GOODS__ b', 'a.goods_id=b.goods_id')->where(['a.sku_id' => $sku_id, 'a.goods_id' => $goods_id])->field('a.price,a.stock,a.sku_name,b.goods_name,b.picture,b.status')->find();
        if($goods_info['status'] != 0){
            return ['code' => 0, 'msg' => '商品已下架'];
        }
        if(!$goods_info['stock']){
            return ['code' => 0, 'msg' => '商品库存不足'];
        }

        $insert = [
            'user_id' => $uid,
            'goods_id' => $goods_id,
            'item_id' => $sku_id,
            'attr_value' => $goods_info['sku_name'],
            'price' => $goods_info['price'],
            'prom_type' => 0,
            'prom_id' => 0,
            'num' => 1
        ];
        $res = Db::name('cart')->insert($insert);
        $cart_id = Db::name('cart')->getLastInsId();
        //更新大礼包信息
        // Db::name('store_gift_bag')->where('bag_id', $bag_id)->update(['bag_uid' => $uid, 'bag_cart_id' => $cart_id, 'bag_buy_stat' => 1]);
        $log_insert = [
            'goods_id' => $goods_id,
            'log_uid' => $uid,
            'share_uid' => $share_uid,
            'log_get_time' => time(),
            'log_cart_id' => $cart_id
        ];
        Db::name('store_bag_log')->insert($log_insert);
        if($res !== false){
            return ['code' => 1, 'msg' => '加入购物车成功'];
        }
        else return ['code' => 0, 'msg' => '加入购物车失败'];

    }
	/*
     * 获取属性  库存
     */
    public function getStockku($sku_id){
		$sku_id = Db::name('goods_sku')->where('sku_id',$sku_id)->value('stock');
		return $sku_id;
	}
}
<?php
namespace app\common\service;

use think\Db;
use app\common\model\Order as OrderModel;
use app\common\service\Goods as GoodsService;
use app\common\service\User as UserService;
use app\common\service\Config as ConfigService;
use app\common\service\Store as StoreService;
use app\common\model\Goods as GoodsModel;
use app\common\service\ApiPay as ApipayService;

class Order extends Base{

    public function __construct(){
        parent::__construct();
        $OrderModel = new OrderModel();
        $this->model = $OrderModel;
    }
public function kuaidi100($type,$num){
     $post_data = array();
    $post_data["customer"] = 'D5BDFB445F778BE6EEE928980E1E07AD';
    $key= 'PIWpMxdt8793' ;
    $post_data["param"] = '{"com":"'.$type.'","num":"'.$num.'"}';

    $url='http://poll.kuaidi100.com/poll/query.do';
    $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
    $post_data["sign"] = strtoupper($post_data["sign"]);
    $o="";
    foreach ($post_data as $k=>$v)
    {
        $o.= "$k=".urlencode($v)."&";       //默认UTF-8编码格式
    }
    $post_data=substr($o,0,-1);
    $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        // curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_TIMEOUT,3);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $result = curl_exec($ch);
        curl_close($ch);
        $data = str_replace("\"",'"',$result);
       return $data;

}

    /*
    * 生成订单号
    */
    public function createOrderNo(){
        $order_no = 'JZ'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $is_exist = $this->model->where('order_no', $order_no)->field('order_no')->find();
        while($is_exist){
            $order_no = $this->createOrderNo();
        }
        return $order_no;
    }

    /*
     * 获取订单列表
     */
    public function getOrderList($map, $field = '*'){
        return parent::select($map, $field);
    }

    /*
    * 获取订单金额
    */
    public function getOrderPrice($goods){
        if(!$goods||!is_array($goods)){
            return 0;
        }
        $total=0;
        $goods_sku=Db::name('goods_sku');
        foreach ($goods as $value) {
            $price=$goods_sku->where('sku_id',$value['sku_id'])->value('price');
            $total+=$price*$value['num'];
        }
        return $total;
    }

    /*
    * 获取订单信息
    */
    public function select($map=[],$field="*",$order="",$limit="",$join=""){
        $res=parent::select($map,$field,$order,$limit,$join);
        return $res;
    }
    /*
    * 分页获取订单信息
    */
    public function paginate($map=[],$field="*",$order="",$pageSize=10,$join=""){
        $res=parent::paginate($map,$field,$order,$pageSize,$join);
        foreach ($res as &$value) {
            $value['status_name']=$value->status_name;
            $value['goods']=$value->goods;

        }
        return $res;
    }

    /**
     * 创建订单
     * @param activity 活动类型
     * @param activity_id 活动id
     */
    public function orderCreate($uid, $goods_id, $goods_num, $addr_id, $invoice, $all_price, $pay_price, $freight, $sku_id, $store_id, $dis_total, $points = 0, $coupon_id = 0, $activity_id = 0, $rc_amount = 0.00, $rc_id = 0, $yz_id = 0,$giving_id=0, $yushou_id = 0,$pick_status=0,$order_remark='',$order_gift='',$distance=0,$stock_id=0){
        $goods = new GoodsService();
        $user = new UserService();
        if($goods_id){
            $goods_info = $goods->goodsDetail($goods_id);
            $user_info = $user->userInfo(['user_id' => $uid], 'user_name,user_account,user_points');
            $sku_info = Db::name('goods_sku')->where('sku_id', $sku_id)->field('sku_name,stock,price,image,integral')->find();
            //购买开店大礼包
            if($goods_info['is_gift'] == 1){
               /*  $check = Db::name('users')->where(['user_id' => $uid])->value('is_seller');
                if($check){
                    return -5;
                } */
				$where = [
					'a.order_uid'=>$uid,
					'a.order_status'=>0,
					'c.is_gift'=>1,
				];
				$orderInfo = Db::name('order')
					->where($where)
					->alias('a')
					->join('order_goods b','a.order_id=b.og_order_id')
					->join('goods c','b.og_goods_id=c.goods_id')
					->field('b.og_goods_id,c.is_gift')
					->find();
				//201812301809
				/* if($orderInfo||(!$giving_id)){
					return -6;
				} */
				if($orderInfo){
					return -6;
				}
            }
			 $prom_type =$goods_info['prom_type'];
			// $exchange_integral = $goods_info['exchange_integral'];
            if ($sku_info){
                $exchange_integral = $sku_info['integral'];
            }else{
                $exchange_integral = $goods_info['exchange_integral'];
            }
			 //新人专享  已付款 未支付
			if( $prom_type == 12){
				$res = Db::name('order')->where(['order_uid' => $uid, 'order_status' => ['neq', 5]])->find();
				if($res){
					return -7;
				}
			}
            //活动商品
            // if($prom_type > 8){
            //     //订单表未付款商品库存
            //     // $order_goods_num = Db::name('order_goods')->where(['og_goods_id' => $goods_id, 'og_order_status' => 0])->count();
            //     //购物车表商品数量
            //     //$cart_num=Db::name('cart')->where(['goods_id'=>$goods_id,'user_id'=>$uid])->count();
            //     //活动表商品数量
            //     $active_goods=Db::name('active_goods')->where(['goods_id'=>$goods_id,'active_type_id'=>$activity_id])->find();
            //     $shen_num=$active_goods['goods_num'];
            //     if($goods_num>$shen_num){
            //         // return -2;
            //     }
            // }
            //普通商品限制
            if($prom_type==0){
                $goods_sku=Db::name('goods_sku')->where('sku_id', $sku_id)->find();
                if($goods_num>$goods_sku['stock']){
                    return -2;
                }
            }
            if($prom_type==2){
                $acti_info = Db::name('goods_activity')->where('goods_id', $goods_id)->field('act_id,act_name,goods_id,spec_id,goods_name,start_time,end_time,is_finished,ext_info,act_count,deposit_use,deposit,price,total_goods')->find();
                $data=Db::name('active_type')->where(['id'=>2])->find();
                $order_goods=Db::name('order_goods')->where(['og_goods_id'=>$goods_id,'og_uid'=>$uid,'og_order_status'=>['neq',5]])->column('og_goods_num');
                $order_num=array_sum($order_goods);
                 if($goods_num+$order_num>$data['limit_num'] && $data['limit_num']!=0   ){
                    return -11;
                }
                if($goods_num>$acti_info['total_goods']){
                    return -2;
                }

            }

            //拼团商品限制
              if($prom_type==3){
                $team_activity=Db::name('team_activity')->where('goods_id', $goods_id)->find();
                $data=Db::name('active_type')->where(['id'=>3])->find();
                $order_goods=Db::name('order_goods')->where(['og_goods_id'=>$goods_id,'og_uid'=>$uid,'og_order_status'=>['neq',5]])->column('og_goods_num');
                $order_num=array_sum($order_goods);

                 if($goods_num+$order_num>$data['limit_num'] && $data['limit_num']!=0){
                    return -11;
                }
                if($team_activity['goods_number']<=0){
                    return -9;
                }
                if($goods_num*$team_activity['need_num']>$team_activity['goods_number']){
                    return -9;
                }//查询订单表中拼团数量
                 // $team_activity=Db::name('order_goods')->where(['og_goods_id'=>$goods_id,'og_uid'=>$uid,'og_order_status'=>' 0'])->find();
                 // if($team_activity){
                 //    return -10;
                 // }
            }
			//秒杀限制
			if($prom_type == 5){
				$prom_id = Db::name('goods')->where('goods_id',$goods_id)->value('prom_id');
				$goods_where = [
					'goods_id' => $goods_id,
					'id' => $prom_id,
				];

				// 秒杀商品 未到时间
				$flash_goods_info = Db::name('flash_goods')->where($goods_where)->find();
				$map = [
					'a.og_goods_id'=>$goods_id,
					'a.og_uid'=>$uid,
					'b.order_status'=>['neq',5],
				];
				$order_number = Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->where($map)->count();
				$order_number  +=  $goods_num;
                if($flash_goods_info['buy_limit']>0){
                    if($flash_goods_info['buy_limit']<$order_number){
                    return -8;
                }
                }

			}
            if($prom_type==1){
                $group_goods= Db::name('group_goods')->where(['goods_id'=>$goods_id])->find();
                $order_goods=Db::name('order_goods')->where(['og_goods_id'=>$goods_id,'og_uid'=>$uid,'og_order_status'=>['neq',5]])->column('og_goods_num');
                $order_num=array_sum($order_goods);
            if($group_goods['buy_limit']>0){
                 if($goods_num+$order_num>$group_goods['buy_limit']){
                return -11;
            }
            if($goods_num>$group_goods['goods_number'] || $group_goods['goods_number']<=0){
                return -2;
            }
            }
        }
            if($prom_type ==4){
              $prom_id = Db::name('goods')->where('goods_id',$goods_id)->value('prom_id');
                $goods_where = [
                    'goods_id' => $goods_id,
                    'id' => $prom_id,
                ];

                //砍价商品
                $bargain_goods_info = Db::name('bargain')->where($goods_where)->find();
                $map = [
                    'a.og_goods_id'=>$goods_id,
                    'a.og_uid'=>$uid,
                    'b.order_status'=>['neq',5],
                ];
                $order_number = Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->where($map)->count();
                $order_number  +=  $goods_num;
                //砍价活动商品库存限制
                if($bargain_goods_info['goods_number']>$order_number){
                    return -2;
                }
            }
            //99元3件限制
            if($prom_type==7){
                 $avtive = Db::name('active_type')->where(['id'=>$prom_type])->find();
                 $order_goods=Db::name('order_goods')->where(['og_acti_id'=>$prom_type,'og_uid'=>$uid,'og_order_status'=>['neq',5]])->column('og_goods_num');
                 $order_num=array_sum($order_goods);
                 if($goods_num+$order_num>$avtive['limit_num'] && $avtive['limit_num']!=0){
                    return -11;
                 }

            }
            if(!$sku_info){
                $sku_info = $goods_info;
            }
            if($goods_info != -1){
                $order_no = $this->createOrderNo();

                Db::startTrans();
                try{
                    //库存不足
                    if($sku_info['stock'] - $goods_num < 0){

                        return -2;
                    }
                    //使用积分
                    if($points){

                        if($user_info['user_points'] - $points < 0){
                            return -3;
                        }
                        else{
                            Db::name('users')->where('user_id', $uid)->update(['user_points' => $user_info['user_points'] - $points]);
                            $order_pay_dedu = $points;

                            // 积分比率
                            $jifen_info = Db::name('config')->value('setjifen');
                            $jifen_info = json_decode($jifen_info,true);
                            if ($jifen_info['status'] == 0) {
                                $jifen_bilv = $jifen_info['number'] ? $jifen_info['number'] : 0;
                                if ($jifen_bilv) {
                                    $jifen_price = round($points / $jifen_bilv,2);
                                    $pay_price = $pay_price - $jifen_price;

                                    //
                                    $points = $jifen_price;
                                }
                            }
                        }
                    }

                    //计算未使用充值卡、元宝的金额
                   // $order_commi_price = $pay_price;
                    //运费
                  $freight = floatval($freight);
                  $all_price = floatval($all_price);
                    $pay_price = floatval($pay_price);
                    if($freight){
                       $all_price = bcadd($all_price,$freight,2);
                       $pay_price = bcadd($pay_price,$freight,2);
                    }


                    //使用优惠券
                    if($coupon_id){
                        $c_coupon_info = Db::name('coupon_users')->where('c_id', $coupon_id)->find();

                        //商品券
                        if($c_coupon_info['c_coupon_type']==1){
                            if($goods_info['goods_id']==$c_coupon_info['coupon_type_id']){
                                $c_coupon_price = $c_coupon_info['c_coupon_price'];
                                $pay_price = bcsub($pay_price,$c_coupon_price,2);
                                Db::name('coupon_users')->where('c_id', $coupon_id)->update(['coupon_stat' => 2, 'update_time' => time()]);
                            }
                        }
                        else {
                            $c_coupon_price = $c_coupon_info['c_coupon_price'];
                                $pay_price = bcsub($pay_price,$c_coupon_price,2);
                                Db::name('coupon_users')->where('c_id', $coupon_id)->update(['coupon_stat' => 2, 'update_time' => time()]);
                        }
                    }

                    //使用充值卡
                    if($rc_id && $rc_amount){
                        $rc_info = Db::name('user_rc')->where('card_id', $rc_id)->field('card_no,card_balance')->find();
                        if($rc_info['card_balance'] > $rc_amount){
                            Db::name('user_rc')->where('card_id', $rc_id)->update(['card_balance' => $rc_info['card_balance'] - $rc_amount]);
                            //$uid,$card_no,$price,$describe
                            $this->add_rc_log($uid,$rc_id,$rc_amount,0);
                        }
                        else{
                            Db::name('user_rc')->where('card_id', $rc_id)->update(['card_stat' => 2,'card_balance' => 0, 'card_use_time' => time()]);
                        }
                       // $order_commi_price += $rc_amount;
                    }
                    //使用元宝
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
                       // $order_commi_price += $yz_info['yin_amount'];
                    }
                    $addInfo = Db::name('addr')->where('addr_id',$addr_id)->field('addr_province,addr_city,addr_area,addr_cont,addr_receiver,addr_phone')->find();
                    //新人专享  已付款 未支付
                    if( $prom_type == 12){
                        //$order_commi_price = 0;
                    }

                    //返回积分金额
                    if($prom_type==0){
                        $order_commi_price = $exchange_integral*$goods_num;
                    }else{
                        $order_commi_price = 0;
                    }
                  $pay_price=($pay_price<0)?0:$pay_price;
                    //订单表
                    $data_order = [
                        'order_no' => $order_no,
                        'order_uid' => $uid,
                        'order_addrid' => $addr_id,
                        'order_all_price' => $all_price,
                        'order_pay_price' => $pay_price,
                        'order_freight' => $freight,
                        'order_coupon_id' => $coupon_id,
                        'order_status' => 0,
                        'pay_status' => 0,
                        'pro_name' => $addInfo['addr_province'],
                        'city_name' => $addInfo['addr_city'],
                        'area' => $addInfo['addr_area'],
                        'address' => $addInfo['addr_cont'],
                        'phone' => $addInfo['addr_phone'],
                        'consigee' => $addInfo['addr_receiver'],
                        'order_prom_id' => $activity_id,
                        'need_invoice' => $invoice['need_invoice'],
                        'order_create_time' => time(),
                        'order_pay_points' => $points,
                        'order_pay_dedu' => $order_pay_dedu,
                        'seller_isdel' => 0,
                        'order_discount' => $dis_total,
                        'order_commi_price' => $order_commi_price,
                        'giving_id' => $giving_id,
                        'pick_status' => $pick_status ? $pick_status : 0,
                        'order_remark' =>$order_remark,
                        'order_gift' =>$order_gift,
                      'distance' =>$distance,
                      'stock_id' =>$stock_id
                    ];
                    if($yz_id) $data_order['yz_id'] = $yz_id;
                    if($rc_id) $data_order['rc_id'] = $rc_id;
                    if($rc_amount) $data_order['rc_amount'] = $rc_amount;

                    if($store_id){
                        $data_order['order_storeid'] = $store_id;
                    }
                    if($invoice['need_invoice']){
                        $data_order['invoice_header'] = $invoice['invoice_header'];
                        $data_order['invoice_com'] = $invoice['invoice_com'];
                        $data_order['invoice_com_tax'] = $invoice['invoice_com_tax'];
                    }
                    //查看活动
                    $data_og_acti = [];
                    $data_ol_acti = [];

                    //预售
                    if($goods_info['prom_type'] == 2){
                        $acti_info = Db::name('goods_activity')->where('goods_id', $goods_id)->field('act_id,act_name,goods_id,spec_id,goods_name,start_time,end_time,is_finished,ext_info,act_count,deposit_use,deposit,price')->find();
                        // if($acti_info['is_finished'] || $acti_info['start_time'] > time() || $acti_info['end_time'] < time())
                        //活动信息不存在
                        if(!$acti_info){
                            return -4;
                        }
                        $data_order['order_refund_price'] = $acti_info['price'] - $acti_info['deposit_use'] + $freight;
                        $extension = unserialize($acti_info['ext_info']);
                        if($goods_num>$extension['total_goods']){
                            return -2;
                        }
                        $acti_type = Db::name('active')->where('active_title', '预售')->field('id')->find();
                        if($acti_type){
                            $data_order['order_prom_type'] = $acti_type['id'];
                        }
                        $data_order['order_prom_id'] = $acti_info['act_id'];
                        //order_goods表
                        $data_og_acti['og_acti_id'] = $activity_id;
                        $data_og_acti['og_acti'] = $acti_info['act_name'];
                        //order_log表
                        $data_ol_acti['o_log_desc'] = '创建了预售订单';

                        Db::name('goods_activity')->where('act_id', $acti_info['act_id'])->setInc('act_count', $goods_num);
                    }
                    // else if($goods_info['prom_type'] == )
                    $res_order = $this->model->insert($data_order);
                    $order_info = $this->model->where('order_no', $order_no)->field('order_id')->find();
                    //订单商品表
                    $data_og = [
                        'og_order_id' => $order_info['order_id'],
                        'og_uid' => $uid,
                        'og_goods_id' => $goods_id,
                        'og_goods_name' => $goods_info['goods_name'],
                        'og_goods_spec_id' => $sku_id,
                        'og_goods_spec_val' => $sku_info['sku_name'],
                        'og_goods_num' => $goods_num,
                        // 'og_goods_price' => $goods_info['price'],
						//20190103 修改  （订单详情价格与）
                        'og_goods_price' => $sku_info['price'],

                        'og_goods_pay_price' => $pay_price + $rc_amount,
                       // 'og_goods_thumb' => $sku_info['image'],
                        'og_goods_thumb' => $goods_info['picture'],
                        'order_commi_price' => $order_commi_price,
                        'og_supplier_id' => $goods_info['supplier_id'],
                        'og_acti_id' => $goods_info['prom_type'] ? $goods_info['prom_type'] : 0,
                        'og_freight' => $freight,
                        'og_add_time' => time()
                    ];

                    if($store_id){
                        $data_og['og_store_id'] = $store_id;
                    }

                    $res_og = Db::name('order_goods')->insert(array_merge($data_og_acti, $data_og));
                    //订单日志表
                    $data_ol = [
                        'o_log_orderid' => $order_info['order_id'],
                        'o_log_role' => $user_info['user_name'],
                        'o_log_desc' => '创建了订单',
                        'o_log_addtime' => time(),
                    ];
                    $res_ol = Db::name('order_log')->insert(array_merge($data_ol, $data_ol_acti));

                    //如果使用积分，添加积分日志
                    if ($points){
                        $log_data = array(
                            'p_uid' => $uid,
                            'point_num' => $order_pay_dedu,
                            'point_type' => 8,
                            'point_desc' => '购物抵扣金额',
                            'point_add_time' => time()
                        );
                        // 积分日志表
                        $res2 = Db::name('points_log')->insert($log_data);
                    }


                    //商品库存
                    Db::name('goods_sku')->where('sku_id', $sku_id)->setDec('stock', $goods_num);
                    Db::name('goods')->where('goods_id', $goods_id)->setDec('stock', $goods_num);
                    //商品销量
                    Db::name('goods')->where('goods_id', $goods_id)->setInc('volume', $goods_num);
                    //  活动商品 销量
                    $this->decStock($goods_id, $goods_info['prom_type'], $goods_num);
                    $user_info = $user->userInfo(['user_id' => $uid], 'user_account');
                    Db::commit();
                    return ['user_account' => $user_info['user_account'], 'order_id' => $order_info['order_id'], 'pay_price' => $pay_price];
                }
                catch(\Exception $e){
                    // return $e->getMessage();
                    Db::rollback();
                    return false;
                }
            }
            //商品已下架
            else return -1;
        }
    }
    /*
     * 活动 商品减库存
     */
    public function decStock($goods_id, $prom_type, $num)
    {
        if ($prom_type > 0) {
            // 减少库存 增加销量
            if ($prom_type == 1) {
                Db::name('group_goods')->where(['goods_id' => $goods_id])->setDec('goods_number', $num);
                Db::name('group_goods')->where(['goods_id' => $goods_id])->setInc('order_number', $num);
            } elseif($prom_type == 2) {
                Db::name('goods_activity')->where(['goods_id' => $goods_id])->setDec('total_goods', $num);
                Db::name('goods_activity')->where(['goods_id' => $goods_id])->setInc('act_count', $num);
            } elseif ($prom_type == 3) {
                Db::name('team_activity')->where(['goods_id' => $goods_id])->setDec('goods_number', $num);
                Db::name('team_activity')->where(['goods_id' => $goods_id])->setInc('order_number', $num);
            } elseif ($prom_type == 4) {
                Db::name('bargain')->where(['goods_id' => $goods_id])->setDec('goods_number', $num);
                Db::name('bargain')->where(['goods_id' => $goods_id])->setInc('order_number', $num);
            } elseif ($prom_type == 5) {
                Db::name('flash_goods')->where(['goods_id' => $goods_id])->setDec('goods_number', $num);
                Db::name('flash_goods')->where(['goods_id' => $goods_id])->setInc('order_number', $num);
            } elseif ($prom_type == 6 || $prom_type == 7 || $prom_type ==8) {
                Db::name('full_goods')->where(['goods_id' => $goods_id])->setDec('goods_number', $num);
                Db::name('full_goods')->where(['goods_id' => $goods_id])->setInc('order_number', $num);
            } else {
                Db::name('active_goods')->where(['goods_id' => $goods_id])->setDec('goods_num', $num);
                Db::name('active_goods')->where(['goods_id' => $goods_id])->setInc('order_number', $num);
            }

        }
    }
    /*
     * 订单列表
     */
    public function orderList($uid, $p, $type, $is_seller,$search=''){
        $num = 10;
        $p = $p ? $p : 1;
        $s = ($p - 1) * $num;
		$map_search = '';
        switch($type){
            case 1 : $stat = '0,1,2,3,4,5'; break;     	// 全部
            case 2 : $stat = 0; break;					// 待付款
            case 3 : $stat = 1; break;				// 待收货
            case 4 : $stat = 2; break;				// 待收货
            case 5 : $stat = 3; break;					// (待评价)
            case 6 : $stat = 4; break;					// 已取消（已完成）
            default : $stat = '0,1,2,3,4,6,8';
        }
        if(!$is_seller){

            $map = [
                'order_uid' => $uid,
                'order_isdel' => 0,
                'order_status' => ['in', $stat],
            ];
        }
        //店铺订单管理

//        else{
//			//搜索功能
//
//			if($search){
//
//				if($search){
//					$map_search['b.og_goods_name'] = ['like', "%$search%"];
//				}
//
//
//			}
//			if($type==1||!$type){
//				$stat = '0,1,2,3,4';
//			}
//
//
//            $store_info = Db::name('store')->where('s_uid', $uid)->field('s_comm_time')->find();
//            //下级
//            $where_1 = [
//                'commi_p_uid' => $uid,
//                'commi_add_time' => ['egt', $store_info['s_comm_time']],
//            ];
//            $where_2 = [
//                'commi_g_uid' => $uid,
//                'commi_add_time' => ['egt', $store_info['s_comm_time']],
//            ];
//
//            $list_1 = Db::name('commission')->where($where_1)->field('commi_uid,commi_order_id')->select();
//            $list_2 = Db::name('commission')->where($where_2)->field('commi_uid,commi_order_id')->select();
//            $list_3 = Db::name('commission')->where($where_2)->field('commi_p_uid,commi_order_id')->select();
//
//            $tmp_arr_uid = [$uid];
//            $tmp_arr_order_id = [];
//            foreach($list_1 as $v){
//                $tmp_arr_uid[] = $v['commi_uid'];
//                $tmp_arr_order_id[] = $v['commi_order_id'];
//            }
//            foreach($list_2 as $v){
//                if(!in_array($v['commi_uid'], $tmp_arr_uid)){
//                    $tmp_arr_uid[] = $v['commi_uid'];
//                }
//                if(!in_array($v['commi_order_id'], $tmp_arr_order_id)){
//                    $tmp_arr_order_id[] = $v['commi_order_id'];
//                }
//            }
//            foreach($list_3 as $v){
//                if(!in_array($v['commi_p_uid'], $tmp_arr_uid)){
//                    $tmp_arr_uid[] = $v['commi_p_uid'];
//                }
//                if(!in_array($v['commi_order_id'], $tmp_arr_order_id)){
//                    $tmp_arr_order_id[] = $v['commi_order_id'];
//                }
//            }
//
//            $map = "order_uid in (".implode(',', $tmp_arr_uid).") and seller_isdel=0 and order_isdel=0 and order_status in (".$stat.")";
//            if($tmp_arr_order_id){
//                $map .= " or order_id in (".implode(',', $tmp_arr_order_id).')';
//            }
//        }

        $orderlist = Db::name('order')->where($map)->where($map_search)->field('order_id,order_uid,order_no,order_pay_code,order_pay_price,order_payed_price,order_status,order_prom_type,order_prom_id,is_commented,order_create_time,pay_status,order_type')->order('order_create_time desc')->limit($s, $num)->select();

        //取出order_id
         $order_id = Db::name('order')->where($map)->where($map_search)->field('order_id,order_uid,order_no,order_pay_price,order_payed_price,order_status,order_prom_type,order_prom_id,is_commented,order_create_time,pay_status,order_type')->order('order_create_time desc')->limit($s, $num)->column('order_id');
       // $order_id = array_column($orderlist,'order_id');
        $order_id = implode(',',$order_id);
        $order_id = ['og_order_id'=>['in',$order_id]];
        $ordergoodslist = Db::name('order_goods')->where($order_id)->field('og_order_id,og_order_status,og_id,og_goods_id,og_goods_name,og_goods_price,og_goods_spec_val,og_goods_num,og_goods_thumb,after_state_status,og_acti_id')->select();
        $i=0;
        foreach ($orderlist as $key => $val) {
            foreach ($ordergoodslist as $k => $v) {
                if($val['order_id'] == $v['og_order_id']){
                    $list[$i]['og_order_id'] = $v['og_order_id'];
                    $list[$i]['og_order_status'] = $v['og_order_status'];
                    $list[$i]['order_id'] = $val['order_id'];
                    $list[$i]['order_uid'] = $val['order_uid'];
                    $list[$i]['order_no'] = $val['order_no'];
                    $list[$i]['order_pay_price'] = $val['order_pay_price'];
                    $list[$i]['order_payed_price'] = $val['order_payed_price'];
                    $list[$i]['order_status'] = $val['order_status'];
                    $list[$i]['order_prom_type'] = $val['order_prom_type'];
                    $list[$i]['order_prom_id'] = $val['order_prom_id'];
                    $list[$i]['is_commented'] = $val['is_commented'];
                    $list[$i]['order_create_time'] = $val['order_create_time'];
                    $list[$i]['og_id'] = $v['og_id'];
                    $list[$i]['og_acti_id'] = $v['og_acti_id'];
                    $list[$i]['og_goods_id'] = $v['og_goods_id'];
                    $list[$i]['og_goods_name'] = $v['og_goods_name'];
                    $list[$i]['og_goods_price'] = $v['og_goods_price'];
                    $list[$i]['pay_status'] = $val['pay_status'];
                    $list[$i]['og_goods_spec_val'] = $v['og_goods_spec_val'];
                    $list[$i]['og_goods_num'] = $v['og_goods_num'];
                    $list[$i]['og_goods_thumb'] = $v['og_goods_thumb'];
                    $list[$i]['after_state_status'] = $v['after_state_status'];
                    $list[$i]['order_type'] = $val['order_type'];
                    $list[$i]['pay_type'] = $val['order_pay_code'];
                    $i++;
                }
            }
        }
        // $list = $this->model->alias('a')->join('__ORDER_GOODS__ b', 'a.order_id=b.og_order_id', 'LEFT')->where($map)->where($map_search)->field('b.og_order_status,a.order_id,a.order_uid,a.order_no,a.order_pay_price,a.order_payed_price,a.order_status,a.order_prom_type,a.order_prom_id,a.is_commented,a.order_create_time,b.og_id,b.og_goods_id,b.og_goods_name,b.og_goods_price,a.pay_status,b.og_goods_spec_val,b.og_goods_num,b.og_goods_thumb,order_create_time,after_state_status')->order('a.order_create_time desc')->limit($s, $num)->select()->toArray();
		$total = $this->model->where($map)->where($map_search)->count('order_id');
        $arr = [];
        $i = 0;
        foreach($list as $k => &$v){
            //如果订单为已支付状态
            if ($v['pay_status']==1){
                if ($v['pay_type']=='offpay'){//如果是货到付款
                    if($v['order_status']==1 || $v['order_status']==2){//可以取消订单
                        $arr['list'][$i]['allow_cancel'] = 1;
                        $arr['list'][$i]['allow_back'] = 0;
                    }elseif($v['order_status']==3){
                        $arr['list'][$i]['allow_cancel'] = 0;
                        $arr['list'][$i]['allow_back'] = 1;
                    }else{
                        $arr['list'][$i]['allow_cancel'] = 0;
                        $arr['list'][$i]['allow_back'] = 0;
                    }
                }else{
                    $arr['list'][$i]['allow_cancel'] = 0;
                    $arr['list'][$i]['allow_back'] = 1;
                }
            }else{
                $arr['list'][$i]['allow_cancel'] = 1;
                $arr['list'][$i]['allow_back'] = 0;
            }


            /*if($v['order_status'] == 0 && $v['order_payed_price'] ==0 ){
                $pay_left_time = (int)($v['order_create_time'] + 1200) - time();
                if($pay_left_time <= 0){
                    $v['order_status'] = 5;
                    $this->model->where('order_id', $v['order_id'])->update(['order_status' => 5, 'pay_status' => 2]);
                }
            }*/

            switch($v['order_status']){
                case 0 : $v['order_status'] = '待付款'; break;
                case 1 : $v['order_status'] = '待发货'; break;
                case 2 : $v['order_status'] = '待收货'; break;
                case 3 : $v['order_status'] = '待评价'; break;
                case 4 : $v['order_status'] = '已完成'; break;
                case 5 : $v['order_status'] = '已取消'; break;
                case 6 : $v['order_status'] = '申请退货'; break;
                case 8 : $v['order_status'] = '退款完成'; break;
            }
            //活动信息
            if($v['order_prom_type']){
                $acti_info = Db::name('goods_activity')->where('act_id', $v['order_prom_id'])->field('act_type')->find();

                switch($acti_info['act_type']){
                    //预售
                    case 2 :
                        if(!$v['order_payed_price']){
                            $v['order_status'] = '待付定金';
                        }else{
                            $cmmm = Db::name('commission')->where('commi_order_id',$v['og_order_id'])->select();
                            if($cmmm){
                                if($v['order_status'] == '待评价'){
                                    $v['order_status'] == '待评价';
                                }elseif($v['order_status'] == '待收货'){
                                    $v['order_status'] == '待收货';
                                }elseif($v['order_status'] == '已完成'){
                                    $v['order_status'] == '已完成';
                                }elseif($v['order_status'] == '已取消'){
                                    $v['order_status'] == '已取消';
                                }elseif($v['order_status'] == '申请退货'){
                                    $v['order_status'] == '申请退货';
                                }elseif ($v['order_status'] == '退款完成'){
                                    $v['order_status'] == '退款完成';
                                }else{
                                    $v['order_status'] = '待发货';
                                }
                            }else{
                                $v['order_status'] = '待付尾款';
                            }
                        }
                        break;
                }
            }
            if ($v['og_acti_id'] == 3 && $val['order_status'] == 1) {
                $status = db('team_follow')->where('order_id', $v['og_order_id'])->value('status');
                if ($status < 2) {
                    $v['order_status'] = '待成团';
                } elseif ($status == 2) {
                    $v['order_status'] = '已成团';
                } else {
                    $v['order_status'] = '成团失败';
                }
            }
            if($k == 0){
                $no_arr[0] = $v['order_no'];
            }
            if(!in_array($v['order_no'], $no_arr)){
                $no_arr[] = $v['order_no'];
                $i++;
            }
            if($is_seller){
                $user_type = 'VIP';
                $user_info = Db::name('store')->where('s_uid', $v['order_uid'])->field('s_id,s_grade,s_comm_time')->find();
                if($user_info){
                    $is_self = 0;
                    /* if($v['order_create_time'] >= $user_info['s_comm_time']){
                        $is_self = ($v['order_uid'] == $uid ? 1 : 0);
                    } */
					$is_self = ($v['order_uid'] == $uid ? 1 : 0);
                    $arr['list'][$i]['is_self'] = $is_self;
                    $user_type = ($user_info['s_grade'] == 1 ? '会员店铺' : ($user_info['s_grade'] == 2 ? '高级店铺' : '旗舰店铺'));
                }
                $arr['list'][$i]['type'] = $user_type;
            }



			$goodsInfo = Db::name('goods')->where('goods_id',$v['og_goods_id'])->field('is_gift')->find();
            $arr['total'] = $total;
            $arr['list'][$i]['order_id'] = $v['order_id'];
            $arr['list'][$i]['pay_status'] = $v['pay_status'];
            $arr['list'][$i]['pay_type'] = $v['pay_type'];
			$arr['list'][$i]['uid_status'] = 0;

			if($v['order_uid'] == $uid){
				  $arr['list'][$i]['uid_status'] = 1;
			}
             if($v['og_acti_id']==4){
              $arr['list'][$i]['kanjia_status']=1;
            }else{
              $arr['list'][$i]['kanjia_status']=0;
            }

            $arr['list'][$i]['order_uid'] = $v['order_uid'];
            $arr['list'][$i]['order_no'] = $v['order_no'];
            $arr['list'][$i]['status'] = $v['order_status'];
            $arr['list'][$i]['is_commented'] = $v['is_commented'];
            $arr['list'][$i]['pay_price'] = $v['order_pay_price'];
            $arr['list'][$i]['order_create_time'] = date('Y-m-d H:i:s', $v['order_create_time']);
            $arr['list'][$i]['order_prom_id'] = $v['order_prom_id'];
            $arr['list'][$i]['order_type'] = $v['order_type'];
			$after_state_status = Db::name('sh_info')->where('og_id',$v['og_id'])->field('after_state_status, og_order_status,status,supplier_status,financial_status,or_goods_note,or_financial_note,or_supplier_note')->find();
            $goods['after_state_status'] = 0;
			if($after_state_status){
			    $goods['after_state_status'] = $after_state_status['after_state_status'];
                $goods['og_order_status'] = $after_state_status['og_order_status'];
                if ($after_state_status['status'] == 3) {
                    $goods['after_status'] = 1;
                    $goods['after_content'] = $after_state_status['or_goods_note'];
                } elseif ($after_state_status['supplier_status'] == 3) {
                    $goods['after_status'] = 2;
                    $goods['after_content'] = $after_state_status['or_supplier_note'];
                } elseif ($after_state_status['financial_status'] == 3) {
                    $goods['after_status'] = 3;
                    $goods['after_content'] = $after_state_status['or_financial_note'];
                } else {
                    $goods['after_status'] = 0;
                    $goods['after_content'] ='';
                }
			}
            $goods['picture'] = $v['og_goods_thumb'];
            $goods['is_gift'] = $goodsInfo['is_gift'];
            $goods['goods_id'] = $v['og_goods_id'];
            $goods['goods_name'] = $v['og_goods_name'];
            $goods['goods_price'] = $v['og_goods_price'];
            $goods['goods_spec'] = $v['og_goods_spec_val'];
            $goods['goods_num'] = $v['og_goods_num'];
            $arr['list'][$i]['goods'][] = $goods;
        }
        //订单数量统计
        //待付款
        $pay_num = Db::name('order')->where("order_status",0)->where('order_uid',$uid)->where("order_isdel",0)->count();
        //待发货
        $shipping_num = Db::name('order')->where('order_status',1)->where('order_uid',$uid)->where("order_isdel",0)->count();
        //待收货
        $receive_num = Db::name('order')->where('order_status',2)->where('order_uid',$uid)->where("order_isdel",0)->count();
        //待评价
        $commet_num = Db::name('order')->where('order_status',3)->where('order_uid',$uid)->where("order_isdel",0)->count();
        $arr["num"] = [
            'pay_num'=>$pay_num,
            'shipping_num'=>$shipping_num,
            'receive_num'=>$receive_num,
            'commet_num'=>$commet_num,
        ];
        return $arr;
    }

    /*
     * 订单详情
     */
    public function orderDetails($uid, $order_id){
        //活动优惠未写
        $order_info = $this->model->alias('a')->join('__COUPON_USERS__ c','a.order_coupon_id=c.c_id','LEFT')->field('a.order_no,a.order_all_price,a.order_freight,a.order_coupon_id,a.order_pay_points,a.need_invoice,a.invoice_header,a.invoice_type,a.order_create_time,a.order_pay_code,a.order_pay_price,a.order_status,a.pay_status,a.order_pay_time,a.order_payed_price,a.yz_id,a.rc_id,a.order_prom_type,a.order_discount as discount_price,a.is_commented,a.order_prom_id,a.rc_amount,a.pro_name as addr_province,a.city_name as addr_city,a.area as addr_area,a.address as addr_cont,a.phone as addr_phone,a.consigee as addr_receiver,a.order_remark,a.order_remark,c.c_coupon_price')->where('a.order_id', $order_id)->find();
        //地址
        if($order_info['addr_province']){
            $order_info['addr_province'] = $this->getRegion(['region_id' => $order_info['addr_province']]);
        }
        if($order_info['addr_city']){
            $order_info['addr_city'] = $this->getRegion(['region_id' => $order_info['addr_city']]);
        }
        if($order_info['addr_area']){
            $order_info['addr_area'] = $this->getRegion(['region_id' => $order_info['addr_area']]);
        }
        $order_info['addr_area'] = $order_info['addr_province'].' '.$order_info['addr_city'].' '.$order_info['addr_area'];

        if($order_info['order_status'] == 0 && $order_info['order_payed_price'] ==0){
            $pay_left_time = (int)($order_info['order_create_time'] + 1200) - time();
            if($pay_left_time <= 0){
                $order_info['order_status'] = 5;
                $this->model->where('order_id', $order_id)->update(['order_status' => 5, 'pay_status' => 2]);

			$order_goods = Db::name('order_goods')->where('og_order_id', $order_id)->select();
			//2190103
            foreach($order_goods as $k => $v){
					//库存处理
					Db::name('goods_sku')->where(['sku_id' => $v['og_goods_spec_id'], 'goods_id' => $v['og_goods_id']])->setInc('stock', $v['og_goods_num']);
					Db::name('goods')->where('goods_id', $v['og_goods_id'])->setInc('stock', $v['og_goods_num']);
					//销量处理
					Db::name('goods')->where('goods_id', $v['og_goods_id'])->setDec('volume', $v['og_goods_num']);
					// 活动处理
					$this->addStock($v['og_goods_id'], $v['og_acti_id'], $v['og_goods_num']);
				}
				//积分
				//优惠券
				if($order_info['order_coupon_id']){
					Db::name('coupon_users')->where(['c_uid' => $uid, 'c_id' => $order_info['order_coupon_id']])->update(['coupon_stat' => 1]);
				}
				//我的元宝
			   if($order_info['yz_id']){
				   $result = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_id' => $order_info['yz_id']])->update(['yin_stat' => 2]);
				   if($result){
					   //元宝日志
						$yinzi_log=[
							'y_log_yid'=>$order_info['yz_id'],
							'y_log_uid'=> $uid,
							'y_log_desc'=>'取消订单退回元宝',
							'y_log_addtime'=>time(),
						];
						Db::name('yinzi_log')->insert($yinzi_log);
				   }
				}
				// 我的充值卡
				if ($order_info['rc_id']) {
					$rc_info = Db::name('user_rc')->where('card_id', $order_info['rc_id'])->field('card_balance,card_stat')->find();
					if ($rc_info['card_stat'] == 2) {
						Db::name('user_rc')->where('card_id', $order_info['rc_id'])->update(['card_stat' => 1, 'card_balance' => $order_info['rc_amount']]);
					} else {
						Db::name('user_rc')->where('card_id', $order_info['rc_id'])->setInc('card_balance', $order_info['rc_amount']);
					}
				}
				// 日志
				$log = [
					'o_log_orderid' => $order_id,
					'o_log_role' => $uid,
					'o_log_desc' => '取消了订单',
					'o_log_addtime' => time()
				];
				Db::name('order_log')->insert($log);
            }
            else {
				$order_info['pay_left_time'] = $this->timerFormat($pay_left_time);
			$order_info['pay_live_time'] = $pay_left_time;
			}
        }
        // $order_info['order_create_time'] = date('Y-m-d H:i:s', $order_info['order_create_time']);
        //活动信息
        if($order_info['order_prom_type']){
            $acti_info = Db::name('goods_activity')->where('act_id', $order_info['order_prom_id'])->field('act_type,ext_info,start_time,end_time,deposit,price,deposit_use')->find();

            switch($acti_info['act_type']){
                //预售
                case 2 :
                    // $extension = unserialize($acti_info['ext_info']);
                    if($order_info['order_payed_price']){
                        $order_info['order_pay_price'] = $acti_info['price'] - $acti_info['deposit_use'];
                        $data = Db::name('active_type')->where('id',2)->field('pay_start_time,pay_end_time')->find();
                        $order_info['pay_start_time'] = date('Y-m-d H:i:s',$data['pay_start_time']);
                        $order_info['pay_end_time'] = date('Y-m-d H:i:s',$data['pay_end_time']);
                        $order_info['deposit_use'] = $acti_info['deposit_use'];
                        if(time()>$data['pay_end_time']||time()<$data['pay_start_time']){
                            return -11;
                        }
                    }
                    else{
                        $order_info['deposit_use'] = $acti_info['deposit_use'];
                        if($acti_info['start_time'] > time() && $acti_info['end_time'] < time()){
                            $order_info['pay_left_time'] = $this->timerFormat($acti_info['end_time'] - time());
                        }
                        else if($acti_info['start_time'] <= time()){
                            $order_info['pay_left_time'] = $this->timerFormat($acti_info['start_time'] - time());
                        }
                        else{
                            $order_info['order_status'] = 5;
                            $this->model->where('order_id', $order_id)->update(['order_status' => 5, 'pay_status' => 2]);
                        }
                    }
                    break;
            }
        }

        if ($order_info['order_prom_id'] == 3 && $order_info['order_status'] == 1) {
            $status = db('team_follow')->where('order_id', $order_id)->value('status');
            if ($status == 1) {
                $order_info['order_status'] = '待成团';
            } elseif($status == 2) {
                $order_info['order_status'] = '已成团';
            } else {
                $order_info['order_status'] = '成团失败';
            }
        }

        if(!$order_info['order_coupon_id']){
            $order_info['c_coupon_price'] = 0;
        }

        if($order_info['order_pay_time']){
            $order_info['order_pay_time'] = date('Y-m-d H:i:s', $order_info['order_pay_time']);
        }

        $order_info['post_info'] = [];
        if($order_info['pay_status'] >= 1 ){
            $post_info = $this->postInfo($uid, $order_id);
            $order_info['post_info'] = end($post_info['data']);
        }
        if($order_info['order_create_time']){
            $order_info['order_create_time'] = date('Y-m-d H:i:s', $order_info['order_create_time']);
        }
        if($order_info['order_pay_time']){
            $order_info['order_pay_time'] = date('Y-m-d H:i:s', $order_info['order_pay_time']);
        }
		if($order_info['yz_id']){
			$yin_amount = Db::name('yinzi')->where('yin_id',$order_info['yz_id'])->value('yin_amount');
			$order_info['yin_amount'] = $yin_amount;
		}else{
			$order_info['yin_amount'] = 0;
		}
        if($order_info['rc_id']){
//            $card_price = Db::name('order')->where('rc_id',$order_info['rc_id'])->value('rc_amount');
            $order_info['card_price'] = $order_info['rc_amount'];
        }else{
            $order_info['card_price'] = 0;
        }
        $order_sh_goods = Db::name('sh_info')->where('og_order_id', $order_id)->field('og_id,og_goods_id,og_goods_name,og_goods_spec_val,og_goods_num,og_goods_price,og_goods_thumb,og_acti_id,after_state_status,og_order_status,status,supplier_status,financial_status')->select();

        if ($order_sh_goods) {
            $order_goods = Db::name('order_goods')->where('og_order_id', $order_id)->field('og_id,og_goods_id,og_goods_name,og_goods_spec_val,og_goods_num,og_goods_price,og_goods_thumb,og_acti_id,after_state_status,og_order_status,status,supplier_status,financial_status')->select();
            foreach ($order_goods as $key => $value) {
                foreach ($order_sh_goods as $k => $val) {
                    if ($val['og_id'] == $value['og_id']) {
                        $order_goods[$key] = $order_sh_goods[$k];
                    }
                }
            }
        } else {
            $order_goods = Db::name('order_goods')->where('og_order_id', $order_id)
                ->field('og_id,og_goods_id,og_goods_name,og_goods_spec_val,og_goods_num,og_goods_price,og_goods_thumb,og_acti_id,after_state_status,og_order_status,status,supplier_status,financial_status')->select();
        }
        foreach($order_goods as &$v){
            if(!$v['og_acti_id']){
                $v['og_acti_id'] = '';
            }
			$goodsInfo = Db::name('goods')->where('goods_id',$v['og_goods_id'])->field('is_gift')->find();
			$v['is_gift'] = $goodsInfo['is_gift'];
			$v['is_after_sales'] = 0;
            //审核是否成功
            if($v['status']==3){
                $v['after_status'] = 1;
            }elseif($v['supplier_status']==3){
                $v['after_status'] = 2;
            }elseif($v['financial_status']==3){
                $v['after_status'] = 3;
            } else {
                $v['after_status'] = 0;
            }
        }
        $order_info['goods'] = $order_goods;
        return $order_info;
    }

    /*
     * 取消订单
     */
    public function orderCancle($uid, $order_id){
        $goods_model = new GoodsModel();
        $order_info = $this->model->where(['order_id' => $order_id])
            ->field('order_no,giving_id,order_id,order_status,order_coupon_id,pay_status,yz_id,rc_id,rc_amount,order_pay_points,order_coupon_id,yz_id,rc_id,order_pay_dedu')->find();
        $user_info = Db::name('users')->where('user_id', $uid)->field('user_name')->find();
        $order_goods = Db::name('order_goods')->where('og_order_id', $order_id)->select();
        Db::startTrans();
        try{
            if(($order_info['order_status'] == 0) || ($order_info['order_status'] == 1)){

                if($order_info['pay_status'] == 1){

                    $this->model->where('order_id', $order_id)->update(['order_status' => 5]);
					Db::name('order_goods')->where('og_order_id',$order_id)->update(['og_order_status' => 5]);

                    /* 	$data = [
                            'apply_time'=>time(),
                            'audit_status'=>1,
                            'after_state_status'=>1,
                            'og_order_status'=>6,
                        ];
                        $order_info = Db::name('order_goods')
                        ->where('og_order_id',$order_id)
                        ->field('og_id')
                        ->select();
                        if($order_info){
                            foreach($order_info as $val){
                                $data['audit_no']=$this->createAsNo();
                                Db::name('order_goods')->where('og_id',$val['og_id'])->update($data);
                            }
                        }   */
                }else{
                    $this->model->where('order_id', $order_id)->update(['order_status' => 5]);
					Db::name('order_goods')->where('og_order_id',$order_id)->update(['og_order_status' => 5]);
                }

            }
            else{
                return false;
            }

            foreach($order_goods as $k => $v){
                //库存处理
                Db::name('goods_sku')->where(['sku_id' => $v['og_goods_spec_id'], 'goods_id' => $v['og_goods_id']])->setInc('stock', $v['og_goods_num']);
                Db::name('goods')->where('goods_id', $v['og_goods_id'])->setInc('stock', $v['og_goods_num']);
                //销量处理
                Db::name('goods')->where('goods_id', $v['og_goods_id'])->setDec('volume', $v['og_goods_num']);
                // 活动处理
                $this->addStock($v['og_goods_id'], $v['og_acti_id'], $v['og_goods_num']);
            }
            //积分
            if($order_info['order_pay_points']){
                //给会员增加积分
                Db::name('users')->where('user_id', $uid)->update(['user_points' => $user_info['user_points'] + $order_info['order_pay_dedu']]);
                //添加日志
                $log_data = array(
                    'p_uid' => $uid,
                    'point_num' => $order_info['order_pay_dedu'],
                    'point_type' => 8,
                    'point_desc' => '取消订单退回积分',
                    'point_add_time' => time()
                );
                // 积分日志表
                $res2 = Db::name('points_log')->insert($log_data);
            }
            //优惠券
            if($order_info['order_coupon_id']){
                Db::name('coupon_users')->where(['c_uid' => $uid, 'c_id' => $order_info['order_coupon_id']])->update(['coupon_stat' => 1]);
            }
			//我的元宝
           if($order_info['yz_id']){
               $result = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_id' => $order_info['yz_id']])->update(['yin_stat' => 2]);
			   if($result){
				   //元宝日志
					$yinzi_log=[
						'y_log_yid'=>$order_info['yz_id'],
						'y_log_uid'=> $uid,
						'y_log_desc'=>'取消订单退回元宝',
						'y_log_addtime'=>time(),
					];
					Db::name('yinzi_log')->insert($yinzi_log);
			   }
            }
            // 我的充值卡
			if ($order_info['rc_id']) {
                $rc_info = Db::name('user_rc')->where('card_id', $order_info['rc_id'])->field('card_uid,card_no,card_balance,card_stat')->find();
                if ($rc_info['card_stat'] == 2) {
                    Db::name('user_rc')->where('card_id', $order_info['rc_id'])->update(['card_stat' => 1, 'card_balance' => $order_info['rc_amount']]);
                    //充值卡记录
                    $this->add_rc_log($rc_info['card_uid'],$order_info['rc_id'],$order_info['rc_amount'],1);
                } else {
                    Db::name('user_rc')->where('card_id', $order_info['rc_id'])->setInc('card_balance', $order_info['rc_amount']);
                    //充值卡记录
                    $this->add_rc_log($rc_info['card_uid'],$order_info['rc_id'],$order_info['rc_amount'],1);
                }
            }
            // 日志
            $log = [
                'o_log_orderid' => $order_id,
                'o_log_role' => $user_info['user_name'],
                'o_log_desc' => '取消了订单',
                'o_log_addtime' => time()
            ];
            Db::name('order_log')->insert($log);
            Db::commit();
            return true;
        }
        catch(\Exception $e){
            Db::rollback();
            return false;
        }
    }
    /*
     * 增加 库存 减少 销量
     */
    public function addStock($goods_id, $prom_type, $num)
    {
        if ($prom_type > 0) {
            // 增加库存 减少销量
            if ($prom_type == 1) {
                Db::name('group_goods')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
                Db::name('group_goods')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
            } elseif($prom_type == 2) {
                Db::name('goods_activity')->where(['goods_id' => $goods_id])->setInc('total_goods', $num);
                Db::name('goods_activity')->where(['goods_id' => $goods_id])->setDec('act_count', $num);
            } elseif ($prom_type == 3) {
                Db::name('team_activity')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
                Db::name('team_activity')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            } elseif ($prom_type == 4) {
                Db::name('bargain')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
                Db::name('bargain')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            } elseif ($prom_type == 5) {
                Db::name('flash_goods')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
                Db::name('flash_goods')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            } elseif ($prom_type == 6 || $prom_type == 7 || $prom_type ==8) {
                Db::name('full_goods')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
                Db::name('full_goods')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            } else {
                Db::name('active_goods')->where(['goods_id' => $goods_id])->setInc('goods_num', $num);
                Db::name('active_goods')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            }

        }
    }

    /*
     * 支付页面
     */
    public function payWay($uid, $order_id){
        $user_info = Db::name('users')->where('user_id', $uid)->field('user_account,user_card')->find();
        if(!$user_info){
            return -1;
        }
        $order_info = $this->model->where('order_id', $order_id)->field('order_refund_price,order_prom_type,order_prom_id,order_id,order_pay_price,order_pay_code,pick_status,is_end')->find();

        if(!$order_info){
            return -2;
        }
        if($order_info['order_prom_type']==2 && !empty($order_info['order_pay_code'])){
            $order_info['order_pay_price'] = $order_info['order_refund_price'];
        }
        return ['user_account' => $user_info['user_account'] + $user_info['user_card'], 'order_id' => $order_info['order_id'], 'pay_price' => $order_info['order_pay_price'], 'pay_code' => $order_info['order_pay_code']];
    }


    /*
     * 立即付款
     */
    public function orderPay($uid, $order_id, $pay_code){
        $user = new UserService();
        $goods = new Goods();
        $user_info = $user->userInfo(['user_id' => $uid], 'user_name,user_account,user_card');
        $order_info = $this->model->where('order_id', $order_id)->field('order_refund_price,order_uid,order_no,order_storeid,order_addrid,order_all_price,order_pay_price,order_freight,order_payed_price,order_prom_type,order_prom_id,order_pay_code,pick_status')->find();
        $order_goods = Db::name('order_goods')->alias('a')->join('__GOODS__ b', 'a.og_goods_id=b.goods_id')->where('a.og_order_id', $order_id)->field('a.og_id,a.og_uid,a.og_order_id,a.og_goods_id,a.og_goods_name,a.og_goods_num,a.og_goods_price,a.order_commi_price,b.is_gift,b.prom_type,b.is_self')->select();
        /*if ($order_goods['prom_type'] == 12) {
            $res = Db::name('order')->where(['order_uid' => $uid, 'pay_status' => ['not in', '0,2']])->find();
            if($res){
                return -3;
            }
        }*/
        //活动信息
        $acti_arr = $this->orderActivity($order_info);
        $acti_o_update = $acti_arr['update'];
        $acti_o_insert = $acti_arr['insert'];
        $order_info = $acti_arr['order'];
        $times = time();

        //余额支付
        if($pay_code == 'balance'  || $order_info['order_pay_price']==0){
            if($user_info['user_account'] + $user_info['user_card'] < $order_info['order_pay_price']){
                return -1;
            }
            Db::startTrans();
            try{
                $pay_price = 0;
                if($order_info['order_prom_type']==2 && !empty($order_info['order_pay_code'])){
                        //付尾款
                        $pay_end = true;//是否支付尾款
                        $last_pay = $order_info['order_refund_price'];

                        //支付尾款
                        $o_update = [
                            'order_all_price'=>$order_info['order_refund_price']+$order_info['order_all_price'],
                            'order_pay_price'=>$order_info['order_refund_price']+$order_info['order_all_price'],
                            'order_commi_price'=>$order_info['order_refund_price']+$order_info['order_all_price']-$order_info['order_freight'],
                            'order_pay_code' => 'balance',
                            'order_status' => 1,
                            'pay_status' => 1,
                            'post_status' => 0,
                            'order_pay_time' => $times
                        ];
                        $og_update = [
                            'og_goods_pay_price'=>$order_info['order_refund_price']+$order_info['order_all_price'],
                            'order_commi_price'=>$order_info['order_refund_price']+$order_info['order_all_price']-$order_info['order_freight'],
                            'og_order_status'=>1
                        ];
                        $pay_price = $order_info['order_refund_price'];
                }else{
                    $pay_end = false;
                    $o_update = [
                        'order_pay_code' => 'balance',
                        'order_status' => 1,
                        'pay_status' => 1,
                        'post_status' => 0,
                        'order_pay_time' => $times
                    ];
                    $og_update = [
                        'og_order_status'=>1
                    ];
                    $pay_price = $order_info['order_pay_price'];
                }


                $o_insert = [
                    'o_log_orderid' => $order_id,
                    'o_log_role' => $user_info['user_name'],
                    'o_log_desc' => '支付了订单',
                    'o_log_addtime' => $times
                ];
				$og_insert = [
					'og_order_status' => 1
				];

				//选择自提支付后，状态直接改为 已完成 无物流
				if($order_info['pick_status'] == 1){
					 $o_update['order_status'] = 4;
					 $og_insert['og_order_status'] = 4;
				}
                $this->model->where('order_id', $order_id)->update(array_merge($o_update, $acti_o_update));
				Db::name('order_goods')->where('og_order_id',$order_id)->update($og_update);
                $o_res = Db::name('order_log')->insert(array_merge($o_insert, $acti_o_insert));
                $o_log_info = Db::name('order_log')->where(['o_log_orderid' => $order_id])->order('o_log_addtime desc')->field('o_log_orderid')->find();
                //处理积分
                //处理余额（优先扣除充值金额）
                // if($user_info['user_account'] < $order_info['order_pay_price']){
                if ($pay_code == 'balance') {
                    if($user_info['user_card'] > $order_info['order_pay_price']){
                        Db::name('users')->where('user_id', $uid)->setDec('user_card', $pay_price);
                    }
                    else{
                        Db::name('users')->where('user_id', $uid)->update(['user_card' => 0.00, 'user_account' => $user_info['user_account'] - ($pay_price - $user_info['user_card'])]);
                    }
                }
                $log_insert = [
                    'a_uid' => $uid,
                    'acco_num' => -$pay_price,
                    'acco_type' => 2,
                    'acco_desc' => '支付了订单',
                    'order_id'=>$order_id,
                    'acco_time' => $times,
                ];
                Db::name('account_log')->insert($log_insert);

                $has_no_gift = true;//没有大礼包
                //处理开店大礼包
                if($order_goods){
                    foreach($order_goods as $v){
                        if($v['is_gift']){
                            // if($v['og_goods_name'] == '开店大礼包'){
                            // $bag_info = Db::name('store_gift_bag')->where(['bag_order_id' => $v['og_order_id'], 'bag_uid' => $uid, 'bag_buy_stat' => 0])->field('bag_id,bag_invite_uid')->find();
                            // $bag_info = Db::name('store_bag_log')->alias('a')->join('__STORE_GIFT_BAG__ b', 'a.log_bag_id=b.bag_id')->where(['a.log_order_id' => $v['og_order_id'], 'a.log_uid' => $uid, 'a.log_bag_stat' => 0])->field('a.log_id,b.bag_id,b.bag_invite_uid')->find();
                            $has_no_gift = false;//有大礼包
                            $bag_info = Db::name('store_bag_log')->where(['log_order_id' => $v['og_order_id'], 'log_uid' => $uid, 'log_bag_stat' => 0])->field('log_id,share_uid as bag_invite_uid,log_order_id')->find();
                            if($bag_info){
                                $this->openStore($uid, $bag_info);
                            } else {
                                $this->buyStory($order_id, $uid);
                            }
                            // Db::name('order')->where('order_id', $order_id)->update(['order_status' => 4, 'post_status' => 4, 'order_finish_time' => time()]);
                        }
                        //是否自有商品
                        if($v['is_self']){
                            $self_data = [
                                'good_id'=>$v['og_goods_id'],
                                's_og_id'=>$v['og_id'],
                                'price'=>$v['order_commi_price'],
                                'sg_addtime'=>$times,
                                's_uid'=>$v['og_uid']
                            ];
                            Db::name('sg_sale')->insert($self_data);
                        }
                        if ($v['prom_type'] == 12) {
                            $res = Db::name('order')->where(['order_uid' => $uid, 'pay_status' => ['not in', '0,2']])->find();
                            if ($res) {
                                return -3;
                            }
                        }
                    }
                }
                $order_info = $this->model->where('order_id', $order_id)->field('order_prom_type,order_uid,order_id,order_commi_price,order_pay_price')->find();
                //处理佣金
                if($has_no_gift){
                    if($order_info['order_prom_type']==2){
                        if($pay_end ){
                            //$this->goodsCommission($order_info);
                            $order_info['order_pay_price'] = $last_pay;
                        }
                    }else{
                        //$this->goodsCommission($order_info);
                    }
                }
                Db::commit();
                return ['pay_code' => $pay_code, 'pay_price' => $order_info['order_pay_price']];
            }
            catch(\Exception $e){

                Db::rollback();
//                print_r($e->getMessage());die;
                return false;
            }
        } else if($pay_code == 'offpay') {
            Db::startTrans();
            try{
                $o_update = [
                    'order_pay_code' => $pay_code,
                    'order_status' => 1,
                    'pay_status' => 1,
                    'post_status' => 0,
                    'order_pay_time' => $times
                ];
                $og_update = [
                    'og_order_status'=>1
                ];
                $pay_price = $order_info['order_pay_price'];
                $o_insert = [
                    'o_log_orderid' => $order_id,
                    'o_log_role' => $user_info['user_name'],
                    'o_log_desc' => '支付了订单',
                    'o_log_addtime' => $times
                ];
                $og_insert = [
                    'og_order_status' => 1
                ];

                //选择自提支付后，状态直接改为 已完成 无物流
                if($order_info['pick_status'] == 1){
                    $o_update['order_status'] = 4;
                    $og_insert['og_order_status'] = 4;
                }
                $this->model->where('order_id', $order_id)->update(array_merge($o_update, $acti_o_update));
                Db::name('order_goods')->where('og_order_id',$order_id)->update($og_update);
                $o_res = Db::name('order_log')->insert(array_merge($o_insert, $acti_o_insert));
                $o_log_info = Db::name('order_log')->where(['o_log_orderid' => $order_id])->order('o_log_addtime desc')->field('o_log_orderid')->find();
                //处理积分

                $has_no_gift = true;//没有大礼包
                //处理开店大礼包
                if($order_goods){
                    foreach($order_goods as $v){
                        if($v['is_gift']){
                            $has_no_gift = false;//有大礼包
                            $bag_info = Db::name('store_bag_log')->where(['log_order_id' => $v['og_order_id'], 'log_uid' => $uid, 'log_bag_stat' => 0])->field('log_id,share_uid as bag_invite_uid,log_order_id')->find();
                            if($bag_info){
                                $this->openStore($uid, $bag_info);
                            } else {
                                $this->buyStory($order_id, $uid);
                            }
                        }
                        //是否自有商品
                        if($v['is_self']){
                            $self_data = [
                                'good_id'=>$v['og_goods_id'],
                                's_og_id'=>$v['og_id'],
                                'price'=>$v['order_commi_price'],
                                'sg_addtime'=>$times,
                                's_uid'=>$v['og_uid']
                            ];
                            Db::name('sg_sale')->insert($self_data);
                        }
                        if ($v['prom_type'] == 12) {
                            $res = Db::name('order')->where(['order_uid' => $uid, 'pay_status' => ['not in', '0,2']])->find();
                            if ($res) {
                                return -3;
                            }
                        }
                    }
                }
                $order_info = $this->model->where('order_id', $order_id)->field('order_prom_type,order_uid,order_id,order_commi_price,order_pay_price')->find();
                //处理佣金
                if($has_no_gift){
                    //$this->goodsCommission($order_info);
                }
                Db::commit();
                return ['pay_code' => $pay_code, 'pay_price' => $order_info['order_pay_price']];
            }
            catch(\Exception $e){

                Db::rollback();
                return false;
            }
        } else {
            $apipay = new ApipayService();
            // $order_info['order_pay_price'] = '0.01';
            if(isset($order_goods[0]['og_goods_name']) && strlen($order_goods[0]['og_goods_name']) > 30){
                $order_goods[0]['og_goods_name'] = mb_strimwidth($order_goods[0]['og_goods_name'], 0, 30, '..','utf-8');
            }
            switch($pay_code){
                //支付宝支付
                case 'alipay' :
                    $data = $apipay->Alipay($order_info['order_no'], $order_info['order_pay_price'], '购买 '.$order_goods[0]['og_goods_name']);
                    break;
                //微信支付
                case 'wxpay' :
                    $order_info['order_pay_price'] *= 100;
                    $data = $apipay->WxPay($order_info['order_no'], $order_info['order_pay_price'], '购买 '.$order_goods[0]['og_goods_name']);
                    break;
                //银联支付
                case 'unionpay' :
                    $order_info['order_pay_price'] *= 100;
                    $data = $apipay->UnionPay($order_info['order_no'], $order_info['order_pay_price']);
                    break;
            }

            if(!$data['code']){
                return false;
            }
            else{
                db('order')->where('order_id', $order_id)->setField('order_pay_code', $pay_code);
                return $data['data'];
            }
        }
    }

    /*
     * 查看物流
     */
    public function lookInfo($og_id,$goods_id){
      $goods_info = Db::name('order_goods')->where(['og_order_id' => $og_id,'og_goods_id' => $goods_id])->field('og_goods_thumb,post_type,post_no')->find();
		// $goods_info = Db::name('order')
		// 	->alias('a')
		// 	->where(['a.order_id' => $og_id])
		// 	->join('order_goods b','b.og_order_id=a.order_id')
		// 	->field('b.og_goods_thumb,a.post_type,a.post_no')
		// 	->find();
        //快递100
        // $order_info['post_type'] = 'yuantong';
        // $order_info['post_no'] = '814206272824';
        $post_info=$this->kuaidi100($goods_info['post_type'],$goods_info['post_no']);
        $arr = [];
        $tmp = json_decode($post_info, true);
        if($tmp['status'] == '200'){
            foreach($tmp['data'] as &$v){
                unset($v['ftime']);
                unset($v['location']);
            }
            $arr['data'] = $tmp['data'];
            $arr['state'] = $tmp['state'];  //0，在途；1，揽件；2，出现问题；3，签收；4，退签；5.派件；6，退回
        }
        else{
			if($order_info['order_create_time']){
				  $arr['data'][0]['time'] = date('Y-m-d H:i:s', $order_info['order_create_time']);
			}
            $arr['data'][0]['context'] = '订单待配货';
            $arr['state'] = $goods_info['og_post_status'];  // 0，未配货；1，未发货；2，已发货
        }

        $arr['picture'] = $goods_info[0]['og_goods_thumb'];
        $arr['post_type'] = $goods_info['post_type'];
        $arr['postno'] = $goods_info['post_no'];
        $arr['goods_num'] = count($goods_info);
        if(!$arr['post_type']){
            $arr['post_type'] = '暂无信息';
        }
        if(!$arr['postno']){
            $arr['postno'] = '暂无信息';
        }
        if(!$arr['post_num']){
            $arr['post_num'] = '暂无信息';
        }

        //配送企业
        return $arr;
    }
    /*
     * 查看物流
     */
    public function postInfo($uid, $order_id){
        $order_info = $this->model->where(['order_id' => $order_id])->field('post_status,post_type,post_no,order_create_time')->find();

        $goods_info = Db::name('order_goods')->where(['og_order_id' => $order_id])->field('og_goods_thumb')->select();

        //快递100
        // $order_info['post_type'] = 'yuantong';
        // $order_info['post_no'] = '814206272824';
         $data=$this->kuaidi100($order_info['post_type'],$order_info['post_no']);

        // if(function_exists('curl_init')){
        // //     $ch = curl_init();
        // //     curl_setopt($ch, CURLOPT_URL, 'http://www.kuaidi100.com/query?type='.$order_info['post_type'].'&postid='.$order_info['post_no']);
        // //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // //     $post_info = curl_exec($ch);
        // //     curl_close($ch);
        // // }
        // // else{
        // //     $post_info = file_get_contents('http://www.kuaidi100.com/query?type='.$order_info['post_type'].'&postid='.$order_info['post_no']);

        // }
        $arr = [];
        $tmp = json_decode($data, true);
        if($tmp['status'] == '200'){
            foreach($tmp['data'] as &$v){
                unset($v['ftime']);
                unset($v['location']);
            }
            $arr['data'] = $tmp['data'];
            $arr['state'] = $tmp['state'];  //0，在途；1，揽件；2，出现问题；3，签收；4，退签；5.派件；6，退回
        }
        else{
            $arr['data'][0]['time'] = date('Y-m-d H:i:s', $order_info['order_create_time']);
            $arr['data'][0]['context'] = '订单待配货';
            $arr['state'] = $order_info['post_status'];  // 0，未配货；1，未发货；2，已发货
        }

        $arr['picture'] = $goods_info[0]['og_goods_thumb'];
        $arr['post_type'] = $order_info['post_type'];
        $arr['postno'] = $order_info['post_no'];
        $arr['goods_num'] = count($goods_info);

        if(!$arr['post_type']){
            $arr['post_type'] = '暂无信息';
        }
        if(!$arr['postno']){
            $arr['postno'] = '暂无信息';
        }
        if(!$arr['post_num']){
            $arr['post_num'] = '暂无信息';
        }
		$express = $this->getPost();
		$post =  $express['express'];
	/* 	if($arr['post_type'] !='暂无信息'){
				$nunber = array_search($arr['post_type'],$post['field']);
				$arr['post_type'] = $post['value'][$nunber];
		} */
        //配送企业
        return $arr;
    }
    /*
     * 查看物流
     */
    public function postInfos($uid, $order_id){
        $order_info = $this->model->where(['order_id' => $order_id])->field('post_status,post_type,post_no,order_create_time,order_pay_time,order_no')->find();
        $goods_info = Db::name('order_goods')->where(['og_order_id' => $order_id])->field('og_goods_thumb,post_type,post_no,og_post_status,og_supplier_id')->group('og_supplier_id')->select();
        foreach($goods_info as $key=>$val){
            $arr = [];
            $post_info = $this->kuaidi100($val['post_type'],$val['post_no']);

            $tmp = json_decode($post_info, true);

            if($tmp['status'] == '200'){
                foreach($tmp['data'] as &$v){
                    unset($v['ftime']);
                    unset($v['location']);
                }
                $arr['data'] = $tmp['data'];
                $arr['state'] = $tmp['state'];  //0，在途；1，揽件；2，出现问题；3，签收；4，退签；5.派件；6，退回
            }
            else{
                $arr['data'][0]['time'] = date('Y-m-d H:i:s', $order_info['order_pay_time']);
                $arr['data'][0]['context'] = '订单待配货';
                $arr['state'] = $order_info['post_status'];  // 0，未配货；1，未发货；2，已发货
            }
            $supplier = Db::name('supplier')->where('id',$val['og_supplier_id'])->field('supplier_title')->find();
            $arr['picture'] = $val['og_goods_thumb'];
            $arr['og_supplier_id'] = $val['og_supplier_id'];
            $arr['post_type'] = $val['post_type'];
            $arr['postno'] = $val['post_no'];
            $arr['order_no'] = $order_info['order_no'];
            if(!$arr['post_type']){
                $arr['post_type'] = '暂无信息';
            }
            if(!$arr['postno']){
                $arr['postno'] = '暂无信息';
            }
            if(!$arr['post_num']){
                $arr['post_num'] = '暂无信息';
            }
			$express = $this->getPost();
			$post =  $express['express'];
			if($arr['post_type'] !='暂无信息'){
				$nunber = array_search($arr['post_type'],$post['field']);
				$arr['post_type'] = $post['value'][$nunber];
			}
            $data[$key] = $arr;
        }


        //配送企业
        return $data;
    }
    public function postFlow($order_id,$og_supplier_id){
        $goods_info = Db::name('order_goods')->where(['og_order_id' => $order_id,'og_supplier_id'=>$og_supplier_id])->field('og_goods_thumb,post_type,post_no,og_post_status,og_supplier_id')->group('og_supplier_id')->find();
        $post_info = $this->getLogistics($goods_info['post_no'],$goods_info['post_type']);
        $arr = [];
        $tmp = json_decode($post_info, true);
        if($tmp['status'] == '200'){
            foreach($tmp['data'] as &$v){
                unset($v['ftime']);
                unset($v['location']);
            }
            $arr['data'] = $tmp['data'];
            $arr['state'] = $tmp['state'];  //0，在途；1，揽件；2，出现问题；3，签收；4，退签；5.派件；6，退回
        }
        else{
			$order_pay_time = Db::name('order')->where('order_id',$order_id)->value('order_pay_time');
            $arr['data'][0]['time'] = date('Y-m-d H:i:s', $order_pay_time);
            $arr['data'][0]['context'] = '订单待配货';
            $arr['state'] = $goods_info['post_status'];  // 0，未配货；1，未发货；2，已发货
        }
        $supplier = Db::name('supplier')->where('id',$goods_info['og_supplier_id'])->field('supplier_title')->find();
        $arr['supplier_title'] = $supplier['supplier_title'];
        $arr['picture'] = $goods_info['og_goods_thumb'];
        $arr['og_supplier_id'] = $goods_info['og_supplier_id'];
        $arr['picture'] = $goods_info['og_goods_thumb'];
        $arr['post_type'] = $goods_info['post_type'];
        $arr['postno'] = $goods_info['post_no'];
        if(!$arr['post_type']){
            $arr['post_type'] = '暂无信息';
        }
        if(!$arr['postno']){
            $arr['postno'] = '暂无信息';
        }
        if(!$arr['post_num']){
            $arr['post_num'] = '暂无信息';
        }
		$express = $this->getPost();
		$post =  $express['express'];
		if($arr['post_type'] !='暂无信息'){
			$nunber = array_search($arr['post_type'],$post['field']);
			$arr['post_type'] = $post['value'][$nunber];
		}
        //配送企业
        return $arr;
    }
    /*
     * 获取物流
     */
    public function getLogistics($post_no='',$post_type=''){
        $data=$this->kuaidi100($post_type,$post_no);
        // if(function_exists('curl_init')){
        //     $ch = curl_init();
        //     curl_setopt($ch, CURLOPT_URL, 'http://www.kuaidi100.com/query?type='.$post_type.'&postid='.$post_no);
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //     $post_info = curl_exec($ch);
        //     curl_close($ch);
        // }
        // else{
        //     $post_info = file_get_contents('http://www.kuaidi100.com/query?type='.$post_type.'&postid='.$post_no);
        // }
         return $data;
    }
    /*
     * 确认收货
     */
    public function postConfirm($uid, $order_id){

        $user_info = Db::name('users')->where(['user_id' => $uid])->field('user_name')->find();
        $order_info = Db::name('order')->where(['order_id' => $order_id, 'post_status' => ['lt', 4]])->find();
        // $order_info = Db::name('order_goods')->where(['og_order_id' => $order_id])->update(['og_post_status' => 4, 'og_order_status' => 4]);
        $time = time();
        if($order_info){
            // 日志
            $log = [
                'o_log_orderid' => $order_id,
                'o_log_role' => $user_info['user_name'],
                'o_log_desc' => '已确认收货',
                'o_log_addtime' => $time
            ];
            Db::name('order_log')->insert($log);

            $order_info = $this->model->where('order_id', $order_id)->find();

            // 返还积分
			$shop_config = Db::name('config')->where(1)->value('shop');

            if($shop_config){
                $shop_config = json_decode($shop_config, true);
                if($shop_config['sp_ctrl']){
                    if ($order_info['order_commi_price']){
                        Db::name('users')->where('user_id', $uid)->setInc('user_points', floor($order_info['order_commi_price']));

                        $points_log_insert = [
                            'p_uid' => $uid,
                            'point_num' => floor($order_info['order_commi_price']),
                            'point_type' => 8,
                            'point_desc' => '购物赠送积分',
                            'point_add_time' => $time
                        ];

                        Db::name('points_log')->insert($points_log_insert);
                    }
                }
            }

            // 处理返利
            //判断是否为首单，如果是则返佣
            //$num =Db::name('order')->where("order_uid = {$order_info['order_uid']} and (order_status = 4 or order_status = 3)")->count();
            //if ($num == 0){
                //$this->goodsCommission($order_info);
            //}
			Db::name('order_goods')->where(['og_order_id' => $order_id])->update(['og_post_status' => 4, 'og_order_status' => 3]);
            return $this->model->where('order_id', $order_id)->update(['order_status' => 3, 'post_status' => 4, 'order_finish_time' => $time]);
        }
        else return false;
    }

    /*
     * 删除订单
     */
    public function orderDel($uid, $order_id, $is_seller){
        $update = [];
        //店铺删除订单
        if($is_seller){
            $update = ['seller_isdel' => 1];
        }
        //普通界面删除订单
        else{
            $update =['order_isdel' => 1];
        }
        return $this->model->where('order_id', $order_id)->update($update);
    }

    /*
     * 商品评价列表
     */
    public function goodsCommList($uid, $order_id){
        return $order_goods = Db::name('order_goods')->where(['og_order_id' => $order_id, 'og_uid' => $uid])->field('og_order_id,og_goods_id,og_goods_name,og_goods_thumb')->select();
    }

    /*
     * 评价
     */
    public function goodsComment($uid, $order_id, $goods_id){
    }
    /**
     * 获取订单 商品信息
     */
    public function getOrderGoodsinfo($og_id)
    {
        return Db::name('order_goods')->where(array('og_id' => $og_id))->find();
    }
    /**
     * 获取订单 商品信息
     */
    public function getOrderGoods($og_order_id)
    {
        return Db::name('order_goods')->where(array('og_order_id' => $og_order_id))->field('og_order_id,og_goods_name,og_goods_spec_val,og_goods_price,og_goods_num')->select();
    }
    /**
     * 获取订单 商品信息
     */
    public function getSupplierName($supplier_id)
    {
        return Db::name('supplier')->where(array('id' =>['in', $supplier_id]))->column('supplier_title');
    }
    /**
     * 获取订单 单个商品信息
     */
    public function getOrderGoodsinfos($order_id)
    {
        return Db::name('order_goods')->where(array('og_order_id' => $order_id))->find();
    }
	/**
     * 获取订单 单个商品信息
     */
    public function getOrderGoodsinfoss($og_id)
    {
        return Db::name('order_goods')->where(array('og_id' => $og_id))->find();
    }

    /*
     * 订单活动信息
     */
    public function orderActivity(&$order_info){
        //活动信息
        $acti_o_update = [];
        $acti_o_insert = [];
        if($order_info['order_prom_type']){
            $acti_info = Db::name('goods_activity')->where('act_id', $order_info['order_prom_id'])->field('act_type,ext_info,start_time,end_time,deposit')->find();
            switch($acti_info['act_type']){
                //预售
                case 2 :
                    // $extension = unserialize($acti_info['ext_info']);
                    if(!$order_info['order_payed_price']){
                        $order_info['order_pay_price'] = $acti_o_update['order_payed_price'] = $acti_info['deposit'];
                        $acti_o_update['order_status'] = 0;
                        $acti_o_update['pay_status'] = 0;
                        $acti_o_insert['o_log_desc'] = '支付了预售订单定金';
                    }
                    else{
                        $order_info['order_pay_price'] -= $acti_info['deposit'];
                        $acti_o_update['order_payed_price'] = $order_info['order_payed_price'] + $order_info['order_pay_price'];
                        $acti_o_insert['o_log_desc'] = '支付了预售订单尾款';
                    }
                    break;
            }
        }
        return ['update' => $acti_o_update, 'insert' => $acti_o_insert, 'order' => $order_info];
    }

    /*
     * 订单倒计时格式处理
     */
    private function timerFormat($second){
        $h = $m = $s = 0;
        if($second){
            $h = floor($second / 3600);
            $m = floor(($second - $h * 3600) / 60);
            $s = $second - $h * 3600 - $m * 60 ;
        }
        if($h > 0){
            return sprintf('%02d', $h).':'.sprintf('%02d', $m).':'.sprintf('%02d', $s);
        }
        return sprintf('%02d', $m).':'.sprintf('%02d', $s);
    }
    /*
     *  获取店铺名称
     */
    public function getOrderStore($s_id){
        $res = Db::name('store')->where('s_id',3)->field('s_name')->find();
        return $res['s_name'];
    }
  
  /*
     * 处理普通商品的返利
     */
    public function goodsCommissionNew($uid,$orderId){

        $order_info = Db::name('order')->where('order_id',$orderId)->find();
        if(!$order_info){
            return ['status'=>-1,'错误'];
        }

        if($order_info['is_cash']!=0){
            return ['status'=>-1,'错误'];
        }

        //用户信息及上下级关系
        $user_info = Db::name('users')->alias('a')->join('__USERS_TREE__ b', 'a.user_id=b.t_uid', 'LEFT')->where('a.user_id', $uid)->field('a.user_name,a.is_seller,b.t_p_uid,b.t_g_uid')->find();

        //总佣金
        $commission = $order_info['order_pay_price']-$order_info['order_freight'];
        if($commission<=0){
            return ['status'=>-1,'错误'];
        }

        $commission = sprintf('%0.2f',$commission);

        //促销开关
        $status = true;
        $f_s_rate = 0;
        $s_s_rate = 0;
        $t_s_rate = 0;
       // $shop_config = Db::name('config')->where(1)->field('commission')->find();
        $shop_config = Db::name('config')->where(1)->field('commission')->find();

        if($shop_config){
            $shop_config = json_decode($shop_config['commission'], true);
            if(!$shop_config['shop_ctrl']){
                $status = false;
            }
            $f_s_rate = $shop_config['f_s_rate'] / 100;
            $s_s_rate = $shop_config['s_s_rate'] / 100;
            $t_s_rate = $shop_config['t_s_rate'] / 100;
        }
      
        //上级
        if ($user_info['t_p_uid']){
            Db::startTrans();
            try{
                // 返利上级（三级）
                if($status){
                    $this->addCommi($user_info['t_p_uid'],$orderId, $commission * $f_s_rate, $user_info['user_name'].' 购买商品返利');
                    if($user_info['t_g_uid']){
                        $this->addCommi($user_info['t_g_uid'],$orderId, $commission * $s_s_rate, $user_info['user_name'].' 购买商品返利');
                    }

                    Db::name('order')->where('order_id',$orderId)->update(['is_cash'=>1]);
                }
                Db::commit();
            }
            catch(\Exception $e){
                Db::rollback();
            }
        }
        return ['status'=>1,'错误'];
       
    }
  
   /*
     * 增加佣金
     */
    private function addCommi($uid, $orderId, $num, $desc){
        Db::name('users')->where('user_id', $uid)->setInc('user_account', $num);
        $acco_log = [
            'a_uid' => $uid,
            'acco_num' => $num,
            'order_id' => $orderId,
          	'acco_type' => 4,
            'acco_desc' => $desc,
            'acco_time' => time(),
        ];
        Db::name('account_log')->insert($acco_log);
    }

    /*
     * 处理普通商品的返利
     */
    public function goodsCommission($order_info){
        if(!$order_info['order_commi_price']) return true;
        //判断该订单是否为改会员收单，如果是，则返佣金
        $count = Db::name('order')->where('order_uid',$order_info['order_uid'])->count();
        if ($count > 0){
            return true;
        }
//        $user_service = new UserService();
        //用户信息及上下级关系
        $user_info = Db::name('users')->alias('a')->join('__USERS_TREE__ b', 'a.user_id=b.t_uid', 'LEFT')->where('a.user_id', $order_info['order_uid'])->field('a.user_name,a.is_seller,b.t_p_uid,b.t_g_uid')->find();
        $goods_info = Db::name('order_goods')->alias('a')->join('__GOODS__ b', 'a.og_goods_id=b.goods_id')->where('a.og_order_id', $order_info['order_id'])->field('a.og_goods_id,a.order_commi_price,b.commission,b.is_gift,a.og_acti_id,a.og_goods_num')->select();

//        //查找上级店铺或者自己店铺的佣金比：
//        if($user_info['is_seller']>0){
//            //店主
//            $s_uid = $order_info['order_uid'];
//        }else{
//            $s_uid = $user_info['t_p_uid'];
//        }
//        $s_grade = Db::name('store')->where('s_uid',$s_uid)->value('s_grade');
        //总佣金
        $commission = $order_info['order_pay_price'];
//        if($goods_info){
//            foreach($goods_info as $v){
                // 大礼包 没有返利 过滤
//                if ($v['is_gift'] == 1) {
//                    continue;
//                }
//                switch ($s_grade){
//                    case 2:   //高级
//                        $v['commission'] = $v['commission'] * 1.20;
//                        break;
//                    case 3: //旗舰
//                        $v['commission'] = $v['commission'] * 1.25;
//                        break;
//                }
//                if($v['og_acti_id']==5 && empty($v['commission'])){
//                    $commission += 0.01*$v['og_goods_num'];
//                }else{
//                    $commission += $v['order_commi_price'] * ($v['commission'] / 100);
//                }
//                $commission += $v['order_commi_price'];
//            }
//        }
        $commission = sprintf('%0.2f',$commission);
//        // 判断 有没有 返利金额
//       /* if ($commission == 0) {
//            return true;
//        }*/
//        Db::startTrans();
//        try{
//            //增加佣金记录
//            $commi_log = [
//                'commi_uid' => $order_info['order_uid'],
//                'commi_order_id' => $order_info['order_id'],
//                'commi_order_price' => $order_info['order_commi_price'],
//                'commi_add_time' => time(),
//                'goods_profit' => $commission,
//            ];
//            if($user_info['is_seller']){
//                //店主
//                $commi_log['uid_role'] = $s_grade + 1;
//                $commi_log['commi_price'] = $commission;
//            }else{
//                //vip
//                $commi_log['uid_role'] = 1;
//                $commi_log['commi_p_uid'] = $user_info['t_p_uid'];//
//                $commi_log['p_uid_role'] = $s_grade + 1;
//                $commi_log['commi_p_price'] = $commission;
//            }
//            $res = Db::name('commission')->insert($commi_log);
//            Db::commit();
//            return $res;
//        }catch(\Exception $e){
//            Db::rollback();
//            return false;
//        }
        //促销开关
        $status = true;
        $f_s_rate = 0;
        $s_s_rate = 0;
        $t_s_rate = 0;
       // $shop_config = Db::name('config')->where(1)->field('commission')->find();
        $shop_config = Db::name('config')->where(1)->field('commission')->find();

        if($shop_config){
            $shop_config = json_decode($shop_config['commission'], true);
            if(!$shop_config['shop_ctrl']){
                $status = false;
            }
            $f_s_rate = $shop_config['f_s_rate'] / 100;
            $s_s_rate = $shop_config['s_s_rate'] / 100;
            $t_s_rate = $shop_config['t_s_rate'] / 100;
        }
        //上级
        if ($user_info['t_p_uid']){
            Db::startTrans();
            try{
                // 返利上级（三级）
                if($status){
                    $this->addCommi($user_info['t_p_uid'], $commission * $f_s_rate, $user_info['user_name'].' 购买商品');
                    if($user_info['t_g_uid']){
                        $this->addCommi($user_info['t_g_uid'], $commission * $s_s_rate, $user_info['user_name'].' 购买商品');
                    }
                }
                Db::commit();
            }
            catch(\Exception $e){
                Db::rollback();
            }
        }
        //VIP
//        if(!$user_info['is_seller']){
//            Db::startTrans();
//            try{
//                // 返利上级（三级）
//                if($status){
//                    $this->addCommi($user_info['t_p_uid'], $commission * $f_s_rate, $user_info['user_name'].' 购买商品');
//                    $p_store_info = Db::name('store')->where('s_uid', $user_info['t_p_uid'])->field('s_grade')->find();
//                    if($user_info['t_g_uid']){
//                        $g_store_info = Db::name('store')->where('s_uid', $user_info['t_g_uid'])->field('s_grade')->find();
//                        $this->addCommi($user_info['t_g_uid'], $commission * $s_s_rate, $user_info['user_name'].' 购买商品');
//
//                        $f_user_info = Db::name('users_tree')->alias('a')->join('__STORE__ b', 'a.t_p_uid=b.s_uid')->where('a.t_uid', $user_info['t_g_uid'])->field('a.t_p_uid,b.s_grade')->find();
//                        if($f_user_info){
//                            $this->addCommi($f_user_info['t_p_uid'], $commission * $t_s_rate, $user_info['user_name'].' 购买商品');
//                        }
//                    }
//
//                    //增加佣金记录
//                    $commi_log = [
//                        'commi_uid' => $order_info['order_uid'],
//                        'uid_role' => $store_info['s_grade'] + 1,
//                        'commi_p_uid' => $user_info['t_p_uid'],
//                        'p_uid_role' => $p_store_info['s_grade'] + 1,
//                        'commi_order_id' => $order_info['order_id'],
//                        'commi_order_price' => $order_info['order_commi_price'],
//                        'commi_price' => $commission * $f_s_rate,
//                        'commi_p_price' => $commission * $s_s_rate,
//                        'commi_add_time' => time(),
//                        'goods_profit' => $commission,
//                    ];
//                    if($user_info['t_g_uid']){
//                        $commi_log['commi_g_uid'] = $user_info['t_g_uid'];
//                        $commi_log['g_uid_role'] = $g_store_info['s_grade'] + 1;
//                        $commi_log['commi_g_price'] = $commission * $t_s_rate;
//                    }
//                    if($f_user_info){
//                        $commi_log['commi_f_uid'] = $f_user_info['t_p_uid'];
//                        $commi_log['f_uid_role'] = $f_user_info['s_grade'] + 1;
//                        $commi_log['commi_f_price'] = $commission * $t_s_rate;
//                    }
//                    Db::name('commission')->insert($commi_log);
//                }
//                //返利上级店主（仅一级）
//                else{
//                    $this->addCommi($user_info['t_p_uid'], $commission, $user_info['user_name'].' 购买商品');
//                    $p_store_info = Db::name('store')->where('s_uid', $user_info['t_p_uid'])->field('s_grade')->find();
//                    //增加佣金记录
//                    $commi_log = [
//                        'commi_uid' => $order_info['order_uid'],
//                        'uid_role' => 1,
//                        'commi_p_uid' => $user_info['t_p_uid'],
//                        'p_uid_role' => $p_store_info['s_grade'] + 1,
//                        'commi_order_id' => $order_info['order_id'],
//                        'commi_order_price' => $order_info['order_commi_price'],
//                        'commi_p_price' => $commission,
//                        'commi_add_time' => time(),
//                        'goods_profit' => $commission,
//                    ];
//                    Db::name('commission')->insert($commi_log);
//                }
//                Db::commit();
//            }
//            catch(\Exception $e){
//                Db::rollback();
//            }
//        }
//        //店主
//        else if($user_info['is_seller']){
//            $store_info = Db::name('store')->where('s_uid', $order_info['order_uid'])->field('s_grade')->find();
//            Db::startTrans();
//            try{
//                //返利三级（包括自己）
//                if($status){
//                    $this->addCommi($order_info['order_uid'], $commission * $f_s_rate, $user_info['user_name'].' 购买商品');
//                    if($user_info['t_p_uid']){
//                        $p_store_info = Db::name('store')->where('s_uid', $user_info['t_p_uid'])->field('s_grade')->find();
//                        $this->addCommi($user_info['t_p_uid'], $commission * $s_s_rate, $user_info['user_name'].' 购买商品');
//                    }
//
//                    if($user_info['t_g_uid']){
//                        $g_store_info = Db::name('store')->where('s_uid', $user_info['t_g_uid'])->field('s_grade')->find();
//                        $this->addCommi($user_info['t_g_uid'], $commission * $t_s_rate, $user_info['user_name'].' 购买商品');
//                    }
//
//                    //增加佣金记录
//                    $commi_log = [
//                        'commi_uid' => $order_info['order_uid'],
//                        'uid_role' => $store_info['s_grade'] + 1,
//                        'commi_p_uid' => $user_info['t_p_uid'],
//                        'p_uid_role' => $p_store_info['s_grade'] + 1,
//                        'commi_order_id' => $order_info['order_id'],
//                        'commi_order_price' => $order_info['order_commi_price'],
//                        'commi_price' => $commission * $f_s_rate,
//                        'commi_p_price' => $commission * $s_s_rate,
//                        'commi_add_time' => time(),
//                        'goods_profit' => $commission,
//                    ];
//                    if($user_info['t_g_uid']){
//                        $commi_log['commi_g_uid'] = $user_info['t_g_uid'];
//                        $commi_log['g_uid_role'] = $g_store_info['s_grade'] + 1;
//                        $commi_log['commi_g_price'] = $commission * $t_s_rate;
//                    }
//                    Db::name('commission')->insert($commi_log);
//                }
//                //返利自己
//                else{
//                    $this->addCommi($order_info['order_uid'], $commission, '购物返利');
//                    $commi_log = [
//                        'commi_uid' => $order_info['order_uid'],
//                        'uid_role' => $store_info['s_grade'] + 1,
//                        'commi_order_id' => $order_info['order_id'],
//                        'commi_order_price' => $order_info['order_commi_price'],
//                        'commi_price' => $commission,
//                        'commi_add_time' => time(),
//                        'goods_profit' => $commission,
//                    ];
//                    Db::name('commission')->insert($commi_log);
//                }
//                Db::commit();
//            }
//            catch(\Exception $e){
//                Db::rollback();
//                // print_r($e->getMessage());
//            }
//        }
    }

   

    /**
     * 处理开店大礼包
     * @param uid 新店主uid
     * @param type 0，购买大礼包；1，赠送店铺或vip购买  // 11/18 赠送店铺改为 openStoreZengsong
     *
     */
    public function openStore($uid, $bag_info = [], $type = 0, $giving_uid = 0, $order_id = 0){
        //促销开关
        /*$status = true;
        $f_p_rate = 0;
        $s_p_rate = 0;
        $t_p_rate = 0;
        $shop_config = Db::name('config')->where(1)->field('commission')->find();
        if($shop_config){
            $shop_config = json_decode($shop_config['commission'], true);
            if(!$shop_config['prom_ctrl']){
                $status = false;
            }
            else{
                $f_p_rate = $shop_config['f_p_rate'];
                $s_p_rate = $shop_config['s_p_rate'];
                $t_p_rate = $shop_config['t_p_rate'];
            }
        }*/
        $time_now = time();
        $open_points = 30;		//开店奖励积分
        $open_invite = 30;		//邀请开店奖励积分
        $from_uid = 0;
        $log_type = 0;			//大礼包获取类型
        Db::startTrans();
        try{
            //我的邀请码
            // $invite_code = $store_service->createInviteCode();
            //更新大礼包信息
            // Db::name('store_gift_bag')->where('bag_id', $bag_info['bag_id'])->update(['bag_buy_stat' => 2, 'bag_buy_time' => time()]);
            if(!$type && $bag_info){
                //通过分享购买的
                Db::name('store_bag_log')->where('log_id', $bag_info['log_id'])->update(['log_bag_stat' => 1, 'log_buy_time' => $time_now]);

                //增加积分
                Db::name('users')->where('user_id', $uid)->setInc('user_points', $open_points);
                $log_insert_1 = [
                    'p_uid' => $uid,
                    'point_num' => $open_points,
                    'point_type' => 4,
                    'point_desc' => '开店成功奖励积分',
                    'point_add_time' => $time_now,
                ];
                Db::name('points_log')->insert($log_insert_1);

                Db::name('users')->where('user_id', $bag_info['bag_invite_uid'])->setInc('user_points', $open_invite);
                $log_insert_2 = [
                    'p_uid' => $bag_info['bag_invite_uid'],
                    'point_num' => $open_invite,
                    'point_type' => 6,
                    'point_desc' => '邀请开店奖励积分',
                    'point_add_time' => $time_now,
                ];
                Db::name('points_log')->insert($log_insert_2);

                $from_uid = $bag_info['bag_invite_uid'];
                $order_id = $bag_info['log_order_id'];
            }
            $order_info = Db::name('order')->where(['order_id' => $order_id])->field('order_uid,order_id,order_pay_price')->find();
            if($type && $giving_uid){
                $from_uid = $giving_uid;//捐赠人
                $log_type = 2;// 自己购买
                if($uid != $order_info['order_uid']){
                    // 店主赠送
                    $log_type = 1;
                }
            }

            //成为店主
            $s_name = $this->createStoreName();
            $store_insert = [
                's_uid' => $uid,
                's_name' => $s_name,
                's_grade' => 1,
                's_comm_time' => $time_now,
                // 's_invite_code' => $invite_code,
            ];
            Db::name('store')->insert($store_insert);
            // 更新用户表
            Db::name('users')->where('user_id', $uid)->update(['is_seller' => 1, 'is_kefu' => 1]);

            $yinzi = new Yinzi();
            //促销打开
            /*if($status){
                //增加现金
                $user_service = new UserService();
                $user_service->changeAccount($from_uid, 9, $f_p_rate);	// 公司额外奖励
                $user_service->changeAccount($from_uid, 9, 40);	// 40 元宝变现金
                // $yinzi->addYinzi($uid, 5, 30);	//新店主

                //二级返利
                $p_account = $s_p_rate;
                // //三级返利
                $g_account = $t_p_rate;
                $tree_info = Db::name('users_tree')->where('t_uid', $from_uid)->field('t_p_uid,t_g_uid')->find();
                if($tree_info['t_p_uid'] && $p_account > 0){
                    $user_service->changeAccount($tree_info['t_p_uid'], 9, $p_account);
                }
                if($tree_info['t_g_uid'] && $g_account > 0){
                    $user_service->changeAccount($tree_info['t_g_uid'], 9, $g_account);
                }
            }
            //促销关闭
            else{
                //增加元宝：邀请人 2*20元宝
//                $yinzi->addYinzi($from_uid, 4, 20);
//                $yinzi->addYinzi($from_uid, 4, 20);

            }*/

            //查询上级
            $t_p_uid = Db::name('users_tree')->where(['t_uid'=>$uid])->value('t_p_uid');

            // 记录销售额
            $gift_log_insert = [
                'log_uid' => $uid,
                // 'log_p_uid' => $from_uid,
                'log_p_uid' => $t_p_uid,
                'log_order_id' =>$order_id,
                'log_order_price' => $order_info['order_pay_price'],
                'log_type' => $log_type,
                'log_add_time' => time()
            ];
            Db::name('gift_log')->insert($gift_log_insert);

            $account_inc = 100;
            $count = Db::name('gift_log')->where('log_p_uid',$from_uid)->count();
            if($count>13){
                $account_inc = 160;
            }
            //奖金表
            $bonus_data = [
                'user_id'=>$from_uid,
                'price'=>$account_inc,
                'type'=>1,
                'add_time'=>$time_now,
                'is_pay'=>1
            ];
            Db::name('bonus')->insert($bonus_data);
            //增加现金
            $user_service = new UserService();
            $user_service->changeAccount($from_uid, 9, $account_inc);	// 发展店主奖励
            // 被邀请人：4*5元宝
            $yinzi->addYinzi($uid, 5, 5);
            $yinzi->addYinzi($uid, 5, 5);
            $yinzi->addYinzi($uid, 5, 5);
            $yinzi->addYinzi($uid, 5, 5);

            Db::commit();
            return true;
        }
        catch(\Exception $e){
            Db::rollback();
            // return $e->getMessage();
            return false;
        }
    }
    /**
     * 处理开店大礼包
     * @param uid 新店主uid
     * @param type 1，赠送店铺
     * $uid 接受人
     * $giving_uid  赠送人
     */
    public function openStoreZengsong($uid, $bag_info = [], $type = 0, $giving_uid = 0, $order_id = 0){
        //促销开关
        /*$status = true;
        $f_p_rate = 0;
        $s_p_rate = 0;
        $t_p_rate = 0;
        $shop_config = Db::name('config')->where(1)->field('commission')->find();
        if($shop_config){
            $shop_config = json_decode($shop_config['commission'], true);
            if(!$shop_config['prom_ctrl']){
                $status = false;
            }
            else{
                $f_p_rate = $shop_config['f_p_rate'];
                $s_p_rate = $shop_config['s_p_rate'];
                $t_p_rate = $shop_config['t_p_rate'];
            }
        }*/

        $open_points = 30;		//开店奖励积分
        $open_invite = 30;		//赠送开店奖励积分
        $from_uid = $giving_uid; // 赠送人
        $log_type = 1;			//大礼包获取类型:赠送
        Db::startTrans();
        try{
            if($uid && $giving_uid){
//                Db::name('store_bag_log')->where('log_id', $bag_info['log_id'])->update(['log_bag_stat' => 1, 'log_buy_time' => time()]);

                //增加积分
                Db::name('users')->where('user_id', $uid)->setInc('user_points', $open_points);
                $log_insert_1 = [
                    'p_uid' => $uid,
                    'point_num' => $open_points,
                    'point_type' => 4,
                    'point_desc' => '开店成功奖励积分',
                    'point_add_time' => time(),
                ];
                Db::name('points_log')->insert($log_insert_1);

                Db::name('users')->where('user_id', $giving_uid)->setInc('user_points', $open_invite);
                $log_insert_2 = [
                    'p_uid' => $giving_uid,
                    'point_num' => $open_invite,
                    'point_type' => 6,
                    'point_desc' => '赠送开店奖励积分',
                    'point_add_time' => time(),
                ];
                Db::name('points_log')->insert($log_insert_2);

            } else {
                return false;
            }

            $order_info = Db::name('order')->where(['order_id' => $order_id])->field('order_uid,order_pay_price')->find();

            //成为店主
            $s_name = $this->createStoreName();
            $store_insert = [
                's_uid' => $uid,
                's_name' => $s_name,
                's_grade' => 1,
                's_comm_time' => time(),
                // 's_invite_code' => $invite_code,
            ];
            Db::name('store')->insert($store_insert);


            // 更新用户表
            Db::name('users')->where('user_id', $uid)->update(['is_seller' => 1, 'is_kefu' => 1]);

            $yinzi = new Yinzi();
            //促销打开
            /*if($status){
                //增加现金
                $user_service = new UserService();
                $user_service->changeAccount($from_uid, 9, $f_p_rate);	// 公司额外奖励
                $user_service->changeAccount($from_uid, 9, 40);	// 40 元宝变现金
                //二级返利
                $p_account = $s_p_rate;
                // //三级返利
                $g_account = $t_p_rate;
                $tree_info = Db::name('users_tree')->where('t_uid', $from_uid)->field('t_p_uid,t_g_uid')->find();
                if($tree_info['t_p_uid'] && $p_account > 0){
                    $user_service->changeAccount($tree_info['t_p_uid'], 9, $p_account);
                }
                if($tree_info['t_g_uid'] && $g_account > 0){
                    $user_service->changeAccount($tree_info['t_g_uid'], 9, $g_account);
                }
            }
            //促销关闭
            else{
                //增加元宝：邀请人 2*20元宝
                $yinzi->addYinzi($from_uid, 4, 20);
                $yinzi->addYinzi($from_uid, 4, 20);
            // }*/
                // 记录销售额
            $gift_log_insert = [
                'log_uid' => $uid,
                'log_p_uid' => $from_uid,
                'log_order_id' =>$order_id,
                'log_order_price' => $order_info['order_pay_price'],
                'log_type' => $log_type,
                'log_add_time' => time()
            ];
            Db::name('gift_log')->insert($gift_log_insert);

            $account_inc = 100;
            $count = Db::name('gift_log')->where('log_p_uid',$from_uid)->count();
            if($count>13){
                $account_inc = 160;
            }
            //奖金表
            $bonus_data = [
                'user_id'=>$from_uid,
                'price'=>$account_inc,
                'type'=>1,
                'add_time'=>$time_now,
                'is_pay'=>1
            ];
            Db::name('bonus')->insert($bonus_data);
            //增加现金
            $user_service = new UserService();
            $user_service->changeAccount($from_uid, 9, $account_inc);   // 发展店主奖励

            // 被邀请人：4*5元宝
            $yinzi->addYinzi($uid, 5, 5);
            $yinzi->addYinzi($uid, 5, 5);
            $yinzi->addYinzi($uid, 5, 5);
            $yinzi->addYinzi($uid, 5, 5);

            Db::commit();
            return true;
        }
        catch(\Exception $e){
            Db::rollback();
            // return $e->getMessage();
            return false;
        }
    }
    /**
     * 获取 订单商品信息
     * @param $uid
     * @param $order_id
    */
    public function getOrderInfo($uid, $order_id)
    {
        $list = Db::name('order_goods')->field("og_id,og_goods_id")->where(['og_order_id' => $order_id, 'og_uid' => $uid])->select();
        if (!empty($list)) {
            return $list;
        } else {
            return false;
        }
    }
    /*
     * 添加商品评论
     */
    public function orderRemark($data)
    {
        return Db::name('order_remark')->insertAll($data);
    }

    /*
     * 创建大礼包订单
     */
    public function giftOrder($uid, $goods_id, $addr_id, $invoice, $freight, $sku_id, $bag_id){
        $user_info = Db::name('users')->where('user_id', $uid)->field('user_name,is_seller')->find();
        if($user_info['is_seller']){
            return ['code' => 0, 'msg' => '您已经是店主'];
        }
        $goods_info = Db::name('goods_sku')->alias('a')->join('__GOODS__ b', 'a.goods_id=b.goods_id')->where(['a.sku_id' => $sku_id, 'a.goods_id' => $goods_id])->field('a.price,a.stock,a.sku_name,b.goods_name,b.picture,b.status')->find();
        if($goods_info['status'] != 0){
            return ['code' => 0, 'msg' => '商品已下架'];
        }
        if(!$goods_info['stock']){
            return ['code' => 0, 'msg' => '商品库存不足'];
        }

        $order_no = $this->createOrderNo();
        Db::startTrans();
        try{
            //更新商品库存
            Db::name('goods_sku')->where(['sku_id' => $sku_id, 'goods_id' => $goods_id])->setDec('stock', 1);
            Db::name('goods')->where(['goods_id' => $goods_id])->setDec('stock', 1);
            Db::name('goods')->where(['goods_id' => $goods_id])->setInc('volume', 1);
            //订单总价
            $order_total_price = $goods_info['price'] + $freight;
            //插入订单表
            $order_data = [
                'order_no' => $order_no,
                'order_uid' => $uid,
                'order_addrid' => $addr_id,
                'order_all_price' => $order_total_price,
                'order_pay_price' => $order_total_price,
                'order_freight' => $freight,
                'order_status' => 0,
                'need_invoice' => $invoice['need_invoice'],
                'order_create_time' => time(),
            ];
            if($invoice['need_invoice']){
                $order_data['invoice_header'] = $invoce['invoice_header'];
                $order_data['invoice_com'] = $invoce['invoice_com'];
                $order_data['invoice_com_tax'] = $invoce['invoice_com_tax'];
            }
            Db::name('order')->insert($order_data);
            $order_id = Db::name('order')->getLastInsId();

            //更新大礼包信息
            $bag_info = Db::name('store_gift_bag')->where('bag_id', $bag_id)->field('bag_id')->find();
            if(!$bag_info){
                return ['code' => 0, 'msg' => '未找到大礼包信息'];
            }
            Db::name('store_gift_bag')->where('bag_id', $bag_id)->update(['bag_uid' => $uid, 'bag_order_id' => $order_id]);

            // 插入订单商品表
            $order_goods_data = [
                'og_order_id' => $order_id,
                'og_goods_id' => $goods_id,
                'og_uid' => $uid,
                'og_goods_name' => $goods_info['goods_name'],
                'og_goods_spec_id' => $sku_id,
                'og_goods_spec_val' => $goods_info['sku_name'],
                'og_goods_num' => 1,
                'og_goods_price' => $goods_info['price'],
                'og_goods_pay_price' => $order_total_price,
                'og_goods_thumb' => $goods_info['picture'],
                'og_add_time' => time(),
            ];
            Db::name('order_goods')->insert($order_goods_data);
            // 插入订单日志表
            $log_data = [
                'o_log_orderid' => $order_id,
                'o_log_role' => $user_info['user_name'],
                'o_log_desc' => '创建了订单',
                'o_log_addtime' => time(),
            ];
            Db::name('order_log')->insert($log_data);
            Db::commit();
            return ['code' => 1, 'data' => ''];
        }
        catch(\Exception $e){
            Db::rollback();
            // return ['code' => 0, 'msg' => $e->getMessage()];
            return ['code' => 0, 'msg' => '订单创建失败'];
        }
    }

    /**
     * @param $where
     * 获得省市区信息
     */
    public function getRegion($where){
        $regionName=Db::name("region")->where($where)->value('region_name');
        return $regionName;
    }

    /*
     * 生成店铺名
     */
    public function createStoreName(){
        $s_name = '合陶家店主'.mt_rand(1000, 9999);
        $check = Db::name('store')->where('s_name', $s_name)->field('s_id')->find();
        while($check){
            $s_name = $this->createStoreName();
        }
        return $s_name;
    }
    /*
     * 获取订单详情
     */
    public function particulars($map){
        $res = Db::name('order')->alias('a')->join('__ADDR__ b', 'a.order_addrid=b.addr_id')
            //->join('__ORDER_GOODS__ c', 'a.order_id=c.og_order_id')
            //->join('__GOODS__ d', 'd.goods_id=c.og_goods_id')
            ->join('__USERS__ e','a.order_uid=e.user_id')
           // ->where($map)->field('a.*,b.*,d.picture,d.goods_numbers,e.user_truename,e.user_avat,e.user_mobile')
            ->where($map)->field('a.*,b.*,e.user_truename,e.user_avat,e.user_mobile,e.shop_name')
            ->find();
        if($res){
            $order_goods = Db::name('order_goods')
                ->alias('c')
                //->where('og_id',$res['og_id'])
                ->where('og_order_id',$res['order_id'])
                ->join('__GOODS__ d', 'd.goods_id=c.og_goods_id')
                ->field('c.*,d.picture,d.goods_numbers,picture')->select();
            $res['order_goods'] =  $order_goods;
        }
        return $res;
    }
    /*
     * 获取订单详情
     */
    public function getStatus($status){
        $order_status = [
            '0' => '待付款',
            '1' => '待发货',
            '2' => '待收货',
            '3' => '待评价',
            '4' => '已完成',
            '5' => '已取消',
            '6' => '申请退货',
            '7' => '申请换货'
        ];
        $status = $order_status[$status];
        return $status;
    }
    /*
     * 获取物流信息
     */
    public function getPhysical($map,$where=''){

        $res = Db::name('order')
            ->alias('a')
            ->join('__ADDR__ b', 'a.order_addrid=b.addr_id')
            ->where('order_id',$map['order_id'])
            ->field('a.*,b.*')
            ->find();
        if($res){
            $order_goods =  Db::name('order_goods')->where('og_order_id',$res['order_id'])->where($where)->select();
            $res ['order_goods'] = $order_goods;
        }
        return $res;
    }
    /*
     * 获取物流信息
     */
    public function getPhysicals($map){
        $res = Db::name('order')
            ->alias('a')
            ->join('__ADDR__ b', 'a.order_addrid=b.addr_id')
            ->where($map)
            ->field('a.*,b.*')
            ->find();
        if($res){
            $order_goods =  Db::name('order_goods')->where('og_order_id',$res['order_id'])->select();
            $res ['order_goods'] = $order_goods;
        }
        return $res;
    }

    /*
     * 获取登录 供应商id
     */
    public function getsupplier($uid){
        $res = Db::name('admin')->where('admin_id',$uid)->field('supplier_id')->find();
        if($res){
            return $res['supplier_id'];
        }
        return 0;
    }


    /*
     * 获取供应商订单
     */
    public function getOrders($map='',$order='',$limit='',$where=''){
        $list = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where($map)->where($where)->order($order)->limit($limit)->field('b.*,a.*')->select();
        return  $list;

    }
    /*
 * 获取供应商订单
 */
    public function getshOrders($map='',$order='',$limit='',$where=''){
        $list = Db::name('sh_info')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where($map)->where($where)->order($order)->limit($limit)->field('b.*,a.*')->select();
        return  $list;

    }
    /*
     * 获取订单商品
     */
    public function getOrderinfos($map='',$order='',$limit='',$type){
        $list = Db::name('order_goods')->alias('a')
            ->join('__ORDER__ b', 'a.og_order_id=b.order_id','LEFT')
            ->join('users c','a.og_uid = c.user_id','left')
            ->where($map)
            ->order($order)
            ->limit($limit)
            ->field('b.*,a.*,c.shop_name')
            ->group('b.order_id')
            ->select();
        if ($type == 1) {
            foreach ($list as $key => $val) {
                $res = Db::name('team_follow')->field('status')->where(['order_id' => $val['order_id']])->find();
                if (!empty($res) && $res['status'] != 2) {
                    unset($list[$key]);
                }
            }
        }
        $list = array_values(array_filter($list));

        $total =  Db::name('order_goods')
            ->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')
            ->join('users c','a.og_uid = c.user_id','left')
            ->where($map)
            ->group('b.order_id ')
            ->count();
        return  array($list,$total);

    }
	/*
     * 获取订单商品
     */
    public function getOrdercof($map='',$order='',$limit='',$where=''){

        $list = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where($map)->order($order)->limit($limit)->field('b.*,a.*')->select();
        return $list;

    }
    /*
     * 获取省级地址
     */
    public function getAddrs($parent_id = 0){
        $map = [
            "parent_id" => $parent_id
        ];
        $list = Db::name('region')->where($map)->select();
        return  $list;

    }
    /*
     * 获取对应名称
     */
    public function getAddrName($region_id=1){
        $map = [
            'region_id'=>$region_id
        ];
        $list = Db::name('region')->where($map)->field('region_name')->find();
        return  $list['region_name'];

    }
    /*
     * 关闭订单
     * */
    public function close($id){
        $re=db('order')->where('order_id',$id)->update(['order_isdel'=>1]);
    }
    /*
    *VIP开店
    * */
    public function buyStory($order_id,$uid){
        $giving_id = Db::name('order')->where('order_id',$order_id)->value('giving_id');
        if($giving_id){
            // 新用户
            if(strlen($giving_id)==11){
                $new_uid = $this->createUser($giving_id,$uid);
                $result = $this->openStore($new_uid, [], 1, $uid, $order_id);
                if(!$result){
                    return ['code' => 0, 'msg' => '赠送店铺失败'];
                }
            }
            // 已注册用户
            else{
                $giving_id = Db::name('order')->where('order_id',$order_id)->value('giving_id');
                $check = Db::name('users')->where(['user_id' => $giving_id])->value('is_seller');
                if($check){
                    return ['code' => 0, 'msg' => '对方已经是店主'];
                }
                $result = $this->openStore($giving_id, [], 1, $uid, $order_id);
                if(!$result){
                    return ['code' => 0, 'msg' => '赠送店铺失败'];
                }
            }
            return ['code' => 1];
        }else{
            // $this->openStory($order_id,$uid);
            $user_tree = Db::name('users_tree')->where(['t_uid' => $uid])->field('t_p_uid')->find();
            $result = $this->openStore($uid, [], 1, $user_tree['t_p_uid'], $order_id);
            if(!$result){
                return ['code' => 0, 'msg' => '购买大礼包失败'];
            }
            return ['code' => 1];
        }
    }
    /*
	*VIP开店
	* */
    public function openStory($order_id,$uid){
        $info = Db::name('order_goods')
            ->where('og_order_id',$order_id)
            ->alias('a')
            ->join('goods b','a.og_goods_id= b.goods_id')
            ->field('b.is_gift')
            ->find();
        $check = Db::name('users')->where(['user_id' => $uid])->value('is_seller');
        if($check){
            return ['code' => 0, 'msg' => '您已经是店主'];
        }
        $user_tree = Db::name('users_tree')->where(['t_uid' => $uid])->field('t_p_uid')->find();
        $result = $this->openStore($uid, [], 1, $user_tree['t_p_uid'], $order_id);
        if(!$result){
            return ['code' => 0, 'msg' => '购买大礼包失败'];
        }

        return ['code' => 1];

    }
    /* 赠送店铺
    *
    */
    public function giving($order_id,$uid){
        $giving_id = Db::name('order')->where('order_id',$order_id)->value('giving_id');
        // 新用户
        if(strlen($giving_id)==11){
            $this->createUser($giving_id,$uid);
        }
        // 已注册用户
        else{
            $check = Db::name('users')->where(['user_id' => $giving_id])->value('is_seller');
            if($check){
                return ['code' => 0, 'msg' => '对方已经是店主'];
            }
            $result = $this->openStoreZengsong($giving_id, [], 1, $uid, $order_id);
            if(!$result){
                return ['code' => 0, 'msg' => '赠送店铺失败'];
            }
        }
        return ['code' => 1];
        // $this->createStory($giving_id);
    }
    /* 创建店主
    *
    */
    public function createStory($uid){
        Db::startTrans();
        try{
            Db::name('users')->where('user_id',$uid)->update(array('is_seller'=>1));
            $story = [
                's_uid'=>$uid,//店主会员id
                's_grade'=>1,//店铺等级：1：会员店；2，高级店铺；3，旗舰店铺',
                's_comm_time'=>time(),//开店时间
            ];
            Db::name('store')->insert($story);
            Db::commit();
        }
        catch(\Exception $e){
            Db::rollback();
        }

    }
    /* 创建新用户
    *
    */
    public function createUser($mobile,$t_p_uid,$order_no){
        $res =  Db::name('users')->where('user_mobile',$mobile)->field('user_id')->find();
        //是否是老用户
        if($res){
            return $res['user_id'];
        }
        $store_service = new StoreService();
        $user_invite_code = $store_service->createInviteCode();
        $data = [
            'user_mobile'=>$mobile,
            'is_seller'=>0,
            's_invite_code'=>$user_invite_code,
            'user_avat' => request()->domain() . '/hetao.png',
            'user_sex' => 0,
            'user_account' => 0.00,
            'user_points' => 0,
            'user_reg_time' => time()
        ];
        $uid = Db::name('users')->insertGetId($data);
        $user_service = new UserService();
        $token = $user_service->createToken($uid);
        Db::name('users')->where('user_id', $uid)->update(['token' => $token]);
        $users_data = [
            't_uid'=> $uid,
            't_p_uid'=> $t_p_uid,
            't_addtime'=> time()
        ];
        Db::name('users_tree')->insert($users_data);
        return $uid;

    }
    /*
    * 获取售后商品列表
     * audit_status 售后进度状态：0，待审核；1，取消申请；2，已通过；3，售后已收货；4，已完成；5，未通过
     * og_post_status 物流状态：0，未配货；1，未发货；2，已发货；3，派送中；4，已收货；3，已退货；4，已换货
    */
    public function getasList($uid){
        //小于十五天
        $week = time()-7 * 24 * 3600;
        $time = time()-15 * 24 * 3600;
        $where = [
            'order_uid'=>$uid,
			'order_status'=>['neq',5],
			'pay_status'=>['gt',0]
        ];
        $list = Db::name('order')->field('order_id,order_no,order_create_time,order_status,yz_id,rc_id,order_coupon_id,rc_amount')->where($where)->order('order_create_time desc')->select();
        if($list){
            foreach ($list as $key=>$val){
                $orderGood = Db::name('sh_info')
					->alias('a')
					->join('goods b','a.og_goods_id = b.goods_id')
                    ->where(array('a.og_order_id'=>$val['order_id'],'a.after_state_status'=>0,'b.is_gift'=>0))
                    ->field('a.og_id,a.og_order_id,a.og_goods_id,a.og_goods_name,a.og_goods_spec_val,a.og_goods_num,a.og_goods_price,a.og_goods_pay_price,a.og_goods_thumb,a.audit_status,b.is_gift,b.prom_type,b.prom_id')
                    ->select();
                if(!$orderGood){
                    unset($list[$key]);
                }else{
                    $list[$key]['order_good'] =  $orderGood;
                    $list[$key]['order_create_time'] =  date('Y-m-d H:i',$list[$key]['order_create_time']);
                }

            }
            foreach ($list[$key]['order_good'] as $k=>$val){


                //超过七天退货
                if(($val['supplier_post_time'] >$week)&&($val['after_state_status'] == 2)){
                    $data  =[
                        'audit_status'=>5,
                        'og_post_status'=>3,
                    ];
                    Db::name('sh_info')->where('og_id',$val['og_id'])->update($data);
                    unset($list[$key]['order_good'][$k]);
                    if(!$list[$key]['order_good']){
                        unset($list[$key]);
                    }
                }
                if($list[$key]['audit_status'] == 0 && $val['after_state_status'] !=0 ) {
                    if($list[$key]['order_create_time']>$time){
                        Db::name('sh_info')->where('og_id',$val['og_id'])->update(array('audit_status'=>7));
                    }else if($list[$key]['order_create_time']>$time && $val['after_state_status'] == 1 ){
                        Db::name('sh_info')->where('og_id',$val['og_id'])->update(array('audit_status'=>8));
                    }
                }
				//元宝 购物券 充值卡
				$yin_amount = Db::name('yinzi')->where('yin_id',$list[$key]['yz_id'])->value('yin_amount');

				$c_coupon_price  = Db::name('coupon_users')->where('c_id',$list[$key]['order_coupon_id'])->value('c_coupon_price');
				$og_goods_price = $list[$key]['order_good'][$k]['og_goods_price'] * $list[$key]['order_good'][$k]['og_goods_num'];
				if($yin_amount){
					$og_goods_price -=$yin_amount;
				}
				if($c_coupon_price){
					$og_goods_price -=$yin_amount;
				}
				if($og_goods_price<=0){
					$og_goods_price = 0;
				}
				$list[$key]['order_good'][$k]['og_goods_price'] = $og_goods_price;
            }
        }
        return $list;
    }
    /*
    * 获取售后申请记录
    */
    public function getasRecord($uid, $p = 1){

        $list = Db::name('sh_info')
            ->where(array('og_uid'=>$uid,'after_state_status'=>['neq',0],'og_order_status'=>['neq',5]))
			->order('apply_time desc')
            ->page($p, 10)
            ->select();
        $total = Db::name('sh_info')
            ->where(array('og_uid'=>$uid,'after_state_status'=>['neq',0],'og_order_status'=>['neq',5]))
            ->count();
        $data = [];
        foreach ($list as $key=>$val){
            $list[$key]['client_post_no'] = $val['client_post_no'] ? $val['client_post_no'] : '';
            $data[$key]['apply_time'] = date('Y-m-d H:i',$val['apply_time']);
            $data[$key]['audit_no'] = $val['audit_no'];
            $data[$key]['og_order_id'] = $val['og_order_id'];
            $data[$key]['og_id'] = $val['og_id'];
			$orderInfo = Db::name('order')->where('order_id',$val['og_order_id'])->find();
			$goodsInfo = Db::name('goods')->where('goods_id',$val['og_goods_id'])->field('prom_type,prom_id')->find();
			$list[$key]['prom_type'] = $goodsInfo['prom_type'];
			$list[$key]['prom_id'] = $goodsInfo['prom_id'];
			$time = time();
			$ok_time = $val['order_goods_ok_time']+3600*24*7;
			if($ok_time<$time&&$val['audit_status']==3){
				Db::name('sh_info')->where('og_id',$val['og_id'])->update(array('audit_status'=>5,'og_order_status'=>10));
				$tota_num = Db::name('sh_info')->where(array('og_order_id'=>$val['og_order_id'],'audit_status'=>5,'og_order_status'=>10))->count();
				$shiji_num = Db::name('sh_info')->where(array('og_order_id'=>$val['og_order_id']))->count();
				if($tota_num == $shiji_num ){
					Db::name('order')->where(array('og_order_id'=>$val['og_order_id']))->update(array('og_order_status'=>10));;
				}
				  $data = [
                    'as_id'=>$val['og_id'],//售后id
                    'agent_type'=>5,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户5：总管理员;
                    'agent_id'=>1,//经办人id; 合陶家官方
                    'agent_name'=>'合陶家官方系统',//经办人名称
                    'as_log_desc'=>'售后时间到期,自动完成售后',//日志内容
                    'agent_status'=>5,
                    'agent_note'=>'售后时间到期,自动完成售后',
                    'add_time'=>time(),
                    'as_status'=>2,//售后进度状态：0，待审核；1，申请审核；2：审核中；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
                ];
				$this->writelog($data);
			}

			if($val['after_state_status']==1){
				if($orderInfo['yz_id']){
					Db::name('yinzi')->where('yin_id',$orderInfo['yz_id'])->update(array('yin_stat'=>2));
				}
				if($orderInfo['rc_id']){
					Db::name('user_rc')->where('card_id',$orderInfo['rc_id'])->update(array('card_stat'=>1));
				}
				if($orderInfo['order_coupon_id']){
						Db::name('coupon_users')->where(array('c_id'=>$orderInfo['order_coupon_id'],'c_uid'=>$uid))->update(array('coupon_stat'=>1));
				}

				$commission = Db::name('commission')->where('commi_order_id',$orderInfo['order_id'])->find();
				$commission = '';

				if($commission['commi_p_uid']){
					$res = Db::name('users')->where('user_id',$commission['commi_p_uid'])->setDec('user_account',$commission['commi_p_price']);
					if($res){
						$this->accountLog($commission['commi_p_uid'],$commission['commi_p_price']);
					}
				}
				if($commission['commi_uid']){
					$res = Db::name('users')->where('user_id',$commission['commi_uid'])->setDec('user_account',$commission['commi_price']);
					if($res){
						$this->accountLog($commission['commi_uid'],$commission['commi_price']);
					}
				}
				if($commission['commi_g_uid']){
					$res = Db::name('users')->where('user_id',$commission['commi_g_uid'])->setDec('user_account',$commission['commi_g_price']);
					if($res){
						$this->accountLog($commission['commi_g_uid'],$commission['commi_g_price']);
					}
				}
				$commission = Db::name('commission')->where('commi_order_id',$orderInfo['order_id'])->delete();
			}
            unset($list[$key]['apply_time']);
            unset($list[$key]['og_order_id']);
            unset($list[$key]['og_id']);
            unset($list[$key]['audit_no']);
            $data[$key]['goods'] = $list[$key];
        }
        $data_arr['list'] = $data;
        $data_arr['total'] = $total;
        return $data_arr;
    }
    /*
	* 获取售后申请记录
	*/
    public function getasRecords($uid){
        $list = Db::name('order')->field('order_id,order_no,order_create_time,yz_id,rc_id')->where('order_uid',$uid)->select();
        if($list){
            foreach ($list as $key=>$val){
                $orderGood = Db::name('sh_info')
                    ->where(array('og_order_id'=>$val['order_id'],'after_state_status'=>['neq',0]))
                    ->field('og_id,og_order_id,og_goods_name,og_goods_spec_val,og_goods_num,og_goods_price,og_goods_pay_price,og_acti_id,og_goods_thumb,audit_status,order_goods_ok_time,after_state_status')
                    ->select();
                if(!$orderGood){
                    unset($list[$key]);
                }else{
					foreach($orderGood as $value){
						$time = time();
						$ok_time = $value['order_goods_ok_time']+3600*7;
						if($ok_time<$time&&$value['audit_status']==3){
							Db::name('order_goods')->where('og_id',$value['og_id'])->update(array('audit_status'=>5));

							if($value['after_state_status']==1){
							Db::name('yinzi')->where('yz_id',$list[$key]['yz_id'])->update(array('yin_stat'=>2));
							Db::name('user_rc')->where('card_id',$list[$key]['rc_id'])->update(array('card_stat'=>1));
							$commission = D::name('commission')->where('commi_order_id',$list[$key]['yz_id'])->find();
							if($commission['commi_p_uid']){
								$res = Db::name('users')->where('user_id',$commission['commi_p_uid'])->setDec('user_account',$commission['commi_p_price']);
								if($res){
									$this->accountLog($commission['commi_p_uid'],$commission['commi_p_price']);
								}
							}
							if($commission['commi_uid']){
								$res = Db::name('users')->where('user_id',$commission['commi_uid'])->setDec('user_account',$commission['commi_price']);
								if($res){
									$this->accountLog($commission['commi_uid'],$commission['commi_price']);
								}
							}
							if($commission['commi_g_uid']){
								$res = Db::name('users')->where('user_id',$commission['commi_g_uid'])->setDec('user_account',$commission['commi_g_price']);
								if($res){
									$this->accountLog($commission['commi_g_uid'],$commission['commi_g_price']);
								}
							}
							$commission = D::name('commission')->where('commi_order_id',$list[$key]['yz_id'])->delete();
						}
						}


					}
                    $list[$key]['order_good'] =  $orderGood;
                }
            }

        }
        return $list;
    }
	/*
     *售后退货申请完成 明细记录
    */
    public function accountLog($uid,$acco_num){

		$log_insert = [
			'a_uid' => $uid,
			'acco_num' => -$acco_num,
			'acco_type' => 11,
			'acco_desc' => '订单退货取消返利',
			'acco_time' => time(),
		];
		Db::name('account_log')->insert($log_insert);

	}

    public function getasType($uid, $type=1)
    {
        $as_info = Db::name('as_type')->where('status',$type)->field('t_type')->order('t_order desc')->select();
        $field = ($type == 1) ? 'addr_area,addr_cont,addr_receiver,addr_phone' : 'addr_receiver,addr_phone';
        $user_addr = Db::name('addr')->where(['a_uid' => $uid, 'is_default' => 1])->field($field)->find();
        return ['as_info' => $as_info, 'addr_info' => $user_addr];
	}
    /*
     * 获取售后申请记录
     * $og_id  售后商品id
     */
    public function getasInfo($og_id){
        $user_model = new User();
        $row = Db::name('sh_info')
            ->where(array('og_id'=>$og_id))
            ->field('og_order_id,audit_status,audit_no,apply_time,audit_issue,or_goods_note,back_address,status,or_goods_note,supplier_status,or_supplier_note,financial_status,or_financial_note,supplier_post_no,supplier_post_time,after_state_status,audit_address,audit_reason,audit_no')
            ->find();

        if($row){
            $orderInfo = Db::name('order')->where('order_id',$row['og_order_id'])->field('order_id,order_uid,order_addrid')->find();
            $row['apply_time']  =  date('Y-m-d H:i',$row['apply_time']);

            $addr_info = $user_model -> addrInfo($orderInfo['order_uid'], $orderInfo['order_addrid']);

            $row['addr_info'] = $addr_info;
			$map = [
				'as_id'=>$og_id,
				'as_status'=>['neq',2],
			];
            $as_log =  Db::name('as_log')->where('as_id',$og_id)->group('as_status')->field('add_time,as_status')->order('as_status asc')->select();
            $row ['as_log'] = '';
            if($as_log){
                foreach($as_log as $key=>$val){
                    $as_log[$key]['add_time'] = date('Y-m-d H:i', $val['add_time']);
                }
                $row ['as_log'] = $as_log;
            }

        }
        return $row;
    }

    /*
	 * 创建售后服务单号
	 */
    public function createAsNo(){
        $audit_no = 'SH'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $is_exist = Db::name('sh_info')->where('audit_no', $audit_no)->field('audit_no')->find();
        while($is_exist){
            $this->createAsNo();
        }
        return $audit_no;
    }
    /*
	 * 售后提交前判断
	 */
    public function asJudge($og_id){
        $res =  Db::name('sh_info')->where('og_id',$og_id)->field('audit_status')->find();
     //   if($res['status']==3 ||$res['financial_status']==3 || $res['supplier_status']==3){
        if($res['status']==3 ||$res['financial_status']==3){
             return 0;
        }else{
            return 1;
        }
//        if($res['audit_status'] > 0){
//            return 1;
//        } else {
//            return 0;
//        }
    }
    /*
	 * 创建售后提交
	 */
    public function asSubmit($og_id,$data){
        Db::startTrans();
        try{
            $og_info =  Db::name('order_goods')->where('og_order_id',$og_id)->find();
            //订单信息
            $order_info = Db::name('order')->where("order_id",$og_id)->value('order_pay_price');
            $data = array_merge($og_info, $data);
            $data['og_goods_pay_price'] = $order_info;
            $res =  Db::name('sh_info')->insert($data);
            if($res){
                //将服务单号保存于订单商品中
                Db::name('order_goods')->where('og_order_id',$og_id)->update(['audit_no'=>$data['audit_no']]);
                Db::name('order')->where('order_id',$og_info['og_order_id'])->update(array('after_status'=>$data['after_state_status'],'order_status'=>6));
            }
            // 提交事务
            Db::commit();
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return false;
        }


    }
    /*`
	 * 获取进度信息
     * $og_id 售后商品id
	 */
    public function schedule($og_id,$type_stu){
        if( $type_stu == 1){
            $row = Db::name('sh_info')->field('og_id,audit_no,apply_time,audit_status,audit_address,back_address,og_order_id')->where('og_order_id',$og_id)->find();
        }else{
            $row = Db::name('sh_info')->field('og_id,audit_no,apply_time,audit_status,audit_address,back_address,og_order_id')->where('og_id',$og_id)->find();
        }
		$row['back_address'] = $row['back_address']?$row['back_address']:'';
        if($row['og_id']){

            $logList =  Db::name('as_log')->where('as_id',$row['og_id'])->field('agent_type,agent_name,as_log_desc,add_time,agent_note,agent_status')->select();
            $num = count($logList) - 1;
            if($logList){
				//1:客服; 2:供应商；3：财务 4:用户
                foreach ( $logList as $key=>$val){
                    $logList[$key]['now'] =  0;
                    if($num == $key){
                        $logList[$key]['now'] = 1;
                    }
                    $logList[$key]['add_time'] = date("Y-m-d H:i",$val['add_time']);
                }
            }
            $row['log_list'] = $logList;
            $row['apply_time'] = date("Y-m-d H:i",$row['apply_time']);
            return $row;
        }
        return 0;
    }
    /*
	 * 获取进度信息
     * $og_id 售后商品id
	 */
    public function aslog($og_id,$uid){
        $data = [
            'as_id'=>$og_id,
            'as_log_desc'=>'售后提交审核',
            'add_time'=>time(),
            'agent_type'=>4,
            'agent_id'=>$uid,
            'as_status'=>0,
        ];
        $res = Db::name('as_log')->insert($data);
        return $res;
    }
    /*
	 * 获取售后联系人方式
     * $uid
	 */
    public function asRelation($order_id){
        $row = Db::name('order')->where('order_id',$order_id)->field('phone,consigee')->find();
        return $row;
    }
    /*
     * 客服、供应商、财务等审核未通过的 7天后 自动结束完成订单
     * 按供应商填写订单号后7天后自动确认收货，结束售后。
     * $uid
	 */
    public function judeOrder(){
        // 售后超过7天自动收货
        $week = time() - 7*24*3600;
//        $time = 15*24*3600;
        // 查出所有 换货 没有结束的 商品
        $where = '(after_state_status = 2 and og_order_status != 4 and supplier_post_time > '.$week.') 
        or ((status = 3 or supplier_status = 3 or financial_status = 3 ) and examine_time >'.$week. ')';
        $list = Db::name('sh_info')
            ->where($where)
            ->field('og_id, og_order_id')
            ->select();
        if ($list) {
            //$id_arr = array_column($list, 'og_id');
            $id_arr = Db::name('sh_info')
            ->where($where)
            ->column('og_id');
            //$order_id_arr = array_column($list, 'og_order_id');
            $order_id_arr =Db::name('sh_info')
            ->where($where)
            ->column('og_order_id');
            $order_id_arr = array_unique($order_id_arr);
            $data = [
                'og_post_status'=>4,
                'audit_status'=>5,
                'order_goods_ok_time' => time()
            ];
            $res = Db::name('sh_info')->where(['og_id' => implode(',', $id_arr)])->update($data);
            if ($res) {
                // 修改 订单状态
                foreach ($order_id_arr as $val) {
                    // 如果该订单 没有其他商品在 售后 则 修改订单表 状态 和 完成时间
                    $wheres = '('. $where.') and og_order_id = '.$val;
                    $result = Db::name('sh_info')->field('og_id')->where($wheres)->find();
                    // 没有查到 该订单的 未完成售后
                    if (empty($result)) {
                        // 把 该订单 状态 改为已完成
                        // 20190113 不知道 订单表以哪个状态为准 都修改
                        $order_info = [
                            'order_finish_time' => time(),
                            'order_status' => 4,
                            'og_order_status' => 4
                        ];
                        Db::name('order')->where(['order_id' => $val])->update($order_info);
                    }
                }

            }
        }
//        foreach ($list as $val){
//            $orderInfo  =  Db::name('order')->where('order_id',$val['og_order_id'])->field('order_create_time')->find();
//            if($val['audit_status']== 0  ){
//                if($orderInfo['order_create_time']> $time){
//                    Db::name('sh_info')->where('og_id',$val['og_id'])->update(array('audit_status'=>7));
//                }else if($orderInfo['order_create_time']> $week){
//                    Db::name('sh_info')->where('og_id',$val['og_id'])->update(array('audit_status'=>8));
//                }
//            }else if($val['after_state_status'] == 1){
//                if($orderInfo['supplier_post_time']>$week){
//                    $data = [
//                        'og_post_status'=>4,
//                        'audit_status'=>5,
//                    ];
//                    Db::name('sh_info')->where('og_id',$val['og_id'])->update($data );
//                }
//            }
//        }
    }
    /*
	 * 获取进度信息
     * $og_id 售后商品id
	 */
    public function writelog($data){
        if($data){
			$where=[
				'agent_type'=>$data['agent_type'],
				'agent_id'=>$data['agent_id'],
				'agent_status'=>$data['agent_status'],
			];
            $res = Db::name('as_log')->where($where)->find();
            if($res){
                Db::name('as_log')->where('as_log_id',$res['as_log_id'])->update($data);
            }else{
                Db::name('as_log')->insert($data);
            }
        }
    }
    /*
	 * 改变售后状态
	 */
    public function asProcess($og_id,$status){
        Db::name('as_log')->where('og_id',$og_id)->update(array('audit_status',$status));
    }
    /* 改变售后状态
	 */
    public function asOrder($og_id,$status){
        Db::name('sh_info')->where('og_id',$og_id)->update(array('audit_status'=>$status, 'examine_time' => time()));
    }
    /*
     *  改变售后状态
     */
    public function setogorderstatus($og_id,$status){
        Db::name('sh_info')->where('og_id',$og_id)->update(array('og_order_status'=>$status, 'order_goods_ok_time' => time()));
    }
    /*
    *  改变订单状态
    */
    public function setorderstatus($order_id,$status){
        Db::name('order')->where('order_id',$order_id)->update(array('order_status'=>$status, 'og_order_status' => $status, 'order_finish_time' => time()));
    }
	/*
	*客新增功能 售后取消
   	*/
   	public function afterundo($og_id){

        Db::startTrans();
        try{
            $og_order_id = Db::name('sh_info')->where('og_id',$og_id)->value('og_order_id');
            if($og_order_id){
                Db::name('order')->where('order_id',$og_order_id)->update(['after_status'=>0]);
            }
            $data = [
                'after_state_status'=>0,
                'audit_status'=>0,
                'og_refund_price'=>0,
                'status'=>0,
                'supplier_status'=>0,
                'financial_status'=>0,
                'or_goods_note'=>'',
                'or_financial_note'=>'',
                'or_supplier_note'=>'',
                'apply_time'=>0,
                'audit_no'=>'',
                'audit_issue'=>'',
                'audit_address'=>'',
                'audit_reason'=>'',
                'order_return_no'=>'',
                'refund_status'=>0,
                'supplier_post_status'=>0,
                'supplier_post_no'=>'',
                'supplier_post_type'=>'',
                'supplier_post_time'=>0,
                'post_agin_status'=>1,
                'client_post_no'=>'',
                'client_post_type'=>'',
            ];
            $res = Db::name('sh_info')->where('og_id',$og_id)->update($data);

            // 提交事务
            Db::commit();
            return $res;
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return false;
        }


		/*$result = 1;
		if($order_list){
			foreach($order_list as $val){
				if($val['after_state_status']>0&&$val['og_order_status']!=4){
					$result = 0;
				}
			}
		}
		if($result){
			//取消售后 返回原状态
			$order_data=[
				'after_status'=>0,
			];
			$res = Db::name('order')->where('order_id',$order_id)->update($order_data);
			if($res===false){
				return $order_id;
			}
		}*/
	}

	/*
	*获取用户名称
   	*/
   	public function getUsername($uid){
		$user_name = Db::name('users')->where('user_id',$uid)->value('user_name');

		$user_name = $user_name?$user_name:'';
		return  $user_name;
	}
	/*
	*购买限制
   	*/
   	public function orderLimit($uid,$goods_id,$prom_type='',$prom_id='',$number){
		$map = [
			'a.og_uid'=>$uid,
			'a.og_goods_id'=>$goods_id,
			'b.order_status'=>['neq',5],
			'b.order_isdel'=>['eq',0],
		];
		$order_num = Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->where($map)->sum('og_goods_num');
		$order_num += $number;


		//1：团购，2:预售，3拼团，4砍价，5秒杀，6满199减100,7:99元3件，8，满2件打9折，9以上自定义活动
		if($prom_type == 1){
			$buy_limit = Db::name('group_goods')->where(array('goods_id'=>$goods_id))->value('buy_limit');
		}else if($prom_type == 5){
			$buy_limit = Db::name('flash_goods')->where(array('id'=>$prom_id))->value('buy_limit');
		}else{
			return 1;
		}
        if($buy_limit==0){
            return 1;
        }elseif($buy_limit>0){
                if($order_num>$buy_limit){
            return 0;
        }else{
            return 1;
        }

        }

	}
	/*
	*购买限制
   	*/
   	public function orderClient($uid,$og_id,$client_post_no,$client_post_type){
		$map =[
			'og_uid'=>$uid,
			'og_id'=>$og_id,
		];
		$res = Db::name('order_goods')->where($map)->update(array('client_post_no'=>$client_post_no,'client_post_type'=>$client_post_type));
		return $res;
	}
	/*
	*物流查询
   	*/
   	public function postClient($uid,$og_id){
		$map =[
			'og_uid'=>$uid,
			'og_id'=>$og_id,
		];
		$res = Db::name('order_goods')->where($map)->field('client_post_no,client_post_type')->find();
		if($res){
			$list = $this->postShows($res);
		}
		return $list;

	}
	/*
     * 查看物流
     */
    public function postShows($goods_info){
        // if(function_exists('curl_init')){
        //     $ch = curl_init();
        //     curl_setopt($ch, CURLOPT_URL, 'http://www.kuaidi100.com/query?type='.$goods_info['client_post_type'].'&postid='.$goods_info['client_post_no']);
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //     $post_info = curl_exec($ch);
        //     curl_close($ch);
        // }
        // else{
        //     $post_info = file_get_contents('http://www.kuaidi100.com/query?type='.$goods_info['client_post_type'].'&postid='.$goods_info['client_post_no']);
        // }
         $post_info=$this->kuaidi100($goods_info['client_post_type'],$goods_info['client_post_no']);
        $arr = [];
        $tmp = json_decode($post_info, true);
        $arr = [];
        if($tmp['status'] == 200){
            foreach($tmp['data'] as &$v){
                unset($v['ftime']);
//                unset($v['location']);
            }
            $arr['data'] = $tmp['data'];
            $arr['state'] = $tmp['state'];  //0，在途；1，揽件；2，出现问题；3，签收；4，退签；5.派件；6，退回
        }else{
			$arr['context'] = '暂无信息';
        }
        //配送企业
        return $arr;
    }

    public function getAsData($uid,$og_id)
    {
        $row = Db::name('sh_info')
            ->where(array('og_id'=>$og_id,'og_uid'=>$uid))
            ->find();
        $detail = [];
        $status = 1;
        $address = '';//回邮地址
        $user_post = [];//用户寄回的
        $supplier_post = [];//供应商寄回的物流信息
        $list = [];
        $no_access_reason = '';
        $good_info = [];
        if(empty($row)) return [];
        $order_id = $row['og_order_id'];
        $good_info['og_goods_prices'] = $row['og_goods_price']*$row['og_goods_num'];
        $good_info['og_goods_thumb'] = $row['og_goods_thumb'];
        $good_info['og_goods_name'] = $row['og_goods_name'];
        $good_info['og_goods_spec_val'] = $row['og_goods_spec_val'];
        $good_info['og_goods_num'] = $row['og_goods_num'];
        $good_info['og_goods_pay_price'] = $row['og_goods_pay_price'];

        $address = $row['back_address'];//回邮地址
        //售后信息
        $detail['audit_reason'] = $row['audit_reason'];//售后原因
        $detail['audit_issue'] = $row['audit_issue'];//售后问题描述
        $detail['audit_no'] = $row['audit_no'];//售后单号
        $detail['apply_time'] = $row['apply_time'];//申请时间
        $detail['apply_time'] = date('Y-m-d H:i:s', $row['apply_time']);//售后申请时间
        $detail['type'] = $row['after_state_status'];//售后类型
        $after_status = 0;
        //审核是否成功
        if($row['status']==3 || $row['supplier_status']==3 || $row['financial_status']==3){
            $status = 6;//审核未通过
            if($row['status']==3){
                $after_status = 1;
                $no_access_reason = $row['or_goods_note'];//客服审核不通过
            }elseif($row['supplier_status']==3){
                $after_status = 2;
                $no_access_reason = $row['or_supplier_note'];//供应商审核不通过
            }elseif($row['financial_status']==3){
                $after_status = 3;
                $no_access_reason = $row['or_financial_note'];//财务审核不通过
            }

        }else{
            //审核进行中
            switch($row['after_state_status']){
                case 1:                //退货
                    if($row['refund_status']==1){
                        $status = 5;
                    }elseif ($row['financial_status']==2 && $row['refund_status']==0){
                        $status = 4;
                    }elseif($row['supplier_post_status']==1 && $row['financial_status']==0){
                        $status = 3;
                    } elseif ($row['status']==2 && $row['supplier_status']==2 && $row['supplier_post_status']==0){
                        $status = 2;
                    } else {
                        $status = 1;
                    }
                    break;
                case 2:                 //换货
                    if($row['client_status']==1){
                        $status = 5;
                    }else if(!empty($row['supplier_post_no']) && $row['supplier_post_status']==1 && $row['client_status']==0){
                        $status = 4;//供应商退回货物
                    }else if($row['supplier_post_status']==1 && empty($row['supplier_post_no'])){
                        $status = 3;//供应商收到退回货物
                    } elseif ($row['status']==2 && $row['supplier_status']==2 && $row['supplier_post_status']==0){
                        $status = 2;//供应同意退货
                    } else {
                        $status = 1;
                    }
                    break;
                case 3:                 //仅退款
                    if($row['refund_status']==1){
                        $status = 4;
                    }else if ($row['financial_status']==2 && $row['refund_status']==0){
                        $status = 3;
                    } else if ($row['status']==2 && $row['supplier_status']==2 && $row['financial_status'] == 2){
                        $status = 2;
                    } else {
                        $status = 1;
                    }
                    break;
            }

            //用户寄回的物流信息
            if(!empty($row['client_post_no']) && !empty($row['client_post_type'])){
                $goods_info = ['client_post_type'=>$row['client_post_type'],'client_post_no'=>$row['client_post_no']];
                $arr = $this->postShows($goods_info);
                if(isset($arr['data']) && count($arr['data'])>0){
                    $user_post = $arr['data'][0];//context和time
                }
            }
            //供应商寄回的物流信息
            if(!empty($row['supplier_post_no']) && !empty($row['supplier_post_type'])){
                $goods_infos = ['client_post_type'=>$row['supplier_post_type'],'client_post_no'=>$row['supplier_post_no']];
                $arr = $this->postShows($goods_infos);
                if(isset($arr['data']) && count($arr['data'])>0){
                    $supplier_post = $arr['data'][0];//context和time
                }
            }
        }


        $list = [
            'detail'=>$detail,
            'status'=>$status,
            'address'=>$address,
            'user_post'=>$user_post,
            'supplier_post'=>$supplier_post,
            'good_info'=>$good_info,
            'order_id'=>$order_id,
            'after_status' => $after_status,
            'no_access_reason'=>$no_access_reason
        ];

        return $list;
    }

	public function afterSaled($uid,$og_id){
		$res = Db::name('sh_info')->where('og_id',$og_id)->update(array('audit_status'=>5,'og_order_status'=>10));
		if(!$res){
			return 0;
		}
        $og_order_id = Db::name('sh_info')->where('og_id',$og_id)->value('og_order_id');
		$tota_num = Db::name('sh_info')->where(array('og_order_id'=>$og_order_id,'audit_status'=>5,'og_order_status'=>10))->count();
		$shiji_num = Db::name('sh_info')->where(array('og_order_id'=>$og_order_id))->count();
		if($tota_num == $shiji_num ){
			Db::name('order')->where(array('og_order_id'=>$og_order_id))->update(array('og_order_status'=>10));;
		}
        $userInfo = Db::name('users')->where('user_id',$uid)->find();
        $data = [
			'as_id'=>$og_id,//售后id
			'agent_type'=>4,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户5：总管理员;
			'agent_id'=>$uid,//经办人id;
			'agent_name'=>$userInfo['user_name'],//经办人名称
			'as_log_desc'=>'售后时间到期,自动完成售后',//日志内容
			'agent_status'=>5,
			'agent_note'=>'售后时间到期,自动完成售后',
			'add_time'=>time(),
			'as_status'=>5,//售后进度状态：0，待审核；1，申请审核；2：审核中；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
		];
		$this->writelog($data);
		return $res;
	}
    //添加充值卡日志
    public function add_rc_log($uid,$card_id,$price,$describe){
        $data = [
            'uid'  => $uid,
            'user_rc_id'  => $card_id,
            'time'     => time(),
            'price'    => $price,
            'use_type' => $describe
        ];
        Db::name('rc_log')->insert($data);
    }

    /**
     * 订单软删除
     * @param array $ids 订单编号
     */
    public function softDelete($ids)
    {
        Db::startTrans();

        //日志记录
        $add['uid'] = session('admin_id');
        $add['ip_address'] = request()->ip();
        $add['controller'] = request()->controller();
        $add['action'] = request()->action();
        $add['remarks'] = '关闭订单';
        $add['number'] = $ids;
        $add['create_at'] = time();
        $log = db('web_log')->insert($add);
        if($ids){
            foreach($ids as $val){
                $map['order_id']=$val;
                $res = Db::name('order')->where($map)->update(['order_isdel'=>1]);
            }
        }
        if ($log && $res) {
            Db::commit();
            return true;
        }
        Db::rollback();
        return false;
    }

    /**
     * 获取订单详细信息
     */
    public function getOrderDetails($id)
    {
        $order = Db::name('order a')
            ->field('a.*,b.user_name,b.shop_name')
            ->join('users b','a.order_uid = b.user_id','LEFT')
            ->where(['a.order_id' => $id])
            ->find();
        $pay_type = [
            'balance'=>'余额支付',
            'alipay'=>'支付宝',
            'wxpay'=>'微信支付',
            'offpay'=>'货到付款',
            '积分支付'=>'积分支付',
            'jsapi'=>'公众号支付'
        ];
        if ($order['order_pay_code']){
            $order['order_pay_code'] = $pay_type[$order['order_pay_code']];
        }else{
            $order['order_pay_code']='-';
        }

        $order['address'] =$this->get_region_name($order['pro_name']).$this->get_region_name($order['city_name']).$this->get_region_name($order['area']).$order['address'];
        $order['sender_name'] = Db::name('admin')->where('admin_id',$order['sender_id'])->value('nickname');
        $order['order_create_time'] = date('Y-m-d',$order['order_create_time']);
        $order_goods = Db::name('order_goods')->alias('og')->join('goods g','og.og_goods_id = g.goods_id','left')->where(['og.og_order_id' => $id])->select();
        $config_data = Db::name('config')->field('app_tell,shop_address,shop_phone')->find();
        $order['shop_address'] = $config_data['shop_address'];
        $order['app_tell'] = $config_data['app_tell'];
        $order['shop_phone'] = $config_data['shop_phone'];
        $data['order'] = $order;
        $data['order_goods'] = $order_goods;

        return $data;
    }
    public function get_region_name($id){
        return db('region')->where('region_id',$id)->value('region_name');
    }
}
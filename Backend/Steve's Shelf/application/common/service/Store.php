<?php
namespace app\common\service;

use think\Db;
use app\common\model\Store as StoreModel;
use app\common\model\Order as OrderModel;
use app\common\service\User as UserService;
use app\common\service\Goods as GoodsService;

class Store extends Base{

    public function __construct(){
        parent::__construct();
        $model = new StoreModel();
        $this->model = $model;
    }

    /*
     * 店铺首页
     */
    public function storeHome($uid, $p){
        $user_service = new UserService();
        $user_info = $user_service->userInfo(['user_id' => $uid], 'is_seller,user_sign');

        //店主
        if($user_info['is_seller'] == 1){
            $order_model = new OrderModel();
            $store_info = $this->getStoreInfo($uid);

            if(!$store_info){
                return ['code' => 0, 'msg' => '店铺信息不存在'];
            }

            // 验证店铺是否可以升级
//			if($store_info['s_grade'] != 3){
//				$type = $store_info['s_grade'];
//				$res = $this->isUpgrade($uid, $store_info['s_id'], $type);
//				if($res){
//					//升级店铺
//					$res = $this->storeUpgrade($store_info, $type);
//					if(!$res){
//						return ['code' => 0, 'msg' => '店铺升级失败'];
//					}
//				}
//			}
            if(date('d', time()) >=26){
                $start = strtotime(date('Y-m-26', time()));
                $end = time();
            }
            //当月26号之前，从上月26号统计
            else{
                $current_month = date('Y-m-26', time());
                $start = strtotime($current_month." -1 month");
                $end = strtotime($current_month);
            }
            /* 店铺数据统计 20190213*/
//            $store_saleroom = $this->getStoreSaleRoom($uid);		// 店铺销售额
//            $store_total = $this->getStoreTotal($uid);
            $store_saleroom = $this->getStoreSaleRooms($uid,$start,$end);		// 当月店铺销售额(包含自购物和vip购物）
            $my_total = $this->myShoppings($uid, $start,$end);
            $child_vip_total = $this->vipShoppings($uid, $start,$end);
            $store_saleroom['my_total'] = sprintf('%0.2f', $my_total ?: 0.00);
            $store_saleroom['child_vip_total'] = sprintf('%0.2f', $child_vip_total ?: 0.00);

            //我的大礼包 和 我的2级 下级
            /*$sql = "SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile,b.is_seller FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid UNION SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile,b.is_seller FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_g_uid=$uid ORDER BY add_time desc";
            $tree_info = Db::name('users_tree')->query($sql);
            $user_ids = [$uid];
            foreach($tree_info as $v){
                if($v['is_seller']==1){
                    $user_ids[] = $v['user_id'];
                }
            }*/

            if($store_info['s_grade']==3){
                //获取子店铺所有大礼包（包含自己的）
                $log_order_price = $this->getChildGiftBags($uid,$start,$end);
                $store_saleroom['bag'] = sprintf('%0.2f', $log_order_price ?: 0.00);
            }else{
                $store_saleroom['bag'] = 0;
            }

            // 店铺总收入

            $data = [
                'saleroom' => $store_saleroom
//                'total' => $store_total,
            ];


            // $store_info['total'] = $sr_total = $this->getStoreSaleRoom($uid, 1);
            // 店铺销售额（奖励）
//			$sr_total = $this->getStoreSaleRoom($uid, 1);

            //奖励（定时执行）
            // if(($store_info['s_grade'] == 3) && (date('d', time()) == '12') && $sr_total['total'] >= 1600){
            // 	$is_reward = Db::name('reward')->where(['reward_uid' => $uid])->field('reward_time')->order('reward_id desc')->find();
            // 	if(date('Y-m', $is_reward['reward_time']) != date('Y-m')){
            // 		$this->bestReward($uid, $sr_total['total']);
            // 	}
            // }
            //销售额展示信息
            // $msg = '';
            // if($store_info['s_grade'] > 1){
            // 	if($sr_total < 1600){
            // 		$msg = '您还差'.(1600 - $sr_total).'销售额即可获得奖励';
            // 	}
            // 	else if(1600 <= $sr_total && $sr_total < 5000){
            // 		$msg = '您还差'.(5000 - $sr_total).'销售额即可获得奖励';
            // 	}
            // 	else if(5000 <= $sr_total && $sr_total < 10000){
            // 		$msg = '您还差'.(40000 - $sr_total).'销售额即可获得奖励';
            // 	}
            // }
            // else{
            // 	$msg = '店铺升级可获得额外奖励';
            // }
            // $store_info['total'] = '¥ '.$store_info['total'];
            // $store_info['sr_msg'] = $msg;
            $data['store'] = $store_info;
            return ['code' => 1, 'data' => $data];
        }
        //普通会员
        else{
            $num = 10;
            $s = ($p - 1) * $num;
            //上级
            $tree = Db::name('users')->alias('a')->join('__USERS_TREE__ b', 'a.user_id=b.t_p_uid', 'LEFT')->field('a.user_sign,b.t_p_uid')->where('b.t_uid', $uid)->find();
            if($tree['t_p_uid']){
                //我的大礼包
                // $my_bag = Db::name('store_gift_bag')->where(['bag_uid' => $uid, 'bag_buy_stat' => 0])->field('bag_id,bag_goods_id as goods_id')->select();
                $store_info = $this->getStoreInfo($tree['t_p_uid']);
                $goods_list = $this->getStoreGoods($store_info['s_id'], 'vip', $s='', $num='');
                // $store_info['bag'] = $my_bag;

                $store_info['goods_total'] = count($goods_list);
                $store_info['goods'] = $goods_list;
                return ['code' => 1, 'data' => $store_info];
            }
            else return ['code' => 0, 'msg' => '无售后联系人'];
        }
    }

    /**
     * 查找我的所有下级店主
     */
    public function getAllChildSellers($uid)
    {
//        $my_child = Db::name('users_tree')->where(['t_p_uid' => $uid])->field('t_uid')->select();
        $my_child = Db::name('users_tree')->alias('a')->join('__STORE__ b ','b.s_uid= a.t_uid')->where(['a.t_p_uid' => $uid])->field('a.t_uid')->select();
        $uids = [];
        if($my_child){
            foreach ($my_child as $v){
                $uids[] = $v['t_uid'];
                $uid = $this->getAllChildSellers($v['t_uid']);
                if(!empty($uid)){
                    $uids = array_merge($uids,$uid);
                }
            }
            $uids = array_unique($uids);
        }
        return $uids;
    }

    /*
     * 销售额规则
     */
    public function srRules($uid){
        $store_info = $this->getStoreInfo($uid);
        $sr_total = $this->getStoreSaleRoom($uid, 1);
        //规则
        $arr = [];
        $cond = [];
        $msg = '';
        //高级店主
        if($store_info['s_grade'] == 2){
            $f_condition = 1600;
            $f_reward_1 = 20 / 100;		//本店铺+下级vip
            $s_condition = 5000;
            $s_reward_1 = 25 / 100;
            $t_condition = 10000;
            $t_reward_1 = 30 / 100;
        }
        //旗舰店主
        else if($store_info['s_grade'] == 3){
            $f_condition = 1600;
            $f_reward_1 = 25 / 100;		//本店铺+下级vip
            $s_condition = 5000;
            $s_reward_1 = 30 / 100;
            $t_condition = 10000;
            $t_reward_1 = 35 / 100;
        }
        //销售额展示信息
        if($sr_total < $f_condition){
            $msg = '销售额还差'.($f_condition - $sr_total).'即可获得奖励';
            $reward = $f_reward_1 * 100;
        }
        else if($f_condition <= $sr_total && $sr_total < $s_condition){
            $msg = '销售额还差'.($s_condition - $sr_total).'即可获得奖励';
            $reward = $s_reward_1 * 100;
        }
        else if($s_condition <= $sr_total && $sr_total < $t_condition){
            $msg = '销售额还差'.($t_condition - $sr_total).'即可获得奖励';
            $reward = $t_reward_1 * 100;
        }


        $cond = [0, $f_condition, $s_condition, $t_condition];
        $rate = $sr_total / $t_condition * 100;
        $sr_total = '¥ '.$sr_total;
        $arr['msg'] = $msg;
        $arr['rate'] = $rate;
        $arr['cond'] = $cond;
        $arr['reward'] = $reward;
        return ['code' => 1, 'data' => $arr];
    }

    /*
     * 自己购物详情
     */
    public function myTotal($uid, $p, $month){
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_id,s_comm_time')->find();
        if(!$store_info['s_id']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }

        $num = 20;
        $s = ($p - 1) * $num;

        $my_total = $this->getStoreSaleRoom($uid);

        $month = $this->dealMonth($month);
        $s_time = strtotime($month);
        $e_time = strtotime($month." +1 month -1seconds");
        /*  if($store_info['s_comm_time'] > $s_time){
                $s_time = $store_info['s_comm_time'];
            } */
        $where = "a.commi_add_time>=$s_time and a.commi_add_time<$e_time and a.commi_uid=$uid and uid_role>1 and is_settle<2";
        $list_total = Db::name('commission')->alias('a')->join('__ORDER__ b', 'a.commi_order_id=b.order_id')->where($where)->count();
        // $list = Db::name('commission')->alias('a')->join('__ORDER__ b', 'a.commi_order_id=b.order_id')->field('a.commi_order_price,b.order_id,b.order_create_time,b.order_pay_price,b.order_status')->where($where)->limit($s, $num)->order('a.commi_add_time')->select();
        $sql_total = "SELECT count(order_id) as total from ht_order where order_id in (SELECT b.order_id from ht_commission as a inner join ht_order as b on a.commi_order_id=b.order_id where a.commi_add_time>=$s_time and a.commi_add_time<$e_time and a.commi_uid=$uid and a.uid_role>1 and a.is_settle<2 UNION select b.order_id from ht_gift_log as a inner join ht_order as b on a.log_order_id=b.order_id where (a.log_uid=$uid and a.log_type=2) or (a.log_p_uid=$uid and a.log_type=1))";

        $list_total = Db::name()->query($sql_total)[0]['total'];
        $sql = "SELECT a.commi_order_price,b.order_id,b.order_create_time,b.order_pay_price,b.order_status from ht_commission as a inner join ht_order as b on a.commi_order_id=b.order_id where a.commi_add_time>=$s_time and a.commi_add_time<$e_time and a.commi_uid=$uid and a.uid_role>1 and a.is_settle<2 UNION select a.log_order_price as commi_order_price,b.order_id,b.order_create_time,b.order_pay_price,b.order_status from ht_gift_log as a inner join ht_order as b on a.log_order_id=b.order_id where (a.log_uid=$uid and (a.log_type=2 or a.log_type=0) and a.log_add_time>=$s_time and a.log_add_time <$e_time) or (a.log_p_uid=$uid and a.log_type=1 and a.log_add_time>=$s_time and a.log_add_time <$e_time)  order by order_create_time desc limit ".$s.','.$num;
        $list = Db::name()->query($sql);

        $total = 0.00;
        $status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货','卖家收货','卖家退款');
        foreach($list as &$v){
            if($v['order_create_time']){
                $v['order_create_time'] = date('Y-m-d H:i', $v['order_create_time']);
            }
            $goods = Db::name('order_goods')->alias('a')->join('__GOODS__ b', 'a.og_goods_id=b.goods_id')->where(['a.og_order_id' => $v['order_id']])->field('a.og_goods_id,a.og_goods_name,a.og_goods_price,a.og_goods_thumb,a.og_goods_num,a.og_acti_id,a.og_goods_spec_id as sku_id,a.og_goods_spec_val as spec_val,b.prom_id')->select();

            $order_sh_goods = Db::name('sh_info')->alias('a')->join('__GOODS__ b','a.og_goods_id=b.goods_id')->where('og_order_id', $v['order_id'])->field('a.og_id,a.og_goods_id,a.og_goods_name,a.og_goods_price,a.og_goods_thumb,a.og_goods_num,a.og_acti_id,a.after_state_status,a.og_order_status,a.og_goods_spec_id as sku_id,a.og_goods_spec_val as spec_val,b.prom_id')->select();

           if ($order_sh_goods) {
               $order_goods = Db::name('order_goods')->alias('a')->join('__GOODS__ b', 'a.og_goods_id=b.goods_id')->where(['a.og_order_id' => $v['order_id']])->field('a.og_id,a.og_goods_id,a.og_goods_name,a.og_goods_price,a.og_goods_thumb,a.og_goods_num,a.og_acti_id,a.after_state_status,a.og_order_status,a.og_goods_spec_id as sku_id,a.og_goods_spec_val as spec_val,b.prom_id')->select();
               foreach ($order_goods as $key => $value) {
                   foreach ($order_sh_goods as $k => $val) {
                       if ($val['og_id'] == $value['og_id']) {
                           $order_goods[$key] = $order_sh_goods[$k];
                       }
                   }
               }
           } else {
               $order_goods = Db::name('order_goods')->alias('a')->join('__GOODS__ b', 'a.og_goods_id=b.goods_id')->where(['a.og_order_id' => $v['order_id']])->field('a.og_goods_id,a.og_goods_name,a.og_goods_price,a.og_goods_thumb,a.og_goods_num,a.og_acti_id,a.after_state_status,a.og_order_status,a.og_goods_spec_id as sku_id,a.og_goods_spec_val as spec_val,b.prom_id')->select();
           }

            $v['goods'] = $order_goods;
            $total += $v['commi_order_price'];
            $v['order_status'] = $status_arr[$v['order_status']];
        }

        return ['code' => 1, 'data' => ['list' => $list, 'sale_total' => $total, 'my_total' => $my_total['my_total'],'list_total' => $list_total, 'month' => $month]];
    }

    /**
     * 处理月份
     */
    public function dealMonth($month)
    {
        if(!$month){
            if(date('d', time()) >=26){
                $month = strtotime(date('Y-m-26', time()));
            }
            //当月26号之前，从上月26号统计
            else{
                $month = date('Y-m-26',strtotime("-1 month"));
            }
        }else{
            $month = date('Y-m-26',strtotime($month." -1 month"));
        }

        return $month;
    }
    /*
     * 子店铺
     */
    public function childStore($uid, $p, $month){
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_id,s_comm_time')->find();
        if(!$store_info['s_id']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }
        $num = 20;
        $s = ($p - 1) * $num;

        $month = $this->dealMonth($month);
        $s_time = strtotime($month);
        $e_time = strtotime($month." +1 month -1seconds");
        if($store_info['s_comm_time'] > $s_time){
            $s_time = $store_info['s_comm_time'];
        }
        $child_seller_total = $this->getStoreSaleRooms($uid,$s_time,$e_time);
        //获取我的直属下级店主
        $myChildSellerIds = Db::name('users_tree')->alias('a')->join('__USERS__ b','a.t_uid=b.user_id')->where(['a.t_p_uid'=>$uid,'b.is_seller'=>1])->column('a.t_uid');
        $gift_total = $this->getChildGiftBags($uid,$s_time,$e_time);
        $list = [];
        $grade = ['会员店','高级店','旗舰店'];
        if($myChildSellerIds){
            $myChildSellerStores = Db::name('store')->where(['s_uid'=>['in',$myChildSellerIds]])->field('s_uid,s_thumb,s_name,s_grade,s_comm_time')->select();
            foreach ($myChildSellerStores as $k=>$one){
                $res = $this->getStoreSaleRooms($one['s_uid'],$s_time,$e_time);
                if($res['total']!=0){
                    $one['s_comm_time'] = date('Y-m-d',$one['s_comm_time']);
                    $one['s_grade'] = $grade[$one['s_grade']+1];
                    $list[$k]['store'] = $one;
                    $list[$k]['sale_total'] = $res['total'];
                    $goift_sale = $this->getChildGiftBags($one['s_uid'],$s_time,$e_time);
                    $list[$k]['goift_sale'] = sprintf('%0.2f', $goift_sale ?: 0.00);
                }
            }
        }

        return ['code' => 1, 'data' => ['list' =>$list,'seller_num' => count($list),'seller_total' =>$child_seller_total['child_seller_total'],'gift_total' => sprintf('%0.2f', $gift_total ?: 0.00),'s_month' => date('Y-m-d', strtotime($month)), 'e_month' => date('Y-m-d', strtotime("$month +1 month -1 day"))]];
        // return ['code' =>1, 'data' => $child_seller_total];
        // $total = $child_seller_total['child_seller_total'];
        // $where = "AND b.s_comm_time>=$s_time AND b.s_comm_time<$e_time";
        // 下级店主
        /*$where = "AND a.commi_add_time>=$s_time AND a.commi_add_time<$e_time AND a.is_settle<2";
        $child_seller = Db::name('commission')->query("SELECT a.commi_uid as child_uid FROM ht_commission  as a LEFT JOIN ht_store as b on a.commi_uid=b.s_uid WHERE a.commi_p_uid=$uid AND a.p_uid_role>1 AND a.uid_role>1 $where UNION SELECT a.commi_uid as child_uid FROM ht_commission as a LEFT JOIN ht_store as b on a.commi_uid=b.s_uid WHERE a.commi_g_uid=$uid and a.g_uid_role>1 AND a.uid_role>1 $where UNION SELECT a.commi_p_uid as child_uid FROM ht_commission as a LEFT JOIN ht_store as b on a.commi_p_uid=b.s_uid WHERE a.commi_g_uid=$uid and a.g_uid_role>1 AND a.p_uid_role>1 $where UNION select a.log_uid as child_uid from ht_gift_log as a left join ht_store as b on a.log_uid=b.s_uid where a.log_uid in (SELECT a.t_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_uid=b.s_uid where a.t_p_uid=$uid UNION select a.t_p_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_p_uid=b.s_uid where a.t_g_uid=$uid UNION select a.t_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_uid=b.s_uid where a.t_g_uid=$uid) and a.log_type!=1 AND a.log_add_time>=$s_time AND a.log_add_time<$e_time");*/

        /*$tmp_arr = [];
        $i = 0;
        $total = 0.00;
        $gift_total = 0.00;
        $map = [
            'log_add_time'=>['between',array($s_time, $e_time)]
        ];
        foreach($child_seller as $v){
            $store = Db::name('store')->where('s_uid', $v['child_uid'])->field('s_name,s_thumb,s_grade,s_comm_time')->find();
            $store['s_grade'] = ($store['s_grade'] == 1 ? '会员店铺' : ($store['s_grade'] == 2 ? '高级店铺' : '旗舰店铺'));
            $store['s_comm_time'] = $store['s_comm_time'] ? date('Y-m-d', $store['s_comm_time']) : '';
            $tmp_arr[$i]['store'] = $store;
            $data = $this->getStoreSaleRooms($v['child_uid'],$s_time,$e_time);
            $goift_sale =  $this->getgoiftSale($v['child_uid'],$map);
            $goift_sale = $goift_sale?$goift_sale:0;
            $tmp_arr[$i]['sale_total'] = $data['total'];
            $tmp_arr[$i]['goift_sale'] = $goift_sale;
            $total += $data['total'];
            $gift_total += $goift_sale;
            $i++;
        }*/
        //子店铺页面 展示 总的大礼包金额 20181221
//        $gift_total = $this->getChildGiftBags($uid,$s_time,$e_time);
//        $gift_total = $this->childStoreGift($uid, $p, $month);

//        $child_seller_total['total'] = $child_seller_total['total'] - $gift_total;
       /* return ['code' => 1, 'data' => ['list' => array_slice($tmp_arr, $s, $num), 'seller_num' => count($child_seller), 'seller_total' => $total, 'gift_total' => $gift_total?:0.00, 'total' => $child_seller_total['child_seller_total'], 's_month' => date('Y-m-d', strtotime($month)), 'e_month' => date('Y-m-d', strtotime("$month +1 month -1 day"))]];*/

    }

    /*
     * 子店铺 大礼包
     */
    public function childStoreGift($uid, $p, $month){
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_id,s_comm_time')->find();
        if(!$store_info['s_id']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }

        $num = 20;
        $s = ($p - 1) * $num;

        $month = $this->dealMonth($month);
        $s_time = strtotime($month);
        $e_time = strtotime($month." +1 month -1seconds");

        $child_seller_total = $this->getStoreSaleRoom($uid);
        // return ['code' =>1, 'data' => $child_seller_total];
        // $total = $child_seller_total['child_seller_total'];
        // $where = "AND b.s_comm_time>=$s_time AND b.s_comm_time<$e_time";
        // 下级店主
        $where = "AND a.commi_add_time>=$s_time AND a.commi_add_time<$e_time AND a.is_settle<2";
        $child_seller = Db::name('commission')->query("SELECT a.commi_uid as child_uid FROM ht_commission  as a LEFT JOIN ht_store as b on a.commi_uid=b.s_uid WHERE a.commi_p_uid=$uid AND a.p_uid_role>1 AND a.uid_role>1 $where UNION SELECT a.commi_uid as child_uid FROM ht_commission as a LEFT JOIN ht_store as b on a.commi_uid=b.s_uid WHERE a.commi_g_uid=$uid and a.g_uid_role>1 AND a.uid_role>1 $where UNION SELECT a.commi_p_uid as child_uid FROM ht_commission as a LEFT JOIN ht_store as b on a.commi_p_uid=b.s_uid WHERE a.commi_g_uid=$uid and a.g_uid_role>1 AND a.p_uid_role>1 $where UNION select a.log_uid as child_uid from ht_gift_log as a left join ht_store as b on a.log_uid=b.s_uid where a.log_uid in (SELECT a.t_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_uid=b.s_uid where a.t_p_uid=$uid UNION select a.t_p_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_p_uid=b.s_uid where a.t_g_uid=$uid UNION select a.t_uid as uid from ht_users_tree as a inner join ht_store as b on a.t_uid=b.s_uid where a.t_g_uid=$uid) and a.log_type!=1 AND a.log_add_time>=$s_time AND a.log_add_time<$e_time");
//		 $child_seller = Db::name()->query("SELECT distinct(user_id) as child_uid from ht_users where user_id in(SELECT t_uid as uid from ht_users_tree where t_p_uid=$uid or t_g_uid=$uid UNION SELECT t_p_uid as uid from ht_users_tree where t_g_uid=$uid) and is_seller=1");

        //我的大礼包 和 我的2级 下级
//        $sql = "SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile,b.is_seller FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid UNION SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile,b.is_seller FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_g_uid=$uid ORDER BY add_time desc";
//        $tree_info = Db::name('users_tree')->query($sql);
//        $child_seller = [];
//        foreach($tree_info as $v){
//            if($v['is_seller']==1){
//                $child_seller[]['child_uid'] = $v['user_id'];
//            }
//        }
        $tmp_arr = [];
        $i = 0;
        $total = 0.00;
        $gift_total = 0.00;
        $map = [
            'log_add_time'=>['between',array($s_time, $e_time)]
        ];
        foreach($child_seller as $v){
            $store = Db::name('store')->where('s_uid', $v['child_uid'])->field('s_name,s_thumb,s_grade,s_comm_time')->find();
            $store['s_grade'] = ($store['s_grade'] == 1 ? '会员店铺' : ($store['s_grade'] == 2 ? '高级店铺' : '旗舰店铺'));
            $store['s_comm_time'] = $store['s_comm_time'] ? date('Y-m-d', $store['s_comm_time']) : '';
            $tmp_arr[$i]['store'] = $store;
            $data = $this->getStoreSaleRoom($v['child_uid']);
            $goift_sale =  $this->getgoiftSale($v['child_uid'],$map);
            $goift_sale = $goift_sale?$goift_sale:0;
            $tmp_arr[$i]['sale_total'] = $data['total'];
            $tmp_arr[$i]['goift_sale'] = $goift_sale;
            $total += $data['total'];
            $gift_total += $goift_sale;
            $i++;
        }
        return $gift_total;
    }
    public function getgoiftSale($uid,$map=''){
        $gift_log = Db::name('gift_log')
            ->where('(log_uid='.$uid .' and log_type =0) or (log_uid='.$uid .' and log_type =2) or (log_p_uid='.$uid .' and log_type =1)')
            ->where($map)
            ->sum('log_order_price');
        return $gift_log;
    }
    /*
     * 子VIP
     */
    public function childVip($uid, $p, $month){
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_id,s_comm_time')->find();
        if(!$store_info['s_id']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }
        $num = 20;
        $s = ($p - 1) * $num;

        $month = $this->dealMonth($month);
        $s_time = strtotime($month);
        $e_time = strtotime($month." +1 month -1seconds");
        if($store_info['s_comm_time'] > $s_time){
            $s_time = $store_info['s_comm_time'];
        }
        $child_seller_total = $this->getStoreSaleRooms($uid,$s_time,$e_time);
        $map = ' and a.commi_add_time >='.$s_time.' and a.commi_add_time<'.$e_time;
//        $where = '(a.commi_p_uid='.$uid.' and a.p_uid_role>1 and a.uid_role=1 '.$map.' and is_settle<2) or (a.commi_g_uid='.$uid.' and a.g_uid_role>1 and a.uid_role=1'.$map.' and is_settle<2)';
        $where = '((a.commi_p_uid='.$uid.' and a.p_uid_role>1) or (a.commi_g_uid='.$uid.' and a.g_uid_role>1 )) and a.uid_role=1'.$map;
        $child_vip = Db::name('commission')->alias('a')->join('__ORDER__ b', 'a.commi_order_id=b.order_id')->field('a.commi_id,a.commi_uid,b.order_create_time')->where($where)->order('commi_add_time desc')->select();
        $vip_arr = [];
        // $vip_arr[0] = $child_vip[0]['commi_uid'];
        $i = 0;
        $vip_total = [];
        $list_total = 0.00;
        foreach($child_vip as $k=> $v){
            if(!$k || !in_array($v['commi_id'], $vip_arr)){
                $vip_arr[$i] = $v['commi_id'];
                $i++;
            }

            $order_price = Db::name('commission')->alias('a')->join('__ORDER__ b', 'a.commi_order_id=b.order_id')->where(['a.commi_id'=>$v['commi_id']])->field('a.commi_order_price,b.order_create_time')->find();
            $list_total += $order_price['commi_order_price'];
            $vip_total[$i]['vip_uid'] = $v['commi_uid'];
            $vip_total[$i]['order_price'] = $order_price['commi_order_price'];
            $vip_total[$i]['order_create_time'] = date('Y-m-d', $order_price['order_create_time']);
        }

        foreach($vip_total as &$v){
            $user_info = Db::name('users')->where('user_id', $v['vip_uid'])->field('user_name,user_avat,user_reg_time')->find();
            $v['user_name'] = $user_info['user_name'];
            $v['user_avat'] = $user_info['user_avat'];
            $v['reg_time'] = $user_info['user_reg_time'] ? date('Y-m-d', $user_info['user_reg_time']) : '';
        }

        $arr['vip_num'] = count($vip_total);
        $arr['child_vip_total'] = $child_seller_total['child_vip_total'];
        $arr['s_month'] = date('Y-m-d', strtotime($month));
        $arr['e_month'] = date('Y-m-d', strtotime("$month +1 month -1 day"));
        $arr['list_total'] = $list_total;
        $arr['list'] = array_slice($vip_total, $s, $num);

        return ['code' => 1, 'data' => $arr];
    }

    /*
     * 销售利润
     */
    public function saleProfit($uid, $p, $month){
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_id,s_comm_time')->find();
        if(!$store_info['s_id']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }

        $num = 20;
        $s = ($p - 1) * $num;

        $month = $this->dealMonth($month);
        $s_time = strtotime($month);
        $e_time = strtotime("$month +1 month -1 day");

        if($store_info['s_comm_time'] > $s_time){
            $s_time = $store_info['s_comm_time'];
        }
        $sale_total = $this->getSaleProfit($uid);;
        $map = "a.commi_add_time>=$s_time and a.commi_add_time<$e_time and a.goods_profit>0";
        $where_1 = "a.commi_uid=$uid and a.uid_role>1 and $map";
        $where_2 = "a.commi_p_uid=$uid and a.uid_role=1 and $map";
        $list = Db::name('commission')->query("SELECT a.commi_uid as list_uid,a.commi_order_price,a.commi_price as price,a.goods_profit,b.order_create_time from ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id where $where_1 UNION SELECT a.commi_uid as list_uid,a.commi_order_price,a.commi_p_price as price,a.goods_profit,b.order_create_time from ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id where $where_2 order by order_create_time desc");
        $list_total = 0.00;
        foreach($list as &$v){
            $user_info = Db::name('users')->where(['user_id'=>$v['list_uid']])->field('is_seller,user_name,user_avat')->find();
            if($user_info['is_seller']==1){
                $store_info = Db::name('store')->where(['s_uid'=>$v['list_uid']])->field('s_id,s_grade')->find();
                $v['grade'] = ($store_info['s_grade'] == 1 ? '会员店主' : ($store_info['s_grade'] == 2 ? '高级店主' : '旗舰店主'));
            }else{
                $v['grade'] = 'VIP';
            }

            $v['user_name'] = $user_info['user_name'];
            if($uid == $v['list_uid']){
                $v['user_name'] = '我自己';
            }
            $v['user_avat'] = $user_info['user_avat'];
            $v['order_create_time'] = $v['order_create_time'] ? date('Y-m-d', $v['order_create_time']) : '';
            $list_total += ($v['goods_profit'] + $v['price']);
        }
        $arr['num'] = count($list);
        $arr['sale_total'] = $sale_total;
        $arr['s_month'] = date('Y-m-d', strtotime($month));
        $arr['e_month'] = date('Y-m-d', strtotime("$month +1 month -1 day"));
        $arr['list_total'] = $list_total;
        $arr['list'] = array_slice($list, $s, $num);

        return ['code' => 1, 'data' => $arr];
    }

    /*
     * 团队奖励
     */
    public function teamReward($uid, $p, $month){
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_id,s_comm_time')->find();
        if(!$store_info['s_id']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }

        $num = 20;
        $s = ($p - 1) * $num;

        $month = $this->dealMonth($month);
        $s_time = strtotime($month);
        $e_time = strtotime("$month +1 month -1 day");
        if($store_info['s_comm_time'] > $s_time){
            $s_time = $store_info['s_comm_time'];
        }
        $map = "a.commi_add_time>=$s_time and a.commi_add_time<$e_time";
        $sale_total = $this->getStoreTotal($uid);
        // 子店铺
        $child_seller = Db::name('commission')->query("SELECT a.commi_uid as child_uid FROM ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id WHERE a.commi_p_uid=$uid AND a.p_uid_role>1 AND a.uid_role>1 AND $map UNION SELECT a.commi_p_uid as child_uid FROM ht_commission as a left join ht_order as b on a.commi_order_id=b.order_id WHERE a.commi_g_uid=$uid and a.g_uid_role>1 AND a.p_uid_role>1 AND $map");
        // $arr = [];
        $total = 0.00;
        foreach($child_seller as &$v){
            $store = Db::name('store')->where(['s_uid' => $v['child_uid']])->field('s_id,s_name,s_thumb,s_grade')->find();
            // print_r($store);
            if(!$store){
                return ['code' => 0, 'msg' => '店铺信息不存在'];
            }
            else{
                $v['grade'] = ($store['s_grade'] == 1 ? '会员店主' : ($store['s_grade'] == 2 ? '高级店主' : '旗舰店主'));
            }
            $v['s_name'] = $store['s_name'];
            $v['s_thumb'] = $store['s_thumb'];
            $v['time'] = $v['order_create_time'];
            $v['order_create_time'] = $v['order_create_time'] ? date('Y-m-d', $v['order_create_time']) : '';
            $v['rate_total'] = $this->getSaleProfit($v['child_uid']);
            $v['profit'] = $v['rate_total'] * (30 / 100);
            $total += $v['profit'];
        }
        // 下下级店铺
        $g_child_seller = Db::name('commission')->query("SELECT commi_uid as child_uid FROM ht_commission WHERE commi_g_uid=$uid and g_uid_role>1 AND uid_role>1");
        if($g_child_seller){
            foreach($g_child_seller as &$v){
                $store = Db::name('store')->where(['s_uid' => $v['child_uid']])->field('s_id,s_name,s_thumb,s_grade')->find();
                if(!$store){
                    return ['code' => 0, 'msg' => '店铺信息不存在'];
                }
                else{
                    $v['grade'] = ($store['s_grade'] == 1 ? '会员店主' : ($store['s_grade'] == 2 ? '高级店主' : '旗舰店主'));
                }
                $v['s_name'] = $store['s_name'];
                $v['s_thumb'] = $store['s_thumb'];
                $v['time'] = $v['order_create_time'];
                $v['order_create_time'] = $v['order_create_time'] ? date('Y-m-d', $v['order_create_time']) : '';
                $v['rate_total'] = $this->getSaleProfit($v['child_uid']);
                $v['profit'] = $v['rate_total'] * (20 / 100);
                $total += $v['profit'];
            }
        }

        $tmp_arr = array_merge($child_seller, $g_child_seller);
        $key = [];
        foreach($tmp_arr as &$v){
            $key[] = $v['time'];
            unset($v['time']);
        }

        array_multisort($key, SORT_DESC, SORT_NUMERIC, $tmp_arr);

        $arr['num'] = count($tmp_arr);
        $arr['sale_total'] = $sale_total['team_total'];
        $arr['s_month'] = date('Y-m-d', strtotime($month));
        $arr['e_month'] = date('Y-m-d', strtotime("$month +1 month -1 day"));
        $arr['list_total'] = $total;
        $arr['list'] = array_slice($tmp_arr, $s, $num);
        return ['code' => 1, 'data' => $arr];
    }

    /*
     * 业绩奖励
     */
    public function perforReward($uid, $p, $month){
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_id,s_comm_time,s_grade')->find();
        if(!$store_info['s_id']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }
        if($store_info['s_grade'] == 3){
            $num = 20;
            $s = ($p - 1) * $num;
            $month = $this->dealMonth($month);
            $s_time = strtotime($month);
            $e_time = strtotime("$month +1 month -1 day");
            if($store_info['s_comm_time'] > $s_time){
                $s_time = $store_info['s_comm_time'];
            }
            $map = "reward_time>=$s_time and reward_time<$e_time";
            $where = "reward_uid=$uid and $map";
            // $list = Db::name('account_log')->field('acco_num,acco_desc,acco_time')->where($where)->order('a_log_id desc')->limit($s, $num)->select();
            $list = Db::name('reward')->where($where)->order('reward_id desc')->limit($s, $num)->select();
            $total = 0.00;
            foreach($list as &$v){
                $v['reward_time'] = $v['reward_time'] ? date('Y-m-d H:i', $v['reward_time']) : '';
                $v['status'] = $v['reward_stat'] == 0 ? '待寄发票' : '已发放';
                unset($v['reward_stat']);
                $v['type'] = '业绩奖励';
                $total += $v['reward_num'];
            }
            $sale_total = $this->getStoreTotal($uid);
            $arr['num'] = Db::name('reward')->where($where)->count();
            $arr['sale_total'] = $sale_total['perfor_total'];
            $arr['s_month'] = date('Y-m-d', strtotime($month));
            $arr['e_month'] = date('Y-m-d', strtotime("$month +1 month -1 day"));
            $arr['list_total'] = $total;
            $arr['list'] = $list;
            return ['code' => 1, 'data' => $arr];
        }
        else{
            $img = Db::name('adsense')->where(['title' => '测试1'])->field('image')->find();
            return ['code' => 2, 'data' => $img];
        }

    }

    /*
     * 店铺设置
     */
    public function storeSetting($uid){
        $store_info = $this->getStoreInfo($uid);
        if(!$store_info['is_seller']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }
        return ['code' => 1, 'data' => $store_info];
    }

    /*
     * 设置信息保存
     */
    public function settingSave($uid, $data){
        $store_info = $this->getStoreInfo($uid);
        if(!$store_info['is_seller']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }
        if($data['name'] && $data['name'] != $store_info['s_name']){
            $check = $this->model->where('s_name', $data['name'])->field('s_id')->find();
            if($check){
                return ['code' => 0, 'msg' => '店铺名已存在'];
            }
        }
        if(!$data['s_logo']){
            unset($data['s_logo']);
        }
        if(!$data['s_thumb']){
            unset($data['s_thumb']);
        }
        $res = $this->model->where('s_id', $store_info['s_id'])->update($data);
        if($res === false){
            return ['code' => 0, 'msg' => '保存失败'];
        }
        return ['code' => 1];
    }

    /*
     * 店铺销售额明细
     */
    public function saleRoom($uid, $p, $month){
        // $order_model = new OrderModel();
        $user_service = new UserService();
        $store = $this->getStoreInfo($uid);
        $num = 20;
        $s = ($p - 1) * $num;
        if(!$store['is_seller']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }

        $time = time();
        if($month){
            $time = strtotime($month);
        }
        $m_start = date('Y-m', $time);				//月的第一天
        $m_end = date('Y-m-d', strtotime("$m_start +1 month -1 day")); //月的最后一天

        $arr = [];
        $arr['saleroom'] = 0.00;
        $arr['month'] = $m_start;

        $w_start = strtotime($m_start);
        if($store['s_comm_time'] > strtotime($m_start)){
            $w_start = $store['s_comm_time'];
        }
        //店主购买商品
        // $where_1 = [
        // 	'commi_uid' => $uid,
        // 	'uid_role' => ['gt', 1],
        // 	'commi_add_time' => [
        // 		['egt', $w_start],
        // 		['lt', strtotime($m_end)],
        // 	],
        // ];
        $where_1 = "(commi_uid=$uid and uid_role>1) or (uid_role=1 and (commi_p_uid=$uid or commi_g_uid=$uid)) and (commi_add_time>=$w_start and commi_add_time < ".strtotime($m_end).")";
        $list = Db::name('commission')->where($where_1)->field('commi_order_id,commi_order_price as order_all_price,commi_add_time as order_create_time')->select();
        // return ['code' => 1, 'data' => Db::name('commission')->getlastSql()];

        $spec_list = [];
        if($list){
            foreach($list as $v){
                $order_info = Db::name('order')->alias('a')->join('__USERS__ b', 'a.order_uid=b.user_id')->join('__STORE__ c', 'b.user_id=c.s_uid')->field('a.order_create_time,b.user_id,b.user_name,b.user_avat,b.is_seller,c.s_name,c.s_grade')->where('a.order_id', $v['commi_order_id'])->find();
                $order_goods = Db::name('order_goods')->where('og_order_id', $v['commi_order_id'])->field('og_goods_name')->find();
                $v['user_type'] = '普通VIP';
                $v['order_all_price'] = sprintf('%0.2f', $v['order_all_price']);
                $v['order_create_time'] = date('Y-m-d', $v['order_create_time']);
                $v['user_id'] = $order_info['user_id'] ?: '';
                $v['user_name'] = $order_info['user_name'] ?: '';
                $v['user_avat'] = $order_info['user_avat'] ?: '';
                if($order_info['is_seller']){
                    $v['user_type'] = $order_info['s_grade'] == 1 ? '会员店主' : ($order_info['s_grade'] == 2 ? '高级店主' : '旗舰店主');
                }
                $v['store_name'] = $order_info['s_name'];
                $v['goods_name'] = $order_goods['og_goods_name'];
                $arr['saleroom'] += $v['order_all_price'];
                $spec_list[] = $v;
            }
        }

        $arr['saleroom'] = sprintf('%0.2f', $arr['saleroom']);
        $arr['total'] = count($spec_list);
        $arr['list'] = array_slice($spec_list, $s, $num);
        return ['code' => 1, 'data' => $arr];
    }

    /*
     * 店主的邀请vip页面
     */
    public function inviteCode($uid){
        $store_info = $this->model->where('s_uid', $uid)->field('s_id')->find();
        if(!$store_info){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }

        //邀请获得元宝
        $yinzi_invite = 7;
        //被邀请获得元宝
        $yinzi_invited = 3;
        //成功邀请人数
        $invite_user_num = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_type' => 1])->count();
        //获得元宝
        $invite_yinzi_num = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_type' => 1])->sum('yin_amount');
        $user_info = Db::name('users')->where('user_id', $uid)->field('s_invite_code as invite_code')->find();
        $data = [
            'invite_code' => $user_info['invite_code'],
            'yz_invite' => $yinzi_invite,
            'yz_invited' => $yinzi_invited,
            'user_num' => $invite_user_num ?: 0,
            'yz_num' => $invite_yinzi_num ?: 0,
        ];
        return ['code' => 1, 'data' => $data];
    }

    /*
     * 邀请开店
     */
    public function openStoreGift(){
        $gift_info = Db::name('goods')->where('goods_name', '开店大礼包')->field('goods_id,images,picture,price,stock,goods_name')->order('weigh desc')->find();
        if($gift_info){
            if($gift_info['images']){
                $gift_info['images'] = explode(',', $gift_info['images']);
            }
            return ['code' => 1, 'data' => $gift_info];
        }
        return ['code' => 0, 'msg' => '未找到商品'];
    }

    /*
     * 业绩管理
     */
    public function perforManage($uid, $p){
        $order_model = new OrderModel();
        $store_info = $this->getStoreInfo($uid);
        if(!$store_info['is_seller']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }

        $arr = [];
        $arr['all_income'] = 0.00;
        $arr['all_gift'] = 0.00;
        //业绩总收入
        $total = $this->getGoodsSalerooms($uid);
        $arr['all_income'] = $this->srFormat($total['goods']);
        $arr['all_gift'] = $this->srFormat($total['gift']);
        //下级总数
        // $tree_info = $this->getTreeChild($uid, 2);
        $num = 20;
        $p = $p ?: 1;
        $s = ($p - 1) * $num;

        $tree_info = Db::name('user_tree')->query("SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.is_seller,b.user_mobile FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid UNION SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.is_seller,b.user_mobile FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_g_uid=$uid ORDER BY add_time desc");
        $tree_total = count($tree_info);
        // print_r($tree_info);
        // $tree_info = array_slice($tree, $s, $num);
        // return ['code' => 1, 'data' => $tree_info];
        if($tree_info){
            $i = 0;
            foreach($tree_info as $v){
                $tmp = [];
                $type = 'VIP会员';
                //会员信息
                $user_info = $this->getStoreInfo($v['t_uid']);
                if($user_info){
                    $type = $user_info['s_grade'] == 1 ? '会员店主' : ($user_info['s_grade'] == 2 ? '高级店主' : '旗舰店主');
                }
                //下级购买总额
                $sr_result = $this->getGoodsSaleroom($v['t_uid']);
                $sr_goods = $this->srFormat($sr_result['goods']);
                $sr_gift = $this->srFormat($sr_result['gift']);

                $tmp['user_id'] = $v['user_id'];
                $tmp['user_name'] = $v['user_name'];
                $tmp['user_avat'] = $v['user_avat'];
                $tmp['mobile'] = $v['user_mobile'];
                $tmp['user_type'] = $type;
                $tmp['add_time'] = $v['add_time'] ? date('Y-m-d', $v['add_time']) : '';
                $tmp['goods'] = $sr_goods ?: 0.00;
                $tmp['gift'] = $sr_gift ?: 0.00;
                $arr['list'][] = $tmp;
            }
        }

        return ['code' => 1, 'data' => ['list' => array_slice($arr, $s, $num), 'total' => $tree_total]];
    }

    /*
     * 业绩明细
     */
    public function perforInfo($uid, $p, $month){
        $num = 20;
        $s = ($p - 1) * $num;

        $time = time();
        if($month){
            $time = strtotime($month);
        }
        $m_start = date('Y-m', $time);
        $m_end = strtotime(date('Y-m-d', strtotime("$m_start +1 month -1 day")));
        $where = "commi_add_time>=".strtotime($m_start)." and commi_add_time<=$m_end";
        $where_bag = "bag_buy_time>=".strtotime($m_start)." and bag_buy_time<=$m_end";
        $arr = [];
        $key = [];
        $arr['list'] = [];
        $arr['all_total'] = 0.00;
        $arr['all_gift'] = 0.00;
        $arr['month_total'] = 0.00;
        $arr['month_gift'] = 0.00;
        $arr['month'] = $m_start;

        $store_info = $this->model->where('s_uid', $uid)->field('s_id')->find();
        if($store_info){
            //店铺销售总额和下级购买总额
            $sr_result = $this->getGoodsSaleroom($uid);
            $arr['all_total'] = $this->srFormat($sr_result['goods']);
            $arr['all_gift'] = $this->srFormat($sr_result['gift']);

            // $sql_1 = "("."SELECT a.commi_order_price,a.commi_uid as list_uid,a.commi_p_price as commi_price,a.commi_add_time as add_time,b.og_goods_name from ht_commission as a inner join ht_order_goods as b on a.commi_order_id=b.og_order_id where $where and a.commi_p_uid=$uid limit 1) UNION (SELECT a.commi_order_price,a.commi_uid as list_uid,a.commi_g_price as commi_price,a.commi_add_time as add_time,b.og_goods_name from ht_commission as a inner join ht_order_goods as b on a.commi_order_id=b.og_order_id where $where and a.commi_g_uid=$uid limit 1) limit $s,$num";
            $sql_1 = "SELECT commi_uid as list_uid,commi_order_id,commi_order_price,commi_add_time as add_time,commi_price from ht_commission where commi_uid=$uid and uid_role>1 and $where union select commi_p_uid as list_uid,commi_order_id,commi_order_price,commi_add_time as add_time,commi_p_price as commi_price from ht_commission where commi_p_uid=$uid and $where union select commi_g_uid as list_uid,commi_order_id,commi_order_price,commi_add_time as add_time,commi_g_price as commi_price from ht_commission where commi_g_uid=$uid and $where";
            $perfor_info = Db::name('commission')->query($sql_1);
            // print_r(Db::name('commission')->getlastSql());
            if($perfor_info){
                foreach($perfor_info as $v){
                    $goods_info = Db::name('order_goods')->where('og_order_id', $v['commi_order_id'])->field('og_goods_name')->find();
                    $v['og_goods_name'] = $goods_info['og_goods_name'];
                    $arr['list'][] = $v;
                    $arr['month_total'] += $v['commi_order_price'];
                }
            }

            $sql_2 = "SELECT bag_goods_id,bag_gift_price as commi_order_price,bag_uid as list_uid,bag_buy_time as add_time FROM ht_store_gift_bag where bag_invite_uid=$uid and bag_buy_stat=1 and $where_bag";
            $gift_info_1 = Db::name('store_gift_bag')->query($sql_2);
            if($gift_info_1){
                foreach($gift_info_1 as &$v){
                    $v['og_goods_name'] = '升级大礼包';
                    $v['commi_price'] = '';
                    $arr['list'][] = $v;
                    $arr['month_total'] += $v['commi_order_price'];
                }
            }

            $sql_3 = "SELECT bag_goods_id,bag_gift_price as commi_order_price,bag_buy_time as add_time FROM ht_store_gift_bag where bag_uid=$uid and bag_buy_stat=1 and $where_bag";
            $gift_info_2 = Db::name('store_gift_bag')->query($sql_3);

            if($gift_info_2){
                foreach($gift_info_2 as &$v){
                    $v['og_goods_name'] = '升级大礼包';
                    $v['commi_price'] = '';
                    $v['list_uid'] = $uid;
                    $arr['list'][] = $v;
                    $arr['month_total'] += $v['commi_order_price'];
                }
            }

            if($arr['list']){
                foreach($arr['list'] as &$v){
                    $seller = Db::name('store')->where('s_uid', $v['list_uid'])->field('s_name,s_grade,s_logo')->find();
                    if($seller){
                        $v['user_type'] = $seller['s_grade'] == 1 ? '会员店主' : ($seller['s_grade'] == 2 ? '高级店主' : '旗舰店主');
                    }
                    else{
                        $v['user_type'] = '普通会员';
                    }
                    $user_info = Db::name('users')->where('user_id', $v['list_uid'])->field('user_name,user_avat')->find();
                    $key[] = $v['add_time'];
                    $v['add_time'] = $v['add_time'] ? date('Y-m-d', $v['add_time']) : '';
                    $v['user_name'] = $user_info['user_name'];
                    $v['user_avat'] = $user_info['user_avat'];
                }
                array_multisort($key, SORT_DESC, SORT_NUMERIC, $arr);
            }
        }

        return ['code' => 1, 'data' => array_slice($arr, $s, $num)];
    }

    /*
     * VIP管理
     */
    public function vipManage($uid, $p,$mobile=''){
        $store_info = $this->getStoreInfo($uid);
        if(!$store_info['is_seller']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }
//		$sql = "SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid UNION SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_g_uid=$uid ORDER BY add_time desc";
//		$sql = "SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid and b.is_seller=0 UNION SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.user_mobile FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_g_uid=$uid and b.is_seller=0 ORDER BY add_time desc";
//		$tree_info = Db::name('users_tree')->query($sql);
//		$tree_info = array_unique(($tree_info));
//		foreach($tree_info as &$v){
//			$v['add_time'] = date('Y-m-d', $v['add_time']);
//		}
        $start = 10*($p-1);
        $total = Db::query("SELECT a.tree_id FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid and b.is_seller=0");
        $total = count($total);
        if(!empty($mobile) && strlen($mobile)==11){
            $sql = "SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.wx,b.user_mobile,b.is_seller FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid and b.is_seller=0 and b.user_mobile=".$mobile."  ORDER BY add_time desc limit ".$start.",10";
        }else{
            $sql = "SELECT a.t_uid,a.t_addtime as add_time,b.user_id,b.user_name,b.user_avat,b.wx,b.user_mobile,b.is_seller FROM ht_users_tree as a inner join ht_users as b on a.t_uid=b.user_id WHERE a.t_p_uid=$uid and b.is_seller=0  ORDER BY add_time desc limit ".$start.",10";
        }
        //echo $sql;die;
        $tree_info = Db::query($sql);
        $temp = array();
        foreach ($tree_info as $key => $value) {
           $value['add_time'] = date('Y-m-d H:i:s',$value['add_time']); 
            $temp[$key] = $value;
        }
        //var_dump($tree_info);die;
        
        // foreach($tree_info as $v){
        //     if($v['is_seller']==1){
        //         continue;
        //     }else{
        //         $v['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
        //         $temp[$v['user_id']] = $v;
        //     }
        // }
        $tree_info = $temp;
        $arr['total'] =  $total;
        $arr['list'] = $tree_info;
        return ['code' => 1, 'data' => $arr];
    }

    /*
     * 店铺管理
     */
    public function storeManage($uid, $p){
        $num = 10;
        $s = ($p - 1) * $num;
        $arr = [];
        $store_info = $this->getStoreInfo($uid);
        if(!$store_info['is_seller']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }
        // $store_goods = Db::name('store_goods')->alias('a')->join('__GOODS__ b', 'a.')->where('s_g_storeid', $store_info['s_id'])->field('')
        $goods_list = $this->getStoreGoods($store_info['s_id'], 'store', $s='', $num='');
        //  if($goods_list){

        // }
        $arr['store'] = $store_info;
        $arr['goods_total'] = count($this->getStoreGoods($store_info['s_id'], $type='store', $s='', $num=''));
        $arr['list'] = $goods_list;
        return ['code' => 1, 'data' => $arr];
    }

    /*
     * 店铺商品管理
     */
    public function goodsManage($uid, $goods_id, $type){
        $store_info = $this->getStoreInfo($uid);
        if(!$store_info['is_seller']){
            return ['code' => 0, 'msg' => '店铺信息不存在'];
        }
        //加入我的店铺
        if($type == 1){
            $insert = [
                's_g_storeid' => $store_info['s_id'],
                's_g_userid' => $uid,
                's_g_goodsid' => $goods_id,
                's_g_addtime' => time()
            ];
            $res = Db::name('store_goods')->insert($insert);
        }
        //移出我的店铺
        else if($type == 2){
            $res = Db::name('store_goods')->where('s_g_goodsid', $goods_id)->delete();
        }
        if($res === false){
            return ['code' => 0, 'msg' => '操作失败'];
        }
        return ['code' => 1];
    }

    /*
     * 分享大礼包
     */
    public function getGiftBag($uid, $goods_id){
        $gift_info = Db::name('goods')->where('goods_id', $goods_id)->field('goods_id,price')->find();
        if(!$gift_info){
            return ['code' => 0, 'msg' => '未找到大礼包信息'];
        }
        $insert = [
            'bag_goods_id' => $gift_info['goods_id'],
            'bag_gift_price' => $gift_info['price'],
            'bag_invite_uid' => $uid,
            'bag_invite_time' => time(),
        ];
        Db::name('store_gift_bag')->insert($insert);
        $bag_id = Db::name('store_gift_bag')->getLastInsId();
        if(!$bag_id){
            return ['code' => 0, 'msg' => '获取失败'];
        }
        return ['code' => 1, 'data' => ['bag_id' => $bag_id, 'goods_id' => $gift_info['goods_id']]];
    }

    /*
     * 领取大礼包
     */
    public function shareGiftbag($uid, $bag_id){
        $bag_info = Db::name('store_gift_bag')->where('bag_id', $bag_id)->field('bag_id')->find();
        if(!$bag_info){
            return ['code' => 0, 'msg' => '未找到信息'];
        }

        $res = Db::name('store_gift_bag')->where('bag_id', $bag_id)->update(['bag_uid' => $uid]);
        if($res === false){
            return ['code' => 0, 'msg' => '领取失败'];
        }
        return ['code' => 1, 'data' => ''];
    }

    /*
     * 获取店铺基本信息
     */
    public function getStoreInfo($uid){
        $store_info = $this->model->alias('a')->join('__USERS__ b', 'a.s_uid=b.user_id', 'INNER')->Distinct(true)->field('a.s_id,a.s_uid,a.s_name,a.s_intro,a.s_logo,a.s_thumb,a.s_grade,a.s_comm_time,a.s_better_time,a.s_best_time,b.user_sign,b.is_seller')->where('a.s_uid', $uid)->find();
        return $store_info;
    }

    /**
     * 获取下级uid
     * @param type 销售额类型：1，店铺；2，市场
     **/
    public function getTreeChild($uid, $type){
        $user_service = new UserService();
        $tree_uid = [];

        //下级vip
        if($type == 1){
            $tree_c = Db::name('users_tree')->alias('a')->join('__USERS__ b', 'a.t_uid=b.user_id')->field('a.t_uid')->where(['a.t_p_uid|a.t_g_uid' => $uid, 'b.is_seller' => 0])->order('a.t_addtime desc')->select();
        }
        //所有下级（包括店主）
        else{
            $tree_c = Db::name('users_tree')->field('t_uid')->where(['t_p_uid|t_g_uid' => $uid])->order('t_addtime desc')->select();
        }

        if($tree_c){
            foreach($tree_c as $v){
                $tree_uid[] = $v['t_uid'];
            }
        }

        return $tree_uid;
    }

    /*
     * 获取店铺商品
     */
    private function getStoreGoods($store_id, $type, $start, $num){
        $goods_list = Db::name('store_goods')->alias('a')->join('__GOODS__ b', 'a.s_g_goodsid=b.goods_id', 'INNER')->where(['a.s_g_storeid'=>$store_id,'b.status'=>0])->field('b.goods_id,b.goods_name,b.images,b.stock,b.goods_banner,b.sum_sales,b.volume,b.show_price,b.price,b.vip_price,b.prom_type,b.prom_id')->order('s_g_addtime desc')->limit($start, $num)->select();
        $time_now = time();
        foreach($goods_list as &$v){
            // 卖出比率
             if($type == 'vip'){
                $v['stock_rate'] = sprintf('%0.2f', ($v['stock'] / ($v['stock'] + $v['volume']) * 100));
            }
            // 店主赚
            if($type == 'store'){
                $v['profit'] = $v['price'] - $v['vip_price'];
            }
            if($v['prom_type']==5){
                $whereFlash = [
                    'a.goods_id'=>$v['goods_id'],
                ];
                /*if($v['images']){
                    $v['images'] = explode(',', $v['images']);
                    $v['main_image'] = $v['images'][0];
                }*/
                //秒杀商品
                $flash_time = Db::name('flash_goods')->alias('a')->join('__FLASH_ACTIVE__ b','b.id=a.flash_id')->where($whereFlash)->field('b.start_time,b.end_time,status')->find();
                if(empty($flash_time) || $flash_time['status']==1){
                    //秒杀商品不存在
                    unset($v);
                }else{
                    if($time_now>=$flash_time['end_time'] || ($time_now<$flash_time['end_time'] && $time_now>=$flash_time['start_time'])){
                        $v['flash_status'] = 0;//已开始
                    }else{
                        $v['flash_status'] = 2;//即将开始
                    }
                }
            }
            if($v['images']){
                $v['images'] = explode(',', $v['images']);
                $v['main_image'] = $v['images'][0];
            }
            
           
            $v['price'] = floatval($v['price']);
            $v['show_price'] = floatval($v['show_price']);
            $v['vip_price'] = floatval($v['vip_price']);
            $v['stock_rate'] = floatval($v['stock_rate']);
        }
        return $goods_list;
    }

    /*
     * 店铺是否可升级
     */
    private function isUpgrade($uid, $store_id, $type){
        $store_info = Db::name('store')->where('s_id', $store_id)->field('s_comm_time')->find();
        // 升级高级店铺
        if($type == 1){
            $store_cond = 5;		//分享店铺个数
            $saleroom = 8.5 * 10000;		//总销售额
            $saleroom_normal = 6 * 10000;		//普通商品额度
            $saleroom_gift = 2.5 * 10000;		//大礼包额度
            $recent_saleroom = 5500;		//最近三个月累计销售额
            $lowest_consume = 1500;			//每月最低消费
        }
        // 升级旗舰店铺
        else{
            $store_cond = 5;
            $saleroom = 85 * 10000;		//总销售额
            $saleroom_normal = 60 * 10000;		//普通商品额度
            $saleroom_gift = 25 * 10000;		//大礼包额度
            $recent_saleroom = 5500;		//最近三个月累计销售额
            $lowest_consume = 1500;			//每月最低消费
        }
        // 子店铺
        if($store_cond){
            // $tree_info = Db::name('users_tree')->query("SELECT a.t_uid FROM ht_users_tree as a inner join ht_users as b on a.t_p_uid=b.user_id WHERE a.t_p_uid=$uid and b.is_seller=1 UNION SELECT a.t_uid FROM ht_users_tree as a inner join ht_users as b on a.t_g_uid=b.user_id WHERE a.t_g_uid=$uid and b.is_seller=1");
            if($type == 1){
                $tree_info = Db::name('users_tree')->query("SELECT a.t_uid FROM ht_users_tree as a inner join ht_users as b on a.t_p_uid=b.user_id WHERE a.t_p_uid=$uid and b.is_seller=1");
            }
            else{
                $tree_info = Db::name('users_tree')->query("SELECT a.t_uid FROM ht_users_tree as a inner join ht_store as b on a.t_p_uid=b.s_uid WHERE a.t_p_uid=$uid and b.s_grade>=2");
            }

        }

        // 子店铺销售额
        if((isset($tree_info) && count($tree_info) >= $store_cond) || !isset($tree_info)){
            //下属店铺累计销售额
            $store_total = 0.00;		//店铺销售额
            $goods_total = 0.00;		//下属店铺销售额

            //下级及下级销售额
            $where_1 = [
                'commi_p_uid' => $uid,
                'commi_add_time' => ['egt', $store_info['s_comm_time']],
            ];
            $where_2 = [
                'commi_g_uid' => $uid,
                'commi_add_time' => ['egt', $store_info['s_comm_time']],
            ];

            $list_1 = Db::name('commission')->where($where_1)->field('commi_uid,commi_order_price')->select();
            $list_2 = Db::name('commission')->where($where_2)->field('commi_uid,commi_order_price')->select();
            $list_3 = Db::name('commission')->where($where_2)->field('commi_p_uid,commi_order_price')->select();
            $tmp_arr = [];
            foreach($list_1 as $v){
                $goods_total += $v['commi_order_price'];
                $tmp_arr[] = $v['commi_uid'];
            }
            foreach($list_2 as $v){
                $goods_total += $v['commi_order_price'];
                if(!in_array($v['commi_uid'], $tmp_arr)){
                    $tmp_arr[] = $v['commi_uid'];
                }
            }
            foreach($list_3 as $v){
                if(!in_array($v['commi_p_uid'], $tmp_arr)){
                    $tmp_arr[] = $v['commi_p_uid'];
                }
            }

            //大礼包销售额
            $my_child = $this->getTreeChild($uid, 2);
            if($my_child){
                // $gift_total = Db::name('store_gift_bag')->where(['bag_uid' => ['in', implode(',', $my_child)], 'bag_buy_stat' => 1])->sum('bag_gift_price');
                $gift_total = Db::name('store_bag_log')->alias('a')->join('__ORDER_GOODS__ b', 'a.log_order_id=b.og_order_id')->where(['a.log_uid' => ['in', implode(',', $my_child)], 'a.log_bag_stat' => 1])->sum('b.order_commi_price');

            }

            // 下级vip消费
            if(($goods_total + $gift_total) >= $saleroom_normal && $gift_total >= $saleroom_gift){
                // 店铺前3月销售额（含vip）
                // 下级vip前3月销售额（含vip）
                $start = date('Y-m-d', $store_info['s_comm_time']);
                $end = date('Y-m-d', strtotime("$start +3 month -1 day"));
                $store_total_1 = 0.00;
                // $store_where_1 = [
                // 	'commi_uid' => $uid,
                // 	'uid_role' => 2,
                // 	'commi_add_time' => [
                // 		['egt', strtotime($start)],
                // 		['lt', strtotime($end)],
                // 	],
                // ];
                // if($type == 2){
                // 	$store_where_1['uid_role'] = 3;
                // }
                // $store_total_1 = Db::name('commission')->where($store_where_1)->sum('commi_order_price');
                //vip消费
                $store_where_2 = [
                    'commi_p_uid|commi_g_uid' => $uid,
                    'uid_role' => 1,
                    'commi_add_time' => [
                        ['egt', strtotime($start)],
                        ['lt', strtotime($end)],
                    ],
                ];
                $store_total_2 = Db::name('commission')->where($store_where_2)->sum('commi_order_price');
                $store_total = $store_total_1 + $store_total_2;

                //店主消费（每月最低1500）
                if($store_total >= $recent_saleroom){
                    $current_time = date('Y-m-d');
                    $current_month_e = date('Y-m-d', strtotime("$current_time +1 month -1 day"));
                    $start_time = date('Y-m-d', $store_info['s_comm_time']);
                    for($i = $start_time; $i <= $current_month_e; $i = date('Y-m-d', strtotime("$i +1 month"))){
                        $month_toal = 0.00;
                        // 当月开始
                        $month_start = date('Y-m', strtotime($i));
                        // 当月结束
                        $month_end = date('Y-m-d', strtotime("$month_start +1 month -1 day"));
                        if($month_start < $start_time){
                            $month_start = $start_time;
                        }
                        if($month_end > $current_time){
                            $month_end = $current_time;
                        }

                        // 普通商品
                        $where_1 = [
                            'commi_uid' => $uid,
                            'uid_role' => ['gt', 1],
                            'commi_add_time' => [
                                ['egt', strtotime($month_start)],
                                ['elt', strtotime($month_end)],
                            ],
                        ];
                        $month_total = Db::name('commission')->where($where_1)->sum('commi_order_price');

                        // 大礼包
                        $where_2 = [
                            'a.log_uid' => $uid,
                            'a.log_buy_time' => [
                                ['egt', strtotime($month_start)],
                                ['elt', strtotime($month_end)],
                            ],
                        ];
                        $gift_total = Db::name('store_bag_log')->alias('a')->join('__ORDER__ b', 'a.log_order_id=b.order_id')->where($where_2)->sum('b.order_commi_price');
                        $month_total += $gift_total;
                        if($month_total >= $lowest_consume){
                            return true;
                        }
                        else return false;
                    }
                }
                else return false;
            }
            else return false;
        }
        else return false;
    }

    /*
     * 升级店铺
     */
    private function storeUpgrade($store_info, $type){
        // 会员店->高级
        if($type == 1){
            $reward_1 = 3000;		//三个月奖金
            $reward_2 = 2500;		//六个月奖金
            $reward_3 = 2000;		//九个月奖金
            $reward_4 = 1500;		//十二个月奖金
            Db::startTrans();
            try{
                $update = [
                    's_grade' => 2,
                    // 's_commission' => $store_info['s_commission'] + 20,
                    's_better_time' => time(),
                ];
                $res = Db::name('store')->where('s_id', $store_info['s_id'])->update($update);
                $open_time = date('Y-m-d H:i:s', $store_info['s_comm_time']);
                if($res && strtotime("$open_time +3 month") >= time()){
                    $reward = $reward_1;
                }
                else if($res && strtotime("$open_time +3 month") <= time() && strtotime("$open_time +6 month") >= time()){
                    $reward = $reward_2;
                }
                else if($res && strtotime("$open_time +6 month") <= time() && strtotime("$open_time +9 month") >= time()){
                    $reward = $reward_3;
                }
                else if($res && strtotime("$open_time +9 month") <= time() && strtotime("$open_time +12 month") >= time()){
                    $reward = $reward_4;
                }
                else{
                    Db::commit();
                    return true;
                }
                Db::name('users')->where('user_id', $store_info['s_uid'])->setInc('user_account', $reward);
                $log = [
                    'a_uid' => $store_info['s_uid'],
                    'acco_num' => $reward,
                    'acco_type' => 8,
                    'acco_desc' => '店铺升级平台奖励',
                    'acco_time' => time()
                ];
                Db::name('account_log')->insert($log);
                Db::commit();
                return true;
            }
            catch(\Exception $e){
                Db::rollback();
                return false;
            }
        }
        // 高级->旗舰
        else{
            $reward_1 = 15000;		//6个月奖金
            $reward_2 = 14000;		//9个月奖金
            $reward_3 = 13000;		//12个月奖金
            $reward_4 = 12000;		//15个月奖金
            $reward_5 = 11000;		//18个月奖金
            $reward_6 = 10000;		//21个月奖金
            $reward_7 = 9000;		//24个月奖金
            Db::startTrans();
            try{
                $update = [
                    's_grade' => 3,
                    's_best_time' => time(),
                ];
                $res = Db::name('store')->where('s_id', $store_info['s_id'])->update($update);
                $open_time = date('Y-m-d H:i:s', $store_info['s_comm_time']);
                if($res && strtotime("$open_time +6 month") >= time()){
                    $reward = $reward_1;
                }
                else if($res && strtotime("$open_time +6 month") <= time() && strtotime("$open_time +9 month") >= time()){
                    $reward = $reward_2;
                }
                else if($res && strtotime("$open_time +9 month") <= time() && strtotime("$open_time +12 month") >= time()){
                    $reward = $reward_3;
                }
                else if($res && strtotime("$open_time +12 month") <= time() && strtotime("$open_time +15 month") >= time()){
                    $reward = $reward_4;
                }
                else if($res && strtotime("$open_time +15 month") <= time() && strtotime("$open_time +18 month") >= time()){
                    $reward = $reward_5;
                }
                else if($res && strtotime("$open_time +18 month") <= time() && strtotime("$open_time +21 month") >= time()){
                    $reward = $reward_6;
                }
                else if($res && strtotime("$open_time +21 month") <= time() && strtotime("$open_time +24 month") >= time()){
                    $reward = $reward_7;
                }
                else{
                    Db::commit();
                    return true;
                }
                Db::name('users')->where('user_id', $store_info['s_uid'])->setInc('user_account', $reward);
                $log = [
                    'a_uid' => $store_info['s_uid'],
                    'acco_num' => $reward,
                    'acco_type' => 8,
                    'acco_desc' => '店铺升级平台奖励',
                    'acco_time' => time()
                ];
                Db::name('account_log')->insert($log);
                Db::commit();
                return true;
            }
            catch(\Exception $e){
                Db::rollback();
                return false;
            }
        }
    }

    /**
     * 店铺销售额（新）
     * @param type 1为每月统计
     */
    public function getStoreSaleRoom($uid, $type = 0){
        $total = 0.00;					// 总销售额
        $my_total = 0.00;		  		// 自己购物
        $child_seller_total = 0.00;		// 子店铺
        $child_vip_total = 0.00;		// 子vip

        $my_total = $this->myShopping($uid, $type);

        $child_vip_total = $this->vipShopping($uid, $type);

        // 子店铺
        // $where_2 = "(commi_p_uid=$uid and p_uid_role>1) or (commi_g_uid=$uid and g_uid_role>1)";
        // $child_seller = Db::name('commission')->query("SELECT commi_uid as child_uid FROM ht_commission WHERE commi_p_uid=$uid AND p_uid_role>1 AND uid_role>1 UNION SELECT commi_uid as child_uid FROM ht_commission WHERE commi_g_uid=$uid and g_uid_role>1 AND uid_role>1 UNION SELECT commi_p_uid as child_uid FROM ht_commission WHERE commi_g_uid=$uid and g_uid_role>1 AND p_uid_role>1");

        $sql_uid = $uid;
        $uid_arr = [];
        do{
            $list = Db::name('users_tree')->alias('a')->join('__USERS__ b', 'a.t_uid=b.user_id')->field('a.t_uid')->where(['a.t_p_uid' => $sql_uid, 'b.is_seller' => 1])->select();
            if($list){
                foreach($list as $k => $v){
                    foreach($v as $val){
                        if(!in_array($val, $uid_arr)){
                            $uid_arr[] = $val;
                        }
                    }
                    $sql_uid = $list[$k]['t_uid'];
                }
                $stat = true;
            }
            else{
                $stat = false;
            }
        }while($stat);
        if($uid_arr = array_unique($uid_arr)){
            foreach($uid_arr as &$v){
                $result = $this->getStoreSaleRoom($v);
                $child_seller_total += $result['total'];
            }
        }
        $store=Db::name('store')->where(['s_uid'=>$uid])->find();
        if($store['s_grade']==1){
           $data = [
            'my_total' => sprintf('%0.2f', $my_total ?: 0.00),
            //'child_seller_total' => sprintf('%0.2f', $child_seller_total ?: 0.00),
            'child_vip_total' => sprintf('%0.2f', $child_vip_total ?: 0.00),
        ]; 
        }else{
          $data = [
            'my_total' => sprintf('%0.2f', $my_total ?: 0.00),
            'child_seller_total' => sprintf('%0.2f', $child_seller_total ?: 0.00),
            'child_vip_total' => sprintf('%0.2f', $child_vip_total ?: 0.00),
        ];   
        }
        
        $data['total'] = sprintf('%0.2f', array_sum($data));
        return $data;
    }

    /*
     * 自己购物
     */
    public function myShopping($uid, $type = 0){
        $my_total = 0.00;		  		// 自己购物
        $gift_total = 0.00;				// 大礼包金额
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_comm_time')->find();
        $where = [
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];
        // 按月统计
        $map = '';
        if($type == 1){
            //当月26号之后，从这月26号统计
            if(date('d', time()) >=26){
                $s_time = strtotime(date('Y-m-26', time()));
                $e_time = time();
            }
            //当月26号之前，从上月26号统计
            else{
                $current_month = date('Y-m-26', time());
                $s_time = strtotime("$current_month -1 month");
                $e_time = strtotime($current_month);
            }

            $where['commi_add_time'] = [
                ['egt', $s_time],
                ['lt', $e_time],
            ];

            if($store_info['s_comm_time'] > $s_time){
                $where['commi_add_time'] = [
                    ['egt', $store_info['s_comm_time']],
                    ['lt', $e_time],
                ];
            }
            $map .= ' and log_add_time>='.$s_time.' and log_add_time<='.$e_time;
        }
        // 自己购物
        $where_1 = $where;
        $where_1['commi_uid'] = $uid;
        $where_1['uid_role'] = ['gt', 1];
        $where_1['is_settle'] = ['<', 2];

        // 大礼包 （自己购物或赠送店铺）
        $where_2 = '(log_uid='.$uid .' and log_type =0) or (log_uid='.$uid .' and log_type =2) or (log_p_uid='.$uid .' and log_type =1)'.$map;
        // $gift_total = Db::name('store_bag_log')->where($where_2)->alias('a')->join('__ORDER__ b', 'a.log_order_id=b.order_id')->sum('b.order_commi_price');
        $gift_total = Db::name('gift_log')->where($where_2)->sum('log_order_price');
        $my_total = Db::name('commission')->where($where_1)->sum('commi_order_price');
        return $my_total + ($gift_total ? : 0);
    }

    /*
     * VIP购物
     */
    public function vipShopping($uid, $type = 0){
        $child_vip_total = 0.00;		// 子vip
        $gift_total = 0.00;				// 大礼包
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_comm_time')->find();
        $map = ' and commi_add_time>='.$store_info['s_comm_time'];
        // $map_gift = 'log_type!=1';
        // 按月统计
        if($type == 1){
            //当月26号之后，从这月26号统计
            if(date('d', time()) >=26){
                $s_time = strtotime(date('Y-m-26', time()));
                $e_time = time();
            }
            //当月26号之前，从上月26号统计
            else{
                $current_month = date('Y-m-26', time());
                $s_time = strtotime("$current_month -1 month");
                $e_time = strtotime($current_month);
            }

            if($store_info['s_comm_time'] > $s_time){
                $s_time = $store_info['s_comm_time'];
            }
            $map = ' and commi_add_time>='.$s_time.' and commi_add_time<'.$e_time;
            // $map_gift .= ' and log_add_time >='.$s_time.' and log_add_time<'.$e_time;
        }

        $where = '(commi_p_uid='.$uid.' and p_uid_role>1 and uid_role=1 and is_settle<2) or (commi_g_uid='.$uid.' and g_uid_role>1 and uid_role=1 and is_settle<2)'.$map;
        $child_vip_total = Db::name('commission')->where($where)->sum('commi_order_price');
        // $map_gift .= ' and log_p_uid='.$uid;
        // $list = Db::name('gift_log')->where($map_gift)->select();
        // $gift_total = Db::name('gift_log')->where($map_gift)->sum('log_order_price');
        return $child_vip_total?:0;
    }

    /**
     * 获取店铺销售额
     * @param uid
     */
    private function getStoreSaleRoom2($uid, $type = 0){
        $store_info = $this->getStoreInfo($uid);
        //店铺销售额(店主本人购买)
        $goods_total = 0.00;
        //下级购买商品(只限于vip)
        $user_total = 0.00;

        //查询条件
        $where_1 = [
            'commi_p_uid|commi_g_uid' => $uid,
            'uid_role' => 1,
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];

        //每月统计
        if($type == 1){
            //当月26号之后，从这月26号统计
            if(date('d', time()) >=26){
                $s_time = strtotime(date('Y-m-26', time()));
                $e_time = time();
            }
            //当月26号之前，从上月26号统计
            else{
                $current_month = date('Y-m-26', time());
                $s_time = strtotime("$current_month -1 month");
                $e_time = strtotime($current_month);
            }

            $where_1['commi_add_time'] = [
                ['egt', $s_time],
                ['lt', $e_time],
            ];

            if($store_info['s_comm_time'] > $s_time){
                $where_1['commi_add_time'] = [
                    ['egt', $store_info['s_comm_time']],
                    ['lt', $e_time],
                ];
            }
        }
        $user_total = Db::name('commission')->where($where_1)->sum('commi_order_price');

        $where_2 = [
            'commi_uid' => $uid,
            'uid_role' => ['gt', 1],
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];
        $goods_total = Db::name('commission')->where($where_2)->sum('commi_order_price');

        return sprintf('%0.2f', ($goods_total + $user_total));
    }

    /*
     * 店铺总收入
     */
    public function getStoreTotal($uid){
        /* 销售利润 */
        $sale_total = 0.00;
        $my_toal = 0.00;		// 自己购物
        $vip_total = 0.00; 		// vip购物

        $sale_total = $this->getSaleProfit($uid);
        /* 团队奖励 */
        $team_total = 0.00;
        // 子店铺
        $child_seller = Db::name('commission')->query("SELECT commi_uid as child_uid FROM ht_commission WHERE commi_p_uid=$uid AND p_uid_role>1 AND uid_role>1 AND is_settle<2 UNION SELECT commi_p_uid as child_uid FROM ht_commission WHERE commi_g_uid=$uid and g_uid_role>1 AND p_uid_role>1 AND is_settle<2 ");
        foreach($child_seller as $v){
            $total = $this->getSaleProfit($v['child_uid']);
            $team_total += $total * (30 / 100);
        }
        // 下下级店铺
        $g_child_seller = Db::name('commission')->query("SELECT commi_uid as child_uid FROM ht_commission WHERE commi_g_uid=$uid and g_uid_role>1 AND uid_role>1 AND is_settle<2");
        foreach($g_child_seller as $v){
            $total = $this->getSaleProfit($v['child_uid']);
            $team_total += $total * (20 / 100);
        }

        /* 业绩奖励 */
        $perfor_total = 0.00;
        // $perfor_total = Db::name('account_log')->where(['a_uid' => $uid, 'acco_type' => 9])->sum('acco_num');
//		$perfor_total = Db::name('reward')->where(['reward_uid' => $uid])->sum('reward_num')
        $store= Db::name('store')->where(['s_uid'=>$uid])->find();

       
          $data = [
            'sale_total' => sprintf('%0.2f', $sale_total),
            'team_total' => sprintf('%0.2f', ($team_total ?: 0.00)),
//          'perfor_total' => sprintf('%0.2f', ($perfor_total ?: 0.00)),
            'perfor_total' => $perfor_total,
        ];  
        
        

        $data['total'] = sprintf('%0.2f', array_sum($data));
        return $data;
    }

    /*
     * 销售利润
     */
    public function getSaleProfit($uid){
        $where_1 = [
            'commi_uid' => $uid,
            'uid_role' => ['gt', 1],
            'is_settle' => ['<',2]
        ];
        $my_total = Db::name('commission')->where($where_1)->sum('goods_profit');
        $where_2 = [
            'commi_p_uid' => $uid,
            'uid_role' => 1,
            'p_uid_role' => ['gt', 1],
            'is_settle' => ['<',2]
        ];
        $vip_total = Db::name('commission')->where($where_2)->sum('goods_profit');

        $my_total = $my_total ?: 0.00;
        $vip_total = $vip_total ?: 0.00;
        return sprintf('%0.2f', ($my_total + $vip_total));
        // return ($my_total + $vip_total);
    }

    /*
     * 获取市场销售额
     */
    private function getGoodsSaleroom($uid){
        $store_total = 0.00;		//本店铺销售额
        $goods_total = 0.00;		//普通商品
        $gift_total = 0.00;			//大礼包

        //店铺信息
        $store_info = $this->getStoreInfo($uid);
        //大礼包信息
        // $gift_info = Db::name('goods')->where('goods_name', '开店大礼包')->field('goods_id')->find();
        //本店铺销售额
        $where = [
            'commi_uid' => $uid,
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];
        $store_total = Db::name('commission')->where($where)->sum('commi_order_price');

        //下级及下级销售额
        $where_1 = [
            'commi_p_uid' => $uid,
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];
        $where_2 = [
            'commi_g_uid' => $uid,
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];

        $list_1 = Db::name('commission')->where($where_1)->field('commi_uid,commi_order_price')->select();
        $list_2 = Db::name('commission')->where($where_2)->field('commi_uid,commi_order_price')->select();
        $list_3 = Db::name('commission')->where($where_2)->field('commi_p_uid,commi_order_price')->select();

        $tmp_arr = [];
        foreach($list_1 as $v){
            $goods_total += $v['commi_order_price'];
            $tmp_arr[] = $v['commi_uid'];
        }
        foreach($list_2 as $v){
            $goods_total += $v['commi_order_price'];
            if(!in_array($v['commi_uid'], $tmp_arr)){
                $tmp_arr[] = $v['commi_uid'];
            }
        }
        foreach($list_3 as $v){
            if(!in_array($v['commi_p_uid'], $tmp_arr)){
                $tmp_arr[] = $v['commi_p_uid'];
            }
        }

        // 下级
        $my_child = $this->getTreeChild($uid, 2);
        $my_child[] = $uid;

        //大礼包销售额
        if($my_child){
            $my_child[] = $uid;
            $gift_total = Db::name('store_gift_bag')->where(['bag_uid' => ['in', implode(',', $my_child)], 'bag_buy_stat' => 1])->sum('bag_gift_price');
        }

        return ['goods' => $store_total + $goods_total + $gift_total, 'gift' => $gift_total];
    }/*
	 * 获取市场销售额
	 */
    private function getGoodsSalerooms($uid){
        $store_total = 0.00;		//本店铺销售额
        $goods_total = 0.00;		//普通商品
        $gift_total = 0.00;			//大礼包

        //店铺信息
        $store_info = $this->getStoreInfo($uid);
        //大礼包信息
        // $gift_info = Db::name('goods')->where('goods_name', '开店大礼包')->field('goods_id')->find();
        //本店铺销售额

        $where = [
            'commi_uid' => $uid,
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];
        $store_total = Db::name('commission')->where($where)->sum('commi_order_price');

        //下级及下级销售额
        $where_1 = [
            'commi_p_uid' => $uid,
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];
        $where_2 = [
            'commi_g_uid' => $uid,
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];
        //当月26号之后，从这月26号统计
        if(date('d', time()) >=26){
            $s_time = strtotime(date('Y-m-26', time()));
            $e_time = time();
        }
        //当月26号之前，从上月26号统计
        else{
            $current_month = date('Y-m-26', time());
            $s_time = strtotime("$current_month -1 month");
            $e_time = strtotime($current_month);
        }

        $where['commi_add_time'] = [
            ['egt', $s_time],
            ['lt', $e_time],
        ];

        $list_1 = Db::name('commission')->where($where_1)->field('commi_uid,commi_order_price')->select();
        $list_2 = Db::name('commission')->where($where_2)->field('commi_uid,commi_order_price')->select();
        $list_3 = Db::name('commission')->where($where_2)->field('commi_p_uid,commi_order_price')->select();

        $tmp_arr = [];
        foreach($list_1 as $v){
            $goods_total += $v['commi_order_price'];
            $tmp_arr[] = $v['commi_uid'];
        }
        foreach($list_2 as $v){
            $goods_total += $v['commi_order_price'];
            if(!in_array($v['commi_uid'], $tmp_arr)){
                $tmp_arr[] = $v['commi_uid'];
            }
        }
        foreach($list_3 as $v){
            if(!in_array($v['commi_p_uid'], $tmp_arr)){
                $tmp_arr[] = $v['commi_p_uid'];
            }
        }

        // 下级
        $my_child = $this->getTreeChild($uid, 2);
        $my_child[] = $uid;

        //大礼包销售额
        if($my_child){
            $my_child[] = $uid;
            $gift_total = Db::name('store_gift_bag')->where(['bag_uid' => ['in', implode(',', $my_child)], 'bag_buy_stat' => 1])->sum('bag_gift_price');
        }

        return ['goods' => $store_total + $goods_total + $gift_total, 'gift' => $gift_total];
    }

    /*
     * 生成邀请码
     */
    public function createInviteCode(){
        $invite_code = strtoupper(substr(md5(time()), 0, 6));
        $check = Db::name('users')->where('s_invite_code', $invite_code)->field('user_id')->find();
        while($check){
            $invite_code = $this->createInviteCode();
        }
        return $invite_code;
    }

    /*
     * 获取随机邀请码
     */
    public function getRandCode(){
        /*$seller_arr = Db::name('users')->where(['is_seller' => 1])->field('user_id')->select();
        $tmp = [];
        foreach($seller_arr as $v){
            $tmp[] = $v['user_id'];
        }
        $rand = mt_rand(1, count($tmp)) - 1;*/
        $store_info = Db::name('users')->where(['user_id' => 1, 'is_seller' => 1])->field('s_invite_code')->find();
        while(!$store_info['s_invite_code']){
            $store_info['s_invite_code'] = $this->getRandCode();
        }
        return $store_info['s_invite_code'];
    }

    /**
     * 高级店铺销售额奖励
     * @param total 店铺销售额
     */
    private function betterReward($uid, $total){
        $user_service = new UserService();
        //奖励分级条件
        $f_condition = 1600;
        $f_reward_1 = 20 / 100;		//本店铺+下级vip
        $f_reward_2 = 26 / 100;		//下级店主（及其vip）
        $s_condition = 5000;
        $s_reward_1 = 25 / 100;
        $s_reward_2 = 30 / 100;
        $t_condition = 10000;
        $t_reward_1 = 30 / 100;
        $t_reward_2 = 35 / 100;

        //我的店铺信息
        $store_info = $this->getStoreInfo($uid);

        //统计数据
        $total_1 = 0.00;		//本店铺消费返利
        $total_2 = 0.00;		//下级VIP消费返利
        $total_3 = 0.00;		//下级店主（含vip）赚取利润

        //奖励金额
        $reward_total = 0.00;

        //统计时间
        $current_month = date('Y-m-26', time());
        $s_time = strtotime("$current_month -1 month");
        if($s_time < $store_info['s_better_time']){
            $s_time = $store_info['s_better_time'];
        }
        $where_1 = [
            'commi_uid' => $uid,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];

        $total_1 = Db::name('commission')->where($where_1)->sum('commi_price');

        //下级VIP
        // $tree_vip = $this->getTreeChild($uid, 1);
        $where_2_1 = [
            'uid_role' => 1,
            'commi_p_uid' => $uid,
            'p_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $where_2_2 = [
            'uid_role' => 1,
            'commi_g_uid' => $uid,
            'g_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $total_2_1 = Db::name('commission')->where($where_2_1)->sum('commi_p_price');
        $total_2_2 = Db::name('commission')->where($where_2_2)->sum('commi_g_price');
        $total_2 = $total_2_1 + $total_2_2;

        //下级店主
        $where_3_1 = [
            'uid_role' => 2,  	//不计算脱离店主
            'commi_p_uid' => $uid,
            'p_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $where_3_2 = [
            'uid_role' => 2,  	//不计算脱离店主
            'commi_g_uid' => $uid,
            'g_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $where_3_3 = [
            'p_uid_role' => 2,  	//不计算脱离店主
            'commi_g_uid' => $uid,
            'g_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];

        $total_3_1 = 0.00;
        $store_arr = [];
        $list_1 = Db::name('commission')->where($where_3_1)->field('commi_uid,commi_p_price')->select();
        foreach($list_1 as $v){
            $total_3_1 += $v['commi_p_price'];
            $store_arr[] = $v['commi_uid'];
        }
        $list_2 = Db::name('commission')->where($where_3_2)->field('commi_uid,commi_g_price')->select();
        foreach($list_2 as $v){
            $total_3_1 += $v['commi_g_price'];
            if(!in_array($v['commi_uid'], $store_arr)){
                $store_arr[] = $v['commi_uid'];
            }
        }
        $list_3 = Db::name('commission')->where($where_3_3)->field('commi_p_uid,commi_g_price')->select();
        foreach($list_3 as $v){
            $total_3_1 += $v['commi_g_price'];
            if(!in_array($v['commi_p_uid'], $store_arr)){
                $store_arr[] = $v['commi_p_uid'];
            }
        }
        //下级店主的vip消费（一级为vip）
        $where_3_4 = [
            'uid_role' => 1,
            'commi_g_uid' => ['in', implode(',', $store_arr)],
            'g_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $total_3_2 = Db::name('commission')->where($where_3_4)->sum('commi_g_price');
        $total_3 = $total_3_1 + $total_3_2;
        if($f_condition <= $total && $total < $s_condition){
            $reward_total = ($total_1 + $total_2) * $f_reward_1 + $total_3 * $f_reward_2;
        }
        else if($s_condition <= $total && $total < $t_condition){
            $reward_total = ($total_1 + $total_2) * $s_reward_1 + $total_3 * $s_reward_2;
        }
        else if($t_condition <= $total){
            $reward_total = ($total_1 + $total_2) * $t_reward_1 + $total_3 * $t_reward_2;
        }

        // 增加奖励
        if($reward_total > 0){
            $user_service->changeAccount($uid, 9, $reward_total);
        }
    }

    /**
     * 旗舰店铺销售额奖励
     * @param total 店铺销售额
     */
    private function bestReward($uid, $total){
        $user_service = new UserService();
        //奖励分级条件
        $f_condition = 1600;
        $f_reward_1 = 25 / 100;		//本店铺+下级vip
        $f_reward_2 = 26 / 100;		//下级会员店主（及其vip）
        $f_reward_3 = 21 / 100;		//下级高级店主（及其vip）
        $s_condition = 5000;
        $s_reward_1 = 30 / 100;
        $s_reward_2 = 30 / 100;
        $s_reward_3 = 25 / 100;
        $t_condition = 10000;
        $t_reward_1 = 35 / 100;
        $t_reward_2 = 35 / 100;
        $t_reward_3 = 30 / 100;

        //我的店铺信息
        $store_info = $this->getStoreInfo($uid);

        //统计数据
        $total_1 = 0.00;		//本店铺消费返利
        $total_2 = 0.00;		//下级VIP消费返利
        $total_3 = 0.00;		//下级会员店主（含vip）赚取利润
        $total_4 = 0.00;		//下级高级店主（含vip）赚取利润

        //奖励金额
        $reward_total = 0.00;

        //统计时间
        $current_month = date('Y-m-26', time());
        $s_time = strtotime("$current_month -1 month");
        if($s_time < $store_info['s_best_time']){
            $s_time = $store_info['s_best_time'];
        }
        $where_1 = [
            'commi_uid' => $uid,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];

        $total_1 = Db::name('commission')->where($where_1)->sum('commi_price');

        //下级VIP
        // $tree_vip = $this->getTreeChild($uid, 1);
        $where_2_1 = [
            'uid_role' => 1,
            'commi_p_uid' => $uid,
            'p_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $where_2_2 = [
            'uid_role' => 1,
            'commi_g_uid' => $uid,
            'g_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $total_2_1 = Db::name('commission')->where($where_2_1)->sum('commi_p_price');
        $total_2_2 = Db::name('commission')->where($where_2_2)->sum('commi_g_price');
        $total_2 = $total_2_1 + $total_2_2;

        //下级店主
        $where_3_1 = [
            'uid_role' => 2,  	//不计算脱离店主
            'commi_p_uid' => $uid,
            'p_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $where_3_2 = [
            'uid_role' => 2,  	//不计算脱离店主
            'commi_g_uid' => $uid,
            'g_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $where_3_3 = [
            'p_uid_role' => 2,  	//不计算脱离店主
            'commi_g_uid' => $uid,
            'g_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];

        $total_3_1 = 0.00;
        $store_arr = [];
        $list_1 = Db::name('commission')->where($where_3_1)->field('commi_uid,commi_p_price')->select();
        foreach($list_1 as $v){
            $total_3_1 += $v['commi_p_price'];
            $store_arr[] = $v['commi_uid'];
        }
        $list_2 = Db::name('commission')->where($where_3_2)->field('commi_uid,commi_g_price')->select();
        foreach($list_2 as $v){
            $total_3_1 += $v['commi_g_price'];
            if(!in_array($v['commi_uid'], $store_arr)){
                $store_arr[] = $v['commi_uid'];
            }
        }
        $list_3 = Db::name('commission')->where($where_3_3)->field('commi_p_uid,commi_g_price')->select();
        foreach($list_3 as $v){
            $total_3_1 += $v['commi_g_price'];
            if(!in_array($v['commi_p_uid'], $store_arr)){
                $store_arr[] = $v['commi_p_uid'];
            }
        }
        //下级店主的vip消费（一级为vip）
        $where_3_4 = [
            'uid_role' => 1,
            'commi_g_uid' => ['in', implode(',', $store_arr)],
            'g_uid_role' => 3,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $total_3_2 = Db::name('commission')->where($where_3_4)->sum('commi_g_price');
        $total_3 = $total_3_1 + $total_3_2;

        //下级高级店主
        $where_4_1 = [
            'uid_role' => 3,  	//不计算脱离店主
            'commi_p_uid' => $uid,
            'p_uid_role' => 4,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $where_4_2 = [
            'uid_role' => 3,  	//不计算脱离店主
            'commi_g_uid' => $uid,
            'g_uid_role' => 4,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $where_4_3 = [
            'p_uid_role' => 3,  	//不计算脱离店主
            'commi_g_uid' => $uid,
            'g_uid_role' => 4,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];

        $total_4_1 = 0.00;
        $b_store_arr = [];
        $b_list_1 = Db::name('commission')->where($where_4_1)->field('commi_uid,commi_p_price')->select();
        foreach($b_list_1 as $v){
            $total_4_1 += $v['commi_p_price'];
            $b_store_arr[] = $v['commi_uid'];
        }
        $b_list_2 = Db::name('commission')->where($where_4_2)->field('commi_uid,commi_g_price')->select();
        foreach($b_list_2 as $v){
            $total_4_1 += $v['commi_g_price'];
            if(!in_array($v['commi_uid'], $b_store_arr)){
                $b_store_arr[] = $v['commi_uid'];
            }
        }
        $b_list_3 = Db::name('commission')->where($where_4_3)->field('commi_p_uid,commi_g_price')->select();
        foreach($b_list_3 as $v){
            $total_4_1 += $v['commi_g_price'];
            if(!in_array($v['commi_p_uid'], $b_store_arr)){
                $b_store_arr[] = $v['commi_p_uid'];
            }
        }

        //下级店主的vip消费（一级为vip）
        $where_4_4 = [
            'uid_role' => 1,
            'commi_g_uid' => ['in', implode(',', $b_store_arr)],
            'g_uid_role' => 4,
            'commi_add_time' => [
                ['egt', $s_time],
                ['lt',strtotime($current_month)],
            ],
        ];
        $total_4_2 = Db::name('commission')->where($where_4_4)->sum('commi_g_price');
        $total_4 = $total_4_1 + $total_4_2;

        if($f_condition <= $total && $total < $s_condition){
            $reward_total = ($total_1 + $total_2) * $f_reward_1 + $total_3 * $f_reward_2 + $total_4 * $f_reward_3;
        }
        else if($s_condition <= $total && $total < $t_condition){
            $reward_total = ($total_1 + $total_2) * $s_reward_1 + $total_3 * $s_reward_2 + $total_4 * $s_reward_3;
        }
        else if($s_condition <= $total){
            $reward_total = ($total_1 + $total_2) * $t_reward_1 + $total_3 * $t_reward_2 + $total_4 * $t_reward_3;
        }

        // 增加奖励
        if($reward_total > 0){
            $reward_insert = [
                'reward_uid' => $uid,
                'reward_num' => $reward_total,
                'reward_stat' => 0,
                'reward_time' => time()
            ];
            Db::name('reward')->insert($reward_insert);
            // $user_service->changeAccount($uid, 9, $reward_total);
        }
    }

    /*
     * 销售额格式化输出
     */
    public function srFormat($total){
        if($total > 850000){
            $total = '+ ¥'.sprintf('%0.2f', ($total - 850000));
        }
        else{
            $total = '¥ '. sprintf('%0.2f', $total);
        }
        return $total;
    }

    /*
     *  获取大礼包详情
     */
    public function  getGoodsInfo($goods_id){
        $info = Db::name('goods')->where('goods_id',$goods_id)->field('goods_id,goods_name,price,picture,stock,description')->find();
        return $info;
    }



    /**
     * 店铺销售额（新）
     * @param type 1为每月统计
     */
    public function getStoreSaleRooms($uid,  $s_time='',$e_time=''){
        $total = 0.00;					// 总销售额
        $my_total = 0.00;		  		// 自己购物
        $child_seller_total = 0.00;		// 子店铺
        $child_vip_total = 0.00;		// 子vip

        $my_total = $this->myShoppings($uid, $s_time,$e_time);

        $child_vip_total = $this->vipShoppings($uid, $s_time,$e_time);

        // 子店铺
        // $where_2 = "(commi_p_uid=$uid and p_uid_role>1) or (commi_g_uid=$uid and g_uid_role>1)";
        // $child_seller = Db::name('commission')->query("SELECT commi_uid as child_uid FROM ht_commission WHERE commi_p_uid=$uid AND p_uid_role>1 AND uid_role>1 UNION SELECT commi_uid as child_uid FROM ht_commission WHERE commi_g_uid=$uid and g_uid_role>1 AND uid_role>1 UNION SELECT commi_p_uid as child_uid FROM ht_commission WHERE commi_g_uid=$uid and g_uid_role>1 AND p_uid_role>1");
        $s_grade = Db::name('store')->where('s_uid',$uid)->value('s_grade');
        if($s_grade==3){
            $sql_uid = $uid;
            $uid_arr = [];
            do{
                $list = Db::name('users_tree')->alias('a')->join('__USERS__ b', 'a.t_uid=b.user_id')->field('a.t_uid')->where(['a.t_p_uid' => $sql_uid, 'b.is_seller' => 1])->select();
                if($list){
                    foreach($list as $k => $v){
                        foreach($v as $val){
                            if(!in_array($val, $uid_arr)){
                                $uid_arr[] = $val;
                            }
                        }
                        $sql_uid = $list[$k]['t_uid'];
                    }
                    $stat = true;
                }
                else{
                    $stat = false;
                }
            }while($stat);
            if($uid_arr = array_unique($uid_arr)){
                foreach($uid_arr as &$v){
                    $result = $this->getStoreSaleRooms($v, $s_time,$e_time);
                    $child_seller_total += $result['total'];
                }
            }
        }

        $data = [
            'my_total' => sprintf('%0.2f', $my_total ?: 0.00),
            'child_seller_total' => sprintf('%0.2f', $child_seller_total ?: 0.00),
            'child_vip_total' => sprintf('%0.2f', $child_vip_total ?: 0.00),
        ];
        $data['total'] = sprintf('%0.2f', array_sum($data));
        return $data;
    }

    /*
     * 自己购物
     */
    public function myShoppings($uid, $s_time='',$e_time=''){
        $my_total = 0.00;		  		// 自己购物
        $gift_total = 0.00;				// 大礼包金额
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_comm_time')->find();
        $where = [
            'commi_add_time' => ['egt', $store_info['s_comm_time']],
        ];
        // 按月统计
        $map = '';
        $where['commi_add_time'] = [
            ['egt', $s_time],
            ['lt', $e_time],
        ];
        if($store_info['s_comm_time'] > $s_time){
            $where['commi_add_time'] = [
                ['egt', $store_info['s_comm_time']],
                ['lt', $e_time],
            ];
        }
        if($s_time && $e_time){
            $map .= ' and log_add_time>='.$s_time.' and log_add_time<='.$e_time;
        }else if($s_time){
            $map .= ' and log_add_time>='.$s_time;
        }else if($e_time){
            $map .= ' and log_add_time<='.$e_time;
        }

        // 自己购物
        $where_1 = $where;
        $where_1['commi_uid'] = $uid;
        $where_1['uid_role'] = ['gt', 1];
        // 大礼包 （自己购物或赠送店铺）
        $where_2 = '((log_uid='.$uid .' and log_type =0) or (log_uid='.$uid .' and log_type =2) or (log_p_uid='.$uid .' and log_type =1))'.$map;
        // $gift_total = Db::name('store_bag_log')->where($where_2)->alias('a')->join('__ORDER__ b', 'a.log_order_id=b.order_id')->sum('b.order_commi_price');
        $gift_total = Db::name('gift_log')->where($where_2)->sum('log_order_price');
        $my_total = Db::name('commission')->where($where_1)->sum('commi_order_price');
        return $my_total + ($gift_total ? : 0.00);
    }
    /*
         * VIP购物
         */
    public function vipShoppings($uid, $s_time='',$e_time=''){
        $child_vip_total = 0.00;		// 子vip
        $gift_total = 0.00;				// 大礼包
        $store_info = Db::name('store')->where('s_uid', $uid)->field('s_comm_time')->find();
        $map = ' and commi_add_time>='.$store_info['s_comm_time'];

        if($store_info['s_comm_time'] > $s_time){
            $s_time = $store_info['s_comm_time'];
        }
        if($e_time && $s_time){
            $map = ' and commi_add_time>='.$s_time.' and commi_add_time<'.$e_time;
        }else if($s_time){
            $map = ' and commi_add_time>='.$s_time;
        }else if($e_time){
            $map = ' and commi_add_time<'.$e_time;
        }
        $where = '((commi_p_uid='.$uid.' and p_uid_role>1 and uid_role=1) or (commi_g_uid='.$uid.' and g_uid_role>1 and uid_role=1))'.$map;
        $child_vip_total = Db::name('commission')->where($where)->sum('commi_order_price');
        return $child_vip_total;
    }

    /**
     * 资金管理:今日收入，累计已结算收入累计待结算收入
     * @return \think\response\Json
     */
    public function fundManagement($uid)
    {
        $today = 0.00;
        $total = 0.00;
        $not_settle = 0.00;
        //今日收入
        $start = strtotime(date('Y-m-d',time()));
        $where1 = [
            'commi_p_uid'=>$uid,
            'p_uid_role'=>['>',1],
            'uid_role'=>1
        ];
        $where2 = [
            'commi_uid'=>$uid,
            'uid_role'=>['>',1]
        ];
        $sql = 'select commi_uid,commi_p_uid,commi_add_time,commi_price,commi_p_price,is_settle from ht_commission where (commi_p_uid='.$uid.' and p_uid_role>1 and uid_role=1 and is_settle<2) or (commi_uid='.$uid.' and uid_role>1 and is_settle<2)';
//        $commis = Db::name('commission')->where($where1)->whereOr($where2)->field('commi_add_time,commi_price')->fetchSql(true)->select();
        $commis = Db::query($sql);
        if(!empty($commis)){
            foreach ($commis as $c){
                $temp = $c['commi_price'] + $c['commi_p_price'];

                if($c['is_settle']==0 && $c['commi_add_time']>$start){
                        $today +=$temp;//今日待结算佣金
                        $not_settle +=$temp;//累计待结算收入
                }elseif($c['is_settle']==1){
                    $total +=$temp;//已计算佣金
                }else{
                    $not_settle +=$temp;//累计待结算收入

                }
            }
        }
        $map = [
            'user_id'=>$uid,
            'type'=>1
        ];
        $bag_prices = Db::name('bonus')->where($map)->field('price,add_time')->select();

        if(!empty($bag_prices)){
            foreach ($bag_prices as $b){
                if($b['add_time']>$start){
                    $today +=$b['price'];//今日大礼包收入
                }
                $total +=$b['price'];//累计大礼包收入
            }
        }
        $today = sprintf('%0.2f', $today);
        $total = sprintf('%0.2f', $total);
        $not_settle = sprintf('%0.2f', $not_settle);
        return ['today'=>$today,'total'=>$total,'not_settle'=>$not_settle];
    }

    /**
     * 获取店铺直属大礼包，和所有子级大礼包
     * @return \think\response\Json
     */
    public function getAllGifts($uid)
    {
        $directs = Db::name('gift_log')->alias('a')->join('__USERS__ b ','b.user_id= a.log_uid')->where('a.log_p_uid',$uid)->field('b.user_id,b.user_avat,b.user_mobile,b.user_name,b.user_truename,a.log_add_time')->select();
        if(empty($directs)) return 0;
        $my_total = count($directs);
        $my_child_total = 0;
        foreach ($directs as &$one){
            $uids = $this->childGiftBag($one['user_id']);
            $one['log_add_time'] = date('Y-m-d',$one['log_add_time']);
            if(empty($uids)) continue;
            $where = [
                'log_p_uid'=>$one['user_id'],
                'log_uid'=>['in',$uids]
            ];
            $child_bag = Db::name('gift_log')->where($where)->count('log_id');
            if(!empty($child_bag)){
                $one['count'] = $child_bag;
                $my_child_total += $child_bag;
            }
        }
        return ['my_total'=>$my_total,'my_child_total'=>$my_child_total,'directs'=>$directs];
    }
    /**
     * 获取该店铺下面所有大礼包
     */
    public function childGiftBag($uid)
    {
        $gs = new GoodsService();
        $my_child_ids = $gs->getAllChild($uid);
        if(empty($my_child_ids)) return 0;
        $my_child_ids = implode(',', $my_child_ids);
        return $my_child_ids;
    }

    /*
     * 获得子店铺包含自己的所有大礼包
     */
    public function getChildGiftBags($uid,$s_time,$e_time)
    {
        $myChildSellers = $this->getAllChildSellers($uid);
        if($myChildSellers){
            $myChildSellers[] = $uid;
        }else{
            $myChildSellers = [$uid];
        }
        //获取子店铺所有大礼包（包含自己的）
        $log_order_price = Db::name('gift_log')->where(['log_uid'=>['in',$myChildSellers],'log_add_time' => [['>',$s_time],['<=',$e_time]]])->sum('log_order_price');
        return $log_order_price;
    }


}
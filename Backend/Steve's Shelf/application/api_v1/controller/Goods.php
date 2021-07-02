<?php
namespace app\api\controller;

use app\common\service\Config;
use app\common\service\GoodsCategory as GoodsCategoryService;
use app\common\service\Goods as GoodsService;
use app\common\service\Cart as CartService;
use app\common\service\User as User;
use app\common\service\Order as OrderSerevice;
use app\common\service\Yinzi as YinziService;
use think\Db;
class Goods extends Common{

	/*
	* 商品列表
	*/
	public function index(){

		$map['status']='normal';

		// if($type=='')
		//排序
		$order="weigh desc";
		if(input('get.order')=='volume'){
			$order.=',volume desc';
		}
		if(input('get.order')=='price'){
			$order.=',price asc';
		}

		if(input('get.keyword')){
			$map['goods_name']=['like','%'.input('get.keyword').'%'];
		}

		$GoodsService=new GoodsService();
		$rows=$GoodsService->paginate($map,'*',$order);
		return $this->json($rows);
	}

	/*
	* 商品分类
	*/
	public function category(){
		$category_id=input('get.category_id');
		//获取全部分类
		$GoodsCategoryService=new GoodsCategoryService();
		$rows=$GoodsCategoryService->select([],"*","weigh desc");
		//转换为树形结构
		$rows=\util\Tree::makeTree(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid','primary_index'=>false]);
		if($category_id){
			foreach ($rows as $key => $value) {
				if($value['category_id']==$category_id){
					$rows[$key]['active']=1;
					if($value['son']){
						$rows[$key]['son'][0]['active']=1;
						$category_id=$value['son'][0]['category_id'];
						$son=$rows[$key]['son'];
					}
				}else{
					foreach ($value['son'] as $k => $v) {
						if($v['category_id']==$category_id){
							$rows[$key]['active']=1;
							$rows[$key]['son'][$k]['active']=1;
							$son=$rows[$key]['son'];
						}
					}
				}
			}
		}
		if(!$category_id){
			$rows[0]['active']=1;
			$rows[0]['son'][0]['active']=1;
			$son=$rows[0]['son'];
			$category_id=$rows[0]['son'][0]['category_id'];
		}
		$data['category']=$rows;
		//获取商品列表
		$ids=array_map(function($v){
			return $v['category_id'];
		},$son);
		$GoodsService=new GoodsService();
		$map['status']='normal';
		$map['category_id']=['in',$ids];
		$rows=$GoodsService->select($map,'*','weigh desc');
		$goods=[];
		foreach ($rows as $key => $value) {
			$goods[$value['category_id']][]=$value->toArray();
		}
		foreach ($son as $key => $value) {
			$son[$key]['goods']=$goods[$value['category_id']]?$goods[$value['category_id']]:[];
		}
		$data['category_son']=$son;
		return $this->json($data);
	}

	/*
	* 商品详情
	*/
	public function detail(){
		$GoodsService=new GoodsService();
		$map['goods_id']=input('get.goods_id');
		$info=$GoodsService->find($map);
		if($info['sku']){
			$info['sku']=$info->sku;
			foreach($info['sku'] as $value){
				$sku_data[$value['attr_value']]=$value;
			}
			$info['sku_data']=$sku_data;
		}
		//替换图片信息
		$info['description']=str_replace("/uploads",request()->domain().'/uploads',$info['description']);
		$data['info']=$info;
		return $this->json($data);
	}

    /*
     * 商品详情--商品
     */
    public function goodsDetail(){
    	$user_id = input('uid');
    	$active_id = input('active_id');
        $goods_id = input('goodsid');
        $goods = new GoodsService();
//    	if ($active_id) {
//            $res = $goods->judgeActive($goods_id, $active_id);
//            if ($res == -1) {
//                return $this->json([], 0, '该活动不存在');
//            } elseif ($res == -2) {
//                return $this->json([], 0, '该活动商品库存不足');
//            } elseif ($res == -3) {
//                return $this->json([], 0, '该商品不在秒杀时段');
//            } elseif ($res == -4) {
//                return $this->json([], 0, '该时段没有秒杀活动');
//            }
//        }
//    	if($user_id){
//    		$token = input('request.token');
//    		if(!$token){
//    			return $this->json('', 0, '未知参数');
//    		}
//    		$user_id = $this->getUid($token, $user_id);
//    		if(!$user_id){
//    			return $this->json('', 0, '未知参数');
//    		}
//    	}
        $store_id = input('request.s_id');
    	$info = $goods->goodsDetail($goods_id, $user_id,$active_id, $store_id);
        $active_info = $goods->getActiveInfo($active_id, $goods_id);
        
		$info['active_info'] = '';
		if($active_info){
			 $info['active_info'] = $active_info;
		}
    	if($info == -1){
    		return $this->json('', 0, '商品已下架');
    	}
        
    	return $this->json($info);
    }

	/*
	 * 领取优惠券
	 */
	public function getCoupon(){
		$user_id = input('request.uid');
		if($user_id){
			$token = input('request.token');
			$uid = $this->getUid($token, $user_id);
			if(!$uid){
				return $this->json('', 0, '未知参数');
			}
		}

		$goods_id = input('request.goodsid');
		$goods_service = new GoodsService();
		$list = $goods_service->getCoupon($goods_id);
		return $this->json($list);
	}

    /*
     * 商品详情--详情
     */
    public function goodsInfo(){
    	$goods_id = input('request.goodsid');
    	$goods = new GoodsService();
    	$info = $goods->goodsInfo($goods_id);
    	return $this->json($info);
    }

    /*
     * 商品详情--素材
     */
    public function goodsMaterial(){
    	$uid = input('request.uid');
    	if($uid){
    		$uid = $this->getUid(input('request.token'), $uid);
	    	if(!$uid){
	    		return $this->json('', 0, '未知参数');
	    	}
    	}

    	$goods_id = input('request.goodsid');
    	$goods = new GoodsService();
    	$info = $goods->goodsMaterial($goods_id, $uid);
    	return $this->json($info);
    }

    /*
     * 收藏商品
     */
    public function goodsFavor(){
    	$uid = $this->getUid(input('request.token'), input('request.uid'));
    	$goods_id = input('request.goodsid');
    	if(!$uid || !$goods_id){
    		return $this->json('', 0, '未知参数');
    	}
    	$goods = new GoodsService();
    	$result = $goods->goodsFavor($uid, $goods_id);
    	if(!$result){
    		return $this->json('', 0, '操作失败');
    	}
    	return $this->json('', 1, '操作成功');
    }

    /*
     * 收藏素材
     */
    public function mateFavor(){
    	$uid = $this->getUid(input('request.token'), input('request.uid'));
    	$m_id = input('request.mid');
    	$type = input('request.type');
    	if(!$uid || !$m_id){
    		return $this->json('', 0, '未知参数');
    	}
    	$goods = new GoodsService();
    	$result = $goods->mateFavor($uid, $m_id,$type);
    	if(!$result){
    		return $this->json('', 0, '操作失败');
    	}
    	return $this->json('', 1, '操作成功');
    }
    /**buyNow
     * 验证活动商品的购买数量
     */
    public function checkGoodsNum()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $goods_id = input('request.goodsid');
        $num = input('request.num', 1);
        $prom_id = input('prom_id', 0);
        $goods_model = new GoodsService();
        $res = $goods_model->checkGoodsNum($uid,$goods_id,$num, $prom_id);
        if ($res) {
            $this->json();
        } else {
            $this->json([],0, '数量超出购买限制');
        }
    }

    /*
     * 立即购买
     */
    public function buyNow(){
    	$user_id = input('request.uid');
    	$token = input('request.token');
    	$uid = $this->getUid($token, $user_id);
    	$goods_id = input('request.goodsid');
    	$sku_id = input('request.skuid');
    	$num = input('request.num/d', 1);
        $prom_id = input('prom_id', 0);
        $store_id = input('s_id');
    	if(!$uid || !$goods_id){
    		return $this->json('', 0, '未知参数');
    	}
    	$goods = new GoodsService();
    	$info = $goods->buyNow($uid, $goods_id, $sku_id, $num, $prom_id, $store_id);

		if($info == -3){
		   //不是新人
			return json(['data'=>$info,'status'=>-3,'msg'=>'新人专享一人仅可参与一次哦～']);
		}  else if ($info == -1) {
            return json(['data'=>[],'status'=>0,'msg'=>'该活动没有找到商品']);
        } elseif ($info == -2) {
            return json(['data'=>[],'status'=>0,'msg'=>'该商品未到活动时间']);
        }
        elseif($info == -4){
            return json(['data'=>$info,'status'=>-3,'msg'=>'购买数量超过活动商品库存']);
        }
    
        // else if($info == -4){
        //     return $this->json('', -4, '商品库存')
        // }
		return $this->json($info);
    }
	/*
	* 购物车
	*/
	public function cart(){
		//排序
		$order="weigh desc";
		//条件
		$map['sku_id']=['in',input('get.ids')];

		$GoodsService=new GoodsService();
		$rows=$GoodsService->getGoodsListBySkuId($map);
		$data['data']=$rows;
		$data['total']=count($rows);
		return $this->json($data);
	}

	/**
     * 获取商品规则
     */
    public function getGuize(){
        $goods_id = input('goods_id');
        $CartModel=new CartService();
        $goods_info = $CartModel->getGoodsinfo($goods_id);
        if (!$goods_info) {
            return $this->json([], 0, '未知参数');
        }
        $arr = json_decode($goods_info['spec_array'], true);
        return $this->json($arr);
    }

    /**
     * 根据规则 获取价格
     */
    public function getPrice(){
        $guize1 = input('guize1');
        $guize2 = input('guize2');
        $guize3 = input('guize3');
        $goods_id = input('goods_id');
        $CartModel=new CartService();
        $goods_info = $CartModel->getPrice($goods_id, $guize1, $guize2, $guize3);
        return $this->json($goods_info);
    }
	/**
     *  获取商品精选聚惠
     */
    public function getPickes(){
        $GoodsModel=new GoodsService();
        $goods_info = $GoodsModel->goodsPicked();
		if(!$goods_info){
			return $this->json('', 0, '获取失败');
		} else {
		    foreach ($goods_info as &$value) {
					$goodsService = new goodsService();
					$active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
					$commission = $this->getCom();
					//开启 返利
					if($commission['shop_ctrl'] == 1){
						$f_p_rate = $commission['f_s_rate'];
					}else{
						$f_p_rate = 100; 
					}
					$value['dianzhu_price'] = floor($active_price * $value['commission']/ 100 * $f_p_rate)/100;
					$value['price'] = floatval($value['price']);
            }
        }
		$goods_info = $GoodsModel->ActiveInfo($goods_info);
        return $this->json($goods_info);
    }
	/**
     *  获取商品今日特卖
     */
    public function getOffers(){
		$user_id = input('request.uid');
    	$token = input('request.token');
    	$uid = $this->getUid($token,$user_id);
        $GoodsModel=new GoodsService();
        $goods_info = $GoodsModel->goodsOffer();
        if(!$goods_info){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($goods_info as &$value) {
				$res = $GoodsModel->getstore($uid, $value['goods_id']);
                $value['vip_price'] = ceil($value['price'] * (100 - $value['commission'])) / 100;
                $value['dianzhu_price'] = floor($value['price'] * $value['commission'])/ 100;
                $value['rate'] = sprintf('%0.2f', $value['volume'] / ($value['stock'] + $value['volume']) * 100);
				//活动商品店主不获利 不 又获利了
				if($value['prom_type']!=0){
					// $value['dianzhu_price'] = 0;
					$goodsService = new goodsService();
					$active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
					$commission = $this->getCom();
					//开启 返利
					if($commission['shop_ctrl'] == 1){
						$f_p_rate = $commission['f_s_rate'];
					}else{
						$f_p_rate = 100; 
					}
					$value['dianzhu_price'] = floor($active_price * $value['commission']/ 100 * $f_p_rate)/100;
				}
                $value['is_put'] = $res;
				$label_title = $GoodsModel->getActive_label($value['prom_type']);
				if($label_title){
					$value['active_name'] = $label_title;
				}
            }
        }
        return $this->json($goods_info);
    }

    /*
     * 商品分享图片的生成
     */
    public function goodsShareImg(){
        $goods_service = new GoodsService();
        $goods_id = input('request.goods_id');
        $acti_id = input('request.acti_id', 0);
        $is_seller = input('request.is_seller', 0);
        if(!$goods_id){
            return $this->json('', 0, '未知参数');
        }
        $info = $goods_service->goodsShareImg($is_seller, $goods_id, $acti_id);
        if(!$info['code']){
            return $this->json('', 0, $info['msg']);
        }
        return $this->json($info['data']);
    }
	/*
     * 商品分享图片的生成（测试）
     */
    public function goodsShareImgs(){
        $goods_service = new GoodsService();
        $goods_id = input('request.goods_id');
        $acti_id = input('request.acti_id', 0);
        $is_seller = input('request.is_seller', 0);
        if(!$goods_id){
            return $this->json('', 0, '未知参数');
        }
        $info = $goods_service->goodsShareImgs($is_seller, $goods_id, $acti_id);
        if(!$info['code']){
            return $this->json('', 0, $info['msg']);
        }
        return $this->json($info['data']);
    }
    /*
     * 商品评价
     */
    public function evaluateGoods()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if (empty($uid)) {
            return $this->json([],0,"请重新登陆");
        }
        $order_id = input('order_id');
        $conent = input('content', '');
        $imgs = input('imgs', '');
        $dengji = input('dengji', 5);
        $order_model = new OrderSerevice();
        $goods_list = $order_model->getOrderInfo($uid, $order_id);
        if (!empty($goods_list)) {
            $data = [];
            $conent = explode(';str;', $conent);
            $imgs = explode(';str;', $imgs);
            $img_arr = [];
            //订单商品是否全部评价
           /*  $comm_flag = count($imgs);
			
            foreach ($imgs as $k => $v) {
                if ($v) {
                   $img_upload = $this->imgBaseUpload($v);
                    $qiniu_model = new Niuyun();
                    $img_upload = $qiniu_model->qiniu_upload($v);
                    if (!$img_upload['code']) {
                        return $this->json('', 0, $img_upload['msg']);
                    }
                    $img_arr[] = $img_upload['data'];
                } else {
                    $comm_flag -= 1;
                }
            } */
			
            $dengji = explode(',', $dengji);
            // 5星 好评数量；
            $star = 0;
            foreach ($goods_list as $key => $val) {
                if (empty($conent[$key])) {
                    continue;
                }
                $data[$key]['or_og_id'] = $val['og_goods_id'];
                $data[$key]['or_order_id'] = $order_id;
                $data[$key]['or_goods_id'] = $val['og_goods_id'];
                $data[$key]['or_cont'] = $conent[$key];
                if (isset($imgs[$key])) {
                    $data[$key]['or_thumb'] = $imgs[$key];
                }
                $data[$key]['or_scores'] = $dengji[$key] ? $dengji[$key] : 4;
                $data[$key]['or_uid'] = $uid;
                // $data[$key]['is_commented'] = 1;
                $data[$key]['or_add_time'] = time();
                if ($dengji[$key] == 5) {
                    $star ++;
                }
            }
			if(count($data)<count($goods_list)){
				return $this->json([], 0, "您还有商品未评价哦！");
			}
            if (empty($data)) {
                return $this->json([], 0, "评价失败");
            }
            $res = $order_model->orderRemark($data);
			// 添加成功
            if (!empty($res)) {
                // 判断5星评价数量
                if ($star > 0) {
                    $ConfigService=new Config();
                    $config=$ConfigService->find();
                    $evaluate_yinzi = $config['evaluate_yinzi'];
                    $evaluate_yinzi = json_decode($evaluate_yinzi, true);
                    //判断前台评价送元宝/积分开关 并且 送的积分或元宝 大于0
                    if ($evaluate_yinzi['status'] == 0 && $evaluate_yinzi['number'] > 0) {

                        for($i=0; $i< $star; $i++) {
                            if ($evaluate_yinzi['type'] == 0) {
                                //赠送积分
                                $jifen = (int)$evaluate_yinzi['number'];
                                Db::name('users')->where('user_id', $uid)->setInc('user_points', $jifen);
                                //加入积分日志
                                $log_point = [
                                    'p_uid' => $uid,
                                    'point_num' => $jifen,
                                    'point_type' => 1,
                                    'point_desc' => '评价赠送',
                                    'point_add_time' => time()
                                ];
                                Db::name('points_log')->insert($log_point);
                            } elseif ($evaluate_yinzi['type'] == 1) {
                                //赠送元宝
                                $jifen = (int)$evaluate_yinzi['number'];
                                $YinziService = new YinziService();
                                $YinziNo = $YinziService->createYzNo();
                                $time = time();
                                $die_time = $time + 720 * 3600;
                                $data1 = [
                                    'yin_no' => $YinziNo,
                                    'yin_uid' => $uid,
                                    'yin_amount' => $jifen,
                                    'yin_type' => 7,
                                    'yin_desc' => '五星评价送元宝',
                                    'yin_stat' => 2,
                                    'yin_add_time' => $time,
                                    'yin_valid_time' => 30,
                                    'yin_die_time' => $die_time
                                ];
                                $Yinzi = Db::name('yinzi')->insert($data1);
                                $y_log_yid = Db::name('yinzi')->getLastInsID();
                                $yz_data = [];
                                $yz_data['y_log_yid'] = $y_log_yid;
                                $yz_data['y_log_uid'] = $uid;
                                $yz_data['y_log_desc'] = '五星好评送元宝';
                                $yz_data['y_log_addtime'] = time();
                                Db::name('yinzi_log')->insert($yz_data);
                            }
                        }
                    }
                }

                //若全部评价，更新状态
                Db::name('order')->where('order_id', $order_id)->update(['is_commented' => 1,'order_status'=>4]);  
				Db::name('order_goods')->where('og_order_id', $order_id)->update(['og_order_status'=>4]);
                return $this->json([],1,"评价成功");
            }
        }
        return $this->json([], 0, "评价失败");


    }
    /*
     * 定时任务 每分钟执行
     */
    public function timedTask()
    {

//        header("Content-Type: text/html;charset=utf-8");
        $goods_service = new GoodsService();
        $goods_service->timedTask();
//        echo date('Y-m-d H:i:s', time()).PHP_EOL;
//        while (true) {
//            echo date('Y-m-d H:i:s', time()).PHP_EOL;
//
//
//            echo "OK".PHP_EOL;
//            echo date('Y-m-d H:i:s', time()).PHP_EOL;
//            sleep(20);
//        }
    }

    /**
     * 每天12点结算佣金、店铺是否升级、培训费等
     */
    public function midnightTask()
    {
        $goods_service = new GoodsService();
        $goods_service->midnightTask();
    }

    /**
     * 月结
     */
    public function monthTask()
    {
        $goods_service = new GoodsService();
        $goods_service->monthTask();
    }
}
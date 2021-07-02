<?php
namespace app\api\controller;

use app\common\service\Config;
use app\common\service\Goods as GoodsService;
use app\common\service\GoodsCategory as GoodsCategoryService;
use app\common\service\Cart as CartService;
use app\common\service\User as User;
use app\common\service\Order as OrderSerevice;
use app\common\service\Yinzi as YinziService;
use app\common\service\Sen;
use think\Db;
class Goods extends Common {
    protected $goods;
    protected $goodsCate;
    protected $cart;
    protected $order;
    public function __construct()
    {
        $this->goods = new GoodsService();
        $this->goodsCate = new GoodsCategoryService();
        $this->cart = new CartService();
        $this->order = new OrderSerevice();
        $this->sen = new Sen();

    }

    /**
     * 商品数据恢复，原商品中没有规格，商品重新编辑后又插入了一条规格
     */
    public function goodsreset(){

    }
    /**
     * 商品列表
     */
	public function index()
    {
		$map['status']='normal';
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
		$rows=$this->goods->paginate($map,'*',$order);
		return $this->json($rows);
	}

	/**
	 * 商品分类
	 */
	public function category()
    {
		$category_id=input('get.category_id');
		//获取全部分类
		$rows=$this->goodsCate->select([],"*","weigh desc");
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
		$map['status']='normal';
		$map['category_id']=['in',$ids];
		$rows=$this->goods->select($map,'*','weigh desc');
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

	/**
	 * 商品详情
	 */
	public function detail()
    {
		$map['goods_id']=input('get.goods_id');
		$info=$this->goods->find($map);
		if($info['sku']){
			$info['sku']=$info->sku;
			foreach($info['sku'] as $value){
				$sku_data[$value['attr_value']]=$value;
			}
			$info['sku_data']=$sku_data;
		}
		//替换图片信息
		$info['description']=str_replace("/uploads",'http://'. $_SERVER['SERVER_NAME'].'/uploads',$info['description']);
		$data['info']=$info;
		return $this->json($data);
	}

    /**
     * 商品详情--商品
     * @param int goodsId 商品id
     * @param int uid
     * @param string token
     * @param int activeId 活动id, 不传没有活动
     * @return json
     */
    public function goodsDetails()
    {
    	$user_id = input('uid');
    	$active_id = input('activeId');
        $goods_id = input('goodsId');
        $store_id = input('request.storeId');
    	$info = $this->goods->goodsDetail($goods_id, $user_id,$active_id, $store_id);
        $active_info = $this->goods->getActiveInfo($active_id, $goods_id);
        
		$info['active_info'] = '';

		//起送价
        $info['standard'] = Db::name('config')->value('standard');
		if($active_info){
			 $info['active_info'] = $active_info;
		}
    	if($info == -1){
    		return $this->json('', 0, '商品已下架');
    	}
        
    	return $this->json($info);
    }

	/**
	 * 领取优惠券(不确定)
     * @param integer uid
     * @param string token
     * @param integer goodsId
     * @return json
	 */
	public function getCoupon()
    {
		$user_id = input('request.uid');
		if($user_id){
			$token = input('request.token');
			$uid = $this->getUid($token, $user_id);
			if(!$uid){
				return $this->json('', 0, '未知参数');
			}
		}
		$goods_id = input('request.goodsId');
		$list = $this->goods->getCoupon($goods_id);
		return $this->json($list);
	}

    /**
     * 商品详情--详情
     * @param integer goodsId 商品id
     * @return json
     */
    public function goodsInfo()
    {
    	$goods_id = input('request.goodsId');
    	$info = $this->goods->goodsInfo($goods_id);
    	return $this->json($info);
    }

    /**
     * 商品详情--素材
     * @param integer goodsId 商品id
     * @param integer uid
     * @param string token
     * @return json
     */
    public function goodsMaterial()
    {
    	$uid = input('request.uid');
    	if($uid){
    		$uid = $this->getUid(input('request.token'), $uid);
	    	if(!$uid){
	    		return $this->json('', 0, '未知参数');
	    	}
    	}

    	$goods_id = input('request.goodsId');
    	$info = $this->goods->goodsMaterial($goods_id, $uid);
    	return $this->json($info);
    }

    /**
     * 收藏商品
     * @param integer uid
     * @param string token
     * @param integer goodsId 商品id
     * @return json
     */
    public function goodsFavor()
    {
    	$uid = $this->getUid(input('request.token'), input('request.uid'));
    	$goods_id = input('request.goodsId');
    	if(!$uid || !$goods_id){
    		return $this->json('', 0, '未知参数');
    	}
    	$result = $this->goods->goodsFavor($uid, $goods_id);
    	if(!$result){
    		return $this->json('', 0, '操作失败');
    	}
    	return $this->json('', 1, '操作成功');
    }

    /**
     * 收藏素材
     */
    public function mateFavor(){
    	$uid = $this->getUid(input('request.token'), input('request.uid'));
    	$m_id = input('request.mid');
    	$type = input('request.type');
    	if(!$uid || !$m_id){
    		return $this->json('', 0, '未知参数');
    	}
    	$result = $this->goods->mateFavor($uid, $m_id,$type);
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
        $res = $this->goods->checkGoodsNum($uid,$goods_id,$num, $prom_id);
        if ($res) {
            $this->json();
        } else {
            $this->json([],0, '数量超出购买限制');
        }
    }

    /**
     * 立即购买
     * @param integer uid
     * @param string token
     * @param integer goodsId 商品id
     * @param integer ruleId 规格id
     * @param integer storeId 从店铺购买时,传入店铺id,其他情况不传
     * @return json
     */
    public function buyNow()
    {
    	$user_id = input('request.uid');
    	$token = input('request.token');
    	$uid = $this->getUid($token, $user_id);
    	$goods_id = input('request.goodsId');
    	$sku_id = input('request.ruleId');
    	$num = input('request.num/d', 1);
        $prom_id = input('prom_id', 0); //活动id
        $store_id = input('s_id');
    	if(!$uid || !$goods_id){
    		return $this->json('', 0, '未知参数');
    	}
    	$info = $this->goods->buyNow($uid, $goods_id, $sku_id, $num, $prom_id, $store_id);

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
		return $this->json($info);
    }
    /**
     * 获取商品积分满赠
     * @param integer ids  购物车id或者商品id
     * @param string token
     * @param integer type 从店铺购买时,传入店铺id,其他情况不传
     * @param integer num 商品数量
     * @return json
     */
    public function get_jifen()
    {
        $ids = input('request.ids');
        $type = input('request.type');
        $num = input('request.num');
        $uid = input('request.uid');
        $sku_id = input('request.sku_id');
        if(!$ids){
            return $this->json('', 0, '未知参数');
        }
        $total=0;
        $gift = '';
        $gift_list = array();
      if($type=='detail'){//立即购买
          $goods = Db::name('goods')->where('goods_id',$ids)->find();
          if ($goods['prom_type']==0 && $goods['prom_id']==0){
              //判断商品中是否有参与满赠的属性
              $flage = false;
                  $goods_sku = Db::name('goods_sku')->where('sku_id',$sku_id)->field('all,gift')->find();
                  if ($goods_sku['all'] && $goods_sku['gift']){
                      if ($num>=$goods_sku['all']){
                          $gift_list[] = $goods['goods_name']."满".$goods_sku['all'].'赠'.$goods_sku['gift'];
                          $flage = true;
                      }
                  }

            if (!$flage){
                if($goods['is_recom_today']==0){
                    $total =$num * $goods['price'];
                }
            }
        }
      }else{//购物车
            $list = Db::name('cart')->where('id','in',$ids)->select();
            foreach($list as $v) {
                $is_recom_today = Db::name('goods')->where('goods_id', $v['goods_id'])->field('is_recom_today,goods_name,prom_type,prom_id')->find();
                if ($is_recom_today['prom_type'] == 0 && $is_recom_today['prom_id'] == 0) {
                    //判断该商品是否有参与满赠的属性
                    $flage = false;
                    $goods_sku = Db::name('goods_sku')->where('sku_id', $v['item_id'])->field('all,gift')->find();
                    if ($goods_sku['all'] && $goods_sku['gift']) {
                        if ($v['num'] >= $goods_sku['all']) {
                            $gift_list[] = $is_recom_today['goods_name'] . "满" . $goods_sku['all'] . '赠' . $goods_sku['gift'];
                            $flage = true;
                        }
                    }
                    if (!$flage) {
                        if ($is_recom_today['is_recom_today'] == 0) {
                            $total += $v['price'] * $v['num'];
                        }
                    }
                }
            }
      }

      //判断满赠状态
        if (count($gift_list)>0){
            $gift = implode(',',$gift_list);
        }else{
            $config = Db::name('config')->field('full_gift,standard,setjifen') ->find();
            $full_give = json_decode($config['full_gift'],true);
            foreach($full_give['field'] as $key=>$v){
                if($total>=$v){
                    $gift = '满'.$v.'赠'.$full_give['value'][$key];
                }
            }
        }
        //起送费
        $standard = $config['standard'];
        //积分可抵扣
        $user_info = Db::name('users')->where('user_id', $uid)->field('user_points')->find();
        $point_config = $config['setjifen'];
        $point_config = json_decode($point_config, true);
        if(!$point_config['status']){
            $jifen_bilv = $point_config['number']?$point_config['number']:0;
            $points_avai = round($user_info['user_points'] / $jifen_bilv, 2);
        }else{
            $points_avai=0;
        }
        $info['points'] = $user_info['user_points'];
        $info['points_avai'] = $points_avai;
        $info['gift'] = $gift;
        $info['standard'] =$standard;
        return $this->json($info);
    }
	/**
     * 获取商品规则
     * @param integer goodsId 商品id
     * @return json
     */
    public function goodsRule()
    {
        $goods_id = input('goodsId');
        $goods_info = $this->cart->getGoodsinfo($goods_id);
        if (!$goods_info) {
            return $this->json([], 0, '未知参数');
        }
        $arr = json_decode($goods_info['spec_array'], true);
        return $this->json($arr);
    }

    /**
     * 根据规则 获取价格
     * @param rule1
     * @param rule2
     * @param rule3
     * @return json
     */
    public function goodsPrice()
    {
        $ruleOne = input('request.rule1');
        $ruleTwo = input('request.rule2');
        $ruleThree = input('request.rule3');
        $goods_id = input('request.goodsId');
        $goods_info = $this->cart->getPrice($goods_id, $ruleOne, $ruleTwo, $ruleThree);
        return $this->json($goods_info);
    }
	/**
     *  获取商品精选聚惠
     */
    public function getPickes()
    {
        $goods_info = $this->goods->goodsPicked();
		if(!$goods_info){
			return $this->json('', 0, '获取失败');
		} else {
		    foreach ($goods_info as &$value) {
					$this->goods = new goodsService();
					$active_price = $this->goods->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
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
		$goods_info = $this->goods->ActiveInfo($goods_info);
        return $this->json($goods_info);
    }

	/**
     *  获取商品今日特卖
     */
    public function getOffers()
    {
		$user_id = input('request.uid');
    	$token = input('request.token');
    	$uid = $this->getUid($token,$user_id);
        $goods_info = $this->goods->goodsOffer();
        if(!$goods_info){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($goods_info as &$value) {
				$res = $this->goods->getstore($uid, $value['goods_id']);
                $value['vip_price'] = ceil($value['price'] * (100 - $value['commission'])) / 100;
                $value['dianzhu_price'] = floor($value['price'] * $value['commission'])/ 100;
                $value['rate'] = sprintf('%0.2f', $value['volume'] / ($value['stock'] + $value['volume']) * 100);
				//活动商品店主不获利 不 又获利了
				if($value['prom_type']!=0){
					$active_price = $this->goods->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
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
				$label_title = $this->goods->getActive_label($value['prom_type']);
				if($label_title){
					$value['active_name'] = $label_title;
				}
            }
        }
        return $this->json($goods_info);
    }

    /**
     * 商品分享图片的生成
     * @param integer goodsId 商品ID
     * @param integer activeId 活动id
     * @param integer isSeller 0 vip ; 1 店主
     * @return json
     */
    public function goodsShare()
    {
        $goods_id = input('request.goodsId');
        $acti_id = input('request.activeId', 0);
        $is_seller = input('request.isSeller', 0);
        if(!$goods_id){
            return $this->json('', 0, '未知参数');
        }
        $info = $this->goods->goodsShareImg($is_seller, $goods_id, $acti_id);
        if(!$info['code']){
            return $this->json('', 0, $info['msg']);
        }
        return $this->json($info['data']);
    }

    /**
     * 商品评价
     * @param integer uid
     * @param string token
     * @param integer orderId 订单ID
     * @param string content 评价内容
     * @param Resource imgs
     * @param string level 评价等级
     * @return json
     */
    public function evaluate()
    {

        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if (empty($uid)) {
            return $this->json([],0,"请重新登陆");
        }
        $order_id = input('request.orderId');
        $conent = input('request.content', '');
        $sen = new  Sen();
        $is_sen = $sen->pure($conent);
        if($is_sen){
            return $this->json([], 0, "您的评价中有敏感词汇！");
        }
        $imgs = input('request.imgs', '');
        $dengji = input('request.level', 5);
        $goods_list = $this->order->getOrderInfo($uid, $order_id);
        if (!empty($goods_list)) {
            $data = [];
            $conent = explode(';str;', $conent);
            $imgs = explode(';str;', $imgs);
            $img_arr = [];
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
            $res = $this->order->orderRemark($data);
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

    /**
     * description:评价列表信息
     * @param int goodsId 商品id
     * @param int page 当前页
     * @return json
     */
    public function commentList(){
        if(Request()->isPost()){
            $this->checkEmploy(Request()->post(),['goodsId']);
            $page=Request()->post("page")?Request()->post("page"):1;
            $goodsId=Request()->post("goodsId/d");
            $commentList=$this->goodsComment($goodsId,$page);
            if($commentList){
                $count=$this->goodsCommentCount($goodsId);
                $totalPage=ceil($count/10);
                return json(['status'=>1,'msg'=>'数据信息','data'=>['commentList'=>$commentList,'totalPage'=>$totalPage]]);
            }
            return json(['status'=>-1,'msg'=>'暂无数据信息','data'=>[]]);
        }
    }

    /**
     * 定时任务 每分钟执行
     */
    public function timedTask()
    {
        $this->goods->timedTask();
    }

    /**
     * 每天12点结算佣金、店铺是否升级、培训费等
     */
    public function midnightTask()
    {
        $this->goods->midnightTask();
    }

    /**
     * 月结
     */
    public function monthTask()
    {
        $this->goods->monthTask();
    }

    /**
     * @param $goodsId
     * @param $page
     * 评价列表表
     */
    function goodsComment($goodsId,$page,$limit=10){
        $comment=Db::name("order_remark")->alias("c")
            ->join("__USERS__ u","c.or_uid=u.user_id")
            ->field("c.or_id,c.or_cont,c.or_scores,c.or_thumb,c.or_add_time,u.user_name,u.user_mobile,u.user_avat")
            ->order("c.or_uid desc")
            ->page($page,$limit)
            ->where(['c.or_goods_id'=>$goodsId])
            ->where(['c.status'=>1])//0:待审核 1:审核通过;2:审核不通过
            ->select();
        $data = [];
        if($comment){
            foreach ($comment as $k=>$v){
                $v['create_time']=date("Y-m-d",$v['or_add_time']);
                $v['or_add_time']=date("Y-m-d",$v['or_add_time']);
                if($v['or_thumb']){
                    $v['img_url']=explode(",",rtrim($v['or_thumb'],','));
                }else{
                    $v['img_url']=[];
                }
                $v['id'] = $v['or_id'];
                $v['star'] = $v['or_scores'];
                $v['content'] = $v['or_cont'];
                $v['user_avat']=$v['user_avat']?$v['user_avat']:"";
                $data[$k]=$v;
            }
        }
        return $data;
    }

    /**
     * @param $goodsId
     * @return int|string
     * 总评价条数
     */
    function goodsCommentCount($goodsId){
        $number=Db::name("order_remark")->where(['or_goods_id'=>$goodsId, 'status' => 1])->count("or_id");
        return $number;
    }

    /**
     * 获取商品规则详情
     * @param int $goods_id 商品id
     */
    public function rulePrice()
    {
        $goods_id = input('request.goods_id');
        //获取商品信息
        $goods_info = Db::name('goods')->where('goods_id',$goods_id)->find();
//        $map['goods_id']=$goods_id;
//        $row=$this->goods->find($map);
//        $row['sku']=collection($row['sku'])->toArray();
        $spec = json_decode($goods_info['spec'],true);
       $arrs =[];
       foreach ($spec as $k=>$v){
           foreach ($v as $vv){
               $arrs[] = $k.':'.$vv;
           }
       }

        if ($goods_info['prom_type']==3){
            $sku_id = Db::name('team_activity')->where('goods_id',$goods_id)->value('sku_id');
            if ($sku_id){
                $data = Db::name("goods_sku")->field('sku_id,sku_name,price,show_price,all,gift,stock')->where(['sku_id' => $sku_id])->where('status',1)->select();
            }else{
                $data = Db::name("goods_sku")->field('sku_id,sku_name,price,show_price,all,gift,stock')->where(['goods_id' => $goods_id])->where('status',1)->select();
                $skus = Db::name("goods_sku")->field('sku_id,sku_name,price,show_price,all,gift,stock,attr_value')
                    ->where('attr_value','in', $arrs)
                    ->where('goods_id',$goods_id)
                    ->order('sku_id desc')
                    ->buildSql();
                //$data = Db::table($skus.' sk')->group('sk.attr_value')->select();
            }
        }elseif ($goods_info['prom_type']==5){
            $sku_id = Db::name('flash_goods')->where('goods_id',$goods_id)->value('sku_id');
            if ($sku_id){
                $data = Db::name("goods_sku")->field('sku_id,sku_name,price,show_price,all,gift,stock')->where(['sku_id' => $sku_id])->where('status',1)->select();
            }else{
               $data = Db::name("goods_sku")->field('sku_id,sku_name,price,show_price,all,gift,stock')->where(['goods_id' => $goods_id])->where('status',1)->select();
                $skus = Db::name("goods_sku")->field('sku_id,sku_name,price,show_price,all,gift,stock,attr_value')
                    ->where('attr_value','in', $arrs)
                    ->where('goods_id',$goods_id)
                    ->order('sku_id desc')
                    ->buildSql();
                //$data = Db::table($skus.' sk')->group('sk.attr_value')->select();
            }
        }else{
            $data = Db::name("goods_sku")->field('sku_id,sku_name,price,show_price,all,gift,stock')->where(['goods_id' => $goods_id])->where('status',1)->select();
            $skus = Db::name("goods_sku")->field('sku_id,sku_name,price,show_price,all,gift,stock,attr_value')
                ->where('attr_value','in', $arrs)
                ->where('goods_id',$goods_id)
                ->order('sku_id desc')
                ->buildSql();
            //$data = Db::table($skus.' sk')->group('sk.attr_value')->select();
        }
     
        foreach ($data as &$v){
            if ($v['all'] && $v['gift']){
                $v['send_gift'] = '满'.$v['all'].'赠'.$v['gift'];
            }else{
                $v['send_gift'] = '';
            }
        }
        return $this->json($data);
    }
}
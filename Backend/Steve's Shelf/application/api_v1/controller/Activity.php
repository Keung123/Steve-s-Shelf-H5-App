<?php
namespace app\api\controller;
use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsActivity;
use app\common\service\User as UserService;
use app\common\service\Cart as CartService;
use app\common\service\GoodsActivity as GoodsActivityService;
use app\common\service\Goods as GoodsService;
use think\Db;
use think\Request;
use getui\Pushs;
class Activity extends Common{

    public function __construct(){
        parent::__construct();
        $GoodsModel = new GoodsModel();
        $this->model = $GoodsModel;
        $userSevice=new UserService();
        $this->userObject=$userSevice;
    }
    /**
     * 预售活动列表信息
     */
    public function activityList(){
        $goodsActivity=new GoodsActivityService();
        if(Request()->isPost()){
            $where=['act_type'=>2,'is_finished'=>0];
            $this->checkEmploy(Request()->post(),['page']);
            if(Request()->post("keywords")){
                $keywords=Request()->post("keywords");
                $where['act_name']=['like','%'.$keywords.'%'];
            }
            $where['start_time'] = ['lt', time()];
            $where['end_time'] = ['gt', time()];
            $page=Request()->post('page/d');
            $preSellList=model("GoodsActivity")
                ->where($where)
                ->page($page,10)
                ->order("start_time")
                ->select();
            $preSellList=collection($preSellList)->toArray();
            if($preSellList){
                foreach ($preSellList as $key=>$value){
                    //$preSellList[$key]=array_merge($preSellList[$key], unserialize($preSellList[$key]['ext_info']));
                   //获得活动状态信息
                    $preSellList[$key]['act_status']=$goodsActivity->activityStatus($preSellList[$key]);
                    //改活动的销量
                    $preSellCount=$goodsActivity->preSellCount($preSellList[$key]['act_id'],$preSellList[$key]['goods_id']);
                    $preSellList[$key]=array_merge($preSellList[$key], $preSellCount);
                    //获得商品价格
                    $goodsPrice=$goodsActivity->getGoodsInfo($preSellList[$key]['goods_id']);
                    $preSellList[$key]['sell_price']=$goodsPrice['price'];
                    $preSellList[$key]['goods_thumb']=$goodsPrice['picture'];
					// $preSellList[$key]['price']=$goodsPrice['price'];
                    $preSellList[$key]['vip_price']=$goodsPrice['vip_price'];
                    $preSellList[$key]['show_price']=$goodsPrice['show_price'];
                    $preSellList[$key]['rate'] = sprintf('%0.2f', $preSellList[$key]['total_goods'] / ($preSellList[$key]['act_count'] + $preSellList[$key]['total_goods']) * 100);
                    $preSellList[$key]['rate'] = floatval($preSellList[$key]['rate']);
                    if($preSellList[$key]['rate']<=0){
                        $preSellList[$key]['rate'] = 0;
                    }

                }
                //计算分页
                $count=model("GoodsActivity")->where($where)->count();
                $totalPage=$count;
                return json(['status'=>1,'msg'=>'数据信息','data'=>['lists'=>$preSellList,'totalPage'=>$totalPage]]);

            }
            return json(['status'=>-1,'msg'=>'暂无数据！','data'=>[]]);
        }
    }
    /**
     * 预售商品详情
     */
    function activityDetail(){
        $goodsActivity=new GoodsActivityService();
        if(Request()->isPost()){
            $this->checkEmploy(Request()->post(),['act_id']);
            //获得活动商品的信息
            $actId=Request()->post("act_id/d");
            $isActivity=model("GoodsActivity")->where(['act_id'=>$actId])->find()->toArray();
			 
            //$isActivity=array_merge($isActivity,unserialize($isActivity['ext_info']));
            if(empty($isActivity)){
                return json(['status'=>-1,'msg'=>'该活动已经结束或者已经下架！']);exit;
            }
            //查询商品信息
            $goodsInfo=model("goods")->where(['goods_id'=>$isActivity['goods_id']])
                ->field("goods_id,price,images,description,spec,introduction,spec_array,subject_values,picture")
                ->find()->toArray();
            if(empty($goodsInfo)){
                return json(['status'=>-1,'msg'=>'该商品已经下架！']);exit;
            }
            $goodsInfo['favorite'] = 0;
            $goodsInfo['is_shangjia'] = 0;
            if(input('request.uid')){
                $uid = $this->getUid(input('request.token'), input('request.uid'));
                if(!$uid){
                    return $this->json('', 0, '未知参数');
                }
                else{
                    // 是否收藏
                    $is_favorite = Db::name('favorite')->where(['f_uid' => $uid, 'favor_type' => 1, 'f_goods_id' => $isActivity['goods_id']])->field('favor_id')->find();
                    if($is_favorite){
                        $goodsInfo['favorite'] = 1;
                    }
                    // 店主是否上架
                    $store_goods = Db::name('store_goods')->where(['s_g_userid' => $uid, 's_g_isdel' => 0, 's_g_goodsid' => $isActivity['goods_id']])->find();
                    $goodsInfo['is_shangjia'] = $store_goods ? 1 : 0;
                }
            }

            //商品相册
            //$goodsInfo['images']=explode(",",rtrim($goodsInfo['images'],","));
            $goodsInfo['description']=explode(",",rtrim($goodsInfo['description'],","));
            //获得商品的属性值
            $goodsAttr=$this->goodsAttrValue($isActivity['goods_id'],$isActivity['price'],$isActivity['total_goods']);
            $goodsInfo['goods_attr']=$goodsAttr;
            //获得商品的评价
            $integralObject=controller("Integral");
            $comment=$integralObject->goodsComment($isActivity['goods_id'],1);
            $goodsInfo['goods_comment']=$comment;
            //评价数量
            $commentNumber=$integralObject->goodsCommentCount($isActivity['goods_id']);
            $goodsInfo['goods_comment_number']=$commentNumber;
            //购物须知
            $content=Db::name("content")->where('content_id',1)->value("content");
            $webUrl=$_SERVER["HTTP_HOST"];
            $content=str_replace('/uploads/',$webUrl.'/uploads/',$content);
            $goodsInfo['need_rule']=$content;
            //获得的状态
            $status=$goodsActivity->activityStatus($isActivity);
            $isActivity['status']=$status;
            // 优惠券
            $coupon_where = 'coupon_type=1 and coupon_type_id='.$isActivity['goods_id'].' or coupon_type=3 and coupon_stat=0';
            $coupon_info = Db::name('coupon')->where($coupon_where)->field('coupon_id,coupon_title,coupon_thumb,coupon_price,coupon_use_limit')->order('coupon_s_time asc')->find();
            $goodsInfo['coupon'] = $coupon_info ? $coupon_info : [];
            $lastInfo=array_merge($goodsInfo,$isActivity);
			$activeInfo = Db::name('active_type')->where('id',$isActivity['act_type'])->field('pay_start_time,pay_end_time,abstract_content')->find();
			$lastInfo['deposit_use'] = $isActivity['deposit_use'];	 
			$lastInfo['abstract_content'] = $activeInfo['abstract_content'];	 
			$lastInfo['pay_start_time'] = date('Y.m.d',$activeInfo['pay_start_time']);	
			$lastInfo['pay_end_time'] = date('Y.m.d',$activeInfo['pay_end_time']);	 
            if($lastInfo){
                return json(['status'=>1,'msg'=>'数据信息','data'=>$lastInfo]);
            }
            return json(['status'=>-1,'msg'=>'暂无信息！','data'=>[]]);
        }
        return json(['status'=>-2,'msg'=>'操作信息','data'=>[]]);
    }
    /**
     * 立即支付订单
     */
    function flagDeposit(){
        $goodsActivity=new GoodsActivityService();
        if(Request()->isPost()){
//            $this->checkEmploy(Request()->post(),['number','act_id','token','user_id']);
            $this->checkEmploy(Request()->post(),['number','act_id']);
            //判断该活动状态
            $actId=Request()->post("act_id/d");
            $goodsNumber=Request()->post("number/d");
            $activity=model("GoodsActivity")->where(['act_id'=>$actId])->find()->toArray();
            //判断用户是否存在
//            if(!$this->getUid(Request()->post("token"),Request()->post("user_id"))){
//                return json(['status'=>-1,'msg'=>'该用户不存在！','data'=>[]]);
//            }
            if(empty($activity)){
                return json(['status'=>-1,'msg'=>'该活动已经结束或者已经下架！']);exit;
            }
            $preSellInfo=array_merge($activity,unserialize($activity['ext_info']));
           if($preSellInfo['total_goods']<$goodsNumber){
                return json(['status'=>-1,'msg'=>'库存不足！']);exit;
            }
            //判断活动状态信息
            $status=$goodsActivity->activityStatus($preSellInfo);
            $preSellInfo['status']=$status;
            if($preSellInfo['status']=='未开始'){
                $msg='该活动还未开始,不能提交定金';
                $status=0;
            }else if($preSellInfo['status']=='预售中'){
                $msg='活动开始';
                $status=1;
            }elseif($preSellInfo['status']=='成功结束'){
                $msg='该活动已经结束！';
                $status=-1;
            }else{
                $msg='该活动已经结束！';
                $status=-1;
            }
            return json(['status'=>$status,'msg'=>$msg,'data'=>[]]);
        }
        return json(['status'=>-1,'msg'=>'非法操作！','data'=>'']);
    }
    /**
     * 进行立即预定的页面
     */
    function nowBooked(){
        if(Request()->isPost()){
            $this->checkEmploy(Request()->post(),['user_id','act_id','number','token']);
            $actId=Request()->post("act_id/d");
            $goodsNumber=Request()->post("number/d");
            $activity=model("GoodsActivity")->where(['act_id'=>$actId])->find()->toArray();
            //判断用户是否存在
            if(!$this->getUid(Request()->post("token"),Request()->post("user_id"))){
                return json(['status'=>0,'msg'=>'该用户不存在！','data'=>[]]);
            }
            if(empty($activity)){
                return json(['status'=>0,'msg'=>'该活动已经结束！']);exit;
            }
            $goods_info=Db::name('goods')->where(['goods_id' => $activity['goods_id']])->find();
            if($goods_info['status']==1){
                return json(['status'=>0,'msg'=>'该商品已下架！']);exit; 
            }
            $preSellInfo=array_merge($activity,unserialize($activity['ext_info']));
            if($activity['total_goods']<$goodsNumber || $activity['total_goods']<=0){
                return $this->json(['status'=>0,'msg'=>'库存不足！']);exit;
            }
            //获得用户的信息
            $userInfo= $this->userObject->userInfo(['user_id'=>Request()->post("user_id")],"user_id");
            if(empty($userInfo)){
                return json(['status'=>0,'msg'=>'非法操作！']);exit;
            }
            $precValue='';
            //获得商品的规格
            if(Request()->post("sku_id")){
                $where['sku_id']=Request()->post("sku_id/d");
                $where['goods_id']=$activity['goods_id'];
                $goodsAttr=controller("Integral")->goodsSpecValue($where);
                $precValue=$goodsAttr['specValue'];
                $preSellInfo['sku_id']=$goodsAttr['sku_id'];
            }
            //获得收货地址信息
            if(Request()->post("address_id")){
                $whereAddress['addr_id']=Request()->post("address_id");
                $whereAddress['a_uid']=Request()->post("user_id/d");
            }else{
                $whereAddress['a_uid']=Request()->post("user_id/d");
                $whereAddress['is_default']=1;
            }
            $address=Db::name("addr")->where($whereAddress)
                ->field("addr_id,addr_province,addr_city,addr_area,addr_cont,addr_receiver,addr_phone")
                ->find();
            //根据省市区获省市区信息
            if($address['addr_province']){
                $proName=$this->getRegion(['region_id'=>$address['addr_province']]);
            }
            if($address['addr_city']){
                $cityName=$this->getRegion(['region_id'=>$address['addr_city']]);
            }
            if($address['addr_area']){
                $areaName=$this->getRegion(['region_id'=>$address['addr_area']]);
            }
            if ($address) {
                $address['addr_area'] = '';
                $address['addr_cont']=$proName.$cityName.$areaName.$address['addr_cont'];
            }

            $preSellInfo['spec_value']=$precValue;
            $preSellInfo['goods_number']=$goodsNumber;
            $preSellInfo['pay_money']=sprintf("%.2f",$goodsNumber*$preSellInfo['deposit']);
            $preSellInfo['picture'] = Db::name('goods')->where('goods_id',$activity['goods_id'])->value('picture');
            $goodsMOdle = new GoodsService();
            $preSellInfo['yunfei'] = $goodsMOdle->getYunfei($activity['goods_id'],$goodsAttr['sku_id'], $address['addr_province'], $goodsNumber);
            $preSellInfo['yunhui'] = $activity['deposit_use'] - $activity['deposit'];
            $uid = Request()->post("user_id");
            // 充值卡
            $recharge = Db::name('user_rc')->where(['card_uid' => $uid, 'card_stat' => 1])->count();
            // 元宝
            $yz = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_stat' => 2])->count();
            $preSellInfo['rc'] = $recharge ? 1 : 0;
            $preSellInfo['yz'] = $yz ? 1 : 0;
           return json(['status'=>1,'msg'=>'数据信息','data'=>['goods_info'=>$preSellInfo,'address'=>$address]]);

        }
        return json(['status'=>0,'msg'=>'非法操作！','data'=>[]]);
    }
    /**
     * @param $goodsId
     * @param $price
     * @param $stock
     * @return array
     * 商品属性规格 库存 价格
     */
    function goodsAttrValue($goodsId,$price,$stock){
        $goodsSku=Db::name("goods_sku")->where(['goods_id'=>$goodsId])
            ->field("sku_id,attr_value,attr_value_array,price,stock,image")->select();
        $goodsAttr=[];
        if($goodsSku){
            foreach($goodsSku as $k=>$v){
                $attrValue=explode(";",$v['attr_value']);
                if(count($attrValue)==1){
                    $goodsAttr[str_replace(":","_",$attrValue[0])]=['price'=>$price,'stock'=>$stock,'skuId'=>$v['sku_id'],'image' => $v['image']];
                }else{
                    $attrHtml='';
                    foreach($attrValue as $kk=>$vv){
                        $attrHtml.= str_replace(":","_",$vv)."_";
                    }

                    $goodsAttr[rtrim($attrHtml,"_")]=['price'=>$price,'stock'=>$stock,'skuId'=>$v['sku_id'],'image' => $v['image']];
                }

            }
        }
        return $goodsAttr;
    }
	 /**
     *  开团前判断 
     */
	function CheckedTeam(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$team_id = input('request.team_id');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$where = [
			'user_id' => $uid,//用户id
			'team_id' => $team_id,//开团id
            'end_time'=>['>',time()],
			 'status' => ['eq',1],//开团状态不等于失败
		];
		$res = Db::name("team_found")->where($where)->find();
		if($res){
			return $this->json('', -1, '已经发起开团');
		}
		$users = Db::name("users")->where(['user_id'=>$uid])->field("user_name")->find();
		//开团商品是否存在
		$team = Db::name("team_activity")->where(['id'=>['eq',$team_id],'status'=>['eq',0]])->field('time_limit,need_num,team_price,goods_id')->find();
        
		if(!$team){
			return $this->json('', 0, '开团商品不存在');
		}
        // if($team['need_num']>$team['goods_num']){
        //    return $this->json('', 0, '库存不满足开团条件'); 
        // }
		return $this->json('', 1, '可以开团');
	}
	/**
     *  参团前判断 
     */
	function CheckedFollow(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$found_id = input('request.found_id');//开团id
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$where = [
			'id' => $found_id,//开团id
			'status' => 1,//开团0:待开团 1：已开团 2：拼团成功 3:拼团失败
		];
		$res = Db::name("team_found")->where($where)->find();	
		
		$wheres = [
			'user_id' => $user_id,//用户id
			'found_id' => $found_id ,//开团id值
		];
		$result = Db::name("team_follow")->where($wheres)->find();
		if($result){
			 return $this->json('', -4, '已经参加过了');
		}
		
		if(!$res){
			   return $this->json('', -3, '此团不存在或者已经结束');
		}
		if($res['joins']>=$res['need']){
			 return $this->json('', -1, '开团人数已经完成');	
		}
		//不能参加自己发起的团
		if($uid == $res['user_id']){
			 return $this->json('', -2, '不能参加自己发起的团');	
		}
		 return $this->json('', 1, '可以参团');	
	}
	 /**
     *  开团 
     */
	function openTeam(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$team_id = input('request.team_id');
		$order_id = input('request.order_id');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$where = [
			'user_id' => $uid,//用户id
			'team_id' => $team_id,//开团id
            'end_time'=>['>',time()],
            'status'=>1
		];
		$res = Db::name("team_found")->where($where)->find();
		if($res){
			return $this->json('', -1, '已经发起开团');
		}
		$users = Db::name("users")->where(['user_id'=>$uid])->field("user_name")->find();
		//开团商品是否存在
		$team = Db::name("team_activity")->where(['id'=>['eq',$team_id],'status'=>['eq',0]])->field('time_limit,need_num,team_price,goods_id')->find();
		//商品限制 商品被开团的次数
		$num = Db::name("team_found")->where('team_id',$team_id)->select();
		$num =  count($num);
		//活动限制
		$nums = Db::name("active_type")->where('id',2)->field('limit_num')->find();
		if($num >= $nums){
			return $this->json('', -2, '此商品到达开团上限');
		}
		if(!$team){
			return $this->json('', 0, '未知参数');
		}
		$goods = Db::name("goods")->where(['goods_id'=>$team['goods_id']])->field('vip_price,show_price,price')->find();
		if(!$team['time_limit']){
			$team['time_limit'] = 2;
		}
		$data = [
			'user_id' => $uid,
			'team_id' => $team_id,
			'nick_name' => $users['user_name'],
			'order_id' => $order_id,//支付成功 订单id
			'start_time' => time(),//成团时间
			'end_time' => time()+ ($team['time_limit']*60),//结束时间
			'joins' => 1,//已经参团人数
			'need' =>  $team['need_num'],//需要人数
			'goods_price' =>  $goods['price'],//拼团价格
			'price' =>  $team['team_price'],//拼团价格
			'status' => 1,//0:待开团 1：已开团 2：拼团成功 3:拼团失败
		 
		 ];
		 $res = Db::name("team_found")->insert($data);
		 //插入到参团表
		 $found_id = Db::name("team_found")->getLastInsID();
		 if($res){
			 $data = [
				'user_id' => $uid,//用户id
				'team_id' => $team_id,//拼团id
				'nick_name' => $users['user_name'],
				'order_id' => $order_id,//订单支付成功 id
				'join_time' => time(),//参团时间
				'found_id' => $found_id, //开团id值
				'found_user_id' => $uid,//开团用户id值
				'status' => 1,//1：拼单成 2：成团成功 3：成团失败
			 ];		 
			 $res = Db::name("team_follow")->insert($data);
		 }

		 if(!$res){
			   return $this->json('', 0, '开团失败');
		 }
		 return $this->json('', 1, '开团成功');
	}
	/**
     *  开团详情 （商品共有几个拼团信息）
     */
	function teamDetails(){
		$goods_id = input('request.goods_id');//商品id
		
		$list = Db::name("team_activity")->where(['goods_id'=>['eq',$goods_id]])->field('id,team_price,need_num')->select();
		 
		$goods = Db::name("goods")->where(['goods_id'=>['eq',$goods_id]])->field('price,vip_price,show_price')->find();
	 
		if(!$list){
			return $this->json('', 0, '暂无开团信息');
		}
		foreach($list as $key=>$val){
			
			$number = 0;
			$where =[
				'team_id'=>['eq',$val['id']],
				'status'=>['eq',1],//只查询有效团人数
			];
			$rows = Db::name('team_found')->where($where)->field('joins,status')->select();
			 foreach($rows as $value ){
					$number += $value['joins'];
			 }
			$list[$key]['joins'] = $number;
			$list[$key]['price'] =  $goods['price'];
			$list[$key]['vip_price'] =  $goods['vip_price'];
			$list[$key]['show_price'] =  $goods['show_price'];
			$list[$key]['active_price'] =  $val['team_price'];
		}
		return json(['data'=>$list,'status'=>1,'msg'=>'获取成功']);
	}
	
	/**
     *  开团信息  
     */
	function teamInfo(){
		$team_id = input('request.id');//拼团id
		$list = Db::name("team_found")->where(['team_id'=>['eq',$team_id],'status'=>1])->select();
		if(!$list){
			return $this->json('', 0, '暂无开团信息');
		}
		
		//超时 拼团失败 有定时执行 这里 注释
//		foreach($list as $key=>$val){
//			if($val['end_time']<=time()){
//				$data = ['status'=>3];
//				Db::name("team_found")->where(['id'=>['eq',$val['id']]])->update($data);
//			}
//		}
		$list = Db::name("team_found")->where(['team_id'=>['eq',$team_id],'status'=>1])->select();
		if(!$list){
			return $this->json('', 0, '暂无开团信息');
		}
		$number = 0;
		foreach($list as $key=>$val){
			$res = Db::name('users')->where(['user_id'=>['eq',$val['user_id']]])->field('user_avat')->find();
			$team = Db::name('team_activity')->where(['id'=>['eq',$val['team_id']]])->field('goods_id,goods_id,price_reduce,price_type,team_price')->find();
			
			$goods = Db::name('goods')->where(['goods_id'=>['eq',$team['goods_id']]])->field('price,vip_price,show_price')->find();

			$number += $val['joins'];
			$list[$key]['user_avat'] = $res['user_avat'];
			// $list[$key]['last_time'] = date('H:i:s',($val['end_time'] - $val['start_time'])); 
			$list[$key]['vip_price'] =  $goods['vip_price'];
			$list[$key]['price'] =  $goods['price'];
			//0:减价 1：折扣
			if($team['price_type'] == 0 ){
				$active_price = $goods['price'] - $team['price_reduce'];
			}else{	
				$active_price = ($goods['price'] * $team['price_reduce'])/100;	
			}
            $list[$key]['start_time'] = date('Y-m-d H:i',$val['start_time']);
            $list[$key]['end_time'] = date('Y-m-d H:i',$val['end_time']) ;
			//减价过大
			if($active_price<0){
				$active_price = 0;
			}	
			$list[$key]['active_price'] =  $active_price;//商品活动价格
		}
		return json(['data'=>$list,'status'=>1,'msg'=>'获取成功','number'=>$number]);
	}
	 /**
     *  参团 
     */
	function  joinTeam(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$found_id = input('request.id');//开团id
		$order_id = input('request.order_id');//开团id
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$where = [
			'id' => $found_id,//开团id
			'status' => 1,//开团0:待开团 1：已开团 2：拼团成功 3:拼团失败
		];
		$res = Db::name("team_found")->where($where)->find();	
		
		$wheres = [
			'user_id' => $user_id,//用户id
			'found_id' => $found_id ,//开团id值
		];
		$result = Db::name("team_follow")->where($wheres)->find();
		if($result){
			 return $this->json('', -4, '已经参加过了');
		}
	 
		if(!$res){
			   return $this->json('', -3, '此团不存在或者已经结束');
		}
		if($res['joins']>=$res['need']){
			 return $this->json('', -1, '开团人数已经完成');	
		}
		//不能参加自己发起的团
		if($uid == $res['user_id']){
			 return $this->json('', -2, '不能参加自己发起的团');	
		}
		$users = Db::name("users")->where(['user_id'=>$uid])->field("user_name")->find();
		$data = [
			'user_id' => $uid,//用户id
			'team_id' => $res['team_id'],//拼团id
			'nick_name' => $users['user_name'],
			'order_id' => $order_id,//订单支付成功 id
			'join_time' => time(),//参团时间
			'found_id' => $found_id, //开团id值
			'found_user_id' => $res['user_id'],//开团用户id值
			'status' => 1,//1：拼单成 2：成团成功 3：成团失败
		 
		 ];
		 $res = Db::name("team_follow")->insert($data);
		 Db::name('team_found')->where(array('id' => $found_id))->setInc('joins',1);
		 //参团人数达标 未超时 开团成功
		 $row =  Db::name('team_found')->where(array('id' => $found_id))->find();
		 if($row['joins']>=$row['need']){
			 $data = [
				'status'=>2
			 ];
			  Db::name('team_found')->where(array('id' => $found_id))->update($data);
		 }
		 if(!$res){
			   return $this->json('', 0, '参团失败');
		 }
		 return $this->json('', 1, '参团成功');
	}
	/**
     *  创建砍价
     * 1、判断 砍价商品是否 已发起砍价-》已发起砍价 则直接 进入砍价详情页面
     * 2、没有发起砍价 -》 创建砍价-》自己先砍
     */
	function openBargain(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$bargain_id = input('request.bargain_id');//砍价表id
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}

        $bargain = Db::name("bargain")->where(['id'=>['eq',$bargain_id],'status'=>['eq',0]])->field('id,join_number,goods_price,goods_name,goods_id,time_limit,end_price,sku_id,goods_number')->find();
        $shen_num=Db::name("active_type")->where(['id'=>4,'status'=>['eq',0]])->find();
        $cart=Db::name("cart")->where(['user_id'=>$user_id,'goods_id'=>$bargain['goods_id']])->find();
        $order=Db::name("order_goods")->where(['og_uid'=>$user_id,'og_goods_id'=>$bargain['goods_id'],'og_acti_id'=>4])->column('og_goods_num');
        $order_num=array_sum($order);
        if($order_num+$cart['num']>=$shen_num['limit_num']){
           return $this->json('',-1, '砍价商品已到购买上限'); 
        }
        if($order)
        //砍价商品是否存在
        if($bargain['goods_number']<=0){
            return $this->json('',-1, '库存不足');
        }

		$where = [
			'user_id' => $uid,//用户id
			'bargain_id' => $bargain_id,//砍价表id
            'status' => 0
		];
        //开始
		$res = Db::name("bargain_user")->where($where)->find();
        if($res && $cart){
            return $this->json($res, 1, '已发起过砍价');

        }
        // 如果 砍价表存在 购物车 没有数据 则是 数据异常  修改砍价状态 并且 重新发起砍价
        if ($res && !$cart) {
            Db::name("bargain_user")->where(['id' => $res['id']])->update(['status' => 3]);
        }
        // 砍价 已完成 或 超时自动结束 可以重新 发起砍价
        //已完成
        $ress=Db::name("bargain_user")->where(['user_id'=>$uid,'bargain_id'=>$bargain_id,'status'=>1])->order('id desc')->find();
        //砍价完成
        if($ress){
            //订单表查询
            $order_goods=Db::name('order_goods')->alias('a')->join('order b','b.order_id=a.og_order_id')->field('a.og_order_id')->where(['a.og_acti_id'=>4,'a.og_uid'=>$uid,'a.og_goods_id'=>$bargain['goods_id'],'b.order_status'=>['eq',0]])->find();
            if($order_goods){
                return $this->json($order_goods, -2, '订单商品未支付');
            }
//            //购物车表查询
            $cart=Db::name('cart')->where(['prom_id'=>4,'user_id'=>$uid,'goods_id'=>$bargain['goods_id']])->find();
            if($cart){
                $cart_info = Db::name("bargain_user")->where(['id' => $cart['active_id']])->find();
                return $this->json($cart_info, 1, '已发起过砍价');
            }
        }

//        //已结束
//        $resss=Db::name("bargain_user")->where(['user_id'=>$uid,'bargain_id'=>$bargain_id,'status'=>2])->find();
//
//
//        //砍价结束
//        if($resss){
//             return $this->json($res, -3, '砍价已结束');
//        }
		$users = Db::name('users')->where(['user_id'=>$uid])->field('user_name,user_avat')->find();

		//头像不能为空
		if(!$users['user_avat']){
			$users['user_avat'] = 0;
		}
		if(!$users['user_name']){
			$users['user_name'] = 0;
		}
		//砍价随机数
		$sku = Db::name('goods_sku')->where(['sku_id'=>$bargain['sku_id']])->find();
		$kan_price = ($sku['price'] - $bargain['end_price']);
		$bargain_price = $this->randMoney($kan_price,$bargain['join_number']);
		$bargain_price =  json_encode($bargain_price);

		// 添加 失败 回滚
        Db::startTrans();
        $data = [
            'user_id' => $uid,
            'bargain_id' => $bargain_id,
            'nick_name' => $users['user_name'],//昵称
            'head_img' => $users['user_avat'],//头像
            'start_time' => time(),//砍价开始时间
            'end_time' => time()+ (24*60*60),//结束时间
            'continue_price' =>  abs($bargain['goods_price'] - $bargain['end_price']),//还需要砍价
            'has_price' =>  0,//已经砍价
            'status' => 0,//0:开始砍价 1:完成 2：结束砍价
            'share_number' => 0,//分享人数
            'bargain_price' =>$bargain_price,//砍价随机数组

        ];
        $res = Db::name('bargain_user')->insert($data);
        if(!$res){
            Db::rollback();
            return $this->json('', 0, '开砍失败');
        }
        $id = Db::name('bargain_user')->getLastInsID();

        //加入购物车
        $CartModel=new CartService();
        $result = $CartModel->addData($bargain['sku_id'],$bargain['goods_id'], $user_id, $num=1, $type=4,0,$id);
        if(!$result){
            Db::rollback();
            return $this->json('', -1, '开砍加购物车失败');
        }
        if ($res && $result) {
            Db::commit();
        }

		 //自己先砍 一刀
		 $this->joinkanjiia($uid,$id,$bargain_id);
        $info = Db::name('bargain_user')->where(['id' => $id])->find();
		 return $this->json($info, 1, '开砍成功');
	}
			
	/**
     *  砍价随机数
     */
	function randMoney($sum,$count){
        $arr = [];
        $hes = 0;
        $hess =0;
        for ($i=0;$i<$count;$i++){
            $rand =rand(1,1000);
            $arr[]=$rand;
            $hes+=$rand;
        }
        $arr2 =[];
        foreach ($arr as $key=>$value){
            $round = round(($value/$hes)*$sum,2);
            $arr2[] =$round;
            $hess+=$round;
        }
        if($sum !=round($hess,2)){
            $hesss =round($sum-$hess,2);
            $arr2[0]=$arr2[0]+$hesss;
        }
        return $arr2;
    }	
	/**
     *  先砍一刀
     */
	function joinkanjiia($uid,$bargain_user_id,$bargain_id){
		$where = [
			'user_id' => $uid,//用户id
			'bargain_user_id' => $bargain_user_id,//开砍价表id
		];
		//用户表
		$users = Db::name('users')->where(['user_id'=>$uid])->field('user_name,user_avat')->find();
		//开砍表
		$bargain_user = Db::name('bargain_user')->where(['id'=>$bargain_user_id, 'bargain_id' => $bargain_id])
            ->field('end_time,has_price,continue_price,share_number,share_money_cutprice,order_id,status,bargain_price')
            ->order('id desc')
            ->find();
		//砍价表
		$result = Db::name('bargain')->where(['id'=>$bargain_id])->field('join_number,goods_id')->find();
		$number = $result['join_number'];//总人数
		//帮好友砍掉的价格
		$bargain_price =  json_decode($bargain_user ['bargain_price']);
		$dis_price = array_shift($bargain_price);
		$bargain_price =  json_encode($bargain_price);
		Db::name('bargain_user')->where(['id'=>$bargain_user_id])->update(array('bargain_price'=>$bargain_price));
		

		$share_number = $bargain_user['share_number'];//已经砍人数
		$num  =  $number - $share_number;//剩余人数
		
		$money  = $bargain_user['continue_price'];//还需要 砍 钱数

		if(!$users['user_avat']){
			$users['user_avat'] = '';
		}
		if(!$users['user_name']){
			$users['user_name'] = '';
		}
		$data = [
			'user_id' => $uid,
			'bargain_id' => $bargain_id,//砍价表id
			'bargain_user_id' => $bargain_user_id,//开砍价表id
			'nick_name' => $users['user_name'],//昵称
			'head_img' => $users['user_avat'],//头像
			'join_time' => time(),//参加时间
			'dis_price' => $dis_price,//帮好友砍掉的价格
			'status' => 1,//1：成功 0：失败		 
		 ];
		 
		 if($bargain_user['continue_price']<$data['dis_price']){
			 $has_price = $bargain_user['continue_price'];
		 }else{
			 $has_price = $data['dis_price'];
		 }
		 $updata = [
			'has_price' => $bargain_user['has_price'] + $has_price,
			'continue_price' => $bargain_user['continue_price'] - $has_price,
			'share_number'=> $bargain_user['share_number'] + 1,
		 ];
		 //超时 
		 if($bargain_user['end_time'] < time()){
			 $updata ['status']  = 2;
		 }
		 //价格砍完 人数到了
		 if(($updata['continue_price'] <= 0)||($updata['share_number']>=$number)){
			  $updata ['status'] = 1;
              // $order_goods=Db::name('order_goods')->where(['og_acti_id'=>4,'og_uid'=>$uid,'og_goods_id'=>$result['goods_id'],'og_order_status'=>'gt 0'])->find();  
              // if(!$order_goods){
              //   return $this->json($bargain_user_id, 0, '砍价商品已砍到最低价,不付款不能再次参加此商品的砍价');
              // }
		 }
		 $res = Db::name('bargain_user')->where(['id'=>$bargain_user_id])->update($updata);
		 if($res){
			  $res = Db::name('bargain_follow')->insert($data);
		 }
		 //规则属性表
		$sku = Db::name('goods_sku')->where(['sku_id'=>$result['sku_id']])->field('sku_name')->find();	
		 //修改购物车 商品价格
		 $where = [
			'prom_id'=>4,
			'user_id'=>$uid,
			'goods_id'=>$result['goods_id'],
		 ];
	     Db::name('cart')->where($where)->setDec('price',$dis_price);
	}
	
	/**
     *  好友帮砍
     */
	function joinBargain(){
		$user_id = input('request.uid');
		$token = input('request.token');
		$bargain_user_id = input('request.id');//开砍价表id
		$bargain_id = input('request.bargain_id');//砍价表id
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$bargain_user_info = Db::name("bargain_user")->where(['id'=>$bargain_user_id])->find();
		if (empty($bargain_user_info)) {
            return $this->json('', -2, '砍价不存在');
        }

		if($bargain_user_info['status'] == 1 || $bargain_user_info['status'] == 2){
			   return $this->json('', -2, '砍价已完成');
		}
		$where = [
			'user_id' => $uid,//用户id
			'bargain_user_id' => $bargain_user_id,//开砍价表id
		];
		
		$res = Db::name("bargain_follow")->where($where)->find();
		if($res){
			   return $this->json('', -1, '已经砍过了');
		}  
		//用户表
		$users = Db::name('users')->where(['user_id'=>$uid])->field('user_name,user_avat')->find();
		//开砍表
		$bargain_user = Db::name('bargain_user')->where(['id'=>$bargain_user_id])->field('end_time,has_price,continue_price,share_number,share_money_cutprice,order_id,status,bargain_price,user_id')->find();
		//砍价表
		$result = Db::name('bargain')->where(['id'=>$bargain_id])->field('join_number,goods_id,sku_id')->find();
		$number = $result['join_number'];//总人数
		//帮好友砍掉的价格
		$bargain_price =  json_decode($bargain_user ['bargain_price']);
		$dis_price = array_shift($bargain_price);
		$bargain_price =  json_encode($bargain_price);
		Db::name('bargain_user')->where(['id'=>$bargain_user_id])->update(array('bargain_price'=>$bargain_price));
		
		if(!$bargain_user){
			return $this->json('', 0, '未知参数');
		}
		
		if($bargain_user['share_number']>=$number){
			return $this->json('', -2, '人数已经达标不能砍价');
		}
		
		$share_number = $bargain_user['share_number'];//已经砍人数
		$num  =  $number - $share_number;//剩余人数
		
		$money  = $bargain_user['continue_price'];//还需要 砍 钱数
		
       /*  if($num>1){
            $min=0.01;     //每个人最少能收到0.01元
            $safe_total = ($money  - ($num-1) * $min)/($num-1);//随机安全上限
            $total = mt_rand($min * 100, $safe_total * 100) / 100;
            $dis_price = sprintf("%.2f", $total);   //砍掉的金额
        }else{
            $dis_price = $money;
        } */
		// alter table ht_bargain add `bargain_price` text COMMENT '砍价随机数组';
		// alter table ht_bargain_user add `bargain_price` text COMMENT '砍价随机数组';
		if(!$users['user_avat']){
			$users['user_avat'] = '';
		}
		if(!$users['user_name']){
			$users['user_name'] = '';
		}
		$data = [
			'user_id' => $uid,
			'bargain_id' => $bargain_id,//砍价表id
			'bargain_user_id' => $bargain_user_id,//开砍价表id
			'nick_name' => $users['user_name'],//昵称
			'head_img' => $users['user_avat'],//头像
			'join_time' => time(),//参加时间
			'dis_price' => $dis_price,//帮好友砍掉的价格
			'status' => 1,//1：成功 0：失败		 
		 ];
		 
		 if($bargain_user['continue_price']<$data['dis_price']){
			 $has_price = $bargain_user['continue_price'];
		 }else{
			 $has_price = $data['dis_price'];
		 }
		 $updata = [
			'has_price' => $bargain_user['has_price'] + $has_price,
			'continue_price' => $bargain_user['continue_price'] - $has_price,
			'share_number'=> $bargain_user['share_number'] + 1,
		 ];
		 //超时 
		 if($bargain_user['end_time'] < time()){
			 $updata ['status']  = 2;
		 }
		 //价格砍完 人数到了
		 if(($updata['continue_price'] <= 0)||($updata['share_number']>=$number)){
			  $updata ['status'] = 1;
		 }
		 
		 $res = Db::name('bargain_user')->where(['id'=>$bargain_user_id])->update($updata);
		 if($res){
			  $res = Db::name('bargain_follow')->insert($data);
		 }
		 //规则属性表
		$sku = Db::name('goods_sku')->where(['sku_id'=>$result['sku_id']])->field('sku_name,price')->find();	
		  //修改购物车 商品价格
		 $where = [
			'prom_id'=>4,
			'user_id'=>$bargain_user['user_id'],
			'goods_id'=>$result['goods_id'],
		 ];
	     Db::name('cart')->where($where)->setDec('price',$dis_price);
		 if(!$res){
			   return $this->json('', 0, '砍价失败');
		 } else {
             $usersInfo = Db::name('users')->where('user_id',$bargain_user_info['user_id'])->field('client_id,app_system')->find();
             if($usersInfo){
                 if ($bargain_user_info['user_id'] != $uid) {
                     $msg = [
                         'content'=>$users['user_name'].' 已帮您砍价',//透传内容
                         'title'=>'砍价通知',//通知栏标题
                         'text'=>$users['user_name'].' 已帮您砍价',//通知栏内容
                     ];
                     $clientids=array(
                         ['client_id'=>$usersInfo['client_id']],
                         'system'=>$usersInfo['app_system'],
                     );
                     $Pushs = new Pushs();
                     $Pushs->getTypes($msg,$clientids);
                 }
                 // 砍价 完成 发送通知
                 if ($updata ['status'] == 1) {
                     $msg = [
                         'content'=>'砍价已完成，请尽快购买',//透传内容
                         'title'=>'砍价通知',//通知栏标题
                         'text'=>' 砍价已完成，请尽快购买',//通知栏内容
                     ];
                     $clientids=array(
                         ['client_id'=>$usersInfo['client_id']],
                         'system'=>$usersInfo['app_system'],
                     );
                     $Pushs = new Pushs();
                     $Pushs->getTypes($msg,$clientids);
                 }
             }

             return $this->json('', 1, '砍价成功');
         }
	}
	
	/**
     *  砍价详情 
     */
	function bargainInfo(){
//		$user_id = input('request.uid');
//		$token = input('request.token');
		$bargain_id = input('bargain_id');//砍价表id
//		$uid = $this->getUid($token, $user_id);
//		if(!$uid){
//			return $this->json('', 0, '未知参数');
//		}


		 

		//开砍表
		$bargain_user = Db::name('bargain_user')->where(['id'=>$bargain_id, 'status' => ['in', '0,1']])->find();
        if(!$bargain_user){
            return $this->json('', 0, '该次砍价已失效，请重新发起砍价');
        }
        //砍价表
        $bargain = Db::name('bargain')->where(['id'=>$bargain_user['bargain_id'],'status'=>0])->find();
		
		//商品表
		$goods = Db::name('goods')->where(['goods_id'=>$bargain['goods_id']])->field('price,vip_price,show_price,picture,goods_name')->find();	
		
		
		//规则属性表
		$sku = Db::name('goods_sku')->where(['sku_id'=>$bargain['sku_id']])->field('sku_name')->find();

        //用户表
        $users = Db::name('users')->where(['user_id'=>$bargain_user['user_id']])->field('user_name,user_avat')->find();


        //帮砍价表
		$where = [
			'bargain_user_id'=>$bargain_user['id'],
			'bargain_id'=>$bargain_user['bargain_id'],
			'status'=>1,
		];
		$list = Db::name('bargain_follow')->where($where)->field('user_id,join_time,nick_name,head_img,dis_price')->order('id desc')->select();

		if($list){
			foreach($list as $key=>$val){
				
				$list[$key]['join_time'] = date('Y-m-d H:i:s',$val['join_time']);
			}	
		}
		// 查询购物车id
        $cart_where  = [
            'goods_id' => $bargain['goods_id'],
            'user_id' => $bargain_user['user_id'],
            'active_id' => $bargain_user['id']
        ];
		$cart_id = Db::name('cart')->where($cart_where)->find();
        if(!$cart_id){
            return $this->json('', 0, '该次砍价已失效，请重新发起砍价');
        }
        //var_dump($cart_id);die;
		$data =[
			'user_name' => $users['user_name'],//用户名
			'user_avat' => $users['user_avat'],//用户头像
			// 'goods_price' => $bargain['goods_price'],//原价
			'price' => $bargain['goods_price'],//原价
			'show_price' => $goods['show_price'],//展示价格
			'vip_price' => $goods['vip_price'],//vip价格
			'end_price' => $bargain['end_price'],//最低砍价
			'has_price' => $bargain_user['has_price'],//已经砍价
			'now_price' => ($bargain['goods_price']-$bargain_user['has_price']),//现价
			'goods_name' => $goods['goods_name'],//名称
			'picture' => $goods['picture'],//图片
            'goods_id' => $bargain['goods_id'],
            'cart_id' => $cart_id['id'],
            'sku_id' => $bargain['sku_id'],
			'continue_price' =>  (abs($bargain['goods_price'] - $bargain['end_price'])-$bargain_user['has_price']),//还需砍价
			'sku_name' => $sku['sku_name'],//规格
			'bargain_id' => $bargain_user['bargain_id'],//砍价表 id 
			'id' => $bargain_user['id'],//用户开砍 id 
			'list' => $list,//帮砍好友列表 
			
		];
		
		return $this->json($data);
	}
	
}



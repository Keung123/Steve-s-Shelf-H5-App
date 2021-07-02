<?php

namespace app\api\controller;

use app\common\model\Goods as GoodsModel;
use app\common\service\User as UserService;
use app\common\service\Config;
use think\Db;
use think\Request;

class Integral extends Common{
    protected $goodsModel;
    protected $userService;
    protected $configService;
    protected $request;
    public function __construct(){
        parent::__construct();
        $this->goodsModel = new GoodsModel();
        $this->userService = new UserService();
        $this->configService = new Config();
    }
    /**
     * 积分商城的列表信息
     * @param string token 秘钥
     * @param int uid 用户id
     * @param int page 页数
     * @param int low 最低积分
     * @param int high 最高积分
     * @return json
     */
    public function pointsList()
    {
        if(Request()->isPost()){
            $this->checkEmploy(Request()->post(),['uid','token','page','low','high']);
            //判断用户的输入是否非法
            if(!$this->getUid(Request()->post("token"),intval(Request()->post("uid")))){
                return json(['status'=>1,'msg'=>'非法操作！','data'=>[]]);
            }
            $page=intval(Request()->post("page"))?intval(Request()->post("page")):1;
            $where['status']=0;
            $where['stock']=['<>',0];
            $sort="goods_id desc";
            //获得用户的基本信息
            $userInfo=$this->userService->userInfo(['uid'=>Request()->post("uid")],"uid,user_points");
			$low=Request()->post("low/d");
			$high=Request()->post("high/d");
			$where_condition =['>',0];
			$condition=['>',0];
            if(isset($low) && isset($high) ){
				$where_condition= array('between',$low.','.$high);
            }
            if(Request()->post("sort/d")){
                $sortPost=Request()->post("sort/d");
                if($sortPost==1){
                    $sort="exchange_integral desc";
                }else{
                    $sort="exchange_integral asc";
                }
            }
            //根据会员积分获得可以兑换的商品
            $goodsList=$this->goodsModel
                ->field('goods_id,goods_name,picture,exchange_integral,goods_type')
                ->where($where)
                ->where('exchange_integral',$condition,$where_condition,'and')
                ->order($sort)
                ->page($page,10)
                ->select();
            if($goodsList){
                $count=$this->goodsModel->where($where)->where('exchange_integral',$condition,$where_condition,'and')->count('goods_id');
                $totalPage=ceil($count/10);
                return json(['status'=>1,'msg'=>'数据信息','data'=>['goods_list'=>$goodsList,'totalPage'=>$totalPage]]);
            }else{
                return json(['status'=>-1,'msg'=>'暂无数据信息','data'=>[]]);
            }
        }
    }

    /**
     * discrption: 积分商品详情
     * @param int goods_id 商品id
     * @return json
     */
    public function pointsDetail()
    {
        if(Request()->post()){
            //积分商品id值
            $this->checkEmploy(Request()->post(),['goods_id']);
            $goodsId=Request()->post("goods_id/d");
            $uid=Request()->post("uid");
            $token=Request()->post("token");
            $goodsDetail=$this->goodsModel
                ->field("goods_id,exchange_integral,stock,picture,goods_name,images,description,spec,introduction,spec_array,subject_values")
                ->where(['goods_id'=>$goodsId,'status'=>0,'stock'=>['gt',0]])
                ->find();
            if($goodsDetail){
                //对相册进行操作
                $goodsDetail['subject_values']=json_decode($goodsDetail['subject_values']);
                $goodsDetail['description']=explode(",",rtrim($goodsDetail['description'],","));
                //读取商品规格信息
                //读取前端的对应数据信息
                $goodsSku=Db::name("goods_sku")->where(['goods_id'=>$goodsDetail['goods_id']])
                    ->field("sku_id,attr_value,attr_value_array,price,stock")->select();
                $goodsAttr=[];
                if($goodsSku){

                    foreach($goodsSku as $k=>$v){
                        $attrValue=explode(";",$v['attr_value']);
                        if(count($attrValue)==1){
                            $goodsAttr[str_replace(":","_",$attrValue[0])]=['price'=>$goodsDetail['exchange_integral'],'stock'=>$v['stock'],'skuId'=>$v['sku_id']];
                        }else{
                            $attrHtml='';
                            foreach($attrValue as $kk=>$vv){
                                $attrHtml.= str_replace(":","_",$vv)."_";
                            }

                            $goodsAttr[rtrim($attrHtml,"_")]=['price'=>$goodsDetail['exchange_integral'],'stock'=>$v['stock'],'skuId'=>$v['sku_id']];
                        }

                    }
                }
				$map=[
					'f_uid'=>$uid,
					'favor_type'=>1,
					'f_goods_id'=>$goodsId,
				];
				$res = Db::name('favorite')->where($map)->find();
				$goodsDetail['is_favor'] = 0;
				if($res){
					$goodsDetail['is_favor'] = 1;
				}
                $goodsDetail['goods_attr']=$goodsAttr;
                //查看商品评价信息
                $goodsComment=$this->goodsComment($goodsId,1,2);
                $goodsDetail['goods_comment']=$goodsComment;
                //评价数量
                $goodsDetail['goods_comment_number']=$this->goodsCommentCount($goodsId);
                //购买须知
                $content=Db::name("content")->where("content_id",1)->value("content");
                $webUrl=$_SERVER["HTTP_HOST"];
                $goodsDetail['need_rule']=str_replace('/uploads/',$webUrl.'/uploads/',$content);
                return json(['status'=>1,'msg'=>'数据信息','data'=>$goodsDetail]);

            }
            return json(['status'=>-1,'msg'=>'该商品已经下架或者库存不足','data'=>[]]);
        }
    }

    /**
     * 评价列表
     * @param $goodsId
     * @param $page
     * @return json
     */
    public function goodsComment()
    {
        $goodsId = input("request.goodsId");
        $page = input("request.page");
        $limit = $limit ?: 10;
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
            return json(['status'=>1,'msg'=>'数据信息','data'=>$data]);
        }
        return json(['status'=>-1,'msg'=>'暂无数据','data'=>'']);
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
      * 立即兑换商品
      * @param string token
      * @param integer uid 用户id
      * @param integer goodsId
      * @param integer number 购买数量
      * @param integer sku_id 属性id
      * @param integer addrId 收货地址id
      * @return json
      */
    public function rightNow()
    {
        if(Request()->isPost()){
            $data=Request()->post();
            $this->checkEmploy($data,['token','uid','goodsId','number']);
            $goodsId=Request()->post("goodsId/d");
            //判断用户是否存在
            if(!$this->getUid(Request()->post("token"),Request()->post("uid/d"))){
                return json(['status'=>-1,'msg'=>'非法操作','data'=>[]]);
            }
            $number=Request()->post("number/d");
            //获得用户的积分信息
            $userInfo=$this->userService->userInfo(['user_id'=>Request()->post("uid/d")],"user_id,user_points");
             //查看该商品的积分
            $goodsIntergal=$this->goodsIntegral(['goods_id'=>$goodsId,'stock'=>['gt',0],'status'=>0]);
            $totalIntegral=($goodsIntergal['exchange_integral']*$number);
            if($userInfo['user_points']<$totalIntegral){
                return json(['status'=>-1,'msg'=>'您的积分不足！','data'=>[]]);exit;
            }
            $stock=$goodsIntergal['stock'];
            if(empty($goodsIntergal)){
                return json(['status'=>-1,'msg'=>'该商品已经下架或者兑换积分活动已经结束！','data'=>[]]);
            }
            $goodsIntergal['total_integral']=sprintf("%.2f",$totalIntegral);
            $goodsIntergal['exchange_integral']=sprintf("%.2f",$goodsIntergal['exchange_integral']);
            $goodsIntergal['goods_number']=$number;
            $specValue='';
            //判断是否存在属性
            if(Request()->post("sku_id/d")){

                $where['sku_id']=Request()->post("sku_id/d");
                $where['goods_id']=$goodsId;
                $goodsAttr=$this->goodsSpecValue($where);
                $specValue=$goodsAttr['specValue'];
                $stock=$goodsAttr['stock'];
                $goodsIntergal['sku_id']=$goodsAttr['sku_id'];
            }
            $goodsIntergal['spec_value']=$specValue;
            if($stock<Request()->post("number")){
                return json(['status'=>-1,'msg'=>'库存不足！','data'=>[]]);
            }
            //获得收货地址信息
            if(Request()->post("address_id")){
                $whereAddress['addr_id']=Request()->post("addrId");
                $whereAddress['a_uid']=Request()->post("uid/d");
            }else{
                $whereAddress['a_uid']=Request()->post("uid/d");
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
            $address['addr_area'] = '';
            $address['addr_cont']=$proName.$cityName.$areaName.$address['addr_cont'];
            //获得运费
            return json(['status'=>1,'msg'=>'数据信息','data'=>['goods_info'=>$goodsIntergal,'address'=>$address]]);
        }
        return json(['status'=>-1,'msg'=>'非法操作！','data'=>[]]);
    }

    /**
     * @param $where
     * 获得商品的属性和属性值
     */
    function goodsSpecValue($where){
        //查看该属性的信息
        $attr=Db::name("goods_sku")->where($where)
            ->field("sku_id,stock,attr_value")
            ->find();
        $specValue='';
        //根据规格值获得对应的汉字
        $attrValue=explode(";",rtrim($attr['attr_value'],";"));
        $goodsSpec=Db::name("goods_spec");
        $goodsSpecValue=Db::name("goods_spec_value");
        foreach($attrValue as $key=>$value){
            $attrIdVal=explode(":",$value);
            $goodsSpecName=$goodsSpec->where(['spec_id'=>$attrIdVal[0]])->value("spec_name");
            $goodsSpecValueName=$goodsSpecValue->where(['spec_value_id'=>$attrIdVal[1]])->value("spec_value_name");
            $specValue.=$goodsSpecName.":".$goodsSpecValueName." ";
        }
        $specValue=rtrim($specValue," ");
        return ['specValue'=>$specValue,'stock'=>$attr['stock'],'sku_id'=>$attr['sku_id']];
    }

    /**
     * @param $where
     * 获得商品的单条信息
     */
   function goodsIntegral($where){
       $goodsIntergal=$this->goodsModel
           ->field("goods_id,goods_name,picture,stock,exchange_integral,is_free_shipping,freight,supplier_id")
           ->where($where)
           ->find();
       return $goodsIntergal;
   }
   /**
    * description:积分商城的文章信息
    * @param int type 6:积分规则 7：积分游戏规则
    * @return Json
    */
   public function integralRule(){
       if(Request()->isPost()){
           $this->checkEmploy(Request()->post(),['type']);
           $type = Request()->post("type");
           $content=Db::name("content")->where(['category_id'=>$type])->value("content");
           $webUrl=$_SERVER["HTTP_HOST"];
           $content=str_replace('/uploads/',$webUrl.'/uploads/',$content);
           return json(['status'=>1,'msg'=>'数据信息','data'=>['content'=>$content]]);
       }
   }
   /**
    * description:积分商城 我的积分 头部显示
    * @param int uid 用户id
    * @param string token
    * @return json
    */
   public function myIntegral()
   {
       if(Request()->isPost()){
           $this->checkEmploy(Request()->post(),['uid','token']);
		   $user_id = Request()->post("uid");
            //判断用户的输入是否非法
            if(!$this->getUid(Request()->post("token"),intval(Request()->post("uid")))){
                return json(['status'=>1,'msg'=>'非法操作！','data'=>[]]);
            }
           $res = Db::name("users")->where(['user_id'=>$user_id])->field("user_name,user_avat,user_mobile as phone,user_points,is_seller")->find();
            //积分折换金额
           $point = Db::name('config')->field('setjifen')->find();
           $point = json_decode($point['setjifen'],true);
           if ($point['status'] == 0) {
               $money = (float)sprintf("%1\$.2f",$res['user_points']/$point['number']);
           } else {
               $money = '';
           }
		  if($res){
			  $res ['is_seller'] = $res ['is_seller'] ==0 ?'会员':'店主';
			  $res ['money'] = $money;
           return json(['status'=>1,'msg'=>'数据信息','data'=>['content'=>$res]]); 
		  }
		  return json(['status'=>-1,'msg'=>'暂无数据信息','data'=>[]]);
       }
   }

   /**
    * 积分商城 幸运转盘
    * @param int uid
    * @param string token
    * @return json
    */
   public function gameRotary(){
		$where = [
			'status'=>0,
			'type'=>1,//幸运转盘
		];
		$type=[
			'1'=>'转盘抽奖',
			'2'=>'砸金蛋活动'
		];
		$res = Db::name("lucky_activity")->where($where)->field('title,type,points,lucky_image')->find();
		if($res){
			 $res ['name'] = $type[$res['type']];
			 $list =   Db::name("lucky")->where('type',1)->select();
			 if($list){
				 $res['prize'] =  $list;
			 }
			 return json(['status'=>1,'msg'=>'数据信息','data'=>$res]); 
		}
	  return json(['status'=>-1,'msg'=>'暂无数据信息','data'=>[]]);

   }
   /**
    * 积分商城 砸金蛋活动
    * @param int uid
    * @param string token
    * @return json
    */
   public function gameEggs(){
		$where = [
			'status'=>0,
			'type'=>2,//砸金蛋活动
		];
		$type=[
			'1'=>'转盘抽奖',
			'2'=>'砸金蛋活动'
		];
		$res = Db::name("lucky_activity")->where($where)->field('title,type,points,lucky_image')->find();
		if($res){
			 $res ['name'] = $type[$res['type']];
			 $list =  Db::name("lucky")->where('type',2)->select();
			 //随机排序
			 shuffle($list);
			 if($list){
				 $res['prize'] =  $list;
			 }
			 return json(['status'=>1,'msg'=>'数据信息','data'=>$res]); 
		}
	  return json(['status'=>-1,'msg'=>'暂无数据信息','data'=>[]]);
 
   }
     /**
     * 生成优惠券编号
     */
    public function createCouponNo(){
        $no = 'YH'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $check = Db::name('coupon_users')->where('c_no', $no)->field('c_id')->find();
        while($check){
            $no = $this->createCouponNo();
        }
        return $no;
    }   
   /**
    * 积分商城 砸金蛋 转盘抽奖
    * @param int uid
    * @param string token
    * @param int type 1：幸运转盘 2：砸金蛋
    * @return json
    */
   public function gameGift()
   {
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $user_id ;
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
        $type = intval(input('type'));//1：幸运转盘 2：砸金蛋

        //减少用户积分
        $where = [
            'type'=>$type
        ];
        $row = Db::name("lucky_activity")->where($where)->find();

        if($row){
             $res = Db::name('users')->where(array('user_id' => $uid))->setDec('user_points', $row['points']);
             $point = [
                'p_uid'=>$user_id,
                'point_num'=>-$row['points'],
                'point_add_time'=>time(),
             ];
             if(input('type') == 1){
                $point['point_type'] = -3;
                $point['point_desc'] = '幸运转盘付费';
             }else{
                $point['point_type'] = -2;
                $point['point_desc'] = '砸金蛋游戏付费';
             }

             Db::name('points_log')->insert($point);
        }

        $list = Db::name('lucky')->where('type',$type)->select();
        foreach ($list as $key => $val) {
            $arr[$val['id']] = $val['rate'];
        }
        $rid = $this->get_rand($arr);
        $data = [];
        foreach($list as $key=>$val){
            if($rid == $val['id']){
                $data = $val;
                $data['number'] = $key+1;
                $data['gift_info'] = Db::name('lucky_gift')->where('id',$val['gift_id'])->find();

            }
        }
        if($data){
            if($data['gift_info']){
                $data['gift_info']['jifen']=1;
                $dd = $this->getGift($uid,$type,$data['gift_info']);
            }
             return json(['status'=>1,'msg'=>'获奖成功','data'=>$data]);
        }
        return json(['status'=>0,'msg'=>'获奖失败','data'=>'获奖失败']);
   }
    /**
    * 积分商城 游戏前判断
     * @param int uid
     * @param string token
     * @param int type 游戏类型 1：转盘抽奖 2：砸金蛋活动
     * @return json
    */
   public function gameJudge()
   {
		$token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $this->getUid($token, $user_id);
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
		$type = input('type'); 
		$res = Db::name("lucky_activity")->where('type',$type)->field('points')->find();
		$users = Db::name('users')->where(array('user_id' => $uid))->field('user_points')->find();
		if($users['user_points']<$res['points']){
			 return json(['status'=>0,'msg'=>'积分不足','data'=>'']);
		}
		return json(['status'=>1,'msg'=>'可以开始游戏','data'=>'']);
   }
   /**
    * description:积分商城 分段
    * @return json
    */
	 public function splitIntegral()
     {
		 $config=$this->configService->find();
		 $express = json_decode($config['exchange'], true);
		 $data = [];
		 foreach($express['field'] as $key=>$val){
			 $data[$key]['low']= $val;
			 $data[$key]['high']=$express['value'][$key];
		 }
		 if($data){
			  return json(['status'=>1,'msg'=>'获取积分段','data'=>$data]);
		 }else{
			 return json(['status'=>0,'msg'=>'暂无积分段','data'=>'']);
		 }
	 }
    /**
    * 积分商城 获取游戏奖品
    */
	 public function getGift($user_id,$type,$res)
     {
			//1：送积分 2：送礼物 3:送元宝
			$desc_list = [
				'1'=>'转盘抽奖奖励',
				'2'=>'砸金蛋活动奖励',
 			];
		 
			//积分添加
			if($res['type'] == 1){
				$point_type = $type == 1?'-2':'-3';
                if($res['jifen']==1){

                    $point_type_name=$res['name'];
                }else{
                    $point_type_name= $desc_list[$point_type];
                }
				 $log_data = array(
					'p_uid' => $user_id,
					'point_num' => $res['worth'],
					'point_type' => $point_type,
					'point_desc' => $point_type_name,//-2，抽奖；-3，砸金蛋
					'point_add_time' => time()
				);
				 $log = Db::name('points_log')->insert($log_data);
				 $res = Db::name('users')->where(array('user_id' => $user_id))->setInc('user_points', $res['worth']);
				 
			//元宝添加
			}else if($res['type'] == 3){
				  $end_time = time()+ 7*24*3600;
				$yin = [
					'yin_no'=>'',//元宝编号
					'yin_uid'=>$user_id,//会员id
					'yin_amount'=>$res['worth'],//元宝大小
					'yin_type'=>7, //元宝大小 7转盘抽奖 8砸金蛋
					'yin_desc'=>$desc_list[$type], //获取详细说明
					'yin_stat'=>2, // 元宝状态：1，未生效；2，未使用；3，已使用；4，已过期；5，已赠送
					'yin_add_time'=>time(), //元宝获取时间
					'yin_valid_time'=>7, //有效天数
					'yin_die_time'=>$end_time, //到期时间
				];
				//创建用户 元宝
				$result = Db::name('yinzi')->insert($yin);
				if($result){
					$yin_id = Db::name()->getLastInsID();
					$point_type = $type == 1?'-2':'-3';
					 $log_data = array(
						'y_log_uid' => $user_id,
						'y_log_yid' => $yin_id,//元宝id 暂时无值
						'y_log_desc' => $desc_list[$type],//-2，抽奖；-3，砸金蛋
						'y_log_addtime' => time()
					);
					$res = Db::name('yinzi_log')->insert($log_data);
				}	
			//送商品券 
			}else if($res['type'] == 2){
			   $couponInfo= Db::name('coupon')->where('coupon_id',$res['coup_id'])->find();
			  
			   if($couponInfo){
					$point_type = $type == 1?'-2':'-3';
					 $coupon_users = array(
						'coupon_id' => $couponInfo['coupon_id'],//优惠券id
						'c_coupon_title' => $couponInfo['coupon_title'],//优惠券id
						'c_uid' => $user_id,//优惠券所属用户
 						'add_time' => time(),//领取时间
 						'c_coupon_thumb' =>$couponInfo['coupon_thumb'],// 优惠券封面未写
 						'coupon_stat' => 1,//优惠券状态：1，未使用；2，已使用；3，已过期；4，已转赠；5，未购买
 						'c_coupon_type' => $couponInfo['coupon_type'],//代金券类型:1，商品券；2，专区券；3，全场券
 						'c_coupon_price' => $couponInfo['coupon_price'],//代金券面额
 						'c_coupon_buy_price' => $couponInfo['coupon_use_limit'],//代金券使用条件
 						'coupon_type_id' =>$couponInfo['coupon_type_id'],//商品券为商品id，专区卷为活动专区id'
 						'coupon_aval_time' => $couponInfo['coupon_aval_time'],//优惠券到期时间
 						'c_no' => $this->createCouponNo(),//优惠券编号（购买时填入）
					);
					$res = Db::name('coupon_users')->insert($coupon_users);
				}		
			}
			return $res;
	}
	public function get_rand($Arr) { 
		$id = ''; 
	    $proSum = array_sum($Arr); 
		foreach ($Arr as $key => $proCur) { 
		   $randNum = mt_rand(1, $proSum); 
			if ($randNum <= $proCur) { 
				$id = $key; 
			  break; 
			} else { 
			   $proSum -= $proCur; 
		  } 
		} 
		unset ($Arr); 
		return $id; 
	 }
    /**
	 * description:我的积分
     * @param int uid
     * @return json
	 */
    public function myPoints()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $points_info = $this->userService->userPoints($uid);
        return $this->json($points_info);
    }

    /**
	 * description:积分明细
     * @param int uid
     * @param int page
     * @param string month 月份 例如:2018-6
     * @param int type
     * @return json
	 */
    public function pointsDetails()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $type = input('request.type');
        $uid = $this->getUid($token, $user_id);

        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $p = input('request.page');
        $p = $p ? $p : 1;
        $month = input('request.month');
        $points_log = $this->userService->pointsLog($uid, $p, $month,$type);
        return $this->json($points_log);
    }
}



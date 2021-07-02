<?php
namespace app\api\controller;

use app\common\model\Goods as GoodsModel;
use app\common\service\ActiveGoods;
use app\common\service\User as UserService;
use app\common\service\Cart as CartService;
use app\common\service\GoodsActivity as GoodsActivityService;
use app\common\service\Goods as GoodsService;
use app\common\service\ActiveType;
use think\Db;
use think\Request;

class Activity extends Common{
    protected $goodsModel;
    protected $userService;
    protected $goodsActive;
    public function __construct(){
        parent::__construct();
        $this->goodsModel = new GoodsModel();
        $this->userSevice = new UserService();
        $this->goodsActive = new GoodsActivityService();
    }
    /**
     * 预售活动列表信息
     */
    public function activityList()
    {
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
                   //获得活动状态信息
                    $preSellList[$key]['act_status']=$this->goodsActive->activityStatus($preSellList[$key]);
                    //改活动的销量
                    $preSellCount=$this->goodsActive->preSellCount($preSellList[$key]['act_id'],$preSellList[$key]['goods_id']);
                    $preSellList[$key]=array_merge($preSellList[$key], $preSellCount);
                    //获得商品价格
                    $goodsPrice=$this->goodsActive->getGoodsInfo($preSellList[$key]['goods_id']);
                    $preSellList[$key]['sell_price']=$goodsPrice['price'];
                    $preSellList[$key]['goods_thumb']=$goodsPrice['picture'];
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
        if(Request()->isPost()){
            $this->checkEmploy(Request()->post(),['activeId']);
            //获得活动商品的信息
            $actId=Request()->post("activeId/d");
            $isActivity=model("GoodsActivity")->where(['act_id'=>$actId])->find()->toArray();
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
            $status=$this->goodsActive->activityStatus($isActivity);
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
    public function flagDeposit(){
        if(Request()->isPost()){
            $this->checkEmploy(Request()->post(),['number','activeId']);
            //判断该活动状态
            $actId=Request()->post("activeId/d");
            $goodsNumber=Request()->post("number/d");
            $activity=model("GoodsActivity")->where(['act_id'=>$actId])->find();
            if(empty($activity)){
                return json(['status'=>-1,'msg'=>'该活动已经结束或者已经下架！']);exit;
            }
            $activity = $activity->toArray();
            $preSellInfo=array_merge($activity,unserialize($activity['ext_info']));
           if($preSellInfo['total_goods']<$goodsNumber){
                return json(['status'=>-1,'msg'=>'库存不足！']);exit;
            }
            //判断活动状态信息
            $status=$this->goodsActive->activityStatus($preSellInfo);
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
    public function nowBooked(){
        if(Request()->isPost()){
            $this->checkEmploy(Request()->post(),['uid','activeId','number','token']);
            $actId=Request()->post("activeId/d");
            $goodsNumber=Request()->post("number/d");
            //判断用户是否存在
            if(!$this->getUid(Request()->post("token"),Request()->post("uid"))){
                return json(['status'=>0,'msg'=>'该用户不存在！','data'=>[]]);
            }
            $activity=model("GoodsActivity")->where(['act_id'=>$actId])->find();
            if(empty($activity)){
                return json(['status'=>0,'msg'=>'该活动已经结束！']);exit;
            }
            $activity = $activity->toArray();
            $goods_info=Db::name('goods')->where(['goods_id' => $activity['goods_id']])->find();
            if($goods_info['status']==1){
                return json(['status'=>0,'msg'=>'该商品已下架！']);exit; 
            }
            $preSellInfo=array_merge($activity,unserialize($activity['ext_info']));
            if($activity['total_goods']<$goodsNumber || $activity['total_goods']<=0){
                return $this->json(['status'=>0,'msg'=>'库存不足！']);exit;
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
            $uid = Request()->post("uid");
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
    function goodsAttrValue($goodsId,$price,$stock)
    {
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
     * 活动专区
     */
    public function actiArea()
    {
        $activeModel = new ActiveType();
        $order="weigh desc";

        $where = [];
        $where['start_time'] = array('lt', time());
        $where['end_time'] = array('gt', time());
        $where['status'] = 0;
        $where['active_type'] = ['eq',0];
        $where['id'] = ['neq',5];//去除秒杀
        $where['id'] = ['neq', 3];//去除拼团
        $limit = 4;//取多少个
        $rows=$activeModel->select($where,'*',$order, $limit);
        if($rows){
            // 隐藏活动
            return $this->json($rows,0);
        } else {
            return $this->json([], 0, '未知参数');
        }
    }

    /**
     * 根据活动id 获取商品
     * @param integer activeId 活动id
     * @param integer limit
     * @param integer page 页数
     */
    public function getActiveGoods()
    {
        $active_type_id = input('request.activeId');
        $goods_id = input('request.goodsId');
        $goods_id = $goods_id ?$goods_id:0;
        $limit = input('request.limit');
        $p = input('request.page');
        $time = input('request.time');
        $activeModel = new ActiveGoods();
        $info = $activeModel->getActiveinfo($active_type_id);

        if ($info['status'] ==1 || !$info) {
            return $this->json([], 0, '该活动不存在或已结束');
        }
        $goodsModel = new GoodsService();
        $activeTypeModel = new ActiveType();
        $type_name = $activeTypeModel->getActive_label($active_type_id);
        $type_banner = $activeTypeModel->getActive_banner($active_type_id);
        $list = $goodsModel->getActiveGoods($active_type_id, $limit, $p, $time,$goods_id);
        $total = $goodsModel->getActiveGoods($active_type_id,'', '', $time,$goods_id);
        $total  = count($total);
        if ($list) {
            foreach ($list as $key=>&$value) {
                $goods  = Db::name('goods')->where('goods_id',$value['goods_id'])->find();
                $goods_info = $goodsModel->getInfoById($value['goods_id'], 'goods_name, picture');
                $value['goods_name'] = $goods_info['goods_name'];
                $value['picture'] = $goods_info['picture'];

                $commission = $this->getCom();
                //开启 返利
                if($commission['shop_ctrl'] == 1){
                    $f_p_rate = $commission['f_s_rate'];
                }else{
                    $f_p_rate = 100;
                }
                $value['profit'] = floor($value['profit'] * $f_p_rate)/100;
                $value['price'] = floatval($value['price']);
                $value['profit'] = floatval($value['profit']);

                $value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
                $value['vip_price'] = floatval($value['vip_price']);
                $value['show_price'] = sprintf('%0.2f', $value['show_price']);
                $value['show_price'] = floatval($value['show_price']);
                $value['active_price'] = sprintf('%0.2f', $value['active_price']);
                $value['active_price'] = floatval($value['active_price']);	$value['goods_price'] = sprintf('%0.2f', $value['goods_price']);
                $value['goods_price'] = floatval($value['goods_price']);
                $value['active_id'] = $active_type_id;

            }
        }

        $data['list'] =$list;

        $ends = 0;
        if ($active_type_id == 5) {
            $date_time_list = $activeModel->getMiaoshatimes();
            if ($date_time_list) {
                foreach ($date_time_list as &$val) {
                    $hour = time();
                    $val['status'] = 0;
                    if ($ends == 1) {
                        $val['status'] = 2;
                    }
                    if ($val['start_time'] <= $hour && $val['end_time'] > $hour) {

                        $val['status'] = 1;
                        $ends = 1;
                    } else if($val['start_time'] > $hour) {
                        $val['status'] = 2;
                        $ends = 1;
                    }
                    $val['start_time'] = date('H:00', $val['start_time']);
                    $val['end_time'] = date('H:00', $val['end_time']);
                }
            }
            $data['miaosha_info'] = $date_time_list;
        }
        return json(['status'=>1,'msg'=>'获取成功','data'=>$data,'active_type_name'=>$type_name,'active_banner'=>$type_banner,'total'=>$total]);
    }
}



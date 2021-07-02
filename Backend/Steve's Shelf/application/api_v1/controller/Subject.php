<?php
namespace app\api\controller;
use app\common\model\Goods as GoodsModel;
use app\common\service\User as UserService;
use app\common\service\Goods as goodsService;
use think\Db;
use think\Request;
class Subject extends Common{

    public function __construct(){
        parent::__construct();
        $GoodsModel = new GoodsModel();
        $this->model = $GoodsModel;
        $userSevice=new UserService();
        $this->userObject=$userSevice;
    }
    //专题基本信息
    public function baseSubject(){
        if(Request()->isPost()){
         //获得专题基本信息
            $this->checkEmploy(Request()->post(),['active_id']);
            $activeId=Request()->post("active_id/d");
            $nowtime=time();
            $where['id']=$activeId;
            $where['start_time']=['elt',$nowtime];
            $where['end_time']=['egt',$nowtime];
            $where['status']=0;
            $baseInfo=db("active_type")->where($where)
                ->field("id,active_type_name,active_img,active_banner")->find();
            if(empty($baseInfo)){
                return json(['status'=>-1,'msg'=>'该活动已经结束！']);
            }
			 $page=Request()->post('page')?Request()->post('page'):1;
            //获得商品信息
            $topGoods=db("active_goods")->alias("ag")
                ->join("ht_goods g","ag.goods_id=g.goods_id")
                ->join("ht_goods_category gc","gc.category_id=g.category_id")
                ->where(['active_type_id'=>$activeId,'ag.status'=>0,'ag.goods_num'=>['gt',0]])
                ->field("ag.id,ag.active_type_id,ag.goods_id,ag.goods_num,ag.sort,ag.goods_price,goods_name,picture,show_price as price,gc.category_name,g.active_name,g.stock,g.prom_type,g.prom_id,g.commission,g.goods_banner,g.show_price,g.vip_price,g.price")
                ->order("ag.sort desc")->limit(0,10)->select();
			foreach ($topGoods as &$value) {
				$goodsService = new goodsService();
				$active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
				$commission = $this->getCom();
				//开启 返利
				if($commission['shop_ctrl'] == 1){
					$f_p_rate = $commission['f_s_rate'];
				}else{
					$f_p_rate = 100; 
				}
				$value['dianzhu_price'] =  floatval(floor($active_price * $value['commission']/ 100 * $f_p_rate)/100);
				$value['price'] = floatval($value['price']);
				$value['show_price'] = floatval($value['show_price']);
				$value['goods_price'] = floatval($value['goods_price']);
				$value['vip_price'] = floatval($value['vip_price']);
				}
            //数据信息 20181221需求更改注释
			/*  $count=db("active_goods")->where(['active_type_id'=>$activeId,'status'=>0,'goods_num'=>['gt',0]])->count();
             $totalPage=ceil($count/10); */
			 
            $totalPage=1;
            return json(['status'=>1,'msg'=>'数据信息','data'=>['active_base'=>$baseInfo,'top_goods'=>$topGoods,'total_page'=>$totalPage]]);
        }
        return json(['status'=>0,'msg'=>'非法操作！']);
    }
    //商品信息列表信息
    function subjectGoodsList(){
        if(Request()->isPost()){
            $this->checkEmploy(Request()->post(),['active_id']);
            $activeId=Request()->post("active_id/d");
            $nowtime=time();
            $where['id']=$activeId;
            $where['start_time']=['elt',$nowtime];
            $where['end_time']=['egt',$nowtime];
            $baseInfo=db("active_type")->where($where)
                ->field("id,active_type_name,active_img,active_banner")->find();
            if(empty($baseInfo)){
                return json(['status'=>-1,'msg'=>'该活动已经结束！']);
            }
            $page=Request()->post('page')?Request()->post('page'):2;
           /*  if ($page < 3) {
                $page = 3;
            } */
            //获得商品信息
            $topGoods=db("active_goods")->alias("ag")
                ->join("ht_goods g","ag.goods_id=g.goods_id")
                ->where(['active_type_id'=>$activeId])
                // ->where(['active_type_id'=>$activeId,'ag.status'=>0,'ag.goods_num'=>['gt',0]])
                ->field("ag.id,ag.active_type_id,ag.goods_id,ag.sort,ag.goods_num,ag.goods_price,g.goods_banner,goods_name,picture,price as market_price,g.active_name,g.stock,g.prom_type,g.prom_id,g.commission,g.show_price,g.vip_price,g.price")
                ->order("ag.sort desc")->page($page,10)->select();
            if($topGoods){
				foreach ($topGoods as &$value) {
					$goodsService = new goodsService();
					$active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
					$commission = $this->getCom();
					//开启 返利
					if($commission['shop_ctrl'] == 1){
						$f_p_rate = $commission['f_s_rate'];
					}else{
						$f_p_rate = 100; 
					}
					$value['dianzhu_price'] = floor($active_price * $value['commission'])/ 100 * $f_p_rate/100;
					$value['price'] = floatval($value['price']);
					$value['dianzhu_price'] = sprintf('%0.2f', $value['dianzhu_price']);
					$value['dianzhu_price'] = floatval($value['dianzhu_price']);	
					$value['goods_price'] = sprintf('%0.2f', $value['goods_price']);
					$value['goods_price'] = floatval($value['goods_price']);
					$value['market_price'] = sprintf('%0.2f', $value['market_price']);
					
					$value['market_price'] = floatval($value['market_price']);
					$value['show_price'] = sprintf('%0.2f', $value['show_price']);
					$value['show_price'] = floatval($value['show_price']);
					$value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
					$value['vip_price'] = floatval($value['vip_price']);	$value['price'] = sprintf('%0.2f', $value['price']);
					$value['price'] = floatval($value['price']);
				}
                $count=db("active_goods")->where(['active_type_id'=>$activeId,'status'=>0,'goods_num'=>['gt',0]])->count();
				$count -= 10; 
                $totalPage=ceil($count/10);
                return json(['status'=>1,'msg'=>'数据信息','data'=>['goods_list'=>$topGoods,'total_page'=>$totalPage]]);
            }
            return json(['status'=>0,'msg'=>'暂无数据']);
        }
        return json(['status'=>0,'msg'=>'非法操作！']);
    }
}



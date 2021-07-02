<?php
namespace app\common\service;

use app\common\model\GoodsActivity as GoodsActivityModel;
use app\common\model\Goods as GoodsModel;

class  GoodsActivity extends Base{

	public function __construct(){
		parent::__construct();
		$GoodsActivityModel=new GoodsActivityModel();
		$this->model=$GoodsActivityModel;
	}
    //获得活动状态
    public function activityStatus($goodItem){
        switch($goodItem['is_finished']){
            case 0:
                if($goodItem['start_time'] > time()){
                    $activityStatus = '未开始';
                }else if($goodItem['start_time'] <= time() && $goodItem['end_time'] > time()){
                    $activityStatus = '预售中';
                }else{
                    $activityStatus = '结束未处理';
                }
                break;
            case 1:
                $activityStatus='成功结束';break;
            case 2:
                $activityStatus='失败结束';break;
            default:
                $activityStatus='';break;
        }
        return $activityStatus;
    }

    /**
     * @param $goodsActId
     * @param $goodsId
     * 该活动的已经销售金额
     */
    public function preSellCount($goodsActId,$goodsId){
        if(empty($goodsId)){
            $goodsId=$this->model->where(['act_type'=>2])->value("goods_id");
        }
        //查询订单
        $condition=[
            'g.og_goods_id'=>$goodsId,
            'o.order_prom_type'=>2,
            'o.order_prom_id'=>$goodsActId,
            'o.order_status'=>0,
            'o.pay_status'=>[['eq',1],['eq',3],'or']
        ];
        $GoodsActivityModel=new GoodsModel();
        $info=model("order_goods")->alias("g")
            ->field("count(*) as total_order,sum(g.og_goods_num) as sell_total_goods")
            ->join('__ORDER__ o','o.order_id=g.og_order_id')
            ->where($condition)
            ->select();
       // dump($info);exit;
        if(empty($info) || $info[0]['total_order'] == 0){
            $res = array('total_order'=>0,'sell_total_goods'=>0);
        }else{
            $res = $info[0];
        }
        return $res;
    }

    /**
     * @param $goodsId
     * 商品id值
     */
    function getGoodsInfo($goodsId){
        $GoodsActivityModel=new GoodsModel();
        $goodsPrice=$GoodsActivityModel->where(['goods_id'=>$goodsId])->field("price,picture,prom_id,prom_type,vip_price,price,show_price")->find();
        return $goodsPrice;
    }
}
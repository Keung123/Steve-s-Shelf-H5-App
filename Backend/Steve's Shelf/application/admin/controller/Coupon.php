<?php
namespace app\admin\controller;

use app\common\service\Bargain;
use app\common\service\Coupon as Couponmodel;
use app\common\service\Config;
use think\Db;
class Coupon extends Base{

    /*
    * 列表管理
    */
    public function index(){
        $Couponmodel = new Couponmodel();
		$coupon_title = trim(input('coupon_title'));
		$coupon_type = trim(input('coupon_type'));
		$this->assign('coupon_title',$coupon_title);
		$this->assign('coupon_type',$coupon_type);
		if(request()->isAjax()){
			$map=[];
			//排序
			$order="coupon_id desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			$coupon_title = input('search');
			if ($coupon_title) {
			    $map['coupon_title'] = array('like', "%$coupon_title%");
            }
			if (input('coupon_title')) {
			    $map['coupon_title'] = ['like','%'.input('coupon_title').'%'];
            }
			if (input('coupon_type')) {
				$coupon_type = array('商品券'=>1, '专区券'=>2, '全场券'=>3);
			    $map['coupon_type'] = ['eq',$coupon_type[input('coupon_type')]];
            }
			 
			$map['status'] =  array('eq', 0);
			$total=$Couponmodel->count($map);
			$rows=$Couponmodel->select($map,'*',$order,$limit);
			if ($rows) {
                $coupon_type_list = array(1=> '商品券', 2=>'专区券', 3=>'全场券');
			    foreach ($rows as &$val) {
			        $val['coupon_type'] = $coupon_type_list[$val['coupon_type']];
                    $val['coupon_s_time'] = date('Y-m-d',$val['coupon_s_time']);
                    $val['coupon_aval_time'] = date('Y-m-d',$val['coupon_aval_time']);
					
                    $val['amount'] = $Couponmodel->getNumer($val['coupon_id']);
 
                }
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{

			return $this->fetch();
		}    	
    }

    /*
    * 添加  
    */
    public function add(){
        if(request()->isAjax()){
            $row=input('post.row/a');
            //判断库存是否为零
            if($row['coupon_type']==1){
                $stock=db('goods')->where('goods_id',$row['coupon_type_id'])->field('stock')->find();
                if($stock['stock']==0){
                    return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
                }
            }
            $Couponmodel = new Couponmodel();
			$disabled = input('post.disabled/a');
			$row['disabled'] = json_encode($disabled);
            // $row['coupon_s_time'] = strtotime($row['coupon_s_time']);
            // $row['coupon_aval_time'] = strtotime($row['coupon_aval_time'])+ 3600*24-1;
            $row['coupon_add_time'] = time();
            if(empty($row['coupon_s_time'])){
                $row['coupon_s_time']=time();
            }else{
                $row['coupon_s_time'] = strtotime($row['coupon_s_time']);
            }
            if(empty($row['coupon_aval_time'])){
                $row['coupon_aval_time']=time();
            }else{
                $row['coupon_aval_time'] = strtotime($row['coupon_aval_time'])+ 3600*24-1;
            }
            $res=$Couponmodel->add($row);

            //添加日志记录
            $id=db('coupon')->getLastInsID();
            $this->write_log('优惠券添加',$id);
            return AjaxReturn($res,getErrorInfo($res));
        }else{
			  $Couponmodel = new Couponmodel();
			$map['coupon_id']=input('get.ids');
            $row=$Couponmodel->find($map);

            $row['coupon_s_time'] = date('Y-m-d',$row['coupon_s_time']);
            $row['coupon_aval_time'] = date('Y-m-d',$row['coupon_aval_time']);
			
			$spdatas = $Couponmodel->getGoodsList();
			$this->assign('spdatas',$spdatas);
			
			$hddatas = $Couponmodel->getActiveList();
			$this->assign('hddatas',$hddatas);
			
			 $goodCategory = $this->getCategory();
			$this->assign('goodCategory',$goodCategory);
            return $this->fetch();
        }
    }

    /*
    * 编辑
    */
    public function edit(){
        $Couponmodel = new Couponmodel();

        if(request()->isAjax()){
            $row=input('post.row/a');

            //判断库存是否为零
            if($row['coupon_type']==1){
                $stock=db('goods')->where('goods_id',$row['coupon_type_id'])->field('stock')->find();
                if($stock['stock']==0){
                    return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
                }
            }
            $map['coupon_id']=$row['coupon_id'];
			$disabled = input('post.disabled/a');
			$row['disabled'] = json_encode($disabled);
            $row['coupon_s_time'] = strtotime($row['coupon_s_time']);
            $row['coupon_aval_time'] = strtotime($row['coupon_aval_time']) + 3600*24-1;
            $res=$Couponmodel->save($map,$row);

            //添加日志记录
            $this->write_log('优惠券修改',$map['coupon_id']);
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['coupon_id']=input('get.ids');
            $row=$Couponmodel->find($map);
			$row['disabled']= json_decode($row['disabled'],true);
			
            $row['coupon_s_time'] = date('Y-m-d',$row['coupon_s_time']);
            $row['coupon_aval_time'] = date('Y-m-d',$row['coupon_aval_time']);
			
			$spdatas = $Couponmodel->getGoodsList();
			$this->assign('spdatas',$spdatas);
			
		    $goodCategory = $this->getCategory();
			$this->assign('goodCategory',$goodCategory);
			 
			
			$hddatas = $Couponmodel->getActiveList();
			$this->assign('hddatas',$hddatas);
			 
            $this->assign('row',$row);
            return $this->fetch();
        }
    }
/*
    * 展示优惠券
    */
    public function showCoupon(){
         $Couponmodel = new Couponmodel();

        if(request()->isAjax()){
            $row=input('post.row/a');
            $map['coupon_id']=input('post.coupon_id');
            $row['coupon_s_time'] = strtotime($row['coupon_s_time']);
            $row['coupon_aval_time'] = strtotime($row['coupon_aval_time']) + 3600*24-1;
            $res=$Couponmodel->save($map,$row);
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['coupon_id']=input('get.ids');
            $row=$Couponmodel->find($map);

            $row['coupon_s_time'] = date('Y-m-d',$row['coupon_s_time']);
            $row['coupon_aval_time'] = date('Y-m-d',$row['coupon_aval_time']);
			
			$spdatas = $Couponmodel->getGoodsList();
			$this->assign('spdatas',$spdatas);
			
		 
			
			$hddatas = $Couponmodel->getActiveList();
			$this->assign('hddatas',$hddatas);
			 
            $this->assign('row',$row);
            return $this->fetch();
        }
    }

    /*
    * 删除
    */
    public function delete(){
        $ids=input('get.ids');
        $map['coupon_id']=['in',$ids];

        $Couponmodel = new Couponmodel();
        // $res=$Couponmodel->delete($map);


		$res= Db::name('coupon')->where($map)->update(array('status'=>1));

		//添加日志记录
        $this->write_log('优惠券删除',$map['coupon_id']);
        return AjaxReturn($res);
    }
	/*
    * 获取商品分类
    */
    public function getCategory(){
		 
		$list= Db::name('goods_category')->where('pid',0)->field('category_id,category_name')->select();
		return $list;	
	}
	
    /**
    *查询商品
    */
    public function searchGood()
    {
        $goodName = input('goodName');
        $res = [];
        if(!empty($goodName)){
            $res = Db::name('goods')->where(['goods_name'=>['like',"%".$goodName."%"]])->field('goods_id,goods_name')->select();
        }
        return $res;
    }
	
}
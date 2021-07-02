<?php
namespace app\admin\controller\coupon;

use app\common\service\Coupon as CouponService;
use think\Db;
use think\Request;
use app\admin\controller\Base;

class Coupon extends Base{

    public $couponService;

    public function __construct(Request $request = null)
    {
        $this->couponService = new CouponService();
        parent::__construct($request);
    }
  
  /*
    * 发放优惠券  
    */
    public function fafang(){
        if(request()->isAjax()){
            $row=input('post.row/a');

            $mobile = $row['mobile'];
            $coupon_id = $row['coupon_id'];
			
          
            if($mobile){
                $users = Db::name('users')->where('user_mobile',$mobile)->find();
                if($users){
                    
                    $user_id = $users['user_id'];
                }
            }

           
            if (!$user_id) {
               return (['code'=>-1,'msg'=>'未知用户','data'=>'未知用户']);
            }

            $res = $this->couponService->getCoupon($user_id, $coupon_id);
          

           if ($res == 1) {
                //添加日志记录
                $this->write_log('赠送优惠券',$coupon_id);
                return (['code'=>1,'msg'=>'赠送优惠券成功','data'=>'赠送优惠券成功']);
            } elseif($res == -1) {
                return (['code'=>-1,'msg'=>'没有优惠券信息','data'=>'没有优惠券信息']);
            }
            else if($res == -2){
                return (['code'=>-1,'msg'=>'已达到每人限领张数','data'=>'已达到每人限领张数']);
            }
            else {
                return (['code'=>-1,'msg'=>'领取失败','data'=>'领取失败']);
            }

        }else{

            $list = Db::name('coupon')->where('status',0)->where('type',4)->where("coupon_s_time",'<',time())->order($order)->limit($start, $limit)->select();

            $this->assign('coupon',$list);
            return $this->fetch();
        }
    }

    public function userList()
    {
        $coupon_title = trim(input('c_coupon_title'));
        $coupon_type = trim(input('c_coupon_type'));
        $coupon_stat = trim(input('coupon_stat'));
        $this->assign('c_coupon_title',$coupon_title);
        $this->assign('c_coupon_type',$coupon_type);
        $this->assign('coupon_stat',$coupon_stat);
        if(request()->isAjax()){
            $map=[];
            //排序
            $order="coupon_id desc";
            //limit
            $limit=input('get.offset').",".input('get.limit');

            if (input('c_coupon_title')) {
                $map['c_coupon_title'] = ['like','%'.input('c_coupon_title').'%'];
            }
            if (input('c_coupon_type')) {
                $map['c_coupon_type'] = ['eq',$coupon_type];
            }

            if (input('coupon_stat')) {
                $map['coupon_stat'] = ['eq',$coupon_stat];
            }
            $total = $this->couponService->couponUsersModel->where($map)->count();
            $rows = $this->couponService->couponUsersModel->where($map)->order($order)->limit($limit)->select();
          
          	
          
            if ($rows) {
                foreach ($rows as &$val) {
                    $val['c_coupon_type'] = $this->couponService->couponUserModel->coupon_type[$val['c_coupon_type']];
                    $val['coupon_aval_time'] = $val['coupon_aval_time'] ? date('Y-m-d H:i:s', $val['coupon_aval_time']) : '';
                    $val['add_time'] = $val['add_time'] ? date('Y-m-d H:i:s', $val['add_time']) : '';
                    $val['update_time'] = $val['update_time'] ? date('Y-m-d H:i:s', $val['update_time']) : '';
                    if($val['coupon_aval_time'] && $val['coupon_aval_time'] < time()){
                        $val['coupon_stat'] = 3;
                    }
                    $val['coupon_stat'] = $this->couponService->couponUsersModel->status[$val['coupon_stat']];
                }
            }
            return json(['total'=>$total,'rows'=>$rows]);
        }else{

            return $this->fetch();
        }
    }

    /*
    * 列表管理
    */
    public function index(){
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
			$total = $this->couponService->couponModel->where($map)->count();
			$rows = $this->couponService->couponModel->where($map)->order($order)->limit($limit)->select();
			if ($rows) {
              $type_list = array(1=> '正常优惠券', 2=>'新人赠送', 3=>'分享赠送', 4=>'公司赠送专用');
                $coupon_type_list = array(1=> '商品券', 2=>'专区券', 3=>'全场券');
			    foreach ($rows as &$val) {
                  $val['type'] = $type_list[$val['type']];
			        $val['coupon_type'] = $coupon_type_list[$val['coupon_type']];
                    $val['coupon_s_time'] = date('Y-m-d',$val['coupon_s_time']);
                    $val['coupon_aval_time'] = date('Y-m-d',$val['coupon_aval_time']);
					
                    $val['amount'] = $this->couponService->getNumer($val['coupon_id']);
 
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

            if(!$row['coupon_price']) {
                return (['code'=>0,'msg'=>'优惠券面额不能为空','data'=>'优惠券面额不能为空']);
            }

            if($row['coupon_use_limit']<0) {
                return (['code'=>0,'msg'=>'优惠券面额不能为空','data'=>'优惠券面额不能为空']);
            }

            if(!$row['coupon_s_time']) {
                return (['code'=>0,'msg'=>'失效时间不能为空','data'=>'失效时间不能为空']);
            }

            if(!$row['coupon_aval_time']) {
                return (['code'=>0,'msg'=>'截止时间不能为空','data'=>'截止时间不能为空']);
            }

            if(!$row['coupon_total']) {
                return (['code'=>0,'msg'=>'优惠券总张数不能为空','data'=>'优惠券总张数不能为空']);
            }
			
          	if($row['type']==2){
                $typecount = $this->couponService->couponModel->where("type",2)->count();
                if($typecount>0){
                    return (['code'=>0,'msg'=>'已经添加过新人赠送优惠券，不可重复添加','data'=>'以及添加过新人赠送优惠券，不可重复添加']);
                }
            }

            if($row['type']==3){
                $typecount = $this->couponService->couponModel->where("type",3)->count();
                if($typecount>0){
                    return (['code'=>0,'msg'=>'已经添加过分享赠送优惠券，不可重复添加','data'=>'以及添加过分享赠送优惠券，不可重复添加']);
                }
            }
            $row['coupon_s_time'] = strtotime($row['coupon_s_time']);
            $row['coupon_aval_time'] = strtotime($row['coupon_aval_time']);

            $res=$this->couponService->couponModel->insert($row);

            //添加日志记录
            $id=db('coupon')->getLastInsID();
            $this->write_log('优惠券添加',$id);
            return AjaxReturn($res,getErrorInfo($res));
        }else{

			$map['coupon_id']=input('get.ids');
            $row = $this->couponService->couponModel->where($map)->find();

            $row['coupon_s_time'] = date('Y-m-d',$row['coupon_s_time']);
            $row['coupon_aval_time'] = date('Y-m-d',$row['coupon_aval_time']);
			
			$spdatas = $this->couponService->getGoodsList();
			$this->assign('spdatas',$spdatas);
			
			$hddatas = $this->couponService->getActiveList();
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
        if(request()->isAjax()){
            $row=input('post.row/a');

            //判断库存是否为零
            if($row['coupon_price'] < 0) {
                return (['code'=>0,'msg'=>'优惠券面额不能小于0','data'=>'优惠券面额不能小于0']);
            }

            if($row['coupon_use_limit'] < 0) {
                return (['code'=>0,'msg'=>'最低消费金额不能小于0','data'=>'最低消费金额不能小于0']);
            }

            if($row['coupon_s_time'] < 0) {
                return (['code'=>0,'msg'=>'失效时间不能为空','data'=>'失效时间不能为空']);
            }

            if($row['coupon_aval_time'] < 0) {
                return (['code'=>0,'msg'=>'截止时间不能为空','data'=>'截止时间不能为空']);
            }

            if(!$row['coupon_total']) {
                return (['code'=>0,'msg'=>'优惠券总张数不能为空','data'=>'优惠券总张数不能为空']);
            }

            $row['coupon_s_time'] = strtotime($row['coupon_s_time']);
            $row['coupon_aval_time'] = strtotime($row['coupon_aval_time']);
            $map['coupon_id']=$row['coupon_id'];
            $res=$this->couponService->couponModel->where($map)->update($row);

            //添加日志记录
            $this->write_log('优惠券修改',$map['coupon_id']);
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['coupon_id']=input('get.ids');
            $row=$this->couponService->couponModel->where($map)->find();
			$row['disabled']= json_decode($row['disabled'],true);
			
            $row['coupon_s_time'] = date('Y-m-d',$row['coupon_s_time']);
            $row['coupon_aval_time'] = date('Y-m-d',$row['coupon_aval_time']);
			
			$spdatas = $this->couponService->getGoodsList();
			$this->assign('spdatas',$spdatas);
			
		    $goodCategory = $this->getCategory();
			$this->assign('goodCategory',$goodCategory);
			 
			
			$hddatas = $this->couponService->getActiveList();
			$this->assign('hddatas',$hddatas);
			 
            $this->assign('row',$row);
            return $this->fetch();
        }
    }
    /*
    * 展示优惠券
    */
    public function showCoupon(){
        if(request()->isAjax()){
            $row=input('post.row/a');
            $map['coupon_id']=input('post.coupon_id');
            $row['coupon_s_time'] = strtotime($row['coupon_s_time']);
            $row['coupon_aval_time'] = strtotime($row['coupon_aval_time']);
            $res=$this->couponService->couponModel->where($map)->save($row);
            return AjaxReturn($res,getErrorInfo($res));
            return AjaxReturn($res,getErrorInfo($res));
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['coupon_id']=input('get.ids');
            $row=$this->couponService->couponModel->where($map)->find();

            $row['coupon_s_time'] = date('Y-m-d',$row['coupon_s_time']);
            $row['coupon_aval_time'] = date('Y-m-d',$row['coupon_aval_time']);
			
			$spdatas = $this->couponService->getGoodsList();
			$this->assign('spdatas',$spdatas);
			
			$hddatas = $this->couponService->getActiveList();
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


        // $res=$this->couponService->couponModel->->delete($map);


		$res= Db::name('coupon')->where($map)->update(array('status'=>1));

		//添加日志记录
        $this->write_log('优惠券删除',$map['coupon_id']);
        return (['code'=>1,'msg'=>'优惠券已删除','data'=>'优惠券已删除']);
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
<?php
namespace app\admin\controller;
use think\Db;
use app\common\service\Order as OrderService;

class Index extends Base{

    public function index(){
        $Order=db('order');
		$admin_id = session('admin_id');
		$group_id = session('group_id');
		$supplier_id = session('supplier_id');
		//供应商
		if($group_id == 3){
			$OrderPost = Db::name('order_goods')
				->alias('a')
				->join('order b','a.og_order_id =b.order_id')
				->where(array('b.order_status'=>1,'a.og_supplier_id'=>$supplier_id))
				->count();
		}else{
			$OrderPost = Db::name('order_goods')
				->alias('a')
				->join('order b','a.og_order_id =b.order_id')
				->where(array('b.order_status'=>1))
				->count();	
		}
        //未处理订单
        $this->assign('OrderPost',$OrderPost);
    	return $this->fetch();
    }

    public function dashboard(){
        $User=db('users');
        $Order=db('order');
    	//用户数量
    	$user=$User->count();
    	$this->assign('user',$user);
    	//商品
    	$goods=db('goods')->count();
    	$this->assign('goods',$goods);   
    	//订单
    	$order=$Order->count();
    	$this->assign('order',$order);
    	//总收入
    	$price=$Order->where('order_status',4)->sum('order_pay_price');
    	//四舍五入 保留两位小数
		$price=round($price,2);
    	$this->assign('price',$price);
        //获取一周订单图表数据
        $start=strtotime(date('Y-m-d',strtotime("-7 days")));
        $end=time();
        $map['order_create_time']=['BETWEEN',[$start,$end]];
        $list= Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->field('b.*,a.*')->where('order_isdel',0)->where($map)->select();
        $DateData=[];
        foreach ($list as $value) {
            $DateData[date('Y-m-d',$value['order_create_time'])]['createdata'][]=$value;
            if($value['order_status']>0){
                $DateData[date('Y-m-d',$value['order_create_time'])]['paydata'][]=$value;
            }            
        }
        $OrderData=[];
        for ($i=$start; $i <=$end ; $i=$i+86400) { 
            $date=date('Y-m-d',$i);
            $OrderData['column'][]=$date;
            $OrderData['createdata'][]=count($DateData[$date]['createdata']);
            $OrderData['paydata'][]=count($DateData[$date]['paydata']);
        }
        $this->assign('OrderData',json_encode($OrderData));
        //今日注册
        $RegisterToday=$User->where('user_reg_time','>=',strtotime(date("Y-m-d")))->count();
        $this->assign('RegisterToday',$RegisterToday);
        //今日登录
        $LoginToday=$User->where('user_last_login','>=',strtotime(date("Y-m-d")))->count();
        $this->assign('LoginToday',$LoginToday);
        //今日订单
        $OrderToday = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->field('b.*,a.*')->where('order_create_time','>=',strtotime(date("Y-m-d")))->where('order_isdel',0)->count();
		
        $this->assign('OrderToday',$OrderToday);
        //未处理订单
        $OrderPost= Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->field('b.*,a.*')->where('order_status',1)->count();
        $this->assign('OrderPost',$OrderPost);
        //近一周订单
        $this->assign('OrderWeek',count($list));
        //近一月订单
        $start=strtotime(date('Y-m-d',strtotime("-30 days")));
        $map['order_create_time']=['BETWEEN',[$start,$end]];
        $OrderMonth= Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where($map)->where('order_isdel',0)->field('b.*,a.*')->count();  
        $this->assign('OrderMonth',$OrderMonth);

        /* 预警中心 */
        $warn_data = [
            'storage' => 0,           // 库存
            'new_order' => 0,         // 新订单
            'as_order' => 0,          // 售后订单
            'new_member' => 0,        // 新会员
            'new_seller' => 0,        // 新店主
            'unhandle_comment' => 0,  // 未处理评价
            'new_material' => 0,      // 新素材
            'new_apply' => 0,         // 实名认证申请
        ];
        $goods_warn = Db::name('config')->where(1)->value('warn_stock');
        $warn_where = 'stock<='.($goods_warn ?:200);        
        $warn_data['storage'] = Db::name('goods_sku')->where($warn_where)->count();
        $warn_time_s = strtotime(date('Y-m-d'));
        $warn_time_e = strtotime(date('Y-m-d H:i:s'));
        $warn_data['new_order'] = Db::name('order')->where(['order_pay_time' => [['egt', $warn_time_s], ['elt', $warn_time_e]]])->count();
        $warn_data['as_order'] = Db::name('as_list')->where(['as_stat' => 0])->count();
        $warn_data['new_member'] = Db::name('users')->where(['user_reg_time' => [['egt', $warn_time_s], ['elt', $warn_time_e]]])->count();
        $warn_data['new_seller'] = Db::name('store')->where(['s_comm_time' => [['egt', $warn_time_s], ['elt', $warn_time_e]]])->count();
        $warn_data['unhandle_comment'] = Db::name('order_remark')->where(['status' => 0])->count();
        $warn_data['new_material'] = Db::name('users_material')->where(['mate_add_time' => [['egt', $warn_time_s], ['elt', $warn_time_e]]])->count();
        $warn_data['new_apply'] = Db::name('idauth')->where(['auth_stat' => 0])->count();

        $this->assign('warn_data', $warn_data);
    	return $this->fetch();

    }

    public function homepage(){
        return $this->fetch();
    }
	public function aaa(){
        return 0;
    }
}

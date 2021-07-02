<?php
namespace app\admin\controller;

use app\api\controller\Integral;
use app\common\service\Order as OrderService;
use app\common\service\User as UserService;
use app\common\service\Config;
use app\common\service\User;
use app\common\service\Goods;
use think\Db;
use getui\Pushs;
use think\Request;

class Order extends Base{
    protected $order;

    public function __construct(OrderService $order)
    {
        parent::__construct();
        $this->order = $order;
    }

    /*
    * 订单管理
    */
   
    function export(){//导出Excel
    	$order_no = explode(',',$_GET['or']);   	
    	$data=array();
    	foreach ($order_no as $key => $value) {
    		
    		$order_id=Db::name('order')->where(['order_no'=>$value])->value('order_id');
    		$order_goods_data=Db::name('order_goods')->where(['og_order_id'=>$order_id])->find();
    	$map['og_supplier_id']=$order_goods_data['og_supplier_id'];
		$map['order_id']=$order_id;
		$map['og_id']=$order_goods_data['og_id']; 
		$OrderService = new OrderService();
		$row = $OrderService->particulars($map);
		 //获取地址详情
		 $supplier_title=Db::name('supplier')->where(['id'=>$order_goods_data['og_supplier_id']])->find();
		$user = new UserService();
		$addr_info = $user->address($row['pro_name'],$row['city_name'],$row['area']);
        $row['addr_phone'] = $row['phone']; 
        $row['addr_ress'] =$addr_info.$row['phone']; 
		$row['order_type'] = $row['order_type']==0?'普通订单':'积分兑换订单';
		if($row){
			//是否需要发票：0，否；1，是
			if($row['need_invoice'] == 1){
				$row['invoice_type'] = $row['invoice_type']= 1?'电子发票':'纸质发票';
			}else{
				$row['invoice_header']  ='';
			}
			$row['need_invoice'] = $row['need_invoice']= 1?'是':'否';
			//发票类型：1，电子发票；2，纸质发票
			if($row['order_pay_time'] == 0){
				$row['order_pay_time'] = '未支付';
			}else{
				$row['order_pay_time'] = date('Y-m-d H:i:s',$row['order_pay_time']);
			}
			//支付方式
			$pay_type = [
				'balance'=>'余额支付',
				'alipay'=>'支付宝',
				'wxpay'=>'微信支付',
				'积分支付'=>'积分支付',
			];
			$row['order_pay_code'] = $pay_type[$row['order_pay_code']];
			$row['order_pay_price'] =  $row['og_goods_price']*$row['og_goods_num'];
			if(!$row['order_pay_points']){
				$row['order_pay_points'] = 0.00;
			}
			$row['yin_amount'] =  0.00;
			if($row['yz_id']){
				$res  = Db::name('yinzi')->where('yin_id',$row['yz_id'])->field('yin_amount')->find();
				if($res){
					$row['yin_amount'] = $res['yin_amount'];
				}
			}
			$row['card_price'] =  0.00;
			if($row['rc_id']){
				$res  = Db::name('user_rc')->where('card_id',$row['rc_id'])->field('card_price')->find();
				if($res){
					$row['card_price'] = $res['card_price'];
				}
			}
			//优惠券
			$row['c_coupon_price'] = 0.00;
			if($row['order_coupon_id']){
				$coupon_data = Db::name('coupon_users')->where('c_id',$row['order_coupon_id'])->find();
				$row['c_coupon_price'] = $coupon_data['c_coupon_price'];
			}
			
			$row['Amount_payable'] = $row['og_goods_price']*$row['og_goods_num'];
			$row['order_pay_price'] -= $res['yin_amount'];
			$row['order_pay_price'] -= $row['c_coupon_price'];
			$row['order_pay_price'] -= $row['order_pay_points'];
			$row['order_pay_price'] -= $row['order_discount'];
			$row['order_pay_price'] -= $row['rc_amount'];
			$row['order_pay_price'] -= $row['order_prom_amount'];
			$row['order_pay_price'] += $row['og_freight'];
			if($row['order_pay_price']<=0){
				$row['order_pay_price'] = 0;
			}
			if ($row['pro_name']) {
                $row['pro_name'] = Db::name('region')->where(['region_id' => $row['pro_name'] ])->value('region_name');
            }
            if ($row['city_name']) {
                $row['city_name'] = Db::name('region')->where(['region_id' => $row['city_name'] ])->value('region_name');
            }
            if ($row['area']) {
                $row['area'] = Db::name('region')->where(['region_id' => $row['area'] ])->value('region_name');
            }
		}
		
		//订单状态
		 $user_name = Db::name('users')->where(['user_id' => $row['order_goods'][0]['og_uid'] ])->value('user_name');
		$row['order_status'] = $OrderService->getStatus($row['order_status']);
    	$data[$key]=['order_no'=>$row['order_no'],'order_type'=>$row['order_type'],'order_pay_time'=>$row['order_pay_time'],'og_goods_name'=>$row['og_goods_name'],'phone'=>$row['phone'],'consigee'=>$row['consigee'],'city_name'=>$row['city_name'].$row['area'].$row['address'],'user_mobile'=>$row['user_mobile'],'og_goods_spec_val'=>$row['og_goods_spec_val'],'og_goods_num'=>$row['og_goods_num'],'supplier_title'=>$supplier_title['supplier_title'],'user_name'=>$user_name];
    	}
        $xlsName  = "";
        $xlsCell  = array(
            array('order_no','订单编号'),
            array('order_type','订单类型'),
            array('order_pay_time','下单时间'),
            array('og_goods_name','订单商品'),
            array('og_goods_spec_val','商品规格'),
            array('og_goods_num','购买数量'),
            array('user_name','购买人'),            
	        array('user_mobile','购买人手机号'),
            array('consigee','收货人'),
            array('phone','收货人手机号'),
            array('city_name','收货地址'),
            array('supplier_title','供应商名称'),

        );

        $this->exportExcel($xlsName,$xlsCell,$data);
        echo json_encode(['code'=>1]);
    }

    function exportExcel($expTitle,$expCellName,$expTableData){
        include_once EXTEND_PATH.'PHPExcel/PHPExcel.php';//方法二
        $xlsTitle = iconv('utf-8', 'gbk', $expTitle);//文件名称
        $fileName = $expTitle.date('_YmdHis');//or $xlsTitle 文件名称可根据自己情况设定
        $cellNum = count($expCellName);
        $dataNum = count($expTableData);

        //var_dump($dataNum);die;
        //$objPHPExcel = new PHPExcel();//方法一
        $objPHPExcel = new \PHPExcel();//方法二
        $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
        $objPHPExcel->getActiveSheet(0)->mergeCells('A1:'.$cellName[$cellNum-1].'1');//合并单元格
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
        for($i=0;$i<$cellNum;$i++){
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'2', $expCellName[$i][1]);
        }
        // Miscellaneous glyphs, UTF-8
        for($i=0;$i<$dataNum;$i++){
            for($j=0;$j<$cellNum;$j++){
            	//var_dump($expTableData[$i][$expCellName[$j][0]]);die;
                $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+3), $expTableData[$i][$expCellName[$j][0]]);
            }
        }
        //var_dump($objPHPExcel);die;
        ob_end_clean();//这一步非常关键，用来清除缓冲区防止导出的excel乱码
        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
        header("Content-Disposition:attachment;filename=$fileName.xls");//"xls"参考下一条备注
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        //var_dump($objWriter);die;
        //"Excel2007"生成2007版本的xlsx，"Excel5"生成2003版本的xls
        $objWriter->save('php://output');
        exit;
    }
    public function index(){
    	$OrderService=new OrderService();
    	//超过7天自动收货
    	$time=7*24*3600;
    	$maps['order_create_time']=['<',time()-$time];
    	$maps['order_status']=2;
    	//3，待评价；4，已完成
    	//$OrderService->save($maps,['order_status'=>4]);
        $OrderInfo = $OrderService->select($maps);

		if($OrderInfo){
            $os = new OrderService();
            foreach ($OrderInfo as $v){
                $os->postConfirm($v['order_uid'], $v['order_id']);
            }
		}

        $order_no = trim(input('order_no'));
        $order_status = input('order_status');
		$og_goods_name = trim(input('og_goods_name'));
		$phone = input('phone');
		$shop_name = trim(input('shop_name'));
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('order_no',$order_no);
        $this->assign('og_goods_name',$og_goods_name);
        $this->assign('shop_name',$shop_name);
        $this->assign('phone',$phone);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
		if(request()->isAjax()){
			$map=[];
			//排序
			$order="og_id desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			
			//搜索	
			if(input('get.search')){
				$map['order_no']=['eq',input('get.search')];
			}	
			if(input('order_no')){
				$map['order_no'] = ['eq',trim(input('order_no'))];
			}
 			if(input('og_goods_name')){
 				$map['a.og_goods_name'] = ['like',"%$og_goods_name%"];
 			}
            if(input('shop_name')){
                $map['c.shop_name|c.user_name'] = ['like',"%$shop_name%"];
            }
 			if(input('phone')){
 				$map['phone'] = ['eq',trim(input('phone'))];
 			}
			if(input('start_time')){
				 $start_time = str_replace('+',' ',input('start_time'));
			}
		    if(input('end_time')){
				 $end_time = str_replace('+',' ',input('end_time'));
			}
			$map_status = input('order_status');
		    $type = $map_status;
			if($map_status){
				switch($map_status){
					case 1 : $map['order_status'] = ['eq', 1]; break;
					case 2 : $map['order_status'] = ['eq', 2]; break;
					case 3 : $map['order_status'] = [['eq', 4],['eq',3],'or']; break;
					case 4 : $map['order_status'] = ['eq', 5]; break;
				}				
			}
//			if($map_status == 1){
//				$map['a.og_order_status'] = 1;
//			}else if($map_status == 2){
//				$map['a.og_order_status'] = 2;
//			}

			$this->assign('order_status', $map_status);
            if ($start_time && $end_time) {
                $map['order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
            } elseif ($start_time) {
                $map['order_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['order_create_time'] = array('<=', strtotime($end_time));
            }
			//订单状态
//			if(isset($order_status)){
//				$map['a.og_order_status']=$order_status;
//				if($order_status == '11269'){
//					$map['a.og_order_status'] = ['eq', 0];
//				}
//			}else{
//                $map['a.og_order_status']= ['egt', 0];
//            }
			if($order_status==1){
                $og_ids = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where(['a.og_order_status'=>1])->column('a.og_id');
                //查一下订单中是否有已完成售后
                if($og_ids){
                    $sh_og_ids = Db::name('sh_info')->where(['og_id' => ['in',$og_ids],'audit_status'=>['>',4]])->column('og_id');
                    if($sh_og_ids){
                        $og_ids = array_diff($og_ids, $sh_og_ids);
                    }
                    $map['a.og_id']=['in',$og_ids];
                }
            }
			//软删除的订单不展示
            $map['order_isdel'] = array('eq',0);
            list($rows,$total)  = $OrderService->getOrderinfos($map,$order,$limit,$type);

			// 供应商订单
			$supplierId =session('supplier_id');
			if($supplierId){
				if($order_status){
					$map2['a.og_order_status']=$order_status;
					if($order_status == '11269'){
						$map2['a.og_order_status'] = ['eq', 0];
					}
                    if($order_status==1){
                        $og_ids = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')->where(['a.og_order_status'=>1,'a.og_supplier_id'=>$supplierId])->column('a.og_id');
                        //查一下订单中是否有已完成售后
                        if($og_ids){
                            $sh_og_ids = Db::name('sh_info')->where(['og_id' => ['in',$og_ids],'audit_status'=>['>',4]])->column('og_id');
                            if($sh_og_ids){
                                $og_ids = array_diff($og_ids, $sh_og_ids);
                            }
                            $map2['a.og_id']=['in',$og_ids];
                        }
                    }
				}
				 if ($start_time && $end_time) {
					$map2['b.order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
				} elseif ($start_time) {
					$map2['b.order_create_time'] = array('>=',strtotime($start_time));
				} elseif ($end_time) {
					$map2['b.order_create_time'] = array('<=', strtotime($end_time));
				}
				$map2['b.order_isdel'] = 0;
				$map2['a.og_supplier_id'] = $supplierId;
				$rows=$OrderService->getOrders($map2,$order,$limit);
				$total = $OrderService->getOrders($map2,$order,'');
				$total= count($total);
			}

			$user_model = new User();
			$goods_model = new Goods();
			// 状态0，待付款；1，待发货；2，待收货；3，待评价；4，已完成；5，已取消；6，申请退货；7，申请换货;'
			$status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退款中','申请换货','退款完成');

			if ($rows) {
				$page_price = 0;
			    foreach ($rows as $key=>$val) {
			        // 判断该商品是否为 售后商品
                    $sh_info = Db::name('sh_info')->where(['og_id' => $val['og_id'], 'supplier_status' => ['eq', 2]])->find();
                    if ($sh_info) {
                        $rows[$key]['sh_status'] = 1;
                    } else {
                        $rows[$key]['sh_status'] = 0;
                    }
//			        $addr_info = $user_model->address($val['pro_name'],$val['city_name'],$val['area']);
			        $rows[$key]['addr_phone'] = $val['phone'];
			        $rows[$key]['addr_receiver'] = $val['consigee'];

			        $rows[$key]['order_create_time'] = date('Y-m-d H:i:s', $val['order_create_time']);
					$rows[$key]['order_type']  =  $val['order_type'] == 0? '普通订单':'积分兑换订单';
					$rows[$key]['pick_status']  =  $val['pick_status'] == 0? '否':'是';
                    $goods_list = $OrderService->getOrderGoodsinfo($val['og_id']);
			        $goods_name_array =  $goods_list['og_goods_name'];
			        $supplier_str = $goods_list['og_supplier_id']; 
					$supplier_name = $OrderService->getSupplierName($supplier_str);

			        $rows[$key]['goods_name'] = implode(',',$goods_name_array);
			        $rows[$key]['supplier_name'] = implode(',',$supplier_name);
					$page_price += $val['order_pay_price'];
					$rows[$key]['page_price'] = round($page_price,2);
					// 总计销售额
					$total_price = Db::name('order_goods')->alias('a')->join('__ORDER__ b', 'a.og_order_id=b.order_id')
                        ->join('users c','a.og_uid = c.user_id','left')
                        ->where($map)->order($order)->limit($limit)->field('b.*,a.*')->sum('order_pay_price');
					$rows[$key]['total_price'] = round($total_price,2);
					//订单商品 发货 订单未发货
					if(($val['order_status'] == 1)&&($val['og_order_status']==2)){
						$rows[$key]['order_status'] = 2;
						$val['order_status'] = 2;
					}
					//$rows[$key]['status_names'] =$status_arr[$val['og_order_status']];
					$rows[$key]['status_names'] =$status_arr[$val['order_status']];
					if (isset($val['order_pay_code']) && $val['order_pay_code'] == 'offpay') {
                        $rows[$key]['offpay'] = '是';
                    } else {
                        $rows[$key]['offpay'] = '否';
                    }
                    //获取用户店铺名称
                    $rows[$key]['shop_name'] = Db::name('users')->where('user_id',$val['order_uid'])->value('shop_name');

                    //支付方式
                    $pay_type = [
                        'balance'=>'余额支付',
                        'alipay'=>'支付宝',
                        'wxpay'=>'微信支付',
                        'offpay'=>'货到付款',
                        '积分支付'=>'积分支付',
                        'jsapi'=>'公众号支付'
                    ];
					if ($val['order_pay_code']){
                        $rows[$key]['order_pay_code'] = $pay_type[$val['order_pay_code']];
                    }else{
                        $rows[$key]['order_pay_code']='-';
                    }
                }

            }
            //判断成团状态
            foreach ($rows as &$v){
			    if ($v['order_status']==1){
			        $acti_id =db('order_goods')->where('og_order_id',$v['order_id'])->select();
			        foreach ($acti_id as $val){
                        $status = db('team_follow')->where('order_id', $v['order_id'])->value('status');
                        if($status){
                            if ($val['og_acti_id'] == 3 && $status < 2) {
                                $v['team_status'] = '待成团';
                            } elseif ($val['og_acti_id'] == 3 && $status == 2) {
                                $v['team_status'] = '已成团';
                            } else {
                                $v['team_status'] = '成团失败';
                            }
                        }else{
                            $v['team_status'] = '否';
                        }
                    }
                }else{
			        $v['team_status'] = '否';
                }
            }
            return json(['total'=>$total,'rows'=>$rows]);
		}else{
			$this->assign('order_status',$order_status);
			return $this->fetch();
		}    	
    }	

    /*
    * 发货  
    */
   	public function post(){

   		$OrderService=new OrderService();
		if(request()->isPost()){
			$map1['order_id']=input('post.order_id');
			
//			if(input('post.og_supplier_id')){
//				$map2['og_supplier_id']=input('post.og_supplier_id');
//			}
			$map2['og_order_id']=input('post.order_id');
			$map2['og_id']=input('post.og_id');

			$order_good =[
				//'post_type'=>input('post.post_type'),
				'og_order_status'=>2,
				//'post_no'=>input('post.post_no'),
				'og_delivery_time'=>time(),
				'order_goods_ok_time'=>time(),
			];
			$res= Db::name('order_goods')->where($map2)->update($order_good);
			 
			$where =[
				'og_order_status'=>['neq',2],
				'og_order_id'=>input('post.order_id'),
			];
			 
			 $row = Db::name('order_goods')->where('og_order_id',input('post.order_id'))->select();
			 $result=1;
			 if($row){
				foreach( $row as $val){
				    if ($val['og_id'] == $map2['og_id']) {
                        continue;
                    }
				    $sh_info = Db::name('sh_info')->where(['og_id' => $val['og_id']])->find();
				    if ($sh_info) {
				        if ($sh_info['status'] == 3 || $sh_info['supplier_status'] == 3 || $sh_info['financial_status'] == 3) {
                            if($val['og_order_status'] ==1){
                                $result	= 0;
                            }
                        }
                    } else {
                        if($val['og_order_status'] ==1){
                            $result	= 0;
                        }
                    }
				}
			 }
			 if($result){

				$datas['order_status']=2;
				$datas['post_status']=2;
				$datas['post_type']=input('post.post_type');
				$datas['post_no']=input('post.post_no');
				$datas['delivery_time'] = time();
				$datas['order_finish_time']=time();
                 $datas['sender_id']=input('post.sender_id');
                 $datas['post_id']=input('post.post_id');
				$res=$OrderService->save($map1,$datas);	 
			 }
				
            //日志记录
            $add['uid'] = session('admin_id');
            $add['ip_address'] = request()->ip();
            $add['controller'] = request()->controller();   
            $add['action'] = request()->action();
            $add['remarks'] = '发货';
            $add['number'] = input('post.order_id');
            $add['create_at'] = time(); 
            db('web_log')->insert($add); 
			//发货通知
			if($res){
				$order_goods  = Db::name('order_goods')->where($map2)->field('og_goods_name,og_uid')->find();
				if($order_goods){
					$usersInfo = Db::name('users')->where('user_id',$order_goods['og_uid'])->field('client_id,app_system')->find();
					if($usersInfo){
						$msg = [
						'content'=>$order_goods['og_goods_name'].'已经发货!',//透传内容
						'title'=>'发货提醒',//通知栏标题
						'text'=>$order_goods['og_goods_name'].'已经发货!',//通知栏内容
						];
						$clientids=array(
							['client_id'=>$usersInfo['client_id']],
							'system'=>$usersInfo['app_system'], 
						);
						//$Pushs = new Pushs();
						//$Pushs->getTypes($msg,$clientids);
					}
				
				}
			}
			return AjaxReturn($res,getErrorInfo($res));
		}else{
            $where['og_order_id']=input('get.order_id');
            //$where['og_supplier_id']=input('get.og_supplier_id');
            $where['og_id']=input('get.og_id');

			$orderInfo = Db::name('order_goods')->where($where)->field('og_order_id,og_supplier_id')->find();
			//获取订单详情
			//$map['order_id'] = $orderInfo['og_order_id'];
			$map['order_id'] = $where['og_order_id'];
			$row=$OrderService->find($map);
			 
            $user_model = new User();
            $status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货');
            $addr_info = $user_model->address($row['pro_name'],$row['city_name'],$row['area']);
            $row['addr_phone'] = $row['phone'];
            $row['og_id'] = $where['og_id'];
 
 
            $row['status_names'] =$status_arr[$row['order_status']];
            $row['create_time'] = date('Y-m-d H:i:s', $row['order_create_time']);
            $goods_list =$OrderService->getOrderGoods($row['order_id']);
			 
            $row['goods_list'] =  $goods_list;
			$this->assign('row',$row);

			//获取配送方式
			$ConfigService=new Config();
			$config=$ConfigService->find();		
			$postType=$config['shop']['postType'];
			$express=$config['express'];
			$express = json_decode($config['express'], true); 
			$express =$express['express']; 
			//获取销售员列表
            $sale_list = Db::name('admin')->where('group_id',16)->select();
            //获取配送员列表
            $post_list = Db::name('admin')->where('group_id',17)->select();
            $this->assign('sale_list',$sale_list);
            $this->assign('post_list',$post_list);
			$this->assign('og_supplier_id',$orderInfo['og_supplier_id']);
			$this->assign('postType',$postType);			
			$this->assign('express',$express);			
			return $this->fetch();
		}

   	}

    /*
    * 关闭订单
    */
   	public function close(){
   		$ids=input('get.ids');
        $array=explode(',',$ids);
        $res = $this->order->softDelete($array);
		return AjaxReturn($res);       		
   	} 
	/*
    *  打开发票详情
    */
   	public function invoice(){
		$map['order_id']=input('get.order_id');
		$OrderService = new OrderService();
		$row = $OrderService->find($map);
		$row['invoice_type'] =  $row['invoice_type']== 1?'电子发票':'纸质发票';
		$row['header'] =  $row['invoice_header']== 1?'个人':'公司';
		$row['order_create_time'] = date('Y-m-d H:i:s',$row['order_create_time']);//下单时间
		$row['order_pay_time'] = date('Y-m-d H:i:s',$row['order_pay_time']);//支付时间
		//调用订单商品信息
		$data =  $OrderService->getOrderGoodsinfo($map['order_id']);
		//获取店名称
		$StoreName =  $OrderService->getOrderStore($row['order_storeid']);
		$data[0]['s_name'] = $StoreName;
		$this->assign('row',$row);
		$this->assign('data',$data[0]);
		return $this->fetch();	
   	}
	/*
    *  查看物流详情
    */
   	public function logistic(){
		$map['order_id']=input('get.order_id');
        $where['og_supplier_id']=input('get.og_supplier_id');
	 	
		$OrderService = new OrderService();
		$row = $OrderService->getPhysical($map,$where);
		//物流状态
		$wuliu = $OrderService->lookInfo($row['order_id'],$row['order_goods'][0]['og_goods_id']);
		$this->assign('wuliu',$wuliu['data']);
		 //获取地址详情
		$user = new UserService();
		$addr_info = $user->address($row['pro_name'],$row['city_name'],$row['area']);
		$row['address'] = $addr_info['pca_address'];
		//调用订单商品信息
		$data =  $OrderService->postInfo('',$map['order_id']);

		//获取配送方式
		$ConfigService=new Config();
		$config=$ConfigService->find();		
		$postType=$config['shop']['postType'];
		$express=$config['express'];
		$express = json_decode($config['express'], true); 
		$express =$express['express'];

		$this->assign('express',$express);		
		
		$this->assign('data',$data);
		$this->assign('row',$row);
		return $this->fetch();	
   	}
	/*
    *  查看订单详情
    */
   	public function particulars(){
		//$map['og_supplier_id']=input('get.og_supplier_id');
		$map['order_id']=input('get.order_id');
		//$map['og_id']=input('get.og_id');
		 
		$OrderService = new OrderService();
		$row = $OrderService->particulars($map);
		 //获取地址详情
		$user = new UserService();
		$addr_info = $user->address($row['pro_name'],$row['city_name'],$row['area']);
        $row['addr_phone'] = $row['phone']; 
        $row['addr_ress'] =$addr_info.$row['phone']; 
		$row['order_type'] = $row['order_type']==0?'普通订单':'积分兑换订单';
		if($row){
			//是否需要发票：0，否；1，是
			if($row['need_invoice'] == 1){
				$row['invoice_type'] = $row['invoice_type']= 1?'电子发票':'纸质发票';
			}else{
				$row['invoice_header']  ='';
			}
			$row['need_invoice'] = $row['need_invoice']= 1?'是':'否';
			//发票类型：1，电子发票；2，纸质发票
			if($row['order_pay_time'] == 0){
				$row['order_pay_time'] = '未支付';
			}else{
				$row['order_pay_time'] = date('Y-m-d H:i:s',$row['order_pay_time']);
			}
			//支付方式
			$pay_type = [
				'balance'=>'余额支付',
				'alipay'=>'支付宝',
				'wxpay'=>'微信支付',
                'offpay'=>'货到付款',
				'积分支付'=>'积分支付',
				'jsapi'=>'公众号支付'
			];
			$row['order_pay_code'] = $pay_type[$row['order_pay_code']];
			//$row['order_pay_price'] =  $row['og_goods_price']*$row['og_goods_num'];
			if(!$row['order_pay_points']){
				$row['order_pay_points'] = 0.00;
			}
			$row['yin_amount'] =  0.00;
			if($row['yz_id']){
				$res  = Db::name('yinzi')->where('yin_id',$row['yz_id'])->field('yin_amount')->find();
				if($res){
					$row['yin_amount'] = $res['yin_amount'];
				}
			}
			$row['card_price'] =  0.00;
			if($row['rc_id']){
				$res  = Db::name('user_rc')->where('card_id',$row['rc_id'])->field('card_price')->find();
				if($res){
					$row['card_price'] = $res['card_price'];
				}
			}
			//优惠券
			$row['c_coupon_price'] = 0.00;
			if($row['order_coupon_id']){
				$coupon_data = Db::name('coupon_users')->where('c_id',$row['order_coupon_id'])->find();
				$row['c_coupon_price'] = $coupon_data['c_coupon_price'];
			}
            foreach ($row['order_goods'] as $v){
                $row['Amount_payable'] += $v['og_goods_price']*$v['og_goods_num'];
                $row['order_pay_price'] +=  $row['og_goods_price']*$row['og_goods_num'];
            }

			//$row['order_pay_price'] -= $res['yin_amount'];
			//$row['order_pay_price'] -= $row['c_coupon_price'];
			//$row['order_pay_price'] -= $row['order_pay_points'];
			//$row['order_pay_price'] -= $row['order_discount'];
			//$row['order_pay_price'] -= $row['rc_amount'];
			//$row['order_pay_price'] -= $row['order_prom_amount'];
			//$row['order_pay_price'] += $row['og_freight'];
			if($row['order_pay_price']<=0){
				$row['order_pay_price'] = 0;
			}
			if ($row['pro_name']) {
                $row['pro_name'] = Db::name('region')->where(['region_id' => $row['pro_name'] ])->value('region_name');
            }
            if ($row['city_name']) {
                $row['city_name'] = Db::name('region')->where(['region_id' => $row['city_name'] ])->value('region_name');
            }
            if ($row['area']) {
                $row['area'] = Db::name('region')->where(['region_id' => $row['area'] ])->value('region_name');
            }
		}
		//订单状态
//		/dump($row);die;
		$row['order_status'] = $OrderService->getStatus($row['order_status']);
		
		$this->assign('row',$row);
		return $this->fetch();	
   	}	
 /*
    *  修改收货地址
    */
   	public function editaddr(){
		$OrderService = new OrderService();
		if(request()->isAjax()){
			$row=input('post.row/a');
			$map['order_id'] = input('post.order_id');
            $res=$OrderService->save($map,$row);

            //添加日志记录
            $this->write_log('修改收货地址',$map['order_id']);

            return AjaxReturn($res,getErrorInfo($res));
		}else{
		//$map['og_supplier_id']=input('get.og_supplier_id');
		$map['order_id']=input('get.order_id');
	
		$row = $OrderService->particulars($map);
		 //获取地址详情
		/* $user = new UserService();
		$addr_info = $user->addrInfo($row['order_uid'], $row['order_addrid']); */
		$row['proname'] = $OrderService->getAddrName($row['pro_name']);
		$row['cityname'] = $OrderService->getAddrName($row['city_name']);
		$row['areaname'] = $OrderService->getAddrName($row['area']);
		 
		if($row){
			//是否需要发票：0，否；1，是
			if($row['need_invoice'] == 1){
				$row['invoice_type'] = $row['invoice_type']= 1?'电子发票':'纸质发票';
			}else{
				$row['invoice_header']  ='';
			}
			$row['need_invoice'] = $row['need_invoice']= 1?'是':'否';
			//发票类型：1，电子发票；2，纸质发票
			if($row['order_pay_time'] == 0){
				$row['order_pay_time'] = '未支付';
			}else{
				$row['order_pay_time'] = date('Y-m-d H:i:s',$row['order_pay_time']);
			}
			//支付方式
			$pay_type = [
				'balance'=>'余额支付',
				'alipay'=>'支付宝',
				'wxpay'=>'微信支付',
				'积分支付'=>'积分支付',
			];
			$row['order_pay_code'] = $pay_type[$row['order_pay_code']];
			$row['order_pay_price'] =  $row['og_goods_price']*$row['og_goods_num'];
			if(!$row['order_pay_points']){
				$row['order_pay_points'] = 0.00;
			}
				$row['yin_amount'] =  0.00;
			if($row['yz_id']){
				$res  = Db::name('yinzi')->where('yin_id',$row['yz_id'])->field('yin_amount')->find();
				if($res){
					$row['yin_amount'] = $res['yin_amount'];
				}
			}
		    $row['card_price'] =  0.00;
			if($row['rc_id']){
				$res  = Db::name('user_rc')->where('card_id',$row['rc_id'])->field('card_price')->find();
				if($res){
					$row['card_price'] = $res['card_price'];
				}
			}
			$row['order_pay_price'] =  $row['og_goods_price']*$row['og_goods_num'];
			$row['order_pay_price'] -= $row['yin_amount'];
			$row['order_pay_price'] -= $row['card_price'];
			$row['cityname'] = $OrderService->getAddrName($row['city_name']);
			$row['areaname'] = $OrderService->getAddrName($row['area']);
			
		}
		//订单状态
		$row['order_status'] = $OrderService->getStatus($row['order_status']);
		$this->assign('row',$row);
		
		//地址
		$addlist = $OrderService->getAddrs();
		$this->assign('addlist',$addlist);
		//获取对应市区名称
		$citylist = $OrderService->getAddrs($row['pro_name']);
		$this->assign('citylist',$citylist);
		
		//获取对应区名称
		$arealist = $OrderService->getAddrs($row['city_name']);
		$this->assign('arealist',$arealist);
		
		return $this->fetch();	
		}    
	}
	
	
	/*
    *  获取地址列表
    */
   	public function getaddrs(){	
		$OrderService = new OrderService();
        $map['parent_id']=input('parent_id');
        $info = $OrderService->getAddrs($map['parent_id']);
        if (!$info) {
            $info =array();
        }
		// var_dump($info);
        return $info;
	}
	/*
    *   实际支付金额
    */
   	public function editpay(){
		if(request()->isAjax()){
			$row = input('post.row/a');
			$order_id = input('order_id');
			$res =Db::name('order')->where('order_id',$order_id)->update($row);

			//添加日志记录
            $this->write_log('修改支付金额',$order_id);

			if($res == false){
				return AjaxReturn($res,getErrorInfo($res));
			}
			return AjaxReturn(true);
		}else{
			$map['a.order_id']=input('get.order_id');
			$OrderService = new OrderService();
			$row = $OrderService->particulars($map);
			 //获取地址详情
			$user = new UserService();
//			$addr_info = $user->addrInfo($row['order_uid'], $row['order_addrid']);
//			$row['address'] = $addr_info['pca_address'];
//			$row['addr_area'] = $addr_info['district'];
            $row['order_type'] = $row['order_type']==0?'普通订单':'积分兑换订单';
            if ($row['pro_name']) {
                $row['pro_name'] = Db::name('region')->where(['region_id' => $row['pro_name'] ])->value('region_name');
            }
            if ($row['city_name']) {
                $row['city_name'] = Db::name('region')->where(['region_id' => $row['city_name'] ])->value('region_name');
            }
            if ($row['area']) {
                $row['area'] = Db::name('region')->where(['region_id' => $row['area'] ])->value('region_name');
            }
            $row['address'] = $row['pro_name'].' '.$row['city_name'].' '.$row['area'].' '.$row['address'];
			if($row){
				//是否需要发票：0，否；1，是
				if($row['need_invoice'] == 1){
					$row['invoice_type'] = $row['invoice_type']= 1?'电子发票':'纸质发票';
				}else{
					$row['invoice_header']  ='';
				}
				$row['need_invoice'] = $row['need_invoice']= 1?'是':'否';
				//发票类型：1，电子发票；2，纸质发票
				if($row['order_pay_time'] == 0){
					$row['order_pay_time'] = '未支付';
				}else{
					$row['order_pay_time'] = date('Y-m-d H:i:s',$row['order_pay_time']);
				}
				//支付方式
				$pay_type = [
					'balance'=>'余额支付',
					'alipay'=>'支付宝',
					'wxpay'=>'微信支付',
					'积分支付'=>'积分支付',
				];
				$row['order_pay_code'] = $pay_type[$row['order_pay_code']];
				if(!$row['order_pay_points']){
					$row['order_pay_points'] = 0.00;
				}
				if($row['yz_id']){
					$res  = Db::name('yinzi')->where('yin_id',$row['yz_id'])->field('yin_amount')->find();
					if($res){
						$row['yin_amount'] = $res['yin_amount'];
					}
				}
				$row['yin_amount'] =  0.00;
				if($row['rc_id']){
					$res  = Db::name('user_rc')->where('card_id',$row['rc_id'])->field('card_price')->find();
					if($res){
						$row['card_price'] = $res['card_price'];
					}
				}
				$row['card_price'] =  0.00;
			}
			//订单状态
			$row['order_status'] = $OrderService->getStatus($row['order_status']);
			$this->assign('row',$row);
			return $this->fetch();	
		}
   	}
	/*
    *  查看今日订单
    */
   	public function orderTody(){
		$OrderService=new OrderService();
    	//超过72小时自动收货
    	$time=72*3600;
    	$maps['order_finish_time']=['<',time()-$time];
    	$maps['order_status']=2;
    	//3，待评价；4，已完成
    	//$OrderService->save($maps,['order_status'=>4]);
    	$OrderService->save($maps,['order_status'=>3]);
		$OrderInfo = $OrderService->find($maps);
		if($OrderInfo){
			foreach($OrderInfo as $val){
				Db::name('order_goods')->where('og_order_id',$val['order_id'])->update(['og_order_status'=>3]);
			}
			
		}

        $order_no = trim(input('order_no'));
        $order_status = input('order_status');
	
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('order_no',$order_no);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
		if(request()->isAjax()){
			$map=[];
			//排序
			$order="og_id desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			
		    $map['order_isdel'] = 0;
            $map['order_create_time'] = ['egt',strtotime(date("Y-m-d"))];
			list($rows,$total)  = $OrderService->getOrderinfos($map,$order,$limit);
			// 供应商订单
			$supplierId =session('supplier_id');
 
			if($supplierId){
				if($order_status){
					$map2['b.order_status']=$order_status;
					if($order_status == '11269'){
						$map2['order_status'] = ['eq', 0];
					}
				}
				 if ($start_time && $end_time) {
					$map2['b.order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
				} elseif ($start_time) {
					$map2['b.order_create_time'] = array('>=',strtotime($start_time));
				} elseif ($end_time) {
					$map2['b.order_create_time'] = array('<=', strtotime($end_time));
				}
				$map2['b.order_isdel'] = 0;
				$map2['a.og_supplier_id'] = $supplierId;
				$rows=$OrderService->getOrders($map2,$order,$limit);
				$total = $OrderService->getOrders($map2,$order,'');
				$total= count($total);
			}

			$user_model = new User();
			$goods_model = new Goods();
			// 状态0，待付款；1，待发货；2，待收货；3，待评价；4，已完成；5，已取消；6，申请退货；7，申请换货；'
			$status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货');
		 
			if ($rows) {
				$page_price = 0;
			    foreach ($rows as $key=>$val) {
			        $addr_info = $user_model->address($val['pro_name'],$val['city_name'],$val['area']);
			        $rows[$key]['addr_phone'] = $val['phone'];
			        $rows[$key]['addr_receiver'] = $val['consigee'];
					
			        $rows[$key]['order_create_time'] = date('Y-m-d H:i:s', $val['order_create_time']);
					$rows[$key]['order_type']  =  $val['order_type'] == 0? '普通订单':'积分兑换订单';	
					$rows[$key]['pick_status']  =  $val['pick_status'] == 0? '否':'是';
                    $goods_list = $OrderService->getOrderGoodsinfo($val['og_id']);
			        $goods_name_array =  $goods_list['og_goods_name'];
			        $supplier_str = $goods_list['og_supplier_id']; 
					$supplier_name = $OrderService->getSupplierName($supplier_str);
				 
			        $rows[$key]['goods_name'] = implode(',',$goods_name_array);
			        $rows[$key]['supplier_name'] = implode(',',$supplier_name);
					$page_price += $val['order_pay_price']; 
					$rows[$key]['page_price'] = round($page_price,2);
					// 总计销售额
					$total_price = Db::name('order')->where($map)->sum('order_pay_price');
					$rows[$key]['total_price'] = round($total_price,2);
					//订单商品 发货 订单未发货
					if(($val['order_status'] == 1)&&($val['og_order_status']==2)){
						$rows[$key]['order_status'] = 2;
						$val['order_status'] = 2;
					}

					$rows[$key]['status_names'] =$status_arr[$val['order_status']];
					 
					//	HT17826421542766858	
                }
				
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			$this->assign('order_status',$order_status);
			return $this->fetch();
		}    	
   	}
	/*
    *  未处理订单
    */
   	public function orderWei(){
		$OrderService=new OrderService();
    	//超过72小时自动收货
    	$time=72*3600;
    	$maps['order_finish_time']=['<',time()-$time];
    	$maps['order_status']=2;
    	//3，待评价；4，已完成
    	//$OrderService->save($maps,['order_status'=>4]);
    	$OrderService->save($maps,['order_status'=>3]);
		$OrderInfo = $OrderService->find($maps);
		if($OrderInfo){
			foreach($OrderInfo as $val){
				Db::name('order_goods')->where('og_order_id',$val['order_id'])->update(['og_order_status'=>3]);
			}
			
		}

        $order_no = trim(input('order_no'));
        $order_status = input('order_status');
	
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('order_no',$order_no);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
		if(request()->isAjax()){
			$map=[];
			//排序
			$order="og_id desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			
            $map['order_status'] = ['eq',1];
			list($rows,$total)  = $OrderService->getOrderinfos($map,$order,$limit);
			// 供应商订单
			$supplierId =session('supplier_id');
 
			if($supplierId){
				if($order_status){
					$map2['b.order_status']=$order_status;
					if($order_status == '11269'){
						$map2['order_status'] = ['eq', 0];
					}
				}
				 if ($start_time && $end_time) {
					$map2['b.order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
				} elseif ($start_time) {
					$map2['b.order_create_time'] = array('>=',strtotime($start_time));
				} elseif ($end_time) {
					$map2['b.order_create_time'] = array('<=', strtotime($end_time));
				}
				$map2['b.order_isdel'] = 0;
				$map2['a.og_supplier_id'] = $supplierId;
				$rows=$OrderService->getOrders($map2,$order,$limit);
				$total = $OrderService->getOrders($map2,$order,'');
				$total= count($total);
			}

			$user_model = new User();
			$goods_model = new Goods();
			// 状态0，待付款；1，待发货；2，待收货；3，待评价；4，已完成；5，已取消；6，申请退货；7，申请换货；'
			$status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货');
		 
			if ($rows) {
				$page_price = 0;
			    foreach ($rows as $key=>$val) {
			        $addr_info = $user_model->address($val['pro_name'],$val['city_name'],$val['area']);
			        $rows[$key]['addr_phone'] = $val['phone'];
			        $rows[$key]['addr_receiver'] = $val['consigee'];
					
			        $rows[$key]['order_create_time'] = date('Y-m-d H:i:s', $val['order_create_time']);
					$rows[$key]['order_type']  =  $val['order_type'] == 0? '普通订单':'积分兑换订单';	
					$rows[$key]['pick_status']  =  $val['pick_status'] == 0? '否':'是';
                    $goods_list = $OrderService->getOrderGoodsinfo($val['og_id']);
			        $goods_name_array =  $goods_list['og_goods_name'];
			        $supplier_str = $goods_list['og_supplier_id']; 
					$supplier_name = $OrderService->getSupplierName($supplier_str);
				 
			        $rows[$key]['goods_name'] = implode(',',$goods_name_array);
			        $rows[$key]['supplier_name'] = implode(',',$supplier_name);
					$page_price += $val['order_pay_price']; 
					$rows[$key]['page_price'] = round($page_price,2);
					// 总计销售额
					$total_price = Db::name('order')->where($map)->sum('order_pay_price');
					$rows[$key]['total_price'] = round($total_price,2);
					//订单商品 发货 订单未发货
					if(($val['order_status'] == 1)&&($val['og_order_status']==2)){
						$rows[$key]['order_status'] = 2;
						$val['order_status'] = 2;
					}
					$rows[$key]['status_names'] =$status_arr[$val['order_status']];
					 
					//	HT17826421542766858	
                }
				
            }
				 
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			$this->assign('order_status',$order_status);
			return $this->fetch();
		}    	
   	}
	/*
    *  一周订单
    */
   	public function orderWeek(){
		$OrderService=new OrderService();
    	//超过72小时自动收货
    	$time=72*3600;
    	$maps['order_finish_time']=['<',time()-$time];
    	$maps['order_status']=2;
    	//3，待评价；4，已完成
    	//$OrderService->save($maps,['order_status'=>4]);
    	$OrderService->save($maps,['order_status'=>3]);
		$OrderInfo = $OrderService->find($maps);
		if($OrderInfo){
			foreach($OrderInfo as $val){
				Db::name('order_goods')->where('og_order_id',$val['order_id'])->update(['og_order_status'=>3]);
			}
			
		}
		
        $order_no = trim(input('order_no'));
        $order_status = input('order_status');
	
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('order_no',$order_no);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
		if(request()->isAjax()){
			$map=[];
			//排序
			// $order="og_id desc";
            $order = '';
			//limit
			$limit=input('get.offset').",".input('get.limit');
			
			$map['order_isdel'] = 0;
			$start=strtotime(date('Y-m-d',strtotime("-7 days")));
			$end=time();
			$map['order_create_time']=['BETWEEN',[$start,$end]];
		
			$total = $OrderService->count($map);
			$rows = $OrderService->select($map,'*',$order,$limit);
		
			list($rows,$total)  = $OrderService->getOrderinfos($map,$order,$limit);
			// 供应商订单
			$supplierId =session('supplier_id');
 
			if($supplierId){
				if($order_status){
					$map2['b.order_status']=$order_status;
					if($order_status == '11269'){
						$map2['order_status'] = ['eq', 0];
					}
				}
				 if ($start_time && $end_time) {
					$map2['b.order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
				} elseif ($start_time) {
					$map2['b.order_create_time'] = array('>=',strtotime($start_time));
				} elseif ($end_time) {
					$map2['b.order_create_time'] = array('<=', strtotime($end_time));
				}
				$map2['b.order_isdel'] = 0;
				$map2['a.og_supplier_id'] = $supplierId;
				$rows=$OrderService->getOrders($map2,$order,$limit);
				$total = $OrderService->getOrders($map2,$order,'');
				$total= count($total);
			}

			$user_model = new User();
			$goods_model = new Goods();
			// 状态0，待付款；1，待发货；2，待收货；3，待评价；4，已完成；5，已取消；6，申请退货；7，申请换货；'
			$status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货');
		
			if ($rows) {
				$page_price = 0;
			    foreach ($rows as $key=>$val) {
			        $addr_info = $user_model->address($val['pro_name'],$val['city_name'],$val['area']);
			        $rows[$key]['addr_phone'] = $val['phone'];
			        $rows[$key]['addr_receiver'] = $val['consigee'];
					
			        $rows[$key]['order_create_time'] = date('Y-m-d H:i:s', $val['order_create_time']);
					$rows[$key]['order_type']  =  $val['order_type'] == 0? '普通订单':'积分兑换订单';	
					$rows[$key]['pick_status']  =  $val['pick_status'] == 0? '否':'是';
                    $goods_list = $OrderService->getOrderGoodsinfo($val['og_id']);
			        $goods_name_array =  $goods_list['og_goods_name'];
			        $supplier_str = $goods_list['og_supplier_id']; 
					$supplier_name = $OrderService->getSupplierName($supplier_str);
				 
			        $rows[$key]['goods_name'] = implode(',',$goods_name_array);
			        $rows[$key]['supplier_name'] = implode(',',$supplier_name);
					$page_price += $val['order_pay_price']; 
					$rows[$key]['page_price'] = round($page_price,2);
					// 总计销售额
					$total_price = Db::name('order')->where($map)->sum('order_pay_price');
					$rows[$key]['total_price'] = round($total_price,2);
					//订单商品 发货 订单未发货
					if(($val['order_status'] == 1)&&($val['og_order_status']==2)){
						$rows[$key]['order_status'] = 2;
						$val['order_status'] = 2;
					}
					$rows[$key]['status_names'] =$status_arr[$val['order_status']];
					 
					//	HT17826421542766858	
                }
				
            }
				 
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			$this->assign('order_status',$order_status);
			return $this->fetch();
		}    	
   	}
	/*
    *  一月订单
    */
   	public function orderMonth(){
		$OrderService=new OrderService();
    	//超过72小时自动收货
    	$time=72*3600;
    	$maps['order_finish_time']=['<',time()-$time];
    	$maps['order_status']=2;
    	//3，待评价；4，已完成
    	//$OrderService->save($maps,['order_status'=>4]);
    	$OrderService->save($maps,['order_status'=>3]);
		$OrderInfo = $OrderService->find($maps);
		if($OrderInfo){
			foreach($OrderInfo as $val){
				Db::name('order_goods')->where('og_order_id',$val['order_id'])->update(['og_order_status'=>3]);
			}
			
		}

        $order_no = trim(input('order_no'));
        $order_status = input('order_status');
	
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('order_no',$order_no);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
		if(request()->isAjax()){
			$map=[];
			//排序
			$order="og_id desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
 
		    $map['order_isdel'] = 0;
		
            $start=strtotime(date('Y-m-d',strtotime("-30 days")));
            $end = time();
			$map['order_create_time']=['BETWEEN',[$start,$end]];
			list($rows,$total)  = $OrderService->getOrderinfos($map,$order,$limit);
			// 供应商订单
			$supplierId =session('supplier_id');
 
			if($supplierId){
				if($order_status){
					$map2['b.order_status']=$order_status;
					if($order_status == '11269'){
						$map2['order_status'] = ['eq', 0];
					}
				}
				 if ($start_time && $end_time) {
					$map2['b.order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
				} elseif ($start_time) {
					$map2['b.order_create_time'] = array('>=',strtotime($start_time));
				} elseif ($end_time) {
					$map2['b.order_create_time'] = array('<=', strtotime($end_time));
				}
				$map2['b.order_isdel'] = 0;
				$map2['a.og_supplier_id'] = $supplierId;
				$rows=$OrderService->getOrders($map2,$order,$limit);
				$total = $OrderService->getOrders($map2,$order,'');
				$total= count($total);
			}

			$user_model = new User();
			$goods_model = new Goods();
			// 状态0，待付款；1，待发货；2，待收货；3，待评价；4，已完成；5，已取消；6，申请退货；7，申请换货；'
			$status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货');
		 
			if ($rows) {
				$page_price = 0;
			    foreach ($rows as $key=>$val) {
			        $addr_info = $user_model->address($val['pro_name'],$val['city_name'],$val['area']);
			        $rows[$key]['addr_phone'] = $val['phone'];
			        $rows[$key]['addr_receiver'] = $val['consigee'];
					
			        $rows[$key]['order_create_time'] = date('Y-m-d H:i:s', $val['order_create_time']);
					$rows[$key]['order_type']  =  $val['order_type'] == 0? '普通订单':'积分兑换订单';	
					$rows[$key]['pick_status']  =  $val['pick_status'] == 0? '否':'是';
                    $goods_list = $OrderService->getOrderGoodsinfo($val['og_id']);
			        $goods_name_array =  $goods_list['og_goods_name'];
			        $supplier_str = $goods_list['og_supplier_id']; 
					$supplier_name = $OrderService->getSupplierName($supplier_str);
				 
			        $rows[$key]['goods_name'] = implode(',',$goods_name_array);
			        $rows[$key]['supplier_name'] = implode(',',$supplier_name);
					$page_price += $val['order_pay_price']; 
					$rows[$key]['page_price'] = round($page_price,2);
					// 总计销售额
					$total_price = Db::name('order')->where($map)->sum('order_pay_price');
					$rows[$key]['total_price'] = round($total_price,2);
					//订单商品 发货 订单未发货
					if(($val['order_status'] == 1)&&($val['og_order_status']==2)){
						$rows[$key]['order_status'] = 2;
						$val['order_status'] = 2;
					}
					$rows[$key]['status_names'] =$status_arr[$val['order_status']];
					 
					//	HT17826421542766858	
                }
				
            }
				 
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			$this->assign('order_status',$order_status);
			return $this->fetch();
		}    	
   	}
	/*
    *  售后管理
    */
   	public function management(){
		$OrderService=new OrderService();
		// 售后 退款和 退货 财务 确认退款后 自动 完成， 换货（供应商重新发货）需要自动结束
    	//$OrderService->judeOrder();
        $after_status = input('after_status');
        //var_dump($after_status);die;
        $audit_no =  trim(input('audit_no'));
        $start_time = input('start_time');
        $end_time = input('end_time');
		if(request()->isAjax()){
			$map=[];
			//排序
			$order="apply_time desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
            if(input('start_time')){
                $start_time = str_replace('+',' ',input('start_time'));
            }
            if(input('end_time')){
                $end_time = str_replace('+',' ',input('end_time'));
            }
            if ($start_time && $end_time) {
                $map3['order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
            } elseif ($start_time) {
                $map3['order_create_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map3['order_create_time'] = array('<=', strtotime($end_time));
            }
            if($map3){
                $order_id = Db::name('order')->where($map3)->column('order_id');
                $order_str = implode(',',$order_id);
                $map['og_order_id'] =  ['in',$order_str];
            }

            if($audit_no){
                $og_order_id = Db::name('sh_info')->where('audit_no',$audit_no)->value('og_order_id');
                $map['audit_no'] = $audit_no;
            }
			//订单售后状态 1，申请退货；2，申请换货
			if(($after_status !=4)&&($after_status)){
				$map['after_state_status']=$after_status;
			}else{
                $where = 'after_state_status != 0 ';
			}
			$uid = session('admin_id');
			$group_id = Db::name('admin')->where('admin_id',$uid)->value('group_id');
			if($group_id == 13){
				 $map['supplier_status'] = ['eq',2];
//				 $map['supplier_post_status'] = ['eq',1];
			}

            $rows = Db::name('sh_info')->where($where)->where($map)->limit($limit)->order($order)->select();
			if ($rows){
			    foreach ( $rows as $key=>$val){
                    $orderInfo = Db::name('order')->where('order_id',$val['og_order_id'])->find(); 
					$supplierInfo = Db::name('supplier')->where('id',$val['og_supplier_id'])->field('supplier_title')->find();
                    $rows[$key]['supplier_title'] =   $supplierInfo['supplier_title'];
					$rows[$key]['order_no'] =   $orderInfo['order_no'];
                    $rows[$key]['order_addrid'] =   $orderInfo['order_addrid'];
                    $rows[$key]['post_status'] =   $orderInfo['post_status'];
                    $rows[$key]['order_create_time'] =   $orderInfo['order_create_time'];
                    $rows[$key]['order_type'] = $orderInfo['order_type'];
                }
            }
            $total =  Db::name('sh_info')->where($where)->where($map)->order($order)->count() ;
			// 供应商订单 
			$uid = session('admin_id');
			$group_id = session('group_id');
			$supplierId =$OrderService->getsupplier($uid);
			 
			if($supplierId){
                //订单售后状态 1，申请退货；2，申请换货
                if($after_status !=4 && $after_status){
                    $map2['a.after_state_status']=$after_status;
                }else{
                    $where = 'a.after_state_status !=0';
                }
//                if($order_no){
//                    $map2['b.order_no']=$order_no;
//                }

                if(input('start_time')){
                    $start_time = str_replace('+',' ',input('start_time'));
                }
                if(input('end_time')){
                    $end_time = str_replace('+',' ',input('end_time'));
                }
                if ($start_time && $end_time) {
                    $map2['b.order_create_time'] = array('between',strtotime($start_time).','.strtotime($end_time) );
                } elseif ($start_time) {
                    $map2['b.order_create_time'] = array('>=',strtotime($start_time));
                } elseif ($end_time) {
                    $map2['b.order_create_time'] = array('<=', strtotime($end_time));
                }
				$map2['b.order_isdel'] = 0;
				$map2['a.og_supplier_id'] = $supplierId;
				$map2['a.status'] = ['eq',2];
				$rows=$OrderService->getShOrders($map2,$order,$limit,$where);
				$total= count($rows);
			}
			$user_model = new User();
			$goods_model = new Goods();
			// 状态0，待付款；1，待发货；2，待收货；3，待评价；4，已完成；5，已取消；6，申请退货；7，申请换货；8，卖家收货
			$status_arr = array('','申请退货','申请换货','仅退款');
			
			$post_arr = array('未配货','未发货','已发货','派送中','已收货','已退货','已换货');
			$group_id = session('group_id');
			//3	供应商 9 客服 13 财务
			if ($rows) {
			    foreach ($rows as &$val) {
					if(($group_id==3)&&($val['supplier_status']>1)){
						$val['state']=1; 
						if(($val['after_state_status'] !=3) &&($val['supplier_post_status']!=1)){
							$val['state']=0; 
						}
						
					}elseif(($group_id==9)&&($val['status']>1)){
						$val['state']=1; 
					}else if(($group_id==13)&&($val['financial_status']==3 || ($val['financial_status']==2 && $val['refund_status']==1))){
						$val['state']=1;
					}else if(($group_id==1)&&($val['financial_status']>1)){
						$val['state']=1; 
					}
					if($val['after_state_status'] == 2){
						if($group_id==13){
							$val['state']=1; 
						}else if(($group_id==1)&&($val['supplier_status']>1)){
							$val['state']=1; 
						}
					}
			        $addr_info = $user_model -> addrInfo($val['og_uid'], $val['order_addrid']);
			        $val['addr_phone'] = $addr_info['addr_phone'];
			        $val['addr_receiver'] = $addr_info['addr_receiver'];
			        $val['status_names'] =$status_arr[$val['after_state_status']];
			        $val['create_time'] = date('Y年m月d日', $val['order_create_time']);
				 
					$val['apply_time'] = date('Y年m月d日', $val['apply_time']);
					$val['order_type']  =  $val['order_type'] == 0? '普通订单':'积分兑换订单';
					if($supplierId){
						$supplierInfo = Db::name('supplier')->where('id',$supplierId)->field('supplier_title')->find();
						$val['supplier_title'] =   $supplierInfo['supplier_title'];
					}
					$val['post_status']  =  $post_arr[$val['post_status']];
                    $goods_list =$OrderService->getOrderGoodsinfo($val['order_id']);

			        //$goods_name_array = array_column($goods_list, 'og_goods_name');
			        $goods_name_array=Db::name('order_goods')->where(array('og_id' => $og_id))->column('og_goods_name');
			        $val['goods_name'] = implode(',',$goods_name_array);
					//$status = array_column($goods_list, 'status');
					$status=Db::name('order_goods')->where(array('og_id' => $og_id))->column('status');
					$row['status'] = implode(',',$status);
                }
            }

			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			$this->assign('after_status',$after_status);
			$this->assign('audit_no',$audit_no);
			$this->assign('end_time',$end_time);
			$this->assign('start_time',$start_time);
			return $this->fetch();
		}    	
	}
	/* 
    *  售后管理退换货 审核状态：0,正常 1,是未审核 2,审核通过 3,审核未通过 
    */
   	public function orderaudit(){
		$OrderService=new OrderService();
		if(request()->isPost()){
            //当前登录人员 客服
            $admin_id = session('admin_id');
            $admin_info = Db::name('admin')
                ->where('admin_id',$admin_id)
                ->field('admin_id,nickname,group_id,kf_id,supplier_id,u_id')
                ->find();
            $after_state_status = input('post.after_state_status');
			$map['og_id']=input('post.og_id');
			//var_dump($map['og_id']);die;
            $data['after_state_status']=input('post.after_state_status');
			$data['status']=input('post.status');
			$data['supplier_status']=input('post.supplier_status');
			$data['supplier_post_no']=input('post.supplier_post_no');
			$data['financial_status']=input('post.financial_status');
			if(input('post.refund_status')){
				$data['refund_status']=input('post.refund_status'); 
			}
            $order_refund_price = input('post.order_refund_price');
			if($order_refund_price){
				$data['og_refund_price'] = $order_refund_price;
            }else{
                // 退款金额 里面 包括 运费
                $sh_infos = Db::name('sh_info')->where(['og_id' =>$map['og_id'] ])->field('og_goods_pay_price,og_freight')->find();
                //获取订单信息
                $data['og_refund_price'] = $sh_infos['og_goods_pay_price'];
                //$data['og_refund_price'] = $sh_infos['og_goods_pay_price'] + $sh_infos['og_freight'];
			}

			if(input('post.post_agin_status') == 2){
				$data['post_agin_status']=input('post.post_agin_status'); 
				$supplier_post_time = Db::name('sh_info')->where($map)->value('supplier_post_time');
				if(!$supplier_post_time){
					$data['supplier_post_time'] = time();
				}
			}
            $back_address  = '';
			$supplier_addr = input('post.supplier_addr');
			if($supplier_addr){
                $ad = [];
                $ad['supplier_addr'] = $supplier_addr;
                $ad['supplier_name'] = input('post.supplier_name');
                $ad['supplier_phone'] = input('post.supplier_phone');
            }
            if(isset($ad )){
                $back_address = json_encode($ad,JSON_UNESCAPED_UNICODE );
            }
            $data['back_address']= $back_address;

 
            $data['supplier_post_status']=input('post.supplier_post_status');
			$data['or_goods_note']= trim(strip_tags(input('post.or_goods_note')));
			$data['or_supplier_note']= trim(strip_tags(input('post.or_supplier_note')));
			$data['or_financial_note']= trim(strip_tags(input('post.or_financial_note')));
			$data['supplier_post_no']= input('post.supplier_post_no');
			$data['supplier_post_type']= input('post.supplier_post_type');

            //如果财务审核不通过，则必须要填写备注
            if($data['financial_status']==3 && empty($data['or_financial_note'])){
                return ['code'=>0,'msg'=>'请填写备注'];
            }

			$sh_info =  Db::name('sh_info')->field('after_state_status, refund_status')->where($map)->find();
			if ($sh_info['after_state_status'] == 1 || $sh_info['after_state_status'] == 3) {
                if ($sh_info['refund_status']) {
                    return AjaxReturn(ERROR);
                }
            }
            Db::startTrans();
            try{
                //退款金额
                /* print_r($data);
                return; */
                $res = Db::name('sh_info')->where($map)->update($data);
                //添加日志记录

                $this->write_log('订单审核',$map['og_id']);
                $maps['order_id']=input('post.order_id');
                //3	供应商 9 客服 13 财务
                if($res===false){
                    return AjaxReturn($res);
                }
                $res =  $this->updateData($admin_info,$data,$map['og_id']);
                // 提交事务
                Db::commit();
                return AjaxReturn($res,getErrorInfo($res));
            } catch (\Exception $e) {
                // 回滚事务
                var_dump($e->getMessage());
                Db::rollback();
                return AjaxReturn(ERROR);
            }

		}else{
          
			//获取订单详情
			$map['a.og_id']=input('get.ids');
            $this->write_log('订单审核',$map['a.og_id']);
			$row = Db::name('sh_info')
                ->alias('a')
                ->join('__ORDER__ b','b.order_id = a.og_order_id')
                ->field('a.*,b.*')
                ->where($map)
                ->find();
            $user_model = new User();
            $status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货','卖家收货'); 
			//物流状态：0，未配货；1，未发货；2，已发货；3，派送中；4，已收货；3，已退货；4，已换货',
			$status_post = array('未配货','未发货','已发货','派送中','已收货','已退货','已换货');
			//审核状态 0,正常 1,是未审核 2,审核通过 3,审核未通过
			$status_audit = array('正常','未审核','审核通过','审核未通过');
            $addr_info = $user_model -> addrInfo($row['order_uid'], $row['order_addrid']);	
 
            $row['addr_phone'] = $addr_info['addr_phone'];
            $row['addr_receiver'] = $addr_info['addr_receiver'];
            $row['address'] = $addr_info['addr_area'];
            $row['order_status_val'] =$status_arr[$row['order_status']];
            $row['supplier_status_val'] =$status_arr[$row['supplier_status']];
            $row['post_status_val'] =$status_arr[$row['post_status']];
            $row['create_time'] = date('Y-m-d H:i:s', $row['order_create_time']);
            // $goods_list =$OrderService->getOrderGoodsinfoss($row['og_id']);
            $goods_list = Db('sh_info')->where(['og_id' => $row['og_id']])->find();
			$goods_Info = Db('goods')->where('goods_id',$goods_list['og_goods_id'])->field('goods_numbers')->find();
			$row['goods_name'] =  $goods_list['og_goods_name'];
			$row['or_goods_note'] =  $goods_list['or_goods_note'];
			$row['status'] =  $goods_list['status'];
			$row['supplier_status'] =  $goods_list['supplier_status'];
			$row['financial_status'] =  $goods_list['financial_status'];
			$row['goods_numbers'] =  $goods_Info['goods_numbers'];
			$row['og_goods_num'] =  $goods_list['og_goods_num'];
			$row['og_goods_pay_price'] =  $goods_list['og_goods_pay_price'];
			$row['back_address'] =  $goods_list['back_address'];
			$row['supplier_post_no'] =  $goods_list['supplier_post_no'];
            $row['goods_info'] = Db::name('order_goods')->alias('og')->join('goods g','og.og_goods_id = g.goods_id','left')->field('og.*,g.goods_numbers')->where('og.audit_no',$row['audit_no'])->select();

			if($goods_list['supplier_post_time']){
                $row['supplier_post_time'] =  date('Y-m-d H:i',$goods_list['supplier_post_time']);
            }
            $row['supplier_post_time'] = $goods_list['supplier_post_time'];
 
            $row['or_supplier_note'] = $goods_list['or_supplier_note'];
            $row['or_financial_note'] = $goods_list['or_financial_note'];
            $row['status_val'] = $status_audit[ $row['status']];
            $row['supplier_status_val'] = $status_audit[ $row['supplier_status']];
            $row['financial_status_val'] = $status_audit[ $row['financial_status']];
			if(!$row['back_address']){
				$supplierInfo = Db::name('supplier')->where('id',$goods_list['og_supplier_id'])->find();
				/*$row['back_address'] = '  联系人：'.$supplierInfo['supplier_name'];
				$row['back_address'] .= ' 电话：'.$supplierInfo['supplier_phone'];
				$row['back_address'] .= ' 地址：'.$supplierInfo['supplier_addr'];*/
                $row['back_address']['supplier_name'] = $supplierInfo['supplier_name'];
                $row['back_address']['supplier_phone'] = $supplierInfo['supplier_phone'];
                $row['back_address']['supplier_addr'] = $supplierInfo['supplier_addr'];
			}else{
                $row['back_address'] = json_decode($row['back_address'],true);
            }
			//退款金额
			if(!$row['order_refund_price']){
				$row['order_refund_price'] = $row['og_goods_pay_price'] + $row['og_freight'];
			}
			//当前登录人员 客服 
			$admin_id = session('admin_id');
			$admin_info = Db::name('admin')
						->where('admin_id',$admin_id)
						->field('group_id')
						->find();
			if($admin_info['group_id']){
				$row['group_id'] = $admin_info['group_id'];
			}
			//3	供应商 9 客服 13 财务
			$this->assign('row',$row);

			//获取配送方式
			$ConfigService=new Config();
			$config=$ConfigService->find();		
			$postType=$config['shop']['postType'];
			$express=$config['express'];
			$express = json_decode($config['express'], true); 
			$express =$express['express'];
			$this->assign('postType',$postType);			
			$this->assign('express',$express);

			if($row['group_id'] == 1){
				 return $this->fetch('orderaudit3');	
			}
            //订单售后状态 1，申请退货；2，申请换货

			if($row['after_state_status'] == 1){
				 return $this->fetch('orderaudit2');	
			}

			return $this->fetch();
		}
		
	}
    /**
     * 判断 该售后 是否 要退元宝
     */
	public function tuihuiyuanbao($order_id, $uid)
    {
        // 统计该订单下 所有商品 是否 都已 退货 或 退款
        $order_goods_num = Db::name('order_goods')->where(['og_order_id' => $order_id])->count();
        $sh_num = Db::name('sh_info') -> where(['og_order_id' => $order_id, 'og_order_status' => 10]) -> count();
        // 如果 售后订单数量 大于 订单商品数量 则 证明 该订单 已全部退款，如果订单 使用了元宝 则 退回 元宝
        if ($order_goods_num <= $sh_num) {
            $yuanbao_id = Db::name('order')->where(['order_id' => $order_id])->value('yz_id');
            if ($yuanbao_id) {
                $result = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_id' => $yuanbao_id])->update(['yin_stat' => 2]);
                if($result){
                    //元宝日志
                    $yinzi_log=[
                        'y_log_yid'=>$yuanbao_id,
                        'y_log_uid'=> $uid,
                        'y_log_desc'=>'取消订单退回元宝',
                        'y_log_addtime'=>time(),
                    ];
                    Db::name('yinzi_log')->insert($yinzi_log);
                }
            }
            // 把订单状态 修改为 已完成
            Db::name('order')->where(['order_id' => $order_id])->update(['order_status' => 8]);
        }


    }
	/*
    *  售后审核查看
    */
   	public function orderaudits(){
		$OrderService=new OrderService();
		//获取订单详情
		$map['a.og_id']=input('get.ids');
		$this->write_log('订单审核',$map['a.og_id']);
		$row = Db::name('sh_info')
			->alias('a')
			->join('__ORDER__ b','b.order_id = a.og_order_id')
			->field('a.*,b.*')
			->where($map)
			->find();
		$user_model = new User();
		$status_arr = array('待付款','待发货','待收货','待评价','已完成','已取消','申请退货','申请换货','卖家收货'); 
		//物流状态：0，未配货；1，未发货；2，已发货；3，派送中；4，已收货；3，已退货；4，已换货',
		$status_post = array('未配货','未发货','已发货','派送中','已收货','已退货','已换货');
		//审核状态 0,正常 1,是未审核 2,审核通过 3,审核未通过
		$status_audit = array('正常','未审核','审核通过','审核未通过');
		$addr_info = $user_model -> addrInfo($row['order_uid'], $row['order_addrid']);	
		
		$row['addr_phone'] = $addr_info['addr_phone'];
		$row['addr_receiver'] = $addr_info['addr_receiver'];
		$row['address'] = $addr_info['addr_area'];
		$row['order_status_val'] =$status_arr[$row['order_status']];
		$row['supplier_status_val'] =$status_arr[$row['supplier_status']];
		$row['post_status_val'] =$status_arr[$row['post_status']];
		$row['create_time'] = date('Y-m-d H:i:s', $row['order_create_time']);
		$goods_list = Db('sh_info')->where(['og_id' => $row['og_id']])->find();
		
		$goods_Info = Db('goods')->where('goods_id',$goods_list['og_goods_id'])->field('goods_numbers')->find();
		$row['goods_name'] =  $goods_list['og_goods_name'];
		$row['or_goods_note'] =  $goods_list['or_goods_note'];
		$row['status'] =  $goods_list['status'];
		$row['supplier_status'] =  $goods_list['supplier_status'];
		$row['financial_status'] =  $goods_list['financial_status'];
		$row['goods_numbers'] =  $goods_Info['goods_numbers'];
		$row['og_goods_num'] =  $goods_list['og_goods_num'];
		$row['back_address'] =  $goods_list['back_address'];
		$row['supplier_post_no'] =  $goods_list['supplier_post_no'];
        $row['goods_info'] = Db::name('order_goods')->alias('og')->join('goods g','og.og_goods_id = g.goods_id','left')->field('og.*,g.goods_numbers')->where('og.audit_no',$row['audit_no'])->select();
		if($goods_list['supplier_post_time']){
			$row['supplier_post_time'] =  date('Y-m-d H:i',$goods_list['supplier_post_time']);
		}
		$row['supplier_post_time'] = $goods_list['supplier_post_time'];

		$row['or_supplier_note'] = $goods_list['or_supplier_note'];
		$row['or_financial_note'] = $goods_list['or_financial_note'];
		$row['status_val'] = $status_audit[ $row['status']];
		$row['supplier_status_val'] = $status_audit[ $row['supplier_status']];
		$row['financial_status_val'] = $status_audit[ $row['financial_status']];
		if(!$row['back_address']){
			$supplierInfo = Db::name('supplier')->where('id',$goods_list['og_supplier_id'])->find();
			/*$row['back_address'] = '  联系人：'.$supplierInfo['supplier_name'];
			$row['back_address'] .= ' 电话：'.$supplierInfo['supplier_phone'];
			$row['back_address'] .= ' 地址：'.$supplierInfo['supplier_addr'];*/
			$row['back_address']['supplier_name'] = $supplierInfo['supplier_name'];
			$row['back_address']['supplier_phone'] = $supplierInfo['supplier_phone'];
			$row['back_address']['supplier_addr'] = $supplierInfo['supplier_addr'];
		}else{
			$row['back_address'] = json_decode($row['back_address'],true);
		}
	 
		//退款金额
		if(!$row['order_refund_price']){
			$row['order_refund_price'] = $row['og_goods_pay_price'];
		}
		//当前登录人员 客服 
		$admin_id = session('admin_id');
		$admin_info = Db::name('admin')
					->where('admin_id',$admin_id)
					->field('group_id')
					->find();
		if($admin_info['group_id']){
			$row['group_id'] = $admin_info['group_id'];
		}
		//3	供应商 9 客服 13 财务
		$this->assign('row',$row);

		//获取配送方式
		$ConfigService=new Config();
		$config=$ConfigService->find();		
		$postType=$config['shop']['postType'];
		$express=$config['express'];
		$express = json_decode($config['express'], true); 
		$express =$express['express']; 
		
		$this->assign('postType',$postType);			
		$this->assign('express',$express);

		if($row['group_id'] == 1){
			 return $this->fetch('orderaudits3');	
		}
		//订单售后状态 1，申请退货；2，申请换货
		if($row['after_state_status'] == 1){
			 return $this->fetch('orderaudits2');	
		}
		return $this->fetch();
	}
	/* 
    *  售后管理修改状态
	* 申请售后状态：1，申请退货；2，申请换货
    * 3	供应商 9 客服 13 财务
    */
	
   	public function updateData($admin_info,$arr,$og_id){
        $desc_arr = ['客服审核','财务审核','供应商审核','供应商收货','财务退款','处理完成'];
		$agent_status = ['通过','拒绝','通过','拒绝'];
		$order_goods = Db::name('sh_info')->alias('a')->join('order b','a.og_order_id=b.order_id')->where('og_id',$og_id)->field('a.id,a.og_id,a.og_order_id,a.og_goods_id,a.og_goods_price,a.og_goods_num,a.og_uid,a.og_refund_price,a.refund_status,b.rc_id,b.rc_amount,a.after_state_status,b.order_pay_code,b.order_pay_no,b.order_no')->find();
		$og_goods_price = $order_goods['og_refund_price'];
        if($admin_info['group_id'] == 1){
            // 管理员
            $OrderService = new OrderService();

            $data = [
                'as_id' => $og_id,//售后id
                'agent_type' => 5,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户:,5:总管理员;
                'agent_id' => $admin_info['admin_id'],//经办人id;
                'agent_name' =>$admin_info['nickname'],//经办人名称
//                'as_log_desc' => '总管理员操作',//日志内容
                'as_status' => 2,
				'add_time'=>time(),
				'agent_status' => '审核中',
                'agent_note'=> '审核中',
				'as_log_desc' => $desc_arr[3],
				//售后进度状态：0，待审核；1，申请审核；2：审核中；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
            ];
           $OrderService->writelog($data);

            if($arr['supplier_post_status'] == 1){
				$data['as_status'] =3;
				$data['agent_status'] = '供应商已经收货';
                $data[ 'agent_note']= '供应商已经收货';
                $data['as_log_desc'] = $desc_arr[3];
				$OrderService->writelog($data);
                $OrderService->asOrder($og_id,4);
            }
		 
			if($arr['financial_status']>1){
				$data['agent_status'] = $agent_status[$arr['financial_status']];
                $data[ 'agent_note']=$arr['or_financial_note'];
				if($arr['financial_status'] == 3){
					$OrderService->asOrder($og_id,6);
					$this->shJujue($og_id);
				}
			}else if($arr['supplier_status']>1){
				$data['agent_status'] = $agent_status[$arr['supplier_status']];
                $data[ 'agent_note']=$arr['or_supplier_note'];
				if($arr['supplier_status'] == 3){
					$OrderService->asOrder($og_id,6);
                    $this->shJujue($og_id);
				}
			}else if($arr['status']>1){
				$data['agent_status'] = $agent_status[$arr['status']];
                $data[ 'agent_note']=$arr['or_goods_note'];
				if($arr['status'] == 3){
					$OrderService->asOrder($og_id,6);
                    $this->shJujue($og_id);
				}
			}
			if(($order_goods['after_state_status'] == 2)&& ($arr['post_agin_status'] == 2)){
				$data ['as_status']= 4;
				$data ['as_log_desc']= $desc_arr[4];
				$OrderService->writelog($data);
				$OrderService->asOrder($og_id,4); 
			}
            if($arr['refund_status'] == 1){
                $data['as_status'] = 4;
                $data['as_log_desc'] = $desc_arr[4];
				$OrderService->writelog($data);
				if($order_goods['refund_status']==1){
				    //退佣金
                    $this->CommissionRefund($order_goods['og_id']);
					if($order_goods['rc_id'] && $order_goods['rc_amount'] > 0){
					    // 充值卡和余额优先退充值卡 20190113
                        $rc_info = Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->field('card_balance,card_stat,card_uid')->find();
                        // 如果退款金额大于充值卡 则 先退充值卡 在退余额
                        if ($order_goods['rc_amount'] >= $og_goods_price) {
                            if ($rc_info['card_stat']== 2) {
                                Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->update(['card_stat' => 1, 'card_balance' => $og_goods_price]);
                                //充值卡记录
                    			$OrderService = new OrderService();
                    			$OrderService->add_rc_log($rc_info['card_uid'],$order_goods['rc_id'],$og_goods_price,1);
                            } else {
                                Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->setInc('card_balance', $og_goods_price);
                                //充值卡记录
                    			$OrderService = new OrderService();
                    			$OrderService->add_rc_log($rc_info['card_uid'],$order_goods['rc_id'],$og_goods_price,1);
                            }
                            Db::name('order') ->where(['order_id' => $order_goods['og_order_id']])->update(['rc_amount' => $order_goods['rc_amount'] - $og_goods_price]);
                        } else {
                            if ($rc_info['card_stat']== 2) {
                                Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->update(['card_stat' => 1, 'card_balance' => $order_goods['rc_amount']]);
                                //充值卡记录
                    			$OrderService = new OrderService();
                    			$OrderService->add_rc_log($rc_info['card_uid'],$order_goods['rc_id'],$order_goods['rc_amount'],1);
                            } else {
                                Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->setInc('card_balance', $order_goods['rc_amount']);
                                //充值卡记录
                    			$OrderService = new OrderService();
                    			$OrderService->add_rc_log($rc_info['card_uid'],$order_goods['rc_id'],$order_goods['rc_amount'],1);
                            }
                            $og_goods_price =$og_goods_price - $order_goods['rc_amount'];
                            //退余额
//                            if($order_goods['order_pay_code']=='balance'){
//
//                            }
                            if ($og_goods_price > 0 && !empty($order_goods['order_pay_code'])) {
                                $this->shRefund($order_goods,$og_goods_price);
                            }

                            Db::name('order') ->where(['order_id' => $order_goods['og_order_id']])->update(['rc_amount' => 0]);
                        }

					} else {
                        if ($og_goods_price > 0 && !empty($order_goods['order_pay_code'])) {
                            $this->shRefund($order_goods,$og_goods_price);
                        }
                        /*if ($og_goods_price > 0) {
                            $this->accountLog($order_goods['og_uid'],$og_goods_price);
                        }
						Db::name('users')->where('user_id',$order_goods['og_uid'])->setInc('user_account',$og_goods_price);*/
					}
				}

                $OrderService->asOrder($og_id, 5);
				$OrderService->setogorderstatus($og_id, 10);
                $this->tuihuiyuanbao($order_goods['og_order_id'], $order_goods['og_uid']);
                // 判断 该订单 正常进行的商品 有没有 全部发货
                $this->fahuoWancheng($order_goods['og_order_id']);
            }
        }elseif ($admin_info['group_id'] == 3){
            // 供应商
            $supplier =  Db::name('supplier')
                ->where('id',$admin_info['supplier_id'])
                ->field('supplier_name,id')
                ->find();
            if($supplier){
                $OrderService=new OrderService();
                $data = [
                    'as_id'=>$og_id,//售后id
                    'agent_type'=>2,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户;
                    'agent_id'=> $supplier['id'],//经办人id;
                    'agent_name'=>$supplier['supplier_name'],//经办人名称
                    'as_log_desc'=>$desc_arr[2],//日志内容
                    'agent_status'=>$agent_status[$arr['supplier_status']],
                    'agent_note'=>$arr['or_supplier_note'],
                    'add_time'=>time(),
                    'as_status'=>2,//售后进度状态：0，待审核；1，申请审核；2：审核中；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
                ];
				$OrderService->writelog($data);
                if($arr['supplier_post_status'] == 1){
                    $data['as_status'] = 3;
                    $data['as_log_desc'] = $desc_arr[3];
					$OrderService->writelog($data);
                    $OrderService->asOrder($og_id,4);
                }
                if($arr['status'] == 3){

                    $OrderService->asOrder($og_id,6);
                }
				if($order_goods['after_state_status'] == 2&& $arr['post_agin_status'] == 2){
					$data ['as_status']= 4;
                    $data ['as_log_desc']= $desc_arr[4];
					$OrderService->writelog($data);
                    $OrderService->asOrder($og_id,4);
				}
                if($arr['refund_status'] == 1){
                    $data ['as_status']= 4;
                    $data ['as_log_desc']= $desc_arr[4];
					$OrderService->writelog($data);
                    $OrderService->asOrder($og_id,4);
                }
                if($arr['supplier_status'] ==3){
					$OrderService->writelog($data);
                    $OrderService->asOrder($og_id,6);
                }
            }
        }elseif ($admin_info['group_id'] == 9){
			// 客服
            $kefu =  Db::name('kefu')
                ->where('kefu_id',$admin_info['kf_id'])
                ->field('kefu_id,kefu_name')
                ->find();

            if($kefu) {
                $OrderService = new OrderService();
                $data = [
                    'as_id' => $og_id,//售后id
                    'agent_type' => 1,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户;
                    'agent_id' => $kefu['kefu_id'],//经办人id;
                    'agent_name' => $kefu['kefu_name'],//经办人名称
					'agent_status'=>$agent_status[$arr['status']],
                    'agent_note'=>$arr['or_goods_note'],
                    'as_log_desc' => $desc_arr[0],//日志内容
                    'as_status' => 2,//售后进度状态：0，待审核；1，申请审核；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
                ];
                if($arr['status'] == 3){
                    $OrderService->asOrder($og_id,6);
                    $OrderService->writelog($data);
                }
            }
        }elseif($admin_info['group_id'] == 13){
            // 财务
                $OrderService = new OrderService();
                $data = [
                    'as_id' => $og_id,//售后id
                    'agent_type' => 3,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户;
                    'agent_id' => $admin_info['admin_id'],//经办人id;
                    'agent_name' =>$admin_info['nickname'],//经办人名称
					'agent_status'=>$agent_status[$arr['financial_status']],
                    'agent_note'=>$arr['or_financial_note'],
                    'as_log_desc' => $desc_arr[2],//日志内容
                    'as_status' =>2,//售后进度状态：0，待审核；1，申请审核；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
                ];
                if($arr['financial_status'] == 2 ){

                    $OrderService->asOrder($og_id,4);
                }
                if($arr['financial_status'] == 3){
					$OrderService->asOrder($og_id,6);
                    $this->shJujue($og_id);
				}
                if($arr['refund_status'] == 1){
                    $data ['as_status']= 4;
                    $data ['as_log_desc']= $desc_arr[4];
                    if($order_goods['refund_status']==1){
                        //退款
                        //退佣金
                        $this->CommissionRefund($order_goods['og_id']);
                        if($order_goods['rc_id'] && $order_goods['rc_amount']){
                            // 充值卡和余额优先退充值卡 20190113
                            $rc_info = Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->field('card_balance,card_stat,card_no,card_uid')->find();
                            // 如果退款金额大于充值卡 则 先退充值卡 在退余额
                            if ($order_goods['rc_amount'] >= $og_goods_price) {
                                if ($rc_info['card_stat']== 2) {
                                    Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->update(['card_stat' => 1, 'card_balance' => $og_goods_price]);
                                    //充值卡记录
                    				$OrderService = new OrderService();
                    				$OrderService->add_rc_log($rc_info['card_uid'],$order_goods['rc_id'],$og_goods_price,1);
                                } else {
                                    Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->setInc('card_balance', $og_goods_price);
                                    //充值卡记录
                    				$OrderService = new OrderService();
                    				$OrderService->add_rc_log($rc_info['card_uid'],$order_goods['rc_id'],$og_goods_price,1);
                                }
                                Db::name('order') ->where(['order_id' => $order_goods['og_order_id']])->update(['rc_amount' => $order_goods['rc_amount'] - $og_goods_price]);
                            } else {
                                if ($rc_info['card_stat']== 2) {
                                    Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->update(['card_stat' => 1, 'card_balance' => $order_goods['rc_amount']]);
                                    //充值卡记录
                    				$OrderService = new OrderService();
                    				$OrderService->add_rc_log($rc_info['card_uid'],$order_goods['rc_id'],$order_goods['rc_amount'],1);
                                } else {
                                    Db::name('user_rc')->where('card_id', $order_goods['rc_id'])->setInc('card_balance', $order_goods['rc_amount']);
                                    //充值卡记录
                    				$OrderService = new OrderService();
                    				$OrderService->add_rc_log($rc_info['card_uid'],$order_goods['rc_id'],$order_goods['rc_amount'],1);

                                }
                                $og_goods_price =$og_goods_price - $order_goods['rc_amount'];
                                if ($og_goods_price > 0 && !empty($order_goods['order_pay_code'])) {
                                    $this->shRefund($order_goods,$og_goods_price);
                                }
                                /*if ($og_goods_price > 0) {
                                    $this->accountLog($order_goods['og_uid'],$og_goods_price);
                                }
                                Db::name('users')->where('user_id',$order_goods['og_uid'])->setInc('user_account',$og_goods_price);
                                */
                                Db::name('order') ->where(['order_id' => $order_goods['og_order_id']])->update(['rc_amount' => 0]);
                            }

                        } else {
                            /*if ($og_goods_price > 0) {
                                $this->accountLog($order_goods['og_uid'],$og_goods_price);
                            }
                            Db::name('users')->where('user_id',$order_goods['og_uid'])->setInc('user_account',$og_goods_price);*/
                            if ($og_goods_price > 0 && !empty($order_goods['order_pay_code'])) {
                                $this->shRefund($order_goods,$og_goods_price);
                            }
                        }
                    }
                    $OrderService->writelog($data);
                    $OrderService->asOrder($og_id,5);
                    $OrderService->setogorderstatus($og_id, 10);
                    $OrderService->setorderstatus($order_goods['og_order_id'], 8);
                    $this->tuihuiyuanbao($order_goods['og_order_id'], $order_goods['og_uid']);
                    // 判断 该订单 正常进行的商品 有没有 全部发货
                    $this->fahuoWancheng($order_goods['og_order_id']);
                }
        }

        return true;
    }
    /**
     * 售后拒绝 订单 恢复 订单原始状态
     * 仅退款 恢复到 待发货
     * 退货、换货 恢复到 已完成
     */
    public function shJujue($og_id)
    {
        $sh_info = Db::name('sh_info')->where(['og_id' => $og_id])->find();
        if ($sh_info) {
        	//客服，供应商，财务审核不通过时修改售后进度状态为未通过
        	if($sh_info['status']==3 || $sh_info['supplier_status']==3 || $sh_info['financial_status']==3){
        		Db::name('sh_info')->where(['id' => $sh_info['id']])->update(['audit_status' => 6]);
        	}
            if ($sh_info['after_state_status'] == 3) {
                // 申请退款 拒绝后 订单改为 未发货状态
                Db::name('order')->where(['order_id' => $sh_info['og_order_id']])->update(['order_status' => 1]);
            } else {
                // 退货 或 换货 拒绝后 查询是否有其他售后 商品 有 不做修改  没有则把订单 改为 已完成
                $sh_where = [
                    'og_order_id' => $sh_info['og_order_id'],
                    'audit_status' => ['neq', 6]
                ];
                $qita_sh = Db::name('sh_info')->where($sh_where)->find();
                if (!$qita_sh) {
                    Db::name('order')->where(['order_id' => $sh_info['og_order_id']])->update(['order_status' => 4]);
                }
            }

//            $data = [
//                'as_id' => $sh_info['og_order_id'],//售后id
//                'agent_type' => 5,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户:,5:总管理员;
//                'agent_id' => 5,//经办人id;
//                'agent_name' =>'admin',//经办人名称
//                'as_log_desc' => '退款审核未通过',//日志内容
//                'agent_note'=>'退款审核未通过',
//                'add_time'=>time(),
//                'as_status' => 5,//售后进度状态：0，待审核；1，申请审核；2：审核中；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
//            ];
//            $this->order->writelog($data);
        }
    }


    /**
     * 退货退款 财务审核通过后 判断该订单 状态
     */
    public function fahuoWancheng($order_id){
        $row = Db::name('order_goods')->where('og_order_id',$order_id)->select();
        // 该订单 有没有 未发货商品
        $order_status1 = false;
        // 该订单 有没有 已发货商品
        $order_status2 = false;
        if($row){
            foreach( $row as $val){
                // 排除售后 商品
                $sh_info = Db::name('sh_info')->where(['og_id' => $val['og_id']])->find();
                if ($sh_info) {
                    continue;
                }
                if($val['og_order_status'] ==1){
                    $order_status1	= true;
                } elseif ($val['og_order_status'] ==2){
                    $order_status2	= true;
                }
            }
        }
        // 订单 没有未发货 并且 有已发货商品
        if(!$order_status1 && $order_status2){
            // 查询 已发货的最后一件商品的 物流信息
            $order_goods_info = Db::name('order_goods')->where(['og_order_id' => $order_id])->order('og_delivery_time desc')->find();
            $datas['order_status']=2;
            $datas['post_status']=2;
            $datas['post_type'] = $order_goods_info['audit_reason'];
            $datas['post_no'] = $order_goods_info['post_no'];
            $datas['delivery_time'] =  $order_goods_info['og_delivery_time'];
            Db::name('order')->where(['order_id' => $order_id])->update($datas);
        }
    }

    /**
     * //退款、退佣金
     */
    public function CommissionRefund($og_id)
    {
    	//判断是否是自有商品
        $has = Db::name('sg_sale')->where('s_og_id',$og_id)->find();
        if($has){
            Db::name('sg_sale')->where('s_og_id',$og_id)->update(['status'=>1]);
        }
        //退款、退佣金
        
        $og_order = Db::name('order_goods')->alias('a')
        			->join('__GOODS__ b','b.goods_id=a.og_goods_id')
        			->where(['a.og_id'=>$og_id])
        			->field('a.order_commi_price,a.og_order_id,b.commission')
        			->find();
        if(empty($og_order)) return;

        $commission = Db::name('commission')->where('commi_order_id',$og_order['og_order_id'])->find();

        if(empty($commission)) return;

        $refund = $commission['commi_order_price'] - $og_order['order_commi_price'];

        if($refund<=0){
        	$res = Db::name('commission')->where('commi_order_id',$og_order['og_order_id'])->update(['is_settle'=>2]);
        	return $res;
        }

        $refund_commi = $og_order['commission'] * $og_order['order_commi_price']/100;

        $data = [
        	'commi_order_price'=>$refund,
            'goods_profit'=>($commission['goods_profit']-$refund_commi)?:0
        ];
        if(!empty($commission['commi_price'])){
        	$r = $commission['commi_price']-$refund_commi;
        	$data['commi_price'] = ($r>0) ? $r : 0;
        }elseif(!empty($commission['commi_p_price'])){
        	$s = $commission['commi_p_price']-$refund_commi;
        	$data['commi_p_price'] = ($s>0) ? $s : 0;
        }
		
		$res = Db::name('commission')->where('commi_order_id',$og_order['og_order_id'])->update($data);
		return $res;
    }

    /*
    *  售后管理订单删除
    */

   	public function orderdel(){
		$id=input('get.ids');
        $where = array();
        $where['order_id'] = array('in', $id);
		 
		$res = Db::name('order')->where($where)->find();
		//未支付完成 未收到货
		if(($res['pay_status']<4) and ($res['post_status']<4)){
			return (['code'=>0,'msg'=>'此订单未完成不能删除',]);
		}else{
			 $res = Db::name('order')->where($where)->update(['order_isdel' => 1]);
			 if($res){
				 return (['code'=>1,'msg'=>'删除成功','data'=>'']);
			 }
			return (['code'=>0,'msg'=>'删除失败','data'=>'']);
		}
       
        return AjaxReturn($res);
	}
	/*
   	*  批量审核
   	*/
   	public function multi(){		
   		$action=input('action');
   		$ids=input('get.ids/a');
		$array = implode(',',$ids);
		if(!$action){
			return AjaxReturn(UPDATA_FAIL);   
		}
		$val = input('params');
		$data['status'] = $val;
		$array = explode(',',$array);
		if($array){
			foreach($array as $val){
				$map['og_order_id'] = $val;
				$res = Db::name('sh_info')->where($map)->update($data);
				if($res===false){
					return AjaxReturn($res);
				}else{
					$res =1;
				}
			}

			//添加日志记录
            $this->write_log('批量审核',$ids);
		}
		return AjaxReturn($res);
   	}
	
	/*
     *售后退货申请完成 明细记录
    */
    public function accountLog($uid,$acco_num,$orderId = ''){
		
		$log_insert = [
			'a_uid' => $uid,
			'acco_num' => $acco_num ? $acco_num : 0,
			'acco_type' => 12,
			'acco_desc' => '订单退款',
			'acco_time' => time(),
            'order_id'=>$orderId
		];
		Db::name('account_log')->insert($log_insert);
		
	}

	//售后退款
    private function shRefund($order_goods,$og_goods_price)
    {
     //   switch ($order_goods['order_pay_code']) {
          //  case 'balance': //余额
                $this->accountLog($order_goods['og_uid'], $og_goods_price,$order_goods['og_order_id']);
                Db::name('users')->where('user_id', $order_goods['og_uid'])->setInc('user_account', $og_goods_price);

                //添加退款审核日志
//        $data = [
//            'as_id' => $order_goods['og_order_id'],//售后id
//            'agent_type' => 5,//经办人类型: 1:客服; 2:供应商；3：财务 4:用户:,5:总管理员;
//            'agent_id' => 5,//经办人id;
//            'agent_name' =>'admin',//经办人名称
//            'as_log_desc' => '退款审核通过',//日志内容
//            'agent_note'=>'退款审核通过',
//            'add_time'=>time(),
//            'as_status' => 5,//售后进度状态：0，待审核；1，申请审核；2：审核中；3，售后已收货；4，进行退款（进行换货）；5， 处理完成
//        ];
//        $this->order->writelog($data);

                //break;
            /*case 'wxpay': //微信

                break;
            case 'alipay': //支付宝

                break;
            case 'jsapi': //微信端支付

                break;*/
//            default:
//                $codeArr = ['wxpay'=>1,'alipay'=>2,'jsapi'=>3];
//                $refund_data = [
//                    're_type'=>$codeArr[$order_goods['order_pay_code']],
//                    're_price'=>$og_goods_price,
//                    're_pay_no'=>$order_goods['order_pay_no'],
//                    're_order_no'=>$order_goods['order_no'],
//                    're_sh_id'=>$order_goods['id'],
//                    'create_time'=>time(),
//                ];
//                Db::name('refund')->insert($refund_data);
//                break;

      //  }
    }

    /**
     * 退款列表
     */
    public function refundlist()
    {
        $is_refund = input('is_refund');
        $re_type = input('re_type');
        if($is_refund===null){
            $is_refund = 4;
        }
        if($re_type===null){
            $re_type = 4;
        }
        if(request()->isAjax()){
            //排序
            $order="create_time desc";
            //limit
            $limit=input('get.offset').",".input('get.limit');
            $total = Db::name('refund')->count();
            $where = [];
            if($re_type!=4){
                $where['re_type'] = $re_type;
            }
            if($is_refund!=4){
                $where['is_refund'] = $is_refund;
            }
            $rows = Db::name('refund')->where($where)->order($order)->limit($limit)->select();

            if($rows){
                //1. 微信 2.支付宝 3.公众号
                $typeArr = ['微信','支付宝','公众号'];
                $isRefund = ['否','是'];
                foreach ($rows as &$v){
                    $v['create_time'] = $v['create_time']?date('Y-m-d H:i:s',$v['create_time']):'';
                    $v['check_time'] = $v['check_time']?date('Y-m-d H:i:s',$v['check_time']):'';
                    $v['re_type'] = $typeArr[$v['re_type']-1];
                    $v['is_refund'] = $isRefund[$v['is_refund']];
                }
            }
            return json(['total'=>$total,'rows'=>$rows]);
        }else{
            $this->assign('is_refund',$is_refund);
            $this->assign('re_type',$re_type);
            return $this->fetch();
        }

    }

    /*
     * 确认退款
     */
    public function refundedit()
    {
        $ids=input('get.ids');
        $where = ['refund_id'=>['in',$ids]];
        $res = Db::name('refund')->where($where)->update(['is_refund' => 1,'check_time'=>time()]);
        return AjaxReturn($res);
    }

    /**
     * 打印页面
     *
     * @param int $order_id 订单ID
     */
    public function printer()
    {
        $id = input("request.order_id");
        $data = $this->order->getOrderDetails($id);
        $this->assign([
            'order' => $data['order'],
            'order_goods' => $data['order_goods'],
        ]);
        return $this->fetch();
    }
}
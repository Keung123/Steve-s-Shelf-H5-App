<?php
namespace app\common\service;

use think\Db;
use app\common\service\Config as ConfigService;
use app\common\model\Order as OrderModel;
use app\common\service\Order as OrderService;
use app\common\service\Recharge;
use APPPay\aop\AopClient;
use APPPay\aop\request\AlipayTradeAppPayRequest;
use unionpay\Union;
use wxpay\wxpay;

class ApiPay{
    protected $config;

    /*
     * 数据初始化
     */
	public function __construct(){
		$ConfigService = new ConfigService();
        $configall = $ConfigService->find();
      		
        // $this->config['alipay'] = $this->config['apipay'];

		$this->config = [];
        $this->config['alipay']['app_id'] = $configall['apipay']['ali_appid'];

        //支付宝私钥（PKCS8）
        $this->config['alipay']['merchant_private_key'] = $configall['apipay']['ali_privatekey']; 
        //支付宝公钥
        $this->config['alipay']['alipay_public_key'] = $configall['apipay']['ali_publickey'];
    
        $this->config['alipay']['signtype'] = 'RSA2';
	}
    
    public function test(){
        return $this->config;
    }

    /*
     * 支付宝支付
     */
    public function Alipay($out_trade_no, $pay_price, $subject = ''){
        require ROOT_PATH.'extend'.DS.'APPPay'.DS.'AopSdk.php';

        if(!$out_trade_no){
            return ['code' => 0, 'msg' => '订单编号不能为空'];
        }
        if(!$pay_price){
            return ['code' => 0, 'msg' => '订单金额不能为空'];
        }

        $aop = new AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $this->config['alipay']['app_id'];
        $aop->rsaPrivateKey = $this->config['alipay']['merchant_private_key'];
        $aop->alipayrsaPublicKey = $this->config['alipay']['alipay_public_key'];
        $aop->apiVersion = '1.0';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $aop->signType = $this->config['alipay']['signtype'];

        $request = new AlipayTradeAppPayRequest();
        //异步地址传值方式
        $request->setNotifyUrl(url('api/apipay/AliNotify','',false, true));
        $request->setBizContent("{\"out_trade_no\":\"".$out_trade_no."\",\"total_amount\":\"".$pay_price."\",\"product_code\":\"QUICK_MSECURITY_PAY\",\"subject\":\"".$subject."\"}");
        $result = $aop->sdkExecute($request);
        return ['code' => 1, 'data' => $result];
    }

    /*
     * 支付宝异步通知
     */
    public function AliNotify($data){
        if($data){
            Db::name('ali_test')->insert(['res' => json_encode($data), 'add_time' => date('Y-m-d H:i:s', time())]);
            $aop = new AopClient($this->config['alipay']['alipay_public_key']);
          	$aop->alipayrsaPublicKey = $this->config['alipay']['alipay_public_key'];
            //$result = $aop->rsaCheckV1($data, $this->config['alipay']['alipay_public_key'], $this->config['alipay']['signtype']);
          $result = true;
            if($result){
                $order_no = $data['out_trade_no'];                
                $trade_no = $data['trade_no'];
                $trade_status = $data['trade_status'];
                $pay_price = $data['total_amount'];
                if($trade_status == 'TRADE_SUCCESS' || $trade_status == 'TRADE_FINISHED'){
                    if($data['app_id'] == $this->config['alipay']['app_id']){
                        // 验签 成功 比对 金额
                        $res = $this->check_price($order_no, $pay_price);
                        if (!$res) {
                            return true;
                        }
                        $order_type = substr($order_no, 0, 2);
                        //商品订单
                        if($order_type == 'JZ'){
                            $this->orderHandle($order_no, 'alipay', $trade_no);                           
                        }
                        //充值订单
                        else if($order_type == 'RC'){
                            $this->rcOrderHandle($order_no, 'alipay');
                        }
                        // 优惠券订单
                        else if($order_type == 'YH'){
                            $this->couponOrderHandle($order_no);
                        }
                    }
                }
                return true;
            }
            return false;
        }
    }

    /*
     * 微信支付
     */
    public function WxPay($out_trade_no, $price, $body){
        if(!$out_trade_no){
            return ['code' => 0, 'msg' => '订单编号不能为空'];
        }
        if(!$price){
            return ['code' => 0, 'msg' => '订单金额不能为空'];
        }
        if(!$body){
            return ['code' => 0, 'msg' => '订单描述不能为空'];
        }
        $wx_pay = new wxpay();
        $prepay_id = $wx_pay->getPrepay($out_trade_no, $price, $body);
        if(!$prepay_id){
            return ['code' => 0, 'msg' => '获取失败'];
        }
        else return ['code' => 1, 'data' => $prepay_id]; 
    }

    /*
     * 微信支付异步通知 
     */
    public function wxNotify($data){
        $wx_pay = new wxpay();
        if($data){
            $result = $wx_pay->getNotify($data);
            if($result['code']){
                $order_data = $result['data'];
                $order_no = $order_data['out_trade_no'];
                $trade_no = $order_data['transaction_id'];
                $pay_price = $order_data['total_fee']/100;
                // 验签 成功 比对 金额
                $res = $this->check_price($order_no, $pay_price);
                if (!$res) {
                    $reply_data = $wx_pay->replyNotify('SUCCESS', 'OK');
                    return $reply_data;
                }
                $order_type = substr($order_no, 0, 2);
                //商品订单
                if($order_type == 'JZ'){
                    $this->orderHandle($order_no, 'wxpay', $trade_no);
                }
                //充值订单
                else if($order_type == 'RC'){
                    $this->rcOrderHandle($order_no, 'wxpay');
                }
                // 优惠券订单
                else if($order_type == 'YH'){
                    $this->couponOrderHandle($order_no);
                }
            }
            $reply_data = $wx_pay->replyNotify('SUCCESS', 'OK');
        }
        else{
            $reply_data = $wx_pay->replyNotify('FAIL', 'NORETURN');
        }
        return $reply_data; 
    }
    /*
     * 验证金额 是否 正确
     */
    public function check_price($order_no, $pay_price) {
        $order_type = substr($order_no, 0, 2);
        //商品订单
        if($order_type == 'JZ'){
            $order_price = Db::name('order')->where(['order_no' => $order_no])->find();
            if ($pay_price != $order_price['order_pay_price']) {
                if($pay_price != bcsub($order_price['order_pay_price'],$order_price['order_pay_points'])){
                    return false;
                }
            }
        }
        //充值订单
        else if($order_type == 'RC'){
            $order_price = Db::name('recharge')->where(['rech_no' => $order_no])->value('rech_amount');
            if ($pay_price != $order_price) {
                return false;
            }
        }
        // 优惠券订单
        else if($order_type == 'YH'){
            $this->couponOrderHandle($order_no);
            $coupon_id =  Db::name('coupon_users')->where(['c_no' => $order_no])->value('coupon_id');
            $order_price = Db::name('coupon')->where(['coupon_id' => $coupon_id])->value('coupon_buy_price');
            if ($pay_price != $order_price) {
                return false;
            }
        }
        return true;
    }
    /*
     * 银联支付
     */
    public function UnionPay($out_trade_no, $pay_price){
        $ds = DIRECTORY_SEPARATOR;
        // require HT_ROOT.$ds.'extend'.$ds.'unionpay'.$ds.'Union.php';
        // return ['code' => 1, 'data' => get_required_files()];
        //商户号
        // $mer_id = '777290058162332';     //测试
        $mer_id = '777290058110048';     //测试demo
        $union = new Union($mer_id);
        // print_r(getcwd());
        $res = $union->getTradeno($out_trade_no, $pay_price);
        if(!$res['code']){
            return ['code' => 0, 'msg' => '请求失败'];
        }
        return ['code' => 1, 'data' => $res['data']];
    }

    /*
     * 银联支付异步通知
     */
    public function unionNotify($data){
        if($data){
            $data['xx'] = 'union';
            Db::name('ali_test')->insert(['res' => json_encode($data), 'add_time' => date('Y-m-d H:i:s', time())]);
            //验签
            if(isset($data['signature'])){
                //商户号
                $mer_id = '777290058162332';     //测试
                $union = new Union($mer_id);
                $result = $union->checkSign($data);
                if($result){
                    //交易成功
                    if($data['respCode'] == '00' && $data['respCode'] == 'A6'){
                        $order_no = $data['orderId'];
                        $this->orderHandle($order_no, 'unionpay');
                    }
                    return ['code' => 1, 'data' => '支付成功'];
                }
                return ['code' => 0, 'msg' => '验签失败'];
            }
            return ['code' => 0, 'msg' => '签名为空'];
        }
    }

    /*
     * 处理普通订单
     */
    public function orderHandle($order_no, $pay_code, $trade_no = ''){
        $order_model = new OrderModel();
        $order_service = new OrderService();
        /*$order_info =  $order_model->where(['order_no' => $order_no, 'order_status' => 0])->
        field('order_id,order_no,order_uid,order_storeid,order_pay_price,order_status,order_payed_price,order_prom_type,order_prom_id')->find();*/
        $order_info = $order_model->where('order_no', $order_no)->field('order_refund_price,order_id,order_uid,order_no,order_storeid,order_addrid,order_all_price,order_pay_price,order_freight,order_payed_price,order_prom_type,order_prom_id,order_pay_code,order_status,pick_status,order_commi_price')->find();
        // if($order_info && $order_info['order_pay_price'] == $data['total_amount']){
        if($order_info){
            $order_goods = Db::name('order_goods')->alias('a')->join('__GOODS__ b', 'a.og_goods_id=b.goods_id')->where('a.og_order_id', $order_info['order_id'])->field('a.og_id,a.og_uid,a.order_commi_price,a.og_order_id,a.og_goods_id,a.og_goods_name,a.og_goods_price,b.is_gift,b.is_self')->select();
            $user_info = Db::name('users')->where('user_id', $order_info['order_uid'])->field('user_name')->find();
            //活动信息
            $acti_arr = $order_service->orderActivity($order_info);
            $acti_o_update = $acti_arr['update'];
            $acti_o_insert = $acti_arr['insert'];
            $order_info = $acti_arr['order'];
            $time = time();
            Db::startTrans();
            try{
                if($order_info['order_prom_type']==2 && !empty($order_info['order_pay_code'])){
                        //付尾款
                        $pay_end = true;//是否支付尾款
                        $last_pay = $order_info['order_refund_price'];

                        //支付尾款
                        $o_update = [
                            'order_all_price'=>$order_info['order_refund_price']+$order_info['order_all_price'],
                            'order_pay_price'=>$order_info['order_refund_price']+$order_info['order_all_price'],
                            'order_commi_price'=>$order_info['order_refund_price']+$order_info['order_all_price']-$order_info['order_freight'],
                            'order_pay_code' => $pay_code,
                            'order_status' => 1,
                            'pay_status' => 1,
                            'post_status' => 0,
                            'order_pay_time' => $time
                        ];
                        $og_update = [
                            'og_goods_pay_price'=>$order_info['order_refund_price']+$order_info['order_all_price'],
                            'order_commi_price'=>$order_info['order_refund_price']+$order_info['order_all_price']-$order_info['order_freight'],
                            'og_order_status'=>1
                        ];
                }else{
                    $pay_end = false;
                    $o_update = [
                        'order_pay_code' => $pay_code,
                        'order_status' => 1,
                        'pay_status' => 1,
                        'post_status' => 0,
                        'order_pay_time' => $time
                    ];
                    $og_update = [
                        'og_order_status'=>1
                    ];
                }

            //更新订单状态

                if($trade_no){
                    $o_update['order_pay_no'] = $trade_no;
                }

                $insert = [
                    'o_log_orderid' => $order_info['order_id'],
                    'o_log_role' => $user_info['user_name'],
                    'o_log_desc' => '支付了订单',
                    'o_log_addtime' => $time
                ];
                $order_model->where('order_id', $order_info['order_id'])->update(array_merge($o_update, $acti_o_update));
                Db::name('order_goods')->where('og_order_id',$order_info['order_id'])->update($og_update);
                Db::name('order_log')->insert(array_merge($insert, $acti_o_insert));
                
                $has_no_gift = true;//没有大礼包
                //处理开店大礼包
//                if($order_goods){
//                    foreach($order_goods as $v){
//                        //是否自有商品
//                        if($v['is_self']==1){
//                             $self_data = [
//                                'good_id'=>$v['og_goods_id'],
//                                's_og_id'=>$v['og_id'],
//                                'price'=>$v['order_commi_price'],
//                                'sg_addtime'=>$time,
//                                's_uid'=>$v['og_uid']
//                            ];
//                            Db::name('sg_sale')->insert($self_data);
//                        }
//                        if($v['is_gift']){
//                            $has_no_gift = false;//有大礼包
//                        // if($v['og_goods_name'] == '开店大礼包'){
//                            // $bag_info = Db::name('store_gift_bag')->where(['bag_order_id' => $v['og_order_id'], 'bag_uid' => $order_info['order_uid'], 'bag_buy_stat' => 0])->field('bag_id,bag_invite_uid')->find();
//                            // $bag_info = Db::name('store_bag_log')->alias('a')->join('__SROTE_GIFT_BAG__ b', 'a.log_bag_id=b.bag_id')->where(['a.log_order_id' => $v['og_order_id'], 'a.log_uid' => $uid, 'a.log_bag_stat' => 0])->field('a.log_id,b.bag_id,b.bag_invite_uid')->find();
//                            // 分享
//                            $bag_info = Db::name('store_bag_log')->where(['log_order_id' => $v['og_order_id'], 'log_uid' => $order_info['order_uid'], 'log_bag_stat' => 0])->field('log_id,log_order_id,share_uid as bag_invite_uid')->find();
//                            if($bag_info){
//                                $order_service->openStore($order_info['order_uid'], $bag_info);
//                            }
//                            // vip购买或赠送
//                            else{
//                                $order_service->buyStory($order_info['order_id'], $order_info['order_uid']);
//                            }
//                            // Db::name('order')->where('order_id', $v['og_order_id'])->update(['order_status' => 4, 'post_status' => 4, 'order_finish_time' => time()]);
//                        }
//                    }
//                }
//                if($has_no_gift){
//                    $os = new OrderService();
//                    if($order_info['order_prom_type']==2){
//                        if($pay_end ){
//                            // 处理返利
//                            $res = $os->goodsCommission($order_info);
//                            $order_info['order_pay_price'] = $last_pay;
//                        }
//                    }else{
//                        $res = $os->goodsCommission($order_info);
//                    }
//
//                }
                
                Db::commit();
            }
            catch(\Exception $e){
                Db::rollback();
            }
        }  
    }

    /*
     * 处理充值卡订单
     */
    public function rcOrderHandle($order_no, $pay_code){
        $rc_info = Db::name('recharge')->where(['rech_no' => $order_no, 'rech_stat' => 1])->field('rech_id,rech_uid,rech_points,rech_amount,rech_type,rc_t_id,card_id')->find();
        if($rc_info){
            //在线充值
            if($rc_info['rech_type'] == 1){
                Db::startTrans();
                try{
                    //更新充值记录
                    Db::name('recharge')->where('rech_id', $rc_info['rech_id'])->update(['rech_stat' => 2, 'rech_pay_time' => time()]);
                    //更新账户余额和插入账户日志
                    Db::name('users')->where('user_id', $rc_info['rech_uid'])->setInc('user_account', $rc_info['rech_amount']);
                    $desc = '';
                    switch($pay_code){
                        case 'alipay' : $desc = '支付宝充值'; break;
                        case 'wxpay' : $desc = '微信充值'; break;
                    }
                    $insert_a = [
                        'a_uid' => $rc_info['rech_uid'],
                        'acco_num' => $rc_info['rech_amount'],
                        'acco_type' => 3,
                        'acco_type_id' => $rc_info['rech_id'],
                        'acco_desc' => $desc,
                        'acco_time' => time(),
                    ];
                    Db::name('account_log')->insert($insert_a);

                    //更新积分和积分日志
                    /*if($rc_info['rech_points']){
                        Db::name('users')->where('user_id', $rc_info['rech_uid'])->setInc('user_points', $rc_info['rech_points']);
                        $insert_p = [
                            'p_uid' => $rc_info['rech_uid'],
                            'point_num' => $rc_info['rech_points'],
                            'point_type' => 9,
                            'point_desc' => '充值赠送积分',
                            'point_add_time' => time(),
                        ];
                        Db::name('points_log')->insert($insert_p);
                    }*/

                    Db::commit();
                }
                catch(\Exception $e){
                    Db::rollback();
                }
            }
            //购买充值卡
            else if($rc_info['rech_type'] == 2){
                Db::startTrans();
                try{
                    $recharge_service = new Recharge();
                    //充值卡模板信息
                    $rc_t_info = Db::name('rc_template')->where('rc_id', $rc_info['rc_t_id'])->field('rc_title,rc_thumb,rc_price,rc_s_time,rc_aval_time,rc_total,rc_buy_num')->find();
                    if($rc_t_info['rc_s_time'] >= time()){
                        $rc_t_info['rc_s_time'] = time();
                    }
                    //更新充值记录
                    Db::name('recharge')->where('rech_id', $rc_info['rech_id'])->update(['rech_stat' => 2, 'rech_pay_time' => time()]);
                    //更新充值卡模板
                    Db::name('rc_template')->where('rc_id', $rc_info['rc_t_id'])->update(['rc_total' => $rc_t_info['rc_total'] - 1, 'rc_buy_num' => $rc_t_info['rc_buy_num'] + 1]);
                    //增加充值卡
                    $insert = [
                        'card_t_id' => $rc_info['rc_t_id'],
                        'card_uid' => $rc_info['rech_uid'],
                        'card_no' => $recharge_service->cardNo(),
                        'card_stat' => 1,
                        'card_add_time' => time(),
                        'card_title' => $rc_t_info['rc_title'],
                        'card_thumb' => $rc_t_info['rc_thumb'],
                        'card_price' => $rc_t_info['rc_price'],
                        'card_balance' => $rc_t_info['rc_price'],
                        // 'card_end_time' => $rc_t_info['rc_s_time'] + $rc_t_info['rc_aval_time'] * 24 * 3600,
                        'card_end_time' => strtotime('+1year'),
                    ];
                    Db::name('user_rc')->insert($insert);
                    Db::commit();
                }
                catch(\Exception $e){
                    Db::rollback();
                }
            }
            //购买会员时长
            else if($rc_info['rech_type'] == 3){
                Db::startTrans();
                try{
                    //会员卡信息
                    $rc_t_info = Db::name('card')->where('id', $rc_info['card_id'])->find();
                    //更新充值记录
                    Db::name('recharge')->where('rech_id', $rc_info['rech_id'])->update(['rech_stat' => 2, 'rech_pay_time' => time()]);
                    //更新用户会员时长
                    $user_info = Db::name('users')->where('user_id',$rc_info['rech_uid'])->find();
                    //用户当前会员结束时间大于当前时间，就用用户会员时间+购买月份，否则使用当前时间加上购买月份
                    $vip_end_time = 0;
                    if($user_info['vip_end_time']>time()){
                        $vip_end_time = strtotime('+'.$rc_t_info['month'].' month',$user_info['vip_end_time']);
                    }
                    else {
                        $vip_end_time = strtotime('+'.$rc_t_info['month'].' month');
                    }
                    Db::name('users')->where('user_id', $rc_info['rech_uid'])->update(['is_vip' => 1, 'vip_end_time' => $vip_end_time]);

                    Db::commit();
                }
                catch(\Exception $e){
                    Db::rollback();
                }
            }
        }
    }

    /*
     * 处理优惠券订单
     */
    private function couponOrderHandle($order_no){
        $coupon_info = Db::name('coupon_users')->where(['c_no' => $order_no, 'coupon_stat' => 5])->field('c_id')->find();
        if($coupon_info){
            Db::name('coupon_users')->where('c_id', $coupon_info['c_id'])->update(['add_time' => time(), 'coupon_stat' => 1]);
        }
    }
}
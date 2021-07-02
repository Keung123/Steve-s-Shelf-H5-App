<?php
namespace app\api\controller;
use think\Controller;
use think\Db;
use app\common\service\Login as LoginService;
use app\common\service\ApiPay;

class Fx extends Controller {
    /*
     * 登陆
     */
    public function login($url, $type = '')
    {
        if ($type == 'weixin' || $type == 'ysorpt') {

            if (session('user_mobile') && session('unionid')) {
                return '';
            } else {
                $wx_model = new \Wx();
                $data =  $wx_model->GetOpenid($url);
                if ($data) {
                    session('openid', $data['openid']);
                    session('unionid', $data['unionid']);
                }
                $info = Db::name('users')->where(['unionid' => $data['unionid']])->find();
                $login_service = new LoginService();
                if ($info) {
                    $res = $login_service->apiLogin($info['user_id']);
                    if ($res) {
                        session('uid', $res['uid']);
                        session('token', $res['token']);
                        session('is_seller', $res['is_seller']);
                        session('user_mobile', $res['user_mobile']);
                    }

                    return json_encode($res);
                } else {
                    $login_data = [
                        'user_avat' => $data['headimgurl'],
                        'user_sex' => (int)$data['sex'] - 1,
                        'user_name' =>  $data['nickname'],
                        'user_addr' =>  $data['province']. $data['city'],
                    ];
                    $res = $login_service->apiRegister('weixin', $data['unionid'], $login_data);
                    if ($res) {
                        session('uid', $res['uid']);
                        session('token', $res['token']);
                        session('is_seller', $res['is_seller']);
                        session('user_mobile', $res['user_mobile']);
                    }
                    return json_encode($res);
                }
            }
        }
    }
    /*
     * 获取 商品信息
     */
    public function goodsDetail()
    {
        $type =input('type', '');
        // 预售 和 拼团 需要 点击进来绑定
        if (empty(session('user_mobile')) && $type == 'ysorpt') {
            $goodsid = input('goodsid');
            $yaoqingma = input('yaoqingma');
            $bargain_id = input('bargain_id');// 砍价
            $act_id = input('act_id');// 预售
            $url = request()->domain().'/api/fx/goodsDetail?goodsid='.$goodsid.'&type='.$type.'&bargain_id='.$bargain_id.'&act_id='.$act_id.'&yaoqingma='.$yaoqingma;
            $this->login($url, 'weixin');
//            var_dump(session(''), $_SESSION);
//            exit;
            if (empty( session('user_mobile'))) {
                $this->redirect('/Api/fx/binding_phone', ['goodsid' => $goodsid, 'type' => $type, 'bargain_id' => $bargain_id, 'act_id' => $act_id, 'result_type' => 'goods', 'yaoqingma' => $yaoqingma]);
            }
        }
        $goodsid = input('goodsid');
        $active_id = Db::name('goods')->where(['goods_id' => $goodsid])->value('prom_type');
        $uid = session('uid');
        $token = session('token');
        $is_seller = session('is_seller');
        $yaoqingma = input('yaoqingma', '');
        $bargain_id = input('bargain_id', 0);
        $act_id = input('act_id', 0);// 预售
        $this->assign('type', 'weixin');
        $this->assign('yaoqingma', $yaoqingma);
        $this->assign('act_id', $act_id);
        $this->assign('bargain_id', $bargain_id);
        $this->assign('goodsid', $goodsid);
        $this->assign('active_id', $active_id ? $active_id : 0);
        // 默认 为 空
        $this->assign('is_seller', $is_seller ? $is_seller : 0);
        $this->assign('uid', $uid? $uid : 0);
        $this->assign('token', $token ? $token : '');

//        if (false) {
//            return $this->fetch('fx/goods_details_jifen');
//        }
        //1、团购，2、预售，3、拼团，4、砍价，5、秒杀，6、满减，7、99元三件，8、满2件打九折 ， 9自定义
        if ($active_id == '') {
            return $this->fetch('fx/goodsDetail');
        } elseif ($active_id == 2) {
            if (!$act_id) {
                $act_id = Db::name('goods_activity')->where(['goods_id' => $goodsid])->value('act_id');
            }
            $this->assign('act_id', $act_id);
            return $this->fetch('fx/goods_details_yushou');
        } elseif ($active_id == 3) {
            return $this->fetch('fx/pintuan_xx');
        } elseif ($active_id == 4) {
            return $this->fetch('fx/goods_details_kanjia');
        } elseif ($active_id == 5) {
            return $this->fetch('fx/seckill_details');
        } else {
            return $this->fetch('fx/goods_details_huodong');
        }


    }
    /*
     *  商品订单
     */
    public function write_order()
    {
        $goodsid = input('goodsid');
        $num = input('num');
        $sku_id = input('sku_id');
        $active_id = input('active_id');
        $type = input('type');  // 活动类型
        $act_id = input('act_id');  // 活动类型
        $order_type = input('order_type');
        $user_id = input('user_id', 0);
        if ((empty(session('user_mobile')) || empty(session('uid')) || empty(session('token'))) && $type == 'weixin') {
            $yaoqingma = input('yaoqingma');
            $url = request()->domain().'/api/fx/write_order?goodsid='.$goodsid.'&type='.$type.'&yaoqingma='.$yaoqingma.'&sku_id='.$sku_id.'&order_type='.$order_type.'&num='.$num.'&active_id='.$active_id.'&act_id='.$act_id.'&user_id='.$user_id;
            $this->login($url, $type);
            if (empty( session('user_mobile'))) {
                $this->redirect('/Api/fx/binding_phone', ['goodsid' => $goodsid, 'type' => $type, 'result_type' => 'order', 'yaoqingma' => $yaoqingma, 'user_id' => $user_id, 'sku_id' => $sku_id, 'order_type' => $order_type, 'num' => $num, 'active_id' => $active_id, 'act_id' => $act_id]);
            }
        }
        $goodsid = input('goodsid');
        $uid = session('uid');
        $token = session('token');
        $num = input('num');
        $sku_id = input('sku_id');
        $active_id = Db::name('goods')->where(['goods_id' => $goodsid])->value('prom_type');
        $type = input('type');  // 活动类型
        $act_id = input('act_id');  // 活动类型
        $share_uid = input('user_id', 0);
        // 分享大礼包 $share_uid  分享人id
        $goods_info = Db::name('goods')->where('goods_id', $goodsid)->field('is_gift')->find();
        if ($share_uid && $goods_info['is_gift'] == 1) {
            $res =  Db::name('store_bag_log')->where(['share_uid' => $share_uid, 'goods_id' => $goodsid, 'log_uid' =>$uid, 'log_bag_stat' => 0])->find();
            if (!$res) {
                $insert = [
                    'goods_id' =>$goodsid,
                    'log_bag_id' => $goodsid,
                    'share_uid' => $share_uid,
                    'log_get_time' => time(),
                    'log_uid' => $uid
                ];
                Db::name('store_bag_log')->insert($insert);
            }
        }
        if ($goodsid) {
            session('goodsid', $goodsid);
        }
        if ($num) {
            session('num', $num);
        }
        if ($sku_id) {
            session('sku_id', $sku_id);
        }
        if ($active_id) {
            session('active_id', $active_id);
        }
        if ($type) {
            session('type', $type);
        }
        if ($order_type) {
            session('order_type', $order_type);
        }
        if ($act_id) {
            session('act_id', $act_id);
        }

        $this->assign('act_id', $act_id ? $act_id : session('act_id'));
        $this->assign('type', $order_type? $order_type : session('order_type'));
        $this->assign('goodsid', $goodsid ? $goodsid : session('goodsid'));
        $this->assign('num', $num ? $num : session('num')?session('num'):1);
        $this->assign('sku_id', $sku_id ? $sku_id : session('sku_id'));
        $this->assign('active_id', $active_id ? $active_id : session('active_id'));
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        $is_seller = session('is_seller');
        $is_gift = Db::name('goods')->where(['goods_id' => $goodsid])->value('is_gift');
        if ($is_seller == 1 && $is_gift == 1) {
            return $this->fetch('fx/yilingqu');
        } else {
            return $this->fetch('fx/write_order');
        }
    }
    /*
     * 礼包 订单
     */
    public function write_libao_order()
    {
        $goodsid = input('goodsid');
        $sku_id = input('sku_id');
        $type = input('type');
        $order_type = input('order_type');
        if (empty(session('user_mobile')) && $type == 'weixin') {
            $yaoqingma = input('yaoqingma');
            $url = request()->domain().'/api/fx/write_libao_order?goodsid='.$goodsid.'&type='.$type.'&yaoqingma='.$yaoqingma.'&sku_id='.$sku_id.'&order_type='.$order_type;
            $this->login($url, $type);
            if (empty( session('user_mobile'))) {
                $this->redirect('/Api/fx/binding_phone', ['goodsid' => $goodsid, 'type' => $type, 'result_type' => 'order', 'yaoqingma' => $yaoqingma, 'sku_id' => $sku_id, 'order_type' => $order_type]);
            }
        }
        $goodsid = input('goodsid');
        $uid = session('uid');
        $token = session('token');
        $sku_id = input('sku_id');
        $order_type = input('order_type');
        if ($goodsid) {
            session('goodsid', $goodsid);
        }
        if ($order_type) {
            session('order_type', $order_type);
        }
        if ($sku_id) {
            session('sku_id', $sku_id);
        }
        $this->assign('goodsid', $goodsid ? $goodsid : session('goodsid'));
        $this->assign('sku_id', $sku_id ? $sku_id : session('sku_id'));
        $this->assign('type', $order_type ? $order_type : session('order_type'));
        $this->assign('num', 1);
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        $is_seller = session('is_seller');
        if ($is_seller == 1) {
            return $this->fetch('fx/yilingqu');
        } else {
            return $this->fetch('fx/write_order');
        }

    }
    /*
     * 选择充值卡
     */
    public function car_ka()
    {
        $goodsid = input('goodsid');
        $uid = session('uid');
        $token = session('token');
        $sku_id = input('sku_id');
        $active_id = input('active_id');
        $zongjia = input('zongjia');  // 活动类型
        $this->assign('zongjia', $zongjia);
        $this->assign('goodsid', $goodsid);
        $this->assign('sku_id', $sku_id);
        $this->assign('active_id', $active_id);
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        return $this->fetch();

    }
    /*
    * 选择优惠券
    */
    public function car_discount()
    {
        $goodsid = input('goodsid');
        $uid = session('uid');
        $token = session('token');
        $num = input('num');
        $sku_id = input('sku_id');
        $active_id = input('active_id');
        $zongjia = input('zongjia');
        $this->assign('zongjia', $zongjia);
        $this->assign('goodsid', $goodsid);
        $this->assign('num', $num);
        $this->assign('sku_id', $sku_id);
        $this->assign('active_id', $active_id);
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        return $this->fetch();

    }
    /*
    * 选择元宝
    */
    public function car_yuanbao()
    {
        $goodsid = input('goodsid');
        $uid = session('uid');
        $token = session('token');
        $num = input('num');
        $sku_id = input('sku_id');
        $active_id = input('active_id');
        $zongjia = input('zongjia');  // 活动类型
        $this->assign('zongjia', $zongjia);
        $this->assign('goodsid', $goodsid);
        $this->assign('num', $num);
        $this->assign('sku_id', $sku_id);
        $this->assign('active_id', $active_id);
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        return $this->fetch();

    }
    /*
    * 添加地址
    */
    public function add_address()
    {
        // 将参数 转化成字符串返给页面
        $data = input();// 获取 所有请求参数
        $str = $this->setRequest($data); // 将所有参数转成字符串
        $goodsid = input('goodsid');
        $uid = session('uid');
        $token = session('token');
        $num = input('num');
        $sku_id = input('sku_id');
        $active_id = input('active_id');
        $type = input('type');  // 活动类型
        $this->assign('type', $type);
        $this->assign('goodsid', $goodsid);
        $this->assign('num', $num);
        $this->assign('sku_id', $sku_id);
        $this->assign('active_id', $active_id);
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        $this->assign('url_str', $str);
        return $this->fetch();

    }
    /*
    * 修改地址
    */
    public function edit_address()
    {
        // 将参数 转化成字符串返给页面
        $data = input();// 获取 所有请求参数
        $str = $this->setRequest($data); // 将所有参数转成字符串
        $uid = session('uid');
        $token = session('token');
        $address_id = input('address_id');  // 活动类型
        $this->assign('address_id', $address_id);

        $this->assign('uid', $uid);
        $this->assign('token', $token);
        $this->assign('url_str', $str);
        return $this->fetch();

    }
    /*
    * 地址列表
    */
    public function my_address()
    {
        // 将参数 转化成字符串返给页面
        $data = input();// 获取 所有请求参数
        $str = $this->setRequest($data); // 将所有参数转成字符串
        $goodsid = input('goodsid');
        $uid = session('uid');
        $token = session('token');
        $num = input('num');
        $sku_id = input('sku_id');
        $active_id = input('active_id');
        $type = input('type');  // 活动类型
        $this->assign('type', $type);
        $this->assign('goodsid', $goodsid);
        $this->assign('num', $num);
        $this->assign('sku_id', $sku_id);
        $this->assign('active_id', $active_id);
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        $this->assign('url_str', $str);
        return $this->fetch();

    }
    /*
     * 助理砍价
     */
    public function zhuli_kanjia()
    {
        $bargain_id = input('bargain_id');
        $kaikan_id = input('kaikan_id');
        $type = input('type');
        $yaoqingma = input('yaoqingma');
        if (empty(session('user_mobile')) && $type == 'ysorpt') {

            $url = request()->domain().'/api/fx/zhuli_kanjia?bargain_id='.$bargain_id.'&type='.$type.'&yaoqingma='.$yaoqingma.'&kaikan_id='.$kaikan_id.'&order_type=kanjia';
            $this->login($url, $type);
            if (empty( session('user_mobile'))) {
                $this->redirect('/Api/fx/binding_phone', ['bargain_id' => $bargain_id, 'type' => $type, 'result_type' => 'kanjia', 'yaoqingma' => $yaoqingma, 'kaikan_id' => $kaikan_id, 'order_type' => 'kanjia']);
            }
        }
        $uid = session('uid');
        $token = session('token');
        $this->assign('uid', $uid ? $uid : 0);
        $this->assign('token', $token ? $token : '');
        $this->assign('bargain_id', $bargain_id);
        $this->assign('kaikan_id', $kaikan_id);
        $this->assign('yaoqingma', $yaoqingma);
        return $this->fetch();
    }
    /*
     * 公众号授权的登陆  绑定手机号
     */
    public function binding_phone()
    {

        $uid = session('uid');
        $token = session('token');
        $bargain_id = input('bargain_id');
        $act_id = input('act_id');// 预售
        $c_id = input('c_id');
        $user_id = input('user_id');
        $yaoqingma = input('yaoqingma');
        $result_type = input('result_type');
        $order_type = input('order_type');
        $goodsid = input('goodsid');
        $sku_id = input('sku_id');
        $yin_id = input('yin_id');
        $share_uid = input('share_uid');
        $this->assign('share_uid', $share_uid);
        $this->assign('yin_id', $yin_id);
        $this->assign('sku_id', $sku_id);
        $this->assign('goodsid', $goodsid);
        $this->assign('order_type', $order_type);
        $this->assign('result_type', $result_type);
        $this->assign('yaoqingma', $yaoqingma);
        $this->assign('c_id', $c_id);
        $this->assign('user_id', $user_id);
        $this->assign('bargain_id', $bargain_id);
        $this->assign('act_id', $act_id);
        $this->assign('uid', $uid);
        $this->assign('token', $token);

        return $this->fetch();
    }


    /*
     * 处理传过来的参数
     * 将传过来的参数拼接成字符串
     */
    public function setRequest($data)
    {
        $str = '';
        if (is_array($data) && !empty($data)) {
            foreach ($data as $key => $val) {
                $str = $str.'/'.$key.'/'.$val;
            }
        }
        return $str;
    }
    /*
     * 生成订单
     */
    public function zhifu_order()
    {
        $uid = session('uid');
        $token = session('token');
        $order_id = input('order_id');
        $res =  Db::name('store_bag_log')->where(['log_uid' => $uid, 'log_bag_stat' => 0])->order('log_id desc')->find();
        if ($res) {
            Db::name('store_bag_log')->where(['log_id' => $res['log_id']])->update(['log_order_id' => $order_id]);
        }
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        $this->assign('order_id', $order_id);
        return $this->fetch();
    }
    /*
     * 支付成功
     */
    public function zhifu_success()
    {
        return $this->fetch();
    }
    /*
    * 支付取消
    */
    public function zhifu_error()
    {
        return $this->fetch();
    }
    /*
     * 公众号支付
     */
    function zhifus(){
        header("Content-Type: text/html;charset=utf-8");
        require_once EXTEND_PATH.'WxPayPubHelper.php';
        $orderId=input("order_id");

        //获得订单
        $order_info=db("order")->where(['order_id'=>$orderId])->find();
        if (!$order_info) {
            echo $orderId;
            exit;
        }
        $order_no = $order_info['order_no'];
        $openid=session('openid');//'oxO1t1k7FqyCyhSsMWjT6v2A1Rlg';//
        if (!$openid) {
            $url = request()->domain().'/Api/fx/zhifus/order_no/'.$order_no;
            $this->login($url, 'weixin');
            $openid=$_SESSION['openid'];
            $order_no=input("order_no");
        }
        $jsApi = new \JsApi_pub();
        //=========步骤2：使用统一支付接口，获取prepay_id============
        //使用统一支付接口
        $unifiedOrder = new \UnifiedOrder_pub();

        $unifiedOrder->setParameter("openid", "$openid"); //商品描述
        $unifiedOrder->setParameter("body", "订单支付" . $order_no); //商品描述
        //自定义订单号，此处仅作举例
        $timeStamp = time();
        //$total_fee = M("Order_info")->where("order_sn='$osn'")->getField("order_amount");
//        $out_trade_no = $order_info['order_no'];

        $unifiedOrder->setParameter("out_trade_no", $order_no); //商户订单号
        $unifiedOrder->setParameter("total_fee", $order_info['order_pay_price'] * 100); //总金额
//        $unifiedOrder->setParameter("total_fee", 0.01 * 100); //总金额
        $unifiedOrder->setParameter("notify_url", \WxPayConf_pub::NOTIFY_URL); //通知地址
        $unifiedOrder->setParameter("trade_type", "JSAPI"); //交易类型

        $prepay_id = $unifiedOrder->getPrepayId();
        //var_dump($prepay_id);exit;
        //=========步骤3：使用jsapi调起支付============
        $jsApi->setPrepayId($prepay_id);
        // var_dump($prepay_id);
        $jsApiParameters = $jsApi->getParameters();
        //var_dump($jsApiParameters);
        // $nextUrl="url('User/index')";
        $html = <<<EOF
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<script type="text/javascript">
	//调用微信JS api 支付
	//调用微信JS api 支付
	function jsApiCall()
	{
		WeixinJSBridge.invoke(
			'getBrandWCPayRequest',$jsApiParameters,
			function(res){
			 
				//WeixinJSBridge.log(res.err_msg);
				//alert(res.err_msg);
				//return;
				 if(res.err_msg == "get_brand_wcpay_request:ok") {
					 window.location.href=  window.location.origin + '/api/fx/zhifu_success';
				 }else{
				 	//alert(res.err_msg);
				   window.location.href= window.location.origin + "/api/fx/zhifu_error";
				 }
			}
		);
	}

	function callpay()
	{      
		if (typeof WeixinJSBridge == "undefined"){
		    if( document.addEventListener ){
		        document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
		    }else if (document.attachEvent){
		        document.attachEvent('WeixinJSBridgeReady', jsApiCall);
		        document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
		    }
		}else{
		    jsApiCall();
		}
	}
	callpay();
	</script>
EOF;

        exit($html) ;
        // $this->assign("jsApiParameters", $jsApiParameters);
    }
    //处理订单回调、
    function dohandel(){
        require_once EXTEND_PATH.'WxPayPubHelper.php';
        $data = file_get_contents("php://input");
        file_put_contents('./fxWxNotifys.txt', date('Y-m-d H:i:s',time()).serialize($data).PHP_EOL,FILE_APPEND);
        //使用通用通知接口
        $notify = new \Notify_pub();

        //存储微信的回调
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $notify->saveData($xml);
        //验证签名，并回应微信。
        //对后台通知交互时，如果微信收到商户的应答不是成功或超时，微信认为通知失败，
        //微信会通过一定的策略（如30分钟共8次）定期重新发起通知，
        //尽可能提高通知的成功率，但微信不保证通知最终能成功。
        //if ($notify->checkSign() == FALSE) {
        // $notify->setReturnParameter("return_code", "FAIL"); //返回状态码
        // $notify->setReturnParameter("return_msg", "签名失败"); //返回信息
        //} else {
        //$notify->setReturnParameter("return_code", "SUCCESS"); //设置返回码
        // }
        // $returnXml = $notify->returnXml();
        //echo $returnXml;

        //==商户根据实际情况设置相应的处理流程，此处仅作举例=======
        //以log文件形式记录回调信息
        //$log_ = new Log_();
        //$log_name="/wap/Uploads/log/notify_url.log";//log文件路径
        //$this->log_result($log_name,"【接收到的notify通知】:\n".$xml."\n");

        if ($notify->checkSign() == TRUE) {
            if ($notify->data["return_code"] == "FAIL") {
                //此处应该更新一下订单状态，商户自行增删操作
                //$this->log_result($log_name,"【通信出错】:\n".$xml."\n");
            } elseif ($notify->data["result_code"] == "FAIL") {
                //此处应该更新一下订单状态，商户自行增删操作
                //$this->log_result($log_name,"【业务出错】:\n".$xml."\n");
            } else {
                $data = $notify->xmlToArray($xml);
                $order_no = $data['out_trade_no'];
                //$res=db("sys_enum")->where(array('id'=>18))->update(['evalue'=>$data['result_code']]);
                if ($data['result_code'] == "SUCCESS") {
                    if ($this->checkorder($order_no)) {
                        //db("sys_enum")->where(array('id'=>17))->update(['evalue'=>$data['result_code']]);
                        $trade_no = $data['transaction_id'];
                        //商品订单
                        $api_model = new ApiPay();
                        $api_model->orderHandle($order_no, 'jsapi', $trade_no);

                    }
                    echo "success";
                } else {
                    echo "fail";
                }
                //此处应该更新一下订单状态，商户自行增删操作
                //$this->log_result($log_name,"【支付成功】:\n".$xml."\n");
            }

            //商户自行增加处理流程,
            //例如：更新订单状态
            //例如：数据库操作
            //例如：推送支付完成信息
        }
    }
    //判断订单状态
    function checkorder($ordersn){
        $orderInfo=db("order")->where(['order_no'=>$ordersn])->field("order_id,order_status")->find();
        if($orderInfo['order_status']==0){
            return true;
        }else{
            return false;
        }
    }
    /*
     * 分享优惠券 领取
     * 未使用
     */
    public function receiveCoupon()
    {
        $type =input('type', '');

        return $this->fetch();
    }
    /*
     * 分享优惠券
     * 未使用
     */
    public function my_discount()
    {
        $c_id = input('c_id');
        $user_id = input('user_id');
        $liuyan_val = input('liuyan_val');
        $type =input('type', '');
        if (empty(session('user_mobile')) && $type == 'ysorpt') {
            $goodsid = input('request.goodsid');
            $url = request()->domain().'/api/fx/my_discount?user_id='.$user_id.'&c_id='.$c_id.'&type='.$type.'&liuyan_val='.$liuyan_val;
            $this->login($url, 'weixin');
            if (empty( session('user_mobile'))) {
                $this->redirect('/Api/fx/binding_phone', ['type' => $type, 'c_id' => $c_id, 'user_id' => $user_id, 'liuyan_val' => $liuyan_val, 'result_type' => 'coupon']);
            }
        }
        $c_id = input('c_id');
//        $user_id = input('user_id');
        $liuyan_val = input('liuyan_val');
        $uid = session('uid');
        $token = session('token');
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        $this->assign('c_id', $c_id);
        $this->assign('to_uid', $user_id);
        $this->assign('liuyan_val', $liuyan_val);
        return $this->fetch();
    }
    /*
     *  优惠券领取后
     */
    public function fenxiang_hou()
    {
        return $this->fetch();
    }
    /*
     * 用户协议
     */
    public function user_protocol()
    {
        return $this->fetch();
    }
    /*
     * 分享大礼包
     */
    public function shop_libao()
    {
        $user_id = input('user_id');
        $yaoqingma = input('yaoqingma');
        $this->assign('user_id', $user_id);
        $this->assign('yaoqingma', $yaoqingma);
        return $this->fetch();
    }
    /*
     * 分享大礼包 更多
     */
    public function share_shop_gengduo()
    {
        $user_id = input('user_id');
        $yaoqingma = input('yaoqingma');
//        $uid = session('uid');
//        $token = session('token');
        $goods_id = input('goods_id');
        $jiage = input('jiage');
//        $is_seller = session('is_seller');
//        $this->assign('is_seller', $is_seller);
        $this->assign('goods_id', $goods_id);
        $this->assign('jiage', $jiage);
//        $this->assign('uid', $uid);
//        $this->assign('token', $token);
        $this->assign('user_id', $user_id);
        $this->assign('yaoqingma', $yaoqingma);
        return $this->fetch();
    }
    /*
     * 元宝分享
     */
    public function my_yuanbao()
    {
        $yaoqingma = input('yaoqingma');
        $share_uid = input('share_uid', 0);
        $yin_id = input('yin_id', 0);
        $types = input('type');
        if (empty(session('user_mobile')) && $types == 'weixin') {
            $url = request()->domain().'/api/fx/my_yuanbao?yaoqingma='.$yaoqingma.'&share_uid='.$share_uid.'&yin_id='.$yin_id.'&type='.$types;
            $this->login($url, $types);
            if (empty( session('user_mobile'))) {
                $this->redirect('/Api/fx/binding_phone', ['result_type' => 'yuanbao', 'yaoqingma' => $yaoqingma, 'share_uid' => $share_uid, 'yin_id' => $yin_id, 'type' => $types]);
            }
        }
        $yaoqingma = input('yaoqingma');
        $share_uid = input('share_uid', 0);
        $yin_id = input('yin_id', 0);
        $this->assign('yaoqingma', $yaoqingma);
        $this->assign('share_uid', $share_uid);
        $this->assign('yin_id', $yin_id);
        $uid = session('uid');
        $token = session('token');
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        return $this->fetch();
    }
    /*
        * 元宝领取成功
        */
    public function yuanbao_success()
    {
        return $this->fetch();
    }
    /*
    * 元宝领取失败
    */
    public function yuanbao_error()
    {
        return $this->fetch();
    }
    /**
     * 邀请注册
     */
    public function register() {

        $type = input('type');
        if (empty(session('user_mobile')) && $type == 'weixin') {
            $yaoqingma = input('yaoqingma');
            $url = request()->domain().'/api/fx/register?type='.$type.'&yaoqingma='.$yaoqingma;
            $this->login($url, $type);
            if (empty( session('user_mobile'))) {
                // 跳转到绑定页面
                $this->redirect('/Api/fx/binding_phone', ['type' => $type, 'result_type' => 'register', 'yaoqingma' => $yaoqingma]);

            } else {
                // 该微信号已绑定过 APP
                return $this->fetch('fx/register_success');
            }
        } else {
            // 已授权登录过
            return $this->fetch('fx/register_success');
        }
    }
    /**
     * 邀请 绑定成功页面
     */
    public function register_ok()
    {
        return $this->fetch();
    }

        /*
         * 分享页，待付款订单列表
         */
    public function pending_order()
    {
        $uid = session('uid');
        $token = session('token');
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        return $this->fetch();
    }

    public function orderdetail()
    {
        $uid = session('uid');
        $token = session('token');
        $order_id = input('request.order_id');
        $this->assign('uid', $uid);
        $this->assign('token', $token);
        $this->assign('order_id', $order_id);
        return $this->fetch();
    }
    public function demo()
    {
        $order_id = "717";
        $goods_info = Db::name('order_goods')->alias('a')->join('__GOODS__ b', 'a.og_goods_id=b.goods_id')->where('a.og_order_id', $order_id)->field('a.og_goods_id,a.order_commi_price,b.commission')->select();
        //总佣金
        $commission = 0.00;
        if($goods_info){
            foreach($goods_info as $v){
                $commission += $v['order_commi_price'] * ($v['commission'] / 100);
                var_dump($v['order_commi_price'], $v['commission'], $commission);
                echo "<br />";
            }
        }
        var_dump($commission);
    }

    // APP 分享
    public function mingxing_detail()
    {
        return $this->fetch();
    }

    public function find_detail()
    {
        return $this->fetch();
    }
    public function goods_details()
    {
        return $this->fetch();
    }
    public function logig_youhui()
    {
        return $this->fetch();
    }

}
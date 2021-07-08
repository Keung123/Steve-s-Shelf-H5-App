<?php
namespace wxpay;


class wxpay{
    
    protected $app_id;
    protected $mch_id;
    protected $app_secret;
    protected $notify_url;
    /* 
     *初始化
     */
    public function __construct(){
        $this->app_id = 'wx86e2ba24a16357bb';
        $this->mch_id = '1600882984';
        $this->app_secret = '11231e20139d4b6d6b7a19953c6e80d2';
        $this->notify_url = 'http://108.61.169.36/index/api/ApiPay/wxNotify';
    }

    /*
     * 获取prepay_id
     */
    public function getPrepay($out_trade_no, $price, $body){
        $wx_service = new wxpayService($this->app_id, $this->mch_id, $this->notify_url, $this->app_secret);
        $params = [
            'body' => $body,
            'out_trade_no' => $out_trade_no,
            'total_fee' => (int)$price,
            'trade_type' => 'APP',
        ];
        $result = $wx_service->unifiedOrder($params);
        $prepay_id = $wx_service->getAppPayParams($result['prepay_id']);
        return $prepay_id;
        // return $result;
    }

    /*
     * 通知
     */
    public function getNotify($data){
        $wx_service = new wxpayService($this->app_id, $this->mch_id, $this->notify_url, $this->app_secret);
        $notify_data = $wx_service->getNotifyData($data);
        // return ['code' => 1, 'data' => $notify_data];
        $notify_sign = '';
        if(isset($notify_data['sign']) && $notify_data['sign']){
            $notify_sign = $notify_data['sign'];
            unset($notify_data['sign']);
        }
        $create_sign = $wx_service->MakeSign($notify_data);
        if($create_sign == $notify_sign){
            return ['code' => 1, 'data' => $notify_data];
        }
        return ['code' => 0, 'msg' => '验签失败'];
    }

    /*
     * 通知返回
     */
    public function replyNotify($code, $msg){
        $wx_service = new wxpayService($this->app_id, $this->mch_id, $this->notify_url, $this->app_secret);
        $result = $wx_service->replyNotify($code, $msg);
        return $result;
    }
}

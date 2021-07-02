<?php

namespace app\api\controller;

use think\Response;
use app\common\service\PayWeChatAli as PayWeChatAliService;

class Pay extends Common
{
    public $payWeChatAliService;

    public function __construct()
    {
        parent::__construct();
        $this->payWeChatAliService = new PayWeChatAliService();
    }

    /**
     * 默认方法
     */
    public function index()
    {
        $this->error();
    }

    /**
     * 订单提交
     */
    public function submit()
    {
        $token = trim(input('token'));
        $uid = trim(input('uid'));
        if (!$this->getUid($token, $uid)) {
            return $this->json([], 0, '未知参数');
        }
        $out_trade_no = $this->request->request("out_trade_no");
        $title = $this->request->request("title");
        $amount = $this->request->request('amount');
        $type = $this->request->request('type');
        $method = $this->request->request('method', 'web');
        $openid = $this->request->request('openid', '');
        $auth_code = $this->request->request('auth_code', '');
        $notifyurl = $this->request->request('notifyurl', '');
        $returnurl = $this->request->request('returnurl', '');

        if (!$amount || $amount < 0) {
            $this->error("支付金额必须大于0");
        }

        if (!$type || !in_array($type, ['alipay', 'wechat'])) {
            $this->error("支付类型错误");
        }

        $params = [
            'type'         => $type,
            'out_trade_no' => $out_trade_no,
            'title'        => $title,
            'amount'       => $amount,
            'method'       => $method,
            'openid'       => $openid,
            'auth_code'    => $auth_code,
            'notifyurl'    => $notifyurl,
            'returnurl'    => $returnurl,
        ];

        $this->payWeChatAliService->submitOrder($params);
    }

    /**
     * 订单查询
     */
    public function orderFind()
    {
        $type = $this->request->param('type');
        $out_trade_no = $this->request->param('out_trade_no');

        return $this->payWeChatAliService->orderFind($out_trade_no, $type);
    }

    /**
     * 退款查询
     */
    public function refundFind()
    {
        $type = $this->request->param('type');
        $out_trade_no = $this->request->param('out_trade_no');

        return $this->payWeChatAliService->refundFind($out_trade_no, $type);
    }

    /**
     * 支付回调
     */
    public function notifyx()
    {
        $xml = file_get_contents('php://input');
        $xmlArray = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $type = $this->request->param('type');
        $txt_path = '';
        if($type == 'alipay'){
            $txt_path = 'uploads/wx_pay_log.txt';
        }else if($type == 'wechat'){
            $txt_path = 'uploads/ali_pay_log.txt';
        }

        file_put_contents($txt_path,json_encode($xmlArray).PHP_EOL,FILE_APPEND);

        $notifyx = $this->payWeChatAliService->notifyx($type);

        echo "success";
        return;
    }

    /**
     * 支付返回
     */
    public function returnx()
    {
        $xml = file_get_contents('php://input');
        $xmlArray = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $type = $this->request->param('type');
        $txt_path = '';
        if($type == 'alipay'){
            $txt_path = 'uploads/wx_pay_log.txt';
        }else if($type == 'wechat'){
            $txt_path = 'uploads/ali_pay_log.txt';
        }

        file_put_contents($txt_path,json_encode($xmlArray).PHP_EOL,FILE_APPEND);
        $returnx = $this->payWeChatAliService->returnx($type);

        return;
    }
}
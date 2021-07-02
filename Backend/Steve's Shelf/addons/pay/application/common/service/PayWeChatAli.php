<?php

namespace app\common\service;

use Exception;
use think\Response;
use think\Session;
use Yansongda\Pay\Pay;

/**
 * 订单服务类
 *
 */
class PayWeChatAli extends Base
{
    public static $config_ext = [
        'log' => [
            'file' => './logs/',
            'level' => 'info', // 建议生产环境等级调整为 info，开发环境为 debug
            'type' => 'single', // optional, 可选 daily.
            'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
        ],
        'http' => [
            'timeout' => 5.0,
            'connect_timeout' => 5.0,
        ]
    ];

    public static function submitOrder($params)
    {
        $type = isset($params['type']) && in_array($params['type'], ['alipay', 'wechat']) ? $params['type'] : 'wechat';
        $method = isset($params['method']) ? $params['method'] : 'web';
        $order_id = isset($params['out_trade_no']) ? $params['out_trade_no'] : date("YmdHis") . mt_rand(100000, 999999);
        $amount = isset($params['amount']) ? $params['amount'] : 1;
        $title = isset($params['title']) ? $params['title'] : "支付";
        $auth_code = isset($params['auth_code']) ? $params['auth_code'] : '';
        $openid = isset($params['openid']) ? $params['openid'] : '';

        $request = request();
        $notifyurl = isset($params['notifyurl']) ? $params['notifyurl'] : $request->root(true) . '/pay/index/' . $type . 'notify';
        $returnurl = isset($params['returnurl']) ? $params['returnurl'] : $request->root(true) . '/pay/index/' . $type . 'return/out_trade_no/' . $order_id;
        $config = self::getConfig($type);
        $config['http'] = self::$config_ext['http'];
        $config['log'] = self::$config_ext['log'] . $type . '.log';

        if($notifyurl){
            $config[$type]['notify_url'] = $notifyurl;
        }
        if($returnurl){
            $config[$type]['return_url'] = $returnurl;
        }

        if ($type == 'alipay') {
            //支付宝支付,请根据你的需求,仅选择你所需要的即可
            $order = [
                'out_trade_no' => $order_id,//你的订单号
                'total_amount' => $amount,//单位元
                'subject'      => $title,
            ];
            //如果是移动端自动切换为wap
            $method = $request->isMobile() ? 'wap' : $method;
            switch ($method) {
                case 'web':
                    //电脑支付,跳转
                    return Pay::alipay($config[$type])->web($order)->send();
                    break;
                case 'wap':
                    //手机网页支付,跳转
                    return Pay::alipay($config[$type])->wap($order)->send();
                    break;
                case 'app':
                    //APP支付,直接返回字符串
                    return Pay::alipay($config[$type])->app($order);
                    break;
                case 'scan':
                    //扫码支付,直接返回字符串
                    return Pay::alipay($config[$type])->scan($order);
                    break;
                case 'pos':
                    //刷卡支付,直接返回字符串
                    //刷卡支付必须要有auth_code
                    $order['auth_code'] = $auth_code;
                    return Pay::alipay($config[$type])->pos($order);
                    break;
                default:
                    //其它支付类型请参考：https://docs.pay.yansongda.cn/alipay
            }
        } else if($type == 'wechat'){
            $order = [
                'out_trade_no' => $order_id,//你的订单号
                'body'         => $title,
                'total_fee'    => $amount,
            ];
            switch ($method) {
                case 'mp':
                    //公众号支付
                    //公众号支付必须有openid
                    $order['openid'] = $openid;
                    return Pay::wechat($config[$type])->mp($order)->send();
                    break;
                case 'wap':
                case 'web':
                    //手机网页支付,跳转
                    return Pay::wechat($config[$type])->wap($order)->send();
                    break;
                case 'app':
                    //APP支付,直接返回字符串
                    return Pay::wechat($config[$type])->app($order)->send();
                    break;
                case 'scan':
                    //扫码支付,直接返回字符串
                    return Pay::wechat($config[$type])->scan($order)->send();
                    break;
                case 'pos':
                    //刷卡支付,直接返回字符串
                    //刷卡支付必须要有auth_code
                    $order['auth_code'] = $auth_code;
                    return Pay::wechat($config[$type])->pos($order)->send();
                    break;
                case 'miniapp':
                    //小程序支付,直接返回字符串
                    //小程序支付必须要有openid
                    $params['openid'] = $openid;
                    return Pay::wechat($config[$type])->miniapp($params)->send();
                    break;
                default:
            }
        }
    }

    /**
     * 获取配置
     * @param string $type 支付类型
     * @return array|mixed
     */
    public static function getConfig($type = 'wechat')
    {
        $config = get_addon_config('pay');
        return $config;
    }

    /**
     * 支付成功回调
     */
    public function notifyx($type)
    {
        $config = self::getConfig($type);
        switch($type){
            case 'wechat':
                if(!Pay::wechat($config[$type])->verify()){
                    $msg = '签名错误';
                    return;
                }
                break;
            case 'alipay';
                if(!Pay::alipay($config[$type])->verify()){
                    $msg = '签名错误';
                    return;
                }
                break;
            default:
                $msg = '签名错误';
                return;
        }

        return;
    }

    /**
     * 支付成功返回
     */
    public function returnx($type)
    {
        $config = self::getConfig($type);
        switch($type){
            case 'wechat':
                if(!Pay::wechat($config[$type])->verify()){
                    echo '签名错误';
                    return;
                }
                return Pay::wechat($config[$type])->success()->send();
                break;
            case 'alipay';
                if(!Pay::alipay($config[$type])->verify()){
                    echo '签名错误';
                    return;
                }
                return Pay::alipay($config[$type])->success()->send();
                break;
            default:
                echo '签名错误';
                return;
        }

        return;
    }

    /**
     * 订单查询
     */
    public function orderFind($out_trade_no, $type)
    {
        $config = self::getConfig($type);
        switch ($type) {
            case 'wechat':
                return Pay::wechat($config[$type])->find(['out_trade_no' => $out_trade_no]);
                break;
            case 'alipay':
                return Pay::alipay($config[$type])->find(['out_trade_no' => $out_trade_no]);
                break;
            default:
                return;

        }
        return;
    }

    /**
     * 退款查询
     */
    public function refundFind($out_trade_no, $type)
    {
        $config = self::getConfig($type);
        switch ($type) {
            case 'wechat':
                return Pay::wechat($config[$type])->find(['out_trade_no' => $out_trade_no], 'refund');
                break;
            case 'alipay':
                return Pay::alipay($config[$type])->find(['out_trade_no' => $out_trade_no], 'refund');
                break;
            default:
                return;

        }
        return;
    }

}
<?php
namespace app\common\service;

use app\common\service\Config as ConfigService;
use Aliyun\Core\Config as ALiconfig;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;
use Aliyun\Api\Sms\Request\V20170525\QuerySendDetailsRequest;

class SmsAli{

	public function __construct(){
		// 加载区域结点配置
        ALiconfig::load();
		// 获取短信配置
		$ConfigService = new ConfigService();
		$config_arr = $ConfigService->find();
		$this->config = [];
		$this->config['sms'] = $config_arr['sms'];
//        $this->config['sms']['sign'] = '合陶';
//        $this->config['sms']['register_code'] = 'SMS_110310011';
//        $this->config['sms']['find_code'] = 'SMS_110175005';
//        $this->config['sms']['bind_code'] = 'SMS_110040025';
//        $this->config['sms']['paypwd_code'] = 'SMS_110120016';
//        $this->config['sms']['access_key_id'] = 'LTAIN0cOpq3VOSAR';
//        $this->config['sms']['access_key_secret'] = '8LaNLWCDdI81zJIn2MXaeN4NGmu3WQ';
        // 短信API产品名
        $product = "Dysmsapi";

        // 短信API产品域名
        $domain = "dysmsapi.aliyuncs.com";

        // 暂时不支持多Region
        $region = "cn-hangzhou";

        // 服务结点
        $endPointName = "cn-hangzhou";

        // 初始化用户Profile实例
        $profile = DefaultProfile::getProfile($region, $this->config['sms']['access_key_id'], $this->config['sms']['access_key_secret']);

        // 增加服务结点
        DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);

        // 初始化AcsClient用于发起请求
        $this->acsClient = new DefaultAcsClient($profile);		
	}

	/*
	* 短信发送注册验证码
	*/
	public function sendRegister($mobile,$code){
		return $this->send($this->config['sms']['sign'],$this->config['sms']['register_code'],$mobile,['code'=>$code]);
	}

    /*
    * 短信发送找回密码验证码
    */
    public function sendFind($mobile,$code){
        return $this->send($this->config['sms']['sign'],$this->config['sms']['find_code'],$mobile,['code'=>$code]);
    }

   /*
    * 短信发送绑定手机
    */
    public function sendBind($mobile,$code){
        return $this->send($this->config['sms']['sign'],$this->config['sms']['bind_code'],$mobile,['code'=>$code]);
    }

    /*
     * 设置支付密码
     */
    public function sendSetPwd($mobile, $code){
        return $this->send($this->config['sms']['sign'],$this->config['sms']['paypwd_code'],$mobile,['code'=>$code]); 
    }
	/*
     * 手机登录
     */
    public function sendLogin($mobile, $code){
        return $this->send($this->config['sms']['sign'],$this->config['sms']['login_code'],$mobile,['code'=>$code]); 
    }
    /*
    * 发送提现成功消息
    */
    public function sendTxcgMsg($mobile, $name, $money){
        return $this->send($this->config['sms']['sign'],$this->config['sms']['txdz_code'],$mobile,['name'=>$name, 'money' => $money]);
    }
    /*
    * 发送提现失败消息
    */
    public function sendTxsbMsg($mobile, $name, $money){
        return $this->send($this->config['sms']['sign'],$this->config['sms']['txjj_code'],$mobile,['name'=>$name, 'money' => $money]);
    }

    public function sendVerUpdate($mobile)
    {
         return $this->send($this->config['sms']['sign'],'SMS_153997844',$mobile);
    }
    /**
     * 发送短信范例
     *
     * @param string $signName <p>
     * 必填, 短信签名，应严格"签名名称"填写，参考：<a href="https://dysms.console.aliyun.com/dysms.htm#/sign">短信签名页</a>
     * </p>
     * @param string $templateCode <p>
     * 必填, 短信模板Code，应严格按"模板CODE"填写, 参考：<a href="https://dysms.console.aliyun.com/dysms.htm#/template">短信模板页</a>
     * (e.g. SMS_0001)
     * </p>
     * @param string $phoneNumbers 必填, 短信接收号码 (e.g. 12345678901)
     * @param array|null $templateParam <p>
     * 选填, 假如模板中存在变量需要替换则为必填项 (e.g. Array("code"=>"12345", "product"=>"阿里通信"))
     * </p>
     * @param string|null $outId [optional] 选填, 发送短信流水号 (e.g. 1234)
     * @return stdClass
     */
    public function send($signName, $templateCode, $phoneNumbers, $templateParam = null, $outId = null) {

        // 初始化SendSmsRequest实例用于设置发送短信的参数
        $request = new SendSmsRequest();

        // 必填，设置雉短信接收号码
        $request->setPhoneNumbers($phoneNumbers);

        // 必填，设置签名名称
        $request->setSignName($signName);

        // 必填，设置模板CODE
        $request->setTemplateCode($templateCode);

        // 可选，设置模板参数
        if($templateParam) {
            $request->setTemplateParam(json_encode($templateParam));
        }

        // 可选，设置流水号
        if($outId) {
            $request->setOutId($outId);
        }

        // 发起访问请求
        $acsResponse = $this->acsClient->getAcsResponse($request);

        // 记录发送结果
        if($acsResponse->Code=='OK'){
        	trace('[ SUCCESS ]'.$phoneNumbers.'：'.$templateCode,'sms');
        }else{
        	trace('[ ERROR ]'.$phoneNumbers.'：Code，'.$acsResponse->Code.'，Message：'.$acsResponse->Message,'sms');
        }

        return $acsResponse;

    }	

    /**
     * 查询短信发送情况范例
     *
     * @param string $phoneNumbers 必填, 短信接收号码 (e.g. 12345678901)
     * @param string $sendDate 必填，短信发送日期，格式Ymd，支持近30天记录查询 (e.g. 20170710)
     * @param int $pageSize 必填，分页大小
     * @param int $currentPage 必填，当前页码
     * @param string $bizId 选填，短信发送流水号 (e.g. abc123)
     * @return stdClass
     */
    public function queryDetails($phoneNumbers, $sendDate, $pageSize = 10, $currentPage = 1, $bizId=null) {

        // 初始化QuerySendDetailsRequest实例用于设置短信查询的参数
        $request = new QuerySendDetailsRequest();

        // 必填，短信接收号码
        $request->setPhoneNumber($phoneNumbers);

        // 选填，短信发送流水号
        $request->setBizId($bizId);

        // 必填，短信发送日期，支持近30天记录查询，格式Ymd
        $request->setSendDate($sendDate);

        // 必填，分页大小
        $request->setPageSize($pageSize);

        // 必填，当前页码
        $request->setCurrentPage($currentPage);

        // 发起访问请求
        $acsResponse = $this->acsClient->getAcsResponse($request);

        // 打印请求结果
        // var_dump($acsResponse);

        return $acsResponse;
    }
}
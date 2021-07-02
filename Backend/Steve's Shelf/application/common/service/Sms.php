<?php
namespace app\common\service;

class Sms{

	/*
	* 短信发送注册验证码
	*/
	public function sendRegister($mobile,$code){
        $content='您的验证码是：'.$code.'。请不要把验证码泄露给其他人。';
		return $this->send($mobile, $content);
	}

    /*
    * 短信发送找回密码验证码
    */
    public function sendFind($mobile,$code){
        $content='您正在找回密码，验证码为：'.$code.'请不要把验证码泄露给其他人。如非本人操作，可不用理会！';
        return $this->send($mobile, $content);
    }

   /*
    * 短信发送绑定手机
    */
    public function sendBind($mobile,$code){
        $content='验证码：'.$code.'，请即时输入。您正在进行绑定手机号，绑定后有效提升您的账号安全。';
        return $this->send($mobile, $content);
    }

    /*
     * 设置支付密码
     */
    public function sendSetPwd($mobile, $code){
        $content='验证码：'.$code.'，您正在设置您的支付密码，请勿提供给别人';
        return $this->send($mobile, $content);
    }
	/*
     * 手机登录
     */
    public function sendLogin($mobile, $code){
        $content='验证码：'.$code.'，此验证码只用于登录您的账户，请勿提供给别人。';
        return $this->send($mobile, $content);
    }
    /*
    * 发送提现成功消息
    */
    public function sendTxcgMsg($mobile, $name, $money){
        return true;
    }
    /*
    * 发送提现失败消息
    */
    public function sendTxsbMsg($mobile, $name, $money){
        return true;
    }

    public function sendVerUpdate($mobile){
        return true;
    }
    /**
     * 发送短信范例
     * @return stdClass
     */
    public function send($phone, $content) {
        $url ="http://106.ihuyi.com/webservice/sms.php?method=Submit&account=C90439765&password=ca9d812b68cddce699b03ce4600ff38b&mobile=".$phone."&content=".$content;
        $data = file_get_contents($url);
        $xml = simplexml_load_string($data);
        $tt=$xml->msg;
        return $tt;
    }
}
<?php

class Wx {

    private $config = [
        'appid' => 'wxd0d85e24dfab2072',
        'secret' => 'b91dcf5202b5d4158be199af6b972a93'
    ];
    // 网页授权登录获取 OpendId
    public function GetOpenid($url)
    {
        if ($_SESSION['unionid'] && $_SESSION['openid']) {
            $data = [
                'unionid' => $_SESSION['unionid'],
                'openid' => $_SESSION['openid']
            ];
            return $data;
        }
        //通过code获得openid
        if (!isset($_GET['code'])){
            //触发微信返回code码
            //$baseUrl = urlencode($this->get_url());
            $baseUrl = urlencode($url);
            $urls = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址
            header("Location:$urls"); // 跳转到微信授权页面 需要用户确认登录的页面
            exit;
        }else {

            // 上面跳转, 这里跳了回来
            //获取code码，以获取openid
            $code = $_GET['code'];
            $data = $this->getOpenidFromMp($code);
            //dump($data['access_token']);exit;
            $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);
            $_SESSION['unionid'] = $data2['unionid'];
            $_SESSION['openid'] = $data2['openid'];
            return $data2;
        }
    }


    /**
     * 获取当前的url 地址
     * @return type
     */
    private function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }
    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->config['appid'];
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
//        $urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }
    /**
     *
     * 通过access_token openid 从工作平台获取UserInfo
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    {

        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回
        $data = json_decode($res,true);//取出openid access_token
        curl_close($ch);

        // 获取看看用户是否关注了 你的微信公众号， 再来判断是否提示用户 关注
        //if(!session('web_expires')){
        //session('web_expires')='1472114521';
        //}
        //$access_token2 = $this->get_access_token();
        // $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token2&openid=$openid";
        // $subscribe_info = httpRequest($url,'GET');
        //$subscribe_info = json_decode($subscribe_info,true);
        // $data['subscribe'] = $subscribe_info['subscribe'];

        return $data;
    }
    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        //通过code换取网页授权access_token  和 openid
        $url = $this->__CreateOauthUrlForOpenid($code);
        //dump($url);exit;
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回
        $data = json_decode($res,true);//取出openid access_token
        curl_close($ch);
        //session('access_token',$data['access_token']);
        return $data;
    }
    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->config['appid'];
        $urlObj["secret"] = $this->config['secret'];
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token,$openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;
    }
    /**
     *
     * 获取地址js参数
     *
     * @return 获取共享收货地址js函数需要的参数，json格式可以直接做参数使用
     */
    public function GetEditAddressParameters()
    {
        // $getData = $this->data;
        $data = array();
        // $data["appid"] =C('appid');
        $data["url"] = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $time = time();
        $data["timestamp"] = "$time";
        $data["noncestr"] = rand(1000000,9999999).rand(1000000,9999999).rand(10,99);
        //查询用户access_token  失效
        $userInfo=db("user")->where(['id'=>session("user_id")])->field("access_token,access_time")->find();

        //if($userInfo['access_time']<$time){
        $aurl='https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->config['appid'].'&secret='.$this->config['secret'];
        $accData=json_decode(httpRequest($aurl,'GET'),true);
        //dump($accData);exit;
        $sdata['access_time']=time()+7200;
        $sdata['access_token']=$accData['access_token'];
        db("user")->where(['id'=>session("user_id")])->update($sdata);
        session('access_token',$accData['access_token']);session("access_token_time",time());
        //}else{
        //获得access_token
        //$access_token=db("user")->where(['id'=>session("user_id")])->value("access_token");
        // $url='https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
        // $infos=json_decode(httpRequest($url,'GET'),true);

        //  if($infos['ticket']){
        //	session('jsapi_ticket',$infos['ticket']);session('jsapi_ticket_time',time());
        //}

        //}
        $nowtime=time();

        if($nowtime<session('jsapi_ticket_time')+7200){
            $data["jsapi_ticket"]=session('jsapi_ticket');
        }else{

            if($nowtime>session("access_token_time")+7200){
                $aurl='https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->config['appid'].'&secret='.$this->config['secret'];
                $accData=json_decode(httpRequest($aurl,'GET'),true);
                session('access_token',$accData['access_token']);session("access_token_time",time());
            }
            if($nowtime>session('jsapi_ticket_time')+7200){
                $url='https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.session('access_token').'&type=jsapi';
                $infos=json_decode(httpRequest($url,'GET'),true);
                session('jsapi_ticket',$infos['ticket']);session('jsapi_ticket_time',time());

            }
            $data["jsapi_ticket"]=session('jsapi_ticket');
        }

        ksort($data);
        $params = $this->ToUrlParams($data);
        $addrSign = sha1($params);

        $afterData = array(
            "addrSign" => $addrSign,
            "signType" => "sha1",
            "scope" => "jsapi_address",
            "appId" => $this->config['appid'],
            "timeStamp" => $data["timestamp"],
            "nonceStr" => $data["noncestr"]
        );
        $parameters = json_encode($afterData);
        return $parameters;
    }

    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }
}

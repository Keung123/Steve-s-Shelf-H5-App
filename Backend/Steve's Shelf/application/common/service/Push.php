<?php
namespace app\common\service;

 
use think\Loader;

class Push
{
	private $host = 'http://sdk.open.api.igexin.com/apiex.htm';
	private $appkey = 'Uz2X4GCMVZ5KuSY0ZPf6d1';
    private $appid = 'uO9rPNoeqf89OxdHtqqXJ2';
    private $mastersecret = 't1uGXP7jnp7pPGkmcHpSt6';   

	//测试
 	/* private $host = 'http://sdk.open.api.igexin.com/apiex.htm';
	private $appkey = 'd72aQeMAK68toQuPeR3zw8';
    private $appid = 'Qxz7rtTjUO7oqdQBgVCyq8';
    private $mastersecret = 'Yymfo5gsb167vX3kaauWq2';  */   

    //群推接口案例
    function pushMessageToApp($mes,$listId){
		Loader::import('getui/IGt', EXTEND_PATH,'.Push.php');
		import('getui/IGt', EXTEND_PATH,'.Push.php');
        $igt = new \IGeTui($this->host, $this->appkey, $this->mastersecret);
        $template = $this->IGtTransmissionTemplateDemos($mes,$listId);
        //$template = IGtLinkTemplateDemo();
        //个推信息体
        //基于应用消息体
        $message = new \IGtAppMessage();
        $message->set_isOffline(true);
        $message->set_offlineExpireTime(10 * 60 * 1000);//离线时间单位为毫秒，例，两个小时离线为3600*1000*2
        $message->set_data($template);

        $appIdList=array($this ->appid);
        $phoneTypeList=array('合陶家');//忽略了
        $provinceList=array('合陶家');//这个也忽略了
        $tagList=array('合陶家');
        //用户属性
        //$age = array("0000", "0010");


        //$cdt = new AppConditions();
        // $cdt->addCondition(AppConditions::PHONE_TYPE, $phoneTypeList);
        // $cdt->addCondition(AppConditions::REGION, $provinceList);
        //$cdt->addCondition(AppConditions::TAG, $tagList);
        //$cdt->addCondition("age", $age);

        $message->set_appIdList($appIdList);
        //$message->set_conditions($cdt->getCondition());

        $rep = $igt->pushMessageToApp($message);

        return $rep;
    }

//所有推送接口均支持四个消息模板，依次为通知弹框下载模板，通知链接模板，通知透传模板，透传模板
//注：IOS离线推送需通过APN进行转发，需填写pushInfo字段，目前仅不支持通知弹框下载功能

    function IGtTransmissionTemplateDemos($mes,$data){
       
        $template =  new \IGtTransmissionTemplate();
        $template->set_appId($this -> appid);//应用appid
        $template->set_appkey($this->appkey);//应用appkey
        $template->set_transmissionType(2);//透传消息类型
        $template->set_transmissionContent($data);//透传内容

        //APN高级推送
        Loader::import('getui\igetui\IGT.APNPayload', EXTEND_PATH);
        $apn = new \IGtAPNPayload();
        $alertmsg=new \DictionaryAlertMsg();
        $alertmsg->body=$mes['content'];
        $alertmsg->actionLocKey="查看";
        $alertmsg->locKey=$data['content'];
        $alertmsg->locArgs=array("locargs");
        $alertmsg->launchImage="launchimage";
//        IOS8.2 支持
        $alertmsg->title=$mes['title'];
        $alertmsg->titleLocKey="合陶家";
        $alertmsg->titleLocArgs=array("TitleLocArg");

        $apn->alertMsg=$alertmsg;
        $apn->badge=1;
        $apn->sound="";
        $apn->add_customMsg("payload","payload");
        $apn->contentAvailable=1;
        $apn->category="ACTIONABLE";
        $template->set_apnInfo($apn);

        //PushApn老方式传参
//    $template = new IGtAPNTemplate();
//          $template->set_pushInfo("", 10, "", "com.gexin.ios.silence", "", "", "", "");

        return $template;
    }
	
	 //单推接口案例
    function pushMessageToSingle($msg,$data){
	 
		 
		Loader::import('getui/IGt', EXTEND_PATH,'.Push.php');
		import('getui/IGt', EXTEND_PATH,'.Push.php');
        $igt = new \IGeTui($this->host, $this->appkey, $this->mastersecret);

        //消息模版：
        // 4.NotyPopLoadTemplate：通知弹框下载功能模板
		// $template =$this->IGtNotyPopLoadTemplateDemo();
        $template =$this-> IGtNotificationTemplateDemo($msg); 
		 
        //定义"SingleMessage"
		// Loader::import('getui\igetui\IGT', EXTEND_PATH,'.AppMessage.php');
        $message = new \IGtSingleMessage();
		 
		$message->set_isOffline(true);//是否离线
		$message->set_offlineExpireTime(3600*12*1000);//离线时间
		$message->set_data($template);//设置推送消息类型
        //$message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，2为4G/3G/2G，1为wifi推送，0为不限制推送
        //接收方
        $target = new \IGtTarget();
        $target->set_appId($this->appid);
        $target->set_clientId($data['client_id']);
		//$target->set_alias(Alias);

        try {
            $rep = $igt->pushMessageToSingle($message, $target);
			// var_dump($rep);
			// echo ("<br><br>");

        }catch(RequestException $e){
            $requstId =$e->getRequestId();
			$rep = $igt->pushMessageToSingle($message, $target,$requstId);
			// var_dump($rep);
			// echo ("<br><br>");
        }
		return($rep);
    }

	//多推接口案例
		function pushMessageToList($msg,$data)
		{
			
			putenv("gexin_pushList_needDetails=true");
			putenv("gexin_pushList_needAsync=true");

			Loader::import('getui/IGt', EXTEND_PATH,'.Push.php');
			import('getui/IGt', EXTEND_PATH,'.Push.php');
			$igt = new \IGeTui($this->host, $this->appkey, $this->mastersecret);
			//消息模版：
			// 1.TransmissionTemplate:透传功能模板
			// 2.LinkTemplate:通知打开链接功能模板
			// 3.NotificationTemplate：通知透传功能模板
			// 4.NotyPopLoadTemplate：通知弹框下载功能模板


			//$template =$this-> IGtNotyPopLoadTemplateDemo();
			//$template = IGtLinkTemplateDemo();
			$template = $this->IGtNotificationTemplateDemo($msg);
			
			//$template =$this->IGtTransmissionTemplateDemo();
			//个推信息体
			$message = new \IGtListMessage();
			$message->set_isOffline(true);//是否离线
			$message->set_offlineExpireTime(3600 * 12 * 1000);//离线时间
			$message->set_data($template);//设置推送消息类型
			$contentId = $igt->getContentId($message,"toList任务别名功能");	//根据TaskId设置组名，支持下划线，中文，英文，数字
            //接收方1
		    //查询用户信息
			//$userInfo=M("User")->where("user_id=1 and user_id=34")->field("client_id,systerm as system")->select();
			
			 /* $userInfo=array(
			    0=>['client_id'=>'6e5d6ecc10b0b5713eb7d42b8fee6ea0'],
				1=>['client_id'=>'b11a5f5f082a7697edf0fc1a396b55cb'],
				2=>['client_id'=>'455b4b8bcf09c767035092c19bcd498e']
			);  */
			foreach($data as $key=>$value){
				$target1 = new \IGtTarget();
				$target1->set_appId($this->appid);
				$target1->set_clientId($value['client_id']);
				$targetList[] = $target1;
			}
		 
			//$target1->set_alias(Alias);
			$rep = $igt->pushMessageToList($contentId, $targetList);
			return($rep);

		}
		function IGtNotificationTemplateDemo($msg){
			$template =  new \IGtNotificationTemplate();
			$template->set_appId($this->appid);//应用appid
			$template->set_appkey($this->appkey);//应用appkey
			$template->set_transmissionType(1);//透传消息类型
			$template->set_transmissionContent($msg['content']);//透传内容
			$template->set_title($msg['title']);//通知栏标题
			$template->set_text("合陶家");//通知栏内容
			$template->set_logo("http://wwww.igetui.com/logo.png");//通知栏logo
			$template->set_isRing(true);//是否响铃
			$template->set_isVibrate(true);//是否震动
			$template->set_isClearable(true);//通知栏是否可清除
			//$template->set_duration(BEGINTIME,ENDTIME); //设置ANDROID客户端在此时间区间内展示消息
		
		return $template;
	}

	function IGtNotyPopLoadTemplateDemo(){
		$template =  new \IGtNotificationTemplate();
		$template->set_appId($this->appid);//应用appid
		$template->set_appkey($this->appkey);//应用appkey
		//通知栏
		$template ->set_notyTitle("请填写通知标题");                 //通知栏标题
		$template ->set_notyContent("请填写通知内容"); //通知栏内容
		$template ->set_notyIcon("");                      //通知栏logo
		$template ->set_isBelled(true);                    //是否响铃
		$template ->set_isVibrationed(true);               //是否震动
		$template ->set_isCleared(true);                   //通知栏是否可清除
		//弹框
		$template ->set_popTitle("弹框标题");   //弹框标题
		$template ->set_popContent("弹框内容"); //弹框内容
		$template ->set_popImage("");           //弹框图片
		$template ->set_popButton1("下载");     //左键
		$template ->set_popButton2("取消");     //右键
		//下载
		$template ->set_loadIcon("");           //弹框图片
		$template ->set_loadTitle("请填写下载内容");
		$template ->set_loadUrl("请填写下载链接地址");
		$template ->set_isAutoInstall(false);
		$template ->set_isActived(true);
		//$template->set_duration(BEGINTIME,ENDTIME); //设置ANDROID客户端在此时间区间内展示消息
		return $template;
	}
	
	//判断安卓ios
	function getTypes($msg,$data){
		/*  $msg=[
				'title'=>'附近用户有人发单',
				'content'=>'附近用户有人发单，马上行动起来！'
			]; 
		*/
		//$msg=['title'=>'抢单成功','content'=>'抢单成功'];
		//$data=M("User")->where("user_id=1 or user_id=253 or user_id=37")->field("client_id ,systerm as system")->select();
		//var_dump($data);
		$iosArray=[];
		$andoreArray=[];
		foreach($data as $k=>$v){
			if($v['system']==1){
				//ios
				$iosArray[]=$v['client_id'];
			}else{
				$andoreArray[]['client_id']=$v['client_id'];

			}
		}
		if(count($iosArray)>0){
			//ios
			if(count($iosArray)>=2){
				//群推
				$message=$this->getIOSMsg($msg,true);

			}else{
				//单推
				$message=$this->getIOSMsg($msg,false);
			}
			$igt = new \IGeTui($this->host, $this->appkey, $this->mastersecret);
			$contentId = $igt->getAPNContentId($this->appid,$msgCache[1]);
            $igt->pushAPNMessageToList($this->appid, $contentId, $iosArray);
		}
		//dump($andoreArray);
		if(count($andoreArray)>0){
			if(count($andoreArray)>=2){
				//群推
				$this->pushMessageToList($msg,$andoreArray);
			}else{
				//单推
				$this->pushMessageToSingle($msg,$andoreArray[0]);
			}
		}
	}
	//ios信息
	function getIOSMsg($data, $isList = false)
    {
        $template = new \IGtAPNTemplate();
        $apn = new \IGtAPNPayload();
        $alertmsg = new \DictionaryAlertMsg();
        $alertmsg->body = $data['content'];
        $alertmsg->launchImage = "launchimage";
        //IOS8.2 支持
        $alertmsg->title = $data['title'];
        //$alertmsg -> titleLocKey = $data['title'];
        //$alertmsg -> titleLocArgs = array("TitleLocArg");
        $apn->alertMsg = $alertmsg;
        $apn->badge = 1;
        $apn->add_customMsg("payload", $data['payload']);
        $apn->contentAvailable = 1;
        $apn->category = "ACTIONABLE";
        $template->set_apnInfo($apn);

        if ($isList) {
            $message = new \IGtListMessage();
            $message->set_data($template);
        } else {
            $message = new \IGtSingleMessage();
            $message->set_isOffline(true);//是否离线
            $message->set_offlineExpireTime(3600 * 12 * 1000);//离线时间
            $message->set_data($template);//设置推送消息类型
        }
        //$ret = $this -> igt -> pushAPNMessageToSingle($this -> appId, $this -> deviceToken, $message);
        //var_dump($ret);
        return $message;
    }

}
var client_id;
var app_system;
mui.plusReady(function(){
  	cid_info = plus.push.getClientInfo();
  	client_id = cid_info.clientid;
  	app_system=plus.os.name;
  	//alert(client_id);
  	//client_id = 222;
  	localStorage.setItem('client_id',client_id);
  	console.log(client_id)
})
var auths = {};
	var obj = {};
	var weixin_data = {};
	function plusReady(){
		plus.oauth.getServices(function(services){
			for(var i in services){
				var service = services[i];
				auths[service.id] = service;
			}
		});
	}
	if(window.plus){
		plusReady();
	}else{
		document.addEventListener("plusready",plusReady,false);
	}
	//登陆认证
	function login(id){ 
		
		console.log("-----登录认证------");
		console.log(id);
		var auth = auths[id];
		console.log(JSON.stringify(auth));
		//类型标记
		if(id == "qq"){
			obj['type'] ='qq';
		}else if(id == "weixin"){
			obj['type'] = 'weixin';
			
		}else{
			obj['type'] = 3;
		}
		if(auth){
			var w = null;
			if(plus.os.name == "Android"){
				var w = plus.nativeUI.showWaiting();
			}
			document.addEventListener('pause',function(){
				setTimeout(function(){
					w&&w.close();w = null;
				},2000);
			},false);
			auth.login(function(){
				w&&w.close();w = null;
				console.log("登录认证成功:");
				console.log(JSON.stringify(auth.authResult));//获取位置
				//获取用户openid
				weixin_data.type = id;
				if(id == "weixin"){
					obj['openid'] =  auth.authResult.unionid;
					weixin_data.unionid = auth.authResult.openid;
					weixin_data.sex = auth.userInfo.sex;
					//weixin_data.token = auth.authResult.refresh_token;
				}else if(id == "qq"){
					obj['openid'] =  auth.authResult.openid ;//微信为unionid，QQ为openid,微博为uid
					weixin_data.openid = auth.authResult.openid;
					weixin_data.gender = auth.userInfo.gender;
				}else{
					obj['openid'] = auth.authResult.uid;
				}
				
				userinfo(auth);
			},function(e){
				w&&w.close();w = null;
				console.log("登录认证失败:");
				console.log(e);
				alert("["+e.code+"]"+e.message);
				console.log("登录失败");
			});
		}else{
			console.log("无效的登录认证通道!");
//        plus.nativeUI.alert("无效的登录登录认证通道!",null,"登录");
		}
	}
	//获取用户信息
	function userinfo(a){
		console.log("------获取用户信息------");
		a.getUserInfo(function(){
			console.log("获取用户信息成功:");
			//获取用户名 性别
			console.log(JSON.stringify(a));
			var nickname = a.userInfo.nickname||a.userInfo.name;
			var province = a.userInfo.province;
			var city = a.userInfo.city;
			var gender = a.userInfo.gender;
			var sex = a.userInfo.sex;
//			.substring(0,a.userInfo.headimgurl.length-1)+96
			var img = a.userInfo.figureurl_qq_2 || a.userInfo.profile_image_url || a.userInfo.headimgurl;
//			console.log(img);return;
			localStorage.setItem('type',JSON.stringify(obj.type));/*本地存储状态和login_name*/
			localStorage.setItem('login_name',JSON.stringify(obj.login_name));
			alert("欢迎“"+nickname+"”登录!");
			//截取较长的用户名
			var re = /[\u4e00-\u9fa5]/;
			if(re.test(nickname)){
				obj['username'] = nickname.substring(0,10);
			}else{
				obj['username'] = nickname.substring(0,20);
			}
			//性别判断
			obj['sex'] = sex;
			obj['gender'] = gender;
			obj['img'] = img;
//			if( sex == 1 || sex == '男' || sex == 'm' ) {
//				obj['sex'] =  "2";
//				weixin_data.sex = sex;
//			}else{
//				obj['sex'] =  "1";
//				weixin_data.sex = sex;
//			}
//			
//			if( gender == 1 || sex == '男' || gender == 'm' ) {
//				obj['gender'] =  "2";
//				weixin_data.gender = gender;
//			}else{
//				obj['gender'] =  "1";
//				weixin_data.gender = gender;
//			}
			
			//console.log(JSON.stringify(obj));
			
			if(weixin_data.type=='weixin'){
				weixin_data.headimgurl = img;
				weixin_data.nickname = nickname;	
				weixin_data.province = province;
				weixin_data.city = city;
				weixin_data.app_system=app_system;
				weixin_data.clientId=client_id;
			}else if(weixin_data.type=='qq'){
				weixin_data.nickname = nickname;
				weixin_data.figureurl_qq_1 = img;	
				weixin_data.app_system=app_system;
				weixin_data.clientId=client_id;
				console.log(11111111);
				//alert(client_id);
			}
			//传递用户信息
			console.log(JSON.stringify(weixin_data));
			$.ajax({
				type:"post",
				dataType:"json",
				url:hetao.url +'Login/apiLogin',
				data:weixin_data,
				success:function (res) {
					console.log(JSON.stringify(res));
//					localStorage.setItem('userId',JSON.stringify(msg.data.user_id));
//              clicked("../../index.html");
					if(res.status == 1){
						console.log(JSON.stringify(res));
						//location.href="<{:U('index/index')}>";
				        //跳转到注册页面	
				        if(res.data.user_mobile==null){
				        	localStorage.setItem('user_id',res.data.uid);
							localStorage.setItem('token',res.data.token);
				        	ca.newInterface({
				        		url:'../../pages/personal/binding_phone.html',
				        		id:'../../pages/personal/binding_phone.html'
				        	})
				        }else{
				        	ca.prompt('登录成功');
	      					localStorage.setItem('user_id',res.data.uid);
							localStorage.setItem('token',res.data.token);
							localStorage.setItem('login_type',res.data.is_seller);
	      					var arr=['pages/index.html','pages/find.html','pages/shop.html','pages/car.html','pages/my.html','index/index-page.html','goods-details.html','index/goods-details.html','goods-details-jifen.html','goods-details-yushou.html','seckill-details.html','pintuan_xx.html'];
							ca.sendNotice(arr,'login',{});	
							ca.newInterface({
								url: '../../index.html',
								id: '../../index.html',
								createNew:true,
							})
//	      					setInterval(function(){
//	      						ca.closeCurrentInterface();      
//	      					},300)
				       }					
					}else{
						alert(res.msg);
					}
				},
				error:function(e){
					console.log(JSON.stringify(res));
					console.log(JSON.stringify(e));
					alert("登录失败，请重新登录!");
				}
			});
		},function(e){
			console.log("获取用户信息失败:");
			console.log("["+e.code+"]"+e.message);
			plus.nativeUI.alert("获取用户信息失败!");
		});
	};


	var _h=$(window).height();
	$(".login").css("height",_h);
	$('#get_login').on('tap',function(){
		mui.openWindow({
			url: "/login/login.html",
			id: "/login/login.html",
			waiting: {
				autoShow: false, //自动显示等待框，默认为true
			},show:{
				aniShow:"fade-in"
			}
		})
	})
	$('.xieyi').on('tap',function(){
		mui.openWindow({
			url: "/my/setting/privacy_article.html",
			id: "/my/setting/privacy_article.html",
			waiting: {
				autoShow: false, //自动显示等待框，默认为true
			},show:{
				aniShow:"fade-in"
			}
		})
	})

	$('.to_login').on('tap',function(){
		window.location.href=hetao.url3+"/index/index/index";
	})

	var telReg = /^[1](([3][0-9])|([4][5-9])|([5][0-3,5-9])|([6][5,6])|([7][0-8])|([8][0-9])|([9][1,8,9]))[0-9]{8}$/;
	//点击获取验证码
	$('#again_code').on('tap',function(){

	var _this = $(this);
	//获取手机号
	var tel = $('#phone').val();
	if(!tel) {
		mui.toast('手机号不能为空！');
		return;
	}
	//验证手机号规则
	if(!telReg.test(tel)) {
		mui.toast('手机号格式不正确!');
		return;
	}
	_this.addClass('no_click');
	_this.html("正在请求中");
	console.log(hetao.url);
	console.log(hetao.url3);
	console.log(api);
	$.ajax({
		type: "POST",
		url: hetao.url + 'login/getCode',
		data:{
			mobile: tel,
			type:1
		},
		success: function(data) {
			if(data.status == 1) {
				// mui.toast("发送成功,验证码是:" + data.data);
				var again_time = 60;
				interval = setInterval(function() {
					again_time--;
					_this.html("重发(" + again_time + ")秒");
					//60秒时间到后重新发送
					if(again_time <= 0) {
						_this.html("重新获取");
						_this.removeClass('no_click');
						//清除定时器
						clearInterval(interval);
					}
				}, 1000);
			} else {
				_this.removeClass('no_click');
				_this.html("获取验证码");
				mui.toast(data.msg);
				return;
			}
		},
		error: function(err) {
			$("#again_code").removeClass('no_click');
			$('#again_code').html("获取验证码");
			mui.toast('网络连接失败!');
			log(S(err))
			return;
		}
	})
})
	//点击注册
	$('#reg_btn').on('tap',function(){
	var _this = $(this);
	//获取手机号
	var tel = $('#phone').val(); 
	if(!tel) {
		mui.toast('手机号不能为空！');
		return;
	}
	//验证手机号规则
	if(!telReg.test(tel)) {
		mui.toast('手机号格式不正确!');
		return;
	}
	//获取验证码
	var code = $('#reg_code').val();
	if(!code) {
		mui.toast('验证码不能为空！');
		return;
	}
	//验证密码
	var reg_psw = $('#reg_psw').val();
	if(!reg_psw) {
		mui.toast('密码不能为空！');
		return;
	}
	//邀请码
	var invite_code = $('#reg_yzm').val();
	//验证密码
	if(!/^(?!([a-zA-Z]+|\d+)$)[a-zA-Z\d]{6,12}$/.test(reg_psw)){  
		mui.toast('密码为6-12位数字和字母组合'); 
		return 
	}
	var err =0;
	//邀请码
	var datas = {
		code:code,
		mobile: tel,
		pwd: reg_psw,
		invite_code:invite_code
	};
	if (err == 1) {
		return false;
	} 
	_this.addClass('no_click');
	mui.showLoading('注册中', 'div');
	$.ajax({
		type: "POST",
		url: hetao.url + "login/register",
		data: datas,
		success: function(res) {
			log(S(res))
			if(res.status == 1) {
				localStorage.setItem('userId', res.data.uid);
				localStorage.setItem('token',res.data.token);
				localStorage.setItem('is_seller',res.data.is_seller);
				mui.toast('注册成功');
				setTimeout(function () {
                    window.location.href=hetao.url3+"/index/index/index";
                },500)
			} else {
				mui.hideLoading();
				_this.removeClass('no_click');
				mui.toast(res.msg);
				return;
			}
		},
		error: function(err) {
			log(S(err))
			mui.hideLoading();
			_this.removeClass('no_click');
			mui.toast('注册失败,请重试');
			return;
		}
	})
})
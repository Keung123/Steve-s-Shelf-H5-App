var BASE_URL = "http://daoxingtianxia.henanyimu.com/apis/";
var IMG_URL = "http://daoxingtianxia.henanyimu.com/";
var APPID = 'benben';
var APPSECRET = 'longdada123456';
var fly;
//工具方法 

var log = console.log.bind(console);

var S = JSON.stringify.bind(JSON);

var retel = /^1[345678]\d{9}$/;
fly = new Fly();
fly.config.baseURL = BASE_URL;
fly.config.timeout = 100000;

var newFly = new Fly;
newFly.config = fly.config;
var errorPrompt = function(err) {
	mui.toast(err && err.msg || '网络繁忙');
}

fly.interceptors.request.use(function(request) {

		log(S(request))

	var TOKEN = localStorage.getItem('TOKEN')

	if(TOKEN) {

		request.headers['A-Token'] = TOKEN;

		return request;

	} else {

		fly.lock(); //锁住请求

		return newFly.post('toKen/generateApiToken', {
			app_id: APPID,
			app_secret: APPSECRET
		}).then(function(res) {

			var res = res.data;
			var time = res.data.time;
			var rand_str = res.data.rand_str;
			var _token = hex_md5(hex_md5(rand_str + APPID + time + rand_str + APPSECRET + 'longdada123456'));

			localStorage.setItem('TOKEN', _token)

			//			log(localStorage.getItem('TOKEN'))
			request.headers['A-Token'] = _token;
			return request;
		}).finally(function() {
			fly.unlock();
		})

	}

});

fly.interceptors.response.use(function(response, promise) {

	//   if (!(response && response.data && response.code === 0)) {
	//     errorPrompt(response)
	//   }

	//	log(S(response.data)) 

	//token失效
	if(response.data.code == 3) {

		log('token失效 ,自动再次获取')
		fly.lock();

		return newFly.post('toKen/generateApiToken', {
			app_id: APPID,
			app_secret: APPSECRET
		}).then(function(res) {

			var res = res.data;

			var time = res.data.time;
			var rand_str = res.data.rand_str;

			var _token = hex_md5(hex_md5(rand_str + APPID + time + rand_str + APPSECRET + 'longdada123456'));

			localStorage.setItem('TOKEN', _token)

			newFly.config.headers['A-Token'] = _token;

		}).finally(function() {
			fly.unlock();
		}).then(function() {

			return fly.request(response.request);

		})

	} else {

		return promise.resolve(response.data)
	}

}, function(err, promise) {

	//	log(S(err))
	errorPrompt(err)
	return promise.reject(err)
});

//}
var API = {
	//获取验证码
	sendSms: function(user_login) {

		return fly.post('user/sendSms', {

			user_login: user_login
		});

	},
	//手机号验证接口
	validateMobile: function(send_type, user_login) {

		return fly.post('user/validateMobile', {
			send_type: send_type,
			user_login: user_login
		});

	},
	check_code: function(type, tel, succFn, errFn) {

		var valida = this.validateMobile(type, tel);

		valida.then(function(res) {

			succFn && succFn(res)

		})

		valida.catch(function(err) {

			errFn && errFn(err)
		})

	},
	//获取验证码
	sendSms: function(user_login) {

		return fly.post('user/sendSms', {

			user_login: user_login
		});

	},
	//注册
	register: function(user_login, passwd, parent_id) {

		return fly.post('user/register', {
			user_login: user_login,
			passwd: passwd,
			parent_id: parent_id
		});

	},
}
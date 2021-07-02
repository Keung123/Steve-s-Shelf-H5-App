//工具方法
var log = console.log.bind(console);
var S = JSON.stringify.bind(JSON);
var P = JSON.parse.bind(JSON);
var _uid = localStorage.getItem('userId');
var _token = localStorage.getItem('token');
//var api = 'http://jz.aipython.top/api/';
//var imgUrl = 'http://jz.aipython.top/';
var api = 'http://app.hangmakeji.com/api/';
var imgUrl = 'http://app.hangmakeji.com/';
//登录验证
function check_login() {
    var userId = localStorage.getItem("userId");
    if (!userId) {
        mui.openWindow({
            url: "/login/login.html",
            id: "/login/login.html",
            waiting: {
                autoShow: false, //自动显示等待框，默认为true
			},
        })
		return false;
	}
    return true;
}
mui.plusReady(function () {
    //全局点击事件
	plus.webview.currentWebview().setStyle({scrollIndicator:'none'});
    plus.navigator.setStatusBarStyle('dark');
    $(".newact").on("tap", function (e) {
        e.stopPropagation();
        var url = $(this).attr("url");
        if (!url) {
			return;
        }
        var web = plus.webview.getWebviewById(url);
        if (web) {
            plus.webview.show(web, "zoom-fade-out", 300);
            return
        }
        mui.openWindow({
            url: url,
            id: url,
			waiting: {
                autoShow: false, //自动显示等待框，默认为true
            }
        })
    })
    //全局点击事件
    $(".newactlogin").on("tap", function (e) {
        if (!check_login()) {
            return;
        }
        e.stopPropagation();
        var url = $(this).attr("url");
		if (!url) {
			return;
        }
        var web = plus.webview.getWebviewById(url)
        if (web) {
			plus.webview.show(web, "zoom-fade-out", 300);
            return
        }
        mui.openWindow({
            url: url,
            id: url,
            waiting: {
                autoShow: false, //自动显示等待框，默认为true
            }
        })
    })
    //断网检测
//     document.addEventListener("netchange", function () {
//         var nt = plus.networkinfo.getCurrentType();
//         switch (nt) {
//             case plus.networkinfo.CONNECTION_ETHERNET:
//             case plus.networkinfo.CONNECTION_WIFI:
//                 mui.toast("已连接WiFi");
//                 plus.webview.getWebviewById('/index/netError/netError.html').close('none');
//                 break;
//             case plus.networkinfo.CONNECTION_CELL2G:
//             case plus.networkinfo.CONNECTION_CELL3G:
//             case plus.networkinfo.CONNECTION_CELL4G:
//                 mui.toast("已切换到3G/4G");
//                 plus.webview.getWebviewById('/index/netError/netError.html').close('none');
//                 break;
//             default:
//                 mui.toast("当前没有网络，请检测网络设置。");
//                 mui.openWindow({
//                     url: '/index/netError/netError.html',
//                     id: '/index/netError/netError.html',
//                 })
//                 break;
//         }
//     }, false);
});
//UI增强
(function ($, window) {
    //显示加载框
    $.showLoading = function (message, type) {
        if ($.os.plus && type !== 'div') {
            $.plusReady(function () {
                plus.nativeUI.showWaiting(message);
            });
		} else {
            var html = '';
            html += '<i class="mui-spinner mui-spinner-white"></i>';
            html += '<p class="text">' + (message || "数据加载中") + '</p>';
            //遮罩层
            var mask = document.getElementsByClassName("mui-show-loading-mask");
            if (mask.length == 0) {
                mask = document.createElement('div');
                mask.classList.add("mui-show-loading-mask");
                document.body.appendChild(mask);
                mask.addEventListener("touchmove", function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                });
            } else {
                mask[0].classList.remove("mui-show-loading-mask-hidden");
            }
            //加载框
            var toast = document.getElementsByClassName("mui-show-loading");
            if (toast.length == 0) {
                toast = document.createElement('div');
                toast.classList.add("mui-show-loading");
                toast.classList.add('loading-visible');
                document.body.appendChild(toast);
                toast.innerHTML = html;
                toast.addEventListener("touchmove", function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                });
            } else {
                toast[0].innerHTML = html;
                toast[0].classList.add("loading-visible");
            }
        }
    };
    //隐藏加载框
    $.hideLoading = function (callback) {
        if ($.os.plus) {
            $.plusReady(function () {
                plus.nativeUI.closeWaiting();
            });
        }
        var mask = document.getElementsByClassName("mui-show-loading-mask");
        var toast = document.getElementsByClassName("mui-show-loading");
        if (mask.length > 0) {
            mask[0].classList.add("mui-show-loading-mask-hidden");
        }
        if (toast.length > 0) {
            toast[0].classList.remove("loading-visible");
            callback && callback();
        }
    }
})(mui, window);
//模板字符串() =>  
String.prototype.render = function (context) {
    return this.replace(/{{(.*?)}}/g, function (match, key) {
        return context[key.trim()]
    });
};
//获取指定范围的随机数
function get_random(max, min) {
    return Math.floor(Math.random() * (max - min + 1) + min);
}
if(window.plus){
	plusReady();
}else{
	document.addEventListener("plusready",plusReady,false);
}
function plusReady(){
	if(plus.os.name=="iOS"){ 
		$('.mui-content').css({'height':'100%','overflow-y':'scroll'})
	}; 
}
function hidden(){
	if(window.plus){
		plusReady(); 
	}else{
		document.addEventListener("plusready",plusReady,false);
	}
	function plusReady(){
		if(plus.os.name=="iOS"){ 
			$('.mui-content').css({'height':'100%','overflow':'hidden'});
		}; 
	}
}
function scroll(){
	if(window.plus){
		plusReady(); 
	}else{
		document.addEventListener("plusready",plusReady,false);
	}
	function plusReady(){
		if(plus.os.name=="iOS"){ 
			$('.mui-content').css({'height':'100%','overflow-y':'scroll'})
		}; 
	}
}
mui.plusReady(function(){   
	if(plus.os.name == "Android"){
		plus.navigator.setStatusBarStyle("dark");
	}else if(plus.os.name == "iOS"){
		plus.navigator.setStatusBarStyle("UIStatusBarStyleDefault");
	}
});
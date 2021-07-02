var api = window.location.origin + '/api/';
var iapi = window.location.origin;
var img_url = window.location.origin;
var base_url=window.location.origin + '/api/';
var root_url=window.location.origin + '/uploads/';
var log = console.log.bind(console);
function check_login() { 
	
	var userId = localStorage.getItem("userId");

	if(!userId) {
		
		//plus.webview.currentWebview().opener().close('none')
		mui.openWindow({
			url: "/user/login_xuanze.html",
			id: "/user/login_xuanze.html",
			waiting: {
				autoShow: false, //自动显示等待框，默认为true
			}
		})
		return false;
	}else{
		
		return true;
	}
	
}

mui.plusReady(function() {
	//全局点击事件
	$(".newact").on("tap", function() {
		var url = $(this).attr("url");
		if(!url) {
			return;
		}
		var web = plus.webview.getWebviewById(url)
		if(web) {
			plus.webview.show(web, "fade-in", 300);
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
	$(".newactlogin").on("tap", function() {
		if(!check_login()) {
			return;
		}
		var url = $(this).attr("url");
		if(!url) {
			return;
		}
		var web = plus.webview.getWebviewById(url)
		if(web) {
			plus.webview.show(web, "fade-in", 300);
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
})

function wainshow() {
	if(plus.networkinfo.getCurrentType() == plus.networkinfo.CONNECTION_NONE) {
		mui.toast("网络异常，请检查网络设置！");
		mui.openWindow({
			url: '/public/noNet.html',
			id: '/public/noNet.html',
			waiting: {
				autoShow: false, //自动显示等待框，默认为true
			}
		})

	} else {

	}
}
//数组去重
/*Array.prototype.unique3 = function() {
	var res = [];
	var json = {};
	for(var i = 0; i < this.length; i++) {
		if(!json[this[i]]) {
			res.push(this[i]);
			json[this[i]] = 1;
		}
	}
	return res;
}
*/
function getDate(nS) {
	return new Date(parseInt(nS) * 1000).toLocaleString().replace(/年|月/g, "-").replace(/日/g, " ");
}

function addcar(id) {
	var gwc = plus.storage.getItem('gwc');
	if(!gwc) {
		gwc = id;
	}

	var gw = gwc.split(",");
	gw.push(id);
	gw = gw.unique3();

	var ngwc = gw.toString()
	plus.storage.setItem('gwc', ngwc);
	mui.toast('添加购物车成功！')
	plus.webview.getWebviewById('pages/cart.html').evalJS('getCar()')
	plus.webview.getLaunchWebview().evalJS('gwc()')
}

function delcar(id) {
	var gwc = plus.storage.getItem('gwc');
	var gw = gwc.split(",");
	gw.remove(id)
	var ngwc = gw.toString()
	plus.storage.setItem('gwc', ngwc);
	mui.toast('删除成功！');
	plus.webview.getWebviewById('pages/cart.html').evalJS('getCar()')
	plus.webview.getLaunchWebview().evalJS('gwc()')

}

/*Array.prototype.indexOf = function(val) {
	for(var i = 0; i < this.length; i++) {
		if(this[i] == val) return i;
	}
	return -1;
};
Array.prototype.remove = function(val) {
	var index = this.indexOf(val);
	if(index > -1) {
		this.splice(index, 1);
	}
};*/


//模板字符串() =>  
String.prototype.render = function (context) {
  return this.replace(/{{(.*?)}}/g, function(match, key){
  	return context[key.trim()]
  });
};

String.prototype.startWith=function(str){     
  var reg=new RegExp("^"+str);     
  return reg.test(this);        
} 


// 数字前补0

function buling(num){
	
	return num>=10?num:'0'+num;
}
var log = console.log.bind(console);

(function($, window) {
    //显示加载框
    $.showLoading = function(message,type) {
        if ($.os.plus && type !== 'div') {
            $.plusReady(function() {
                plus.nativeUI.showWaiting(message);
            });
        } else {
            var html = '';
            html += '<i class="mui-spinner mui-spinner-white"></i>';
            html += '<p class="text">' + (message || "数据加载中") + '</p>';

            //遮罩层
            var mask=document.getElementsByClassName("mui-show-loading-mask");
            if(mask.length==0){
                mask = document.createElement('div');
                mask.classList.add("mui-show-loading-mask");
                document.body.appendChild(mask);
                mask.addEventListener("touchmove", function(e){e.stopPropagation();e.preventDefault();});
            }else{
                mask[0].classList.remove("mui-show-loading-mask-hidden");
            }
            //加载框
            var toast=document.getElementsByClassName("mui-show-loading");
            if(toast.length==0){
                toast = document.createElement('div');
                toast.classList.add("mui-show-loading");
                toast.classList.add('loading-visible');
                document.body.appendChild(toast);
                toast.innerHTML = html;
                toast.addEventListener("touchmove", function(e){e.stopPropagation();e.preventDefault();});
            }else{
                toast[0].innerHTML = html;
                toast[0].classList.add("loading-visible");
            }
        }   
    };

    //隐藏加载框
      $.hideLoading = function(callback) {
        if ($.os.plus) {
            $.plusReady(function() {
                mui.plusReady(function(){plus.nativeUI.closeWaiting();}) 
            });
        } 
        var mask=document.getElementsByClassName("mui-show-loading-mask");
        var toast=document.getElementsByClassName("mui-show-loading");
        if(mask.length>0){
            mask[0].classList.add("mui-show-loading-mask-hidden");
        }
        if(toast.length>0){
            toast[0].classList.remove("loading-visible");
            callback && callback();
        }
      }
})(mui, window);

 $(window).scroll(function() {
			    var $_scrollTop = document.documentElement.scrollTop || window.pageYOffset || document.body.scrollTop;  
			    if($_scrollTop>100){
				    $("#back_top").fadeIn();
				}else{
				    $("#back_top").fadeOut();
			    }
		
		})
    $("#back_top").click(function(){
			$('html,body').animate({
				scrollTop:'0px'
			},500)
		})

var shop = window.location.search.split('=')[1].split('&');
console.log(shop);
var goods_id = shop[0];
var acti_id = shop[1];
var login_type;
	login_type = localStorage.getItem('login_type');
var	user_id = localStorage.getItem('user_id');
var	token = localStorage.getItem('token');
if(acti_id) {
	acti_id = acti_id
} else {
	acti_id = '';
}
console.log(goods_id);
console.log(acti_id);
	





//分享
function plusReady() {
	tuijian = localStorage.getItem('tuijian')
	phone_tjr = localStorage.getItem('user_name')

	function updateSerivces() {
		plus.share.getServices(function(s) {
			shares = {};
			for(var i in s) {
				var t = s[i];
				shares[t.id] = t;
			}
		}, function(e) {
			console.log('获取分享服务列表失败：' + e.message);
		});
	}

	updateSerivces();
	//	var img_pic;
	var type;
	var share_weixin_2 = ca.className("weixin");
	ca.click(share_weixin_2[0], function() {
		console.log(33333333);
		//img_pic=$('#fenxiang_pic').attr('src');
		var share = shares["weixin"];
		type='weixin';
		if(share.authenticated) {
			shareMessage_1(share, "WXSceneSession");
		} else {
			share.authorize(function() {
				shareMessage_1(share, "WXSceneSession");
			}, function(e) {
				console.log("认证授权失败：" + e.code + " - " + e.message);
			});
		}
	});

	$('.qq_fenxiang').click(function() {
		console.log(32222222);
		//img_pic=$('#fenxiang_pic').attr('src');
		var share = shares["qq"];
		type='qq';
		if(share.authenticated) {
			shareMessage_1(share, 'qq');
		} else {
			share.authorize(function() {
				shareMessage_1(share, 'qq');
			}, function(e) {
				console.log("认证授权失败：" + e.code + " - " + e.message);
			});
		}
	});

		$('.penyouquan').click(function(){
			console.log(32222222);
			//img_pic=$('#fenxiang_pic').attr('src');
			var share = shares["weixin"];	
			type='weixin';
			if (share.authenticated) {
				shareMessage_1(share,'WXSceneTimeline');
			} else {
				share.authorize(function() {
					shareMessage_1(share,'WXSceneTimeline');
				}, function(e) {
					console.log("认证授权失败：" + e.code + " - " + e.message);
				});
			}		  
		});  

	function shareMessage_1(share, ex) {
		//var tuijian=localStorage.getItem('tuijian');
		var msg = {
			extra: {
				scene: ex
			}
		};
		msg.href = window.location.origin + "/erweima/xianshang/goods-details.html?goods_id=" + goods_id+'&'+acti_id+'&'+type;
		msg.title = $('.text_overflow').html().split('</em>')[1];
		msg.content ='¥'+ $('#top').html().split('</em>')[1]+' | '+$('.text_overflow').html().split('</em>')[1];
		msg.thumbs = [$('#tops img').attr('src')];
		//msg.pictures=img_pic;
		console.log(msg.content);
		console.log(msg.thumbs);
		console.log(msg.href);
		//console.log(msg.pictures);
		share.send(msg, function() {
			$('.zhezhao-fenxiang').css('display', 'none');
			console.log("分享到\"" + share.description + "\"成功！ ");

		}, function(e) {
			console.log("分享到\"" + share.description + "\"失败: " + e.code + " - " + e.message);
		});
	}
}
if(window.plus) {
	plusReady();
} else {
	document.addEventListener('plusready', plusReady, false);
}

//生成相册图片
function xiangce_pic(){
	var obj = {};
	obj.goods_id = goods_id;
	obj.acti_id = acti_id;
	obj.is_seller=login_type;
	console.log(obj)
	ca.post({
		url: hetao.url + 'goods/goodsShareImg',
		data: obj,
		succFn: function(data) {
			var res = JSON.parse(data);
			console.log('生成相册图片');
			console.log(res);
			if(res.status == 1) {
				$('.share1 img').attr('src', hetao.url2 + res.data.img);
				$('.share1 img').attr('img', res.data.img_64);
			}
		}
	})
}
xiangce_pic();
//点击保存相册
$('.share1 button').click(function() {
	plusReady1();
})
var plusReady1 = function() {
	var src = $('.share1 img').attr("src") //base64字符串
	// 创建下载任务
	picurl = src;
	var dtask = plus.downloader.createDownload(picurl, {}, function(d, status) {
		// 下载完成
		if(status == 200) { 
	//			alert("Download success: " + d.filename);
			plus.gallery.save(picurl, function() {
				mui.toast('保存成功');
			}, function() {
				mui.toast('保存失败，请重试！');
			});
		} else {
			mui.toast('保存失败，请重试！'); 
	//			alert("Download failed: " + status); 
		}
	});
	dtask.start();
};

//file:///storage/emulated/0/DCIM/Camera/project_barcode.jpg

//复制链接
$('.shop_lian').click(function() {
	mui.plusReady(function() {
		//复制链接到剪切板
		var copy_content = window.location.origin + "/erweima/xianshang/goods-details.html?goods_id=" + goods_id+'&'+acti_id;
		console.log(copy_content);
		//判断是安卓还是ios
		if(mui.os.ios) {
			//ios
			var UIPasteboard = plus.ios.importClass("UIPasteboard");
			var generalPasteboard = UIPasteboard.generalPasteboard();
			//设置/获取文本内容:
			generalPasteboard.plusCallMethod({
				setValue: copy_content,
				forPasteboardType: "public.utf8-plain-text"
			});
			generalPasteboard.plusCallMethod({
				valueForPasteboardType: "public.utf8-plain-text"
			});
			mui.toast("邀请链接地址复制成功！");
		} else {
			//安卓
			var context = plus.android.importClass("android.content.Context");
			var main = plus.android.runtimeMainActivity();
			var clip = main.getSystemService(context.CLIPBOARD_SERVICE);
			plus.android.invoke(clip, "setText", copy_content);
			mui.toast("邀请链接地址复制成功！");
		}
	});
});
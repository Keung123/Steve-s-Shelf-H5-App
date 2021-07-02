/**
 * 新版分享
 * Author liuhuan 
 * */
mui.plusReady(function () {
	const _uid = localStorage.getItem('userId');
	const _token = localStorage.getItem('token');
	const shop = window.location.search.split('=')[1].split('&');
	const goods_id = shop[0];
	const acti_id = shop[1];
	const tuijian = localStorage.getItem('tuijian')
	const phone_tjr = localStorage.getItem('user_name')

	let yaoqingma;
	let shares = {};
	let type = 'weixin';
	plus.share.getServices(function (s) {
		for (var i in s) {
			var t = s[i];
			shares[t.id] = t;
		}
	}, function (e) {
		console.log('获取分享服务列表失败：' + e.message);
	});

	yaoqing(_uid, _token);
	shareImage();

	//微信好友分享
	$('.weixin_share').on('click', function () {
		const share = shares["weixin"];
		type = 'weixin';
		if (share.authenticated) {
			shareMessage(share, "WXSceneSession");
		} else {
			share.authorize(function () {
				shareMessage(share, "WXSceneSession");
			}, function (e) {
				console.log("认证授权失败：" + e.code + " - " + e.message);
			});
		}
	})
	//朋友圈分享
	$('.penyouquan_share').on('click', function () {
		const share = shares["weixin"];
		type = 'weixin';
		if (share.authenticated) {
			shareMessage(share, 'WXSceneTimeline');
		} else {
			share.authorize(function () {
				shareMessage(share, 'WXSceneTimeline');
			}, function (e) {
				console.log("认证授权失败：" + e.code + " - " + e.message);
			});
		}
	})
	//QQ分享
	$('.qq_share').on('click', function () {
		const share = shares["qq"];
		type = 'qq';
		if (share.authenticated) {
			shareMessage(share);
		} else {
			share.authorize(function () {
				shareMessage(share);
			}, function (e) {
				console.log("认证授权失败：" + e.code + " - " + e.message);
			});
		}
	})
	//微博分享
	$('.weibo_share').on('click', function (e) {
		let share = shares["sinaweibo"];
		type = 'sinaweibo';
		if (share.authenticated) {
			shareMessage(share);
		} else {
			share.authorize(function () {
				shareMessage(share);
			}, function (e) {
				console.log("认证授权失败：" + e.code + " - " + e.message);
			});
		}
	})
	//图片分享
	$('.image_share').on('tap', function () {
		$('.share1').show();
		$('.zhedang').css('display', 'none');
	})
	//连接分享
	$('.link_share').on('tap', function () {
		//复制链接到剪切板
		const copy_content = api + "Fx/goodsDetail?goodsid=" + goods_id + '&acti_id=' + acti_id + '&type=' + 'weixin' + '&yaoqingma=' + yaoqingma;
		//判断是安卓还是ios
		if (mui.os.ios) {
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
		$('.zhedang').css('display', 'none');
	})
	//保存图片
	$('.share1 button').click(function () {
		mui.showLoading();
		var src = $('.share1 img').attr("img");
		var dtask = plus.downloader.createDownload(src, {}, function (d, status) {
			if (status == 200) {
				plus.gallery.save(src, function () {
					mui.hideLoading();
					mui.toast('保存成功');
					$('.share1').hide();
				}, function (res) {
					mui.toast('保存失败');
					mui.hideLoading();
				})
			} else {
				mui.toast('保存失败，请重试！');
				mui.hideLoading();
			}
		});
		dtask.start();
	})
	//取消分享
	$('.quxiao').on('tap', function () {
		$('.zhedang').css('display', 'none');
	})
	//分享详细信息
	function shareMessage(share, ex) {
		var msg = {
			extra: {
				scene: ex
			}
		};
		if (msg.extra.scene == "WXSceneSession" || msg.extra.scene == "WXSceneTimeline") {
			msg.type = "web";
		}
		msg.href = api + "Fx/goodsDetail?goodsid=" + goods_id + '&acti_id=' + acti_id + '&type=' + type + '&yaoqingma=' + yaoqingma;
		msg.title = $('#detail_title').html().split('</em>')[1] || $('#detail_title').html();
		msg.content = '¥' + $('#top').html().split('</em>')[1];
		msg.thumbs = [$('#tops img').attr('src') + '?imageView2/1/w/200/h/200'];
		share.send(msg, function () {
			$('.zhezhao-fenxiang').css('display', 'none');
		}, function (e) {
			console.log("分享到\"" + share.description + "\"失败: " + e.code + " - " + e.message);
		});
	}
	//获取邀请码
	function yaoqing(uid, token) {
		mui.post(api + 'yinzi/getMycode', { uid, token }, function (data) {
			if (data.status == 1) {
				yaoqingma = data.data.invite_code;
			}
		}, 'json');
	}
	//生成商品分享图
	function shareImage() {
		mui.post(api + 'goods/goodsShare', { goodsId: goods_id, activeId: acti_id }, function (data) {
			if (data.status == 1) {
				$('.share1 img').attr('src', data.data.img_64);
				$('.share1 img').attr('img', data.data.img);
			}
		}, 'json');
	}
})
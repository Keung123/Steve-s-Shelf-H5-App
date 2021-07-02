	mui.init({
		beforeback: function () {
			plus.webview.currentWebview().close();
		}
	});
	var user_id = localStorage.getItem('userId');
	var token = localStorage.getItem('token');
	var login_type = localStorage.getItem('login_type');
	var goods_id = window.location.search.split('=')[1].split('&')[0];
	$(".mui-slider").css("height", $(".mui-slider").width());
	//规格弹出属性的切换
	$('.center_top').on('tap', 'p', function () {
		var index = $('.center .ys').attr('spec_value_id');
		$(this).addClass('on').siblings().removeClass('on');
	});
	/*切换*/
	$('.header-tab').on('tap', 'a', function () {
//		var index = $(this).index();
//		$(this).find('span').addClass('active');
//		$(this).siblings().find('span').removeClass('active');
//		$('.mui-content .tab-box').eq(index).show().siblings().hide();
		$(this).addClass('active').siblings().removeClass('active');
	});
	//图片轮播
	var gallery = mui('.mui-slider');
	gallery.slider({
		interval: 5000 //自动轮播周期，若为0则不自动播放，默认为0；
	});
	document.querySelector('.mui-slider').addEventListener('slide', function (event) {
		$(".shuzi em").html(event.detail.slideNumber + 1);
	});
	/*规格*/
	$('.guige-list').on('tap', function () {
		$('.max-box').show();
	});
	//加入购物车
	$(".min-box").on('tap', function () {
		$(".max-box").hide();
	})
	$('.add-car,.buy').on('tap', function (e) {
		$('.max-box').show();
	});
	$("#del").on('tap', function () {
		$(".max-box").hide();
	})
	//优惠券
	$('.lingquan').on('tap', function () {
		$('.yhq-box').show();
	})
	$('.yhq-shadow').on('tap', function () {
		$('.yhq-box').hide();
	})
	$("#yhq-del").on('tap', function () {
		$('.yhq-box').hide();
	})
	//商品详情顶部切换
	$('.xiangqing_top span').click(function () {
		var index = $(this).index();
		$(this).addClass('active').siblings().removeClass('active');
		if (index == 0) {
			$('.shop_jieshao').css('display', 'block');
			$('.xq_guige').css('display', 'none');
			$('.need').css('display', 'none');
			$('.xiangqing_top span:nth-child(1)').css('border-right', '1px solid #D31A1A');
			$('.xiangqing_top span:nth-child(3)').css('border-left', '1px solid #999');
		} else if (index == 1) {
			$('.xq_guige').css('display', 'block');
			$('.shop_jieshao').css('display', 'none');
			$('.need').css('display', 'none');
			$('.xiangqing_top span:nth-child(1)').css('border-right', 'none');
			$('.xiangqing_top span:nth-child(3)').css('border-left', 'none');
		} else {
			$('.need').css('display', 'block');
			$('.shop_jieshao').css('display', 'none');
			$('.xq_guige').css('display', 'none');
			$('.xiangqing_top span:nth-child(1)').css('border-right', '1px solid #999');
			$('.xiangqing_top span:nth-child(3)').css('border-left', '1px solid #D31A1A');
		}
	});
	/*领券*/
	$('.yhq-list').on('tap', 'li', function () {
		var coupon_id = $(this).attr('coupon_id');
		var coupon_buy_price = $(this).attr('coupon_buy_price');
		if (!user_id) {
			mui.toast('请先登录');
			return;
		}
		if (coupon_buy_price == 0) {
			var obj = {};
			obj.token = token;
			obj.uid = user_id;
			obj.couponId = coupon_id;
			$.ajax({
				type: 'GET',
				url: api + 'discount/getCoupon',
				data: obj,
				success: function (res) {
					if (res.status == 1) {
						$('.get-success').show();
						setTimeout(function () {
							$('.get-success').hide();
						}, 1000)
					} else {
						mui.toast(res.msg)
					}
				},error:function(err){
					console.log(JSON.stringify(err))
				}
			});
		} else {
			mui.openWindow({
				url: '../my/order/submit_yhq.html?coupon_id=' + coupon_id + '&' + coupon_buy_price,
				id: 'my/order/submit_yhq.html',
				waiting: {
					autoShow: false
				}
			})
		}
	})
	
	/*分享*/
	$('.iconfenxiang').on('tap', function () {
		var user_id = localStorage.getItem('userId');
		if (!user_id) {
			mui.toast('请先登录');
			mui.openWindow({
				url: "/login/login.html",
				id: "/login/login.html",
				waiting: {
					autoShow: false, //自动显示等待框，默认为true
				}
			})
			return false;
		}
		$('.zhedang').css('display', 'block');
	})
	//打开购物车页面
	/*点击评论跳转*/
	$('.comment-list').on('tap', function () {
		mui.openWindow({
			url: './comment/comment.html?goods_id=' + goods_id,
			id: './comment/comment.html',
			waiting: {
				autoShow: false
			}
		})
	});
	/*查看全部*/
	$('.all-comment').on('tap', function () {
		// var user_id = localStorage.getItem('userId');
		// if (!user_id) {
		// 	mui.openWindow({
		// 		url: "/login/login.html",
		// 		id: "/login/login.html",
		// 		waiting: {
		// 			autoShow: false, //自动显示等待框，默认为true
		// 		}, show: {
		// 			autoShow: true,//页面loaded事件发生后自动显示，默认为true
		// 			aniShow: 'zoom-fade-out'
		// 		},
		// 	})
		// 	return false;
		// }
		mui.openWindow({
			url: './comment/comment.html?goods_id=' + goods_id,
			id: './comment/comment.html',
			waiting: {
				autoShow: false, //自动显示等待框，默认为true
			}
		})
	});
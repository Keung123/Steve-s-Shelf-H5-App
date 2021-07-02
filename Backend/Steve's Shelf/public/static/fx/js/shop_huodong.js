/*商品详情*/
function close() {
	mui.plusReady(function () {
		plus.webview.currentWebview().hide();
		plus.webview.currentWebview().close();
	})
}
mui.showLoading('加载中', 'div');
var user_id = localStorage.getItem('userId');
var token = localStorage.getItem('token');
var login_type = '';
var goods_id = '';
var active_type_id = '';
var index1, index2, index3;
var is_favor; //是否收藏
var is_shangjia; //是否上架
var client_id = localStorage.getItem('client_id');
//商品
getGuize();
lunbo_xx();
var prom_id;//活动商品下的不同id
var stock; // 库存
var guize1;
var guize2;
var guize3;
var a = "{$goodsid}";
alert(a);
function lunbo_xx() {


	localStorage.removeItem('coupon_id');
	localStorage.removeItem('coupon_money');
	var obj = {};
	if (!user_id) {
		user_id = '';
		token = '';
	} else {
		user_id = user_id;
		token = token;
	}
	obj.uid = user_id;
	obj.token = token;
	obj.goodsId = "{$goods_id}";
	if (active_type_id) {
		obj.activeId = active_type_id;
	}
	$.ajax({
		type: 'GET',
		url: api + 'goods/goodsDetails',
		data: obj,
		success: function (res) {
			mui.hideLoading();
			goods_id = res.data.goods_id;
			var str = '';
			var pic = '';
			if (res.status == 1) {
				$('.no_data').css('display', 'none');
				$('.mui-slider-group').css('display', 'block');
				prom_id = res.data.prom_id;
				is_favor = res.data.is_favor;
				is_shangjia = res.data.is_shangjia;
				
				$('#exchange_integral').html('购买可得'+ res.data.exchange_integral +'积分')
				$('#yunfei').html('满'+res.data.standard+'元起送')
				var top_img = res.data.images
				var html = '';
				var point;
				//商品图片轮播渲染
				html += '<div class="mui-slider-item mui-slider-item-duplicate">' +
					'<a>' +
					'<img src="' + top_img[top_img.length - 1] + '"/>' +
					'</a>' +
					'</div>'
				for (var i in top_img) {
					html += '<div class="mui-slider-item">' +
						'<a>' +
						'<img src="' + top_img[i] + '" category_index="category_' + i + '""/>' +
						'</a>' +
						'</div>'
				}
				html += '<div class="mui-slider-item mui-slider-item-duplicate">' +
					'<a>' +
					'<img src="' + top_img[0] + '"/>' +
					'</a>' +
					'</div>'
				$('.mui-slider-loop').html(html);
				$('.shuzi i').html(top_img.length);
				var gallery = mui('.mui-slider');
				gallery.slider({
					interval: 1500 //自动轮播周期，若为0则不自动播放，默认为0；			
				})
				var price_yuan;
				var huodong_price;
				var show_pice;
				var dz_vip;
				var vip_dz_price;
				var show_price_zhanshi;
				var show_zhanshi;
				if (login_type == 1) {
					dz_vip = '赚';
					vip_dz_price = res.data.dianzhu_price;
					if (vip_dz_price == 0) {
						show_zhanshi = 'none';
						// $('.fenxiang').html('<h4>分享到</h4>');
					} else {
						show_zhanshi = 'inline-block';
						$('.share_zhuan').css('display', 'block');
						$('.share_zhuan .zhuan_money span').html(vip_dz_price);
						$('.share_zhuan i').html(vip_dz_price);
					}
				} else {
					show_zhanshi = 'none';
					// $('.fenxiang').prepend('<h4>分享到</h4>');
				}

				//商品信息
				var menu_xs;
				var menu_xs1;
				if (res.data.abstract_content) {
					menu_xs = 'block';
					menu_xs1 = 'none';
				} else {
					menu_xs = 'none !important';
					menu_xs1 = 'inline-block';
				}
				// console.log(JSON.stringify(res))
				str += 
				// '<p class="text_overflow" style="display:' + menu_xs + '"><em>' + res.data.active_name + '</em>' + res.data.abstract_content + '</p>' +
				// <em style="display:' + menu_xs1 + '">' + res.data.active_name + '</em>
					'<p class="text_overflow shop_ti"><span id="detail_title" style="font-size: 15px;">' + res.data.goods_name + '</span></p>' +
					// '<i>' + res.data.introduction + '</i>' +
					'<div class="price">' +
					'<span>¥' + res.data.price + '</span>' +
					'<del class="vip">¥' + res.data.show_price + '</del>' +
					'<div class="number xiaol">销量：' + res.data.volume + '</div>' +
					// '<div class="number f_right">库存：' + res.data.stock + '</div>' +
					'<div class="guanzhu" id="guanzhu"><i class="iconfont iconshoucang1"></i>关注</div>'+
					'</div>'
				$('.title').html(str);
				if (is_favor == 0) {
					$('.guanzhu').removeClass('act')
				} else {
					$('.guanzhu').addClass('act')
				}
				stock = res.data.stock;
				if (stock <= 0) {
					$('.add-car').css('display', 'none');
					$('.buy').css('display', 'none');
					$('.shangjia').css('display', 'none');
					$('.none').css('display', 'block');
					$('.guige-list').css('pointer-events', 'none');
				}
				//规格 商品照片 单价 库存
				$('#imgs').attr('data-src', res.data.picture);
				$('#top').html('<em>¥</em>' + res.data.price);
				$('#top').attr('data-qian', res.data.price);
				$('#ku1').html('库存' + res.data.stock + '件');
				var nbox = mui('.mui-numbox').numbox();  
				nbox.options["max"]=res.data.stock;
				//商品评论
				var comment_list = res.data.comment_list;
				if (comment_list.length == 0) {
					$('.comment ul.mui-table-view').html('<div class="zanwu">暂无评价</div>')
				} else{
					$('.comment_total').html('商品评论(' + res.data.comment_total + ')');
					 var pinglun = '';
					 var user_avat;
					 for (var i=0;i<1;i++) {
					 	if (comment_list[0].user_avat.indexOf('http') != -1) {
					 		user_avat = comment_list[0].user_avat;
					 	} else if (comment_list[0].user_avat) {
					 		user_avat = imgUrl + comment_list[0].user_avat;
					 	} else {
					 		user_avat = '../img/zhutu.jpg';
					 	}
					 	var xingxin = '';
						for(var k = 0; k < 5; k++){
							console.log(JSON.stringify(comment_list[0]));
							if(k < comment_list[0].or_scores){
								xingxin += '<span data-index="'+k+'" style="color: #ffad3e;" class="iconfont iconshoucang-copy-copy act"></span>';
							}else{
								xingxin += '<span data-index="'+k+'" class="iconfont iconshoucang-copy-copy"></span>';
							}
						}
					 	pinglun += '<li class="mui-table-view-cell">'
					 		+ '<a class="">'
					 		+ '<div class="photo">'
					 		+ '<img src="' + user_avat + '" />'
					 		+ '<span>' + comment_list[0].user_name + '</span>'
					 		+'<div class="box">'+ xingxin +'</div>'
					 		+ '<i class="f_right">' + comment_list[0].or_add_time + '</i>'
					 		+ '</div>'
					 		+ '<div class="text">'
					 		+ '<p class="text_line">' + comment_list[0].or_cont + '</p>'
					 	pinglun += '<ul class="display-flex">'
					 	for (var j in comment_list[0].or_thumb) {
					 		pinglun += '<li class="flex">'
					 		pinglun += '<img src="'+ imgUrl+ comment_list[0].or_thumb[j] + '" data-preview-src="' + comment_list[0].or_thumb[j] + '" data-preview-group="1" alt="" />'
					 		pinglun += '</li>'
					 	}
					 	pinglun += '</ul>'
					 		+ '</div>'
					 		+ '</a>'
					 	pinglun += '</li>'
					 }
					 $('.comment ul.mui-table-view').html(pinglun);
				}
				//详情  购买须知
				var need_rule = res.data.need_rule;
				$('.need').html(need_rule);
				//相关推荐
				var recomm = res.data.recomm;
				var shop_tuijain = '';
				var dz; //判断店主、vip的显示隐藏
				var vip;
				var zhuan;
				if (login_type == 1) {
					dz = 'block';
					vip = 'none';
					$('.shangjia').css('display', 'block');
				} else {
					dz = 'none';
					vip = 'block';
					$('.shangjia').css('display', 'none');
				}
				shop_xiangq();
			} else {
				mui.toast(res.msg);
				setTimeout(function () {
					mui.back();
				}, 500)
			}
		}
	})
}
//商品详情详情
function shop_xiangq() {
	var obj = {};
	obj.goodsId = goods_id;
	$.ajax({
		url: api + 'goods/goodsInfo',
		data: obj,
		success: function (data) {
			// console.log(JSON.stringify(data))
			var res = data;
			var imgs = '';
			var description = res.data.intro;
			var content = res.data.content;
			var spec = res.data.spec;
			var str = '';
			if (res.status == 1) {
				//满赠
				$('#manzeng').html(res.data.full_give[0]);
				var html = '';
				for (var i in res.data.full_give) {
					html += '<li>'+ res.data.full_give[i] +'</li>'
				}
				$('#yhqList').html(html)
				
				
				//规格参数
				if (spec.subject) {
					str += '<tr>'
						+ '<td width="40%">商品编号</td>'
						+ '<td>' + spec.code + '</td>'
					str += '</tr>'
					str += '<tr>'
						+ '<td colspan="2">产品参数</td>'
					str += '</tr>'
					for (var i in spec.subject.key) {
						str += '<tr>'
							+ '<td>' + spec.subject.key[i] + '</td>'
							+ '<td>' + spec.subject.val[i] + '</td>'
						str += '</tr>'
					}
					$('.guige table').html(str);
				} else {
					$('.guige table').html('<p class="shuju_wu_xx">没有规格参数</p>');
				}
				//商品介绍
				for (var i in description) {
					imgs += '<li>' +
						'    <img src="' + description[i] + '" style="margin-bottom:0px;">' +
						'</li>';
				}
				$('.shop_jieshao').html(imgs);
				//购买须知
				$('.need').html(content.content);
			}
		}
	})
}
shop_xiangq();
//规格属性
function getGuize() {
	var obj = {};
	obj.goodsId = goods_id;
	$.ajax({
		url: api + 'goods/goodsRule',
		data: obj,
		success: function (res) {
			var data = res.data;
			var str = '';
			var yangshi; //属性的样式
			var shuxing; //属性的样式
			if (res.status == 1) {
				for (var i in data) {
					if (i == 0) {
						shuxing = 'gg'
					} else if (i == 1) {
						shuxing = 'zhongjian'
					} else {
						shuxing = 'ys'
					}
					str += '<li class="types">' +
						'<span>' + data[i].spec_name + '</span>'
					str + '</li>'
					str += '<li class="center_top">'
					for (var j in data[i].values) {
						if (j == 0) {
							yangshi = 'on';
						} else {
							yangshi = '';
						}
						str += '<p class="' + yangshi + ' ' + shuxing + '"  shuxing="' + shuxing + '" spec_value_id="' + data[i].values[j].spec_value_id + '">' + data[i].values[j].spec_value_name + '</p>'
					}
					str += '</li>'
				}
				$('.center ul').html(str);
				guize1 = $('.center .gg.on').attr('spec_value_id');
				guize2 = $('.center .zhongjian.on').attr('spec_value_id');
				guize3 = $('.center .ys.on').attr('spec_value_id');
				jiage();
			}
		}
	})
}
//规格属性样式的切换
$('.center').on('tap', 'p', function () {
	var spec_value_id = $(this).attr('spec_value_id');
	var shuxing = $(this).attr('shuxing');
	if (shuxing == 'gg') {
		guize1 = spec_value_id;
		if (!guize2) {
			var index = $('.center .zhongjian.on').attr('spec_value_id');
			guize2 = index;
		}
		if (!guize3) {
			var index = $('.center .ys.on').attr('spec_value_id');
			guize3 = index;
		}
	} else if (shuxing == 'zhongjian') {
		if (!guize1) {
			var index = $('.center .gg.on').attr('spec_value_id');
			guize1 = index;
		}
		if (!guize3) {
			var index = $('.center .ys.on').attr('spec_value_id');
			guize3 = index;
		}
		guize2 = spec_value_id;
	} else {
		if (!guize1) {
			var index = $('.center .gg.on').attr('spec_value_id');
			guize1 = index;
		}
		if (!guize2) {
			var index = $('.center .zhongjian.on').attr('spec_value_id');
			guize2 = index;
		}
		guize3 = spec_value_id;
	}
	jiage();
	$(this).addClass('on').siblings().removeClass('on');
})
var sku_id;
//规格价格
function jiage() {
	index1 = $('.center .gg').attr('spec_value_id');
	index2 = $('.center .zhongjian').attr('spec_value_id');
	index3 = $('.center .ys').attr('spec_value_id');
	if (!guize1) {
		if (index1) {
			guize1 = index1;
		} else {
			guize1 = '';
		}
	} else {
		guize1 = guize1;
	}
	if (!guize2) {
		if (index2) {
			guize2 = index2;
		} else {
			guize2 = '';
		}
	} else {
		guize2 = guize2;
	}
	if (!guize3) {
		if (index3) {
			guize3 = index3;
		} else {
			guize3 = '';
		}
	} else {
		guize3 = guize3;
	}
	var obj = {};
	obj.goodsId = goods_id;
	obj.rule1 = guize1;
	obj.rule2 = guize2;
	obj.rule3 = guize3;
	$.ajax({
		url: api + 'goods/goodsPrice',
		data: obj,
		success: function (data) {
			var res = data;
			if (res.status == 1) {
				var nbox = mui('.mui-numbox').numbox();  
				nbox.options["max"]=res.data.stock;
				sku_id = res.data.sku_id;
				if (res.data.image) {
					$('#imgs').attr('src', res.data.image);
				}
				$('.guige-list p').html(res.data.sku_name)
				$('#top').html('<em>¥</em>' + res.data.price);
				$('#ku1').html('库存' + res.data.stock + '件');
				$('.title .price del').html('¥' + res.data.show_price);
				$('#user_num').val(1);
				$('.mui-btn').attr('disabled',false);
			}
		}
	})
}

//关注商品
$('body').on('tap','#guanzhu',function () {
	var _uid = localStorage.getItem('userId');
	var _token = localStorage.getItem('token');
	if (!_uid) {
		mui.toast('您还没有登录');
		mui.openWindow({
			url: '/login/login.html',
			id: '/login/login.html',
			waiting: {
				autoShow: false
			}
		})
		return;
	}
	var obj = {};
	obj.token = _token;
	obj.uid = _uid;
	obj.goodsId = goods_id;
	if (is_favor == 0) {
		$.ajax({
			url: api + 'goods/goodsFavor',
			data: obj,
			success: function (res) {
				var data = res.data;
				var str = '';
				if (res.status == 1) {
					mui.toast('商品关注成功');
					noticeShoucang()
					$('.guanzhu').addClass('act')
					lunbo_xx();
				}
			}
		})
	} else if (is_favor == 1) {
		$.ajax({
			url: api + 'goods/goodsFavor',
			data: obj,
			success: function (res) {
				var data = res.data;
				var str = '';
				if (res.status == 1) {
					mui.toast('商品取消关注成功');
					noticeShoucang()
					$('.guanzhu').removeClass('act')
					lunbo_xx();
				}
			}
		})
	}
})
// 通知我的关注
function noticeShoucang() {
	mui.plusReady(function () {
		var curView = plus.webview.getWebviewById('./shoucang/shoucang.html');
		mui.fire(curView, 'refreshShoucang');
	})
}
//加入购物车
$('#gouwuche').on('tap', function () {
	// var client_id = localStorage.getItem('client_id');
	var num = $('.mui-input-numbox').val();
	var _uid = localStorage.getItem('userId');
	var _token = localStorage.getItem('token');
	if (!_uid) {
		mui.toast('您还没有登录');
		mui.openWindow({
			url: '/login/login.html',
			id: '/login/login.html',
			waiting: {
				autoShow: false
			}
		})
		return;
	}
	var obj = {};
	if (!active_type_id) {
		active_id = '';
	} else {
		active_type_id = active_type_id;
	}
	obj.token = _token;
	obj.uid = _uid;
	obj.goodsId = goods_id;
	obj.activeId = active_type_id;
	obj.num = num;
	obj.ruleId = sku_id;
	$(this).addClass('no_click')
	$.ajax({
		url: api + 'cart/cartAdd',
		data: obj,
		success: function (data) {
			$(this).removeClass('no_click')
			var res = data;
			if (res.status == 1) {
				mui.toast('加入购物车成功');
				mui.plusReady(function () {
					plus.webview.getWebviewById('./car/car.html').evalJS('add_cart2()')
				})
				setTimeout(function () {
					$('.max-box').css('display', 'none');
				}, 300)
			} else {
				mui.toast(res.msg);
				$(".max-box").hide();
			}
		}, errFn: function (err) {
			$(this).removeClass('no_click')
			console.log(S(err))
		}
	})
})
//立即购买
$('#buy').on('tap', function () {
	mui.showLoading('加载中','div')
	var guige = $('.center p.on').html();
	var _uid = localStorage.getItem('userId');
	var _token = localStorage.getItem('token');
	if (!_uid) {
		mui.openWindow({
			url: "/login/login.html",
			id: "/login/login.html",
			waiting: {
				autoShow: false, //自动显示等待框，默认为true
			}
		})
		return false;
	}
	var goods = [];
	var goods_pic = '';
	var goods_name = '';
	var _goodsNum = '';
	var goods_price = '';
	var _goodsId = '';
	var good = {};
	good._goodsId = goods_id;
	good.goods_pic = $("#imgs").attr('data-src');
	good._skuId = sku_id;
	good._goodsNum = $('#user_num').val();
	good.goods_name = $('#detail_title').html();
	good.goods_price = $('#top').attr('data-qian');
	good.goods_guige = guige;
	goods.push(good);
	$.ajax({
		type: 'GET',
		url: api + 'goods/buyNow',
		data: {
			token: _token,
			uid: _uid,
			goodsId: goods_id,
			ruleId: sku_id
		},
		success: function (res) {
			mui.hideLoading()
			mui.openWindow({
				url: '/my/order/submit_order.html',
				id: '/my/order/submit_order.html',
				extras: {//extras里面的就是参数了
					goods: goods,
					submitType: 'detail'
				},
				waiting: {
					autoShow: false
				}
			});
			mui.plusReady(function () {
				plus.webview.getWebviewById('./car/car.html').evalJS('add_cart2()');
			})
		},
		error: function (err) {
			mui.hideLoading()
			console.log(JSON.stringify(err));
		}
	})
})

$('.open_car').on('tap',function(){
	mui.plusReady(function () {
		mui.fire( plus.webview.getLaunchWebview(), 'orderlist',{id:3});
		var launch = plus.webview.getLaunchWebview();
		var wvs = plus.webview.all();
		for (var i = 0,len = wvs.length; i < len; i++) {
			if (wvs[i].id === launch.id || wvs[i].id === './index/index.html' ||wvs[i].id === './category/category.html' ||wvs[i].id === './car/car.html' ||wvs[i].id === './my/my.html' ||wvs[i].id === './changyong/changyong.html') {
				continue;
			} else {
			//关闭中间的窗口对象，为防止闪屏，不使用动画效果;
			  wvs[i].close('none');
			}　　
		}
	})
})
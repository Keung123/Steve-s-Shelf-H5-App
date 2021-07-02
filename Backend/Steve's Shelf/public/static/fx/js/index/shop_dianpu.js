	/*商品详情*/
	var user_id = localStorage.getItem('user_id');
	var token = localStorage.getItem('token');
	var login_type;
	login_type = localStorage.getItem('login_type');
	var goods_id;
	goods_id= window.location.search.split('=')[1].split('&')[0];

	var s_id = window.location.search.split('=')[1].split('&')[1];
	console.log(goods_id);
	console.log(s_id);
	var is_favor; //是否收藏
	var is_shangjia;//是否上架
	ca.receiveNotice('qxshoucang', function() {
		lunbo_xx();
		shop_xiangq();
		shop_sucai();		
	})

	
	
	
	
	
	
	
	//商品
	lunbo_xx();
	function lunbo_xx() {
		localStorage.removeItem('coupon_id');
		localStorage.removeItem('coupon_money');
		var obj = {};
		if(!user_id) {
			user_id = '';
			token = '';
		} else {
			user_id = user_id;
			token = token;
		}
		obj.uid = user_id;
		obj.token = token;
		obj.goodsid = goods_id;
		console.log(obj);
		ca.get({
			url: hetao.url + 'goods/goodsDetail',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
				console.log(res);
				var str = '';
				var pic = '';
				if(res.status == 1) {
					var arr=['personal/my_footprint.html'];
					ca.sendNotice(arr,'zuji',{});
					
					
					is_favor = res.data.is_favor;
					is_shangjia = res.data.is_shangjia;
					console.log(is_favor);
					console.log(is_shangjia);
					if(is_favor == 0) {
						$('.collects icon').css('color', '#333');
						$('.collects i').css('color', '#333');
						$('.collects icon').attr('class','iconfont icon-shoucang2');	
					} else {
						$('.collects icon').css('color', '#d31a1a');
						$('.collects i').css('color', '#d31a1a');
						$('.collects icon').attr('class','iconfont icon-shoucang3');		
					}
					
					if(is_shangjia == 0) {
						console.log('上架')
						$('.shangjia icon').attr('class','iconfont icon-shangjia1');		
					} else {
						console.log('下架')
						$('.shangjia icon').attr('class','iconfont icon-xiajia1');
					}
					var top_img = res.data.images
					//console.log(top_img[0]);	
					var html = '';
					var point;
					//商品图片轮播渲染
					html += '<div class="mui-slider-item mui-slider-item-duplicate">' +
						'<a>' +
						'<img src="' + hetao.url2 + '' + top_img[top_img.length - 1] + '"/>' +
						'</a>' +
						'</div>'
					for(var i in top_img) {
						html += '<div class="mui-slider-item">' +
							'<a>' +
							'<img src="' + hetao.url2 + '' + top_img[i] + '" category_index="category_' + i + '""/>' +
							'</a>' +
							'</div>'
					}
					html += '<div class="mui-slider-item mui-slider-item-duplicate">' +
						'<a>' +
						'<img src="' + hetao.url2 + '' + top_img[0] + '"/>' +
						'</a>' +
						'</div>'
					$('.mui-slider-loop').html(html)
					$('.shuzi i').html(top_img.length);
					var gallery = mui('.mui-slider');
					gallery.slider({
						interval: 1500 //自动轮播周期，若为0则不自动播放，默认为0；			
					})
					
					
					//------------------秒杀判断------------------ 
					//console.log(res.data.active_info)
					if(res.data.active_info != null){
						//倒计时
						var start_time = res.data.active_info.start_time;
						var end_time = res.data.active_info.end_time; 
					 	var data={
							//nowdate:系统时间,
							startdate:start_time,//开始时间，格式为：h:m或日期格式
							enddate:end_time,//结束时间，格式为：h:m或日期格式
							setday:0//提前天数，例如：0为当天，1为明天
							//init:是否跳过开始时间，默认是false，当为true倒计时跳过开始时间
						}
						$.leftTime(data,function(d){
							if(d.status){//d.step 0表示普通倒计时，1表示未过开始时间，2表示已过开始时间未过结束时间，3表示已过结束时间；
								var $dateShow1=$(".seckill-time");
								$dateShow1.find(".h").html(d.h);
								$dateShow1.find(".m").html(d.m);
								$dateShow1.find(".s").html(d.s);
							} 
							//秒杀时间已过 再次调取数据
							if(d.step == 3){
								alert(d.step)
								mui.toast('当前秒杀已结束,即将开始新一轮秒杀!');
								setTimeout(function(){
									mui.back();
								},1000)
							} 
						})
					}else{
						
					}
					var dz_vip;
					var vip_dz_price;
					var show_price_zhanshi;
					if(login_type==1){
						dz_vip='赚';
						vip_dz_price=res.data.dianzhu_price;
					}else{
						dz_vip='会员价';
						vip_dz_price=res.data.vip_price;
					}
					if(res.data.show_price==0){
						show_price_zhanshi='none'
					}else{
						show_price_zhanshi='inline-block'
					}
					
					//商品信息
					str += '<p class="text_overflow">' + res.data.goods_name + '</p>' +
						'<i>' + res.data.introduction + '</i>' +
						'<div class="price">' +
						'<span><em>¥</em>' + res.data.price + '</span>' +
						'<i class="vip">'+dz_vip+'¥' + vip_dz_price + '</i>' +
						'<del class="vip" style="display:'+show_price_zhanshi+'">¥' + res.data.show_price + '</del>' +
						
						'<div class="number f_right">库存：' + res.data.stock + '</div>' +
						'</div>'
					$('.title').html(str);
	
					//规格 商品照片 单价 库存
					$('#imgs').attr('src', hetao.url2 + res.data.picture);
					$('#top').html('<em>¥</em>' + res.data.price);
					$('#ku1').html('库存' + res.data.stock + '件');
	
					//领券
					if(res.data.coupon.length==0) {
						$('.lingquan p').html('')
					} else {
						$('.lingquan p').html('满' + res.data.coupon.coupon_use_limit + '减' + res.data.coupon.coupon_price)
					}
	
					//商品评论
					var comment_list = res.data.comment_list;
					//console.log(comment_list);
					$('.comment_total').html('商品评论' + (res.data.comment_total));
					
					var pinglun = '';
					var user_avat;
					for(var i in comment_list) {
						if(comment_list[i].user_avat.indexOf('http')!=-1){
							user_avat=comment_list[i].user_avat;
						}else if(comment_list[i].user_avat){
							user_avat=hetao.url2+comment_list[i].user_avat;
						}else{
							user_avat='../../img/hetao.png';
						}
						
						
						
						
						
						
						pinglun+='<li class="mui-table-view-cell">'
							+'<a class="">'
								+'<div class="photo">'
									+'<img src="'+user_avat+'" />'
									+'<span>'+comment_list[i].user_name+'</span>'
									+'<i class="f_right">'+comment_list[i].or_add_time+'</i>'
								+'</div>'
								+'<div class="text">'
									+'<p class="text_line">'+comment_list[i].or_cont+'</p>'
									pinglun+='<ul class="display-flex">'
									for(var j in comment_list[i].or_thumb){
										pinglun+='<li class="flex">'
											pinglun+='<img src="'+comment_list[i].or_thumb[j]+'" alt="" />'
										pinglun+='</li>'
									}	
									pinglun+='</ul>'
								+'</div>'
							+'</a>'
						pinglun+='</li>'
					}
					$('.comment ul.mui-table-view').html(pinglun);
	
					//详情  商品介绍
//					
	
					//详情 规格参数
	
					//详情  购买须知
					var need_rule = res.data.need_rule;
					$('.need').html(need_rule);
	
					//相关推荐
					var recomm = res.data.recomm;   
					var shop_tuijain = '';
					var dz; //判断店主、vip的显示隐藏
					var vip;
					var zhuan;
					if(login_type == 1) {
						dz = 'block';
						vip = 'none';
						$('.shangjia').css('display','block');
					} else {
						dz = 'none';
						vip = 'block';
						$('.shangjia').css('display','none');
					}
					console.log(recomm);
					for(var i in recomm) {
						zhuan = parseInt(recomm[i].price) - parseInt(recomm[i].vip_price);
						shop_tuijain += '<li goods_id="' + recomm[i].goods_id + '">' +
							'<img src="' + hetao.url2 + '' + recomm[i].picture + '" />' +
							'<p class="text_line">' + recomm[i].goods_name + '</p>'
							//							    	 +'<em>少量</em>'
							+
							'<div class="vip like-list-price" style="display:' + vip + ';"><span class="t-color">¥' + recomm[i].price + '</span><i>会员价:¥' + recomm[i].vip_price + '</i></div>' +
							'<div class="dz like-list-price" style="display:' + dz + ';">' +
							'<span>¥' + recomm[i].price + '</span>' +
							'<i class="t-color">赚¥' + zhuan + '</i>' +
							'</div>'
						shop_tuijain += '</li>'
					}
					$('.like ul').html(shop_tuijain);
					shop_xiangq();
				}else{
					ca.prompt(res.msg);
					setTimeout(function(){
						ca.closeCurrentInterface();
					},500)	
				}
			}
		})
	}
	//lunbo_xx();
	
	
	setTimeout(function(){
		shop_xiangq();
	},1000)
		//商品详情详情
	function shop_xiangq() {
		var obj = {};
		obj.goodsid = goods_id;
				console.log(obj);
		ca.get({
			url: hetao.url + 'goods/goodsInfo',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
			//	console.log(res);
				var imgs = '';
				var description = res.data.intro;
				var content=res.data.content ;
				//console.log(content);
				if(res.status==1){
					//商品介绍
					for(var i in description) {
						imgs += '<li>' +
							'    <img src="' + hetao.url2 + description[i] + '" style="margin-bottom:0px;">' +
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
	function getGuize(){
		var obj = {};
		obj.goods_id = goods_id;
		//console.log(obj);
		ca.get({
			url: hetao.url + 'goods/getGuize',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
					console.log(res);
				var data = res.data;
				var str = '';
				var yangshi; //属性的样式
				var shuxing; //属性的样式
				if(res.status == 1) {
					for(var i in data) {
							console.log(data.length);
						if(i == 0) {
							shuxing = 'gg'
						}else if(i==1){
							shuxing = 'zhongjian'
						}else{
							shuxing = 'ys'
						}
						
						str += '<li class="types">' +
							'<span>' + data[i].spec_name + '</span>'
						str + '</li>'
						str += '<li class="center_top">'
						for(var j in data[i].values) {
							if(j == 0) {
								yangshi = 'on';
							} else {
								yangshi = '';
							}
							str += '<p class="' + yangshi + ' ' + shuxing + '"  shuxing="' + shuxing + '" spec_value_id="' + data[i].values[j].spec_value_id + '">' + data[i].values[j].spec_value_name + '</p>'
						}
						str += '</li>'
					}
					$('.center ul').html(str);
					jiage();
				}
			}
		});
	}
	getGuize();
	var guize1;
	var guize2;
	var guize3;
	//本页面商品详情的数据更新
	$('.like').on('tap', 'li', function() {
		goods_id = $(this).attr('goods_id');
		console.log(goods_id);
		guize1='';
		guize2='';
		guize3='';
		console.log(guize1);
		lunbo_xx();
		shop_xiangq();
		getGuize();
		youhui();
		shop_sucai();
		xiangce_pic();
		setTimeout(function() {
			jiage();
		},500);
	});
	
	//规格属性样式的切换
	
	$('.center').on('tap', 'p', function() {
		var spec_value_id = $(this).attr('spec_value_id');
		var shuxing = $(this).attr('shuxing');
		
		if(shuxing == 'gg') {
			guize1 = spec_value_id;
			if(!guize2) {
				var index =$('.center .zhongjian').attr('spec_value_id'); 
				guize2 = index;
			}
			if(!guize3){
				var index = $('.center .ys').attr('spec_value_id'); 
				guize3 = index;
			}
		} else if(shuxing == 'zhongjian'){
			if(!guize1) {
				var index = $('.center .gg').attr('spec_value_id');
				guize1 = index;
			}
			if(!guize3){
				var index = $('.center .ys').attr('spec_value_id'); 
				guize3 = index;
			}
			guize2 = spec_value_id;
			
		}else{
			if(!guize1) {
				var index = $('.center .gg').attr('spec_value_id');
				guize1 = index;
			}
			
			if(!guize2) {
				var index = $('.center .zhongjian').attr('spec_value_id'); 
				guize2 = index;
			}
			guize3 = spec_value_id;
		}
		
		jiage();
		$(this).addClass('on').siblings().removeClass('on')
	})
	var sku_id;
	//规格价格
	function jiage() {
		var index1 = $('.center .gg').attr('spec_value_id');
		var index2 = $('.center .zhongjian').attr('spec_value_id');
		var index3 = $('.center .ys').attr('spec_value_id');
		console.log(index1);
		console.log(index2);
		console.log(index3);
		if(!guize1) {
			if(index1) {
				guize1 = index1;
				console.log('再次点击1')
			} else {
				guize1 = '';
				console.log('再次点击2')
			}
		} else {
			guize1 = guize1;
			console.log('再次点击3')
		}
		
		if(!guize2) {
			if(index2) {
				guize2 = index2;
			} else {
				guize2 = '';
			}
		} else {
			guize2 = guize2;
		}
		if(!guize3) {
			if(index3) {
				guize3 = index3;
			} else {
				guize3 = '';
			}
		} else {
			guize3 = guize3;
		}
		var obj = {};
		obj.goods_id = goods_id;
		obj.guize1 = guize1;
		obj.guize2 = guize2;
		obj.guize3 = guize3;
		console.log(obj);
		ca.get({
			url: hetao.url + 'goods/getPrice',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
				console.log(res);
				if(res.status == 1) {
					sku_id = res.data.sku_id;
					console.log('果果');
					console.log(sku_id);
					if(res.data.image){
						$('#imgs').attr('src',hetao.url2+res.data.image);
					}
					$('.guige-list p').html(res.data.sku_name);
					$('#top').html('<em>¥</em>' + res.data.price);
					$('#ku1').html('库存' + res.data.stock + '件');
				}
				mui.plusReady(function(){plus.nativeUI.closeWaiting();});
			}
		})
	}

	
	//优惠券列表
	function youhui(){
		if(!user_id){
			user_id='';
			token='';
		}
		var obj = {};
		obj.token = token;
		obj.uid = user_id;
		obj.goodsid = goods_id;
		ca.get({
			url: hetao.url + 'goods/getCoupon',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
				console.log(res);
				var data = res.data;
				var str = '';
				if(res.status == 1) {
					if(data.length!=0){
						for(var i in data) {
							str += '<li coupon_id="' + data[i].coupon_id + '">' +
								'<div class="yhq-list-left">' +
								'<span>订单金额满' + data[i].coupon_use_limit + '可用</span>' +
								'<p>' + data[i].coupon_s_time + '-' + data[i].coupon_aval_time + '</p>' +
								'</div>' +
								'<div class="yhq-list-right">' +
								'<span><em>¥</em>' + data[i].coupon_price + '</span>' +
								'<p>立即领取</p>' +
								'</div>'
							str += '</li>'
						}
						$('.yhq-list ul').html(str);
					}else{
						$('.yhq-list ul').html('<p style="text-align: center;padding:30vw 0;font-size:13px;">当前商品没有优惠券</p>');
					}	
				}else{
					$('.yhq-list ul').html('<p style="text-align: center;padding:30vw 0;font-size:13px;">当前商品没有优惠券</p>');
				}
			}
		})
	}
	youhui();
	
	//收藏商品
	$('.collects').click(function() {
		console.log(is_favor);
		if(!user_id) {
			ca.prompt('请先登录');
			return;
		}
		var obj = {};
		obj.token = token;
		obj.uid = user_id;
		obj.goodsid = goods_id;
		console.log(obj);
		if(is_favor == 0) {
			ca.get({
				url: hetao.url + 'goods/goodsFavor',
				data: obj,
				succFn: function(data) {
					var res = JSON.parse(data);
					//console.log(res);
					var data = res.data;
					var str = '';
					if(res.status == 1) {
						ca.prompt('商品收藏成功');
						var arr = ['personal/my_shoucang.html'];
						ca.sendNotice(arr, 'shoucang', {});
						$('.collects icon').css('color', '#d31a1a');
						$('.collects i').css('color', '#d31a1a');
						lunbo_xx();
					}
				}
			});
		} else if(is_favor == 1) {
			ca.get({
				url: hetao.url + 'goods/goodsFavor',
				data: obj,
				succFn: function(data) {
					var res = JSON.parse(data);
					//console.log(res);
					var data = res.data;
					var str = '';
					if(res.status == 1) {
						ca.prompt('商品取消收藏成功');
						$('.collects icon').css('color', '#333');
						$('.collects i').css('color', '#333');
						lunbo_xx();
	
					}
				}
			});
		}
	
	});
	
	//商品上下架
	$('.shangjia').click(function() {
		console.log(is_shangjia);
		if(is_shangjia==1){
			var obj={};
			obj.uid=user_id;
			obj.token=token;
			obj.goods_id=goods_id;
			obj.type=2;
			console.log(obj);
			ca.confirm({
				title:'提示',
				content:'是否下架商品',
				callback:function(data){
					if(data){
						ca.get({
							url:hetao.url+'store/goodsManage',
							data:obj,
							succFn:function(data){
								var res=JSON.parse(data);
								console.log(res);
								if(res.status==1){
									var arr=['shop/shop_manage.html'];
									ca.sendNotice(arr,'xiajia',{});
									ca.prompt('该商品已从商铺中下架');
									lunbo_xx();
								}
							}
						})
					}
				}
			});
		}else{
			var obj={};
			obj.uid=user_id;
			obj.token=token;
			obj.goods_id=goods_id;
			obj.type=1;
			console.log(obj);
			ca.confirm({
				title:'提示',
				content:'是否上架商品',
				callback:function(data){
					if(data){
						ca.get({
							url:hetao.url+'store/goodsManage',
							data:obj,
							succFn:function(data){
								var res=JSON.parse(data);
								console.log(res);
								if(res.status==1){
									var arr=['shop/shop_manage.html'];
									ca.sendNotice(arr,'xiajia',{});
									
									ca.prompt('该商品已上架到商铺中');
									lunbo_xx();
								}
							}
						})
					}
				}
			});
		}	
	});
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	//商品详情素材
	//var is_favorite;//判断素材收藏
	function shop_sucai() {
		if(login_type == 1) {
			$('.shangchuan').css('display', 'block');

		} else {
			$('.shangchuan').css('display', 'none');

		}
		var obj = {};
		if(!user_id) {
			user_id = '';
			token = '';
		} else {
			user_id = user_id;
			token = token;
		}
		obj.uid = user_id;
		obj.token = token;
		obj.goodsid = goods_id;
		var user_avat;
		var sc_img;
		console.log(obj);
		ca.get({
			url: hetao.url + 'goods/goodsMaterial',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
				//console.log(res);
				var data = res.data;
				var str = '';
				var sucai_shou;
				if(res.status == 1) {
					for(var i in data) {
						if(data[i].is_favorite == 1) {
							sucai_shou = 'active';
						} else {
							sucai_shou = '';
						}

						if(!data[i].user_avat) {
							user_avat = '../../img/hetao.png';
						} else if(data[i].user_avat.indexOf('http')!=-1){
							user_avat=data[i].user_avat;
						}else {
							user_avat = hetao.url2 + data[i].user_avat;
						}
						str += '<div class="find-list" m_id="' + data[i].m_id + '">' +
							'<div class="find-list-top">' +
							'<img src="' + user_avat + '"/>' +
							'<span>' + data[i].user_name + '</span>' +
							'<p>' + data[i].mate_add_time + '</p>' +
							'</div>' +
							'<div class="find-list-text text_line">' +
							'<p>' + data[i].mate_content + '</p>' +
							'</div>'
						str += '<div class="find-list-imgs">'
							for(var j in data[i].mate_thumb) {
								if(data[i].mate_thumb){
									sc_img='inline-block';
								}else{
									sc_img='';
								}
								str += '<img src="' + hetao.url2 + '' + data[i].mate_thumb[j] + '" data-preview-src="" data-preview-group="1"  style="display: '+sc_img+';"/>'
							}
						str += '</div>' +
							'<div class="collect">' +
							'<div class="coll" id="coll" is_favorite="' + data[i].is_favorite + '">' +
							'<i class="iconfont icon-shoucang ' + sucai_shou + '"></i>' +
							'<span class="' + sucai_shou + '">收藏</span>' +
							'</div>' +
							'<div class="share" style="display:block;position:initial">' +
								'<i class=""></i>' +
								'<span>分享</span>' +
							'</div>' +
							'</div>'
						str += '</div>'
					}
					if(res.data == '') {
						str = '<p style="text-align: center;padding:30vw 0;font-size:13px;">当前商品没有更多素材</p>';
						$('.find').css('background-color', '#fff');
					}
					$('.find #source').html(str);
				}
			}
		})
	}
	shop_sucai();

	//素材收藏
	$('.find').on('tap', '.collect #coll', function() {
		var is_favorite = $(this).attr('is_favorite');
		var m_id = $(this).parents('.find-list').attr('m_id');
		//console.log(is_favorite);
		//console.log(m_id);
		if(!user_id) {
			ca.prompt('请先登录');
			return;
		}
		var obj = {};
		obj.token = token;
		obj.uid = user_id;
		obj.mid = m_id;
		if(is_favorite == 1) {
			ca.get({
				url: hetao.url + 'Goods/mateFavor',
				data: obj,
				succFn: function(data) {
					var res = JSON.parse(data);
					//console.log(res);
					var data = res.data;
					var str = '';
					if(res.status == 1) {
						ca.prompt('素材取消收藏成功');
						var arr = ['personal/my_shoucang.html'];
						ca.sendNotice(arr, 'shoucang', {});
						$('.find .collect #coll span').removeClass('active');
						$('.find .collect #coll i').removeClass('active');
						shop_sucai();
						//$('.find .collect #coll').attr('is_favorite', 0);
					}
				}
			});
		} else {
			ca.get({
				url: hetao.url + 'Goods/mateFavor',
				data: obj,
				succFn: function(data) {
					var res = JSON.parse(data);
					//console.log(res);
					var data = res.data;
					var str = '';
					if(res.status == 1) {
						ca.prompt('素材收藏成功');
						var arr = ['personal/my_shoucang.html'];
						ca.sendNotice(arr, 'shoucang', {});
						$('.find .collect #coll span').addClass('active');
						$('.find .collect #coll i').addClass('active');
						shop_sucai();
						//$('.find .collect #coll').attr('is_favorite', 1);

					}
				}
			});
		}
	})
	
	
	
	
	
	
	
	
	
	
	
	

	


	//立即购买
	$('.buy').click(function() {
        goods_id=goods_id.split('&')[0];
        console.log(goods_id);
        var num = $('.mui-input-numbox').val();
        localStorage.setItem('num', num);
        console.log(num)
        console.log(sku_id);
        if(!user_id) {
            ca.prompt('您还没有登录');
            ca.newInterface({
                url: '../personal/login_xuanze.html',
                id: '../personal/login_xuanze.html'
            })
            return;
        } else {
            ca.newInterface({
                url: '../car/write_order.html?goods_id=' + goods_id + '&' + sku_id + '&' + 'shop'+'&'+s_id,
                id: '../car/write_order.html'
            })
        }
	});

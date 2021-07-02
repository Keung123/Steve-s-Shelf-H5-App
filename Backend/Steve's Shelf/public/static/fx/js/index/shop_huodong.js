	/*商品详情*/
	console.log(user_id, token, login_type,goods_id, active_type_id, 111111111111112333333333333333333);
	//console.log(goods_id);
	//console.log(active_id);
	var is_favor; //是否收藏
	var is_shangjia; //是否收藏
	//console.log(goods_id);

	
	
	
	
	
	
	
	
	ca.receiveNotice('qxshoucang', function() {
		lunbo_xx();
		
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
		if(active_type_id) {
			obj.active_id = active_type_id;
		}
		console.log(JSON.stringify(obj));
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
					var price_yuan;
					var huodong_price;
					var show_pice;
					if(active_type_id==6 || active_type_id==7 || active_type_id==8){
						price_yuan=res.data.price;
						huodong_price='none';
					}else{
						price_yuan=res.data.active_price;
						huodong_price='inline-block';
					}
					
					var dz_vip;
					var vip_dz_price;
					var show_price_zhanshi;
					var show_zhanshi;
					if(login_type==1){
						dz_vip='赚';
						vip_dz_price=res.data.dianzhu_price;
						show_zhanshi='inline-block'
					}else{
						show_zhanshi='none'
						//dz_vip='会员价';
						//vip_dz_price=res.data.vip_price;
					}
					
					
					//alert(active_type_id);
					if(active_type_id==6 || active_type_id==7 || active_type_id==8){
						if(res.data.show_price==0.00){
							show_price_zhanshi='none';
							show_pice=res.data.price;
							//alert(active_type_id);
						}else{
							show_price_zhanshi='inline-block';
							show_pice=res.data.show_price;
							//alert(active_type_id);
						}
					}else{
						if(res.data.show_price==0.00){
							show_price_zhanshi='none';
							show_pice=res.data.show_price;
						}else{
							show_price_zhanshi='inline-block';
							show_pice=res.data.show_price;
						}
					}
					
					
					//商品信息
					var menu_xs;
					var menu_xs1;
					if(res.data.abstract_content){
						menu_xs='block';
						menu_xs1='none';
					}else{
						menu_xs='none !important';
						menu_xs1='inline-block';
					}
					
					str += '<p class="text_overflow" style="display:'+menu_xs+'"><em>'+res.data.active_name+'</em>' + res.data.abstract_content + '</p>' +
						'<p class="text_overflow"><em style="display:'+menu_xs1+'">'+res.data.active_name+'</em>' + res.data.goods_name + '</p>' +
						'<i>' + res.data.introduction + '</i>' +
						'<div class="price">' +
						'<span><em>¥</em>' + res.data.active_price + '</span>' +
						'<i class="vip" style="display:'+show_zhanshi+'">'+dz_vip+'¥' + vip_dz_price + '</i>' +
						'<del class="vip" style="display:'+show_price_zhanshi+'">¥' +show_pice + '</del>' +	
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
//					for(var i in recomm) {
//						zhuan = parseInt(recomm[i].price) - parseInt(recomm[i].vip_price);
//						shop_tuijain += '<li goods_id="' + recomm[i].goods_id + '">' +
//							'<img src="' + hetao.url2 + '' + recomm[i].picture + '" />' +
//							'<p class="text_line">' + recomm[i].goods_name + '</p>'
//							//							    	 +'<em>少量</em>'
//							+
//							'<div class="vip like-list-price" style="display:' + vip + ';"><span class="t-color">¥' + recomm[i].price + '</span><i>会员价:¥' + recomm[i].vip_price + '</i></div>' +
//							'<div class="dz like-list-price" style="display:' + dz + ';">' +
//							'<span>¥' + recomm[i].price + '</span>' +
//							'<i class="t-color">赚¥' + zhuan + '</i>' +
//							'</div>'
//						shop_tuijain += '</li>'
//					}
//					$('.like ul').html(shop_tuijain);
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
	
	//相关推荐
	var page=0;
	function tuijian(){
		var obj={};
		page++;
		obj.active_type_id=active_type_id;
		obj.limit='4';
		obj.p=page;
		console.log(obj);
		ca.get({
			url:hetao.url+'index/getActiveGoods',
			data:obj,
			succFn:function(data){
				var res=JSON.parse(data);
				console.log(res);
				var data=res.data.list;
				var str='';
				var show_price_zhanshi;
				var vip_zhanshi;
				var dz_zhanshi;
				if(res.status==1){
					//alert(active_type_id);
					for(var i in data) {
						if(active_type_id==6 || active_type_id==7 || active_type_id==8){
							if(data[i].show_price==0.00){
								show_price_zhanshi='none';
								show_pice=data[i].show_price;
								//alert(active_type_id);
							}else{
								show_price_zhanshi='inline-block';
								show_pice=data[i].show_price;
								//alert(active_type_id);
							}
						}else{
							if(data[i].show_price==0.00){
								show_price_zhanshi='none';
								show_pice=data[i].show_price;
							}else{
								show_price_zhanshi='inline-block';
								show_pice=data[i].show_price;
							}
						}
						
						if(login_type==1){
							dz_zhanshi='block';
							vip_zhanshi='none';
						}else{
							vip_zhanshi='block';
							dz_zhanshi='none';
						}
						
						str += '<li goods_id="' + data[i].goods_id + '">' +
							'<img src="' + hetao.url2 + '' + data[i].picture + '" />' +
							'<p class="text_line"><em>'+res.active_type_name+'</em>'+ data[i].goods_name + '</p>'
							+'<em>库存:'+data[i].goods_number+'</em>'
							+'<div class="vip like-list-price" style="display:'+vip_zhanshi+'"><span class="t-color">¥' + data[i].active_price + '</span><del style="display:'+show_price_zhanshi+'">¥' + data[i].show_price + '</del></div>'
							+'<div class="like-list-price" style="display:'+dz_zhanshi+'"><span class="t-color">¥' + data[i].profit + '</span><lable>赚¥' + data[i].profit + '</label></div>'
						str += '</li>'
					}
					$('.like ul').html(str);
				}
			}
		})
	}
	tuijian();
	
		//本页面商品详情的数据更新
	$('.like').on('tap', 'li', function() {
		goods_id = $(this).attr('goods_id');
		console.log(active_type_id);
		console.log(goods_id);
		mui.openWindow({
			url:'goods-details_huodong.html?goods_id='+goods_id+'&'+active_type_id,
			id:'goods-details_huodong',
			createNew:true,//是否重复创建同样id的webview，默认为false:不重复创建，直接显示
		})
//		ca.newInterface({
//			url:'goods-details_huodong.html?goods_id='+goods_id+'&'+active_type_id,
//			id:'goods-details_huodong.html'
//		})
	});
	
	
	
	
	
	
	
	
	
	
	
	
	//商品详情详情
	function shop_xiangq() {
		var obj = {};
		obj.goodsid = goods_id;
//				console.log(obj);
		ca.get({
			url: hetao.url + 'goods/goodsInfo',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
				console.log(res);
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
	//shop_xiangq();
	
	
	
	
	
	
	
	
	
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
					//console.log(res);
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
//		console.log(index);
		if(!guize1) {
			if(index1) {
				guize1 = index1;
			} else {
				guize1 = '';
			}
		} else {
			guize1 = guize1;
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
		//console.log(obj);
		ca.get({
			url: hetao.url + 'goods/getPrice',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
				console.log(res);
				if(res.status == 1) {
					sku_id = res.data.sku_id;
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
//	setTimeout(function() {
//		
//	}, 1500);
	
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
									ca.prompt('该商品已下架到商铺中');
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
		//console.log(obj);
		ca.get({
			url: hetao.url + 'goods/goodsMaterial',
			data: obj,
			succFn: function(data) {
				var res = JSON.parse(data);
				console.log(res);
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
					console.log(res);
					var data = res.data;
					var str = '';
					if(res.status == 1) {
						ca.prompt('素材取消收藏成功');
						var arr = ['personal/my_shoucang.html'];
						ca.sendNotice(arr, 'shoucang', {});
						$('.find .collect #coll span').removeClass('active');
						$('.find .collect #coll i').removeClass('active');
						//$('.find .collect #coll').attr('is_favorite', 0);
						shop_sucai();
					}
				}
			});
		} else {
			ca.get({
				url: hetao.url + 'Goods/mateFavor',
				data: obj,
				succFn: function(data) {
					var res = JSON.parse(data);
					console.log(res);
					var data = res.data;
					var str = '';
					if(res.status == 1) {
						ca.prompt('素材收藏成功');
						var arr = ['personal/my_shoucang.html'];
						ca.sendNotice(arr, 'shoucang', {});
						$('.find .collect #coll span').addClass('active');
						$('.find .collect #coll i').addClass('active');
						//$('.find .collect #coll').attr('is_favorite', 1);
						shop_sucai();
					}
				}
			});
		}
	})
	
	
	
	
	
	
	
	
	
	
	
	

	


	//立即购买
	$('.buy').click(function() {
        console.log(sku_id);
        var num = $('.mui-input-numbox').val();
        localStorage.setItem('num', num);
        console.log(num)
        if(!user_id) {
            ca.prompt('您还没有登录');
            ca.newInterface({
                url: '../personal/login_xuanze.html',
                id: '../personal/login_xuanze.html'
            })
            return;
        } else {
            ca.newInterface({
                url: '../car/write_order.html?goods_id=' + goods_id + '&' + sku_id + '&' + 'huodong'+'&'+active_type_id,
                id: '../car/write_order.html'
            })
        }
	});

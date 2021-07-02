	/*商品详情*/
	var user_id = localStorage.getItem('user_id');
	var token = localStorage.getItem('token');
	var login_type;
	login_type = localStorage.getItem('login_type');
	var pintaun_id=window.location.search.split('=')[1].split('&');
	var goods_id=pintaun_id[0];
	var active_type_id=pintaun_id[1];
	var pintaunid=pintaun_id[2];
	var is_favor; //是否收藏
	//console.log(goods_id);
	
	ca.receiveNotice('qxshoucang', function() {
		lunbo_xx();
		shop_xiangq();
		shop_sucai();
	})
	
	ca.receiveNotice('login',function(){
		user_id=localStorage.getItem('user_id');
		token=localStorage.getItem('token');
		login_type = localStorage.getItem('login_type');
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
					//console.log(is_favor);
					if(is_favor == 0) {
						//alert(00000);
						$('.shoucang em').css('color', '#333');
						$('.shoucang p').css('color', '#333');
						$('.shoucang em').attr('class','iconfont icon-shoucang2');	
					} else {
						//alert(1111);
						$('.shoucang em').css('color', '#d31a1a');
						$('.shoucang p').css('color', '#d31a1a');
						$('.shoucang em').attr('class','iconfont icon-shoucang3');		
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
						interval: 3000 //自动轮播周期，若为0则不自动播放，默认为0；			
					})

					//商品信息
					//商品信息    
					str+='<div>'
					     +'<span>'+res.data.goods_name+'</span>'
//					     +'<p>（柔肤水200ml+乳液160ml+精华露5ml+眼部精华露5ml+面霜10m</p>'
					     +'<label><i>¥</i>'+res.data.active_price+'</label>'
					     +'<em class="mui-pull-right">库存：'+res.data.stock+'</em>'
					     str+='</div>'
					$('.shop').html(str);
	
					//规格 商品照片 单价 库存
					$('#imgs').attr('src', hetao.url2 + res.data.picture);
					$('#imgs1').attr('src', hetao.url2 + res.data.picture);
					$('#top').html('<em>¥</em>' + res.data.active_price);
					$('#ku1').html('库存' + res.data.stock + '件');
					$('.top1').html('<em>¥</em>' + res.data.active_price);
					$('.ku2').html('库存' + res.data.stock + '件');
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
					//单独购买价
					$('.dandu span').html(res.data.price);
//					//相关推荐
//					var recomm = res.data.recomm;
//					var shop_tuijain = '';
//					var dz; //判断店主、vip的显示隐藏
//					var vip;
//					var zhuan;
//					if(login_type == 1) {
//						dz = 'block';
//						vip = 'none';
//					} else {
//						dz = 'none';
//						vip = 'block';
//					}
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
				//console.log(res);
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
					shop_sucai();
				}
				
				
			}
		})
	}
	//shop_xiangq();
	
	//收藏商品
	$('.shoucang').click(function() {
		console.log(is_favor);
		if(!user_id) {
			ca.prompt('请先登录');
			return;
		}
		var obj = {};
		obj.token = token;
		obj.uid = user_id;
		obj.goodsid = goods_id;
		//console.log(obj);
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
						$('.shoucang em').css('color', '#d31a1a');
						$('.shoucang p').css('color', '#d31a1a');
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
						$('.shoucang em').css('color', '#333');
						$('.shoucang p').css('color', '#333');
						lunbo_xx();
	
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
						} else {
							user_avat = hetao.url2 + data[i].user_avat;
						}
						str+='<li m_id="'+data[i].m_id+'">'
							+'<div class="top">'
								+'<img src="'+user_avat+'" />'
								+'<span>'+data[i].user_name+'</span>'
								+'<label class="mui-pull-right">'+data[i].mate_add_time+'</label>'
							+'</div>'
							+'<div class="content content1">'+data[i].mate_content+'</div>'
							+'<div class="pic">'
								str +='<ul>'
								for(var j in data[i].mate_thumb) {
									if(data[i].mate_thumb){
										sc_img='inline-block';
									}else{
										sc_img='';
									}
									str += '<li><img src="' + hetao.url2 + '' + data[i].mate_thumb[j] + '" data-preview-src="" data-preview-group="1"  style="display: '+sc_img+';"/></li>'
								}
								str +='</ul>'
							+'</div>'
							+'<div class="operation">'
								+'<ul>'
									+'<li class="bianji '+sucai_shou+'" is_favorite="'+data[i].is_favorite+'">'
										+'<em class="iconfont icon-shoucang"></em>'
										+'<span>收藏</span>'
									+'</li>'
									+'<li  class="share" style="display:block;position:initial">'
										+'<em class="iconfont icon-fenxiang12"></em>'
										+'<span>分享</span>'   
									+'</li>'
								+'</ul>'
							+'</div>'
						+'</li>'
					}
					if(res.data == '') {
						str = '<p style="text-align: center;padding:30vw 0;font-size:13px;">当前商品没有更多素材</p>';
						$('.source').css('background-color', '#fff');
					}
					$('.source_sc').html(str);
				}
			}
		})
	}
	shop_sucai();

	//素材收藏
	$('.source_sc').on('tap', '.bianji', function() {
		var is_favorite = $(this).attr('is_favorite');
		var m_id = $(this).parents('li').attr('m_id');
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
		//console.log(obj);
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
						$('.source_sc .bianji').removeClass('active');
						$('.source_sc .bianji').removeClass('active');
						//$('.source_sc .bianji').attr('is_favorite', 0);
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
						$('.source_sc .bianji').addClass('active');
						$('.source_sc .bianji').addClass('active');
						//$('.source_sc .bianji').attr('is_favorite', 1);
						shop_sucai();
					}
				}
			});
		}
	})
	
	
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
	
	//规格属性样式的切换
	var guize1;
	var guize2;
	var guize3;
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
		$(this).addClass('on').siblings().removeClass('on');
		jiage();
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
		console.log(obj);
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
					//alert(res.data.price);
					$('#top').html('<em>¥</em>' + res.data.price);
					$('#ku1').html('库存' + res.data.stock + '件');
					$('.guige-list p').html(res.data.sku_name);
					$('.top1').html('<em>¥</em>' + res.data.price);
					$('.ku2').html('库存' + res.data.stock + '件');
					//alert(res.data.stock);
				}
				mui.plusReady(function(){plus.nativeUI.closeWaiting();});
			}
		})
	}
//	setTimeout(function() {
//		jiage();
//	}, 1500);
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	//拼团方式
	function pt_fangshi(){
		var obj={};
		obj.goods_id=goods_id;
		console.log(obj);
		ca.get({
			url:hetao.url+'Activity/teamDetails',
			data:obj,
			succFn:function(data){
				var res=JSON.parse(data);
				console.log(res);
				var data=res.data;
				var str='';
				var str1='';
				if(res.status==1){
					for(var i in data){
						str+='<li id="'+data[i].id+'">'
							+'<span>'+data[i].need_num+'人拼团</span>'
							+'<label>¥'+data[i].price+'</label>'
							+'<em class="vip">拼团价¥'+data[i].team_price+'</em>'
							+'<strong class="iconfont icon-aui-icon-right mui-pull-right"></strong>'
							+'<i class="mui-pull-right">'+data[i].joins+'人正在拼团</i>'
						str+='</li>'		
					}
					$('.pintaun ul').html(str);
				}
			}
		})
	}
	pt_fangshi();
	
	function dibu_fangshi(){
		var obj={};
		obj.goods_id=goods_id;
		console.log(obj);
		ca.get({
			url:hetao.url+'Activity/teamDetails',
			data:obj,
			succFn:function(data){
				var res=JSON.parse(data);
				console.log(res);
				var data=res.data;
				var str='';
				var str1='';
				if(res.status==1){
					for(var i in data){
						str1+='<li id="'+data[i].id+'">'
							+'<label>¥</label>'
							+'<span>'+data[i].team_price+'</span>'
							+'<p>'+data[i].need_num+'人拼团</p>'
						str1+='<li>'	
					}
					$('.footer ul').append(str1);
					pintuan();
				}
			}
		})
	}
	dibu_fangshi();
	
	
	
	//拼团信息
	$('.pintaun').on('tap','li',function(){
		hidden();
//		var data_time=new Date();
//		var dq_time=data_time.getHours()+':'+data_time.getMinutes();
//		console.log(dq_time);
	
	}); 
	function pintuan(){
		
		var id=$('.pintaun li').attr('id');
		console.log(id);
		var obj={};
		obj.id=id;
		ca.get({
			url:hetao.url+'Activity/teamInfo',
			data:obj,
			succFn:function(data){
				var res=JSON.parse(data);
				console.log(res);
				var list=res.data;
				var str='';
				var user_avat;
				var people;
				if(res.status==1){
					for(var i in list){
						
						if(!list[i].user_avat){
							user_avat='../../img/hetao.png';
						}else if(list[i].user_avat.indexOf('http')!=-1){
							user_avat=list[i].user_avat;
						}else{
							user_avat=hetao.url2+list[i].user_avat;
						}
						var shi_time;
						var fen_time;
						var miao_time;
						people=parseInt(list[i].need)-parseInt(list[i].joins);
						
						//shop_sta='秒杀倒计时'+list[i].last_time;
						//倒计时
//						var start_time =list[i].start_time;
//						var end_time = list[i].end_time;
//						console.log(start_time);
//						console.log(end_time);
//					 	var data={
//							//nowdate:dq_time,//系统时间
//							startdate:start_time,//开始时间，格式为：h:m或日期格式
//							enddate:end_time,//结束时间，格式为：h:m或日期格式
//							setday:1,//提前天数，例如：0为当天，1为明天
//							//init:false//是否跳过开始时间，默认是false，当为true倒计时跳过开始时间
//						}
//					 	console.log(data)
//						$.leftTime(data,function(d){
//							//console.log(d.h)
//							//shop_sta='秒杀倒计时';	
//							if(d.status){//d.step 0表示普通倒计时，1表示未过开始时间，2表示已过开始时间未过结束时间，3表示已过结束时间；
//								var $dateShow1=$(".collage label p");
//								//console.log($dateShow1)
//								//$dateShow1.find(".d").html(d.d);
//								shi_time=d.h;
//								fen_time=d.m;
//								miao_time=d.s;
//								//console.log(miao_time);
//								//$dateShow1.find(".h").html(d.h);
//								//$dateShow1.find(".m").html(d.m);
//								//$dateShow1.find(".s").html(d.s);
//							}
//							if(d.step==3){
//								
//							}
//						})
						
						
						str+='<li id="'+list[i].id+'">'
							+'<img src="'+user_avat+'" />'
							+'<span>'+list[i].nick_name+'</span>'
							+'<label>'
								+'<em>还差'+people+'人</em>'
								+'<p data-min="'+list[i].start_time+'" data-max="'+list[i].end_time+'" class="time"></p>'
							+'</label> '
							+'<i class="mui-pull-right">参与拼团</i>'
						str+='</li>'	
					}
					$('.collage ul').html(str);
					
					
					
						//多组定时器
						var number=$('.collage').find("li").length;
						if(number>0){
							console.log('倒计时')
							$(".collage ul li").each(function(){
								var start_time = $(this).find(".time").attr("data-max");
								var end_time = $(this).find(".time").attr("data-min");
							 	var datas={
									//nowdate:系统时间,
									startdate:start_time,//开始时间，格式为：h:m或日期格式
									enddate:end_time,//结束时间，格式为：h:m或日期格式
//									setday:0//提前天数，例如：0为当天，1为明天
									//init:是否跳过开始时间，默认是false，当为true倒计时跳过开始时间
								}
							 	var that =$(this);
								$.leftTime(datas,function(d){
//									if(d.status){//d.step 0表示普通倒计时，1表示未过开始时间，2表示已过开始时间未过结束时间，3表示已过结束时间；
										that.find('.time').html('<a>'+d.h +'</a>:<a>'+ d.m +'</a>:<a>'+ d.s+'</a>' );	 
//									} 
									//秒杀时间已过 再次调取数据 
									if(d.step == 3){ 
										that.find('.time').html('当前拼团已结束~'); 
									} 
								})
							})
						}
				}else{
					$('.collage ul').html('<p class="mark">当前还没有拼团信息哦</p>');
				}
			}
		})	
	}
	
	//参与拼团
	var pintuan_canyu={};
	$('.collage').on('tap','i',function(){
		var id=$(this).parents('li').attr('id');
		console.log(id);	
		pintuan_canyu.uid=user_id;
		pintuan_canyu.token=token;
		pintuan_canyu.found_id=id;
		console.log(pintuan_canyu);
		$('.cantuan').css('display','block');
		localStorage.setItem('pintuanid',id);
		localStorage.setItem('pincan',1);
	});
	
	$('.cantuan_car').click(function(){
        console.log(pintuan_canyu);
        console.log(goods_id);
        console.log(sku_id);
        var num=$('.mui-input-numbox').val();
        console.log(num);
        localStorage.setItem('num',num);
        if(user_id){
            if(num==1){
                ca.confirm({
                    title:'提示',
                    content:'是否参与拼团',
                    callback:function(data){
                        ca.get({
                            url:hetao.url+'Activity/CheckedFollow',
                            data:pintuan_canyu,
                            succFn:function(data){
                                var res=JSON.parse(data);
                                console.log(res);
                                if(res.status==1){
                                    ca.newInterface({
                                        url: '../car/write_order.html?goods_id=' + goods_id + '&' + sku_id + '&' + 'pintuan'+'&'+3,
                                        id: '../car/write_order.html'
                                    })
                                }else{
                                    ca.prompt(res.msg)
                                }
                            }
                        })
                    }
                });
            }else{
                ca.prompt('拼团活动只能选择一件商品');
            }
        }else{
            ca.prompt('请登录');
            ca.newInterface({
                url:'../personal/login_xuanze.html',
                id:'../personal/login_xuanze.html',
            })
            return;
        }
	});
	
	//发起拼团	
	var kaituan_canyu={};
	$('.footer').on('tap','li',function(){
		var id=$(this).attr('id');
		console.log(id);
		kaituan_canyu.uid=user_id;
		kaituan_canyu.token=token;
		kaituan_canyu.team_id=id;
		localStorage.setItem('pintuanid',id);
		localStorage.setItem('pincan',2);
		$('.kaituan').css('display','block');
	});
	
	
	$('.foot').on('tap','#gouwuche1',function(){
        console.log(kaituan_canyu);
        console.log(goods_id);
        console.log(sku_id);
        var num=$('.mui-input-numbox').val();
        console.log(num);
        localStorage.setItem('num',num);
        if(user_id){
            if(num==1){
                ca.get({
                    url:hetao.url+'Activity/CheckedTeam',
                    data:kaituan_canyu,
                    succFn:function(data){
                        var res=JSON.parse(data);
                        console.log(res);
                        if(res.status==1){

                            ca.newInterface({
                                url: '../car/write_order.html?goods_id=' + goods_id + '&' + sku_id + '&' + 'pintuan'+'&'+3,
                                id: '../car/write_order.html'
                            })
                        }else{
                            ca.prompt(res.msg);
                        }
                    }
                })
            }else{
                ca.prompt('拼团活动只能选择一件商品');
            }
        }else{
            ca.prompt('请登录');
            ca.newInterface({
                url:'../personal/login_xuanze.html',
                id:'../personal/login_xuanze.html',
            })
            return;
        }
	});
	
	
	//单独购买
	$('.dandu').click(function(){
		if(!user_id){
			ca.prompt('请登录');
			ca.newInterface({
				url:'../personal/login_xuanze.html',
				id:'../personal/login_xuanze.html',
			})
			return;
		}else{
			ca.newInterface({
				url:'../car/write_order.html?goods_id='+goods_id,
				id:'../car/write_order.html'
			})
		}
	});
	
	

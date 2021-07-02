
var is_favor; //是否收藏

ca.receiveNotice('qxshoucang', function() {
	lunbo_xx();
	shop_xiangq();
	shop_sucai();
})







//商品
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
	obj.uid=user_id;
	obj.token=token;
	obj.goods_id = goods_id;
	console.log(obj)
	ca.post({
		url: hetao.url + 'Integral/intergalDetail',
		data: obj,
		succFn: function(data) {
			var res = JSON.parse(data);
						console.log(res);
			var str = '';
			var pic = '';
			var goods_attr = res.data.goods_attr;
//			console.log(goods_attr)
			if(res.status == 1) {
				var arr=['personal/my_footprint.html'];
				ca.sendNotice(arr,'zuji',{});
				is_favor = res.data.is_favor;
				if(is_favor == 0) {
					$('.collects icon').css('color', '#333');
					$('.collects i').css('color', '#333');
					$('.collects icon').attr('class','iconfont icon-shoucang2');	
				} else {
					$('.collects icon').css('color', '#d31a1a');
					$('.collects i').css('color', '#d31a1a');
					$('.collects icon').attr('class','iconfont icon-shoucang3');		
				}
				var top_img = res.data.images
				var html = '';
				var point;
				
				//轮播图渲染
				html += '<div class="mui-slider-item mui-slider-item-duplicate">' +
					'<a>' +
					'<img src="' + hetao.url2 + '' + top_img[top_img.length - 1]+ '"/>' +
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
					'<img src="' + hetao.url2 + '' + top_img[0]  + '"/>' +
					'</a>' +
					'</div>'
				$('.mui-slider-loop').html(html)
				$('.shuzi i').html(top_img.length);
				var gallery = mui('.mui-slider');
				gallery.slider({
					interval: 2000 //自动轮播周期，若为0则不自动播放，默认为0；			
				})

				//商品信息
				str += '<p class="text_overflow">' + res.data.goods_name + '</p>' +
					'<i>' + res.data.introduction + '</i>' +
					'<div class="price">' +
					'<span><em>积分：</em>' + res.data.exchange_integral + '</span>' +
					'<div class="number f_right">库存：' + res.data.stock + '</div>' +
					'</div>'

				$('.title').html(str);

				//规格商品照片 库存
				$('#imgs').attr('src', hetao.url2 + res.data.picture);
				$('#top').html('<em>¥</em>' + res.data.exchange_integral);
				$('#ku1').html('库存' + res.data.stock + '件');

				//规格属性
				var yangshi; //属性的样式
				var shuxing; //属性的样式
				var type = '';
				var spec_array = res.data.spec_array
				for(var i in spec_array) {
					if(i == 0) {
						shuxing = 'gg'
					} else {
						shuxing = 'ys'
					}
					type += '<ul id="' + spec_array[i].spec_id + '"><li class="types" >' +
						'<span>' + spec_array[i].spec_desc + '</span>' +
						'</li>' +
						'<li class="center_top">';
					for(var j in spec_array[i].values) {
						if(j == 0) {
							yangshi = 'on';
						} else {
							yangshi = '';
						}
						type += '<p class="' + yangshi + ' ' + shuxing + '"  shuxing="' + shuxing + '" spec_value_id="' + spec_array[i].values[j].spec_value_id + '">' + spec_array[i].values[j].spec_value_name + '</p>';
					}
					type += '</li></ul>';
				}
				$('.max-box .center').html(type);

				//each 获取到规格分类  规格参数
				var g = '';
				$('.content .center ul').each(function() {
					var spec_id = $(this).attr('id');
					var spec_value_id = $(this).find('.on').attr('spec_value_id');
					g += spec_id + "_" + spec_value_id + "_";
				})
				//规格拼接得到 id
				var goods_attr_id = g.slice(0, g.length - 1);
				//判断商品类型不存在 库存为0
				if(goods_attr[goods_attr_id] == undefined) {
					$('#ku1').html('库存0件');
				} else {
					$('#top').html('<em>¥</em>' + goods_attr[goods_attr_id].price);
					$('#ku1').html('库存' + goods_attr[goods_attr_id].stock + '件');

					$('.buy').attr('sku_id', goods_attr[goods_attr_id].skuId)
				}

				//规格属性切换
				var guize1;
				var guize2;
				$('.center').on('tap', 'p', function() {
					var spec_value_id = $(this).attr('spec_value_id');
					var shuxing = $(this).attr('shuxing');
					if(shuxing == 'gg') {
						guize1 = spec_value_id;
						if(!guize2) {
							var index = $('.center .ys').attr('spec_value_id');
							guize2 = index;
						}
					} else {
						if(!guize1) {
							var index = $('.center .gg').attr('spec_value_id');
							guize1 = index;
						}
						guize2 = spec_value_id;
					}

					$(this).addClass('on').siblings().removeClass('on')
					//获取规格分类  规格参数
					var g = '';
					$('.content .center ul').each(function() {
						var spec_id = $(this).attr('id');
						var spec_value_id = $(this).find('.on').attr('spec_value_id');
						g += spec_id + "_" + spec_value_id + "_";
					})
					//规格拼接得到 id
					console.log(g.slice(0, g.length - 1))
					var goods_attr_id = g.slice(0, g.length - 1);

					console.log(goods_attr[goods_attr_id]);
					//判断商品类型不存在 库存为0
					if(goods_attr[goods_attr_id] == undefined) {
						$('#ku1').html('库存0件');
					} else {
						$('#top').html('<em>¥</em>' + goods_attr[goods_attr_id].price);
						$('#ku1').html('库存' + goods_attr[goods_attr_id].stock + '件');
						$('.buy').attr('sku_id', goods_attr[goods_attr_id].skuId)
					}
				})

				//商品评论
				var comment_list = res.data.goods_comment;
				$('.comment_total').html('商品评论(' + (res.data.goods_comment_number) + ')');
				var pinglun = '';
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
				var imgs = '';
				var description = res.data.description;
				for(var i in description){
					imgs+='<li>'+
							'    <img src="'+hetao.url2+ description[i] +'" style="margin-bottom:0px;">'+
							'</li>';
				}
				$('.shop_jieshao').html(imgs);

				//详情规格参数
				
				//详情  购买须知
				var need_rule = res.data.need_rule;
				$('.need').html(need_rule);
				
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

//本页面商品详情的数据更新
$('.like').on('tap', 'li', function() {
	goods_id = $(this).attr('goods_id');
	console.log(goods_id);
	lunbo_xx();
	getGuize();
	youhui();
//	setTimeout(function() {
//		jiage();
//	},500);
	
});

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
				console.log(res);
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
				console.log(res);
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

//立即兑换
$('.buy').click(function() {
    var num = $('.mui-input-numbox').val();
    //判断库存为0
    if($('#ku1').html() == '库存0件') {
        mui.toast('当前商品库存不足');
        return;
    }
    var sku_id = $(this).attr('sku_id')
    if(!user_id) {
        ca.prompt('您还没有登录');
        ca.newInterface({
            url: '../personal/login_xuanze.html',
            id: '../personal/login_xuanze.html'
        })
        return;
    } else {
        localStorage.setItem('num',num);
        ca.newInterface({
            url:'../car/write_order.html?goods_id='+goods_id+'&'+sku_id+'&'+'jifen',
            id:'../car/write_order.html'
        })
    }
		
});
var basepath = "http://192.168.1.100:8085/portal-bos";
var interval = 1000;
$("#gz-b").on('click', function() {
	$(".zz").show();
	$(".cjfx").show();
});
$(".cjgz-c").on('click', function() {
	$(".zz").hide();
	$(".cjfx").hide();
});
$("#look-gz").on('click', function() {
	$(".zz").show();
	$(".zpgz").show();
});
$(".cjgz-c").on('click', function() {
	$(".zz").hide();
	$(".zpgz").hide();
});
$("#zjjl").on('click', function() {
	$(".zz").show();
	$(".zj").show();
});
$(".cjgz-c").on('click', function() {
	$(".zz").hide();
	$(".zj").hide();
});
$(".cjgz-c").on('click', function() {
	$(".wcs").hide();
	$(".zz").hide();
});

function getQueryString(name) {
	var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
	var r = window.location.search.substr(1).match(reg);
	if(r != null) return unescape(r[2]);
	return null;
}
var login = getQueryString("login");
var loginName = getQueryString("loginName");
var isapp = getQueryString("isapp");
var memberId = getQueryString("memberId");
if(isapp == 1) {
	if(login == 1) {
		$("#tzbtn").attr("href", "cjq:terminal");
	} else {
		$("#tzbtn").attr('href', 'cjq:login');
	}
} else {
	$("#share").hide();
}
var speedi = 80;
var colee2 = document.getElementById("colee2");
var colee1 = document.getElementById("colee1");
var colee = document.getElementById("colee");
colee2.innerHTML = colee1.innerHTML;

function Marquee1() {
	if(colee2.offsetTop - colee.scrollTop <= 0) {
		colee.scrollTop -= colee1.offsetHeight;
	} else {
		colee.scrollTop++
	}
}
var MyMar1 = setInterval(Marquee1, speedi)
var coleer2 = document.getElementById("coleer2");
var coleer1 = document.getElementById("coleer1");
var coleer = document.getElementById("coleer");
coleer2.innerHTML = coleer1.innerHTML;

function Marqueer1() {
	if(coleer2.offsetTop - coleer.scrollTop <= 0) {
		coleer.scrollTop -= coleer1.offsetHeight;
	} else {
		coleer.scrollTop++
	}
}
var MyMarr1 = setInterval(Marqueer1, speedi)
//jp = {
//	'0': ["0", "0.1%加息券"],
//	'1': ["1", "0.2%加息券"],
//	'2': ["2", "0.3%加息券"],
//	'3': ["3", "谢谢参与222"],
//	'4': ["4", "Iphone8"],
//	'5': ["5", "0.5元"],
//	'6': ["6", "0.1元"],
//	'7': ["7", "10元"],
//};
var prize;
var arr=[];
	function jindan(){
		ca.get({
			url:hetao.url+'Integral/gameRotary',
			succFn:function(data){
				var res=JSON.parse(data);
				console.log(res);
				var str='';
				if(res.status==1){
					jindan_index=0;
					prize=res.data.prize;
					console.log(prize);
					
					for(var i in prize){
						console.log(prize[i].rate);
						if(prize[i].rate>86){
							arr.push(parseInt(i));
						}	
					}
					console.log(arr);
					$('.jindan h4').html(res.data.name);
					$('.jindan p').html(res.data.title);
				}
			}
		})
	}
	jindan();
	
	function drawRouletteWheel() {    
		  var canvas = document.getElementById("wheelcanvas");    
		  if (canvas.getContext) {
			  //根据奖品个数计算圆周角度
			  var arc = Math.PI / (turnplate.restaraunts.length/2);
			  var ctx = canvas.getContext("2d");
			  //在给定矩形内清空一个矩形
			  ctx.clearRect(0,0,422,422);
			  //strokeStyle 属性设置或返回用于笔触的颜色、渐变或模式  
			  ctx.strokeStyle = "#FFBE04";
			  //font 属性设置或返回画布上文本内容的当前字体属性
			  ctx.font = '16px Microsoft YaHei';      
			  for(var i = 0; i < turnplate.restaraunts.length; i++) {       
				  var angle = turnplate.startAngle + i * arc;
				  ctx.fillStyle = turnplate.colors[i];
				  ctx.beginPath();
				  //arc(x,y,r,起始角,结束角,绘制方向) 方法创建弧/曲线（用于创建圆或部分圆）    
				  ctx.arc(211, 211, turnplate.outsideRadius, angle, angle + arc, false);    
				  ctx.arc(211, 211, turnplate.insideRadius, angle + arc, angle, true);
				  ctx.stroke();  
				  ctx.fill();
				  //锁画布(为了保存之前的画布状态)
				  ctx.save();   
				  
				  //----绘制奖品开始----
				  //ctx.fillStyle = "#E5302F";
				  ctx.fillStyle = "#fff";
				  var text = turnplate.restaraunts[i];
				  var line_height = 17;
				  //translate方法重新映射画布上的 (0,0) 位置
				  ctx.translate(211 + Math.cos(angle + arc / 2) * turnplate.textRadius, 211 + Math.sin(angle + arc / 2) * turnplate.textRadius);
				  
				  //rotate方法旋转当前的绘图
				  ctx.rotate(angle + arc / 2 + Math.PI / 2);
				  
				  /** 下面代码根据奖品类型、奖品名称长度渲染不同效果，如字体、颜色、图片效果。(具体根据实际情况改变) **/
				  if(text.indexOf("M")>0){
					  var texts = text.split("M"); 
					  for(var j = 0; j<texts.length; j++){
						  ctx.font = j == 0?'bold 20px Microsoft YaHei':'16px Microsoft YaHei';
						  if(j == 0){
							  ctx.fillText(texts[j]+"M", -ctx.measureText(texts[j]+"M").width / 2, j * line_height);
						  }else{
							  ctx.fillText(texts[j], -ctx.measureText(texts[j]).width / 2, j * line_height);
						  }
					  }
					  //奖品名称长度超过一定范围 
				}else if(text.indexOf("M") == -1 && text.length>6){
					  text = text.substring(0,6)+"||"+text.substring(6);
					  var texts = text.split("||");
					  for(var j = 0; j<texts.length; j++){
						  ctx.fillText(texts[j], -ctx.measureText(texts[j]).width / 2, j * line_height);
					  }
				}else{
					//在画布上绘制填色的文本。文本的默认颜色是黑色
					  //measureText()方法返回包含一个对象，该对象包含以像素计的指定字体宽度
					  ctx.fillText(text, -ctx.measureText(text).width / 2, 0);
				  }
				  
				  //添加对应图标
				  if(text.indexOf("闪币")>0){
					  var img= document.getElementById("shan-img");
					  img.onload=function(){  
						  ctx.drawImage(img,-15,10);      
					  }; 
					  ctx.drawImage(img,-15,10);  
				  }else if(text.indexOf("谢谢参与")>=0){
					  var img= document.getElementById("sorry-img");
					  img.onload=function(){  
						  ctx.drawImage(img,-15,10);      
					  };  
					  ctx.drawImage(img,-15,10);  
				  }
				  //把当前画布返回（调整）到上一个save()状态之前 
				  ctx.restore();
				  //----绘制奖品结束----
			  }     
		  } 
		}
	
	
	
	
	
	
	
	
	
$(function() {
	var $btn = $('.g-lottery-img');
//	var cishu = 2;
//	$('#cishu').html(cishu);
	var isture = 0;
	var clickfunc = function() {
		//var data = [0, 1, 2, 3, 4, 5,];
		//var data = [4];
		console.log(arr);
		var data = arr[Math.floor(Math.random() * arr.length)];
		console.log(data);
		//console.log(prize.length);
			switch(data) {
				case 0:
					rotateFunc(0, 25, prize[0].name,prize[0].gift_id,prize[0].is_gift,prize[0].id);
					break;
				case 1:
					rotateFunc(1, 70, prize[1].name,prize[1].gift_id,prize[1].is_gift,prize[1].id);
					break;
				case 2:
					rotateFunc(2, 115,prize[2].name,prize[2].gift_id,prize[2].is_gift,prize[2].id);
					break;
				case 3:
					rotateFunc(3, 160, prize[3].name,prize[3].gift_id,prize[3].is_gift,prize[3].id);
					break;
				case 4:
					rotateFunc(4, 203, prize[4].name,prize[4].gift_id,prize[4].is_gift,prize[4].id);
					break;
				case 5:
					rotateFunc(5, 245, prize[5].name,prize[5].gift_id,prize[5].is_gift,prize[5].id);
					break;
			}
		
		
	}
	$(".zhizhen").click(function() {
		var touzi = "没投资11";
		if(touzi == "没投资") {
			$(".zz").show();
			$(".today").show();
			$(".cjgz-c").on('click', function() {
				$(".zz").hide();
				$(".today").hide();
			});
			$(".ok-img").on('click', function() {
				$(".zz").hide();
				$(".today").hide();
			});
		} else {
			$(".zz").hide()
			$(".today").hide();
			if(isture) return;
			isture = true;
			ca.confirm({
				title:'提示',
				content:'是否参与大转盘游戏，消耗积分',
				callback:function(data){
					if(data){
						var obj={};
						obj.uid=user_id;
						obj.token=token;
						obj.type=1;
						console.log(obj);
						//clickfunc();
						//jindan();
						ca.get({
							url:hetao.url+'Integral/gamejudge',
							data:obj,
							succFn:function(data){
								var res=JSON.parse(data);
								console.log(res);
								if(res.status==1){
									clickfunc();
								}else{
									ca.prompt(res.msg);
								}
							}
						});
					}
				}
			});
		}
	});
	var rotateFunc = function(awards, angle, text,gift_id,is_gift,id) {
		isture = true;
		$btn.stopRotate();
		$btn.rotate({
			angle: 0,
			duration: 4000,
			animateTo: angle + 1440,
			callback: function() {
				isture = false;
				//alert(text);
//				console.log(text);
//				console.log(gift_id);
//				console.log(is_gift);
//				console.log(id);
				var obj={};
				obj.uid=user_id;
				obj.token=token;
				obj.type=1;
				obj.gift_id=gift_id;
				obj.is_gift=is_gift;
				console.log(obj)
				ca.get({
					url:hetao.url+'Integral/gameGift',
					data:obj,
					succFn:function(data){
						var res=JSON.parse(data);
						console.log(res)
						if(res.status==1){
							ca.prompt(text);
							people_xx();
						}
					}
				})
				$(".texts").html("恭喜您，已获得<br>" + text);
				$(".zz").show();
				$(".jl-tk").show();
				$(".cjgz-c").on('click', function() {
					$(".zz").hide();
					$(".jl-tk").hide();
				});
				$(".ok-img").on('click', function() {
					$(".zz").hide();
					$(".jl-tk").hide();
				});
			}
		});
	};
});
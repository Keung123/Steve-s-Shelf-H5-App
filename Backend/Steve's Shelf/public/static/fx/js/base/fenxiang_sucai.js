//素材分享
var m_id;
$('.find').on('tap', '.share', function() {
	
	hidden(); 
	m_id = $(this).parents('.find-list').attr('m_id');
	console.log(m_id);
	
	$('.zhedang_fenxiang').css('display','block');
});



$('.source_sc').on('tap','.share',function(){
	//$('.mui-content').css('padding','2%');
	scroll(); 
	m_id=$(this).parents('li').attr('m_id');
	console.log(m_id);
	$('.zhedang_fenxiang').css('display','block');
});




$('.quxiao_fengxiang').click(function(){
	$('.zhedang_fenxiang').css('display','none');
});
//分享
function plusReady(){
	tuijian = localStorage.getItem('tuijian')
	phone_tjr = localStorage.getItem('user_name')
	function updateSerivces(){
		plus.share.getServices(function(s){
			shares={};
			for(var i in s){
				var t=s[i];
				shares[t.id]=t;	
			}
		}, function(e){
			console.log('获取分享服务列表失败：'+e.message);
		});
	}
	
updateSerivces();
//	var img_pic;
	var share_weixin_2 = ca.className("weixin_sucai");	
	ca.click(share_weixin_2[0],function(){
		console.log(33333333);
		//img_pic=$('#fenxiang_pic').attr('src');
		var share = shares["weixin"];
		
		if (share.authenticated) {
			shareMessage_1(share,"WXSceneSession");
		} else {
			share.authorize(function() {
				shareMessage_1(share,"WXSceneSession");
			}, function(e) {
				console.log("认证授权失败：" + e.code + " - " + e.message);
			});
		}	
	});
	$('.fenxiang').on('tap','.qq_sucai',function(){
//	$('.qq_sucai').click(function(){
		console.log(32222222);
		//img_pic=$('#fenxiang_pic').attr('src');
		var share = shares["qq"];			
		if (share.authenticated) {
			shareMessage_1(share,'qq');
		} else {
			share.authorize(function() {
				shareMessage_1(share,'qq');
			}, function(e) {
				console.log("认证授权失败：" + e.code + " - " + e.message);
			});
		}		  
	});
	
//	$('.penyouquan').click(function(){
//		console.log(32222222);
//		//img_pic=$('#fenxiang_pic').attr('src');
//		var share = shares["weixin"];			
//		if (share.authenticated) {
//			shareMessage_1(share,'WXSceneTimeline');
//		} else {
//			share.authorize(function() {
//				shareMessage_1(share,'WXSceneTimeline');
//			}, function(e) {
//				console.log("认证授权失败：" + e.code + " - " + e.message);
//			});
//		}		  
//	});  
	  
	function shareMessage_1(share, ex){
		//var tuijian=localStorage.getItem('tuijian');
			var msg = {
				extra: {
					scene: ex
				}
			};
			msg.href = window.location.origin + "/erweima/xianshang/find-detail.html?m_id="+m_id;
			msg.title = '合生活，淘天下';
			msg.content = '合生活，淘天下';
			msg.thumbs =[window.location.origin + "/erweima/img/hetao.png"];
			//msg.pictures=img_pic;
			console.log(msg.content);
			console.log(msg.thumbs);
			console.log(msg.href);
			//console.log(msg.pictures);
			share.send(msg, function() {
				$('.zhezhao-fenxiang').css('display','none');
				console.log("分享到\"" + share.description + "\"成功！ ");
				
			}, function(e) {
				console.log("分享到\"" + share.description + "\"失败: " + e.code + " - " + e.message);
			});
	  }  
}  
if(window.plus){
	plusReady();
}else{
	document.addEventListener('plusready', plusReady, false);
}	
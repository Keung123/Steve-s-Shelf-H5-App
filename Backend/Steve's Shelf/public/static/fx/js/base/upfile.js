
//上传图片
(function(up){
	up.bts = function(fn){
		up.fn = fn;
		var bts = [{
			title: "拍照"
		}, {
			title: "本地图片"
		}];
		plus.nativeUI.actionSheet({
			cancel: "取消",
			buttons: bts
		},
		function(e) {
			var _s = ((e.index > 0) ? bts[e.index - 1].title : "取消");
			if(_s == '本地图片') {
				up.appendByGallery();
			} else if(_s == '拍照') {
				up.getImage();
			} 
		});
	}
	
	up.appendByGallery = function() {
		// 从相册中选择图片
//		console.log("从相册中选择图片:");
		plus.gallery.pick(function(path) {
			appendFile(path); //处理图片的地方       
		}, function(e) {
			alert("图片选择失败，请尝试换图片");
		}, {
			filter: "image"
		});
	}
	
	up.getImage = function() {
		var cmr = plus.camera.getCamera();
//		console.log(cmr);
		cmr.captureImage(function(p) {
			plus.io.resolveLocalFileSystemURL(p, function(entry) {
				var localurl = entry.toLocalURL(); //把拍照的目录路径，变成本地url路径，例如file:///........之类的。
				appendFile(localurl);
			});
		});
	}
	
	function appendFile(path) {
		var img = new Image();
		img.src = path; // 传过来的图片路径在这里用。
		img.onload = function() {
			var that = this;
			//生成比例 
			//        console.log(that.width,that.height);
			var w = that.width,
				h = that.height,
				scale = w / h;
			w = (w > 360) ? 360 : w; //480  你想压缩到多大，改这里
			h = w / scale;
			//生成canvas
			var canvas = document.createElement('canvas');
			var ctx = canvas.getContext('2d');
			$(canvas).attr({
				width: w,
				height: h
			});
			ctx.drawImage(that, 0, 0, w, h);
			var base64 = canvas.toDataURL('image/jpeg', 1 || 0.8); //1最清晰，越低越模糊。有一点不清楚这里明明设置的是jpeg。弹出 base64 开头的一段 data：image/png;却是png。哎开心就好，开心就好     
			f1 = base64; // 把base64数据丢过去，上传要用。
			up.fn(base64);
			//这里丢到img 的 src 里面就能看到效果了
//			console.log(new Date().getTime() + '转换成base64');
		}
	}
	
}(window.upfile = {}))

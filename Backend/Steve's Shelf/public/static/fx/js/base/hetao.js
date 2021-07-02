var hetao = {
	url: window.location.origin +　"/api/",
	url2:"",
	url3:　window.location.origin
};

mui.init({
    statusBarBackground:"#333333"
});


if(window.plus){
	plusReady(); 
}else{
	document.addEventListener("plusready",plusReady,false);
}
function plusReady(){
	if(plus.os.name=="iOS"){ 
		$('.mui-content').css({'height':'100%','overflow-y':'scroll'})
	}; 
}

function hidden(){
	if(window.plus){
		plusReady(); 
	}else{
		document.addEventListener("plusready",plusReady,false);
	}
	function plusReady(){
		if(plus.os.name=="iOS"){ 
			$('.mui-content').css({'height':'100%','overflow':'hidden'});
		}; 
	}
}

function scroll(){
	if(window.plus){
		plusReady(); 
	}else{
		document.addEventListener("plusready",plusReady,false);
	}
	function plusReady(){
		if(plus.os.name=="iOS"){ 
			$('.mui-content').css({'height':'100%','overflow-y':'scroll'})
		}; 
	}
}












var user_id=localStorage.getItem('user_id');
var client_id=localStorage.getItem('client_id');
alert(client_id+'型号');
function tongzhi(){
	var obj={};
	obj.uid=user_id;
	ca.get({
		url:hetao.url+'User/userCenters',
		data:obj,
		succFn:function(data){
			var res=JSON.parse(data);
			console.log(res);
			alert(res.data.client_id)
			if(res.status==1){
				alert(res.data.client_id+'型号')
				if(res.data.client_id==client_id){
					alert('等于')
				}else{
					alert('不等于')
				}
			}
			
		}
	});	
}
//tongzhi();
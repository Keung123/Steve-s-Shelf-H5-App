/**
 * @namespace 顶部导航按钮设置
 * @author 李凌云
 */
var TopAnch=function(){ 
  var conf={
    prevText:'返回',//左侧按钮文本  
    prevHref:null,//左侧按钮链接
    prevFun:function(){//左侧按钮绑定事件  
     back();
    },
    prevShow:true,//左侧按钮是否显示
    prevIcon:null,//左按钮icon
    nextText:'',//右侧按钮文本  
    nextHref:null,//右侧按钮链接
    nextFun:null,//右侧按钮绑定事件
    nextShow:false,//右侧按钮是否显示
    nextIcon:null,//右按钮icon
    title:'',//中间标题显示
    bottomNav:null,//底部导航
    headerSelect:null//头部下拉选择
  }
 
  var init=function(obj){       
    //setTimeout(function(){
        obj = $.extend(conf,obj);
        //如果有标题栏，则加载顶部条
        if(obj.title||obj.prevFun){
          document.title = obj.title;     
          var prev_class = obj.prevShow?'':'hide';
          var _topBar='<div id="header" class="header">\
                  <a id="btn_prev" href="javascript:viod(0);" class="'+prev_class+'"><i class="arrow-left arrow-size-16"></i><span id="left_text"></span></a>\
                  <a id="btn_next" href="javascript:viod(0);" class="hide" >'+obj.nextText+'</a>\
                  <h1 id="h_title">'+obj.title+'</h1>\
                </div>';          
        
          $("#content").before(_topBar);
          setTimeout(function(){          
            var $dom={    
              prev:$('#btn_prev'),
              next:$('#btn_next'),    
              title:$('#h_title')
            }
            //左右按钮显示        
            if(obj.prevShow===false){
              $dom.prev.hide(); 
            }else{
              $dom.prev.show(); 
            }
            if(obj.nextShow===false){
              $dom.next.hide(); 
            }else{
              $dom.next.show(); 
            } 
            if(obj.prevIcon){
              $dom.prev.show();
              $dom.prev.addClass('prev-icon-'+obj.prevIcon);
            }
            if(obj.nextIcon){
              $dom.next.show();
              $dom.next.addClass('next-icon-'+obj.nextIcon);
            }
            //事件绑定
            if(obj.prevHref){
              $dom.prev.attr('href',obj.prevHref).off();
              obj.prevFun==null;
            }else{
              $dom.prev.attr('href','javascript:void(0);')
            }
            if(obj.nextHref){
              $dom.next.attr('href',obj.nextHref).off();
              obj.nextFun==null;
            }else{
              $dom.next.attr('href','javascript:void(0);')
            }
    
            if(obj.prevFun){
             $dom.prev.off().get(0).onclick = obj.prevFun;
            }
            if(obj.nextFun){
              $dom.next.off().get(0).onclick = obj.nextFun;
            }   
          },250)  
        }
  }
  return {
    init:init
  }
}()
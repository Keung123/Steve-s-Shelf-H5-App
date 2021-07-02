
$(function(){
    if($(".menu_fenlei").length>0){
        var detailmenuh=$(".menu_fenlei").height()+40;
       $('.header-tab').on('tap', 'a', function() {
       
        	var i = $(this).index();
        		//alert(i);
        	console.log($($(this).attr("href")).offset().top-detailmenuh);
     		//$(this).addClass("active").siblings("a").removeClass("active");
            $('html,body').animate({
            	
                scrollTop: ($($(this).attr("href")).offset().top-detailmenuh)
            }, 500);
            return false;
        })
        var obj = document.getElementById("menu_fenlei"),eq=0;
        var top = getTop(obj);
        var navTar = $(".swiper-wrapper");
		$("body,html").scroll(function(){
            var bodyScrollTop = document.documentElement.scrollTop || document.body.scrollTop;
            navTar.find("a").removeClass("active");
            $(".list_content").each(function(i){
                var scrolltop=$(this).offset().top;
                if( scrolltop+$(this).height()-detailmenuh>0){
                    eq=i;
                    return false;
                }
            });
            navTar.find("a:eq("+eq+")").addClass("active");
        });
    }
});
function getTop(e){
	e = e||window.event;
    var offset = e.offsetTop;
    if(e.offsetParent != null) offset += getTop(e.offsetParent);
    return offset;
}

function aa(){
    console.log(111111111111)
    var navTar = $(".swiper-wrapper");
    var detailmenuh=$(".menu_fenlei").height();
    //console.log(detailmenuh+'detailmenuh');
    var bodyScrollTop = document.documentElement.scrollTop || document.body.scrollTop;
    //console.log(bodyScrollTop+' bodyScrollTop');
    navTar.find("a").removeClass("active");
    $(".list_content").each(function(i){
        var scrolltop=$(this).offset().top;
        //console.log(scrolltop+' scrolltop');
       // console.log($(this).height() +'qqqqqqqqqqq');
       // console.log(detailmenuh +'7777777777777');
        if(scrolltop+$(this).height()-detailmenuh>0){
            eq=i;
           // console.log(eq  +'wwwwwwwwww')
           // console.log(i   +'fffsdfsdfsd')

           // console.log(scrolltop+' scrolltop');
           // console.log($(this).height())
            return false;
        }else{
        	eq=parseInt(eq)+1;
           // console.log(eq  +'qqqqqqqqqq')
        	//navTar.find("a:eq("+eq+")").addClass("active");
        }
    });
     navTar.find("a:eq("+eq+")").addClass("active");
}

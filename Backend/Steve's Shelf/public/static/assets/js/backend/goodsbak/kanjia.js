define(['jquery', 'bootstrap', 'backend', 'table', 'form','upload'], function ($, undefined, Backend, Table, Form,Upload) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                    multi_url: multi_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'id',
                sortName: 'weigh',
                commonSearch:false,
				search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'goods_name', title: __('Goods Name'), align: 'left'},
                        {field: 'goods_price', title: __('price'), align: 'left'},
                        {field: 'end_price', title: __('end_price'), align: 'left'},
                        {field: 'join_number', title: __('join_number'), operate: false},
                        {field: 'picture', title: __('Image'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'status', title: __('Status'), operate: false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter:function(){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
                            return html.join(' ');
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            //获取二级分类
            $(document).on("change","#category",function(){
                var categoryid=$(this).val();
                if(!categoryid){
                    return false;
                }
                $.get(getSecondName,{
                    categoryid:categoryid
                    },function(res){
                          var html="<option value=''> </option>";
                    if (res.rows.length > 0) {
                        $.each(res.rows, function (index, value) {
                            html += "<option value='"+value.category_id+"'>"+value.category_name+"</option>";
                        });
                        $('#category_er').html(html);
                        $('#category_er').selectpicker('refresh');
                        $('#category_er').selectpicker('render');
                    }  else {
                        html= "<option value=''>暂无数据</option>";

                        $("#category_er").html(html);
                    }
                    },'json');
            });
            //获取品牌
            $(document).on("change","#category_er",function(){
            // $("#category").change(function(){
                var goryid=$(this).val();
                if(!goryid){
                    return false;
                }
                $.get(getGoodsName,{
                    goryid:goryid
                },function(res){
					// console.log(res);
                    var html="";
                    if(res.code=='0'){
						var html="<option value=''> "+res.msg+"</option>";
						 $("#category_er").html(html);
						 $('#category_er').selectpicker('refresh');
                         $('#category_er').selectpicker('render');
					}else  if (res.rows.length > 0) {
                        /*console.log(res.rows);*/
                        html="";
                        $.each(res.rows,function(index,value){
                            html = html+"<option data-price="+value.price+" data-stock="+value.stock+" data-num="+index+" value="+value.goods_id+" >"+value.goods_name+"</option>";
							 
                        }); 
						var num = $("#goods_name option:selected").attr('data-num');
						if(!num){
							num =0;
						}
						 if(res.rows[num].list.length > 0){
							var sku = "";
							var arr = res.rows[num].list;
							 // console.log(arr[index].sku_name);
							for(let index in arr) {
								 // console.log(arr);
								 sku = sku+"<div class='SKU_LIST'><span><label><input type='radio' name='row[sku_id]' data-stock="+arr[index].stock+" data-price="+arr[index].price+" value="+arr[index].sku_id+"";
								 if(res.rows.sku_id == arr[index].sku_id){
									  sku = sku+" checked";
								 }else if(index == 0){
									  sku = sku+" checked ";
								 }
								 sku = sku+">"+arr[index].sku_name+"</label></span></div>";   
							}
						}
                        var prices = res.rows[0].list[0].price;
                        $("#c-goods_price").val(prices);
                        var stock= res.rows[0].list[0].stock;

                        $("#c-goods_stock").val(stock);
                        $("#goods_name").html(html);
                        $("#goods_sku").html(sku);
                        var goodsname = res.rows[0].goods_name;
                        $("#goodsname").val(goodsname);
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#goods_name").html(html);
                    }
                },'json');
            });
            $(document).on("change","#goods_name",function(){
				 var goodsname = $("#goods_name option:selected").html();
				 var goods_id = $("#goods_name option:selected").attr('value');
					$.get(getSku,{
						goods_id:goods_id
					},function(res){
						// console.log(res);
						 var sku= "";
						 if(res.code=='0'){
						var html="<option value=''> "+res.msg+"</option>";
						 $("#category_er").html(html);
						 $('#category_er').selectpicker('refresh');
                         $('#category_er').selectpicker('render');
					 }else if (res.rows.length > 0) {
							 sku = "";
							   $.each(res.rows,function(index,value){
								 sku = sku+"<div class='SKU_LIST'><span><label><input type='radio' name='row[sku_id]' data-price="+value.price+"  data-stock="+value.stock+" value="+value.sku_id+"";
								 if(value.sku_id == res.id){
									  sku = sku+" checked";
								 }else if(index == 0){
									  sku = sku+" checked ";
								 }
								 sku = sku+">"+value.sku_name+"</label></span></div>";  
								   
							   });   
                                $("#goods_sku").html(sku);
                                $("#goodsname").val(goodsname);
                             var goods_price = $("input:radio[name='row[sku_id]']:checked").attr("data-price");
								$("#c-goods_price").val(goods_price);
                             var goods_stock = $("#goods_name option:selected").attr("data-stock");
                                $("#c-goods_stock").val(goods_stock);
						 } 
					},'json');
              
            });
            $(document).on("change","#goods_sku",function(){
				  var goods_price = $("input:radio[name='row[sku_id]']:checked").attr("data-price");
                    $("#c-goods_price").val(goods_price);
                  var goods_stock = $("input:radio[name='row[sku_id]']:checked").attr("data-stock");
                    $("#c-goods_stock").val(goods_stock);

			 });
            //进入页面默认触发一次
            // $("#goods_name").trigger('change');



        },
        edit: function () {
            Controller.api.bindevent();
            //获取二级分类
            $(document).on("change","#category",function(){
                var categoryid=$(this).val();
                if(!categoryid){
                    return false;
                }
                $.get(getSecondName,{
                    categoryid:categoryid
                },function(res){
                    var html="";
                    if(res.code=='0'){
						var html="<option value=''> "+res.msg+"</option>";
						 $("#category_er").html(html);
						 $('#category_er').selectpicker('refresh');
                         $('#category_er').selectpicker('render');
					}else if (res.rows.length > 0) {
                        $.each(res.rows, function (index, value) {
                            html += "<option value='"+value.category_id+"'>"+value.category_name+"</option>";
                        });
                        // console.log(html,11);
                        $('#category_er').html(html);
                        $('#category_er').selectpicker('refresh');
                        $('#category_er').selectpicker('render');
                    }  else {
                        html= "<option value=''>暂无数据</option>";

                        $("#category_er").html(html);
                    }
                },'json');
            });
            //获取品牌
            $(document).on("change","#category_er",function(){
                // $("#category").change(function(){
                var goryid=$(this).val();
                if(!goryid){
                    return false;
                }
                $.get(getGoodsName,{
                    goryid:goryid
                },function(res){
                    // console.log(res);
                    var html="";
                  if(res.code=='0'){
						var html="<option value=''> "+res.msg+"</option>";
						 $("#category_er").html(html);
						 $('#category_er').selectpicker('refresh');
                         $('#category_er').selectpicker('render');
					}else  if (res.rows.length > 0) {
                        // console.log(res.rows);
                        html="";
                        $.each(res.rows,function(index,value){
                            html = html+"<option data-price="+value.price+" data-stock="+value.stock+"  data-num="+index+" value="+value.goods_id+" >"+value.goods_name+"</option>";

                        });
                        var num = $("#goods_name option:selected").attr('data-num');
                        if(!num){
                            num =0;
                        }
                        if(res.rows[num].list.length > 0){
                            var sku = "";
                            var arr = res.rows[num].list;
                            // console.log(arr[index].sku_name);
                            for(let index in arr) {
                                console.log(arr);
                                sku = sku+"<div class='SKU_LIST'><span><label><input type='radio' name='row[sku_id]' data-price="+arr[index].price+" value="+arr[index].sku_id+"";
                                if(res.rows.sku_id == arr[index].sku_id){
                                    sku = sku+" checked";
                                }else if(index == 0){
                                    sku = sku+" checked ";
                                }
                                sku = sku+">"+arr[index].sku_name+"</label></span></div>";
                            };
                        }
                        var prices = res.rows[0].list[0].price;
                        $("#c-goods_price").val(prices);
                        var stock = res.rows[0].list[0].stock;
                        $("#c-goods_price").val(stock);
                        $("#goods_name").html(html);
                        $("#goods_sku").html(sku);
                        var goodsname = res.rows[0].goods_name;
                        $("#goodsname").val(goodsname);
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#goods_name").html(html);
                    }
                },'json');
            });
            $(document).on("change","#goods_name",function(){
                var goodsname = $("#goods_name option:selected").html();
                var goods_id = $("#goods_name option:selected").attr('value');
                $.get(getSku,{
                    goods_id:goods_id
                },function(res){
                    console.log(res.id);
                    var sku= "";
                   if(res.code=='0'){
						var html="<option value=''> "+res.msg+"</option>";
						 $("#category_er").html(html);
						 $('#category_er').selectpicker('refresh');
                         $('#category_er').selectpicker('render');
					}else  if (res.rows.length > 0) {
                        sku = "";
                        $.each(res.rows,function(index,value){
                            sku = sku+"<div class='SKU_LIST'><span><label><input type='radio' name='row[sku_id]' data-price="+value.price+" value="+value.sku_id+"";
                            if(value.sku_id == res.id){
                                sku = sku+" checked";
                            }else if(index == 0){
                                sku = sku+" checked ";
                            }
                            sku = sku+">"+value.sku_name+"</label></span></div>";

                        });
                            $("#goods_sku").html(sku);
                            $("#goodsname").val(goodsname);
                        var goods_price = $("input:radio[name='row[sku_id]']:checked").attr("data-price");
                            $("#c-goods_price").val(goods_price);
                        var goods_stock = $("#goods_name option:selected").attr("data-stock");
                            $("#c-goods_stock").val(goods_stock);
                    }
                },'json');

            });
            $(document).on("change","#goods_sku",function(){
                var goods_price = $("input:radio[name='row[sku_id]']:checked").attr("data-price");
                    $("#c-goods_price").val(goods_price);
                var goods_stock = $("#goods_name option:selected").attr("data-stock");
                    $("#c-goods_stock").val(goods_stock);

            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
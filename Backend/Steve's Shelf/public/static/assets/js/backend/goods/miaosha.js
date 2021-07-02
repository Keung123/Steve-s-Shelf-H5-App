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
                        {field: 'limit_price', title: __('limit_price'), align: 'left'},
                        {field: 'price', title: __('price'), align: 'left'},
                        {field: 'time', title: "秒杀时段",perate: false},
                        {field: 'goods_number', title: __('num'), operate: false},
                        {field: 'picture', title: __('Image'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'is_end', title: __('Status'), operate: false},
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
                    var html="";
                    var sku='';
                    if(res.code=='0'){
						var html="<option value=''> "+res.msg+"</option>";
						 $("#category_er").html(html);
						 $('#category_er').selectpicker('refresh');
                         $('#category_er').selectpicker('render');
					}else if (res.rows.length > 0) {
                        console.log(res.rows);
                        html="";
                        $.each(res.rows,function(index,value){
                            html = html+"<option data-price ="+value.price+" data-stock ="+value.stock+" value="+value.goods_id+" data-sku="+JSON.stringify(value.goods_sku)+">"+value.goods_name+"</option>";
                        });
                        var skus = res.rows[0].goods_sku;
                        $.each(skus,function(index,value){
                            sku = sku+'<label><input  class="sku_list"  type="radio" name="row[sku_id]" value="'+value.sku_id+'" data-price ="'+value.price+'"/>'+value.sku_name+'<label/>';
                        });
                        $("#sku").html(sku);
                        var prices = res.rows[0].price;

                        $("#c-goods_price").val(prices);
                        $("#goods_name").html(html);
                        var goodsname = res.rows[0].goods_name;
                        $("#goodsname").val(goodsname);
                        var stock= res.rows[0].stock;
                        $("#c-goods_stock").val(stock);
                    } else {
                        html= "<option value=''>暂无数据</option>";
                        sku ="<option value=''>暂无数据</option>";
                        $("#goods_name").html(html);
                        $("#sku").html(html);
                    }
                },'json');
            });
            $(document).on("change","#goods_name",function(){
                var goods_price = $("#goods_name option:selected").attr("data-price");
                $("#c-goods_price").val(goods_price);
                var goods_stock = $("#goods_name option:selected").attr("data-stock");
                $("#c-goods_stock").val(goods_stock);
                var goodsname = $("#goods_name option:selected").html();
                $("#goodsname").val(goodsname);

                var skuss =  JSON.parse($("#goods_name option:selected").attr("data-sku"));
                var sku='';
                $.each(skuss,function(index,value){
                    sku = sku+'<label><input class="sku_list" type="radio" name="row[sku_id]" value="'+value.sku_id+'"  data-price ="'+value.price+'"/>'+value.sku_name+'<label/>';
                });
                $("#sku").html(sku);
            });
            $(document).on("change",".sku_list",function(){
                var price = $(this).attr('data-price');
                $("#c-goods_price").val(price);
            });


            //进入页面默认触发一次
            $("#attribute_id").trigger('change');



        },
        edit: function () {
            Controller.api.bindevent();
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
                    var html="";
                    if(res.code=='0'){
						var html="<option value=''> "+res.msg+"</option>";
						 $("#category_er").html(html);
						 $('#category_er').selectpicker('refresh');
                         $('#category_er').selectpicker('render');
					}else if (res.rows.length > 0) {
                        console.log(res.rows);
                        html="";
                        $.each(res.rows,function(index,value){
                            html = html+"<option data-price="+value.price+" data-stock="+value.stock+" value="+value.goods_id+" >"+value.goods_name+"</option>";
                        });
                        var prices = res.rows[0].price;

                        $("#c-goods_price").val(prices);
                        $("#goods_name").html(html);
                        var goodsname = res.rows[0].goods_name;
                        $("#goodsname").val(goodsname);
                        var stock= res.rows[0].stock;
                        $("#c-goods_stock").val(stock);
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#goods_name").html(html);
                    }
                },'json');
            });
            $(document).on("change","#goods_name",function(){
                var goods_price = $("#goods_name option:selected").attr("data-price");
                $("#c-goods_price").val(goods_price);
                var goods_stock = $("#goods_name option:selected").attr("data-stock");
                $("#c-goods_stock").val(goods_stock);
                var goodsname = $("#goods_name option:selected").html();
                $("#goodsname").val(goodsname);

                if(!goodsname){
                    return false;
                }
                $.get(getGoodsStock,{goodsname:goodsname},function(res){
                    if(res.rows.length >0){
                        var stock= res.rows[0].stock;
                        $("#c-goods_stock").val(stock);
                        var prices = res.rows[0].price;
                        $("#c-goods_price").val(prices);
                    }
                },'json');
            });
            //进入页面默认触发一次
            $("#attribute_id").trigger('change');
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
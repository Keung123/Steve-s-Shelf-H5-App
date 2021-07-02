define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                    multi_url: multi_url,
                }
            });
            // alert($.fn.bootstrapTable.defaults.extend.index_url);
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                // url: '/admin/user/index.html',
				escape: false,
				pk: 'm_id',
                sortName: 'weigh',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {field: 'state', checkbox: true },
                        {field: 'm_id', title: 'ID'},
                        {field: 'user_avat', title: __('Avatar'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'user_name', title: __('Username'),operate: false},
                         
                          
                        {field: 'goods_name', title: "商品名称"},
                        {field: 'mate_content', title: "素材内容",width:350},
                        {field: 'mate_status', title: "是否加精"},
                        {field: 'mate_zhiding', title: "是否置顶"},
                        {field: 'mate_add_time', title: "添加时间"},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function (value, row, index) {
                                var html = [];
									html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-showDetails btn-xs"><i class="fa fa-eye"></i></a>');
									html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
									html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
									if(row.mate_zhiding=='是'){
											html.push('<a href="javascript:;" class="btn btn-success btn-invoice btn-xs"><i class="fa fa-sort"></i>取消置顶</a>');
									}else{
											html.push('<a href="javascript:;" class="btn btn-success btn-invoice btn-xs"><i class="fa fa-sort"></i>置顶</a>');
									}
								
								return html.join(' ');
                            }}
                    ]
                ],
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            //判断用户名是否存在
            $(document).on("click","#check",function(){
                var name= $("#c-name").val();

                $.get(check_name,{name:name},function(res){
                    if(res ==0){
                        $('#waring').text('用户名不存在,请重新输入');
                        $('#waring').css("color","red");
                        return false;
                    }else{
                        console.log(res.rows['user_id']);
                        var user_id=res.rows['user_id']

                        $("#name-hidden").val(user_id);
                         //alert($("#name-hidden").val());
                        $("#waring").text('用户名存在');
                        $('#waring').css("color","green");
                    }
                });
            });
            $(document).on("mouseover","#c-name",function(){
                $("#waring").text('');
            });
            //获取一级分类
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
            //获取二级分类
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
                    if (res.rows.length > 0) {
                        console.log(res.rows);
                        html="";
                        $.each(res.rows,function(index,value){
                            html = html+"<option data-price="+value.price+" data-stock="+value.stock+" value="+value.goods_id+" >"+value.goods_name+"</option>";
                        });
                        var prices = res.rows[0].price;
                        $("#c-goods_price").val(prices);
                        var stock = res.rows[0].stock;
                        $("#c-goods_stock").val(stock);
                        $("#goods_name").html(html);
                        var goodsname = res.rows[0].goods_name;
                        $("#goodsname").val(goodsname);
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#goods_name").html(html);
                    }
                },'json')
            });
            //获取商品名称
            $(document).on("change","#goods_name",function(){
                var goods_price = $("#goods_name option:selected").attr("data-price");
                $("#c-goods_price").val(goods_price);
                var goods_stock = $("#goods_name option:selected").attr("data-stock");
                $("#c-goods_stock").val(goods_stock);
                var goodsname = $("#goods_name option:selected").html();
                $("#goodsname").val(goodsname);
            });
        },
        edit: function () {
            Controller.api.bindevent();
        },
		api: {
			  bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
			events: {//绑定事件的方法
                operate: $.extend({
					'click .btn-invoice':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __(' 是否改变置顶状态?'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                   $.get(zhiding_url,{
											id:row['m_id'],
										  uid:row['m_uid'],
									},function(res){},'json')
									
                                    Layer.close(index);
									window.location.href = url;
                                }
                        );
                    },
					'click .btn-showDetails': function (e, value, row, index) {
                       Backend.api.open(show_url+'?ids='+ row['m_id']+'&uid='+row['m_uid'], __('详情'));
                    },

                }, Table.api.events.operate)
            }         
			 
		}
    };
    return Controller;
});
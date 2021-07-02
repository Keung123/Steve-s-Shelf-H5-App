define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                    invoice_url: invoice_url,
                    multi_url: multi_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'order_id',
                sortName: 'weigh',
                // searchText: '订单编号',
				liveSearchPlaceholder:'订单编号', 
				trimOnSearch:true,
				commonSearch:false,
				search:false,				
                columns: [
                    [
                        {checkbox: true},
                        {field: 'order_no', title: "订单编号"},
                        // {field: 'supplier_name', title: __('所属供应商')},
                        //{field: 'og_goods_price', title:__('商品单价')},
                        {field: 'order_pay_points', title: __('积分抵扣金额')},
                        {field: 'shop_name', title: __('店铺名称')},
                        {field: 'addr_receiver', title: __('link name')},
                        {field: 'addr_phone', title: __('mobile')},
                        {field: 'order_create_time', title: __('Createtime')},
                        {field: 'order_remark', title: __('order_remark')},
                        {field: 'order_type', title: __('订单类型')},
                        {field: 'status_names', title: __('status')},
                        {field: 'offpay', title: __('是否线下支付')},
                        {field: 'pick_status', title: __('自提状态')},
                        {field: 'team_status', title: __('拼团状态')},
                        {field: 'order_pay_code', title: __('支付方式')},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value, row, index){
                            var detail="";
							 detail+='<a class="btn btn-xs btn-success btn-particulars" data-id="'+row.og_id+'"><i class="fa fa-eye"></i>订单</a> ';
							 detail+='<a class="btn btn-xs btn-success btn-href" target="view_window" href="'+printer_url+'?order_id='+row.order_id+'">打印</a> ';
                            if(row.order_status==0){
                                //待付款
                                detail+='<a class="btn btn-xs btn-danger btn-close">删除</a> ';
                                detail+='<a class="btn btn-xs btn-success btn-pay">修改金额</a> ';
                            }else if(row.order_status==1){
                                //待发货
								if(row.after_state_status == 0){
								    if (row.sh_status == 0) {
                                        detail+='<a class="btn btn-xs btn-success btn-post" data-id="'+row.og_id+'">发货</a> ';
                                    } else {
                                        detail+='售后中';
                                    }
								}
                                
								detail+='<a class="btn btn-xs btn-success btn-editaddr" data-id="'+row.order_id+'">修改地址</a> ';                                
                            }else if(row.order_status==2 || row.order_status == 4){
                                //收货 查看物流
                               /* detail+='<a class="btn btn-xs btn-success btn-logistic" data-id="'+row.og_id+'">查看物流</a> ';*/
                            }else if(row.need_invoice == 1&& row.order_status == 4){
								 //发票展示
                                /*detail+='<a class="btn btn-xs btn-success btn-invoice" data-id="'+row.order_id+'">发票详情</a> ';    */
							}
						$price_html = "";
						$price_html +='<li class="price">总计销售额：'+row.total_price+'</li>';
						$price_html +='<li class="price">当前页销售额：'+row.page_price+'</li>';
						  $("#sku").html($price_html);     
                            return detail;                            
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
			 //获取品牌
            $(document).on("change","#pro_name",function(){
            // $("#pro_name").change(function(){
                var parent_id=$(this).val();
                if(!parent_id){
                    return false;
                }
                $.get(addr_url,{
                    parent_id:parent_id
                },function(res){
					
                    var html="";
                    if (res.length > 0) {
                        $.each(res,function(index,value){
                            html = html+"<option value="+value.region_id+" >"+value.region_name+"</option>";
                        });
						
                        $("#city_name").html(html);
						$.get(addr_url,{
							parent_id:res[0].region_id
						},function(res){
							var html="";
							if (res.length > 0) {
								$.each(res,function(index,value){
									html = html+"<option value="+value.region_id+" >"+value.region_name+"</option>";
								});
								 
								$("#area").html(html);
							} else {
								html= "<option value=''>暂无数据</option>";

								$("#area").html(html);
							}
						},'json');
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#city_name").html(html);
                    }
                },'json');
            });
			$(document).on("change","#city_name",function(){
            // $("#pro_name").change(function(){
                var parent_id=$(this).val();
                if(!parent_id){
                    return false;
                }
                $.get(addr_url,{
                    parent_id:parent_id
                },function(res){
                    var html="";
                    if (res.length > 0) {
                        $.each(res,function(index,value){
                            html = html+"<option value="+value.region_id+" >"+value.region_name+"</option>";
                        });
						 
                        $("#area").html(html);
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#area").html(html);
                    }
                },'json')
            });
        },
        edit: function () {
            Controller.api.bindevent();
        },
		
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {//渲染的方法
                operate: function (value, row, index) {
                    //返回字符串加上Table.api.formatter.operate的结果
                    //默认需要按需显示排序/编辑/删除按钮,则需要在Table.api.formatter.operate将table传入
                    //传入了table以后如果edit_url为空则不显示编辑按钮,如果del_url为空则不显显删除按钮
                    return '<a class="btn btn-info btn-xs btn-detail"><i class="fa fa-list"></i> ' + __('Detail') + '</a> '
                            + Table.api.formatter.operate(value, row, index, $("#table"));
                },
            },  
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-detail': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open('example/bootstraptable/detail/ids/' + row['id'], __('Detail'));
                    }, 
					'click .btn-post': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open(edit_url +'?order_id='+ row['order_id']+'&og_supplier_id='+row['og_supplier_id']+'&og_id='+row['og_id'], __('发货'));
                    }, 
					'click .btn-pay': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open(edit_pay_url +'?order_id='+ row['order_id'], __('修改支付金额'));
                    },
					'click .btn-editaddr': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open(editaddr_url +'?order_id='+ row['order_id']+'&og_supplier_id='+row['og_supplier_id']+'&og_id='+row['og_id'], __('修改收货地址'));
                    },
					 
					'click .btn-invoice': function (e, value, row, index) {
                       Backend.api.open(invoice_url +'?order_id='+ row['order_id'], __('发票详情'));
                    },
					'click .btn-logistic': function (e, value, row, index) {
                       Backend.api.open(logistic_url +'?order_id='+ row['order_id']+'&og_supplier_id='+row['og_supplier_id']+'&og_id='+row['og_id'], __('物流详情'));
                    },
					'click .btn-particulars': function (e, value, row, index) {
                       Backend.api.open(particulars_url +'?order_id='+ row['order_id']+'&og_supplier_id='+row['og_supplier_id']+'&og_id='+row['og_id'], __('订单详情'));
                    },
                    'click .btn-close':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __('确定要关闭该订单吗?'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                    Table.api.multi("del", row['order_id'], $("#table"), that);
                                    Layer.close(index);
                                }
                        );
                    }
                }, Table.api.events.operate)
            }                      
        }
    };
    return Controller;
});
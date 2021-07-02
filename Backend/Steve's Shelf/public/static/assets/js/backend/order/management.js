define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    show_url: show_url,
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
                pk: 'og_id',
                sortName: 'weigh',
                // searchText: '订单编号',
				liveSearchPlaceholder:'订单编号', 
				trimOnSearch:true,
				// onColumnSearch:'订单编号',
				 search:false,
				commonSearch:false,		
                columns: [
                    [
                        {checkbox: true},
                        {field: 'audit_no', title: "售后单号"},
                        // {field: 'og_goods_price', title:__('商品单价')},
                        {field: 'og_goods_pay_price', title:__('订单支付价格')},
                        {field: 'addr_receiver', title: __('link name')},
                        {field: 'addr_phone', title: __('mobile')},
                        {field: 'create_time', title: __('订单时间')},
                        // {field: 'order_remark', title: __('order_remark')},
                        {field: 'order_type', title: __('订单类型')},
                        // {field: 'order_return_no', title: __('退货单号')},
                        {field: 'apply_time', title: __('申请时间')},
                        {field: 'post_status', title: __('订单状态')},
                        {field: 'status_names', title: __('申请类型')},
                        // {field: 'supplier_title', title: __('供应商')},
                        {field: 'operate', title: __('Operate'),events: Controller.api.events.operate, formatter: function(value, row, index){
                            var detail="";
							 detail+='<a class="btn btn-xs btn-danger btn-close" data-id="'+row.order_id+'"><i class="fa fa-eye"></i>删除</a> ';  
							 if(row['state'] == 1){
								 	detail+='<a href="javascript:;" data-width="80%" class="btn btn-success btn-showone btn-xs"><i class="fa fa-eye"></i>审核详情</a> ';  
							 }else{
								 	detail+='<a href="javascript:;" data-width="80%" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i>审核</a> ';  
							 }
						
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
					 
					'click .btn-invoice': function (e, value, row, index) {
                       Backend.api.open('/admin/order/invoice' +'?order_id='+ row['order_id'], __('发票详情'));
                    }, 
					'click .btn-showone': function (e, value, row, index) {
                       Backend.api.open(show_url+'?ids='+ row['og_id'], __('审核详情'));
                    },
					'click .btn-logistic': function (e, value, row, index) {
                       Backend.api.open('/admin/order/logistic' +'?order_id='+ row['order_id'], __('物流详情'));
                    }
					,
					'click .btn-particulars': function (e, value, row, index) {
                       Backend.api.open('/admin/order/particulars' +'?order_id='+ row['order_id'], __('订单详情'));
                    },
                    'click .btn-close':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __('确定要删除该订单吗?'),
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
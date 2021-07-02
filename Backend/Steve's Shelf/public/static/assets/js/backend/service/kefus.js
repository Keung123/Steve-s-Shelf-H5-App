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
                    kefu_url: kefu_url,
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
				pk: 'kefu_id',
                sortName: 'weigh',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {field: 'state', checkbox: true },
                        {field: 'kefu_id', title: 'ID'},
                        {field: 'kefu_avat', title: __('Avatar'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'kefu_name', title: __('Username'),operate: 'LIKE %...%', placeholder: '用户名，模糊搜索'},
                        {field: 'status', title: __('Status')},

                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function (value, row, index) {
                                var html = [];
								html.push('<a href="'+kefu_url+'?kefu_id='+row.kefu_id+'" title="客服聊天消息" class="btn btn-success btn-addtabs btn-xs">客服聊天消息 </a>');  
								  html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
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
				
                   'click .btn-invoice': function (e, value, row, index) {
					   	console.log('aaaaaaa');
					   console.log('aaaa');
                       Backend.api.open('/admin/order/invoice' +'?order_id='+ row['order_id'], __('发票详情'));
                    }
                }, Table.api.events.operate)
            }         
			 
		}
    };
    return Controller;
});
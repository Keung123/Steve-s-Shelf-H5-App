define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: url,
                }
            });
            // alert($.fn.bootstrapTable.defaults.extend.index_url);
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                // url: '/admin/user/index.html',
				escape: false,
				pk: 'id',
                sortName: 'id',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {field: 'id', title: 'ID'},
                        {field: 'uid', title: '管理员ID'},
                        {field: 'admin_name', title: '账号'},
                        {field: 'nickname', title: '名称'},
                        {field: 'ip_address', title: '地址'},
                        {field: 'create_at', title: '时间'},                       
                    ]
                ],
                commonSearch:false
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
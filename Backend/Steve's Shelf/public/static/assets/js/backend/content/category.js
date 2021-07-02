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
                    dragsort_url:"",
                    multi_url: 'data/multi.json',
                    table: 'category',
                }
            });

            var table = $("#table");

            // 初始化表格
				table.bootstrapTable('destroy'); 
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'category_id',
                sortName: 'weigh',
				search:false,
				commonSearch:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'category_id', title: __('Id')},
                        {field: 'category_name', title: __('Name'), align: 'left'},
                        {field: 'title', title: __('标题'), align: 'left'},
                        {field: 'img', title: __('Image'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value, row, index){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
							/*html.push('<a href="javascript:;" class="btn btn-success btn-invoice  btn-xs"><i class="fa fa-eye"></i>分类内容</a>');*/
                            return html.join(' ');
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            // Controller.api.bindevent();
            Form.api.bindevent($("form[role=form]"));
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
					   window.location.href = (list_url+'?id='+ row['category_id']);
					},
 
				}, Table.api.events.operate)
			}       
        }
    };
    return Controller;
});
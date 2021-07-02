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
                    multi_url: multi_url
                }
            });

            var table = $("#table");

            // 初始化表格
			table.bootstrapTable('destroy'); 
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'content_id',
                sortName: 'weigh',
				search:false,
				commonSearch:false,
				contentType: "application/x-www-form-urlencoded",
                columns: [
                    [
                        {checkbox: true},
                        {field: 'content_id', title: __('Id')},
                        {field: 'title', title: __('title'), align: 'left'},
                        {field: 'create_time', title: __('createtime'), operate: false},
                        {field: 'update_time', title: __('updatetime'), operate: false},
                        {field: 'weigh', title: __('Weigh'), operate: false},
						{field: 'category_name', title: __('所属分类'), operate: false},
                        {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function(value,row,index){
                            var html = [];
							html.push('<a href="javascript:;" data-width="80%" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>'); /*  html.push('<a href="'+edit_url+'?ids='+row.content_id+'" data-width="80%" class="btn btn-success  btn-xs"><i class="fa fa-pencil"></i></a>'); */
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
            Form.api.bindevent($("form[role=form]"));
            // Controller.api.bindevent();
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"));
            // Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
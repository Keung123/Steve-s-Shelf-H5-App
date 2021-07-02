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
				pk: 'kefu_id',
                sortName: 'weigh',
                columns: [
                    [
                        {field: 'state', checkbox: true },
                        {field: 'kefu_id', title: 'ID'},
                        {field: 'kefu_avat', title: __('Avatar'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'kefu_name', title: __('Username'),operate: 'LIKE %...%', placeholder: '用户名，模糊搜索'},
                        {field: 'status', title: __('Status')},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter:function(value, row, index){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>'); 
                            return html.join(' ');
                        }}
                    ]
                ],
                commonSearch:false
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"));
        }
    };
    return Controller;
});
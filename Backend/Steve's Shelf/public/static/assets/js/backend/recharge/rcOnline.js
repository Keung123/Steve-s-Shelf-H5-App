define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        rcOnline: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'ro_id',
                sortName: '',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'ro_price', title: '充值金额'},
                        {field: 'ro_points', title: '赠送积分'},
                        {field: 'ro_add_time', title: '增加时间', operate: false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function(){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="80%" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
                            return html.join(' ');
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        roAdd : function(){
            Form.api.bindevent($("form[role=form]"));
        },
        roEdit : function(){
            Form.api.bindevent($("form[role=form]"));            
        },
        roDel: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
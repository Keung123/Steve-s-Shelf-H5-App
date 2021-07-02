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
                    multi_url: 'data/multi.json',
                }
            });
            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'admin_id',
                sortName: 'admin_id',
				search:false,
				commonSearch:false,
                columns: [
                    [
                        {field: 'state', checkbox: true, },
                        {field: 'admin_id', title: 'ID'},
                        {field: 'group_name', title: '所属角色组'},
                        {field: 'avatar', title: __('Avatar'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'admin_name', title: __('Username'),operate: false},
                        {field: 'mobile', title: __('Mobile')},
                        {field: 'nickname', title: __('Nickname')},
                        {field: 'qq', title: __('QQ')},   
                        {field: 'email', title: __('Email')},                      
                        {field: 'status', title: __("Status"),operate:false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function (value, row, index) {
                            if(row.admin_id!=1){
                                return Table.api.formatter.operate.call(this, value, row, index, table);
                            }
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
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
                columns: [
                    [
                        {field: 'id', title: 'ID'},
                        {field: 'user', title: __('Username'),formatter:function(value, row, index){
                            return value['user_name']||'-';
                        }},
                        {field: 'user', title: __('Mobile'),formatter:function(value, row, index){
                            return value['mobile'];
                        }},    
                        {field: 'num', title: __("point change")},                    
                        {field: 'type_name', title: __("point type")},
                        {field: 'create_time', title: __("times")}
                    ]
                ],
                commonSearch:false,
                search:false
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
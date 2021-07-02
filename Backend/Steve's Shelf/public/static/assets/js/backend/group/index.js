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
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'group_id',
                sortName: 'weigh',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'group_id', title: __('Id')},
                        {field: 'group_name', title: __('Name'), align: 'left'},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter:function(value,row){
                            var html = [];
                            html.push('<a href="javascript:;" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                            if(row.group_id!=1){
                                html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
                            }
                            return html.join(' ');
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
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-add': function (e, value, row, index) {
                        var area = [$(window).width() > 800 ? '800px' : '95%', $(window).height() > 600 ? '600px' : '95%'];
                        if($(this).data('width')){
                            area[0]=$(this).data('width');
                        }
                        if($(this).data('height')){
                            area[1]=$(this).data('height');
                        }
                        Fast.api.open(add_server_url + (add_server_url.match(/(\?|&)+/) ? "&category_id=" : "?category_id=")+row.category_id, __('Add'),{area:area});                          
                    }
                },Table.api.events.operate)
            }
        }
    };
    return Controller;
});
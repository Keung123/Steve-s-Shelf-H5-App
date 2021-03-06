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
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
				commonSearch:false,
				search:false,
                pk: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'title', title: __('adsense title'), align: 'left'},
                        {field: 'width', title: __('imagewidth'), align: 'left'},
                        {field: 'height', title: __('imageheight'), align: 'left'},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter:function(value,row){
                            var html = [];
                            html.push('<a href="'+adsense_url+'?pid='+row.id+'" title="广告位列表" class="btn btn-success btn-addtabs btn-xs">广告位列表</a>');
                            html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                          /*   html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>'); */
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
            }
        }
    };
    return Controller;
});
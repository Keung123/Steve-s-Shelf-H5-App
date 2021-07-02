define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    edit_url:edit_url
                }
            });
            // alert($.fn.bootstrapTable.defaults.extend.index_url);
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                // url: '/admin/user/index.html',
                escape: false,
                pk: 'm_id',
                sortName: 'weigh',
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {field: 'm_id', title: 'ID'},
                        {field: 'user_avat', title: __('Avatar'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'user_name', title: __('Username'),operate: false},
                        {field: 'goods_name', title: "商品名称"},
                        {field: 'mate_content', title: "素材内容",width:350},
                        {field: 'mate_status', title: "是否加精"},
                        {field: 'mate_zhiding', title: "是否置顶"},
                        {field: 'mate_add_time', title: "添加时间"},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function(){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs">详情</a>');
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
            }
        }
    };
    return Controller;
});
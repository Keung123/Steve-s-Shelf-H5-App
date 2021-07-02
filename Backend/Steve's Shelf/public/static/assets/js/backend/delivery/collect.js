define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                }
            });
            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: '',
                sortName: '',
                // searchText: '订单编号',
                liveSearchPlaceholder:'',
                trimOnSearch:true,
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {field: 'nickname', title: "操作人员姓名"},
                        {field: 'num', title: "操作单数"},
                        {field: 'allMoney', title: "订单总额"},
                        {field: 'operate', title: __('Operate'),events: Controller.api.events.operate, formatter: function(value, row, index){
                                var detail="";
                                detail+='<a class="btn btn-xs btn-success saleslist" title="配送订单列表">订单列表</a> ';
                                return detail;
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
            formatter: {//渲染的方法
                operate: function (value, row, index) {
                    //返回字符串加上Table.api.formatter.operate的结果
                    //默认需要按需显示排序/编辑/删除按钮,则需要在Table.api.formatter.operate将table传入
                    //传入了table以后如果edit_url为空则不显示编辑按钮,如果del_url为空则不显显删除按钮
                    return '<a class="btn btn-info btn-xs btn-detail"><i class="fa fa-list"></i> ' + __('Detail') + '</a> '
                        + Table.api.formatter.operate(value, row, index, $("#table"));
                },
            },
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .saleslist': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open("/admin/delivery/saleslist?user_id="+ row.user_id, '配送订单列表');
                    },
                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});
define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pagination:false,
                pk: '',
                sortName: '',
				commonSearch:false,
				search:false,
                columns: [
                    [   
                        // {title: '序号', formatter:function(value, row, index){
                        //     return index+1;
                        // }},
                        {field: 'order_id', title: '订单id'},
                        {field: 'user_name', title: '用户名'},
                        {field: 'order_create_time', title: '订单时间', operate: false},
                        {field: 'commi_order_price', title: '付款金额', operate: false},
                        {field: 'commi_rate', title: '返利比列', operate: false},
                        {field: 'goods_profit', title: '返利总额', operate: false},
                        {field: 'profit_rate', title: '提成比列', operate: false},
                        {field: 'commi_price', title: '提成金额', operate: false},
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        // storeSr : function(){
        //     Form.api.bindevent($("form[role=form]"));
        // },
        storeSr : function(){
            Form.api.bindevent($("form[role=form]"));
        },
        rcDel: function () {
            Controller.api.bindevent($("form[role=form]"));
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});


define(['jquery', 'bootstrap', 'backend', 'table', 'form','upload'], function ($, undefined, Backend, Table, Form,Upload) {

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
                    chuli_url: chuli_url,
                    chuliwan_url: chuliwan_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pageList: [10, 25, 50, 100],        //可供选择的每页的行数
                search: false,                       //是否显示表格搜索
                pk: 'og_id',
                sortName: 'og_id',
                commonSearch:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'order_no', title: "订单单号"},
                        {field: 'goods_numbers', title:"商品编号"},
                        {field: 'cargo_numbers', title:"货号"},
                        {field: 'supplier_title', title:"供应商名称", align: 'left'},
                        {field: 'supplier_phone', title:"联系电话"},
                        {field: 'jiesuan', title:"结算方式"},
                        {field: 'price', title:"结算金额"},
                        {field: 'og_freight', title:"运费"},
                        // {field: 'og_status', title:"是否可以结算"},
                        // {field: 'og_remark', title:"备注"}
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
                    'click .btn-logistic': function (e, value, row, index) {
                        Backend.api.open(chuli_url+'?ids='+row.og_id, __('暂不结算'));
                    }

                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});
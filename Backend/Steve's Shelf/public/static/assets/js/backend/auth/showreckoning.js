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
                        {field: 'goods_name', title:"商品名称"},
                        {field: 'og_goods_spec_val', title:"规格"},
                        {field: 'supplier_title', title:"供应商名称", align: 'left'},
                        {field: 'og_goods_num', title:"销量"},
                        {field: 'price', title:"成本价"},
                        {field: 'og_freight', title:"运费"},
                        {field: 'og_status', title:"状态"}
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
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
                pk: 'order_id',
                sortName: '',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {field: 'order_id', title: '订单序号', class: 'order_id'},
                        {field: 'order_no', title: '订单编号'},
                        {field: 'user_name', title: '用户名', operate: false},
                        {field: 'user_mobile', title: '联系电话', operate: false},
                        {field: 'order_create_time', title: '订单时间', operate: false},
                        {field: 'commi_order_price', title: '金额（元）', operate: false},
                        {field: 'goods_profit', title: '获得利润（元）', operate: false},
                        // {field: 'commi_order_price',operate: false,events: Table.api.events.operate, formatter: function(value, row, index){
                        //     $price_html = "";
                        //     $price_html +='<li class="price">合计金额：'+row.sumPrice+'</li>';
                        //     $("#sku").html($price_html);
                        // }}
                        /*{field: 'order_all_price',events: Table.api.events.operate, formatter: function(value, row, index){
                            $price_html = "";
                            $price_html +='<li class="price">总计销售额：'+row.total+'</li>';
                            $("#sku").html($price_html);
                        }}*/
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


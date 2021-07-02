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
                pk: 'id',
                sortName: '',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {title: '序号', formatter:function(value, row, index){
                            return index + 1;
                        }},
                        {field: 's_name', title: '店铺名称'},
                        {field: 'user_name', title: '用户名', operate: false},
                        {field: 's_grade', title: '店铺等级', operate: false},
                        {field: 's_comm_time', title: '增加时间', operate: false},
                        {field: 'sale_total', title: '销售额', operate: false},
                        // {field: 'order_all_price',events: Table.api.events.operate, formatter: function(value, row, index){
                        //     $price_html = "";
                        //     $price_html +='<li class="price">总计销售额：'+row.total+'</li>';
                        //     $("#sku").html($price_html);
                        // }}
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


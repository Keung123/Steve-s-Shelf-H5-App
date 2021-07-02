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
                pk: 'reward_id',
                sortName: '',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {field: 'reward_id', title: '序号', class: 'reward_id'},
                        {field: 'reward_num', title: '奖励金额'},
                        {field: 'status', title: '奖励状态', operate: false},
                        {field: 'reward_time', title: '奖励时间', operate: false},
                        {field: 'order_all_price',events: Table.api.events.operate, formatter: function(value, row, index){
                            $price_html = "";
                            $price_html +='<li class="price">总计销售额：'+row.total+'</li>';
                            $("#sku").html($price_html);
                        }}
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


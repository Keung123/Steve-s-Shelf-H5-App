define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: url,
                    multi_url: multi_url,
                    show_url: show_url,
                }
            });
            // alert($.fn.bootstrapTable.defaults.extend.index_url);
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
				escape: false,
				pk: 'user_id',
                sortName: 'weigh',
				commonSearch:false,
				search:false,
                columns: [
                    [
                    // {field: 'state', checkbox: true },
                        {field: 'user_id', title: '序号'},
                        {field: 'shop_name', title: '店铺名称'},
                        {field: 'user_truename', title: '真实姓名'},
                        {field: 'csclename', title: '周期'},
                        /* {field: 'user_avat', title: __('Avatar'), operate: false, formatter: Table.api.formatter.image}, */
                        {field: 'user_mobile', title: __('手机号'),operate: false},
                        {field: 'user_level', title: "级别"},
                        {field: 'usergift', title: "礼包"},
                        {field: 'sales', title: "销售额"},
                        {field: 'self_sales', title: "个人销售额"},
                        {field: 'self_sales_profit', title: "个人销售利润"},
                        {field: 'vip_sales', title: "vip销售额"},
                        {field: 'vip_sales_profit', title: "VIP销售利润"},
                        {field: 'market_train', title: "市场培训"},
                        {field: 'shop_share', title: "店铺分享"},
                        {field: 'group_sale', title: "社群销售"},
                        {field: 'sales_profit', title: "社群服务费"},
                        {field: 'market_exp', title: "市场拓展"},
                        {field: 'goods_sales', title: "产品销售"},
                        {field: 'shop_award', title: "实体店铺奖励"},
                        {field: 'promotion', title: "促销奖励"},
                        {field: 'group_num', title: "社群数"}
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
		}
    };
    return Controller;
});
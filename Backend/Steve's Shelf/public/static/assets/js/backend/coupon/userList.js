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
                pk: 'coupon_id',
                sortName: 'coupon_id',
				  commonSearch:false,
				search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'coupon_id', title: __('Id')},
                        {field: 'c_coupon_title', title: __('coupon_title'), align: 'left'},
                        {field: 'c_coupon_price', title: __('优惠券面额'), align: 'left'},
                        {field: 'c_coupon_type', title: __('类型'), operate: false},
                        {field: 'c_coupon_buy_price', title: __('使用条件'), operate: false},
                        {field: 'c_uid', title: __('优惠券所属用户id')},
                        {field: 'coupon_stat', title: __('状态'), operate: false},
                        {field: 'add_time', title: __('领取时间'), operate: false},
                        {field: 'update_time', title: __('使用时间'), operate: false},
                        {field: 'coupon_aval_time', title: __('过期时间'), operate: false},
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {

            // Controller.api.bindevent();
            Form.api.bindevent($("form[role=form]"));

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
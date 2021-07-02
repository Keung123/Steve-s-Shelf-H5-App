define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: url,
                    /* multi_url: multi_url,
                    show_url: show_url, */
                }
            });
            // alert($.fn.bootstrapTable.defaults.extend.index_url);
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
				escape: false,
				pk: 'c_id',
                sortName: 'weigh',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {field: 'state', checkbox: true },
                        {field: 'c_id', title: '序号'},
                        {field: 'c_coupon_title', title: '名称'},
                        {field: 'c_no', title: '编号'},
                        {field: 'c_coupon_type', title: __('类型'),operate: false},
                        {field: 'c_coupon_price', title: "面额"},
                        {field: 'c_coupon_buy_price', title: "使用条件",width:350},
                        {field: 'add_time', title: "获取时间"},
                        {field: 'coupon_aval_time', title: "到期时间"}, 
                        {field: 'coupon_stat', title: "状态"}, 
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
            },
			events: {//绑定事件的方法
                operate: $.extend({
					'click .btn-invoice':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __(' 是否改变置顶状态?'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                   $.get(zhiding_url,{
											id:row['m_id'],
										  uid:row['m_uid'],
									},function(res){},'json')
									
                                    Layer.close(index);
									window.location.href = url;
                                }
                        );
                    },
					'click .btn-showDetails': function (e, value, row, index) {
					   window.location.href = (show_url+'?user_id='+ row['user_id']);
                    },

                }, Table.api.events.operate)
            }         
			 
		}
    };
    return Controller;
});
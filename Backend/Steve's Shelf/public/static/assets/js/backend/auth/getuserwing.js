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
				pk: 'yin_id',
                sortName: 'weigh',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {field: 'state', checkbox: true },
                        {field: 'yin_id', title: '序号'},
                        {field: 'yin_no', title: '元宝编号'},
                        {field: 'yin_amount', title: __('元宝大小'),operate: false},
                        {field: 'yin_type', title: "元宝获取方式"},
                        {field: 'yin_desc', title: "获取详细说明",width:350},
                        {field: 'yin_add_time', title: "元宝获取时间"},
                        {field: 'yin_valid_time', title: "有效天数"},
                        {field: 'yin_die_time', title: "到期时间"}, 
                        {field: 'yin_stat', title: "元宝状态"}, 
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
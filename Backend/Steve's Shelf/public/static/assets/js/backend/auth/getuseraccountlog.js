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
                        {field: 'a_log_id', title: '序号'},
                        {field: 'user_name', title: '用户名'},
                        {field: 'acco_num', title: '金额'},
                        {field: 'acco_type', title: __('类型'),operate: false},
                        {field: 'acco_desc', title: "描述"},
                        {field: 'acco_time', title: "时间",width:350},
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
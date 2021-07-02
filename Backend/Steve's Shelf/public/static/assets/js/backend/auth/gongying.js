define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                    kefu_url: kefu_url,
                    multi_url: multi_url,
                }
            });
            // alert($.fn.bootstrapTable.defaults.extend.index_url);
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                // url: '/admin/user/index.html',
				escape: false,
				pk: 'user_id',
                sortName: 'weigh',
                columns: [
                    [
                        {field: 'state', checkbox: true },
                        // {field: 'user_id', title: 'ID'},
                        {field: 'og_goods_name', title: "商品名称", operate: false},
                        {field: 'og_goods_thumb', title: "商品图片",operate:false, formatter: Table.api.formatter.image},
                        {field: 'stock', title: "库存"},
                        {field: 'volume', title: "销量"},
                       //  {field: 'nickname', title: __('Nickname')},
                       //  {field: 'email', title: __('Email')},
                       //  {field: 'user_points', title: '积分',operate: false, formatter: Table.api.formatter.status},
						// {field: 'is_kefu', title: __('是否为客服')},
                       // {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value, row, index){
                       //          var html = [];
						// 		html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
						// 		html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
						// 		html.push('<a  class="btn btn-success btn-invoice btn-xs" data-id="'+row.order_id+'">客服按钮</a>');
						// 		return html.join(' ');
                       //      }}
                    ]
                ],
                commonSearch:false
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
                                __(' 是否改变客服状态?'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                   $.get(kefu_url,{
										 uid:row['user_id'],
										 is_kefu:row['is_kefu'],
									},function(res){},'json')
									
                                    Layer.close(index);
									window.location.href = url;
                                }
                        );
                    }
				
                }, Table.api.events.operate)
            }         
			 
		}
    };
    return Controller;
});
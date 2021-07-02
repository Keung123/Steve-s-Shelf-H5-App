define(['jquery', 'bootstrap', 'backend', 'table', 'form','upload'], function ($, undefined, Backend, Table, Form,Upload) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                    multi_url: multi_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'sku_id',
                sortName: 'weigh',
                commonSearch:false,
                columns: [
                    [
                         {checkbox: true},
                        {field: 'code', title: __('商家编码'), align: 'left'},
                        {field: 'goods_name', title: __('Goods Name'), align: 'left'},
                        {field: 'stock', title: __('库存'), align: 'left'},
                        {field: 'sku_name', title: __('属性'), align: 'left'},
                        {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter:function(value, row, index){
                            var html = [];

							html.push('<a href="javascript:;" class="btn btn-success btn-invoice  btn-xs"><i class="fa fa-eye"></i></a>');
							html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                            return html.join(' ');
                        }}
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
            },
			 events: {//绑定事件的方法
                operate: $.extend({
					'click .btn-invoice': function (e, value, row, index) {
					  console.log('aaaaaa');
                       Backend.api.open(show_url+'?goods_id='+ row['goods_id'], __('商品详情'));
                    },
					'click .btn-restore':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __('是否恢复商品'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                   $.get(restore_url,{
											id:row['goods_id'],
									},function(res){},'json')
									
                                    Layer.close(index);
									window.location.href = index_url;
                                }
                        );
                    },
					'click .btn-remove':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __(' 是否删除?'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                   $.get(remove_url,{
											ids:row['goods_id'],
									},function(res){},'json')
									
                                    Layer.close(index);
									window.location.href = index_url;
                                }
                        );
                    }
 
                }, Table.api.events.operate)
            }                
        }
    };
    return Controller;
});
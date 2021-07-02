define(['jquery', 'bootstrap', 'backend', 'table', 'form','upload'], function ($, undefined, Backend, Table, Form,Upload) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: url,
                    add_url: add_url,
                    edit_url: chuli_url,
                    del_url: del_url,
                    multi_url: multi_url,
                    chuli_url: chuli_url,
                    chuliwan_url: chuliwan_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pageList: [10, 25, 50, 100],        //可供选择的每页的行数
                search: false,                       //是否显示表格搜索
                pk: 'og_id',
                sortName: 'og_id',
                commonSearch:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'order_no', title: "订单单号"},
                        {field: 'goods_name', title:"商品名称"},
                        {field: 'og_goods_spec_val', title:"规格"},
                        {field: 'supplier_title', title:"供应商名称", align: 'left'},
                        {field: 'og_goods_num', title:"销量"},
                        {field: 'price', title:"成本价"},
                        {field: 'og_freight', title:"运费"},
                        {field: 'og_status', title:"状态"},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter:function(value, row, index){
                            var html = [];
							if (row.og_status == '未结算') {
									html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs">  暂不处理</a>');
							} else if (row.og_status == '暂不处理') {
								html.push('<a href="javascript:;" class="btn btn-danger btn-invoice btn-xs"> 取消暂不处理</a>');
							}
                            return html.join(' ');
                         }
						},
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
					'click .btn-cancel': function (e, value, row, index) {
                        Backend.api.open(chuliwan_url+'?ids='+ row['og_id'], __('取消'));
                    },
					'click .btn-invoice':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __(' 确定取消？'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                   $.get(chuliwan_url,{
											ids:row['og_id'],
									},function(res){},'json')
									
                                    Layer.close(index);
									window.location.href = url;
                                }
                        );
                    },
				}, Table.api.events.operate)
			}       
        }
    };
    return Controller;
});
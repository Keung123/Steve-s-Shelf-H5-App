define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        rcList: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,                    
                    del_url: del_url,
                }
            });
            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'rech_id',
                sortName: '',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'rech_no', title: '充值编号'},
                        {field: 'rech_uid', title: '用户id'},
                        {field: 'rech_uname', title: '用户昵称', operate: false},
                        {field: 'rech_amount', title: '充值金额', operate: false},
                        {field: 'rech_way', title: '支付方式', operate: false},
                        {field: 'rech_type', title: '充值方式', operate: false},
                        {field: 'rech_pay_time', title: '充值时间', operate: false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function(value, row, index){
                            var html = [];
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
                            $price_html = "";
                            $price_html +='<li class="price">总计充值金额：'+row.total_price+'</li>';
                            $price_html +='<li class="price">当前页充值金额：'+row.amount+'</li>';
                            $("#sku").html($price_html);
                            return html.join(' ');
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        delete: function () {
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

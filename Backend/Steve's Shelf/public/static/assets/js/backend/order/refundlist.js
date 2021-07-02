/**
 * Created by benbenkeji on 2018/10/15.
 */
define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-danger").data("area", ["300px","200px"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    // add_url:add_url,
                    // edit_url:edit_url
                    del_url: del_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'refund_id',
                sortName: '',
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'refund_id', title: '序号'},
                        {field: 're_order_no', title: "订单编号"},
                        {field: 're_pay_no', title:__('支付编号')},
                        {field: 're_type', title:__('支付方式')},
                        {field: 're_price', title: __('退款金额(元)')},
                        {field: 'create_time', title: __('审核时间')},
                        {field: 'is_refund', title: __('是否退款')},
                        {field: 'check_time', title: __('退款时间')},
                        {field: 'operate', title: __('Operate'),  events: Controller.api.events.operate, formatter: function(value, row, index){
                                var html = [];
                                if(row['is_refund'] == '否') {
                                    html.push('<a href="javascript:;" data-width="1000px" class="btn btn-danger btn-delone btn-xs">确认退款</a>');
                                }
                                return html.join(' ');
                            }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-danger': function (e, value, row, index) {
                        e.stopPropagation();

                        /*layer.confirm('是否确定退款？', {
                            btn: ['确认','取消'] //按钮
                        }, function(){
                            $.get(edit_url,{
                                refund_id:row['refund_id']
                            },function(res){
                                if(res.code==1){
                                    layer.msg('操作成功', {icon: 1});
                                    top.window.location.reload();
                                }
                            },'json')
                        });*/

                    }
                }, Table.api.events.operate)
            }

        }
    };
    return Controller;
});
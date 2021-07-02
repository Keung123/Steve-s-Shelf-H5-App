/**
 * Created by benbenkeji on 2018/10/15.
 */
define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    del_url: del_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'cash_id',
                sortName: '',
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'cash_id', title: '序号', class: 'cash_id'},
                        {field: 'user_name', title: '用户名'},
                        {field: 'user_mobile', title: '手机号'},
                        {field: 'cash_way', title: '提现账号'},
                        {field: 'cash_amount', title: '提现金额'},
                        {field: 'cash_addtime', title: '提交时间'},
                        {field: 'cash_stat', title: '提现状态'},
                        {field: 'cash_operat', title: '操作人'},
                        {field: 'cash_paytime', title: '打款时间'},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value,row,index){
                            var html = [];
                            if(row.cash_stat =='未审核') {
                                html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-shenhe btn-xs">审核</a>');
                            }else {
                                html.push('<a href="javascript:;" class="btn btn-invoice btn-success btn-xs">详情</a>');
                            }
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
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
        edit : function(){
            Form.api.bindevent($("form[role=form]"));
        },
        del: function () {
            Controller.api.bindevent($("form[role=form]"));
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-invoice': function (e, value, row, index) {
                        console.log();
                        Backend.api.open(show_url+'?cash_id='+ row['cash_id'], __('提现详情'));
                    },
                    'click .btn-shenhe': function (e, value, row, index) {
                        console.log();
                        Backend.api.open(pay_url+'?cash_id='+ row['cash_id'], __('审核'));
                    },
                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});
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
                    edit_url:edit_url,
                    del_url: del_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'b_id',
                sortName: '',
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'b_id', title: '序号', class: 'b_id'},
                        {field: 'user_name', title: '用户名'},
                        {field: 'user_mobile', title: '手机号'},
                        {field: 'user_truename', title: '真实姓名'},
                        {field: 'company_name', title: '公司名称'},
                        {field: 'corporation_name', title: '法人名称'},
                        {field: 'submit_time', title: '提交时间'},
                        {field: 'status', title: '审核状态'},
                        {field: 'operate_name', title: '操作人'},
                        {field: 'revie_time', title: '审核时间'},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value,row,index){
                            var html = [];
                            if(row.status=='未审核') {
                                html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-shenhe btn-xs">审核</a>');
                            }else if(row.status=='已通过'){
                                html.push('<a href="javascript:;" class="btn btn-invoice btn-success btn-xs">详情</a>');
                            }else{
                                html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs">修改</a>');
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
                        Backend.api.open(show_url+'?b_id='+ row['b_id'], __('营业执照详情'));
                    },
                    'click .btn-shenhe': function (e, value, row, index) {
                        console.log();
                        Backend.api.open(shen_url+'?b_id='+ row['b_id'], __('营业执照审核'));
                    },
                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});
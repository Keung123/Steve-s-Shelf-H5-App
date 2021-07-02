/**
 * Created by benbenkeji on 2018/10/31.
 */
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
                pk: 'id',
                sortName: '',
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: '序号', class: 'id'},
                        {field: 'user_name', title: '用户名'},
                        {field: 'user_mobile', title: '手机号'},
                        {field: 'b_balance', title: '发票余额'},
                        {field: 'add_time', title: '提交时间'},
                        {field: 'sh_status', title: '审核状态'},
                        {field: 'sh_user', title: '审核人'},
                        {field: 'sh_relation', title: '审核备注'},
                        {field: 'sh_time', title: '审核时间'},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value,row,index){
                            var html = [];
                            if(row.sh_status=='未审核'){
                                html.push('<a href="javascript:;" class="btn btn-shenhe btn-success btn-xs">审核</a>');
                                html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs">修改金额</a>');

                            }else{
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
            console.log(123);
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
                        Backend.api.open(show_url+'?id='+ row['id'], __('发票详情'));
                    },
                    'click .btn-shenhe': function (e, value, row, index) {
                        Backend.api.open(shen_url+'?id='+ row['id'], __('发票审核'));
                    },
                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});
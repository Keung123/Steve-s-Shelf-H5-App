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
                        {field: 'user_truename', title: '真实姓名'},
                        {field: 's_grade', title: '店主级别'},
                        {field: 'self_wages', title: '本人工资'},
                        {field: 'gk_wages', title: '挂靠工资'},
                        {field: 'number', title: '挂靠人数'},
                        {field: 'status', title: '状态'},
                        {field: 'b_balance', title: '发票余额'},
                        {field: 'add_time', title: '提交时间'},
                        {field: 'put_out_time', title: '发放时间'},
                        {field: 'sh_status', title: '发票审核'},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function(value,row,index){
                            var html = [];
                            html.push('<a href="javascript:;" class="btn btn-editone btn-success btn-xs">详情</a>');
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
            }
        }
    };
    return Controller;
});
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
                    add_url:add_url,
                    edit_url:edit_url,
                    del_url: del_url,
                    show_url: show_url
                }
            });
            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'bg_id',
                sortName: '',
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'bg_id', title: '序号'},
                        {field: 'user_name', title: '用户名'},
                        {field: 'user_mobile', title: '手机号'},
                        {field: 'user_truename', title: '真实姓名'},
                        {field: 's_grade', title: '店主级别'},
                        {field: 'bg_time', title: '挂靠时间'},
                        {field: 'bg_protocol', title:'挂靠协议',events: Controller.api.events.operate,formatter: function(value,row,index){
                            var html = [];
                            html.push('<a href="'+row["bg_protocol"]+'" data-width="1000px" class="btn btn-success btn-xs">附件</a>');
                            return html.join(' ');
                        }},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value,row,index){
                            var html = [];
                            html.push('<a href="javascript:;"  class="btn btn-success btn-invoice btn-xs">详情</a>');
                            // html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs">详情</a>');
                            html.push('<a href="javascript:;"  class="btn btn-warning btn-editone btn-xs">修改</a>');
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
                            return html.join(' ');
                        }}
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
        edit : function(){
             Controller.api.bindevent();
        },
        del: function () {
             Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },events: {//绑定事件的方法
                operate: $.extend({
                    
                    'click .btn-invoice': function (e, value, row, index) {
                        console.log(show_url);
                       Backend.api.open(show_url+'?ids='+ row['bg_id'], __('挂靠关系详情'));
                    },
                }, Table.api.events.operate)
            }              
        }
    };
    return Controller;
});
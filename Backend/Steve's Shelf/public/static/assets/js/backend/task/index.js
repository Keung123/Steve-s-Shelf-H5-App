define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'task_id',
                sortName: 'task_id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'task_id', title: __('Id')},
                        {field: 'create_time', title: __('create time'), align: 'left'},
                        {field: 'buyer', title: __('buyer'), align: 'left'},
                        {field: 'link_name', title: __('link name'), align: 'left'},
                        {field: 'link_type', title: __('link type'), align: 'left'},
                        {field: 'address', title: __('address'), operate: false},
                        {field: 'price', title: __('total price'), operate: false},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value, row, index){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="80%" class="btn btn-success btn-detail btn-xs"><i class="fa fa-eye"></i></a>');
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
                            return html.join(' ');
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            //打印
            $(document).on("click", ".btn-print", function () {
                var options=table.bootstrapTable('getSelections');
                var ids=[];
                for(var i in options){
                    ids.push(options[i].id);
                }
                Backend.api.open(task_add_url+"?ids="+ids.join(','), __('print'));
            });            
        },
        add: function () {
            Controller.api.bindevent();
            //为表单绑定事件
            Form.api.bindevent($("#add-form"), null, function (data,ret) {
                if(ret.code!=1){
                    Toastr.error(msg ? msg : __('Operation failed'));
                }
                //打开打印列表
                var a = $("<a href='"+info_url+"?task_id="+data.task_id+"' target='_blank' >打印</a>").get(0);
                var e = document.createEvent('MouseEvents');
                e.initEvent('click', true, true);
                a.dispatchEvent(e);
                return true;
            });            
        },
        edit: function () {
            Controller.api.bindevent();
        },
        info:function(){
            $("#print").click(function(){
                $("#none").hide();
                layer.closeAll();
                window.print();
            })
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events:{
                operate: $.extend({
                    'click .btn-detail': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open(info_url +"?task_id="+ row['task_id'], __('print'));
                    }
                }, Table.api.events.operate)          
            }
        }
    };
    return Controller;
});
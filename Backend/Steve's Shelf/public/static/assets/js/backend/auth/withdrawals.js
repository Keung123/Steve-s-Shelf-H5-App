define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                    multi_url: multi_url,
                }
            });
            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                columns: [
                    [
                        {field: 'id', title: 'ID'},
                        {field: 'user', title: __('Username'),formatter:function(value, row, index){
                            return value['user_name']||'-';
                        }},
                        {field: 'user', title: __('Mobile'),formatter:function(value, row, index){
                            return value['mobile'];
                        }},  
                        {field: 'create_time', title: __("tx time")},       
                        {field: 'point', title: __("tx point")},                    
                        {field: 'ali_id', title: __("ali id")},
                        {field: 'status_name', title: __("status")},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value, row, index){
                            var detail="";
                            if(row.status==0){
                                //待审核
                                detail+='<a class="btn btn-xs btn-danger btn-refuse">拒绝</a> ';
                                detail+='<a class="btn btn-xs btn-success btn-pass">通过</a> ';
                            }
                            if(row.status==1){
                                //待发货
                                detail+='<a class="btn btn-xs btn-success btn-transfer">转账完成</a> ';                                
                            }
                            return detail;                            
                        }}
                    ]
                ],
                commonSearch:false,
                search:false                
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-detail': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open('example/bootstraptable/detail/ids/' + row['id'], __('Detail'));
                    },
                    'click .btn-refuse':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __('确定要拒绝该申请吗?'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                    Table.api.multi("refuse", row['id'], $("#table"), that);
                                    Layer.close(index);
                                }
                        );
                    },
                    'click .btn-pass':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __('确定通过该申请吗？'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                    Table.api.multi("pass", row['id'], $("#table"), that);
                                    Layer.close(index);
                                }
                        );
                    },
                    'click .btn-transfer':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __('确定已经完成转账吗？'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                    Table.api.multi("transfer", row['id'], $("#table"), that);
                                    Layer.close(index);
                                }
                        );
                    }                   
                }, Table.api.events.operate)
            }                      
        }        
    };
    return Controller;
});
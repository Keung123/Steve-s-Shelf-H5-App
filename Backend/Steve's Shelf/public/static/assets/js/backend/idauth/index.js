define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    edit_url: edit_url,
                    del_url: del_url,

                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'auth_id',
                sortName: '',
				 commonSearch:false,
				search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'auth_id', title: 'id', class: 'auth-id'},
                        {field: 'auth_uid', title: '用户id'},
                        {field: 'auth_uname', title: '用户昵称'},
                        {field: 'auth_truename', title: '真实姓名', operate: false},
                        {field: 'auth_phone', title: '联系方式', operate: false},
                        {field: 'auth_id_no', title: '身份证号', operate: false},
                        {field: 'auth_stat', title: '审核状态', operate: false},
                        {field: 'auth_addtime', title: '提交时间', operate: false},
                        {field: 'auth_checktime', title: '审核时间', operate: false},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value,row,index){
                            var html = [];
                            if(row.auth_stat =='未审核'){
                                html.push('<a href="javascript:;" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil">审核</i></a>');
                                html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i><span>删除</span></a>');
                            }else{
                                html.push('<a href="javascript:;" class="btn btn-invoice btn-success btn-xs"><i class="fa fa-eye">查看</i></a>');
                                html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i><span>删除</span></a>');
                            }



                            return html.join(' ');
                        }}
                    ]
                ]
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
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
                            console.log('row');
                            Backend.api.open(show_url+'?auth_id='+ row['auth_id'], __('实名认证详情'));
                        },
                    }, Table.api.events.operate)
                }
            }
        };
    return Controller;
});

/*function check_idauth(e){
    var oEv = $(e.target);
    var auth_id = oEv.parents('td').siblings(".auth-id").html();
    $.get('/admin/Idauth/edit?ids='+auth_id, function(data){
        var view_page = layer.open({
            type : 1,
            title : '查看会员',
            shadeClose : true,
            area : ['1000px','600px'],
            skin : 'yourclass',
            maxmin : 1,
            content : data,
        });
    });

}*/

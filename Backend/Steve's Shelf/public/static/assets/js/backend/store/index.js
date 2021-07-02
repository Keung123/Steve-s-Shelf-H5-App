define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    sr_url: sr_url,
                    edit_url: edit_url,
                    add_url: add_url, 
                    // del_url: del_url,
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 's_id',
                sortName: '',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        // {checkbox: false},
                        {field: 's_id', title: '店铺id', class: 's-id'},
                        {field: 's_name', title: '店铺名称'},
                        {field: 'user_name', title: '用户名', operate: false},
                        {field: 'invite_code', title: '邀请码', operate: false},
                        {field: 'user_mobile', title: '手机号', operate: false},
                        {field: 's_grade', title: '店铺等级', operate: false},
                        {field: 's_comm_time', title: '开店时间', operate: false},
                        {field: 'saleroom', title: '店铺销售额', sortable:true},
                        {field: 'store_total', title: '店铺总收入',sortable:true},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(){
                            var html = [];
                            // html.push('<a href="javascript:;" data-width="80%" class="btn btn-primary btn-xs" onclick="viewSr(event)"><i class="fa fa-eye"></i> 销售额</a>');
                            html.push('<a href="javascript:;" data-width="80%" class="btn btn-default btn-invoice btn-xs" >详情</a>');
                            // html.push('<a href="javascript:;" data-width="80%" class="btn btn-default btn-xs" onclick="viewPerfor(event)"><i class="fa fa-line-chart"></i> 业绩明细</a>');
                            // html.push('<a href="javascript:;" data-width="80%" class="btn btn-link btn-xs" onclick="viewVip(event)"><i class="fa fa-vimeo"></i> vip管理</a>');
                            // html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
                            return html.join(' ');
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        // storeSr : function(){
        //     Form.api.bindevent($("form[role=form]"));
        // },
        storeSr : function(){
            Form.api.bindevent($("form[role=form]"));
        },
        addStore: function () {
            Form.api.bindevent($("form[role=form]"));
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-invoice': function (e, value, row, index) {
                        Backend.api.open(show_url +'?s_id='+ row['s_id'], __('店铺详情'));
                    }
                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});

// 销售额
/*function viewSr(ev){
    var oTr = $(ev.target);
    var s_id = oTr.parent().siblings('.s-id').html();
    $.post('/admin/store/storeSr', {s_id : s_id}, function(data){
        layer.open({
            type : 1,
            title : '查看销售额',
            shadeClose : true,
            area : ['1000px','600px'],
            skin : 'yourclass',
            maxmin : 1,
            content : data,
        });
    });
}*/

// 业绩明细
function viewPerfor(ev){
    var oTr = $(ev.target);
    var s_id = oTr.parent().siblings('.s-id').html();
    location.href  = '/admin/store/perforManage?s_id='+s_id;
}

// 我的VIP
function viewVip(ev){
    var oTr = $(ev.target);
    var s_id = oTr.parent().siblings('.s-id').html();
    $.post('/admin/store/storeVip', {s_id : s_id}, function(data){
        layer.open({
            type : 1,
            title : '查看销售额',
            shadeClose : true,
            area : ['1000px','600px'],
            skin : 'yourclass',
            maxmin : 1,
            content : data,
        });
    });
}

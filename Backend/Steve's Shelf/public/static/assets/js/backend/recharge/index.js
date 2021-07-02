define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'rc_id',
                sortName: '',
				commonSearch:false,
				search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'rc_title', title: '充值卡名称'},
                        {field: 'rc_price', title: '面额'},
                        {field: 'rc_buy_price', title: '购买金额', operate: false},
                        {field: 'rc_s_time', title: '生效时间', operate: false},
                        {field: 'rc_aval_time', title: '有效天数', operate: false},
                        {field: 'rc_total', title: '剩余张数', operate: false},
                        {field: 'rc_buy_num', title: '已购买张数', operate: false},
                        {field: 'rc_add_time', title: '增加时间', operate: false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function(){
                            var html = [];
                            /*html.push('<a  href="javascript:;" data-width="80%" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');*/
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa ">修改状态</i></a>');
                            return html.join(' ');
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        rcAdd : function(){
            Form.api.bindevent($("form[role=form]"));
        },
        rcEdit : function(){
            Form.api.bindevent($("form[role=form]"));            
        },
        rcDel: function () {
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


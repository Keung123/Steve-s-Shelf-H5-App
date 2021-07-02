define(['jquery', 'bootstrap', 'backend', 'table', 'form','upload'], function ($, undefined, Backend, Table, Form,Upload) {

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
                    jiesuan_url: jiesuan_url,
                    dingdanInfo_url: dingdanInfo_url
                }
            });

            var table = $("#table");





            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'id',
                sortName: 'id',
                search:false,
                commonSearch:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'supplier_title', title:"供应商名称"},
                        {field: 'supplier_phone', title:"联系电话"},
                        {field: 'total_price', title:"结算金额"},
                        {field: 'jiesuan', title:"结算方式"},
                        {field: 'status', title:"结算状态"},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter:function(value, row, index){
                                var html = [];
                                html.push('<a class="btn btn-xs btn-success btn-logistic">详情</a>');
                                html.push('<a class="btn btn-xs btn-success btn-invoice">确认</a>');
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
            //检测商品名称是否存在
            // $("#c-goods_name").blur(function(){
            //     var goods_name=$(this).val();
            //     if(!goods_name){
            //         return false;
            //     }
            //     $.get(getGoodsInfo_url,{goods_name:goods_name},function(res){
            //         if(res){
            //             layer.alert("该商品名称已存在");
            //         }
            //     },'json');
            // });
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-logistic': function (e, value, row, index) {
                        Backend.api.open(dingdanInfo_url + '?sett_id=' + row['id'], __('结算单详情'));
                    },
                    'click .btn-invoice':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                            __(' 是否确认？'),
                            {icon: 3, title: __('Warning'), shadeClose: true},
                            function () {
                                $.get(jiesuan_url,{
                                    sett_id:row['id'],
                                },function(res){},'json')

                                Layer.close(index);
                                window.location.href = url;
                            }
                        );
                    },

                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});
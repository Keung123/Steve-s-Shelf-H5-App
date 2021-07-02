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
                    jiesuan_url: jiesuan_url
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
                        {field: 'supplier_name', title:"联系人"},
                        {field: 'supplier_phone', title:"联系人电话"},
                        {field: 'price', title:"成交总额"},
                        {field: 'order_num', title:"成交订单数"},
                        {field: 'number', title:"成交商品数"},
                        {
                            title:'操作',
                            field:'id',
                            formatter:function(value,row,index){
                                var thisStr='<a href="'+index_url+'?ids='+value+'">查看详情</a> ';
                                // thisStr +='<a href="'+jiesuan_url+'?ids='+value+'">结算</a>';
                                return thisStr;
                            }
                        },

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
            }
        }
    };
    return Controller;
});
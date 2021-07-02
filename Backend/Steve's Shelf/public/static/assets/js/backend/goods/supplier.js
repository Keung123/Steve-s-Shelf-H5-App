define(['jquery', 'bootstrap', 'backend', 'table', 'form','upload'], function ($, undefined, Backend, Table, Form,Upload) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                    multi_url: multi_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'id',
                sortName: 'id',
                commonSearch:false,
				search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'supplier_title', title:"供应商名称", align: 'left'},
                        {field: 'supplier_name', title:"联系人"},
                        {field: 'supplier_phone', title:"联系电话"},
                        {field: 'jiesuan', title:"结算方式"},
                        {field: 'supplier_addr', title:"供应商地址"},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter:function(){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                            // html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
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
            $("#c-goods_name").blur(function(){
                var goods_name=$(this).val();
                if(!goods_name){
                    return false;
                }
                $.get(getGoodsInfo_url,{goods_name:goods_name},function(res){
                    if(res){
                        layer.alert("该商品名称已存在");
                    }
                },'json');
            });
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
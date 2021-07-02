define(['jquery', 'bootstrap', 'backend', 'table', 'form','bootstrap-typeahead'], function ($, undefined, Backend, Table, Form,Typeahead) {

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
                pk: 'id',
                sortName: 'id',
                search:false,
                commonSearch:false,
                pageSize:100,
                pageList:[],
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'code', title: __('code'), align: 'left'},
                        {field: 'goods_name', title: __('goods name'), align: 'left'},
                        {field: 'num', title: __('num'), operate: false},
                        {field: 'unit', title: __('unit'), operate: false},
                        {field: 'price', title: __('price'), operate: false},
                        {field: 'price', title: __('total price'), operate: false, formatter:function(value,row){
                            return value*row.num;
                        }},
                        {field: 'remark', title: __('remark'), operate: false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: function(){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="80%" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
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

            //获取商品信息
            $("#getGoodsInfo").click(function(){
                var code=$("#c-code").val();
                if(!code){
                    layer.msg("请输入编号");
                    return false;
                }
                $.get(sku_info_url,{code:code},function(res){
                    if(res.code!=1){
                        layer.msg("商品不存在");
                        return false;                        
                    }
                    $("#c-goods_name").val(res.data.goods.goods_name+"/"+res.data.sku_name);
                    $("#c-price").val(res.data.price);
                    $("#c-unit").val(res.data.goods.unit);
                },'json')
            });
            //自动补全
            $("#c-goods_name").typeahead({
                source:function(query, process){
                    return $.get(get_goods_url,{goods_name:query},function(res){
                        if(res){
                            for(var i in res){
                                res[i].name=res[i].goods.goods_name+'/'+res[i].sku_name;
                            }
                        }else{
                            res=[];
                        }
                        return process(res);
                    });
                },
                afterSelect:function(data){
                    $("#c-code").val(data.code);
                    $("#c-price").val(data.price);
                    $("#c-unit").val(data.goods.unit);                    
                }
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
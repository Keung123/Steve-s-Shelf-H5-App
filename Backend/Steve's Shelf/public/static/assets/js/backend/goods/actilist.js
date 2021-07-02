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
                sortName: 'weigh',
                commonSearch:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'active_type_name', title:"活动名称", align: 'left'},
                        {field: 'active_type', title:"活动类型"},
                        {field: 'rules_title', title:"活动规则名称"},
                        {field: 'rules_content', title:"活动规则内容"},
                        {field: 'active_img', title: __('Image'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: __('Status'), operate: false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate,  formatter:function(value, row, index){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
							if(row.id>8){
								html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
							}
                           
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
            $('input[type=radio][name="row[active_type]"]').change(function() {
                var type_val =$("input[name='row[active_type]']:checked").val();
                if (type_val == 1) {
                    $('#c-active_type_val').html('减价');
                   // $(this).parent().parent().parent().nextAll().css("display","block");
                    $('#c-active_type_vals').show();
                    $("#weight_").show();
                    $("#limit_").show();
                    $("#status_").show();
                } else if(type_val == 2){
                    $('#c-active_type_val').html('打折');
                   // $(this).parent().parent().parent().nextAll().css("display","block");
                    $('#c-active_type_vals').show();
                    $("#weight_").show();
                    $("#limit_").show();
                    $("#status_").show();
                } else if(type_val == 3){
                    $('#c-active_type_val').html('免邮');
                    //$(this).parent().parent().parent().nextAll().css("display","block");
                    $('#c-active_type_vals').hide();
                    $("#weight_").show();
                    $("#limit_").show();
                    $("#status_").show();
                } else if(type_val == 4){
                    $('#c-active_type_val').html('送积分');
                   // $(this).parent().parent().parent().nextAll().css("display","block");
                    $('#c-active_type_vals').show();
                    $("#weight_").show();
                    $("#limit_").show();
                    $("#status_").show();
                }else if(type_val==5){
                    $('#c-active_type_vals').hide();
                    $('#weight_').hide();
                    $('#limit_').hide();

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
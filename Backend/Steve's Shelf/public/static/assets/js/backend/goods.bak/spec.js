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
                pk: 'spec_id',
                sortName: 'spec_id',
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'spec_id', title: __('Id')},
                        {field: 'spec_name', title: __('goods spec mame'), align: 'left'},
                        {field: 'spec_desc', title: __('goods spec desc'), align: 'left'},
                        {field: 'values', title: __('values'),formatter:function(value,rows){
                            if(!value){
                                return "";
                            }
                            var arr=[];
                            for (var i in value) {
                                arr.push(value[i].spec_value_name);
                            }
                            return arr.join(',');
                        }},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter:function(){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
                            html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>');
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
            $("#add").on("click",function(){
                var tpl=$("#tpl").html();
                $("#rows").append(tpl);
            })
            $("#rows").on("click",".btn-del",function(){
                $(this).closest('.form-group').remove();
            })
        },
        edit: function () {
            Controller.api.bindevent();
            $("#add").on("click",function(){
                var tpl=$("#tpl").html();
                $("#rows").append(tpl);
            })
            //删除
            $("#rows").on("click",".btn-del",function(){
                var _this=this;
                var id=$(_this).data('id');
                if(!id){
                    $(_this).closest('.form-group').remove();
                    return false;
                }
                $.get(delete_url,{id:id},function(res){
                    if(res.code==1){
                        $(_this).closest('.form-group').remove();
                    }else{
                        layer.msg("删除失败");
                    }
                },'json');
            });
            //更新
            var spec_value_name="";
            $("#rows").on("focus","[data-edit]",function(){
                spec_value_name=$(this).val();
            });
            $("#rows").on("blur","[data-edit]",function(){
                var value=$(this).val();
                if(value!==spec_value_name&&value!=""){
                    var _this=this;
                    var id=$(_this).data('id');
                    $.post(update_url,{id:id,name:value},function(res){
                        console.log(res);
                    })
                }
            });

            // 规格名称判定
            // $(".btn-success").on("click", function(){
            //     var oId = $('[name="spec_id"]').val();                
            //     var oName = $("#c-name").val();
            //     $.post('/admin/goods/specNameCheck', {spec_id : oId, spec_name : oName}, function(res){
            //         console.log(res);
            //         if(!res.code){
            //             layer.msg(res.msg);
            //             throw "err";
            //         }
            //         else if(res.code == -1){
            //             layer.confirm('规格名称已存在，是否要覆盖？',{
            //                     btn : ['确定', '取消']
            //                 },function(){
            //                     $("#add-form").submit();
            //                 },function(){
            //                     layer.msg('请更换规格名称');
            //                 }
            //             );
            //         }
            //     });
            // });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
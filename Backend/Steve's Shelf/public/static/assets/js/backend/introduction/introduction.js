define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    del_url: del_url,
                    multi_url: 'data/multi.json',
                    dragsort_url: 'ajax/weigh',
                    table: 'introduction',
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
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('编号')},
                        {field: 'img', title: __('Image'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'weigh', title: __('排序')},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function(value, row, index){
                                var detail = '<a href="javascript:;" class="btn btn-primary btn-dragsort btn-xs"><i class="fa fa-arrows"></i></a>';
                                detail += '<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa fa-trash"></i></a>';
                                return detail;
                            }},
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            $(document).on("change","#c-flag",function(){
                var categoryid=$(this).val();
                if(categoryid == 0){
                    var html = "";
                    $('#category_er').html(html);
                    $('#category_er').selectpicker('refresh');
                    $('#category_er').selectpicker('render');
                    return false;
                }

                $.get(getSecondName,{
                    categoryid:categoryid
                },function(res){
                    console.log(res);
                    var html="<option value=''> </option>";
                    if(res.code=='0'){
                        var html="<option value=''> "+res.msg+"</option>";
                        $("#category_er").html(html);
                        $('#category_er').selectpicker('refresh');
                        $('#category_er').selectpicker('render');
                    }else if (res.rows.length > 0) {
                        $.each(res.rows, function (index, value) {
                            html += "<option value='"+value.category_id+"'>"+value.category_name+"</option>";
                        });
                        // console.log(html,11);
                        $('#category_er').html(html);
                        $('#category_er').selectpicker('refresh');
                        $('#category_er').selectpicker('render');
                    }  else {
                        html= "<option value=''>暂无数据</option>";

                        $("#category_er").html(html);
                    }
                },'json');
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
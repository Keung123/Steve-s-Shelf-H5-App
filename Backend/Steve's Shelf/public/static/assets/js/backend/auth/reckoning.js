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
                    shenhe_url: shenhe_url,
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
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'supplier_name', title:"供应商名称"},
                        {field: 'end_time', title:"结算结束时间"},
                        {field: 'total_price', title:"结算金额"},
                        {field: 'jiesuan', title:"结算方式"},
                        {field: 'add_time', title:"申请时间"},
                        {field: 'status', title:"审核状态"},
                        {field: 'applicant', title:"申请人"},

                        {field: 'auditor', title:"审核人"},
                        {field: 'examine_time', title:"审核时间"},
                        {
                            title:'操作',
                            field:'id',
                            formatter:function(value,row,index){
                                var thisStr='<a href="'+index_url+'?ids='+value+'">查看详情</a> | ';
                                thisStr +='<a href="'+shenhe_url+'?ids='+value+'">审核</a>';
								$price_html = "";
								$price_html +='<li class="price">总计销售额：'+row.totalprice+'</li>';
								$price_html +='<li class="price">当前页销售额：'+row.pageprice+'</li>';
								$("#sku").html($price_html);     
                                  
                                return thisStr;
                            }
                        }

                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
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
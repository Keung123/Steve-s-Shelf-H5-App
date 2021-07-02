define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'og_id',
                sortName: 'weigh',
                // searchText: '订单编号',
                liveSearchPlaceholder:'订单编号',
                trimOnSearch:true,
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {field: 'order_no', title: "订单编号"},
                        {field: 'order_pay_price', title:__('订单金额')},
                        {field: 'addr_receiver', title: __('link name')},
                        {field: 'addr_phone', title: __('mobile')},
                        {field: 'order_create_time', title: __('Createtime')},
                        {field: 'order_type', title: __('订单类型')},
                        {field: 'status_names', title: __('status')},
                        {field: 'is_cash', title: __('是否返现')},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value, row, index){
                                var detail="";
                                if(row.is_cash == '否'){
                                    detail+='<a class="btn btn-xs btn-success btn-editaddr" data-id="'+row.order_id+'">操作返现</a> ';
                                } else {
                                    detail+='<a class="btn btn-xs btn-success btn-editdetails" data-id="'+row.order_id+'">返现详情</a> ';
                                }
                                $price_html = "";
                                $price_html +='<li class="price">总计销售额：'+row.total_price+'</li>';
                                $price_html +='<li class="price">当前页销售额：'+row.page_price+'</li>';
                                $("#sku").html($price_html);
                                return detail;
                            }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            //获取品牌
            $(document).on("change","#pro_name",function(){
                // $("#pro_name").change(function(){
                var parent_id=$(this).val();
                if(!parent_id){
                    return false;
                }
                $.get(addr_url,{
                    parent_id:parent_id
                },function(res){

                    var html="";
                    if (res.length > 0) {
                        $.each(res,function(index,value){
                            html = html+"<option value="+value.region_id+" >"+value.region_name+"</option>";
                        });

                        $("#city_name").html(html);
                        $.get(addr_url,{
                            parent_id:res[0].region_id
                        },function(res){
                            var html="";
                            if (res.length > 0) {
                                $.each(res,function(index,value){
                                    html = html+"<option value="+value.region_id+" >"+value.region_name+"</option>";
                                });

                                $("#area").html(html);
                            } else {
                                html= "<option value=''>暂无数据</option>";

                                $("#area").html(html);
                            }
                        },'json');
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#city_name").html(html);
                    }
                },'json');
            });
            $(document).on("change","#city_name",function(){
                // $("#pro_name").change(function(){
                var parent_id=$(this).val();
                if(!parent_id){
                    return false;
                }
                $.get(addr_url,{
                    parent_id:parent_id
                },function(res){
                    var html="";
                    if (res.length > 0) {
                        $.each(res,function(index,value){
                            html = html+"<option value="+value.region_id+" >"+value.region_name+"</option>";
                        });

                        $("#area").html(html);
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#area").html(html);
                    }
                },'json')
            });
        },
        edit: function () {
            Controller.api.bindevent();
        },

        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {//渲染的方法
                operate: function (value, row, index) {
                    //返回字符串加上Table.api.formatter.operate的结果
                    //默认需要按需显示排序/编辑/删除按钮,则需要在Table.api.formatter.operate将table传入
                    //传入了table以后如果edit_url为空则不显示编辑按钮,如果del_url为空则不显显删除按钮
                    return '<a class="btn btn-info btn-xs btn-detail"><i class="fa fa-list"></i> ' + __('Detail') + '</a> '
                        + Table.api.formatter.operate(value, row, index, $("#table"));
                },
            },
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-detail': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open('example/bootstraptable/detail/ids/' + row['id'], __('Detail'));
                    },
                    'click .btn-post': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open(edit_url +'?order_id='+ row['order_id']+'&og_supplier_id='+row['og_supplier_id']+'&og_id='+row['og_id'], __('发货'));
                    },
                    'click .btn-pay': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open(edit_pay_url +'?order_id='+ row['order_id'], __('修改支付金额'));
                    },
                    'click .btn-editaddr': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open(editaddr_url +'?order_id='+ row['order_id'], __('操作返现'));
                    },
                    'click .btn-editdetails': function (e, value, row, index) {
                        e.stopPropagation();
                        Backend.api.open(editdetails_url +'?order_id='+ row['order_id'], __('返现详情'));
                    },
                    'click .btn-invoice': function (e, value, row, index) {
                        Backend.api.open(invoice_url +'?order_id='+ row['order_id'], __('发票详情'));
                    },
                    'click .btn-logistic': function (e, value, row, index) {
                        Backend.api.open(logistic_url +'?order_id='+ row['order_id']+'&og_supplier_id='+row['og_supplier_id']+'&og_id='+row['og_id'], __('物流详情'));
                    },
                    'click .btn-particulars': function (e, value, row, index) {
                        Backend.api.open(particulars_url +'?order_id='+ row['order_id']+'&og_supplier_id='+row['og_supplier_id']+'&og_id='+row['og_id'], __('订单详情'));
                    },
                    'click .btn-close':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                            __('确定要关闭该订单吗?'),
                            {icon: 3, title: __('Warning'), shadeClose: true},
                            function () {
                                Table.api.multi("del", row['order_id'], $("#table"), that);
                                Layer.close(index);
                            }
                        );
                    }
                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});
define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url: add_url,
                    edit_url: edit_url,
                    search_url: search_url,
                    del_url: del_url,
                    multi_url: multi_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'coupon_id',
                sortName: 'coupon_id',
				  commonSearch:false,
				search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'coupon_id', title: __('Id')},
                        {field: 'coupon_title', title: __('coupon_title'), align: 'left'},
                        {field: 'coupon_price', title: __('优惠券面额'), align: 'left'},
                        {field: 'coupon_get_limit', title: __('每人限领张数'), align: 'left'},

                        {field: 'coupon_type', title: __('coupon_type'), operate: false},
                        {field: 'coupon_s_time', title: __('coupon_s_time'), operate: false},
                        {field: 'coupon_aval_time', title: __('coupon_aval_time'), operate: false},
                        {field: 'coupon_total', title: __('coupon_total'), operate: false},
                        {field: 'amount', title: __('已经领取数量'), operate: false},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(){
                            var html = [];
							html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-invoice btn-xs"><i class="fa fa-eye"></i></a>');
                            html.push('<a href="javascript:;" data-width="80%" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
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
            //搜索商品
            $('.search_goods').click(function(){
                var search_content = $('input[name=search_name]').val();
                if(!search_content){
                    return false;
                }

                $.get(search_url,{'goodName':search_content},function(res){
                    if(res.length>0){
                        //替换
                        var shtml="";
                        $.each(res,function(index,v){
                            shtml += "<option value='"+v.goods_id+"' data-price='"+v.goods_name+"'>"+v.goods_name+"</option>"
                        })
                    }else{
                        shtml= "<option value=''>未搜索到相关商品</option>";
                    }

                    $('#coupon_sp').html(shtml);
                    $('#coupon_sp').selectpicker('refresh');
                    $('#coupon_sp').selectpicker('render');
                },'json');

            })
            // Controller.api.bindevent();
            Form.api.bindevent($("form[role=form]"));

        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },events: {//绑定事件的方法
                operate: $.extend({
					
					'click .btn-invoice': function (e, value, row, index) {
						console.log(show_url);
                       Backend.api.open(show_url+'?ids='+ row['coupon_id'], __('优惠券详情'));
                    },
                }, Table.api.events.operate)
            }              
        }
    };
    return Controller;
});
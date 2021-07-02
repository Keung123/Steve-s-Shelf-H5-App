/**
 * Created by benbenkeji on 2018/10/15.
 */
define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: index_url,
                    add_url:add_url,
                    edit_url:edit_url,
                    del_url: del_url
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                escape: false,
                pk: 'tp_id',
                sortName: '',
                commonSearch:false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'tp_id', title: '序号', class: 'ty_id'},
                        {field: 'tp_title', title: '话题名称'},
                        {field: 'tp_addtime', title: '创建时间'},
                        {field: 'tp_partake_num', title: '参与数量', operate: false},
                        {field: 'tp_status', title: '状态'},
                        {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(){
                            var html = [];
                            html.push('<a href="javascript:;" data-width="80%" class="btn btn-default btn-invoice btn-xs" >素材</a>');
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
            //获取二级分类
            $(document).on("change","#category",function(){
                var categoryid=$(this).val();
                if(!categoryid){
                    return false;
                }
                $.get(getSecondName,{
                    categoryid:categoryid
                },function(res){
                    var html="";
                    if (res.rows.length > 0) {
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
            //获取品牌
            $(document).on("change","#category_er",function(){
                // $("#category").change(function(){
                var goryid=$(this).val();
                if(!goryid){
                    return false;
                }
                $.get(getGoodsName,{
                    goryid:goryid
                },function(res){
                    var html="";
                    if (res.rows.length > 0) {
                        console.log(res.rows);
                        html="";
                        $.each(res.rows,function(index,value){
                            html = html+"<option data-price ="+value.price+" data-stock ="+value.stock+" value="+value.goods_id+" >"+value.goods_name+"</option>";
                        });
                        var prices = res.rows[0].price;
                        $("#c-goods_price").val(prices);
                        $("#goods_name").html(html);
                        var goodsname = res.rows[0].goods_name;
                        $("#goodsname").val(goodsname);
                        var stock= res.rows[0].stock;
                        $("#c-goods_stock").val(stock);
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#goods_name").html(html);
                    }
                },'json');
            });
            $(document).on("change","#goods_name",function(){
                var goods_price = $("#goods_name option:selected").attr("data-price");
                $("#c-goods_price").val(goods_price);
                var goods_stock = $("#goods_name option:selected").attr("data-stock");
                $("#c-goods_stock").val(goods_stock);
                var goodsname = $("#goods_name option:selected").html();
                $("#goodsname").val(goodsname);
            });
            //进入页面默认触发一次
            $("#attribute_id").trigger('change');
			    $('#check_user').click(function(){
					
                var phone= $('#user_phone').val();
                $.get(checkName,{phone:phone},function(res){
                    console.log(res);
                    if(!phone){
                        $('#wring').text('用户手机号不能为空');
                        $('#wring').css('color','red');
						$('#tp_user_id').val('');
                        $('#user_name').val('');
                        return false;
                    }
                   if(res.code==0){
                       $('#wring').text('用户不存在');
                       $('#wring').css('color','red');
					   $('#tp_user_id').val('');
                       $('#user_name').val('');
                   }else if(res.code==1){
					   $('#wring').text('该用户不是店主');
                       $('#wring').css('color','red');
					   $('#tp_user_id').val('');
                       $('#user_name').val('');
				   }else{
                        var user_id=res.row['user_id'];
                        $('#tp_user_id').val(res.row['user_id']);
                        $('#user_name').val(res.row['user_name']);
                   }
                });
            });
            $('#user_phone').mousemove(function(){
                $('#wring').text('');
            });
        },
        edit : function(){
            Form.api.bindevent($("form[role=form]"));
        },
        del: function () {
            Controller.api.bindevent($("form[role=form]"));
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {//绑定事件的方法
                operate: $.extend({
                    'click .btn-invoice': function (e, value, row, index) {
                        window.location.href = (show_url+'?tp_id='+ row['tp_id']);
                    }
                }, Table.api.events.operate)
            }
        }
    };
    return Controller;
});
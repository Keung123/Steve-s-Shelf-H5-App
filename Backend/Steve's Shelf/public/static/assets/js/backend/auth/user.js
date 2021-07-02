define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                    kefu_url: kefu_url,
                    multi_url: multi_url,
                }
            });
            // alert($.fn.bootstrapTable.defaults.extend.index_url);
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                // url: '/admin/user/index.html',
				escape: false,
				pk: 'user_id',
                sortName: 'weigh',
				search:false,
				commonSearch:false,
                columns: [
                    [
                        {field: 'state', checkbox: true },
                        {field: 'user_id', title: 'ID'},
                        {field: 'user_avat', title: __('Avatar'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'user_name', title: __('Username')},
                        // {field: 'shop_name', title: __('店铺名称')},
                        {field: 'user_mobile', title: __('Mobile')},
                        {field: 'is_vip', title: __('会员')},
                        {field: 'vip_end_time', title: __('会员到期时间')},
                        {field: 'user_account', title: __('余额')},
                       /* {field: 'user_card', title: __('充值卡余额')},
                        {field: 'yin_amount', title: __('元宝个数')},                    
                        {field: 'coupon_amount', title: __('优惠券个数')},         */
                        {field: 'user_points', title: '积分',operate: false, formatter: Table.api.formatter.status},
                        {field: 'user_reg_time', title:__('注册时间'),operate: false},
                        {field: 's_invite_code', title:__('邀请码'),operate: false},
                        //{field: 'id_no', title:__('身份证号'),operate: false},
						// {field: 'is_kefu', title: __('是否为客服')},
                       {field: 'operate', title: __('Operate'), events: Controller.api.events.operate, formatter: function(value, row, index){
                                var html = [];
							 	html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-userinfo btn-xs"><i class="fa fa-eye"></i></a>');  
								html.push('<a href="javascript:;" data-width="1000px" class="btn btn-success btn-editone btn-xs"><i class="fa fa-pencil"></i></a>');
								if(row.status== 0){
                                    html.push('<a href="javascript:;" class="btn btn-danger btn-delone btn-xs"><i class="fa ">禁用</i></a>');
                                }else{
                                    html.push('<a href="javascript:;" class="btn bg-green btn-delone btn-xs"><i class="fa ">启用</i></a>');
                                }
                               html.push('<a href="javascript:;" class="btn bg-green btn-chongzhi btn-xs"><i class="fa ">充值</i></a>');
                               html.push('<a href="javascript:;" class="btn bg-green btn-huiyuan  btn-xs"><i class="fa ">会员</i></a>');

                               /* html.push('<a href="javascript:;" class="btn btn-danger btn-giving btn-xs"><i class=" fa fa-gift"></i></a>');
                                   html.push('<a  class="btn btn-success btn-invoice btn-xs" data-id="'+row.order_id+'">客服按钮</a>');  */
                                 $price_html = "";
                                // $price_html +='<li class="price">总计充值卡余额：'+row.total_card+'</li>';
                                // $price_html +='<li class="price">当前页充值卡余额：'+row.sum_card+'</li>';
                                // $price_html +='<li class="price">总计元宝个数：'+row.sum_yunbao+'</li>';
                           $("#sku").html($price_html);
								return html.join(' ');
                            }}
                    ]
                ],
                commonSearch:false
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
			 
            Controller.api.bindevent();
            $('#check_user').click(function(){
                var phone= $('#user_phone').val();
                $.get(checkName,{phone:phone},function(res){
                    console.log(res);
                    if(!phone){
                        $('#wring').text('用户手机号不能为空');
                        $('#wring').css('color','red');
                        return false;
                    }
                   if(res.code==0){
                       $('#wring').text('用户不存在');
                       $('#wring').css('color','red');
                   }else if(res.code==1){
                        var img=res.row['user_avat'];
                        var name=res.row['user_name'];
                        var mobile=res.row['user_mobile'];
                        var regTime=res.row['user_reg_time'];
                        var html="<img id='img' src="+"'"+img+"'/>"+"<input type='text' name='pid' id='pid' value='"+res.row['user_id']+"'>"+"<div id='right'>"+"<p>"+"<span id='name'>"+name+"</span>"+"<span>"+mobile+"</span>"+"</p>"+"<p id='plain'>普通会员</p>"+"<p>注册时间："+regTime+"</p>"+"</div>";
                        $('#user_descript').html(html);
                   }else if(res.code==2){
                       var img=res.row['user_avat'];
                       var name=res.row['user_name'];
                       var mobile=res.row['user_mobile'];
                       var regTime=res.row['user_reg_time'];
                       var grade=res.row['s_grade'];
                       if(grade==1){
                           var Time=res.row['s_comm_time'];
                           var html="<img id='img' src="+"'"+img+"'/>"+"<input type='text' name='pid' id='pid' value='"+res.row['user_id']+"'>"+"<div id='right'>"+"<p>"+"<span id='name'>"+name+"</span>"+"<span>"+mobile+"</span>"+"</p>"+"<p >普通店主</p>"+"<p>注册时间："+regTime+"</p>"+"<p>晋升时间："+Time+"</p>"+"</div>";
                           $('#user_descript').html(html);
                       }else if(grade==2){
                           var Time=res.row['s_better_time'];
                           var html="<img id='img' src="+"'"+img+"'/>"+"<input type='text' name='pid' id='pid' value='"+res.row['user_id']+"'>"+"<div id='right'>"+"<p>"+"<span id='name'>"+name+"</span>"+"<span>"+mobile+"</span>"+"</p>"+"<p >高级店主</p>"+"<p>注册时间："+regTime+"</p>"+"<p>晋升时间："+Time+"</p>"+"</div>";
                           $('#user_descript').html(html);
                       }else{
                           var Time=res.row['s_best_time'];
                           var html="<img id='img' src="+"'"+img+"'/>"+"<input type='text' name='pid' id='pid' value='"+res.row['user_id']+"'>"+"<div id='right'>"+"<p>"+"<span id='name'>"+name+"</span>"+"<span>"+mobile+"</span>"+"</p>"+"<p >旗舰店主</p>"+"<p>注册时间："+regTime+"</p>"+"<p>晋升时间："+Time+"</p>"+"</div>";
                           $('#user_descript').html(html);
                       }

                    }
                });
            });
            $('#user_phone').mousemove(function(){
                $('#wring').text('');
            });
        },
        edit: function () {
            Controller.api.bindevent();
        },
		api: {
			  bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
			events: {//绑定事件的方法
                operate: $.extend({
                   
					'click .btn-invoice':function(e, value, row, index){
                        e.stopPropagation();
                        var that = this;
                        var index = Layer.confirm(
                                __(' 是否改变客服状态?'),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function () {
                                   $.get(kefu_url,{
										 uid:row['user_id'],
										 is_kefu:row['is_kefu'],
									},function(res){},'json')
									
                                    Layer.close(index);
									window.location.href = url;
                                }
                        );
                    },
					'click .btn-userinfo': function (e, value, row, index) {
                       Backend.api.open(show_url+'?user_id='+ row['user_id'], __('用户详情'));
                    },
					'click .btn-chongzhi': function (e, value, row, index) {
                       Backend.api.open(chongzhi_url+'?user_id='+ row['user_id'], __('充值'));
                    },
					'click .btn-huiyuan': function (e, value, row, index) {
                       Backend.api.open(huiyuan_url+'?user_id='+ row['user_id'], __('会员'));
                    },
					'click .btn-giving': function (e, value, row, index) {
                       Backend.api.open(give_url+'?user_id='+ row['user_id'], __('用户赠送'));
                    }
                }, Table.api.events.operate)
            }         
			 
		}
    };
    return Controller;
});
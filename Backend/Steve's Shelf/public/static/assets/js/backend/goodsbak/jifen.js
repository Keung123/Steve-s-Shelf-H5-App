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
                pk: 'goods_id',
                sortName: 'weigh',
                commonSearch:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'goods_id', title: __('Id')},
                        {field: 'goods_name', title: __('Goods Name'), align: 'left'},
                        {field: 'volume', title: __('volume'), align: 'left'},
                        {field: 'price', title: __('price'), align: 'left'},
                        {field: 'picture', title: __('Image'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: __('Status'), operate: false, formatter: Table.api.formatter.status},
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
            console.log(sku)
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
            //获取分类
            $("#attribute_id").change(function(){
                $("#sku").hide();
                var attr_id=$(this).val();
                if(!attr_id){
                    return false;
                }
                $.get(attribute_url,{
                    attr_id:attr_id
                },function(res){
                    var html="";
                    $.each(res.spec_list,function(index,value){
                        var desc=value.spec_desc?'['+value.spec_desc+']':"";
                        html+='<div class="SKU_TYPE"><span propid ="'+value.spec_id+'" sku-type-name="'+value.spec_name+'">'+value.spec_name+desc+'：</span>'; 
                        $.each(value.values,function(i,v){
                            var checked="";
                            console.log(value.spec_id)
                            if(spec&&spec[value.spec_id]&&$.inArray(v.spec_value_id.toString(),spec[value.spec_id])>-1){
                                checked="checked";
                            }
                            html+='<div class="SKU_LIST"><span><label><input type="checkbox" '+checked+' class="sku_value" propvaltitle="'+v.spec_value_name+'" propvalid="'+v.spec_value_id+'" value="'+v.spec_value_id+'" name="row[spec]['+value.spec_id+'][]" />'+v.spec_value_name+'</label></span></div>';
                        })                      
                        html+='</div>';
                    });
                    var subjecthtml = "";
                    $.each(res.subject_list,function(index,value){
                        var desc=value.desc?'['+value.desc+']':"";
                        subjecthtml+='<div class="SUBJECT_TYPE"><span propid ="'+value.subject_id+'" sku-type-name="'+value.title+'">'+value.title+desc+'：</span>';
                        $.each(value.values,function(i,v){
                            var checked="";
                            if(spec&&spec[value.subject_id]&&$.inArray(v.subject_value_id.toString(),spec[value.subject_id])>-1){
                                checked="checked";
                            }
                            subjecthtml+='<div class="SUBJECT_LIST"><span><label><input type="checkbox" '+checked+' class="sku_value" propvaltitle="'+v.subject_value_name+'" propvalid="'+v.subject_value_id+'" value="'+v.subject_value_id+'" name="row[subject]['+value.subject_id+'][]" />'+v.subject_value_name+'</label></span></div>';
                        })
                        subjecthtml+='</div>';
                    });
                    $("#spec").show();
                    $("#subject").show();
                    $("#spec .html").html(html);
                    $("#subject .html").html(subjecthtml);
                    //进入页面默认执行一次
                    tableCreate();
                },'json')
            });
            //获取品牌
            $(document).on("change","#category",function(){
            // $("#category").change(function(){
                var goryid=$(this).val();
                if(!goryid){
                    return false;
                }
                $.get(getcatebrand_url,{
                    goryid:goryid
                },function(res){
                    var html="";
                    if (res.length > 0) {
                        html="";
                        $.each(res,function(index,value){
                            html = html+"<option value="+value.id+" >"+value.title+"</option>";
                        });
                        $("#brandid").html(html);
                    } else {
                        html= "<option value=''>暂无数据</option>";

                        $("#brandid").html(html);
                    }

                    //进入页面默认执行一次
                    tableCreate();
                },'json')
            });
            //进入页面默认触发一次
            $("#attribute_id").trigger('change');
            //监听选择事件
            $(document).on("change",'.sku_value',function(){
                tableCreate();
            });
            var tableCreate=function(){
                var b = true;
                var skuTypeArr =  [];//存放SKU类型的数组
                var totalRow = 1;//总行数
                //获取元素类型
                $(".SKU_TYPE").each(function(){
                    //SKU类型节点
                    var skuTypeNode = $(this).children("span");
                    var skuTypeObj = {};//sku类型对象
                    //SKU属性类型标题
                    skuTypeObj.skuTypeTitle = $(skuTypeNode).attr("sku-type-name");
                    //SKU属性类型主键
                    var propid = $(skuTypeNode).attr("propid");
                    skuTypeObj.skuTypeKey = propid;
                    //是否是必选SKU 0：不是；1：是；
                    var is_required = $(skuTypeNode).attr("is_required");
                    skuValueArr = [];//存放SKU值得数组
                    //SKU相对应的节点
                    var skuValNode = $(this).find(".SKU_LIST");
                    //获取SKU值
                    var skuValCheckBoxs = $(skuValNode).find("input[type='checkbox'][class*='sku_value']");
                    var checkedNodeLen = 0 ;//选中的SKU节点的个数
                    $(skuValCheckBoxs).each(function(){
                        if($(this).is(":checked")){
                            var skuValObj = {};//SKU值对象
                            skuValObj.skuValueTitle = $(this).attr("propvaltitle");
                            skuValObj.skuValueId = $(this).attr("propvalid");
                            skuValueArr.push(skuValObj);
                            checkedNodeLen ++ ;
                        }
                    });
                    if(is_required && "1" == is_required){//必选sku
                        if(checkedNodeLen <= 0){//有必选的SKU仍然没有选中
                            b = false;
                            return false;//直接返回
                        }
                    }
                    if(skuValueArr && skuValueArr.length > 0){
                        totalRow = totalRow * skuValueArr.length;
                        skuTypeObj.skuValues = skuValueArr;//sku值数组
                        skuTypeObj.skuValueLen = skuValueArr.length;//sku值长度
                        skuTypeArr.push(skuTypeObj);//保存进数组中
                    }
                });
                var SKUTableDom = "";//sku表格数据
                //开始创建行
                if(b){//必选的SKU属性已经都选中了
                    //调整顺序(少的在前面,多的在后面)
                    skuTypeArr.sort(function(skuType1,skuType2){
                        return (skuType1.skuValueLen - skuType2.skuValueLen)
                    });
                    SKUTableDom += "<table class='skuTable'><tr>";
                    //创建表头
                    for(var t = 0 ; t < skuTypeArr.length ; t ++){
                        SKUTableDom += '<th>'+skuTypeArr[t].skuTypeTitle+'</th>';
                    }
                    SKUTableDom += '<th>价格</th><th>库存</th><th>编号</th><th>图片</th>';
                    SKUTableDom += "</tr>";
                    var getName=function(arr){
                        var arr_1={};
                        var arr_2=[];
                        for(var i in arr){
                            var key=arr[i].split(':')[0];
                            arr_1[key]=arr[i];
                        }
                        for (var i in arr_1) {
                            arr_2.push(arr_1[i]);
                        }
                        return arr_2.join(";");
                    }
                    //获取表单值
                    var getValue=function(name,key){
                        if(sku.length<1){
                            return "";
                        }
                        if(!sku[name]){
                            return "";
                        }
                        return sku[name][key]?sku[name][key]:"";
                    }
                    function generateMixed(n) {
                        var chars = ['0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
                        var res = "";
                        for(var i = 0; i < n ; i ++) {
                            var id = Math.ceil(Math.random()*35);
                            res += chars[id];
                            }
                        return res;
                    }
                    //循环处理表体
                    for(var i = 0 ; i < totalRow ; i ++){//总共需要创建多少行
                        SKUTableDom += "<tr>"
                        var rowCount = 1;//记录行数
                        var ids=[];      //记录ID
                        for(var j = 0 ; j < skuTypeArr.length ; j ++){//sku列
                            var skuValues = skuTypeArr[j].skuValues;//SKU值数组
                            var skuValueLen = skuValues.length;//sku值长度
                            rowCount = (rowCount * skuValueLen);//目前的生成的总行数
                            var anInterBankNum = (totalRow / rowCount);//跨行数    
                            var point = ((i / anInterBankNum) % skuValueLen);       
                            if(0  == (i % anInterBankNum)){//需要创建td
                                ids.push(skuTypeArr[j].skuTypeKey+':'+skuValues[point].skuValueId);
                                SKUTableDom += '<td rowspan='+anInterBankNum+'>'+skuValues[point].skuValueTitle+'</td>';
                            }else{
                                ids.push(skuTypeArr[j].skuTypeKey+':'+skuValues[Math.floor(point)].skuValueId);                               
                            }                           
                        }
                        var name=getName(ids);
                        //价格
                        SKUTableDom += '<td><input style="width:80px;" name="sku['+name+'][price]" value="'+getValue(name,'price')+'" class="form-control" type="text"/></td>';
                        //库存
                        SKUTableDom +='<td><input style="width:80px;" name="sku['+name+'][stock]" value="'+getValue(name,'stock')+'" class="form-control" type="text"/></td>';
                        //商家编码
                        SKUTableDom +='<td><input style="width:80px;" name="sku['+name+'][code]" value="'+getValue(name,'code')+'" class="form-control" type="text"/></td>';
                        //规格图片
                        var _id=generateMixed(10);
                        SKUTableDom +='<td style="min-width: 130px;"><input style="width:60%;min-width: 60px;margin-right:5px;" id="sku-image-'+_id+'" class="form-control" name="sku['+name+'][image]" value="'+getValue(name,'image')+'" type="text"><span><button id="sku-image-upload-'+_id+'" type="button" class="btn btn-danger plupload" data-multiple="false" data-input-id="sku-image-'+_id+'"><i class="fa fa-upload"></i></button></span></td>';
                        
                        SKUTableDom +='</tr>';
                    }
                    SKUTableDom += "</table>";
                }
                $("#sku").show();
                $("#sku .html").html(SKUTableDom);    
                Upload.api.plupload();            
            }         

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
define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

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
				commonSearch:false,
				search:false,
                pk: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'title', title: '仓库名称', align: 'left'},
                        {field: 'city', title: '所在城市', align: 'left'},
                        {field: 'address', title: '详细地址', align: 'left'},
                        {field: 'start_distance', title: '起送距离', align: 'left'},
                        {field: 'start_price', title: '起送价格', align: 'left'},
                        {field: 'add_price', title: '每公里增加价格', align: 'left'},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter:function(value,row){
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

            $("#get_address").click(function(){
                var area = $("#c-address").val();
                if(area.length>0){
                    // 百度地图API功能
                    var map = new BMap.Map("allmap");
                    var point = new BMap.Point(116.331398,39.897445);
                    map.enableScrollWheelZoom(true);
                    map.centerAndZoom(point,12);
                    // 创建地址解析器实例
                    var myGeo = new BMap.Geocoder();
                    // 将地址解析结果显示在地图上,并调整地图视野
                    myGeo.getPoint(area, function(point){
                        if (point) {
                            $("#lat").val(point.lat);
                            $("#lng").val(point.lng);
                            la = point.lat;
                            l = point.lng;
                            map.centerAndZoom(point, 16);
                            var marker = new BMap.Marker(point);
                            map.addOverlay(marker);
                            marker.enableDragging();
                            //标注拖拽后的位置
                            marker.addEventListener("dragend", function (e) {
                                $("#lat").val(e.point.lat);
                                $("#lng").val(e.point.lng);
                            });
                        }else{
                            alert("您的地址没有解析到结果!");
                        }
                    }, "北京市");
                }
                else{
                    alert("请填写详细地址获取定位坐标!");
                }
            });

            
            
        },
        edit: function () {
            Controller.api.bindevent();


            $("#get_address").click(function(){
                var area = $("#c-address").val();
                if(area.length>0){
                    // 百度地图API功能
                    var map = new BMap.Map("allmap");
                    var point = new BMap.Point(116.331398,39.897445);
                    map.enableScrollWheelZoom(true);
                    map.centerAndZoom(point,12);
                    // 创建地址解析器实例
                    var myGeo = new BMap.Geocoder();
                    // 将地址解析结果显示在地图上,并调整地图视野
                    myGeo.getPoint(area, function(point){
                        if (point) {
                            $("#lat").val(point.lat);
                            $("#lng").val(point.lng);
                            la = point.lat;
                            l = point.lng;
                            map.centerAndZoom(point, 16);
                            var marker = new BMap.Marker(point);
                            map.addOverlay(marker);
                            marker.enableDragging();
                            //标注拖拽后的位置
                            marker.addEventListener("dragend", function (e) {
                                $("#lat").val(e.point.lat);
                                $("#lng").val(e.point.lng);
                            });
                        }else{
                            alert("您的地址没有解析到结果!");
                        }
                    }, "北京市");
                }
                else{
                    alert("请填写详细地址获取定位坐标!");
                }
            });


            var area = $("#c-address").val();
                if(area.length>0){
                    // 百度地图API功能
                    var map = new BMap.Map("allmap");
                    var point = new BMap.Point(116.331398,39.897445);
                    map.enableScrollWheelZoom(true);
                    map.centerAndZoom(point,12);
                    // 创建地址解析器实例
                    var myGeo = new BMap.Geocoder();
                    // 将地址解析结果显示在地图上,并调整地图视野
                    myGeo.getPoint(area, function(point){
                        if (point) {
                            $("#lat").val(point.lat);
                            $("#lng").val(point.lng);
                            la = point.lat;
                            l = point.lng;
                            map.centerAndZoom(point, 16);
                            var marker = new BMap.Marker(point);
                            map.addOverlay(marker);
                            marker.enableDragging();
                            //标注拖拽后的位置
                            marker.addEventListener("dragend", function (e) {
                                $("#lat").val(e.point.lat);
                                $("#lng").val(e.point.lng);
                            });
                        }else{
                            alert("您的地址没有解析到结果!");
                        }
                    }, "北京市");
                }
                else{
                    alert("请填写详细地址获取定位坐标!");
                }

        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
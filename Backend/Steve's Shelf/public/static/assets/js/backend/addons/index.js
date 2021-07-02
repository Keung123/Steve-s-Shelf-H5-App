define(['jquery','bootstrap','backend','table','form'], function($, undefined, Backend, Table, Form){
    var Controller = {
        index: function() {
            Table.api.init({
                url: url,
                columns: [
                    {field: 'name', title: '插件名称'},
                    {field: 'author', title: '作者'},
                    {field: 'intro', title: '描述'},
                    {field: 'version', title: '版本'},
                    {field: 'state', title: '状态', formatter: Table.api.formatter.status},
                    {field: 'id', title: '操作', formatter: function(value, row, index) {
                            var html = [];
                            html.push('<a href="javascript:;" class="btn btn-success btn-xs btn-install" data-name="'+row.name+'"><i class="fa fa-pencil"></i>安装</a>');
                            if (row.state == 1) {
                                html.push('<a href="javascript:;" class="btn btn-success btn-xs btn-enable" data-name="'+row.name+'"><i class="fa fa-pencil"></i>关闭</a>');
                            } else {
                                html.push('<a href="javascript:;" class="btn btn-success btn-xs btn-enable" data-name="'+row.name+'"><i class="fa fa-pencil"></i>开启</a>');
                            }
                            html.push('<a href="javascript:;" class="btn btn-danger btn-xs btn-uninstall" data-name="'+row.name+'"><i class="fa fa-pencil"></i>卸载</a>');
                            return html.join(' ');
                    }}
                ],
                extend: {
                    index_url: url,
                    add_url: add_url,
                    edit_url: edit_url,
                    del_url: del_url,
                }
            });
            var table = $('#table').bootstrapTable();
            Table.api.bindevent(table);

            // 离线安装
            require(['upload'], function (Upload) {
                Upload.api.plupload("#plupload-addon", function (data) {
                    $('#table').bootstrapTable('refresh');
                });
            });

            // 为表格绑定事件
            $(document).on('click', '.btn-install', function(){
                var addon_name = $(this).data('name');
                if (addon_name) {
                    Fast.api.ajax({
                        url: 'addons/install',
                        data: {
                            addon_name: addon_name
                        },
                    }, function(res) {
                        $('#table').bootstrapTable('refresh');
                    });
                }
            });
            $(document).on('click', '.btn-enable', function(){
                var addon_name = $(this).data('name');
                if (addon_name) {
                    Fast.api.ajax({
                        url: 'addons/enable',
                        data: {
                            addon_name: addon_name
                        },
                    }, function(res) {
                        $('#table').bootstrapTable('refresh');
                    });
                }
            });
            $(document).on('click', '.btn-uninstall', function(){
                var addon_name = $(this).data('name');
                if (addon_name) {
                    Fast.api.ajax({
                        url: 'addons/uninstall',
                        data: {
                            addon_name: addon_name
                        },
                    }, function(res) {
                        $('#table').bootstrapTable('refresh');
                    });
                }
            });
        },
        add: function() {
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"));
        },
    };
    return Controller;
});
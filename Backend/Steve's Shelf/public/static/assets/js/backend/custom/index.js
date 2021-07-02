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
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('名称'), align: 'left'},
                        {field: 'author', title: __('作者'), align: 'left'},
                        {field: 'controller', title: __('控制器'), align: 'left'},
                        {field: 'micon', title: __('图标'), align: 'left'},

                        {field: 'createtime', title: __('创建时间'), operate: false},
                        {field: 'updatetime', title: __('更新时间'), operate: false},
                        {field: 'status', title: __('状态'), operate: false},
                        {field: 'operate', title: __('Operate'), events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Form.api.bindevent($("form[role=form]"),null, function(data, ret){
                window.location.href="addtable?cid="+ret.data;
                return false;
            });
        },
        addTable: function() {
            var i = 0;
            $('#addfield').click(function(){
                i++;
                var tmpl = '<tr>\n' +
                    '                    <td><input type="text" name="fields['+i+'][name]" class="form-control" /></td>\n' +
                    '                    <td><input type="text" name="fields['+i+'][extra]" class="form-control"/></td>\n' +
                    '                    <td><input type="text" name="fields['+i+'][type]" class="form-control"/></td>\n' +
                    '                    <td><input type="text" name="fields['+i+'][length]" class="form-control"/></td>\n' +
                    '                    <td><input type="text" name="fields['+i+'][value]" class="form-control"/></td>\n' +
                    '                    <td>\n' +
                    '                        <button type="button" class="btn btn-sm btn-danger remove">删除</button>\n' +
                    '                    </td>\n' +
                    '                </tr>';
                $('#fields').append(tmpl);
                $('.remove').click(function(){
                    $(this).parents('tr').remove();
                });
            });
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"), null, function(data, ret){
                window.location.href += '&step=1';
                return false;
            });
        },
        editTable: function(){
            var i = 0;
            $('#addfield').click(function(){
                i++;
                var tmpl = '<tr>\n' +
                    '                    <td><input type="text" name="fields['+i+'][name]" class="form-control" /></td>\n' +
                    '                    <td><input type="text" name="fields['+i+'][extra]" class="form-control"/></td>\n' +
                    '                    <td><input type="text" name="fields['+i+'][type]" class="form-control"/></td>\n' +
                    '                    <td><input type="text" name="fields['+i+'][length]" class="form-control"/></td>\n' +
                    '                    <td><input type="text" name="fields['+i+'][value]" class="form-control"/></td>\n' +
                    '                    <td>\n' +
                    '                        <button type="button" class="btn btn-sm btn-danger remove">删除</button>\n' +
                    '                    </td>\n' +
                    '                </tr>';
                $('#fields').append(tmpl);
                $('.remove').click(function(){
                    $(this).parents('tr').remove();
                });
            });
            $('.remove').click(function(){
                $(this).parents('tr').remove();
            });
            Form.api.bindevent($("form[role=form]"));
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'fyxbl/record/index',
                    add_url: 'fyxbl/lists/add',
                    edit_url: 'fyxbl/lists/edit',
                    del_url: 'fyxbl/lists/del',
                    multi_url: 'fyxbl/lists/multi',
                    table: 'fyxbl',
                    feedback_url: 'fyxbl/lists'
                }
            });
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pageSize: 10,
                search: false,
                commonSearch: false,
                showExport: false,
                toolbar: false,
                showToggle: false,
                showColumns: false,
                pageList: [10],
                columns: [
                    [
                        {field: 'id', title: __('Id')},

                        {field: 'msg', title: __('Msg')},
                        {field: 'tid', title: __('Taks id')},
                        {field: 'url', title: __('Url'), formatter: Table.api.formatter.url},
                        {
                            field: 'create_time',
                            title: __('Create time'),
                            formatter: Table.api.formatter.datetime,
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            sortable: true
                        },
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate},
                    ]
                ]
            });

            // 为表格绑定事件
        },

        feedback: function () {
            $(document).on('click', '.btn-callback', function () {
                Fast.api.close($("input[name=callback]").val());
            });
        },
    };
    return Controller;
});

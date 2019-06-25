define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'tasklist/index',
                    feedback_url: 'tasklist/feedback'
                }
            });
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pageSize: 5,
                search: false,
                commonSearch: false,
                showExport: false,
                toolbar: false,
                showToggle: false,
                showColumns: false,
                pageList: [5],
                columns: [
                    [
                        {field: 'title', title: __('Title')},

                        {
                            field: 'source',
                            title: __('Source'),
                            table: table,
                            custom: {
                                "done": 'success',
                                "fail": 'danger',
                                "Retry": 'default',
                                "running": 'warning',
                                'queue': 'info'
                            },
                            searchList: {"rabbit": __('rabbit'), "eqxiu": __('rqxiu')},
                            formatter: Table.api.formatter.table
                        },
                        {
                            field: 'status',
                            title: __('Status'),
                            table: table,
                            custom: {
                                "done": 'success',
                                "fail": 'danger',
                                "Retry": 'default',
                                "running": 'warning',
                                'queue': 'info'
                            },
                            searchList: {
                                "queue": __('queue'),
                                "running": __('running'),
                                "retry": __('retry'),
                                "done": __('done'),
                                "fail": __('fail')
                            },
                            formatter: Table.api.formatter.status
                        },
                        {field: 'url', title: __('Url'), formatter: Table.api.formatter.url},
                        {
                            field: 'createtime',
                            title: __('Create time'),
                            formatter: Table.api.formatter.datetime,
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            sortable: true
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [{
                                name: 'ajax',
                                title: __('发送Ajax'),
                                classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                icon: 'fa fa-bug',
                                url: $.fn.bootstrapTable.defaults.extend.feedback_url,
                                success: function (data, ret) {
                                    console.log(data);
                                    Layer.prompt({
                                            title: __('Please explain your problems'),
                                            formType: 2
                                        }, function (text, index) {
                                            layer.close(index);
                                            //采用POST方式调用服务
                                            $.post('tasklist/record', {msg: text,tid: data.row.id,url: data.row.url});
                                            layer.msg(__('We have submitted your feedback'));
                                        }
                                    );
                                    //如果需要阻止成功提示，则必须使用return false;
                                    return false;
                                },
                                error: function (data, ret) {
                                    console.log(data, ret);
                                    Layer.alert(ret.msg);
                                    return false;
                                }
                            }

                            ],
                            formatter: Table.api.formatter.operate

                        }
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

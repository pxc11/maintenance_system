define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {



    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/order/index' + location.search,
                    add_url: 'order/order/add',
                    edit_url: 'order/order/edit',
                    del_url: 'order/order/del',
                    multi_url: 'order/order/multi',
                    table: 'order',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'order_number', title: __('Order_number')},
                        {field: 'client_name', title: __('Client_name')},
                        {field: 'client_mobile', title: __('Client_mobile')},
                        {field: 'client_address', title: __('Client_address'),visible:false},
                        {field: 'factory', title: __('Factory'),visible:false},
                        {field: 'buy_date', title: __('Buy_date'),visible:false, operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime,datetimeFormat: "YYYY-MM-DD"},
                        {field: 'car_code', title: __('Car_code'),visible:false},
                        {field: 'engine_code', title: __('Engine_code'),visible:false},
                        {field: 'malfunction', title: __('Malfunction')},
                        {field: 'emergency_address', title: __('Emergency_address')},
                        {field: 'state', title: __('State'), searchList: {"未接单":__('未接单'),"已接单":__('已接单'),"已处理":__('已处理')}, formatter: Table.api.formatter.normal,
                        custom: {"已处理":"success","未接单":"danger","已接单":"info"}
                        },
                        {field: 'user.real_name', title: __('接单师傅')},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'jd_time', title: __('接单时间'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'wc_time', title: __('完成时间'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                        buttons:[
                            {
                                name: 'detail',

                                title: __('派单记录'),
                                classname: 'btn btn-xs btn-info btn-dialog',
                                extend: 'data-toggle="tooltip"',
                                icon: 'fa fa-hand-paper-o',
                                url: 'order/dispatch/index',
                                callback: function (data) {
                                    Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                }
                            },
                            {
                                name: 'edit',
                                icon: 'fa fa-align-justify',
                                title: __('详情'),
                                extend: 'data-toggle="tooltip"',
                                classname: 'btn btn-xs btn-success btn-editone'
                            }
                        ]}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();


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

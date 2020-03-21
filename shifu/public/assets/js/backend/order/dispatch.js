define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/dispatch/index' + location.search,
                    add_url: 'order/dispatch/add',
                    edit_url: 'order/dispatch/edit',
                    del_url: 'order/dispatch/del',
                    multi_url: 'order/dispatch/multi',
                    table: 'dispatch',
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
                        {field: 'user.real_name', title: __('User.real_name')},
                       // {field: 'is_start', title: __('订单是否开始处理'), searchList: {"是":__('是'),"否":__('否')}, formatter: Table.api.formatter.normal},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},

                        {field: 'state', title: __('State'), searchList: {"未接单":__('未接单'),"已接单":__('已接单'),"已处理":__('已处理'),"已拒绝":__('已拒绝')}, formatter: Table.api.formatter.normal},

                        {field: 'jj_time', title: __('Jj_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},

                       // {field: 'is_weixin', title: __('微信是否已推送'), searchList: {"是":__('是'),"否":__('否')}, formatter: Table.api.formatter.normal},

                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                        buttons:[
                          /*  {
                                name: 'ajax',
                                text: __('微信推送'),
                                title: __('微信推送'),
                                classname: 'btn btn-xs btn-success btn-magic btn-ajax',

                                url: function (v,row) {
                                    return "order/dispatch/ts"+"?id="+v.id;
                                },
                                confirm: '确认推送',
                                success: function (data, ret) {
                                    Layer.alert(ret.msg);
                                    $(".btn-refresh").trigger("click");
                                    //如果需要阻止成功提示，则必须使用return false;
                                    //return false;
                                },
                                error: function (data, ret) {
                                    console.log(data, ret);
                                    Layer.alert(ret.msg);
                                    return false;
                                },
                                visible: function (row) {
                                    if(row.is_weixin=="是"){
                                        return false;
                                    }else {
                                        if(row.is_start=="是"){
                                            return  false;
                                        }
                                        if(row.state=="未接单"){
                                            return true;
                                        }

                                    }

                                }
                            }*/
                        ]
                        }
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
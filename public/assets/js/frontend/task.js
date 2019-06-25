define(['jquery', 'bootstrap', 'frontend', 'form', 'template'], function ($, undefined, Frontend, Form, Template) {
    var validatoroptions = {
        invalid: function (form, errors) {
            $.each(errors, function (i, j) {
                Layer.msg(j);
            });
        }
    };
    var Controller = {
        rabbit: function () {
            Form.api.bindevent($("#login-form"), function (data, ret) {
                setTimeout(function () {
                    location.reload()
                }, 1000);
            }, function (data, ret) {
                console.log('rabbit login error')
            });
            Form.api.bindevent($("#logout-form"), function (data, ret) {
                setTimeout(function () {
                    location.reload()
                }, 1000);
            }, function (data, ret) {
                console.log('rabbit login error')
            });
            Form.api.bindevent($("#add-task-form"), function (data, ret) {
                setTimeout(function () {
                    // location.reload()
                }, 1000);
            }, function (data, ret) {
                console.log('7777')
            });

        },
        recharge: function () {
            Form.api.bindevent($("#recharge-form"), function (data, ret) {
                setTimeout(function () {
                    location.reload()
                }, 1000);
            }, function (data, ret) {
                location.href=ret.url
            });
        }
    };

    return Controller;
});
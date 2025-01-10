<?php
// 银行卡快捷支付页面

if (!defined('IN_PLUGIN'))
    exit();
?>
<html lang="zh-cn">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="renderer" content="webkit" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>银行卡快捷支付</title>
        <link href="<?php echo $cdnpublic ?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet" />
       <style>
            /* 通用设置 */
            body {
                font-family: 'Roboto', sans-serif;
                background-color: #f4f8fb;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }

            .container {
                max-width: 500px;
                width: 100%;
                background-color: #fff;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
            }

            h1 {
                font-size: 28px;
                color: #333;
                text-align: center;
                margin-bottom: 20px;
                font-weight: 500;
            }

            .amount {
                font-size: 32px;
                font-weight: bold;
                color: #4CAF50;
                text-align: center;
                margin-bottom: 20px;
            }

            label {
                font-size: 16px;
                font-weight: bold;
                color: #333;
            }

            .form-control {
                font-size: 16px;
                height: 45px;
                border-radius: 8px;
                padding-left: 15px;
                margin-bottom: 15px;
                transition: all 0.3s ease;
                box-shadow: inset 0 2px 3px rgba(0, 0, 0, 0.05);
            }

            .form-control:focus {
                border-color: #4CAF50;
                box-shadow: 0px 0px 8px rgba(76, 175, 80, 0.2);
                outline: none;
            }

            button {
                width: 100%;
                height: 50px;
                padding: 10px 0;
                font-size: 18px;
                border-radius: 8px;
                transition: all 0.3s ease;
                margin-bottom: 10px;
                cursor: pointer;
                display: inline-block;
                text-align: center;
            }

            button.btn-primary {
                background: linear-gradient(45deg, #4CAF50, #81C784);
                color: #fff;
                border: none;
                box-shadow: 0px 4px 10px rgba(76, 175, 80, 0.3);
            }

            button.btn-primary:hover {
                background: linear-gradient(45deg, #45A049, #66BB6A);
                box-shadow: 0px 4px 12px rgba(76, 175, 80, 0.5);
            }

            button.btn-success {
                background: linear-gradient(45deg, #2196F3, #64B5F6);
                color: #fff;
                border: none;
                box-shadow: 0px 4px 10px rgba(33, 150, 243, 0.3);
            }

            button.btn-success:hover {
                background: linear-gradient(45deg, #1976D2, #42A5F5);
                box-shadow: 0px 4px 12px rgba(33, 150, 243, 0.5);
            }

            button.btn-secondary {
                background-color: #f0f0f0;
                color: #333;
            }

            button:active {
                transform: scale(0.98);
            }

            /* Flexbox 布局 - 验证码和按钮在同一行 */
            .flex-container {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 15px;
            }

            .flex-container .form-control {
                width: 70%; /* 设置输入框宽度 */
            }

            .flex-container button {
                width: 30%; /* 设置按钮宽度 */
            }

            /* 加载动画 */
            .loading-spinner {
                display: none;
                text-align: center;
                margin-bottom: 20px;
            }

            .loading-spinner img {
                width: 50px;
                height: 50px;
            }

            /* 响应式布局 */
            @media (max-width: 768px) {
                h1 {
                    font-size: 24px;
                }

                .container {
                    padding: 20px;
                }

                button {
                    font-size: 16px;
                }
            }

            /* 表单验证提示 */
            .form-group.has-error .form-control {
                border-color: #F44336;
                box-shadow: 0 0 5px rgba(244, 67, 54, 0.3);
            }

            .form-group.has-error label {
                color: #F44336;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="amount">订单金额 ￥<?php echo $order['money']; ?></div>
            
            <form id="bank-form">
                <div class="form-group">
                    <label for="card_number">银行卡号</label>
                    <input type="text" class="form-control" id="card_number" placeholder="请输入银行卡号">
                </div>
                <div class="form-group">
                    <label for="name">姓名</label>
                    <input type="text" class="form-control" id="name" placeholder="请输入姓名">
                </div>
                <div class="form-group">
                    <label for="id_card">身份证号</label>
                    <input type="text" class="form-control" id="id_card" placeholder="请输入身份证号">
                </div>
                <div class="form-group">
                    <label for="phone">手机号</label>
                    <input type="text" class="form-control" id="phone" placeholder="请输入银行卡预留手机号">
                </div>

                <div class="flex-container">
                    <input type="text" class="form-control" id="code" placeholder="请输入验证码" disabled>
                    <button type="button" id="send-code-btn" class="btn btn-primary">发送验证码</button>
                </div>
                <input type="hidden" id="token">
                <button type="button" id="pay-btn" class="btn btn-success" disabled>确定支付</button>
            </form>

        </div>

        <script src="<?php echo $cdnpublic ?>jquery/1.12.4/jquery.min.js"></script>
        <script src="<?php echo $cdnpublic ?>layer/3.1.1/layer.min.js"></script>
        <script>
            $('#send-code-btn').click(function () {
                var card_number = $('#card_number').val();
                var name = $('#name').val();
                var id_card = $('#id_card').val();
                var phone = $('#phone').val();
                var trade_no = '<?php echo $order['trade_no']; ?>';

                if (!card_number || !name || !id_card || !phone) {
                    layer.msg('请填写完整信息');
                    return;
                }

                $('#send-code-btn').prop('disabled', true); // 禁用发送按钮
                $('#loading-spinner').show(); // 显示加载动画

                $.ajax({
                    type: "GET",
                    url: "/getshop.php?act=bank_pay&s=bank_send_sms",
                    data: {
                        trade_no:trade_no,
                        card_number: card_number,
                        name: name,
                        id_card: id_card,
                        phone: phone
                    },
                    success: function (response) {
                        if (response.code == 1) {
                            layer.msg('验证码发送成功');
                            $('#code').prop('disabled', false); // 启用验证码输入框
                            $('#pay-btn').prop('disabled', false); // 启用支付按钮
                            $('#token').val(response.token); // 设置 token 值
                        } else {
                            layer.msg(response.message);
                            $('#send-code-btn').prop('disabled', false); // 重新启用发送按钮
                        }
                    },
                    error: function () {
                        layer.msg('发送验证码失败，请重试');
                        $('#send-code-btn').prop('disabled', false); // 重新启用发送按钮
                    }
                });
            });

            $('#pay-btn').click(function () {
                var card_number = $('#card_number').val();
                var name = $('#name').val();
                var id_card = $('#id_card').val();
                var phone = $('#phone').val();
                var code = $('#code').val();
                var trade_no = '<?php echo $order['trade_no']; ?>';
                var token =  $('#token').val();
                if (!code) {
                    layer.msg('请输入验证码');
                    return;
                }
                $.ajax({
                    type: "GET",
                    url: "/getshop.php?act=bank_pay&s=confirm_sms",
                    data: {
                        card_number: card_number,
                        name: name,
                        id_card: id_card,
                        phone: phone,
                        verify_code: code,
                        token:token,
                        trade_no: trade_no
                    },
                    success: function (response) {
                        if (response.code == 1) {
                            layer.msg('支付成功，正在跳转中...', { icon: 16, shade: 0.1, time: 1500 });
                            setTimeout(function () {
                                window.location.href = response.backurl;
                            }, 1000);
                        } else {
                            layer.msg(response.message);
                        }
                    },
                    error: function () {
                        layer.msg('支付请求失败，请重试');
                        $('#loading-spinner').hide();
                    }
                });
            });
                       // 检测支付状态
            function checkresult() {
                $.ajax({
                    type: "GET",
                    dataType: "json",
                    url: "/getshop.php",
                    data: { type: "bankpay", trade_no: "<?php echo $order['trade_no'] ?>" },
                    success: function (data) {
                        if (data.code == 1) {
                            layer.msg('支付成功，正在跳转中...', { icon: 16, shade: 0.1, time: 15000 });
                            setTimeout(function () {
                                window.location.href = data.backurl;
                            }, 1000);
                        } else {
                            setTimeout("checkresult()", 2000); // 每2秒检测一次
                        }
                    },
                    error: function () {
                        setTimeout("checkresult()", 2000); // 如果出错也继续检测
                    }
                });
            }

            // 页面加载时触发支付状态检测
            window.onload = function () {
                setTimeout("checkresult()", 3000);
            }
        </script>
    </body>
</html>

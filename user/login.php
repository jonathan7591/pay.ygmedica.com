<?php
/**
 * 登录
**/
$is_defend=true;
include("../includes/common.php");

if(isset($_GET['logout'])){
	if(!checkRefererHost())exit();
	setcookie("user_token", "", time() - 2592000);
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登录！');window.location.href='./login.php';</script>");
}elseif($islogin2==1){
	exit("<script language='javascript'>alert('您已登录！');window.location.href='./';</script>");
}
$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8" />
    <title>登录 | <?php echo $conf['sitename']?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <link rel="stylesheet" href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" type="text/css" />
    <link rel="stylesheet" href="<?php echo $cdnpublic?>animate.css/3.5.2/animate.min.css" type="text/css" />
    <link rel="stylesheet" href="<?php echo $cdnpublic?>font-awesome/4.7.0/css/font-awesome.min.css" type="text/css" />
    <link rel="stylesheet" href="<?php echo $cdnpublic?>simple-line-icons/2.4.1/css/simple-line-icons.min.css" type="text/css" />
    <link rel="stylesheet" href="./assets/css/font.css" type="text/css" />
    <link rel="stylesheet" href="./assets/css/app.css" type="text/css" />
    <link rel="stylesheet" href="./assets/css/captcha.css" type="text/css" />
    <style>
        /* 上面提供的CSS样式放在这里 */
/* 基础样式 */
body {
    font-family: 'Helvetica Neue', Arial, sans-serif;
    background: #f4f7fc; /* 淡灰背景色 */
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh; /* 保证页面至少填满视口高度 */
    margin: 0;
    padding: 0;
}

/* 卡片样式 */
.apps {
    background-color: #ffffff;
    border-radius: 16px;
    padding: 40px 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    max-width: 420px;
    width: 100%;
    text-align: center;
    transition: transform 0.3s ease;
    overflow: hidden;
    max-height: 90vh; /* 限制卡片的最大高度，避免过长 */
    overflow-y: auto; /* 如果内容超长，允许滚动 */
}



/* 标题样式 */
.navbar-brand {
    font-size: 32px;
    font-weight: 600;
    color: #004e92;
    margin-bottom: 30px;
}

/* 输入框样式 */
input.form-control {
    /*border-radius: 25px;*/
    padding: 16px;
    margin-bottom: 20px;
    border: 2px solid #ddd;
    font-size: 16px;
    background-color: #f9f9f9;
    transition: border-color 0.3s ease;
}

input.form-control:focus {
    border-color: #004e92;
    outline: none;
    background-color: #ffffff;
}

/* 登录按钮 */
button.btn-primary {
    background: linear-gradient(135deg, #004e92, #00c6ff);
    border: none;
    color: white;
    font-size: 18px;
    padding: 16px;
    border-radius: 30px;
    width: 100%;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

button.btn-primary:hover {
    background: linear-gradient(135deg, #00c6ff, #004e92);
    transform: scale(1.05); /* 鼠标悬停时按钮放大效果 */
}

/* 错误消息框 */
.text-danger {
    color: #e74c3c;
    font-size: 14px;
    margin-bottom: 20px;
}

/* 底部链接 */
.form-group a {
    font-size: 14px;
    color: #004e92;
    text-decoration: none;
    transition: color 0.3s;
}

.form-group a:hover {
    color: #00c6ff;
}

/* 注册和找回密码按钮 */
.btn-info, .btn-danger {
    font-size: 14px;
    padding: 10px 20px;
    border-radius: 25px;
    width: 48%;
    margin: 5px 0;
}

.btn-danger {
    background-color: #e74c3c;
}

.btn-info {
    background-color: #1abc9c;
}

.btn-info:hover, .btn-danger:hover {
    opacity: 0.8;
}

/* 小屏幕适配 */
@media (max-width: 767px) {
    body {
        height: auto;
        padding: 20px;
    }

    .apps {
        padding: 20px;
        max-width: 100%;
    }

    .navbar-brand {
        font-size: 28px;
    }

    input.form-control {
        padding: 12px;
        font-size: 14px;
    }

    button.btn-primary {
        padding: 14px;
        font-size: 14px;
    }

    .form-group a {
        font-size: 12px;
    }

    .btn-info, .btn-danger {
        font-size: 12px;
        padding: 8px 16px;
    }
}

/* 极小屏幕适配 (手机竖屏等) */
@media (max-width: 480px) {
    .apps {
        padding: 15px;
    }

    .navbar-brand {
        font-size: 24px;
        margin-bottom: 20px;
    }

    input.form-control {
        padding: 10px;
        font-size: 14px;
    }

    button.btn-primary {
        padding: 12px;
        font-size: 14px;
    }

    .btn-info, .btn-danger {
        font-size: 12px;
        padding: 6px 12px;
    }
}



    </style>
</head>
<body>
           <div class="apps">
        <span class="navbar-brand"><?php echo $conf['sitename']?></span>
        <form name="form" class="form-validation" method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token?>">
            <div class="text-danger wrapper text-center" ng-show="authError"></div>

            <!-- 登录方式切换 -->
            <?php if(!$conf['close_keylogin']){?>
            <ul class="nav nav-tabs">
                <li style="width: 50%;" align="center" class="<?php echo $_GET['m']!='key'?'active':null;?>">
                    <a href="./login.php" style="color:black;">密码登录</a>
                </li>
                <li style="width: 50%;" align="center" class="<?php echo $_GET['m']=='key'?'active':null;?>">
                    <a href="./login.php?m=key" style="color:black;">密钥登录</a>
                </li>
            </ul>
            <?php }?>
            
            <div class="list-group">
                <?php if($_GET['m']=='key'){ ?>
                    <!-- 密钥登录 -->
                    <input type="hidden" name="type" value="0"/>
                    <div class="list-group-item">
                        <input type="text" name="user" placeholder="商户ID" class="form-control" onkeydown="if(event.keyCode==13){$('#submit').click()}">
                    </div>
                    <div class="list-group-item">
                        <input type="password" name="pass" placeholder="商户密钥" class="form-control" onkeydown="if(event.keyCode==13){$('#submit').click()}">
                    </div>
                <?php } else { ?>
                    <!-- 密码登录 -->
                    <input type="hidden" name="type" value="1"/>
                    <div class="list-group-item">
                        <input type="text" name="user" placeholder="邮箱/手机号" class="form-control" onkeydown="if(event.keyCode==13){$('#submit').click()}">
                    </div>
                    <div class="list-group-item">
                        <input type="password" name="pass" placeholder="密码" class="form-control" onkeydown="if(event.keyCode==13){$('#submit').click()}">
                    </div>
                <?php } ?>

                <?php if($conf['captcha_open_login']==1){ ?>
                <div class="list-group-item" id="captcha">
                    <div id="captcha_text">正在加载验证码</div>
                    <div id="captcha_wait" style="display: none;">
                        <div class="loading">
                            <div class="loading-dot"></div>
                            <div class="loading-dot"></div>
                            <div class="loading-dot"></div>
                            <div class="loading-dot"></div>
                        </div>
                    </div>
                </div>
                <div id="captchaform"></div>
                <?php } ?>
            </div>
            <button type="button" class="btn btn-primary" id="submit">立即登录</button>
        </form>

        <div class="form-group">
            <a href="findpwd.php" class="btn btn-info btn-rounded"><i class="fa fa-unlock"></i>&nbsp;找回密码</a>
            <a href="reg.php" class="btn btn-danger btn-rounded <?php echo $conf['reg_open']==0?'hide':null;?>" style="float:right;">
                <i class="fa fa-user-plus"></i>&nbsp;注册商户
            </a>
        </div>
    </div>
<?php if(!isset($_GET['connect'])){?>
<div class="wrapper text-center">
<?php if($conf['login_alipay']>0 || $conf['login_alipay']==-1){?>
<button type="button" class="btn btn-rounded btn-lg btn-icon btn-default" title="支付宝快捷登录" onclick="connect('alipay')"><img src="../assets/icon/alipay.ico" style="border-radius:50px;"></button>
<?php }?>
<?php if($conf['login_qq']>0){?>
<button type="button" class="btn btn-rounded btn-lg btn-icon btn-default" title="QQ快捷登录" onclick="connect('qq')"><i class="fa fa-qq fa-lg" style="color: #0BB2FF"></i></button>
<?php }?>
<?php if($conf['login_wx']>0 || $conf['login_wx']==-1){?>
<button type="button" class="btn btn-rounded btn-lg btn-icon btn-default" title="微信快捷登录" onclick="connect('wx')"><i class="fa fa-wechat fa-lg" style="color: green"></i></button>
</div>
<?php }?>
<?php }?>
</form>
</div>
<div class="text-center" style="position:fixed;bottom:0;">
<p>
<small class="text-muted"><a href="/"><?php echo $conf['sitename']?></a><br>&copy; 2016~<?php echo date("Y")?></small>
</p>
</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="//static.geetest.com/static/tools/gt.js"></script>
<script>
   var captcha_open = 0;
        var handlerEmbed = function (captchaObj) {
            captchaObj.appendTo('#captcha');
            captchaObj.onReady(function () {
                $("#captcha_wait").hide();
            }).onSuccess(function () {
                var result = captchaObj.getValidate();
                if (!result) {
                    return alert('请完成验证');
                }
                $("#captchaform").html('<input type="hidden" name="geetest_challenge" value="'+result.geetest_challenge+'" /><input type="hidden" name="geetest_validate" value="'+result.geetest_validate+'" /><input type="hidden" name="geetest_seccode" value="'+result.geetest_seccode+'" />');
                $.captchaObj = captchaObj;
            });
        };

        $(document).ready(function(){
            if($("#captcha").length>0) captcha_open=1;
            $("#submit").click(function(){
                var type = $("input[name='type']").val();
                var user = $("input[name='user']").val();
                var pass = $("input[name='pass']").val();
                if(user == '' || pass == '') { 
                    layer.alert(type == 1 ? '账号和密码不能为空！' : 'ID和密钥不能为空！');
                    return false;
                }
                submitLogin(type, user, pass);
            });

            if(captcha_open == 1){
                $.ajax({
                    url: "./ajax.php?act=captcha&t=" + (new Date()).getTime(),
                    type: "get",
                    dataType: "json",
                    success: function (data) {
                        $('#captcha_text').hide();
                        $('#captcha_wait').show();
                        initGeetest({
                            gt: data.gt,
                            challenge: data.challenge,
                            new_captcha: data.new_captcha,
                            product: "popup",
                            width: "100%",
                            offline: !data.success
                        }, handlerEmbed);
                    }
                });
            }
        });

        function submitLogin(type, user, pass) {
            var csrf_token = $("input[name='csrf_token']").val();
            var data = {type: type, user: user, pass: pass, csrf_token: csrf_token};
            if(captcha_open == 1) {
                var geetest_challenge = $("input[name='geetest_challenge']").val();
                var geetest_validate = $("input[name='geetest_validate']").val();
                var geetest_seccode = $("input[name='geetest_seccode']").val();
                if(geetest_challenge == "") {
                    layer.alert('请先完成滑动验证！');
                    return false;
                }
                var adddata = {geetest_challenge: geetest_challenge, geetest_validate: geetest_validate, geetest_seccode: geetest_seccode};
            }
            var ii = layer.load();
            $.ajax({
                type: "POST",
                dataType: "json",
                data: Object.assign(data, adddata),
                url: "ajax.php?act=login",
                success: function (data, textStatus) {
                    layer.close(ii);
                    if (data.code == 0) {
                        layer.msg(data.msg, {icon: 16,time: 10000,shade:[0.3, "#000"]});
                        setTimeout(function(){ window.location.href=data.url }, 1000);
                    } else {
                        layer.alert(data.msg, {icon: 2});
                        $.captchaObj.reset();
                    }
                },
                error: function (data) {
                    layer.msg('服务器错误', {icon: 2});
                    return false;
                }
            });
        }
    </script>
</body>
</html>
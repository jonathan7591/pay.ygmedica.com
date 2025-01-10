<?php
if(!defined('IN_CRONLITE'))exit();?>
<style>tbody tr>td:nth-child(1){width:180px}</style>
<div class="panel panel-primary">

<div class="panel-heading"><h3 class="panel-title">当前配置查询<span class="pull-right"><a class="btn btn-default btn-xs" href="./applyments_form.php?type=page&action=wxconf&id=<?php echo $row['id']?>">刷新</a></span></h3></div>
<div class="panel-body">
<p><b>已关联的公众号AppID：</b></p>
<p><?php foreach($data['appid_config_list'] as $rows){ echo $rows['sub_appid'].'<br/>';}?></p>
<p><b>JSAPI支付授权目录：</b></p>
<p><?php foreach($data['jsapi_path_list'] as $rows){ echo $rows.'<br/>';}?></p>
</div>
</div>
<div class="panel panel-success">
<div class="panel-heading"><h3 class="panel-title">关联公众号AppID配置</span></h3></div>

<div class="panel-body">


	<form action="./applyments_form.php?type=page&action=wxconf&id=<?php echo $row['id']?>" method="POST" class="form-horizontal" role="form">
		<input type="hidden" name="conf_key" value="appid">
		<div class="form-group">
			<label class="col-sm-3 control-label">公众号AppID</label>
			<div class="col-sm-7"><input type="text" name="conf_value" value="" placeholder="只能填写已认证的服务号，且必须与当前主体或渠道商主体一致" class="form-control" required></div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-3 col-sm-7"><input type="submit" name="submit" value="新增" class="btn btn-primary form-control"><br>
		</div>
		</div>
	</form>
</div>
</div>
<div class="panel panel-success">
<div class="panel-heading"><h3 class="panel-title">JSAPI支付授权目录配置</h3></div>
<div class="panel-body">
	<form action="./applyments_form.php?type=page&action=wxconf&id=<?php echo $row['id']?>" method="POST" class="form-horizontal" role="form">
		<input type="hidden" name="conf_key" value="path">
		<div class="form-group">
			<label class="col-sm-3 control-label">JSAPI支付授权目录</label>
			<div class="col-sm-7"><input type="text" name="conf_value" value="<?php echo $siteurl?>" placeholder="必须以http://或https://开头，以/结尾" class="form-control" required></div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-3 col-sm-7"><input type="submit" name="submits" value="新增" class="btn btn-primary form-control"><br>
		</div>
		</div>
	</form>
</div>
</div>
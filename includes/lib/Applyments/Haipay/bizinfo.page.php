<?php
if(!defined('IN_CRONLITE'))exit();?>
<style>tbody tr>td:nth-child(1){width:180px}</style>
<div class="panel panel-primary">
<div class="panel-heading"><h3 class="panel-title">查看商户报备信息<span class="pull-right"><a class="btn btn-default btn-xs" href="./applyments_form.php?type=page&action=bizinfo&id=<?php echo $row['id']?>">刷新</a></span></h3></div>
<div class="panel-body">
<div class="alert alert-info">当前页面可查看支付宝、微信、银联子商户的报备状态。其中支付宝、微信的子商户报备成功后，需要提交实名认证申请，并由法人扫码授权后，才能正常支付。</div>

<?php if(isset($data['ali'])){?> 
<table class="table table-hover table-bordered">
	<tbody>
		<tr><td colspan="2"><strong style="font-size:large">支付宝支付</strong></td></tr>
		<tr><td><b>支付宝报备状态</b></td><td><?php echo $data['ali']['status_text']?></td></tr>
		<?php if(isset($data['ali']['reason'])){?><tr><td><b>报备失败原因</b></td><td><font color="red"><?php echo $data['ali']['reason']?></font></td></tr><?php }?>
		<?php if(isset($data['ali']['reason'])){?><tr><td></td><td><br/><?php echo '<a class="btn btn-primary btn-sm" href="javascript:submit(\'apply_merchant_ali\')">商户报备</a>'?></td></tr><?php }?>
		<?php if(isset($data['ali']['sub_mch_id'])){?><tr><td><b>支付宝子商户号</b></td><td><?php echo $data['ali']['sub_mch_id']?></td></tr><?php }?>
		<?php if($data['ali']['status']=='3'){?><tr><td><b>实名认证申请单</b></td><td>
			状态：<?php echo $data['ali']['real_status_text']?> <?php if(!empty($data['ali']['real_reason'])){?>（原因：<font color="red"><?php echo $data['ali']['real_reason']?></font>）<?php }?>
			<?php if(!$data['ali']['real_status'] || $data['ali']['real_status']=='CANCELED'){ echo '<br/><a class="btn btn-primary btn-sm" href="javascript:submit(\'submitAliRealName\')">提交申请单</a>'; }elseif($data['ali']['real_status']=='AUDIT_REJECT'||$data['ali']['real_status']=='AUDIT_FREEZE'){ echo '<br/><a class="btn btn-info btn-sm" href="javascript:submit(\'queryAliRealName\')">刷新</a>  <a class="btn btn-warning btn-sm" href="javascript:submit(\'cancelAliRealName\')">关闭申请单</a>'; }else{ echo '<br/><a class="btn btn-info btn-sm" href="javascript:submit(\'queryAliRealName\')">刷新</a>'; } ?>
		</td></tr><?php }?>
	</tbody>
</table><hr/>
<?php }?>

<?php if(isset($data['wx'])){?> 
<table class="table table-hover table-bordered">
	<tbody>
		<tr><td colspan="2"><strong style="font-size:large">微信支付</strong></td></tr>
		<tr><td><b>微信报备状态</b></td><td><?php echo $data['wx']['status_text']?></td></tr>
		<?php if(isset($data['wx']['reason'])){?><tr><td><b>报备失败原因</b></td><td><font color="red"><?php echo $data['wx']['reason']?></font></td></tr><?php }?>
		<?php if(isset($data['wx']['reason'])){?><tr><td></td><td><br/><?php echo '<a class="btn btn-primary btn-sm" href="javascript:submit(\'apply_merchant_wx\')">商户报备</a>'?></td></tr><?php }?>
		<?php if(isset($data['wx']['sub_mch_id'])){?><tr><td><b>微信子商户号</b></td><td><?php echo $data['wx']['sub_mch_id']?></td></tr><?php }?>
		<?php if($data['wx']['status']=='3'){?><tr><td><b>实名认证申请单</b></td><td>
			状态：<?php echo $data['wx']['real_status_text']?> <?php if(!empty($data['wx']['real_reason'])){?>（原因：<font color="red"><?php echo $data['wx']['real_reason']?></font>）<?php }?>
			<?php if(!$data['wx']['real_status']){ echo '<br/><a class="btn btn-primary btn-sm" href="javascript:submit(\'submitWxRealName\')">提交申请单</a> <a class="btn btn-default btn-sm" href="javascript:submit(\'queryWxAuth\')">查询实名</a>'; }elseif($data['wx']['real_status']=='APPLYMENT_STATE_REJECTED'||$data['wx']['real_status']=='APPLYMENT_STATE_FREEZED' || $data['applyment_state'] == 'APPLYMENT_STATE_FREEZED'){ echo '<br/><a class="btn btn-info btn-sm" href="javascript:submit(\'queryWxRealName\')">刷新</a> <a class="btn btn-warning btn-sm" href="javascript:submit(\'cancelWxRealName\')">撤销申请单</a>'; }else{ echo '<br/><a class="btn btn-info btn-sm" href="javascript:submit(\'queryWxRealName\')">刷新</a>'; } ?>
		</td></tr><?php }?>
	</tbody>
</table><hr/>
<?php }?>

<?php if(isset($data['bank'])){?> 
<table class="table table-hover table-bordered">
	<tbody>
		<tr><td colspan="2"><strong style="font-size:large">银联支付</strong></td></tr>
		<tr><td><b>银联报备状态</b></td><td><?php echo $data['bank']['status_text']?></td></tr>
		<?php if(isset($data['bank']['reason'])){?><tr><td><b>报备失败原因</b></td><td><font color="red"><?php echo $data['bank']['reason']?></font></td></tr><?php }?>
		<?php if(isset($data['bank']['sub_mch_id'])){?><tr><td><b>银联子商户号</b></td><td><?php echo $data['bank']['sub_mch_id']?></td></tr><?php }?>
	</tbody>
</table><hr/>
<?php }?>

</div>
</div>
<?php register_shutdown_function(function() use($row, $cdnpublic){?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script>
function submit(action) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=query',
		data : {action:action, id:<?php echo $row['id']?>},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg, {icon: 1}, function(){
					layer.closeAll();
					if(data.update){
						location.reload();
					}
					if(data.qrcode_url){
						layer.open({
							type: 1,
							title: '请使用微信扫描以下二维码',
							shadeClose: true,
							content: '<div class="text-center"><img height="240px" src="'+data.qrcode_url+'"></div>'
						});
					}else if(data.alipay_qrcode_url){
						layer.open({
							type: 1,
							title: '请使用支付宝扫描以下二维码',
							shadeClose: true,
							content: '<div class="text-center"><img height="240px" src="'+data.alipay_qrcode_url+'"></div>'
						});
					}
				});
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		}
	});
	return false;
}
</script>
<?php });?>
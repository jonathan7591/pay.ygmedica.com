<?php
if(!defined('IN_CRONLITE'))exit();?>
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
<?php
/**
 * 进件商户管理
**/
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='进件商户管理';
include './head.php';

$channel_select = '<option value="0">全部进件渠道</option>';
$typelist = \lib\Applyments\CommUtil::getTypeList();
$channels = $DB->getAll("SELECT * FROM pre_applychannel where status=1 order by sort asc");
foreach($channels as $row){
	$channel_select .= '<option value="'.$row['id'].'">'.$row['name'].'</option>';
}
?>
<style>
.fixed-table-toolbar,.fixed-table-pagination{padding: 15px;}
.form-inline .form-control{display:inline-block;width:auto;vertical-align:middle}
.form-inline .form-group{display:inline-block;margin-bottom:0;vertical-align:middle}
.type-logo{width:18px;margin-top:-2px;padding-right:4px}
.channel-box{display:flex;flex-wrap:wrap;justify-content:space-between}
.channel-item{width:48%;margin-bottom:10px;padding:10px;border:1px solid #e5e5e5;border-radius:5px;cursor:pointer;font-size:14px;color:#777}
.channel-item:hover{background-color:#f5f5f5}
.channel-name{font-size:16px;font-weight:700;margin-bottom:3px;color:#555}
.channel-item .channel-name img{width:18px;height:18px;margin-right:5px;margin-top:-2px}
.channel-item .channel-price{font-size:12px;color:#ad35e3;padding-top:3px}
.tips{color:#f6a838;padding-left:5px}
tbody tr>td:nth-child(4){overflow: hidden;text-overflow: ellipsis;white-space: nowrap;max-width:180px;}
tbody tr>td:nth-child(5){overflow: hidden;text-overflow: ellipsis;white-space: nowrap;max-width:200px;}
</style>

<div class="modal" id="modal-store" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">请选择进件渠道</h4>
			</div>
			<div class="modal-body">
				<div class="channel-box">
					<?php foreach($channels as $row){?>
					<div class="channel-item" onclick="location.href='applyments_form.php?cid=<?php echo $row['id']?>'">
						<div class="channel-name"><img src="/assets/icon/<?php echo $typelist[$row['type']]['icon']?>" onerror="this.style.display='none'"><?php echo $row['name']?></div>
						<?php echo $row['desc']?><div class="channel-price">价格：<?php if($row['price']>0){ ?><?php echo $row['price']?>元<span class="tips" title="" data-toggle="tooltip" data-placement="bottom" data-original-title="进件成功后从余额扣费，进件不成功不扣费"><i class="fa fa-question-circle"></i></span><?php }else{?>免费<?php }?></div>
					</div>
					<?php }?>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
			</div>
		</div>
	</div>
</div>

<div id="content" class="app-content" role="main">
    <div class="app-content-body ">

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">商户进件</h1>
</div>
<div class="wrapper-md control">
<?php if(isset($msg)){?>
<div class="alert alert-info">
	<?php echo $msg?>
</div>
<?php }?>
<div class="panel panel-default">
		<div class="panel-heading font-bold">
			进件管理
		</div>
<form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
<input type="hidden" class="form-control" name="id">
<input type="hidden" class="form-control" name="batch">
  <div class="form-group">
    <label>搜索</label>
	<select name="type" class="form-control"><option value="1">申请单号</option><option value="2">第三方单号</option><option value="3">商户名称</option><option value="4">子商户号</option></select>
  </div>
  <div class="form-group">
    <input type="text" class="form-control" name="kw" placeholder="搜索内容">
  </div>
  <div class="form-group">
    <select name="cid" class="form-control"><?php echo $channel_select?></select>
  </div>
  <div class="form-group">
	<select name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="0">待提交</option><option value="1">审核中</option><option value="2">待签约</option><option value="3">进件失败</option><option value="4">已完成</option><option value="5">审核中</option><option value="6">审核失败</option><option value="7">已注销</option></select>
  </div>
  <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 搜索</button>
  <a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-refresh"></i> 重置</a>
  <a href="javascript:addframe()" class="btn btn-success"><i class="fa fa-plus"></i> 新增商户进件</a>
</form>

<table id="listTable">
</table>

    </div>
  </div>
</div>
<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="../assets/js/bootstrap-table.min.js"></script>
<script src="../assets/js/bootstrap-table-page-jump-to.min.js"></script>
<script src="<?php echo $cdnpublic?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
$(document).ready(function(){
	updateToolbar();

	$("#listTable").bootstrapTable({
		url: 'ajax_applyments.php?act=merchantList',
		pageNumber: 1,
		pageSize: 10,
        sidePagination: 'client',
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: 'id',
				title: 'ID',
				formatter: function(value, row, index) {
					return '<b>'+value+'</b>';
				}
			},
			{
				field: 'channelname',
				title: '进件渠道',
				formatter: function(value, row, index) {
					return '<img src="/assets/icon/'+row.typeicon+'" class="type-logo" onerror="this.style.display=\'none\'">' + value;
				}
			},
			{
				field: 'orderid',
				title: '申请单号/子商户号',
				formatter: function(value, row, index) {
					return value + '<br/>' + (row.mchid ? '<font color="green">'+row.mchid+'</font>' : '');
				}
			},
			{
				field: 'mchname',
				title: '商户名称/商户类型',
				formatter: function(value, row, index) {
					if(row.mchtype==1){
						return value + '<br/>个人';
					}else if(row.mchtype==2){
						return value + '<br/>个体工商户';
					}else if(row.mchtype==3){
						return value + '<br/>企业';
					}else{
						return value + '<br/>未知';
					}
				}
			},
			{
				field: 'addtime',
				title: '创建时间/更新时间',
				formatter: function(value, row, index) {
					return value + '<br/>' + row.updatetime;
				}
			},
			{
				field: 'status',
				title: '状态',
				formatter: function(value, row, index) {
					if(value == '7'){
						return '<span class="label" style="background-color: #a5a5a5;">已注销</span>';
					}else if(value == '6'){
						return '<span class="label label-danger">审核失败</span>' + (row.reason ? ' <span title="'+row.reason+'" data-toggle="tooltip" data-placement="right"><i class="fa fa-info-circle"></i></span>' : '');
					}else if(value == '5'){
						return '<span class="label label-info">审核中</span> <a href="javascript:queryStatus('+row.id+')"><i class="fa fa-refresh" title="刷新状态"></i></a>';
					}else if(value == '4'){
						return '<span class="label label-success">已完成</span>';
					}else if(value == '3'){
						return '<span class="label label-danger">进件失败</span>' + (row.reason ? ' <span title="'+row.reason+'" data-toggle="tooltip" data-placement="right"><i class="fa fa-info-circle"></i></span>' : '');
					}else if(value == '2'){
						return '<span class="label label-warning">待签约</span> <a href="javascript:queryStatus('+row.id+')" title="刷新状态"><i class="fa fa-refresh"></i></a>';
					}else if(value == '1'){
						return '<span class="label label-info">审核中</span> <a href="javascript:queryStatus('+row.id+')" title="刷新状态"><i class="fa fa-refresh"></i></a>';
					}else{
						return '<span class="label label-primary">待提交</span>';
					}
				}
			},
			{
				field: 'subchannel_count',
				title: '支付开关',
				formatter: function(value, row, index) {
					if(row.status >= 4 || value > 0){
						var html = row.subchannel_open>0?'<a class="btn btn-xs btn-success" onclick="setPayStatus('+row.id+',0)">已开启</a>':'<a class="btn btn-xs btn-danger" onclick="setPayStatus('+row.id+',1)">已关闭</a>';
						if(value > 1){
							html += ' <a onclick="showChannelStatus('+row.uid+','+row.id+')" class="btn btn-xs btn-info"><i class="fa fa-cog" title="子通道状态"></i></a>';
						}
						return html;
					}
					return '';
				}
			},
			{
				field: '',
				title: '收款统计',
				formatter: function(value, row, index) {
					if(row.subchannel){
						return '今日:<a onclick="getAll(0,\''+row.subchannel+'\',this)" title="点此获取最新数据">[刷新]</a><br/>昨日:<a onclick="getAll(1,\''+row.subchannel+'\',this)" title="点此获取最新数据">[刷新]</a>';
					}
				}
			},
			{
				field: '',
				title: '操作',
				formatter: function(value, row, index) {
					let html = '';
					if(row.status == 3){
						html += '<a class="btn btn-xs btn-info" href="applyments_form.php?action=create&id='+row.id+'">修改</a>';
					}else if(row.status == 0){
						html += '<a class="btn btn-xs btn-info" href="applyments_form.php?action=create&id='+row.id+'">继续填写</a>';
					}else{
						html += '<a class="btn btn-xs btn-primary" href="applyments_form.php?action=view&id='+row.id+'">详情</a>';
					}
					html += '&nbsp;<div class="btn-group dropdown-group" role="group"><button type="button" class="btn btn-warning btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">更多 <span class="caret"></span></button><ul class="dropdown-menu">';
					$.each(row.operation, function(index, item){
						if(item.type == 'form'){
							html += '<li><a href="applyments_form.php?action='+item.action+'&id='+row.id+'">'+item.title+'</a></li>';
						}else if(item.type == 'page'){
							html += '<li><a href="applyments_form.php?type=page&action='+item.action+'&id='+row.id+'">'+item.title+'</a></li>';
						}else if(item.type == 'jsfunc'){
							html += '<li><a href="javascript:'+item.action+'('+row.id+')">'+item.title+'</a></li>';
						}else if(item.type == 'query'){
							html += '<li><a href="javascript:queryCommon(\''+item.action+'\', '+row.id+')">'+item.title+'</a></li>';
						}
					});
					if(row.subchannel){
						html += '<li><a href="order.php?subchannel='+row.subchannel+'">查询订单</a></li>';
					}
					if(row.status == 0 || row.status == 3){
						html += '<li><a href="javascript:delItem('+row.id+')">删除</a></li>';
					}
					html += '</ul></div>';
					return html;
				}
			},
		],
		onLoadSuccess: function(data) {
			$('[data-toggle="tooltip"]').tooltip()
			$('.dropdown-group').on('show.bs.dropdown', function (e) {
				var btnPos = $(e.target)[0].getBoundingClientRect();
				var screenWidth = $(window).width();
				var screenHeight = $(window).height();
				var childrenWidth = $(e.target).children('.dropdown-menu').width();
				var childrenHeight = $(e.target).children('.dropdown-menu').height();
				var top = btnPos.bottom;
				if(top + childrenHeight + 12 > screenHeight){
					top = btnPos.top - childrenHeight - 12;
				}
				var left = btnPos.left;
				if(left + childrenWidth + 7 > screenWidth){
					left = screenWidth - childrenWidth - 7;
				}
				$(e.target).children('.dropdown-menu').css({position:'fixed', top:top, left:left});
			});
		}
	})

	$('[data-toggle="tooltip"]').tooltip();
})

function addframe(){
	$("#modal-store").modal('show');
}

function delItem(id) {
	var confirmobj = layer.confirm('确定要删除此进件记录吗？删除后将无法使用该商户发起支付', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=delMerchant',
		data : {id:id},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				layer.closeAll();
				searchSubmit();
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
function setPayStatus(id,status) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=setPayStatus',
		data : {id:id, status:status},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				searchSubmit();
			}else{
				layer.msg(data.msg, {icon:2, time:1500});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function queryStatus(id) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=query',
		data : {id:id},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				var code = data.update ? data.res == 1 ? 6 : 5 : 0;
				layer.alert(data.msg, {icon: code}, function(){
					layer.closeAll();
					if(data.update){
						searchSubmit();
					}
					showqrcode(data);
				});
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function showqrcode(data){
	if(data.jump_url){
		window.open(data.jump_url);
	}else if(data.qrcode_url){
		layer.open({
			type: 1,
			title: '请使用微信扫描以下二维码',
			shadeClose: true,
			content: '<div class="text-center"><img height="240px" src="'+data.qrcode_url+'"></div>'
		});
	}else if(data.sign_url){
		layer.open({
			type: 1,
			title: '请使用微信扫描以下二维码',
			shadeClose: true,
			content: '<div id="qrcode" class="list-group-item text-center"></div>',
			success: function(){
				$('#qrcode').qrcode({
					text: data.sign_url,
					width: 230,
					height: 230,
					foreground: "#000000",
					background: "#ffffff",
					typeNumber: -1
				});
			}
		});
	}else if(data.alipay_qrcode_url){
		layer.open({
			type: 1,
			title: '请使用支付宝扫描以下二维码',
			shadeClose: true,
			content: '<div class="text-center"><img height="240px" src="'+data.alipay_qrcode_url+'"></div>'
		});
	}else if(data.alipay_sign_url){
		layer.open({
			type: 1,
			title: '请使用支付宝扫描以下二维码',
			shadeClose: true,
			content: '<div id="qrcode" class="list-group-item text-center"></div>',
			success: function(){
				$('#qrcode').qrcode({
					text: data.alipay_sign_url,
					width: 230,
					height: 230,
					foreground: "#000000",
					background: "#ffffff",
					typeNumber: -1
				});
			}
		});
	}else if(data.alipay_auth_url){
		$.alipayauthform = layer.open({
			type: 1,
			title: '请使用支付宝扫描以下二维码',
			shadeClose: true,
			content: '<div id="qrcode" class="list-group-item text-center"></div>',
			success: function(){
				$('#qrcode').qrcode({
					text: data.alipay_auth_url,
					width: 230,
					height: 230,
					foreground: "#000000",
					background: "#ffffff",
					typeNumber: -1
				});
				$.ostart = true;
				setTimeout('checkalipayauth('+id+')', 2000);
			},
			end: function(){
				$.ostart = false;
			}
		});
	}
}
function checkalipayauth(id){
	$.ajax({
		type: "GET",
		dataType: "json",
		url: "ajax_applyments.php?act=getalipayauth&id="+id,
		success: function (data, textStatus) {
			if (data.code == 0) {
				layer.msg('支付宝应用授权成功');
				layer.close($.alipayauthform);
				searchSubmit();
			}else if($.ostart==true){
				setTimeout('checkalipayauth('+id+')', 1500);
			}else{
				return false;
			}
		},
		error: function (data) {
			layer.msg('服务器错误', {icon: 2});
			return false;
		}
	});
}
function queryCommon(action, id) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=query',
		data : {action:action, id:id},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg, {icon: 1}, function(){
					layer.closeAll();
					showqrcode(data);
				});
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
function getAll(type, channel, obj){
	var ii = layer.load();
	$.ajax({
		type : 'GET',
		url : 'ajax_applyments.php?act=getSubChannelMoney&type='+type+'&channel='+channel,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$(obj).html(data.money);
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function showChannelStatus(uid, id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_applyments.php?act=subchannel_list&uid='+uid+'&id='+id,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			var content = '<table class="table table-striped"><thead><tr><th>ID</th><th>支付方式</th><th>状态</th><th>操作</th></tr></thead><tbody>';
			$.each(data, function(i, item){
				content += '<tr><td>'+item.id+'</td><td><img src="/assets/icon/'+item.type+'.ico" width="16" onerror="this.style.display=\'none\'"> '+item.typename+'</td><td>'+(item.status==1?'<a class="btn btn-xs btn-success" onclick="setChannelStatus(this,'+item.id+',0)">已开启</a>':'<a class="btn btn-xs btn-danger" onclick="setChannelStatus(this,'+item.id+',1)">已关闭</a>')+'</td><td><a href="./order.php?subchannel='+item.id+'" target="_blank" class="btn btn-xs btn-default">订单</a></td></tr>';
			});
			content += '</tbody></table>';
			layer.open({
				type: 1,
				closeBtn:2,
				shadeClose:true,
				title: '单独支付方式开关',
				content: content,
				area: [$(window).width() > 350 ? '350px' : '100%', ';max-height:100%']
			});
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function setChannelStatus(obj,id,status) {
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=setChannelStatus',
		data : {id:id, status:status},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(status == 1){
				$(obj).removeClass('btn-danger').addClass('btn-success').html('已开启').attr('onclick', 'setChannelStatus(this,'+id+',0)');
			}else{
				$(obj).removeClass('btn-success').addClass('btn-danger').html('已关闭').attr('onclick', 'setChannelStatus(this,'+id+',1)');
			}
		}
	});
}
</script>
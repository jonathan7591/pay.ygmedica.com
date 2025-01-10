<?php
/**
 * 进件渠道管理
**/
include("../includes/common.php");
$title='进件渠道管理';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

preg_match("#^\d.\d#", PHP_VERSION, $p_v);
if($p_v[0] < 8.0 || $p_v[0] >= 8.1 || !extension_loaded('swoole_loader')){
	include 'loader_helper.php';
	exit;
}

$type_select = '<option value="0">进件渠道类型</option>';
$typelist = \lib\Applyments\CommUtil::getTypeList();
foreach($typelist as $key=>$row){
	$type_select .= '<option value="'.$key.'" data-pay-plugin="'.$row['pay_plugin'].'">'.$row['name'].'</option>';
}

$group_select = '<option value="0">不限用户组</option>';
$rs = $DB->getAll("SELECT * FROM pre_group where gid>0");
foreach($rs as $row){
	$group_select.='<option value="'.$row['gid'].'">'.$row['name'].'</option>';
}
unset($rs);
?>
<style>
.form-inline .form-control {
    display: inline-block;
    width: auto;
    vertical-align: middle;
}
.form-inline .form-group {
    display: inline-block;
    margin-bottom: 0;
    vertical-align: middle;
}
.type-logo{width: 18px;margin-top: -2px;padding-right: 4px;}
</style>

<div class="modal" id="modal-store" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">进件渠道修改/添加</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="form-store">
					<input type="hidden" name="action"/>
					<input type="hidden" name="id"/>
					<div class="form-group">
						<label class="col-sm-3 control-label no-padding-right">名称</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="name" placeholder="用户中心展示使用，不要与其他通道名称重复">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label no-padding-right">简介</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="desc" placeholder="用户中心展示使用，非必填">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">进件渠道类型</label>
						<div class="col-sm-9">
							<select name="type" class="form-control" onchange="changeType()">
								<?php echo $type_select; ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">关联支付通道</label>
						<div class="col-sm-9">
							<select name="channel" id="channel" class="form-control">
							</select>
							<font color="green" id="channelnote"></font>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label no-padding-right">进件价格</label>
						<div class="col-sm-9">
							<div class="input-group"><input type="text" class="form-control" name="price" placeholder="0或留空为免费"><span class="input-group-addon">元</span></div>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label no-padding-right">排序号</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="sort" placeholder="用户中心展示的排序，越小越靠前">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">用户组限制</label>
						<div class="col-sm-9">
							<select name="gid" class="form-control">
								<?php echo $group_select; ?>
							</select>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary" id="store" onclick="save()">保存</button>
			</div>
		</div>
	</div>
</div>

  <div class="container" style="padding-top:70px;">
  <div class="row">
    <div class="col-md-12 center-block" style="float: none;">
<form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
<input type="hidden" class="form-control" name="id">
<input type="hidden" class="form-control" name="batch">
  <div class="form-group">
	<label>搜索</label>
    <input type="text" class="form-control" name="kw" placeholder="渠道ID/名称">
  </div>
  <div class="form-group">
    <select name="type" class="form-control"><?php echo $type_select?></select>
  </div>
  <div class="form-group">
	<select name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="1">状态已开启</option><option value="0">状态已关闭</option></select>
  </div>
  <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 搜索</button>
  <a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-refresh"></i> 重置</a>
  <a href="javascript:addframe()" class="btn btn-success"><i class="fa fa-plus"></i> 新增</a>
</form>

<table id="listTable">
</table>

    </div>
  </div>
</div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="../assets/js/bootstrap-table.min.js"></script>
<script src="../assets/js/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
$(document).ready(function(){
	updateToolbar();

	$("#listTable").bootstrapTable({
		url: 'ajax_applyments.php?act=channelList',
		pageNumber: 1,
		pageSize: 15,
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
				field: 'name',
				title: '名称'
			},
			{
				field: 'typename',
				title: '进件渠道类型',
				formatter: function(value, row, index) {
					return '<img src="/assets/icon/'+row.typeicon+'" class="type-logo" onerror="this.style.display=\'none\'">' + value;
				}
			},
			{
				field: 'channelname',
				title: '关联支付通道',
				formatter: function(value, row, index) {
					return value+'('+row.channel+')';
				}
			},
			{
				field: 'price',
				title: '价格'
			},
			{
				field: 'sort',
				title: '排序号'
			},
			{
				field: 'status',
				title: '状态',
				formatter: function(value, row, index) {
					if(value == '1'){
						return '<a class="btn btn-xs btn-success" onclick="setStatus('+row.id+',0)">已开启</a>';
					}else{
						return '<a class="btn btn-xs btn-warning" onclick="setStatus('+row.id+',1)">已关闭</a>';
					}
				}
			},
			{
				field: '',
				title: '操作',
				formatter: function(value, row, index) {
					return '<a class="btn btn-xs btn-primary" href="applyments_form.php?action=config&cid='+row.id+'">进件配置</a>&nbsp;<a class="btn btn-xs btn-info" onclick="editframe('+row.id+')">编辑</a>&nbsp;<a class="btn btn-xs btn-danger" onclick="delItem('+row.id+')">删除</a>';
				}
			},
		],
	})
})

function changeType(channel){
	channel = channel || null;
	if(channel == null){
		channel = $("#form-store select[name=channel]").val();
	}
	var type = $("#form-store select[name=type]").val();
	if(type==0)return;
	$("#channel").empty();
	var pay_plugin = $("#form-store select[name=type] option[value="+type+"]").data('pay-plugin');
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=getChannelsByPlugin&plugin='+pay_plugin,
		dataType : 'json',
		success : function(data) {
			$("#channelnote").text('选择'+pay_plugin+'插件的支付通道');
			if(data.code == 0){
				$.each(data.data, function (i, res) {
					$("#channel").append('<option value="'+res.id+'">'+res.name+'('+res.id+')</option>');
				})
				if(channel!=null)$("#channel").val(channel);
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function addframe(){
	$("#modal-store").modal('show');
	$("#modal-title").html("新增进件渠道");
	$("#action").val("add");
	$("#form-store input[name=action]").val("add");
	$("#form-store input[name=id]").val('');
	$("#form-store input[name=name]").val('');
	$("#form-store input[name=desc]").val('');
	$("#form-store select[name=type]").val(0);
	$("#form-store input[name=price]").val('');
	$("#form-store input[name=sort]").val('');
	$("#form-store select[name=gid]").val(0);
	$("#form-store select[name=type]").change();
}
function editframe(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_applyments.php?act=getChannel&id='+id,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#modal-store").modal('show');
				$("#modal-title").html("修改进件渠道");
				$("#form-store input[name=action]").val("edit");
				$("#form-store input[name=id]").val(data.data.id);
				$("#form-store input[name=name]").val(data.data.name);
				$("#form-store input[name=desc]").val(data.data.desc);
				$("#form-store select[name=type]").val(data.data.type);
				$("#form-store input[name=price]").val(data.data.price);
				$("#form-store input[name=sort]").val(data.data.sort);
				$("#form-store select[name=gid]").val(data.data.gid);
				changeType(data.data.channel);
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
function save(){
	if($("#form-store input[name=name]").val()==''){
		layer.alert('必填项不能为空！');return false;
	}
	if($("#form-store select[name=type]").val()==0){
		layer.alert('请选择进件渠道类型！');return false;
	}
	if($("#form-store select[name=channel]").val()==0 || $("#form-store select[name=channel]").val()==null){
		layer.alert('请选择关联支付通道！');return false;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=saveChannel',
		data : $("#form-store").serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg,{
					icon: 1,
					closeBtn: false
				}, function(){
					layer.closeAll();
					$("#modal-store").modal('hide');
					searchSubmit();
				});
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
function delItem(id) {
	var confirmobj = layer.confirm('你确实要删除此进件渠道吗？', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=delChannel',
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
function setStatus(id,status) {
	$.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=setChannel',
		data : {id:id, status:status},
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				searchSubmit();
			}else{
				layer.msg(data.msg, {icon:2, time:1500});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function editInfo(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_applyments.php?act=channelConfig&id='+id,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				var area = [$(window).width() > 520 ? '520px' : '100%', ';max-height:100%'];
				layer.open({
				  type: 1,
				  area: area,
				  title: '进件配置',
				  skin: 'layui-layer-rim',
				  content: data.data
				});
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
function saveInfo(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_applyments.php?act=saveChannelConfig&id='+id,
		data : $("#form-info").serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg,{
					icon: 1,
					closeBtn: false
				}, function(){
					layer.closeAll();
				});
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
</script>
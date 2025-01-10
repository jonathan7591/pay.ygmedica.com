<?php
include("../includes/common.php");
if($islogin2==1){}else exit('{"code":-3,"msg":"No Login"}');
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

$groupconfig = getGroupConfig($userrow['gid']);
$conf = array_merge($conf, $groupconfig);

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'merchantList':
	$typelist = \lib\Applyments\CommUtil::getTypeList();
	$sql=" A.uid=$uid";
	if(isset($_POST['id']) && !empty($_POST['id'])) {
		$id = intval($_POST['id']);
		$sql.=" AND A.`id`='$id'";
	}
	if(isset($_POST['cid']) && !empty($_POST['cid'])) {
		$cid = intval($_POST['cid']);
		$sql.=" AND A.`cid`='$cid'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND A.`status`={$dstatus}";
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw=daddslashes($_POST['kw']);
		if($_POST['type']==1){
			$sql.=" AND A.`orderid`='{$kw}'";
		}elseif($_POST['type']==2){
			$sql.=" AND A.`thirdid`='{$kw}'";
		}elseif($_POST['type']==3){
			$sql.=" AND A.`mchname` like '%{$kw}%'";
		}elseif($_POST['type']==4){
			$sql.=" AND A.`mchid`='{$kw}'";
		}
	}
	$list = $DB->getAll("SELECT A.*,B.channel,B.name channelname,B.type,B.config FROM pre_applymerchant A LEFT JOIN pre_applychannel B ON A.cid=B.id WHERE{$sql} ORDER BY id DESC");
	$list2 = [];
    foreach($list as $row){
		$subchannel = $DB->findAll('subchannel', 'id,status', ['uid'=>$row['uid'], 'apply_id'=>$row['id']]);
		$subchannelid = [];
		foreach($subchannel as $sub){
			$subchannelid[] = $sub['id'];
		}
		$row['typename'] = $typelist[$row['type']]['name'];
		$row['typeicon'] = $typelist[$row['type']]['icon'];
		$row['subchannel_count'] = count($subchannel);
        $row['subchannel_open'] = array_count_values(array_column($subchannel, 'status'))[1];
		$row['subchannel'] = implode('|', $subchannelid);
		$row['operation'] = \lib\Applyments\CommUtil::getOperation($row['type'], $row);
		unset($row['info']);
		unset($row['ext']);
        $list2[] = $row;
    }
	exit(json_encode($list2));
break;

case 'subchannel_list':
	$id = intval($_GET['id']);
	
	$paytype = [];
	$paytypes = [];
	$rs = $DB->getAll("SELECT * FROM pre_type ORDER BY id ASC");
	foreach($rs as $row){
		$paytype[$row['id']] = $row['showname'];
		$paytypes[$row['id']] = $row['name'];
	}
	unset($rs);

	$list = $DB->getAll("SELECT A.*,B.type FROM pre_subchannel A LEFT JOIN pre_channel B ON A.channel=B.id WHERE A.uid='$uid' AND apply_id='$id' ORDER BY A.id ASC");
	$list2 = [];
	foreach($list as $row){
		$list2[] = ['id'=>$row['id'], 'type'=>$paytypes[$row['type']], 'typename'=>$paytype[$row['type']], 'channel'=>$row['channel'], 'status'=>$row['status']];
	}
	exit(json_encode($list2));
break;
case 'setChannelStatus':
	$id=intval($_POST['id']);
	$status=intval($_POST['status']);
	$row=$DB->find('subchannel', '*', ['id'=>$id]);
	if(!$row) exit('{"code":-1,"msg":"该子通道不存在"}');
	$DB->update('subchannel', ['status'=>$status], ['id'=>$id]);
	exit(json_encode(['code'=>0, 'msg'=>'修改成功']));
break;

case 'getFormData':
	$action = isset($_POST['action'])?$_POST['action']:'create';
	$cid = isset($_POST['cid'])?intval($_POST['cid']):0;
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	if(!$cid) exit('{"code":-1,"msg":"进件渠道ID不能为空"}');
	if($id > 0){
		$row = $DB->find('applymerchant', '*', ['id'=>$id, 'uid'=>$uid]);
		if(!$row) exit('{"code":-1,"msg":"该商户进件信息不存在"}');
	}

	$model = \lib\Applyments\CommUtil::getModel($cid);
	if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');
	$result = $model->getFormData($action, $row?$row['info']:null);
	exit(json_encode($result));
break;

case 'submit':
	$action = isset($_POST['action'])?$_POST['action']:null;
	$cid = isset($_POST['cid'])?intval($_POST['cid']):0;
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	$data = json_decode($_POST['data'], true);
	if(!$action || !$cid || !$data) exit('{"code":-1,"msg":"参数错误"}');
	if($id > 0){
		$row = $DB->find('applymerchant', '*', ['id'=>$id, 'uid'=>$uid]);
		if(!$row) exit('{"code":-1,"msg":"该进件信息不存在"}');
	}
	
	$model = \lib\Applyments\CommUtil::getModel($cid);
	if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');
	if($action == 'create'){
		$data['uid'] = $uid;
		$result = $model->create($data);
	}else{
		if(method_exists($model, $action)){
			$result = $model->$action($row, $data);
		}else{
			exit('{"code":-1,"msg":"未知的操作类型"}');
		}
	}
	exit(json_encode($result));
break;

case 'query':
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	$action = isset($_POST['action']) ? $_POST['action'] : 'query';
	if(!$action) exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->find('applymerchant', '*', ['id'=>$id, 'uid'=>$uid]);
	if(!$row) exit('{"code":-1,"msg":"该进件信息不存在"}');
	$model = \lib\Applyments\CommUtil::getModel($row['cid']);
	if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');
	if(method_exists($model, $action)){
		$result = $model->$action($row);
		exit(json_encode($result));
	}else{
		exit('{"code":-1,"msg":"未知的操作类型"}');
	}
break;

case 'uploadImage':
	if(!isset($_FILES['file']))exit('{"code":-1,"msg":"请选择图片"}');
	$cid = isset($_POST['cid'])?intval($_POST['cid']):0;
	$type = isset($_POST['type'])?$_POST['type']:null;

	$model = \lib\Applyments\CommUtil::getModel($cid);
	if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');

	try{
		$data = \lib\Applyments\CommUtil::imageRecognize($type, $_FILES['file']['tmp_name']);
	}catch(Exception $e){
		exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
	}

	if($type == 'wx_cancel'){
		$result = $model->uploadCancelApplyImage($_FILES['file']['tmp_name'], $_FILES['file']['name']);
	}else{
		$result = $model->uploadImage($_FILES['file']['tmp_name'], $_FILES['file']['name']);
	}
	$result['data'] = $data;
	exit(json_encode($result));
break;

case 'uploadFile':
	if(!isset($_FILES['file']))exit('{"code":-1,"msg":"请选择文件"}');
	$cid = isset($_POST['cid'])?intval($_POST['cid']):0;
	$type = isset($_POST['type'])?$_POST['type']:null;
	
	$model = \lib\Applyments\CommUtil::getModel($cid);
	if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');
	$result = $model->uploadFile($_FILES['file']['tmp_name'], $_FILES['file']['name']);
	exit(json_encode($result));
break;

case 'checkBankCard':
	$cardno = isset($_POST['cardno'])?trim($_POST['cardno']):null;
	try{
		$data = getBankCardInfo($cardno);
		exit(json_encode(['code'=>0, 'data'=>$data]));
	}catch(Exception $e){
		exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
	}
break;

case 'getBankList':
	$cid = isset($_POST['cid'])?intval($_POST['cid']):0;
	$page = isset($_POST['page'])?intval($_POST['page']):1;
	$limit = isset($_POST['limit'])?intval($_POST['limit']):10;
	$keyword = isset($_POST['keyword'])?trim($_POST['keyword']):null;
	$keyid = isset($_POST['keyid'])?trim($_POST['keyid']):null;
	$model = \lib\Applyments\CommUtil::getModel($cid);
	if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');
	$result = $model->getBankList($page, $limit, $keyword, $keyid);
	exit(json_encode($result));
break;

case 'getBankBranchList':
	$cid = isset($_POST['cid'])?intval($_POST['cid']):0;
	$bank_code = isset($_POST['bank_code'])?trim($_POST['bank_code']):null;
	$city_code = isset($_POST['city_code'])?trim($_POST['city_code']):null;
	if(empty($bank_code)) exit('{"code":-1,"msg":"银行编码不能为空"}');
	if(empty($city_code)) exit('{"code":-1,"msg":"城市编码不能为空"}');
	$model = \lib\Applyments\CommUtil::getModel($cid);
	if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');
	$result = $model->getBankBranchList($bank_code, $city_code);
	exit(json_encode($result));
break;

case 'setPayStatus':
	$id=intval($_POST['id']);
	$status=intval($_POST['status']);
	$row=$DB->find('applymerchant', '*', ['id'=>$id, 'uid'=>$uid]);
	if(!$row) exit('{"code":-1,"msg":"该进件信息不存在"}');
	if($status==1 && empty($row['mchid'])) exit('{"code":-1,"msg":"未完成进件，无法开启支付"}');
	$model = \lib\Applyments\CommUtil::getModel($row['cid']);
	if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');
	$result = $model->setPayStatus($row, $status);
	exit(json_encode($result));
break;

case 'delMerchant':
	$id=intval($_POST['id']);
	$row=$DB->find('applymerchant', '*', ['id'=>$id, 'uid'=>$uid]);
	if(!$row) exit('{"code":-1,"msg":"该进件信息不存在"}');
	if($row['status']>3 || $row['status']==1) exit('{"code":-1,"msg":"只支持删除待提交和失败状态的记录"}');
	if($DB->delete('applymerchant', ['id'=>$id])){
		$DB->delete('subchannel', ['apply_id'=>$id]);
		exit('{"code":0,"msg":"删除进件商户成功！"}');
	}
	else exit('{"code":-1,"msg":"删除进件商户失败['.$DB->error().']"}');
break;

case 'getSubChannelMoney': //统计子通道金额
	$type=intval($_GET['type']);
	$channel=trim($_GET['channel']);
	$row = $DB->find('subchannel', '*', ['uid'=>$uid, 'id'=>$channel]);
	if(!$row) exit('{"code":-1,"msg":"该子通道不存在"}');
	$today=$type==1 ? date("Y-m-d", strtotime("-1 day")) : date("Y-m-d");
	$channel = explode('|', $channel);
	$channel = array_map('intval', $channel);
	$money=$DB->getColumn("SELECT SUM(realmoney) FROM pre_order WHERE date='$today' AND subchannel IN (".implode(",", $channel).") AND status>0");
	exit('{"code":0,"msg":"succ","money":"'.round($money,2).'"}');
break;

case 'getalipayauth':
	$id=intval($_GET['id']);
	$row=$DB->find('applymerchant', '*', ['id'=>$id, 'uid'=>$uid]);
	if(!$row) exit('{"code":-1,"msg":"该进件信息不存在"}');
	if(isset($_SESSION['alipay_app_token']) && !empty($_SESSION['alipay_app_token'])){

		$data['app_auth_token'] = $_SESSION['alipay_app_token'];
		$data['user_id'] = $_SESSION['alipay_user_id'];
		$data['auth_app_id'] = $_SESSION['alipay_app_id'];
		unset($_SESSION['alipay_app_token']);
		unset($_SESSION['alipay_user_id']);
		unset($_SESSION['alipay_app_id']);

		$model = \lib\Applyments\CommUtil::getModel($row['cid']);
		if(!$model)exit('{"code":-1,"msg":"进件渠道不存在"}');
		$result = $model->setmchid($row, $data);

		$result=array("code"=>0,"msg"=>"支付宝应用授权成功");
	}else{
		$result=array("code"=>-1);
	}
	exit(json_encode($result));
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
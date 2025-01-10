<?php
/**
 * 商户进件表单
 */
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='商户进件表单';
include './head.php';
?>
<style>
</style>
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
<div class="row">
<div class="col-lg-10 col-md-12 center-block" style="float: none;">
<?php
$type = isset($_GET['type'])?$_GET['type']:'form';
$action = isset($_GET['action'])?$_GET['action']:'create';
$cid = isset($_GET['cid'])?intval($_GET['cid']):0;
$id = isset($_GET['id'])?intval($_GET['id']):0;
if($id > 0){
    $row = $DB->find('applymerchant', '*', ['id'=>$id, 'uid'=>$uid]);
    if(!$row) showmsg('该商户进件信息不存在');
    $cid = $row['cid'];
}
if(!$cid) showmsg('请选择进件渠道',3);

$applychannel = $DB->find('applychannel', '*', ['id'=>$cid, 'status'=>1]);
if(!$applychannel) showmsg('该进件渠道不存在',3);
if($action == 'create'){
    if($applychannel['gid'] > 0 && $userrow['gid'] != $applychannel['gid']){
        $group = $DB->find('group', '*', ['gid'=>$applychannel['gid']]);
        showmsg('该进件渠道仅限《'.$group['name'].'》等级会员进件，请先<a href="./groupbuy.php">购买会员</a>',2);
    }
    /*if($applychannel['price'] > 0 && $userrow['money'] < $applychannel['price']){
        showmsg('进件价格为'.$applychannel['price'].'元，您的余额不足，请先<a href="./recharge.php">充值</a>！<br/>进件成功后从余额扣费，进件不成功不扣费',2);
    }*/
}

try{
    $model = \lib\Applyments\CommUtil::getModel($cid);
}catch(Exception $e){
	showmsg($e->getMessage());
}

if($type == 'page'){
    if(method_exists($model, $action)){
        $model->$action($row);
    }else{
        showmsg('未知的操作类型',3);
    }
}else{
    $title = \lib\Applyments\CommUtil::getFormTitle($action);
    if(!$title) showmsg('未知的操作类型',3);
    
    include(SYSTEM_ROOT.'lib/Applyments/form.php');
}
?>
</div>
</div>
<?php
include 'foot.php';
if($type == 'form'){
    include(SYSTEM_ROOT.'lib/Applyments/form_foot.php');
}
?>
<?php
/**
 * 商户进件表单
 */
include("../includes/common.php");
$title='商户进件表单';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<style>
</style>
<div class="container" style="padding-top:70px;">
<div class="row">
<div class="col-lg-10 col-md-12 center-block" style="float: none;">
<?php
$type = isset($_GET['type'])?$_GET['type']:'form';
$action = isset($_GET['action'])?$_GET['action']:'create';
$cid = isset($_GET['cid'])?intval($_GET['cid']):0;
$id = isset($_GET['id'])?intval($_GET['id']):0;
if($id > 0){
    $row = $DB->find('applymerchant', '*', ['id'=>$id]);
    if(!$row) showmsg('该商户进件信息不存在');
    $cid = $row['cid'];
}
if(!$cid) showmsg('请选择进件渠道',3);

try{
    $model = \lib\Applyments\CommUtil::getModel($cid);
}catch(Exception $e){
	showmsg($e->getMessage());
}
if(!$model) showmsg('进件渠道或关联支付通道不存在',3);

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
if($type == 'form'){
    include(SYSTEM_ROOT.'lib/Applyments/form_foot.php');
}
?>
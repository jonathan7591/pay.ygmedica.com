<?php
namespace lib\Applyments\Haipay;

use Exception;
use lib\Applyments\CommUtil;
use lib\Applyments\IApplyments;

require_once PLUGIN_ROOT.'haipay/inc/HaiPayClient.php';

class Haipay implements IApplyments
{
    private $applychannel;
    private $channel;
    private $service;
    private $basedir = SYSTEM_ROOT.'/lib/Applyments/Haipay/';
    private static $status = ['1'=>'提交失败','2'=>'已受理','3'=>'自动审核', '4'=>'待审核', '5'=>'审核失败', '6'=>'审核拒绝', '7'=>'审核成功', '8'=>'运营审核退回', '9'=>'运营审核退回未处理完'];
    private static $reportStatus = ['0'=>'待报备','1'=>'报备中','2'=>'报备失败','3'=>'报备成功'];
    private static $aliRealNameStatus = ['AUDITING'=>'审核中','CONTACT_CONFIRM'=>'待联系人确认','LEGAL_CONFIRM'=>'待法人确认','AUDIT_PASS'=>'审核通过','AUDIT_REJECT'=>'审核失败','AUDIT_FREEZE'=>'已冻结','CANCELED'=>'已撤回'];
    private static $wxRealNameStatus = ['APPLYMENT_STATE_EDITTING'=>'编辑中','APPLYMENT_STATE_WAITTING_FOR_AUDIT'=>'审核中','APPLYMENT_STATE_WAITTING_FOR_CONFIRM_CONTACT'=>'待确认联系信息','APPLYMENT_STATE_WAITTING_FOR_CONFIRM_LEGALPERSON'=>'待账户验证','APPLYMENT_STATE_PASSED'=>'审核通过','APPLYMENT_STATE_REJECTED'=>'审核驳回','APPLYMENT_STATE_FREEZED'=>'已冻结','APPLYMENT_STATE_CANCELED'=>'已作废'];

    function __construct($applychannel, $channel){
		$this->applychannel = $applychannel;
        $this->channel = $channel;
        $this->service = new \HaiPayClient($channel['accessid'], $channel['accesskey'],false);
	}

    public static function getOperation($row){
        global $islogin;
        $data = [];
        if($row['status'] == 4 || $row['status'] == 6){
           
            $data[] = ['title'=>'查看商户报备信息', 'type'=>'page', 'action'=>'bizinfo'];
            $data[] = ['title'=>'微信公众号配置', 'type'=>'page', 'action'=>'wxconf'];
            $data[] = ['title'=>'修改商户信息', 'type'=>'form', 'action'=>'modify'];
            $data[] = ['title'=>'修改结算账户', 'type'=>'form', 'action'=>'settlement'];
        }
        return $data;
    }

    //获取表单数据
    public function getFormData($action, $info = null){
        global $DB, $islogin, $siteurl;
        $info = $info ? json_decode($info, true) : [];
        $config = json_decode($this->applychannel['config'], true);
        
        if($action == 'config'){
            $file_path = $this->basedir.'form_config.json';
            $info = $config;
        }elseif($action == 'settlement'){
            $file_path = $this->basedir.'form_settlement.json';
        }elseif($action == 'create' || $action == 'view' || $action == 'modify'){
            $file_path = $this->basedir.'form_create.json';
        }else{
            return ['code'=>-1, 'msg'=>'未知的操作类型'];
        }
        $file = file_get_contents($file_path);
        if(strpos($file, '${mcc}')){
            $file = str_replace('"${mcc}"', file_get_contents($this->basedir.'mcc.json'), $file);
        }
        if(strpos($file, '${city}')){
            $file = str_replace('"${city}"', file_get_contents($this->basedir.'city.json'), $file);
        }
        $form = json_decode($file, true);

        foreach($form['items'] as &$step){
            foreach($step as &$item){
                if($item['type'] == 'alert'){
                    $item['content'] = str_replace(['[siteurl]','[channel]'], [$siteurl,$this->channel['id']], $item['content']);
                }
                if(!isset($item['name'])) continue;
                if($item['name'] == 'uid' && (!$islogin || $action != 'create')){
                    $item['type'] = 'hidden';
                }
                if(isset($info[$item['name']])){
                    $item['value'] = $info[$item['name']];
                }
                if($action == 'view'){
                    $item['disabled'] = true;
                }
                if($action == 'modify' && $item['name'] == 'merchant_type'){
                    $item['disabled'] = true;
                }
            }
        }
        return ['code'=>0, 'data'=>$form];
    }

    private function getSubmitParams($config, $data, $mch_id = null){
        $merchant_type = $this->getMchType($data['merchant_type']);
        if($data['idcard_period_end'] == '2999-12-31' || $data['idcard_period_end'] == '长期') $data['idcard_period_end'] = '9999-12-31';
        if($data['merchant_type'] == '1'){ //小微商户
            $data['merchant_name'] = $data['idcard_name'];
            $merchant_data = [
                'merch_type' => $merchant_type,
                'merch_short_name' => $data['short_name'],
                'shop_name' => $data['short_name'],
                'province_code' => $data['shop_address'][2],
                'city_code' => $data['shop_address'][1],
                'area_code' => $data['shop_address'][0],
                'address' => $data['shop_address_detail'],
                'legal_name' => $data['idcard_name'],
                'legal_cert_type' => '1',
                'legal_cert_no' => $data['idcard_no'],
                'legal_cert_start' => $data['idcard_period_begin'],
                'legal_cert_end' => $data['idcard_period_end'],
                'contact_name' => $data['idcard_name'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'],
                'service_phone' => $data['contact_phone'],
                'bus_scope_code' => $data['mcc'],
            ];
            $bankcard_data = [
                'acc_type' => '10B',
                'acc_no' => $data['card_no'],
                'acc_name' => $data['card_name'],
                'idcard_no' => $data['idcard_no'],
                'phone' => $data['bank_phone'],
                'bank_code' => $data['bank_branch_code'],
                'bank_province_code' => $data['bank_address'][1],
                'bank_city_code' => $data['bank_address'][0],
            ];
            $image_data = [
                'A2' => $data['idcard_image'],
                'A3' => $data['idcard_back_image'],
                'A5' => $data['shop_entrance_pic'],
                'A6' => $data['shop_indoor_pic'],
                'A31' => $data['shop_indoor_pic2'],
                'A4' => $data['bank_card_image'],
            ];
        }else{ //企业/个体户商户
            if($data['license_period_end'] == '2999-12-31' || $data['license_period_end'] == '长期') $data['license_period_end'] = '9999-12-31';
            $merchant_data = [
                'merch_type' => $merchant_type,
                'merch_name' => $data['merchant_name'],
                'merch_short_name' => $data['short_name'],
                'shop_name' => $data['short_name'],
                'merchant_cert_type' => '0',
                'bus_license_no' => $data['license_no'],
                'bus_license_start' => $data['license_period_begin'],
                'bus_license_end' => $data['license_period_end'],
                'bus_province_code' => $data['shop_address'][2],
                'bus_city_code' => $data['shop_address'][1],
                'bus_area_code' => $data['shop_address'][0],
                'bus_address' => $data['license_address'],
                'province_code' => $data['shop_address'][2],
                'city_code' => $data['shop_address'][1],
                'area_code' => $data['shop_address'][0],
                'address' => $data['shop_address_detail'],
                'legal_name' => $data['idcard_name'],
                'legal_cert_type' => '1',
                'legal_cert_no' => $data['idcard_no'],
                'legal_cert_start' => $data['idcard_period_begin'],
                'legal_cert_end' => $data['idcard_period_end'],
                'contact_name' => $data['idcard_name'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'],
                'service_phone' => $data['contact_phone'],
                'bus_scope_code' => $data['mcc'],
            ];
            $bankcard_data = [
                'acc_type' => $data['card_type'] == '0' ? '10A' : '10B',
                'acc_no' => $data['card_no'],
                'acc_name' => $data['card_name'],
                'bank_code' => $data['bank_branch_code'],
                'bank_province_code' => $data['bank_address'][1],
                'bank_city_code' => $data['bank_address'][0],
            ];
            $image_data = [
                'A1' => $data['license_image'],
                'A2' => $data['idcard_image'],
                'A3' => $data['idcard_back_image'],
                'A5' => $data['shop_entrance_pic'],
                'A6' => $data['shop_indoor_pic'],
                'A31' => $data['shop_indoor_pic2'],
                'A4' => $data['card_type'] == '0' ? $data['bank_license_image'] : $data['bank_card_image'],
            ];
            if($data['card_type'] == '0'){
                $bankcard_data['bank_name'] = $data['bank_branch_name'];
            }else{
                $bankcard_data += [
                    'idcard_no' => $data['idcard_no'],
                    'phone' => $data['bank_phone'],
                ];
            }
            if($data['card_type'] == '2'){
                $bankcard_data['idcard_no'] = $data['auth_idcard_no'];
                $image_data += [
                    'A9999' => $data['settle_auth_image'],
                    'A32' => $data['settle_auth_hold_image'],
                    'A35' => $data['auth_idcard_image'],
                    'A36' => $data['auth_idcard_back_image'],
                ];
            }
        }
        $product_data = [];
        if(!empty($config['paytypes'])){
            if(in_array('ali', $config['paytypes'])){
                $product_data['ali'] = [
                    'channel_no' => $config['alipay_channel_no'],
                    'rate' => strval($config['alipay_rate'] * 100),
                ];
            }
            if(in_array('wx', $config['paytypes'])){
                $product_data['wx'] = [
                    'channel_no' => $config['wxpay_channel_no'],
                    'rate' => strval($config['wxpay_rate'] * 100),
                ];
            }
            if(in_array('bank', $config['paytypes'])){
                $product_data['bank'] = [
                    'union_debit_rate' => strval($config['bank_debit_rate'] * 100),
                    'union_credit_rate' => strval($config['bank_credit_rate'] * 100),
                    'union_mix_rate' => strval($config['bank_debit_rate'] * 100),
                ];
            }
        }
        if($config['settle_cycle'] == 'T1'){
            $settle_data = [
                'settlement_cycle' => 'T1',
                'withdrawal_rate' => '0',
                'withdrawal_feemin' => '0',
                'public_withdrawal_rate' => '0',
                'public_withdrawal_feemin' => '0',
            ];
        }else{
            $settle_data = [
                'settlement_cycle' => $config['settle_cycle'],
                'withdrawal_rate' => $config['settle_rate'] ? $config['settle_rate'] : '0',
                'withdrawal_feemin' => $config['settle_feemin'] ? $config['settle_feemin'] : '0',
                'public_withdrawal_rate' => $config['settle_rate'] ? $config['settle_rate'] : '0',
                'public_withdrawal_feemin' => $config['settle_feemin'] ? $config['settle_feemin'] : '0',
            ];
        }

        $params = [
            'agent_no' => $this->channel['agent_no'],
            'agent_apply_no' => $data['order_id'],
            'merchant_data' => $merchant_data,
            'bankcard_data' => $bankcard_data,
            'image_data' => $image_data,
            'product_data' => $product_data,
            'settle_data' => $settle_data,
        ];
        if(!empty($mch_id)){
            $params['merch_no'] = $mch_id;
        }elseif(!empty($data['mch_id'])){
            $params['merch_no'] = $data['mch_id'];
        }
        return $params;
    }

    //商户创建
    public function create($data){
        global $conf, $DB;
        self::getcheck();
        $config = json_decode($this->applychannel['config'], true);
        if(empty($config['paytypes'])){
            return ['code'=>-1, 'msg'=>'未配置默认开通的支付方式'];
        }

        $data['order_id'] = date('YmdHis').rand(10,99);
        $params = $this->getSubmitParams($config, $data);

        //echo json_encode($params);exit;
        try{
            $result = $this->service->mchRequest('/api/v2/merchant/aggregation-apply', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户申请单提交失败，'.$e->getMessage(), 'orderid'=>$data['order_id']];
        }

        $data['mch_id'] = $result['merch_no'];

        if($row = $DB->find('applymerchant', '*', ['cid'=>$this->applychannel['id'], 'mchid'=>$result['merch_no']])){
            $DB->update('applymerchant', [
                'thirdid' => $data['order_id'],
                'mchtype' => $data['merchant_type'],
                'mchname' => $data['merchant_type'] == '1' ? $data['idcard_name'] : $data['merchant_name'],
                'updatetime' => 'NOW()',
                'status' => 1,
                'info' => json_encode($data),
            ], ['id'=>$row['id']]);
        }else{
            $DB->insert('applymerchant', [
                'cid' => $this->applychannel['id'],
                'uid' => $data['uid'] ? $data['uid'] : 0,
                'orderid' => $data['order_id'],
                'thirdid' => $data['order_id'],
                'mchid' => $result['merch_no'],
                'mchtype' => $data['merchant_type'],
                'mchname' => $data['merchant_type'] == '1' ? $data['idcard_name'] : $data['merchant_name'],
                'addtime' => 'NOW()',
                'updatetime' => 'NOW()',
                'status' => 1,
                'paid' => $this->applychannel['price'] == 0 ? 1 : 0,
                'info' => json_encode($data),
            ]);
        }

        return ['code'=>0, 'msg'=>'商户申请单提交成功！请等待审核', 'orderid'=>$data['order_id']];
    }

    //申请单进度查询
    public function query($row){
        global $DB;
        $params = [
            'agent_no' => $this->channel['agent_no'],
        ];
        if(!empty($row['mchid'])){
            $params['merch_no'] = $row['mchid'];
        }else{
            $params['agent_apply_no'] = $row['thirdid'];
        }
        try{
            $result = $this->service->mchRequest('/api/v1/sub/query-merchant-info', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'申请单查询失败，'.$e->getMessage()];
        }

        if($result['status'] == '7' || ($result['status'] == '3' || $result['status'] == '4' || $result['status'] == '8' || $result['status'] == '9') && ($result['wx_biz_info']['report_status'] >= '2' || $result['ali_biz_info']['report_status'] >= '2')){

            if($row['status'] == 5){
                $DB->update('applymerchant', ['status'=>4, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
                return ['code'=>0, 'update'=>true, 'res'=>1, 'msg'=>'申请单已审核通过！'];
            }
            $terminal_res = $this->addterminal($row);
            if($terminal_res['code'] == -1){
                return ['code'=>-1, 'msg'=>$terminal_res['msg']];
            }
            $ext = json_decode($row['ext'], true);
            $ext['pn'] = $terminal_res['pn'];

            $config = json_decode($this->applychannel['config'], true);
            if(!empty($config['wxpay_appid'])){
                $this->wxconfadd($result['merch_no'], 'appid', $config['wxpay_appid']);
            }
            if(!empty($config['wxpay_path'])){
                $this->wxconfadd($result['merch_no'], 'path', $config['wxpay_path']);
            }

            $DB->update('applymerchant', ['status'=>4, 'mchid'=>$result['merch_no'], 'ext'=>json_encode($ext), 'updatetime'=>'NOW()'], ['id'=>$row['id']]);

            if($row['paid'] == 0 && !empty($row['uid']) && $this->applychannel['price'] > 0){
                CommUtil::payForMerchant($row['id'], $row['uid'], $this->applychannel['price']);
            }
            return ['code'=>0, 'update'=>true, 'res'=>1, 'msg'=>'申请单已审核通过', 'mchid'=>$result['merch_no'], 'result'=>$result];
        }elseif($result['status'] == '1' || $result['status'] == '5' || $result['status'] == '6'){
            $status = $row['status'] == 5 ? 6 : 3;
            if(empty($result['fail_msg'])) $result['fail_msg'] = self::$status[$result['status']];
            $DB->update('applymerchant', ['status'=>$status, 'reason'=>$result['fail_msg'], 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
            return ['code'=>0, 'update'=>true, 'res'=>0, 'msg'=>'申请单审核失败，原因：'.$result['fail_msg']];
        }else{
            $msg = self::$status[$result['status']];
            return ['code'=>0, 'msg'=>'申请单正在审核中（当前状态：'.$msg.'），请耐心等待'];
        }
    }

    //新增终端
    private function addterminal($row){
        $info = json_decode($row['info'], true);

        $terminal_address = $info['shop_address_name'][2].'-'.$info['shop_address_name'][1].'-'.$info['shop_address_name'][0].' '.$info['shop_address_detail'];
        if(mb_strlen($terminal_address) > 60){
            $terminal_address = mb_substr($terminal_address, 0, 60);
        }
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
            'agent_apply_no' => date('YmdHis').rand(100,999),
            'sn' => $row['orderid'],
            'code' => '11',
            'terminal_address' => $terminal_address,
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/merchant-terminal/new-bind', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户终端绑定失败，'.$e->getMessage()];
        }
        return ['code'=>0, 'msg'=>'商户终端绑定成功！', 'pn'=>$result['pn']];
    }

    //商户业务开通查询
    public function queryReport($row){
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/sub/query-merchant-info', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户报备状态查询失败，'.$e->getMessage()];
        }
        $msg = '';
        if(!empty($result['ali_biz_info'])){
            $msg .= '支付宝报备状态：'.self::$reportStatus[$result['ali_biz_info']['report_status']];
            if($result['ali_biz_info']['report_status'] == '2' && !empty($result['ali_biz_info']['fail_msg'])){
                $msg .= '（'.$result['fail_msg'].'）';
            }
            if(!empty($result['ali_biz_info']['alisub_merch_no'])){
                $msg .= '<br/>支付宝子商户号：'.$result['ali_biz_info']['alisub_merch_no'];
            }
            $msg .= '<br/>';
        }
        if(!empty($result['wx_biz_info'])){
            $msg .= '微信报备状态：'.self::$reportStatus[$result['wx_biz_info']['report_status']];
            if($result['wx_biz_info']['report_status'] == '2' && !empty($result['wx_biz_info']['fail_msg'])){
                $msg .= '（'.$result['fail_msg'].'）';
            }
            if(!empty($result['wx_biz_info']['wxsub_merch_no'])){
                $msg .= '<br/>微信子商户号：'.$result['wx_biz_info']['wxsub_merch_no'];
            }
            $msg .= '<br/>';
        }
        if(!empty($result['bank_biz_info'])){
            $msg .= '银联报备状态：'.self::$reportStatus[$result['bank_biz_info']['report_status']];
            if($result['bank_biz_info']['report_status'] == '2' && !empty($result['bank_biz_info']['fail_msg'])){
                $msg .= '（'.$result['fail_msg'].'）';
            }
            if(!empty($result['bank_biz_info']['banksub_merch_no'])){
                $msg .= '<br/>银联子商户号：'.$result['bank_biz_info']['banksub_merch_no'];
            }
            $msg .= '<br/>';
        }
        return ['code'=>0, 'msg'=>$msg];
    }

    //商户信息修改
    public function modify($row, $data){
        global $conf, $DB;
        $config = json_decode($this->applychannel['config'], true);
        if(empty($config['paytypes'])){
            return ['code'=>-1, 'msg'=>'未配置默认开通的支付方式'];
        }

        $data['order_id'] = date('YmdHis').rand(10,99);
        $params = $this->getSubmitParams($config, $data, $row['mchid']);

        try{
            $result = $this->service->mchRequest('/api/v2/merchant/aggregation-apply', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户修改提交失败，'.$e->getMessage()];
        }

        $info = json_decode($row['info'], true);
        $info = array_merge($info, $data);

        $DB->update('applymerchant', ['thirdid'=>$data['order_id'], 'status'=>5, 'updatetime'=>'NOW()', 'info' => json_encode($info)], ['id'=>$row['id']]);

        return ['code'=>0, 'msg'=>'商户修改提交成功！请等待审核'];
    }
    
    //业务申请开通
    public function apply_merchant_ali($row){
         global $conf, $DB;
         $id = $_GET['id'];
         $config = json_decode($this->applychannel['config'], true);
         $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
            'rate'=>strval($config['alipay_rate'] * 100),
            'channel_no'=>$config['alipay_channel_no'],
        ];
       
        log_debug('支付宝业务申请参数'.json_encode($params,JSON_UNESCAPED_SLASHES).'id:'.$id,'haipay');
        try{
            $result = $this->service->mchRequest('/api/v1/merchant-ali-biz/apply', $params);
            
            log_debug('支付宝业务申请返回'.json_encode($result),'haipay');
            
            return ['code'=>0,'msg'=>'申请成功'];
        }catch(Exception $e){
            return ['code'=>-1,'msg'=>$e->getMessage()];
            // showmsg('商户报备申请失败，'.$e->getMessage(),4);
        }
    }
    
     //业务申请开通
    public function apply_merchant_wx($row){
         global $conf, $DB;
         $id = $_GET['id'];
         $config = json_decode($this->applychannel['config'], true);
         $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
            'rate'=>strval($config['wxpay_rate'] * 100),
            'channel_no'=>$config['wxpay_channel_no'],
        ];
       
        log_debug('微信业务申请参数'.json_encode($params,JSON_UNESCAPED_SLASHES).'id:'.$id,'haipay');
        try{
            $result = $this->service->mchRequest('/api/v1/merchant-wx-biz/apply', $params);
           
            log_debug('微信业务申请返回'.json_encode($result),'haipay');
            
            return ['code'=>0,'msg'=>'申请成功'];
        }catch(Exception $e){
            return ['code'=>-1,'msg'=>$e->getMessage()];
            // showmsg('商户报备申请失败，'.$e->getMessage(),4);
        }
    }
    
    //查看商户报备信息
    public function bizinfo($row){
        global $DB, $cdnpublic;

        $ext = json_decode($row['ext'], true);

        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/sub/query-merchant-info', $params);
        }catch(Exception $e){
            showmsg('商户报备状态查询失败，'.$e->getMessage(),4);
        }
        $data = [];
        $sub_mchid = [];
        if(!empty($result['ali_biz_info'])){
            $data['ali']['status'] = $result['ali_biz_info']['report_status'];
            $data['ali']['status_text'] = self::$reportStatus[$result['ali_biz_info']['report_status']];
            if($result['ali_biz_info']['report_status'] == '2' && !empty($result['ali_biz_info']['fail_msg'])){
                $data['ali']['reason'] = $result['ali_biz_info']['fail_msg'];
            }
            if(!empty($result['ali_biz_info']['alisub_merch_no'])){
                $data['ali']['sub_mch_id'] = $result['ali_biz_info']['alisub_merch_no'];
                $sub_mchid[] = $result['ali_biz_info']['alisub_merch_no'];
            }
            $data['ali']['real_status'] = null;
            $data['ali']['real_status_text'] = '未提交';
            if(isset($ext['ali_real']['status'])){
                $data['ali']['real_status'] = $ext['ali_real']['status'];
                $data['ali']['real_status_text'] = self::$aliRealNameStatus[$ext['ali_real']['status']];
                $data['ali']['real_reason'] = $ext['ali_real']['reason'];
            }
        }
        if(!empty($result['wx_biz_info'])){
            $data['wx']['status'] = $result['wx_biz_info']['report_status'];
            $data['wx']['status_text'] = self::$reportStatus[$result['wx_biz_info']['report_status']];
            if($result['wx_biz_info']['report_status'] == '2' && !empty($result['wx_biz_info']['fail_msg'])){
                $data['wx']['reason'] = $result['wx_biz_info']['fail_msg'];
            }
            //
            if(!empty($result['wx_biz_info']['wxsub_merch_no'])){
                $data['wx']['sub_mch_id'] = $result['wx_biz_info']['wxsub_merch_no'];
                $sub_mchid[] = $result['wx_biz_info']['wxsub_merch_no'];
            }
            $data['wx']['real_status'] = null;
            $data['wx']['real_status_text'] = '未提交';
            if(isset($ext['wx_real']['status'])){
                $data['wx']['real_status'] = $ext['wx_real']['status'];
                $data['wx']['real_status_text'] = self::$wxRealNameStatus[$ext['wx_real']['status']];
                $data['wx']['real_reason'] = $ext['wx_real']['reason'];
            }
            
        }
        if(!empty($result['bank_biz_info'])){
            $data['bank']['status'] = $result['bank_biz_info']['report_status'];
            $data['bank']['status_text'] = self::$reportStatus[$result['bank_biz_info']['report_status']];
            if($result['bank_biz_info']['report_status'] == '2' && !empty($result['bank_biz_info']['fail_msg'])){
                $data['bank']['reason'] = $result['bank_biz_info']['fail_msg'];
            }
            if(!empty($result['bank_biz_info']['banksub_merch_no'])){
                $data['bank']['sub_mch_id'] = $result['bank_biz_info']['banksub_merch_no'];
                $sub_mchid[] = $result['bank_biz_info']['banksub_merch_no'];
            }
        }

        $sub_mchid = implode('|', $sub_mchid);
        if(!empty($sub_mchid) && $sub_mchid != $row['sub_mchid']){
            $DB->update('applymerchant', ['sub_mchid'=>$sub_mchid], ['id'=>$row['id']]);
        }

        include dirname(__FILE__).'/bizinfo.page.php';
    }

    //支付宝提交实名认证
    public function submitAliRealName($row){
        global $DB;
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/sub/query-merchant-info', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户报备状态查询失败，'.$e->getMessage()];
        }
        $ali_merch_no = $result['ali_biz_info']['alisub_merch_no'];
        if(empty($ali_merch_no)) return ['code'=>-1, 'msg'=>'支付宝子商户号为空'];
        $info = json_decode($row['info'], true);

        $contact_person_info = [
            'contact_name' => $info['idcard_name'],
            'contact_phone_no' => $info['contact_phone'],
            'contact_card_no' => $info['idcard_no'],
        ];
        $auth_identity_info = [
            'identity_type' => ['1'=>'MSE', '2'=>'IND_BIZ', '3'=>'ENTERPRISE'][$info['merchant_type']],
        ];
        if($info['merchant_type'] == '1'){ //小微商户
            $auth_identity_info += [
                'merchant_type' => 'STORE',
                'store_name' => $info['shop_name'],
                'province_code' => $info['shop_address'][2],
                'province' => $info['shop_address_name'][2],
                'city_code' => $info['shop_address'][1],
                'city' => $info['shop_address_name'][1],
                'district_code' => $info['shop_address'][0],
                'district' => $info['shop_address_name'][0],
                'store_address' => $info['shop_address_detail'],
                'store_door_img' => $info['shop_entrance_pic'],
                'store_inner_img' => $info['shop_indoor_pic'],
            ];
        }else{
            $auth_identity_info += [
                'cert_no' => $info['license_no'],
                'cert_image' => $info['license_image'],
                'merchant_name' => $info['merchant_name'],
                'legal_person_name' => $info['idcard_name'],
                'register_address' => $info['license_address'],
                'effect_time' => $info['license_period_begin'],
                'expire_time' => $info['license_period_end'] == '2999-12-31' || $info['license_period_end'] == '长期' ? 'forever' : $info['license_period_end'],
            ];
        }
        $legal_person_info = [
            'person_name' => $info['idcard_name'],
            'card_no' => $info['idcard_no'],
            'effect_time' => $info['idcard_period_begin'],
            'expire_time' => $info['idcard_period_end'] == '2999-12-31' || $info['idcard_period_end'] == '长期' ? 'forever' : $info['idcard_period_end'],
            'card_front_img' => $info['idcard_image'],
            'card_back_img' => $info['idcard_back_image'],
        ];
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
            'ali_merch_no' => $ali_merch_no,
            'business_code' => date('YmdHis').rand(100,999),
            'contact_person_info' => $contact_person_info,
            'auth_identity_info' => $auth_identity_info,
            'legal_person_info' => $legal_person_info,
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/ali-auth/create', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'实名认证申请单提交失败，'.$e->getMessage()];
        }

        $ext = json_decode($row['ext'], true);
        $ext['ali_real'] = ['business_code'=>$params['business_code'], 'apply_no'=>$result['apply_no'], 'status'=>'AUDITING'];

        $DB->update('applymerchant', ['ext'=>json_encode($ext)], ['id'=>$row['id']]);

        return ['code'=>0, 'msg'=>'实名认证申请单提交成功！请稍后查询状态', 'update'=>true];
    }

    //支付宝查询实名认证状态
    public function queryAliRealName($row){
        global $DB;
        $ext = json_decode($row['ext'], true);
        if(empty($ext['ali_real']['apply_no'])) return ['code'=>-1, 'msg'=>'未提交实名认证申请'];

        $params = [
            'agent_no' => $this->channel['agent_no'],
            'apply_no' => $ext['ali_real']['apply_no'],
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/ali-auth/query-status', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'申请单查询失败，'.$e->getMessage()];
        }

        $update = false;
        if($result['apply_status'] != $ext['ali_real']['status']){
            $ext['ali_real']['status'] = $result['apply_status'];
            $update = true;
        }
        if($result['fail_reason'] != $ext['ali_real']['reason']){
            $ext['ali_real']['reason'] = $result['fail_reason'];
            $update = true;
        }
        if($update){
            $DB->update('applymerchant', ['ext'=>json_encode($ext)], ['id'=>$row['id']]);
        }
        $msg = '申请单查询成功，当前状态：'.self::$aliRealNameStatus[$result['apply_status']];
        if($result['apply_status'] == 'CONTACT_CONFIRM' || $result['apply_status'] == 'LEGAL_CONFIRM'){
            return ['code'=>0, 'msg'=>$msg, 'alipay_qrcode_url'=>$result['qr_code']];
        }elseif($update){
            return ['code'=>0, 'msg'=>$msg, 'update'=>true];
        }else{
            return ['code'=>0, 'msg'=>$msg];
        }
    }

    //支付宝关闭实名认证申请单
    public function cancelAliRealName($row){
        global $DB;
        $ext = json_decode($row['ext'], true);
        if(empty($ext['ali_real']['apply_no'])) return ['code'=>-1, 'msg'=>'未提交实名认证申请'];

        $params = [
            'agent_no' => $this->channel['agent_no'],
            'apply_no' => $ext['ali_real']['apply_no'],
        ];
        try{
            $this->service->mchRequest('/api/v1/ali-auth/cancel', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'申请单关闭失败，'.$e->getMessage()];
        }

        $ext['ali_real']['status'] = 'CANCELED';
        $DB->update('applymerchant', ['ext'=>json_encode($ext)], ['id'=>$row['id']]);

        return ['code'=>-1, 'msg'=>'申请单关闭成功！'];
    }

    //微信提交实名认证
    public function submitWxRealName($row){
        global $DB;
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/sub/query-merchant-info', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户报备状态查询失败，'.$e->getMessage()];
        }
        $wx_merch_no = $result['wx_biz_info']['wxsub_merch_no'];
        if(empty($wx_merch_no)) return ['code'=>-1, 'msg'=>'微信子商户号为空'];
        $info = json_decode($row['info'], true);

        $contact_info = [
            'name' => $info['idcard_name'],
            'mobile' => $info['contact_phone'],
            'id_card_number' => $info['idcard_no'],
            'contact_type' => 'LEGAL',
        ];
        $subject_info = [
            'subject_type' => ['1'=>'SUBJECT_TYPE_MICRO', '2'=>'SUBJECT_TYPE_INDIVIDUAL', '3'=>'SUBJECT_TYPE_ENTERPRISE'][$info['merchant_type']],
        ];
        if($info['merchant_type'] == '1'){ //小微商户
            $subject_info['assist_prove_info'] = [
                'micro_biz_type' => 'MICRO_TYPE_STORE',
                'store_name' => $info['shop_name'],
                'store_address_code' => $info['shop_address'][0],
                'store_address' => $info['shop_address_detail'],
                'store_header_copy' => $info['shop_entrance_pic'],
                'store_indoor_copy' => $info['shop_indoor_pic'],
            ];
        }else{
            $subject_info['business_licence_info'] = [
                'licence_number' => $info['license_no'],
                'licence_copy' => $info['license_image'],
                'merchant_name' => $info['merchant_name'],
                'legal_person' => $info['idcard_name'],
                'company_address' => $info['license_address'],
                'licence_valid_date' => [$info['license_period_begin'], $info['license_period_end'] == '2999-12-31' || $info['license_period_end'] == '长期' ? 'forever' : $info['license_period_end']],
            ];
        }
        $identification_info = [
            'identification_type' => 'IDENTIFICATION_TYPE_IDCARD',
            'identification_name' => $info['idcard_name'],
            'identification_number' => $info['idcard_no'],
            'identification_valid_date' => [$info['idcard_period_begin'], $info['idcard_period_end'] == '2999-12-31' || $info['idcard_period_end'] == '长期' ? 'forever' : $info['idcard_period_end']],
            'identification_front_copy' => $info['idcard_image'],
            'identification_back_copy' => $info['idcard_back_image'],
            'identification_address' => $info['idcard_address'],
        ];
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
            'wx_merch_no' => $wx_merch_no,
            'business_code' => date('YmdHis').rand(100,999),
            'contact_info' => $contact_info,
            'subject_info' => $subject_info,
            'identification_info' => $identification_info,
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/wx-auth/apply', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'实名认证申请单提交失败，'.$e->getMessage()];
        }

        $ext = json_decode($row['ext'], true);
        $ext['wx_real'] = ['business_code'=>$params['business_code'], 'apply_no'=>$result['apply_no'], 'status'=>'APPLYMENT_STATE_WAITTING_FOR_AUDIT'];

        $DB->update('applymerchant', ['ext'=>json_encode($ext)], ['id'=>$row['id']]);

        return ['code'=>0, 'msg'=>'实名认证申请单提交成功！请稍后查询状态', 'update'=>true];
    }

    //微信查询实名认证状态
    public function queryWxRealName($row){
        global $DB;
        $ext = json_decode($row['ext'], true);
        if(empty($ext['wx_real']['apply_no'])) return ['code'=>-1, 'msg'=>'未提交实名认证申请'];

        $params = [
            'agent_no' => $this->channel['agent_no'],
            'apply_no' => $ext['wx_real']['apply_no'],
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/wx-auth/query-apply-status', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'申请单查询失败，'.$e->getMessage()];
        }

        $update = false;
        if($result['applyment_state'] != $ext['wx_real']['status']){
            $ext['wx_real']['status'] = $result['applyment_state'];
            $update = true;
        }
        if($result['reject_reason'] != $ext['wx_real']['reason']){
            $ext['wx_real']['reason'] = $result['reject_reason'];
            $update = true;
        }
        if($update){
            $DB->update('applymerchant', ['ext'=>json_encode($ext)], ['id'=>$row['id']]);
        }
        $msg = '申请单查询成功，当前状态：'.self::$wxRealNameStatus[$result['applyment_state']];
        if($result['applyment_state'] == 'APPLYMENT_STATE_WAITTING_FOR_CONFIRM_CONTACT' || $result['applyment_state'] == 'APPLYMENT_STATE_WAITTING_FOR_CONFIRM_LEGALPERSON' || $result['applyment_state'] == 'APPLYMENT_STATE_PASSED' || $result['applyment_state'] == 'APPLYMENT_STATE_FREEZED'){
            return ['code'=>0, 'msg'=>$msg, 'applyment_state'=>$result['applyment_state'],'qrcode_url'=>'data:image/jpg;base64,'.$result['qrcode_data']];
        }elseif($update){
            return ['code'=>0, 'msg'=>$msg, 'update'=>true];
        }else{
            return ['code'=>0, 'msg'=>$msg];
        }
    }
    
    //微信查询意愿确认状态
    public function queryWxAuth($row){
       global $DB;
       var_dump($row['ext']);
       $ext = json_decode($row['ext'], true);
        // var_dump($ext);
       if(empty($ext['wx_real']['apply_no'])) return ['code'=>-1, 'msg'=>'未提交实名认证申请'];
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
            'wx_merch_no' =>'723385956'
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/wx-auth/query-merchant-auth-status', $params);
            return ['code'=>0, 'msg'=>$result['return_msg'], 'authorize_state'=>$result['authorize_state']];
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'实名确认查询失败，'.$e->getMessage()];
        }
      //  var_dump($result);
    }

    //微信撤销实名认证申请单
    public function cancelWxRealName($row){
        global $DB;
        $ext = json_decode($row['ext'], true);
        if(empty($ext['wx_real']['apply_no'])) return ['code'=>-1, 'msg'=>'未提交实名认证申请'];

        $params = [
            'agent_no' => $this->channel['agent_no'],
            'apply_no' => $ext['wx_real']['apply_no'],
        ];
        try{
            $this->service->mchRequest('/api/v1/wx-auth/cancel', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'申请单撤销失败，'.$e->getMessage()];
        }

        $ext['wx_real']['status'] = 'APPLYMENT_STATE_CANCELED';
        $DB->update('applymerchant', ['ext'=>json_encode($ext)], ['id'=>$row['id']]);

        return ['code'=>-1, 'msg'=>'申请单撤销成功！'];
    }

    //微信公众号配置
    public function wxconf($row){
        global $DB, $cdnpublic, $siteurl;
        
        if(isset($_POST['conf_key']) && isset($_POST['conf_value'])){
           
            $conf_key = $_POST['conf_key'];
            $conf_value = $_POST['conf_value'];
            
            if(!in_array($conf_key, ['appid', 'path']) || empty($conf_value)) showmsg('参数错误',4);
            $conf_value = str_replace('；', ';', trim($conf_value));

            $params = [
                'agent_no' => $this->channel['agent_no'],
                'merch_no' => $row['mchid'],
                'conf_key' => ['appid'=>'pay_appId', 'path'=>'auth_path'][$conf_key],
                'conf_value' => $conf_value,
            ];
           
            log_debug('微信公众号配置参数'.json_encode($params),'haipay');
            try{
                $this->service->mchRequest('/api/v1/wx-appid-conf/add', $params);
            }catch(Exception $e){
                showmsg('配置保存失败，'.$e->getMessage(),4);
            }

            $return_url = './applyments_form.php?type=page&action=wxconf&id='.$row['id'];
            showmsg('配置保存成功！',1,$return_url);
        }

        $params = [
            'merch_no' => $row['mchid'],
        ];
        try{
            $result = $this->service->mchRequest('/api/v1/wx-appid-conf/query', $params);
        }catch(Exception $e){
            showmsg('公众号配置查询失败，'.$e->getMessage(),4);
        }
        if($result['result_code'] == 'SUCCESS'){
            $data = ['jsapi_path_list'=>$result['jsapi_path_list'], 'appid_config_list'=>$result['appid_config_list']];
        }else{
            showmsg('['.$result['err_code'].']'.$result['err_code_des'],4);
        }
        include dirname(__FILE__).'/wxconf.page.php';
    }

    private function wxconfadd($merch_no, $conf_key, $conf_value){
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $merch_no,
            'conf_key' => ['appid'=>'pay_appId', 'path'=>'auth_path'][$conf_key],
            'conf_value' => $conf_value,
        ];
        try{
            $this->service->mchRequest('/api/v1/wx-appid-conf/add', $params);
            return ['code'=>0];
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //修改结算信息
    public function settlement($row, $data){
        global $conf, $DB;
        $config = json_decode($this->applychannel['config'], true);
        $info = json_decode($row['info'], true);

        if($data['merchant_type'] == '1'){ //小微商户
            $bankcard_data = [
                'acc_type' => '10B',
                'acc_no' => $data['card_no'],
                'acc_name' => $data['card_name'],
                'idcard_no' => $info['idcard_no'],
                'phone' => $data['bank_phone'],
                'bank_code' => $data['bank_branch_code'],
                'bank_province_code' => $data['bank_address'][1],
                'bank_city_code' => $data['bank_address'][0],
            ];
            $image_data = [
                'A4' => $data['bank_card_image'],
            ];
        }else{
            $bankcard_data = [
                'acc_type' => $data['card_type'] == '0' ? '10A' : '10B',
                'acc_no' => $data['card_no'],
                'acc_name' => $data['card_name'],
                'bank_code' => $data['bank_branch_code'],
                'bank_province_code' => $data['bank_address'][1],
                'bank_city_code' => $data['bank_address'][0],
            ];
            if($data['card_type'] == '0'){
                $bankcard_data['bank_name'] = $data['bank_branch_name'];
                $image_data = [
                    'A28' => $data['bank_license_image'],
                ];
            }else{
                $bankcard_data += [
                    'idcard_no' => $info['idcard_no'],
                    'phone' => $data['bank_phone'],
                ];
                $image_data = [
                    'A4' => $data['bank_card_image'],
                ];
            }
            if($data['card_type'] == '2'){
                $bankcard_data['idcard_no'] = $data['auth_idcard_no'];
                $image_data += [
                    'A9999' => $data['settle_auth_image'],
                    'A32' => $data['settle_auth_hold_image'],
                    'A35' => $data['auth_idcard_image'],
                    'A36' => $data['auth_idcard_back_image'],
                ];
            }
        }
        $params = [
            'agent_no' => $this->channel['agent_no'],
            'merch_no' => $row['mchid'],
            'agent_apply_no' => date('YmdHis').rand(10,99),
            'bankcard_data' => $bankcard_data,
            'image_data' => $image_data,
        ];

        try{
            $result = $this->service->mchRequest('/api/v1/merchant-bank-info/modify', $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'结算信息修改提交失败，'.$e->getMessage()];
        }

        $info = json_decode($row['info'], true);
        $info = array_merge($info, $data);

        $DB->update('applymerchant', ['updatetime'=>'NOW()', 'info' => json_encode($info)], ['id'=>$row['id']]);

        return ['code'=>0, 'msg'=>'结算信息修改提交成功！'];
    }

    //上传图片
    public function uploadImage($filepath, $filename){
        $image = file_get_contents($filepath);
        try{
            $result = $this->service->mchRequest('/api/v1/merchant-image/upload', ['image' => base64_encode($image)]);
            return ['code'=>0, 'image_id'=>$result['image_id']];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //修改支付状态
    public function setPayStatus($row, $status){
        global $DB, $islogin;
        $config = json_decode($this->applychannel['config'], true);
        $channelids = $config['pay_channel'] ?? [];
        $channelids[] = $this->channel['id'];
        $channelids = array_unique($channelids);
        $info = json_decode($row['info'], true);

        if($this->channel['merch_no'] && substr($this->channel['merch_no'],0,1)=='[' && $this->channel['pn'] && substr($this->channel['pn'],0,1)=='['){
            $merch_no_key = substr($this->channel['merch_no'],1,-1);
            $pn_key = substr($this->channel['pn'],1,-1);
        }else{
            return ['code'=>-1, 'msg'=>'当前支付通道参数配置错误，需要在商户编号和终端号字段使用变量模式'];
        }
        $ext = json_decode($row['ext'], true);
        $info = json_encode([$merch_no_key => $row['mchid'], $pn_key => $ext['pn']]);

        if($status == 1){
            if($row['paid'] == 0 && $this->applychannel['price'] > 0 && !$islogin){
                if(!CommUtil::payForMerchant($row['id'], $row['uid'], $this->applychannel['price'])){
                    exit('{"code":-1,"msg":"进件价格为'.$this->applychannel['price'].'元，您的余额不足，请充值后重试！"}');
                }
            }
        }

        foreach($channelids as $channelid){
            $subchannel = $DB->find('subchannel', '*', ['uid'=>$row['uid'], 'channel'=>$channelid, 'apply_id'=>$row['id']]);
            if($status == 1){
                if(!$subchannel){
                    $DB->insert('subchannel', ['channel'=>$channelid, 'uid'=>$row['uid'], 'name'=>$row['mchname'].'('.$row['id'].')', 'status'=>1, 'info'=>$info, 'addtime'=>date('Y-m-d H:i:s'), 'apply_id'=>$row['id']]);
                }elseif($subchannel && $subchannel['status'] == 0){
                    $DB->update('subchannel', ['status'=>1, 'info'=>$info], ['id'=>$subchannel['id']]);
                }
            }else{
                if($subchannel){
                    $DB->update('subchannel', ['status'=>0], ['id'=>$subchannel['id']]);
                }
            }
        }
        
        return ['code'=>0, 'msg'=>'succ'];
    }


    //获取银行列表
    public function getBankList($page, $limit, $keyword, $keyid){
        $file = file_get_contents($this->basedir.'bank.json');
        $data = json_decode($file, true);

        if(!empty($keyid)){
            $data = array_filter($data, function($item) use($keyid){
                return $item['value'] == $keyid;
            });
        }elseif(!empty($keyword)){
            $data = array_filter($data, function($item) use($keyword){
                return strpos($item['label'], $keyword) !== false;
            });
        }
        $total = count($data);
        $data = array_slice($data, ($page-1)*$limit, $limit);
        
        $results = [];
        foreach($data as $item){
            $results[] = ['id'=>$item['value'], 'text'=>$item['label']];
        }
        return ['code'=>0, 'data'=>['results'=>$results, 'pagination'=>['more'=>($page*$limit < $total)]]];
    }

    //查询支行列表
    public function getBankBranchList($bank_code, $city_code){
        try{
            $branches = CommUtil::getBankBranchList($bank_code, $city_code);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
        $results = [];
        foreach($branches as $item){
            $results[] = ['id'=>$item['id'], 'text'=>$item['name']];
        }
        return ['code'=>0, 'data'=>$results];
    }

    //进件异步通知
    public function notify($bizParams){
        global $DB;
        $requestNo = $bizParams['requestNo'];
        $status = $bizParams['applicationStatus'];
        $row = $DB->find('applymerchant', '*', ['orderid'=>$requestNo]);
        if(!$row) return ['code'=>-1, 'msg'=>'未找到对应的商户申请单'];

        if($status == 'COMPLETED'){
            $DB->update('applymerchant', ['status'=>4, 'mchid'=>$bizParams['merchantNo'], 'reason'=>$bizParams['auditOpinion'], 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
        }elseif($status == 'REVIEW_BACK'){
            $DB->update('applymerchant', ['status'=>3, 'reason'=>$bizParams['auditOpinion'], 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
        }elseif($status == 'AGREEMENT_SIGNING'){
            $DB->update('applymerchant', ['status'=>2, 'ext'=>$bizParams['agreementSignUrl'], 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
        }
        return ['code'=>0, 'msg'=>'succ', 'status'=>$status];
    }

    private function getMchType($merchant_type){
        $mchtype = ['1' => '10C', '2' => '10A', '3' => '10B'];
        return $mchtype[$merchant_type];
    }

    private static function getcheck(){
        global $CACHE;
        
    }

}
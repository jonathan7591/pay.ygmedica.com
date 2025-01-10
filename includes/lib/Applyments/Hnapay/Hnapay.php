<?php
namespace lib\Applyments\Hnapay;

use Exception;
use lib\Applyments\CommUtil;
use lib\Applyments\IApplyments;

class Hnapay implements IApplyments
{
    private $applychannel;
    private $channel;
    private $service;
    private $basedir = SYSTEM_ROOT.'/lib/Applyments/Hnapay/';
    private static $reportStatus = ['1'=>'成功','2'=>'失败'];

    function __construct($applychannel, $channel){
		$this->applychannel = $applychannel;
        $this->channel = $channel;
        $this->service = new HnapayMerchantService($channel['appid'], $channel['appkey'], $channel['appsecret'], $channel['appswitch'] == 1);
	}

    public static function getOperation($row){
        global $islogin;
        $data = [];
        if($row['status'] == 4 || $row['status'] == 6){
            $data[] = ['title'=>'查询商户报备状态', 'type'=>'query', 'action'=>'queryReport'];
            $data[] = ['title'=>'修改结算账户', 'type'=>'form', 'action'=>'settlement'];
            $data[] = ['title'=>'修改商户信息', 'type'=>'form', 'action'=>'modify'];
        }
        return $data;
    }

    //获取表单数据
    public function getFormData($action, $info = null){
        global $DB, $islogin, $siteurl;
        self::getcheck();
        $info = $info ? json_decode($info, true) : [];
        $config = json_decode($this->applychannel['config'], true);
        
        if($action == 'config'){
            $file_path = $this->basedir.'form_config.json';
            $info = $config;
        }elseif($action == 'settlement'){
            $file_path = $this->basedir.'form_settlement.json';
        }elseif($action == 'modify'){
            $file_path = $this->basedir.'form_modify.json';
        }elseif($action == 'setkey'){
            $file_path = $this->basedir.'form_setkey.json';
        }elseif($action == 'create' || $action == 'view'){
            $file_path = $this->basedir.'form_create.json';
        }else{
            return ['code'=>-1, 'msg'=>'未知的操作类型'];
        }
        $file = file_get_contents($file_path);
        if(strpos($file, '${city}')){
            $file = str_replace('"${city}"', file_get_contents($this->basedir.'city.json'), $file);
        }
        if(strpos($file, '${mcc}')){
            $file = str_replace('"${mcc}"', file_get_contents($this->basedir.'mcc.json'), $file);
        }
        if(strpos($file, '${bank}')){
            $file = str_replace('"${bank}"', file_get_contents($this->basedir.'bank.json'), $file);
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
            }
        }
        return ['code'=>0, 'data'=>$form];
    }

    //商户创建
    public function create($data){
        global $conf, $DB;
        self::getcheck();
        $config = json_decode($this->applychannel['config'], true);
        if(empty($config['paytypes'])){
            return ['code'=>-1, 'msg'=>'未配置默认开通的支付方式'];
        }

        if(empty($data['order_id'])) $data['order_id'] = date('YmdHis').rand(10,99);
        if(!is_numeric($data['order_id']) || strlen($data['order_id']) != 16){
            return ['code'=>-1, 'msg'=>'商户申请单号格式错误'];
        }

        $merchant_type = $this->getMchType($data['merchant_type']);

        if($data['idcard_period_end'] == '长期') $data['idcard_period_end'] = '9999-99-99';
        if($data['merchant_type'] == '1') $data['merchant_name'] = $data['idcard_name'];
        $params = [
            'requestId' => $data['order_id'],
            'merchantNm' => $data['merchant_name'],
            'merchantShortNm' => $data['alias_name'],
            'merchantType' => $merchant_type,
            'chainType' => '00',
            'mcc' => $data['mcc'],
            'province' => $data['shop_address'][2] . '000000',
            'city' => $data['shop_address'][1] . '000000',
            'district' => $data['shop_address'][0] . '000000',
            'address' => $data['shop_address_detail'],
            'linkman' => $data['contact_name'],
            'phone' => $data['contact_phone'],
            'email' => $data['contact_email'],
            'customerPhone' => $data['contact_phone'],
            'principal' => $data['idcard_name'],
            'principalPhone' => $data['contact_phone'],
            'principalIdcodeType' => '0',
            'principalIdcode' => $data['idcard_no'],
            'legalPersonCertificateStt' => $data['idcard_period_begin'],
            'legalPersonCertificateEnt' => $data['idcard_period_end'],
            'lawyerCertFrontPhoto' => $data['idcard_image'],
            'lawyerCertBackPhoto' => $data['idcard_back_image'],
        ];
        if(!empty($config['channel_code'])){
            $params['channelCode'] = $config['channel_code'];
        }
        if($data['merchant_type'] != '1'){
            if($data['license_period_end'] == '长期') $data['license_period_end'] = '9999-99-99';
            $params += [
                'licenseMatch' => '00',
                'businessLicenseName' => $data['merchant_name'],
                'businesslicense' => $data['license_no'],
                'businessLicStt' => $data['license_period_begin'],
                'businessLicEnt' => $data['license_period_end'],
                'licensePhoto' => $data['license_image'],
            ];
        }
        if(!empty($data['online_name'])){
            $params += [
                'onlineType' => $data['online_type'],
                'onlineName' => $data['online_name'],
                'onlineTypeInfo' => $data['online_type_info'],
                'icpLicencePhoto' => $data['icp_license_image'],
            ];
        }
        $params += [
            'protocolPhoto' => $data['protocol_image'],
            'mainPhoto' => $data['shop_entrance_pic'],
            'storeHallPhoto' => $data['shop_indoor_pic'],
            'storeCashierPhoto' => $data['shop_cashier_pic'],
        ];
        $params += [
            'accountNo' => $data['account_number'],
            'bankId' => $data['bank_code'],
            'accountNm' => $data['account_name'],
            'accountType' => $data['settle_account_type'],
            'cnapsCode' => $data['bank_branch_id'],
            'bankName' => $data['bank_branch_name'],
            'bankProvince' => substr($data['bank_address'], 0, 2),
            'bankCity' => $data['bank_address'],
            'settleWay' => $data['settle_way'],
        ];
        if($data['settle_account_type'] == '2'){
            $params += [
                'accountNature' => $data['settle_account_nature'],
                'idcardType' => '1',
                'identityPhone' => $data['bank_phone'],
                'bankCardFrontPhoto' => $data['bank_card_image'],
            ];
            if($data['settle_account_nature'] == '2'){
                if($data['auth_idcard_period_end'] == '长期') $data['auth_idcard_period_end'] = '9999-99-99';
                $params += [
                    'idcardNo' => $data['auth_idcard_no'],
                    'validateDateStart' => $data['auth_idcard_period_begin'],
                    'validateDateExpired' => $data['auth_idcard_period_end'],
                    'settleAuthLetterPhoto' => $data['settle_auth_image'],
                    'authorizedCertFrontPhoto' => $data['auth_idcard_image'],
                    'authorizedCertBackPhoto' => $data['auth_idcard_back_image'],
                    'holdIdentityPic' => $data['hold_idcard_image'],
                ];
            }else{
                $params += [
                    'idcardNo' => $data['idcard_no'],
                    'validateDateStart' => $data['idcard_period_begin'],
                    'validateDateExpired' => $data['idcard_period_end'],
                    'authorizedCertFrontPhoto' => $data['idcard_image'],
                    'authorizedCertBackPhoto' => $data['idcard_back_image'],
                    'holdIdentityPic' => $data['hold_idcard_image'],
                ];
            }
        }else{
            $params['openingLicenseAccountPhoto'] = $data['bank_license_image'];
        }
        $params['profitConf'] = [
            ['rateTypeId'=>'0101', 'channel'=>'01', 'openFlag'=>in_array('01', $config['paytypes'])?'1':'0', 'feeRate' => strval($config['alipay_rate']/100)],
            ['rateTypeId'=>'0201', 'channel'=>'02', 'openFlag'=>in_array('02', $config['paytypes'])?'1':'0', 'feeRate' => strval($config['wxpay_rate']/100)],
            ['rateTypeId'=>'0307', 'channel'=>'03', 'openFlag'=>in_array('03', $config['paytypes'])?'1':'0', 'feeRate' => strval($config['bank_rate']/100)],
            ['rateTypeId'=>'0308', 'channel'=>'03', 'openFlag'=>in_array('03', $config['paytypes'])?'1':'0', 'feeRate' => strval($config['bank_rate']/100)],
            ['rateTypeId'=>'0401', 'channel'=>'04', 'openFlag'=>'0', 'feeRate' => ''],
            ['rateTypeId'=>'0402', 'channel'=>'04', 'openFlag'=>'0', 'feeRate' => ''],
        ];

        if($row = $DB->find('applymerchant', '*', ['cid'=>$this->applychannel['id'], 'orderid'=>$data['order_id']])){
            $params['merchantNo'] = $row['mchid'];
            try{
                $result = $this->service->patch($params);
            }catch(Exception $e){
                return ['code'=>-1, 'msg'=>'商户申请单提交失败，'.$e->getMessage()];
            }

            $DB->update('applymerchant', [
                'thirdid' => $result['requestId'],
                'mchid' => $result['merchantNo'],
                'mchtype' => $data['merchant_type'],
                'mchname' => $data['merchant_name'],
                'updatetime' => 'NOW()',
                'status' => 1,
                'info' => json_encode($data),
            ], ['id'=>$row['id']]);
        }else{
            try{
                $result = $this->service->apply($params);
            }catch(Exception $e){
                return ['code'=>-1, 'msg'=>'商户申请单提交失败，'.$e->getMessage()];
            }

            $DB->insert('applymerchant', [
                'cid' => $this->applychannel['id'],
                'uid' => $data['uid'] ? $data['uid'] : 0,
                'orderid' => $data['order_id'],
                'thirdid' => $result['requestId'],
                'mchid' => $result['merchantNo'],
                'mchtype' => $data['merchant_type'],
                'mchname' => $data['merchant_name'],
                'addtime' => 'NOW()',
                'updatetime' => 'NOW()',
                'status' => 1,
                'paid' => $this->applychannel['price'] == 0 ? 1 : 0,
                'info' => json_encode($data),
            ]);
        }

        return ['code'=>0, 'msg'=>'商户申请单提交成功！请等待新生易聚合支付审核', 'orderid'=>$data['order_id']];
    }

    //申请单进度查询
    public function query($row){
        global $DB;
        if($row['status'] == 5){
            $ext = explode('|', $row['ext']);
            return $this->queryModify($row, $ext[1]);
        }
        try{
            $result = $this->service->queryApply($row['thirdid']);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户进件结果查询失败，'.$e->getMessage()];
        }
        if($result['opStatus'] == '8'){
            $DB->update('applymerchant', ['status'=>4, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);

            if($row['paid'] == 0 && !empty($row['uid']) && $this->applychannel['price'] > 0){
                CommUtil::payForMerchant($row['id'], $row['uid'], $this->applychannel['price']);
            }
            return ['code'=>0, 'update'=>true, 'res'=>1, 'msg'=>'申请单已审核通过', 'mchid'=>$row['mchid'], 'result'=>$result];
        }elseif($result['opStatus'] == '5' || $result['opStatus'] == '7' || $result['opStatus'] == '9'){
            $optext = $result['opStatus'] == '5' ? '开户请求失败' : ($result['opStatus'] == '7' ? '审核驳回' : '图片审核驳回');
            $reason = $result['suggestion'];
            $DB->update('applymerchant', ['status'=>3, 'reason'=>$reason, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
            return ['code'=>0, 'update'=>true, 'res'=>0, 'msg'=>'申请单'.$optext.'，原因：'.$reason];
        }elseif($result['opStatus'] == '0' || $result['opStatus'] == '6'){
            return ['code'=>0, 'msg'=>'申请单正在审核中，请耐心等待'];
        }else{
            return ['code'=>0, 'msg'=>'申请单状态:'.$result['opStatus']];
        }
    }

    //商户报备信息查询
    public function queryReport($row){
        try{
            $result = $this->service->queryApply($row['thirdid']);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户进件结果查询失败，'.$e->getMessage()];
        }
        $msg = '';
        if(!empty($result['wechatPayRecordStatus'])){
            $msg .= '微信报备状态：'.self::$reportStatus[$result['wechatPayRecordStatus']];
            if($result['wechatPayRecordStatus'] == '2' && !empty($result['wechatPayRecordMsg'])){
                $msg .= '（'.$result['wechatPayRecordMsg'].'）';
            }
            if(!empty($result['wechatPayRecordMerchantNo'])){
                $msg .= '<br/>微信子商户号：'.$result['wechatPayRecordMerchantNo'].'<br/>';
            }
        }
        if(!empty($result['aliPayRecordStatus'])){
            $msg .= '支付宝报备状态：'.self::$reportStatus[$result['aliPayRecordStatus']];
            if($result['aliPayRecordStatus'] == '2' && !empty($result['aliPayRecordMsg'])){
                $msg .= '（'.$result['aliPayRecordMsg'].'）';
            }
            if(!empty($result['aliPayRecordMerchantNo'])){
                $msg .= '<br/>支付宝子商户号：'.$result['aliPayRecordMerchantNo'].'<br/>';
            }
        }
        if(!empty($result['unionPayRecordStatus'])){
            $msg .= '银联报备状态：'.self::$reportStatus[$result['unionPayRecordStatus']];
            if($result['unionPayRecordStatus'] == '2' && !empty($result['unionPayRecordMsg'])){
                $msg .= '（'.$result['unionPayRecordMsg'].'）';
            }
            if(!empty($result['unionPayRecordMerchantNo'])){
                $msg .= '<br/>银联子商户号：'.$result['unionPayRecordMerchantNo'].'<br/>';
            }
        }
        return ['code'=>0, 'msg'=>$msg];
    }

    //商户修改结果查询
    private function queryModify($row, $requestId){
        global $DB;
        try{
            $result = $this->service->queryModify($requestId);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户修改结果查询失败，'.$e->getMessage()];
        }
        if($result['opStatus'] == '8'){
            $DB->update('applymerchant', ['status'=>4, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);

            return ['code'=>0, 'update'=>true, 'res'=>1, 'msg'=>'申请单已审核通过'];
        }elseif($result['opStatus'] == '5' || $result['opStatus'] == '7' || $result['opStatus'] == '9'){
            $optext = $result['opStatus'] == '5' ? '请求失败' : ($result['opStatus'] == '7' ? '审核驳回' : '图片审核驳回');
            $reason = $result['suggestion'];
            $DB->update('applymerchant', ['status'=>6, 'reason'=>$reason, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
            return ['code'=>0, 'update'=>true, 'res'=>0, 'msg'=>'申请单'.$optext.'，原因：'.$reason];
        }elseif($result['opStatus'] == '0' || $result['opStatus'] == '6'){
            return ['code'=>0, 'msg'=>'申请单正在审核中，请耐心等待'];
        }else{
            return ['code'=>0, 'msg'=>'申请单状态:'.$result['opStatus']];
        }
    }

    //结算信息修改
    public function settlement($row, $data){
        global $conf, $DB;
        $config = json_decode($this->applychannel['config'], true);

        $info = json_decode($row['info'], true);

        $order_id = date('YmdHis').rand(10,99);
        
        $params = [
            'requestId' => $order_id,
            'merchantNo' => $row['mchid'],
            'modifyType' => '2',
            'accountNo' => $data['account_number'],
            'bankId' => $data['bank_code'],
            'accountNm' => $data['account_name'],
            'accountType' => $data['settle_account_type'],
            'cnapsCode' => $data['bank_branch_id'],
            'bankName' => $data['bank_branch_name'],
            'bankProvince' => substr($data['bank_address'], 0, 2),
            'bankCity' => $data['bank_address'],
            'settleWay' => $data['settle_way'],
        ];
        if($data['settle_account_type'] == '2'){
            $params += [
                'accountNature' => $data['settle_account_nature'],
                'idcardType' => '1',
                'identityPhone' => $data['bank_phone'],
                'bankCardFrontPhoto' => $data['bank_card_image'],
            ];
            if($data['settle_account_nature'] == '2'){
                if($data['auth_idcard_period_end'] == '长期') $data['auth_idcard_period_end'] = '9999-99-99';
                $params += [
                    'idcardNo' => $data['auth_idcard_no'],
                    'validateDateStart' => $data['auth_idcard_period_begin'],
                    'validateDateExpired' => $data['auth_idcard_period_end'],
                    'settleAuthLetterPhoto' => $data['settle_auth_image'],
                    'authorizedCertFrontPhoto' => $data['auth_idcard_image'],
                    'authorizedCertBackPhoto' => $data['auth_idcard_back_image'],
                    'holdIdentityPic' => $data['hold_idcard_image'],
                ];
            }else{
                $params += [
                    'idcardNo' => $info['idcard_no'],
                    'validateDateStart' => $info['idcard_period_begin'],
                    'validateDateExpired' => $info['idcard_period_end'],
                    'authorizedCertFrontPhoto' => $info['idcard_image'],
                    'authorizedCertBackPhoto' => $info['idcard_back_image'],
                    'holdIdentityPic' => $data['hold_idcard_image'],
                ];
            }
        }else{
            $params['openingLicenseAccountPhoto'] = $data['bank_license_image'];
        }
        $params['merchantInfoModifyPhoto'] = $data['merchant_modify_image'];

        try{
            $result = $this->service->modify($params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'结算账户修改失败，'.$e->getMessage()];
        }

        $info = array_merge($info, $data);

        $DB->update('applymerchant', ['ext'=>'settle|'.$result['requestId'], 'status'=>5, 'updatetime'=>'NOW()', 'info' => json_encode($info)], ['id'=>$row['id']]);

        return ['code'=>0, 'msg'=>'结算账户修改提交成功！请等待新生易聚合支付审核'];
    }

    //商户信息修改
    public function modify($row, $data){
        global $conf, $DB;
        $config = json_decode($this->applychannel['config'], true);

        $info = json_decode($row['info'], true);

        $order_id = date('YmdHis').rand(10,99);
        
        $params = [
            'requestId' => $order_id,
            'merchantNo' => $row['mchid'],
            'modifyType' => '1',
            'merchantShortNm' => $data['alias_name'],
            'province' => $data['shop_address'][2] . '000000',
            'city' => $data['shop_address'][1] . '000000',
            'district' => $data['shop_address'][0] . '000000',
            'address' => $data['shop_address_detail'],
            'phone' => $data['contact_phone'],
            'customerPhone' => $data['contact_phone'],
            'lawyerCertFrontPhoto' => $info['idcard_image'],
            'lawyerCertBackPhoto' => $info['idcard_back_image'],
            'licensePhoto' => $info['license_image'],
            'protocolPhoto' => $info['protocol_image'],
            'mainPhoto' => $data['shop_entrance_pic'],
            'storeHallPhoto' => $data['shop_indoor_pic'],
            'storeCashierPhoto' => $data['shop_cashier_pic'],
            'merchantInfoModifyPhoto' => $data['merchant_modify_image'],
        ];
    
        try{
            $result = $this->service->modify($params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户信息修改失败，'.$e->getMessage()];
        }

        $info = array_merge($info, $data);

        $DB->update('applymerchant', ['ext'=>'modify|'.$result['requestId'], 'status'=>5, 'updatetime'=>'NOW()', 'info' => json_encode($info)], ['id'=>$row['id']]);

        return ['code'=>0, 'msg'=>'商户信息修改提交成功！请等待新生易聚合支付审核'];
    }

    //上传图片
    public function uploadImage($filepath, $filename){
        $pictureType = isset($_POST['pictureType'])?$_POST['pictureType']:null;
        try{
            $image_id = $this->service->uploadImage($pictureType, $filepath, $filename);
            return ['code'=>0, 'image_id'=>$image_id];
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

        if($this->channel['appmchid'] && substr($this->channel['appmchid'],0,1)=='['){
            $merchantIdKey = substr($this->channel['appmchid'],1,-1);
        }else{
            return ['code'=>-1, 'msg'=>'当前支付通道参数配置错误，需要在商户号和终端号字段使用变量模式'];
        }
        $info = json_encode([$merchantIdKey => $row['mchid']]);

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
                    $DB->insert('subchannel', ['channel'=>$channelid, 'uid'=>$row['uid'], 'name'=>$row['mchname'], 'status'=>1, 'info'=>$info, 'addtime'=>date('Y-m-d H:i:s'), 'apply_id'=>$row['id']]);
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

    //查询支行列表
    public function getBankBranchList($bank_code, $city_code){
        global $DB;
        $branches = $DB->findAll('hnapay_bank_data', '*', ['bank_id'=>$bank_code, 'city_id'=>$city_code], 'id ASC');
        $results = [];
        foreach($branches as $item){
            $results[] = ['id'=>$item['branch_no'], 'text'=>$item['branch_name']];
        }
        return ['code'=>0, 'data'=>$results];
    }

    private function getMchType($merchant_type){
        $mchtype = ['1' => '01', '2' => '02', '3' => '03'];
        return $mchtype[$merchant_type];
    }


    private static function getcheck(){
        global $CACHE;
        
    }

}
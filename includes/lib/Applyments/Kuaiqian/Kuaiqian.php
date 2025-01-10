<?php
namespace lib\Applyments\Kuaiqian;

use Exception;
use lib\Applyments\CommUtil;
use lib\Applyments\IApplyments;

class Kuaiqian implements IApplyments
{
    private $applychannel;
    private $channel;
    private $service;
    private $basedir = SYSTEM_ROOT.'/lib/Applyments/Kuaiqian/';

    function __construct($applychannel, $channel){
		$this->applychannel = $applychannel;
        $this->channel = $channel;
        $this->service = new KuaiqianMerchantService($channel['appid'], $channel['appkey'], $channel['appsecret']);
	}

    public static function getOperation($row){
        global $islogin;
        $data = [];
        if($row['status'] == 4 || $row['status'] == 6){
            $data[] = ['title'=>'查询商户报备状态', 'type'=>'query', 'action'=>'queryReport'];
            $data[] = ['title'=>'修改结算账户', 'type'=>'form', 'action'=>'settlement'];
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
        if(strpos($file, '${mcc2}')){
            $file = str_replace('"${mcc2}"', file_get_contents($this->basedir.'mcc2.json'), $file);
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
        if(empty($config['operator'])){
            return ['code'=>-1, 'msg'=>'未配置操作员账号'];
        }

        if(empty($data['order_id'])) $data['order_id'] = date('YmdHis').rand(10,99);
        if(!is_numeric($data['order_id']) || strlen($data['order_id']) != 16){
            return ['code'=>-1, 'msg'=>'商户申请单号格式错误'];
        }

        $subject_type = $this->getMchType($data['merchant_type']);
        $package_code = 'PSS-OFFLINE-001-004';
        if($data['merchant_type'] == '1'){
            $package_code = 'PSS-OFFLINE-001-001';
        }

        $additionList = [];
        if($data['idcard_period_end'] == '长期') $data['idcard_period_end'] = '9999-12-31';
        $params = [
            'operator' => $config['operator'],
            'subBillAccount' => $data['sub_account'],
            'packageInfo' => [
                'packageCode' => $package_code,
                'packageParameter' => [
                    'mcc' => $data['merchant_type'] == '3' ? $data['mcc'] : $data['mcc2'],
                    'cuRateDebit' => $config['debit_rate'],
                    'cuDebitMaxFee' => $config['debit_max_fee'],
                    'cuRateCredit' => $config['credit_rate'],
                    'zhifubaoRate' => $config['alipay_rate'],
                    'weixinRate' => $config['wxpay_rate'],
                    'terminalQkf' => '1',
                    'settlementCycle' => 'D1',
                    'holidayCashFee' => '0',
                ]
            ],
            'settlementInfo' => [
                'settleAcctType' => $data['settle_account_type'],
                'settlePan' => $data['account_number'],
                'accountName' => $data['account_name'],
                'bankName' => $data['bank_name'],
                'branchName' => $data['bank_branch_name'],
                'areaCode' => $data['bank_address'],
                'bankMobilePhone' => $data['bank_phone'],
                'cardFssId' => $data['bank_card_image']
            ],
            'subjectInfo' => [
                'subjectType' => $subject_type,
                'identityInfo' => [
                    'telephone' => $data['contact_phone'],
                    'idType' => '1',
                    'idCardInfo' => [
                        'idCardName' => $data['idcard_name'],
                        'idCardNumber' => $data['idcard_no'],
                        'beginDate' => $data['idcard_period_begin'],
                        'expireDate' => $data['idcard_period_end'],
                        'cardPersonFssId' => $data['idcard_image'],
                        'cardNationalFssId' => $data['idcard_back_image']
                    ],
                ],
            ],
            'storeInfo' => [
                'storeName' => $data['shop_name'],
                'areaCode' => $data['shop_address'],
                'address' => $data['shop_address_detail'],
                'mobile' => $data['contact_phone'],
                'contact' => $data['contact_name'],
                'headFssId' => $data['shop_entrance_pic'],
                'inDoorFssId' => $data['shop_indoor_pic'],
                'cashierFssId' => $data['shop_cashier_pic'],
            ],
        ];
        if($data['merchant_type'] != '1'){
            if($data['license_period_end'] == '长期') $data['license_period_end'] = '9999-12-31';
            $params['subjectInfo']['businessLicenseInfo'] = [
                'businessRegno' => $data['license_no'],
                'merchantName' => $data['merchant_name'],
                'legalName' => $data['idcard_name'],
                'fssId' => $data['license_image'],
                'constraintBusiness' => $data['constraint_business'],
                'areaCode' => $data['shop_address'],
                'address' => $data['shop_address_detail'],
                'handleDate' => $data['license_period_begin'],
                'cancelDate' => $data['license_period_end'],
            ];
        }else{
            $data['merchant_name'] = $data['idcard_name'];
            $params['subjectInfo']['identityInfo']['merchantName'] = $data['shop_name'];
            $params['subjectInfo']['identityInfo']['areaCode'] = $data['shop_address'];
            $params['subjectInfo']['identityInfo']['address'] = $data['shop_address_detail'];
            $params['subjectInfo']['identityInfo']['constraintBusiness'] = $data['constraint_business'];
        }
        if($data['merchant_type'] == '3'){
            $params['subjectInfo']['accOpenInfo'] = [
                'accOpenFssId' => $data['bank_license_image'],
                //'accOpenCode' => $data['bank_license_no']
            ];
        }
        if($data['settle_account_type'] == '2'){
            if($data['auth_idcard_period_end'] == '长期') $data['auth_idcard_period_end'] = '9999-12-31';
            $params['subjectInfo']['authorizerInfo'] = [
                'authName' => $data['auth_idcard_name'],
                'authIdCard' => $data['auth_idcard_no'],
                'beginDate' => $data['auth_idcard_period_begin'],
                'expireDate' => $data['auth_idcard_period_end'],
                'authPersonFssId' => $data['auth_idcard_image'],
                'authNationalFssId' => $data['auth_idcard_back_image'],
                'authTel' => $data['bank_phone']
            ];
        }
        if(!empty($data['personal_info_auth_image'])){
            $additionList[] = [
                'additionFssId' => $data['personal_info_auth_image'],
                'additionFileType' => 'PERSONAL_INFORMATION_ATTORNEY',
                'additionRemark' => '个人信息单独同意授权书',
                'extName' => 'jpg'
            ];
        }
        if(!empty($data['settlement_confirmation_image'])){
            $additionList[] = [
                'additionFssId' => $data['settlement_confirmation_image'],
                'additionFileType' => 'SETTLEMENT_CONFIRMATION_LETTER',
                'additionRemark' => '结算意愿确认函',
                'extName' => 'jpg'
            ];
        }
        if(!empty($data['open_account_will_image'])){
            $additionList[] = [
                'additionFssId' => $data['open_account_will_image'],
                'additionFileType' => 'OPEN_ACCOUNT_WILLINGNESS_LETTER',
                'additionRemark' => '开户意愿确认函',
                'extName' => 'jpg'
            ];
        }
        if(!empty($data['qualification_other'])){
            foreach($data['qualification_other'] as $item){
                $additionList[] = [
                    'additionFssId' => $item,
                    'additionFileType' => 'QUALIFICATION_OTHER',
                    'additionRemark' => '其他资质',
                    'extName' => 'jpg'
                ];
            }
        }
        if(!empty($additionList)){
            $params['additionList'] = $additionList;
        }


        try{
            $result = $this->service->submitNew($data['order_id'], $params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'商户申请单提交失败，'.$e->getMessage()];
        }

        if($row = $DB->find('applymerchant', '*', ['cid'=>$this->applychannel['id'], 'orderid'=>$data['order_id']])){
            $DB->update('applymerchant', [
                'thirdid' => $result['orderId'],
                'mchtype' => $data['merchant_type'],
                'mchname' => $data['merchant_name'],
                'updatetime' => 'NOW()',
                'status' => 1,
                'info' => json_encode($data),
            ], ['id'=>$row['id']]);
        }else{
            $DB->insert('applymerchant', [
                'cid' => $this->applychannel['id'],
                'uid' => $data['uid'] ? $data['uid'] : 0,
                'orderid' => $data['order_id'],
                'thirdid' => $result['orderId'],
                'mchtype' => $data['merchant_type'],
                'mchname' => $data['merchant_name'],
                'addtime' => 'NOW()',
                'updatetime' => 'NOW()',
                'status' => 1,
                'paid' => $this->applychannel['price'] == 0 ? 1 : 0,
                'info' => json_encode($data),
            ]);
        }

        return ['code'=>0, 'msg'=>'商户申请单提交成功！请等待快钱支付审核', 'orderid'=>$data['order_id']];
    }

    //申请单进度查询
    public function query($row){
        global $DB;
        if($row['status'] == 5){
            $ext = explode('|', $row['ext']);
            $row['thirdid'] = $ext[1];
        }
        try{
            $result = $this->service->query($row['thirdid']);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'申请单查询失败，'.$e->getMessage()];
        }
        if($result['status'] == 'FINISHED'){
            if($ext[0] == 'settle'){
                $DB->update('applymerchant', ['status'=>4, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
                return ['code'=>0, 'update'=>true, 'res'=>1, 'msg'=>'申请单已审核通过', 'result'=>$result];
            }
            $subMemberCode = $result['openResult']['subMemberCode'];
            $subMerchantId = $result['openResult']['subMerchantId'];
            $terminalId = $result['openResult']['terminalInfos'][0]['terminalIds'][0];
            if(empty($terminalId)){
                return ['code'=>0, 'msg'=>'终端信息不存在，请稍后查询', 'result'=>$result];
            }
            $mchid = $subMemberCode.'|'.$subMerchantId.'|'.$terminalId;
            
            $DB->update('applymerchant', ['status'=>4, 'mchid'=>$mchid, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);

            if($row['paid'] == 0 && !empty($row['uid']) && $this->applychannel['price'] > 0){
                CommUtil::payForMerchant($row['id'], $row['uid'], $this->applychannel['price']);
            }
            return ['code'=>0, 'update'=>true, 'res'=>1, 'msg'=>'申请单已审核通过', 'mchid'=>$mchid, 'result'=>$result];
        }elseif($result['status'] == 'REJECTED'){
            $reason = $result['comments'];
            $status = $row['status'] == 5 ? 6 : 3;
            $DB->update('applymerchant', ['status'=>$status, 'reason'=>$reason, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
            return ['code'=>0, 'update'=>true, 'res'=>0, 'msg'=>'申请单已驳回，原因：'.$reason];
        }elseif($result['status'] == 'DRAFT' && $row['status'] == 1){
            $DB->update('applymerchant', ['status'=>0, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
            return ['code'=>0, 'update'=>true, 'res'=>0, 'msg'=>'申请单状态编辑中，请尝试重新提交'];
        }elseif($result['status'] == 'TO_BE_SIGNED'){
            try{
                $result = $this->service->queryContract($row['thirdid']);
            }catch(Exception $e){
                return ['code'=>-1, 'msg'=>'合同查询失败，'.$e->getMessage()];
            }
            if(!empty($result['econtractList'])){
                try{
                    $result = $this->service->signContract($row['thirdid']);
                }catch(Exception $e){
                    return ['code'=>-1, 'msg'=>'合同签署失败，'.$e->getMessage()];
                }
            }
            return ['code'=>0, 'msg'=>'合同签署完成，申请单正在审核中'];
        }elseif($result['status'] == 'AUDITING'){
            return ['code'=>0, 'msg'=>'申请单正在审核中'];
        }elseif($result['status'] == 'TO_BE_REAUDITED'){
            return ['code'=>0, 'msg'=>'申请单正在审核中，请耐心等待'];
        }else{
            return ['code'=>0, 'msg'=>'申请单状态:'.$result['status']];
        }
    }

    //新增终端
    private function add_terminal($row, $merchantId){
        global $conf, $DB;
        $config = json_decode($this->applychannel['config'], true);

        $info = json_decode($row['info'], true);
        $package_code = 'PSS-OFFLINE-003-005';

        $params = [
            'operator' => $config['operator'],
            'subBillAccount' => $info['sub_account'],
            'packageInfo' => [
                'packageCode' => $package_code,
                'packageParameter' => [
                    'merchantId' => $merchantId,
                    'balanceQuery' => '0',
                    'preAuth' => '0',
                    'terminalYd' => '1',
                    'storeInfo' => [
                        'headFssId' => $info['shop_entrance_pic'],
                        'inDoorFssId' => $info['shop_indoor_pic'],
                        'cashierFssId' => $info['shop_cashier_pic'],
                        'mobile' => $info['contact_phone'],
                        'contact' => $info['contact_name'],
                        'areaCode' => $info['shop_address'],
                        'address' => $info['shop_address_detail'],
                        'storeName' => $info['shop_name'],
                    ],
                ]
            ],
        ];

        $result = $this->service->modify($params);

        $DB->update('applymerchant', ['ext'=>'terminal|'.$result['orderId'], 'status'=>5, 'updatetime'=>'NOW()'], ['id'=>$row['id']]);
    }

    //结算信息修改
    public function settlement($row, $data){
        global $conf, $DB;
        $config = json_decode($this->applychannel['config'], true);
        if(empty($config['operator'])){
            return ['code'=>-1, 'msg'=>'未配置操作员账号'];
        }

        $info = json_decode($row['info'], true);
        $package_code = 'PSS-OFFLINE-004-001';

        $additionList = [];
        $params = [
            'operator' => $config['operator'],
            'subBillAccount' => $info['sub_account'],
            'packageInfo' => [
                'packageCode' => $package_code,
                'packageParameter' => [
                    'direPayInfo' => [
                        'isAutoDirePay' => '1',
                        'isHolidayCash' => $data['is_holiday_settle'],
                        'holidayCashFee' => $data['holiday_settle_rate'],
                        'workingDayFee' => $data['dire_pay_fee'],
                        'isAutoCash' => '1',
                    ],
                    'settlementInfo' => [
                        'accountType' => $data['settle_account_type'],
                        'accountNo' => $data['account_number'],
                        'accountName' => $data['account_name'],
                        'bankName' => $data['bank_name'],
                        'bankBranch' => $data['bank_branch_name'],
                        'areaCode' => $data['bank_address'],
                        'bankMobilePhone' => $data['bank_phone']
                    ],
                ]
            ],
        ];
        if($data['merchant_type'] == '3'){
            $params['packageInfo']['packageParameter']['accOpenInfo'] = [
                'accOpenFssId' => $data['bank_license_image'],
                //'accOpenCode' => $data['bank_license_no']
            ];
        }
        if($data['settle_account_type'] == '2'){
            if($data['auth_idcard_period_end'] == '长期') $data['auth_idcard_period_end'] = '9999-12-31';
            $params['packageInfo']['packageParameter']['authorizerInfo'] = [
                'authName' => $data['auth_idcard_name'],
                'authIdCard' => $data['auth_idcard_no'],
                'beginDate' => $data['auth_idcard_period_begin'],
                'expireDate' => $data['auth_idcard_period_end'],
                'authPersonFssId' => $data['auth_idcard_image'],
                'authNationalFssId' => $data['auth_idcard_back_image'],
                'authTel' => $data['bank_phone']
            ];
        }
        if(!empty($additionList)){
            $params['additionList'] = $additionList;
        }

        try{
            $result = $this->service->modify($params);
        }catch(Exception $e){
            return ['code'=>-1, 'msg'=>'结算账户修改失败，'.$e->getMessage()];
        }

        $info = array_merge($info, $data);

        $DB->update('applymerchant', ['ext'=>'settle|'.$result['orderId'], 'status'=>5, 'updatetime'=>'NOW()', 'info' => json_encode($info)], ['id'=>$row['id']]);

        return ['code'=>0, 'msg'=>'结算账户修改提交成功！请等待快钱支付审核'];
    }

    //上传图片
    public function uploadImage($filepath, $filename){
        try{
            $image_id = $this->service->uploadImage($filepath, $filename);
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

        if($this->channel['merchant_id'] && substr($this->channel['merchant_id'],0,1)=='[' && $this->channel['terminal_id'] && substr($this->channel['terminal_id'],0,1)=='['){
            $merchantIdKey = substr($this->channel['merchant_id'],1,-1);
            $terminalIdKey = substr($this->channel['terminal_id'],1,-1);
            $subMemberCode = substr($this->channel['appmchid'],1,-1);
        }else{
            return ['code'=>-1, 'msg'=>'当前支付通道参数配置错误，需要在商户号和终端号字段使用变量模式'];
        }
        $mchid = explode('|', $row['mchid']);
        $info = json_encode([$merchantIdKey => $mchid[1], $terminalIdKey => $mchid[2], $subMemberCode => $mchid[0]]);

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

    private function getMchType($merchant_type){
        $mchtype = ['1' => '0', '2' => '5', '3' => '1'];
        return $mchtype[$merchant_type];
    }


    private static function getcheck(){
        global $CACHE;
        
    }

}
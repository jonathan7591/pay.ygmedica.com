<?php
namespace lib\Applyments;

use Exception;

class CommUtil
{
    private static $typeList = [
        'Alipay' => [
            'name' => '支付宝服务商',
            'pay_plugin' => 'alipaysl',
            'icon' => 'alipay.ico',
        ],
        'AlipayDirect' => [
            'name' => '支付宝直付通',
            'pay_plugin' => 'alipayd',
            'icon' => 'alipay.ico',
        ],
        'Wxpay' => [
            'name' => '微信支付服务商',
            'pay_plugin' => 'wxpaynp',
            'icon' => 'wechat.ico',
        ],
        'WxpayEC' => [
            'name' => '微信支付收付通',
            'pay_plugin' => 'wxpaynp',
            'icon' => 'wechat.ico',
        ],
        'Kuaiqian' => [
            'name' => '快钱支付',
            'pay_plugin' => 'kuaiqian',
            'icon' => 'kuaiqian.ico',
        ],
        'Hnapay' => [
            'name' => '新生易聚合支付',
            'pay_plugin' => 'xsy',
            'icon' => 'hnapay.ico',
        ],
		'Haipay' => [
            'name' => '海科聚合支付',
            'pay_plugin' => 'haipay',
            'icon' => 'haipay.ico',
        ],
    ];

    private static $formType = ['view'=>'查看商户详情', 'create'=>'新增商户进件', 'modify'=>'修改商户信息', 'settlement'=>'修改结算账户', 'upgrade'=>'商户限额升级', 'config'=>'系统配置', 'cancel'=>'申请注销商户', 'setmchid'=>'设置应用授权token', 'setkey'=>'设置商户自定义信息', 'report'=>'提交商户报备', 'withdraw'=>'申请提现'];

    public static function getTypeList()
    {
        return self::$typeList;
    }

    public static function getModel($cid){
        global $DB, $conf;
        $applychannel = $DB->find('applychannel', '*', ['id'=>$cid]);
        if(!$applychannel) return false;
	    $channel = \lib\Channel::get($applychannel['channel']);
        if(!$channel) return false;
        if($applychannel['price'] > 0 && $conf['applyments_free'] == 1) $applychannel['price'] = 0;

        $type = $applychannel['type'];
        $classname = '\\lib\\Applyments\\'.$type.'\\'.$type;
        if (class_exists($classname)) {
            $x = new $classname($applychannel, $channel);
            $x->type = $type;
            $x->plugin = $channel['plugin'];
            return $x;
        }else{
            return false;
        }
    }

    public static function getModel2($channel){
        global $DB;
        $applychannel = $DB->find('applychannel', '*', ['channel'=>$channel['id']]);
        if(!$applychannel) return false;

        $type = $applychannel['type'];
        $classname = '\\lib\\Applyments\\'.$type.'\\'.$type;
        if (class_exists($classname)) {
            return new $classname($applychannel, $channel);
        }else{
            return false;
        }
    }

    public static function getOperation($type, $row){
        $classname = '\\lib\\Applyments\\'.$type.'\\'.$type;
        if (class_exists($classname)) {
            return $classname::getOperation($row);
        }else{
            return [];
        }
    }

    //证照识别
    public static function imageRecognize($type, $file_path){
        global $conf;
        if($type && (empty($conf['ocr_aliyunid']) || empty($conf['ocr_aliyunkey'])))throw new Exception('请先配置阿里云OCR文字识别密钥');
        $recognize = new \lib\AliyunRecognize($conf['ocr_aliyunid'], $conf['ocr_aliyunkey']);
        $result = null;
        if($type == 'idcard'){
            try{
                $data = $recognize->RecognizeIdcard($file_path);
                if(isset($data['data']['face'])){
                    $arr = $data['data']['face']['data'];
                    $result = ['id_no'=>$arr['idNumber'], 'name'=>$arr['name'], 'address'=>$arr['address'], 'sex'=>$arr['sex'], 'ethnicity'=>$arr['ethnicity'], 'birthDate'=>$arr['birthDate']];
                }else{
                    throw new Exception('身份证识别失败，请上传人像面照片');
                }
            }catch(Exception $e){
                throw new Exception('身份证识别失败，'.$e->getMessage());
            }
        }elseif($type == 'idcard_back'){
            try{
                $data = $recognize->RecognizeIdcard($file_path);
                if(isset($data['data']['back'])){
                    $arr = $data['data']['back']['data'];
                    $period = explode('-', $arr['validPeriod']);
                    $result = ['issue_authority'=>$arr['issueAuthority'], 'valid_period'=>$arr['validPeriod'], 'period_begin'=>str_replace('.','-',$period[0]), 'period_end'=>str_replace('.','-',$period[1])];
                }else{
                    throw new Exception('身份证识别失败，请上传国徽面照片');
                }
            }catch(Exception $e){
                throw new Exception('身份证识别失败，'.$e->getMessage());
            }
        }elseif($type == 'bankcard'){
            try{
                $data = $recognize->RecognizeBankCard($file_path);
                if(isset($data['data'])){
                    $arr = $data['data'];
                    $result = ['bank_name'=>$arr['bankName'], 'card_type'=>$arr['cardType'], 'card_no'=>$arr['cardNumber'], 'period_end'=>$arr['validToDate']];
                }
            }catch(Exception $e){
                throw new Exception('银行卡识别失败，'.$e->getMessage());
            }
        }elseif($type == 'business'){
            try{
                $data = $recognize->RecognizeBusinessLicense($file_path);
                if(isset($data['data'])){
                    $arr = $data['data'];
                    $result = ['license_no'=>$arr['creditCode'], 'name'=>$arr['companyName'], 'address'=>$arr['businessAddress'], 'valid_period'=>$arr['validPeriod'], 'reg_date'=>str_replace(['年','月','日'], ['-','-',''], $arr['RegistrationDate']), 'legal_name'=>$arr['legalPerson'], 'type'=>$arr['companyType'], 'registered_capital'=>$arr['registeredCapital'], 'business_scope'=>$arr['businessScope'], 'period_begin'=>self::formatDate($arr['validFromDate']), 'period_end'=>self::formatDate($arr['validToDate'])];
                }
            }catch(Exception $e){
                throw new Exception('营业执照识别失败，'.$e->getMessage());
            }
        }elseif($type == 'bankaccount'){
            try{
                $data = $recognize->RecognizeBankAccountLicense($file_path);
                if(isset($data['data'])){
                    $arr = $data['data'];
                    $result = ['bank_account'=>$arr['bankAccount'], 'legal_name'=>$arr['legalRepresentative'], 'bank_name'=>$arr['depositaryBank'], 'approval_no'=>$arr['approvalNumber'], 'name'=>$arr['customerName'], 'permit_no'=>$arr['permitNumber']];
                }
            }catch(Exception $e){
                throw new Exception('银行开户许可证识别失败，'.$e->getMessage());
            }
        }
        
        return $result;
    }

    private static function formatDate($str){
        return substr($str, 0, 4).'-'.substr($str, 4, 2).'-'.substr($str, 6, 2);
    }

    public static function getFormTitle($action){
        if(!isset(self::$formType[$action]))return '自定义表单';
        return self::$formType[$action];
    }

    public static function payForMerchant($id, $uid, $money){
        global $DB;
        $userrow = $DB->find('user', 'money', ['uid'=>$uid]);
        if($userrow['money'] >= $money){
            changeUserMoney($uid, $money, false, '新增商户进件');
            $DB->update('applymerchant', ['paid'=>1], ['id'=>$id]);
            return true;
        }
        return false;
    }

    public static function getBankBranchList($bank_code, $city_code){
        global $siteurl;
        $url = 'https://api.cccyun.cc/bankbranch.php?bank_code='.$bank_code.'&city_code='.$city_code;
        $response = get_curl($url, 0, $siteurl);
        $result = json_decode($response, true);
        if(isset($result['code']) && $result['code'] == 0){
            return $result['data'];
        }else{
            throw new \Exception($result['msg']);
        }
    }

}
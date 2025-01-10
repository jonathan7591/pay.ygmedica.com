<?php

class lianlianpay_plugin
{
	static public $info = [
		'name'        => 'lianlianpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '连连惠商通支付', //支付插件显示名称
		'author'      => '连连惠商通支付', //支付插件作者
		'link'        => 'https://join.lianlianpay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['alipay','wxpay','bank'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appurl' => [
				'name' => '接口域名',
				'type' => 'input',
				'note' => '必须以http://或https://开头，以/结尾',
			],
			'appmchid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appid' => [
				'name' => '应用AppId',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '私钥AppSecret',
				'type' => 'textarea',
				'note' => '',
			],
		],
		'select_alipay' => [
			'1' => '支付宝扫码',
			'2' => '支付宝PC网站',
			'3' => '支付宝WAP',
			'4' => '支付宝生活号',
			'5' => '聚合码收银台'
		
		],
		'select_wxpay' => [
			'1' => '微信扫码',
			'2' => '微信H5',
			'3' => '微信公众号',
			'4' => '微信小程序',
			'5' => '聚合扫码',
			'6' => 'WEB收银台',
		],
		'select_bank' => [
			'1' => '云闪付扫码',
			'5' => '聚合扫码',
			'6' => '银行卡收银台',
		],
		'note' => '', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false && in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile()==true){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		if($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && in_array('3',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile'){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	static private function getMillisecond()
	{
		list($s1, $s2) = explode(' ', microtime());
		return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
	}

	//下单通用
	static private function addOrder($wayCode, $channelExtra = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
       if($wayCode=='BANK_CARD_PAY'){
           
            return \lib\Payment::lockPayData(TRADE_NO, function() use($client,$wayCode) {
                
    	        $pay_url = self::zhbank(TRADE_NO);
    // 			$result = $pay->scanPay($param);
    			return ['payurl',$pay_url];
    		}); 
       }
		$apiurl = $channel['appurl'].'/v1/txn/tradecreate';
		$current = date("YmdHis");//当前时间
		if(empty($order['param'])){
            $order['param']='{
                "contact":"测试",
                "submchid": "2408061532473822918",
                "phone": "13170295010",
                "province": "安徽省",
                "city": "黄山市"
            }';
	    }
		$params = json_decode($order['param'],true);
		
		$TradeCreateParams = [
		        'timestamp'=>$current,
		        'oid_partner'=>$channel['appmchid'],
		        'txn_type'=>'SECURED_CONSUME',
		        'user_id'=>$params['phone'],
		        'user_type'=>'ANONYMOUS',
		        
		        'notify_url'=>$conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		        'return_url'=>$siteurl.'pay/return/'.TRADE_NO.'/',
		    ];
	    $orderInfo = [
	        'txn_seqno'=>TRADE_NO,
	        'txn_time'=>date('YmdHis'),
	        'total_amount'=>round($order['realmoney'],2),
	        'goods_name'=>'订单疑问请联系客服4008116028，或微信关注公众号【锦鲤盒子】',
	        'goods_url'=>'https://tb.53kf.com/code/client/9394e235306c13840f514a0c6d3c462b8/1'
	        ];
	    $TradeCreateParams['orderInfo']= $orderInfo;  
		$payeeInfo = [
		    'payee_id'=>$channel['appmchid'],
		    'payee_type'=>'MERCHANT',
		    'payee_amount'=>round($order['realmoney'],2),
		 ];    
		$TradeCreateParams['payeeInfo'] = $payeeInfo;
		require_once PAY_ROOT."inc/LLianPayClient.php";
		$client = new LLianPayClient();
        $result = $client->sendRequest($apiurl,json_encode($TradeCreateParams));
    	if($result['ret_code']==0000){
    	     //进行下单支付操作
    	      return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $TradeCreateParams,$wayCode) {
    	        $pay_url = self::pay(TRADE_NO,$TradeCreateParams,$wayCode);
    // 			$result = $pay->scanPay($param);
    			return ['qrcode',$pay_url];
    		});
    	 }else{
    	    throw new Exception($result['ret_msg']?$result['ret_msg']:'返回数据解析失败'); 
    	 }

		
	}
	
	
	//网关支付
	static private function pay($txn_seqno,$TradeCreateParams,$wayCode){
	    global $siteurl, $channel, $order, $ordername, $conf, $clientip;
	    $current = date("YmdHis");//当前时间
	    if(empty($order['param'])){
            $order['param']='{
                "contact":"测试",
                "submchid": "2408061532473822918",
                "phone": "13170295010",
                "province": "安徽省",
                "city": "黄山市"
            }';
	    }
	    $params = json_decode($order['param'],true);
	    $jsonFilePath =  PLUGIN_ROOT . 'lianlianpay'  . DIRECTORY_SEPARATOR . 'city.json';

        // 读取并解析JSON文件
        $jsonData = file_get_contents($jsonFilePath);
        // var_dump($jsonData);
        $provinceCityMapping = json_decode($jsonData, true);
        
        // 获取用户输入的省市
        $province = isset($params['province']) ? $params['province'] : '';
        $city = isset($params['city']) ? $params['city'] : '';
        
        $provinceCode = '';
        $cityCode = '';
        
        // 遍历省份查找对应的省份和城市代码
        foreach ($provinceCityMapping as $provinceData) {
            if ($provinceData['region'] === $province) {
                $provinceCode = $provinceData['code'];  // 找到省份代码
        
                // 遍历省份内的城市列表
                foreach ($provinceData['regionEntitys'] as $cityData) {
                    if ($cityData['region'] === $city) {
                        $cityCode = $cityData['code'];  // 找到城市代码
                        break;
                    }
                }
                break;
            }
        }
	    $apiurl = $channel['appurl'].'/v1/txn/payment-gw';
	    $risk = array(
	        'frms_ware_category'=>'4016',
	        'user_info_mercht_userno'=>$TradeCreateParams['user_id'],
	        'user_info_bind_phone'=>$params['phone'],
	        'user_info_dt_register'=>$current,
	        'goods_name'=>'锦鲤盒子',
	        'virtual_goods_status'=>'0',
	        'goods_count'=>1,
	        'delivery_full_name'=>$params['contact'],
	        'delivery_phone'=>$params['phone'],
	        'logistics_mode'=>'2',
	        'delivery_cycle'=>'48h',
	        'delivery_addr_province'=>$provinceCode,
	        'delivery_addr_city'=>$cityCode
	        );
	    $encodedRiskItem = json_encode($risk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	    
	    $payParams = [
	        'timestamp'=>$current,
	        'oid_partner'=>$channel['appmchid'],
	        'txn_seqno'=>$txn_seqno,
	        'total_amount'=>round($order['realmoney'],2),
	        'risk_item'=>$encodedRiskItem,
	       
	        'client_ip'=>$clientip
	        ];
	   if($params['submchid']!='2408061532473822918'){
	      $payParams['extend_params'] = json_encode(array('accp_sub_mch_id'=>$params['submchid']));
	   }
	   $payerInfo = [
	       'payer_type'=>'USER',
	       'payer_id'=>$TradeCreateParams['user_id']
	       ];
	   $payParams['payerInfo'] = $payerInfo;
	   $payMethods = [
	       'method'=>$wayCode,
	       'amount'=>round($order['realmoney'],2),
	       ];
	    $payParams['payMethods'] =  $payMethods;
	   	require_once PAY_ROOT."inc/LLianPayClient.php";
		$client = new LLianPayClient();
		$result = $client->sendRequest($apiurl,json_encode($payParams));
 		
    	if($result['ret_code']==0000){
    	     //发起支付返回
    	   //  var_dump($result);
    	    return $result['gateway_url'];
    	   
    	 }else{
    	    throw new Exception($result['ret_msg']?$result['ret_msg']:'返回数据解析失败'); 
    	 }
	   
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device, $mdevice, $siteurl;
	//	var_dump($channel['apptype']);
		if(in_array('3',$channel['apptype']) && ($device=='mobile' || checkmobile())){
			$wayCode = 'ALIPAY_H5';
		}elseif(in_array('2',$channel['apptype']) && ($device=='pc' || !checkmobile())){
			$wayCode = 'ALIPAY_WEB';
		}elseif(in_array('1',$channel['apptype'])){
			$wayCode = 'ALIPAY_NATIVE'; //扫码
		}elseif(in_array('4',$channel['apptype'])){
			$qrcode_url = $siteurl.'pay/alipayjs/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$qrcode_url];
		}elseif(in_array('5',$channel['apptype'])){
		    $wayCode = 'AGGREGATE_CODE';
		}
		else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}

		try{
			list($type, $payData) = self::addOrder($wayCode);
// 			var_dump($payData);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}
        //var_dump($payData);
		if($wayCode == 'QR_CASHIER' && strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient')===false && $mdevice!='alipay'){
			$type = 'codeurl';
		}
        // var_dump($type);
		if($type == 'payurl'){
			return ['type'=>'jump','url'=>$payData];
		}elseif($type == 'form'){
			return ['type'=>'html','url'=>$payData];
		}else{
		  //  var_dump($payData);
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$payData];
		}
	}

	//支付宝生活号支付
	static public function alipayjs(){
		global $channel;

		if (!isset($_GET['channelUserId'])) {
			$apiurl = $channel['appurl'].'api/channelUserId/jump';
			$redirect_url = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			$param = [
				'mchNo' => $channel['appmchid'],
				'appId' => $channel['appid'],
				'ifCode' => 'AUTO',
				'redirectUrl' => $redirect_url,
				'reqTime' => self::getMillisecond(),
				'version' => '1.0',
				'signType' => 'MD5',
			];
			$param['sign'] = self::make_sign($param, $channel['appkey']);
			$jump_url = $apiurl.'?'.http_build_query($param);
			return ['type'=>'jump','url'=>$jump_url];
		}else{
			$openId = $_GET['channelUserId'];
		}

		$blocks = checkBlockUser($openId, TRADE_NO);
		if($blocks) return $blocks;

		try{
			$extra = json_encode(['buyerUserId' => $openId]);
			list($type, $payData) = self::addOrder('ALI_JSAPI', $extra);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}

		if($type == 'payurl'){
			return ['type'=>'jump','url'=>$payData];
		}elseif($type == 'form'){
			return ['type'=>'html','url'=>$payData];
		}else{
			if($_GET['d']=='1'){
				$redirect_url='data.backurl';
			}else{
				$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
			}
			$arr = json_decode($payData, true);
			return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$arr['alipayTradeNo'], 'redirect_url'=>$redirect_url]];
		}
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $device, $mdevice, $siteurl;
		if(in_array('1',$channel['apptype'])){
			$wayCode = 'WX_NATIVE';
		}elseif(in_array('3',$channel['apptype'])){
			$qrcode_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$qrcode_url];
		}elseif(in_array('4',$channel['apptype'])){
			$qrcode_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$qrcode_url];
		}elseif(in_array('5',$channel['apptype'])){
			$wayCode = 'QR_CASHIER';
		}elseif(in_array('6',$channel['apptype'])){
			$wayCode = 'WEB_CASHIER';
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}
		try{
			list($type, $payData) = self::addOrder($wayCode);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if($wayCode == 'QR_CASHIER' && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')===false && $mdevice!='wechat'){
			$type = 'codeurl';
		}
		if($type == 'payurl'){
			return ['type'=>'jump','url'=>$payData];
		}elseif($type == 'form'){
			return ['type'=>'html','url'=>$payData];
		}elseif (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$payData];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$payData];
		}
	}

    //银行卡快捷
    static private function zhbank($txn_seqno){
        global $siteurl, $channel, $order, $ordername, $conf, $clientip;
        // var_dump($channel);
        $current = date("YmdHis");//当前时间
	    if(empty($order['param'])){
            $order['param']='{
                "contact":"测试",
                "submchid": "2408061532473822918",
                "phone": "13170295010",
                "province": "安徽省",
                "city": "黄山市"
            }';
	    }
	    $params = json_decode($order['param'],true);
	    $jsonFilePath =  PLUGIN_ROOT . 'lianlianpay'  . DIRECTORY_SEPARATOR . 'city.json';

        // 读取并解析JSON文件
        $jsonData = file_get_contents($jsonFilePath);
        // var_dump($jsonData);
        $provinceCityMapping = json_decode($jsonData, true);
        
        // 获取用户输入的省市
        $province = isset($params['province']) ? $params['province'] : '';
        $city = isset($params['city']) ? $params['city'] : '';
        
        $provinceCode = '';
        $cityCode = '';
        
        // 遍历省份查找对应的省份和城市代码
        foreach ($provinceCityMapping as $provinceData) {
            if ($provinceData['region'] === $province) {
                $provinceCode = $provinceData['code'];  // 找到省份代码
        
                // 遍历省份内的城市列表
                foreach ($provinceData['regionEntitys'] as $cityData) {
                    if ($cityData['region'] === $city) {
                        $cityCode = $cityData['code'];  // 找到城市代码
                        break;
                    }
                }
                break;
            }
        }
	    $apiurl = 'https://accpgw.lianlianpay.com//v1/cashier/paycreate';
	    
	    $risk = array(
	        'frms_ware_category'=>'4016',
	        'user_info_mercht_userno'=>$params['phone'],
	        'user_info_bind_phone'=>$params['phone'],
	        'user_info_dt_register'=>$current,
	        'goods_name'=>'宝贝盒子',
	        'virtual_goods_status'=>'0',
	        'goods_count'=>1,
	        'delivery_full_name'=>$params['contact'],
	        'delivery_phone'=>$params['phone'],
	        'logistics_mode'=>'2',
	        'delivery_cycle'=>'48h',
	        'delivery_addr_province'=>$provinceCode,
	        'delivery_addr_city'=>$cityCode
	        );
	    $encodedRiskItem = json_encode($risk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	    $payParams = [
	        'timestamp'=>$current,
	        'oid_partner'=>$channel['appmchid'],
	        'txn_type'=>'SECURED_CONSUME',
	        'user_id'=>$params['phone'],
	        'user_type'=>'ANONYMOUS',
	        'flag_chnl'=>'H5',
	        'allow_method'=>'EBANK_DEBIT_CARD',
	        'notify_url'=>$conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		    'return_url'=>$siteurl.'pay/return/'.TRADE_NO.'/',
		    
	       // 'txn_seqno'=>$txn_seqno,
	       // 'total_amount'=>round($order['realmoney'],2),
	        'risk_item'=>$encodedRiskItem,
	       
	        ];
	    $orderInfo = [
	        'txn_seqno'=>$txn_seqno,
	        'txn_time'=>$current,
	        'total_amount'=>round($order['realmoney'],2),
	        'goods_name'=>'宝贝盒子'
	        
	        ];
	    $payParams['orderInfo'] = $orderInfo;
	    $payeeInfo=[
	        'payee_id'=>$channel['appmchid'],
	        'payee_type'=>'MERCHANT',
	        'payee_amount'=>round($order['realmoney'],2),
	        ];
	    $payParams['payeeInfo'] = $payeeInfo;   
	    $payerInfo=[
	        'payer_type'=>'USER',
	        'payer_id'=>$params['phone']
	        
	        ];
	    $payParams['payerInfo'] = $payerInfo;
	   
	    require_once PAY_ROOT."inc/LLianPayClient.php";
		$client = new LLianPayClient();
		$result = $client->sendRequest($apiurl,json_encode($payParams));
 	
    	if($result['ret_code']==0000){
    	     //发起支付返回
    	   //  var_dump($result);
    	    return $result['gateway_url'];
    	   
    	 }else{
    	    throw new Exception($result['ret_msg']?$result['ret_msg']:'返回数据解析失败'); 
    	 }
    }
	//云闪付扫码支付
	static public function bank(){
		global $channel;
		if(in_array('1',$channel['apptype'])){
			$wayCode = 'YSF_NATIVE';
		}elseif(in_array('5',$channel['apptype'])){
			$wayCode = 'QR_CASHIER';
		}elseif(in_array('6',$channel['apptype'])){
			$wayCode = 'BANK_CARD_PAY'; //银行卡收银台
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}

		try{
			list($type, $payData) = self::addOrder($wayCode);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'银行卡支付下单失败！'.$ex->getMessage()];
		}

		if($type == 'payurl'){
			return ['type'=>'jump','url'=>$payData];
		}elseif($type == 'form'){
			return ['type'=>'html','url'=>$payData];
		}else{
			return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$payData];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf;

		//①、获取用户openid
		if($channel['appwxmp']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
			try{
				$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
				$openid = $tools->GetOpenid();
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
		}else{
			if (!isset($_GET['channelUserId'])) {
				$apiurl = $channel['appurl'].'api/channelUserId/jump';
				$redirect_url = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
				$param = [
					'mchNo' => $channel['appmchid'],
					'appId' => $channel['appid'],
					'ifCode' => 'AUTO',
					'redirectUrl' => $redirect_url,
					'reqTime' => self::getMillisecond(),
					'version' => '1.0',
					'signType' => 'MD5',
				];
				$param['sign'] = self::make_sign($param, $channel['appkey']);
				$jump_url = $apiurl.'?'.http_build_query($param);
				return ['type'=>'jump','url'=>$jump_url];
			}else{
				$openid = $_GET['channelUserId'];
			}
		}

		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;
		
		//②、统一下单
		try{
			$extra = json_encode(['openid' => $openid]);
			list($type, $jsApiParameters) = self::addOrder('WX_JSAPI', $extra);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		
		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$jsApiParameters, 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel, $order, $ordername, $conf;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');
		
		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		try{
			$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
			$openid = $tools->AppGetOpenid($code);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');

		//②、统一下单
		try{
			$extra = json_encode(['openid' => $openid]);
			list($type, $jsApiParameters) = self::addOrder('WX_LITE', $extra);
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"微信支付下单失败！'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($jsApiParameters, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if(in_array('2',$channel['apptype'])){ //H5支付
			try{
				list($type,$jump_url) = self::addOrder('WX_H5');
				return ['type'=>'jump','url'=>$jump_url];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信H5支付下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('4',$channel['apptype']) && $channel['appwxa']>0){ //小程序支付
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}elseif(in_array('3',$channel['apptype'])){ //公众号支付
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;
		// 获取上游发送的所有请求头
		$headers = [];
        foreach ($_SERVER as $key => $value) {
         //  if (strpos($key, 'HTTP_') == 0) {
            //    $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$key] = $value;
            
        }
//         // $headers = $_SERVER['HTTP_YOUR_HEADER_NAME'];
        
//         // 1. 获取上游发送的 Signature-Type 和 Signature-Data
        $signatureType = isset($headers['HTTP_SIGNATURE_TYPE']) ? $headers['HTTP_SIGNATURE_TYPE'] : '';
        $signatureData = isset($headers['HTTP_SIGNATURE_DATA']) ? $headers['HTTP_SIGNATURE_DATA'] : '';
        
        // 2. 获取上游发送的 JSON 请求体内容
        $jsonContent = file_get_contents('php://input');
        file_put_contents('llnoti.txt','返回内容'.$jsonContent.'头部：'.$signatureData.PHP_EOL,FILE_APPEND);
        $arr = json_decode($jsonContent, true);
    
        // 如果没有接收到数据，返回错误
        if (empty($jsonContent) || empty($arr)) {
            return ['type' => 'html', 'data' => 'no data received'];
        }
        //  require_once PAY_ROOT . "inc/LLianPayAccpSignature.php";
// 		$signVar = LLianPayAccpSignature::sign($content);
        // 3. 验证签名是否有效
        // $isValid = LLianPayAccpSignature::checkSign($jsonContent, $signatureData);
        // if (!$isValid) {
        //     return ['type' => 'html', 'data' => 'signature verification failed'];
        // }
    
        // 4. 处理业务逻辑
        if ($arr['txn_status'] === 'TRADE_SUCCESS') {
            $orderInfo = $arr['orderInfo'];
            $txnSeqNo = $orderInfo['txn_seqno'];
            $amount = $orderInfo['total_amount'];
    
            // 假设 TRADE_NO 是本地订单号，校验订单号和金额
            if ($txnSeqNo == TRADE_NO && $amount == strval($order['realmoney'])) {
                
                processNotify($order, $arr['accp_txno']);  // 处理通知
                self::confirm_order();
                return ['type' => 'html', 'data' => 'Success'];  // 返回给上游成功处理
            } else {
                return ['type' => 'html', 'data' => '订单校验失败'];
            }
        } else {
            return ['type' => 'html', 'data' => 'state=' . htmlspecialchars($arr['txn_status'])];
        }
        // $arr = $_POST;
	

		
// 			if($arr['txn_status'] == 'TRADE_SUCCESS'){
// 				$out_trade_no = $arr['mchOrderNo'];
// 				$api_trade_no = $arr['payOrderId'];
// 				$money = $arr['amount'];

// 				if ($out_trade_no == TRADE_NO && $money==strval($order['realmoney']*100)) {
// 					processNotify($order, $api_trade_no);
// 				}
// 				return ['type'=>'html','data'=>'success'];
// 			}else{
// 				return ['type'=>'html','data'=>'state='.$arr['state']];
// 			}
		
	}
	
	//交易结果确认
	static public function confirm_order(){
	   global $siteurl, $channel, $order, $ordername, $conf, $clientip;
	   $apiurl = $channel['appurl'].'/v1/txn/secured-confirm';
	   $params = json_decode($order['param'],true);
	   $current = date("YmdHis");//当前时间
	   $conparam = [
	       'timestamp'=>$current,
	       'oid_partner'=>$channel['appmchid'],
	       'user_id'=>$params['phone'],
	       'confirm_mode'=>'ALL'
	       ];
	   $originalOrderInfo = [
	       'txn_seqno'=> $order['trade_no'],
	       'total_amount'=>$order['realmoney']
	       ];
	   $conparam['originalOrderInfo']=$originalOrderInfo;
	   $confirmOrderInfo = [
	       'confirm_seqno'=>'CON'.$order['trade_no'],
	       'confirm_time'=>date('YmdHis'),
	       'confirm_amount'=>$order['realmoney']
	       ];
	   $conparam['confirmOrderInfo']=$confirmOrderInfo;
	   	require_once PAY_ROOT."inc/LLianPayClient.php";
		$client = new LLianPayClient();
        $result = $client->sendRequest($apiurl,json_encode($conparam));
        file_put_contents(date('Ymd').'confirm.txt',json_encode($result).PHP_EOL,FILE_APPEND);
    	if($result['ret_code']==0000){
    	     return $result['accp_confirm_txno'];
    	 }else{
    	    throw new Exception($result['ret_msg']?$result['ret_msg']:'返回数据解析失败'); 
    	 }
	   
	}

	//支付返回页面
	static public function return(){
		global $channel, $order;
		// 检查POST数据是否存在
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return ['type' => 'error', 'msg' => '非法请求方式'];
            }
         
            // 提取POST中的签名类型、签名数据和交易内容
            $signatureType = $_POST['Signature-Type'] ?? '';
            $signatureData = $_POST['Signature-Data'] ?? '';
            $jsonContext = $_POST['json_context'] ?? '';
           file_put_contents('llre.txt',$jsonContext);
            if (empty($signatureType) || empty($signatureData) || empty($jsonContext)) {
                return ['type' => 'error', 'msg' => '缺少必要的参数'];
            }
        
            // 将 json_context 转为数组
            $contextData = json_decode($jsonContext, true);
            if (!$contextData) {
                return ['type' => 'error', 'msg' => 'json_context 解析失败'];
            }
        
            // // 校验签名 (根据具体支付平台的要求)
            // if (!self::verifySignature($jsonContext, $signatureData)) {
            //     return ['type' => 'error', 'msg' => '签名校验失败'];
            // }
        
            // 提取订单信息和交易状态
            $txn_status = $contextData['txn_status'] ?? '';
            $orderInfo = $contextData['orderInfo'] ?? [];
            $out_trade_no = $orderInfo['txn_seqno'] ?? '';
            $money = $orderInfo['total_amount'] ?? 0;
        
            // 确认交易状态成功
            if ($txn_status === 'TRADE_SUCCESS') {
                // 比较订单号并验证金额
                if ($out_trade_no === TRADE_NO && bccomp($money, $order['realmoney'], 2) == 0) {
                    processReturn($order, $contextData['accp_txno']);  // 处理订单
                    // return ['type' => 'success', 'msg' => '订单处理成功'];
                } else {
                    return ['type' => 'error', 'msg' => '订单信息或金额校验失败'];
                }
            } else {
                return ['type' => 'error', 'msg' => '交易未成功，状态：' . htmlspecialchars($txn_status)];
            }
// 			if($_POST['txn_status'] == 'TRADE_SUCCESS'){
// 				$out_trade_no = daddslashes($_POST['mchOrderNo']);
// 				$api_trade_no = daddslashes($_POST['payOrderId']);
// 				$money = $_POST['amount'];

// 				if ($out_trade_no == TRADE_NO && $money==strval($order['realmoney']*100)) {
// 					processReturn($order, $api_trade_no);
// 				}else{
// 					return ['type'=>'error','msg'=>'订单信息校验失败'];
// 				}
// 			}else{
// 				return ['type'=>'error','msg'=>'state='.$_POST['state']];
// 			}
		
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$apiurl = $channel['appurl'].'/v1/txn/more-payee-refund';
		$params = json_decode($order['param'],true);
		$current = date("YmdHis");//当前时间
		$rparam = [
		        'timestamp'=>$current,
		        'oid_partner'=>$channel['appmchid'],
		        'user_id'=>$params['phone'],
		        'notify_url'=>$conf['localurl'].'pay/refundnotify/'.$channel['id'].'/',
		    ];
		    
		$originalOrderInfo = [
		    'txn_seqno'=>$order['trade_no'],
		    'total_amount'=>$order['realmoney'],
		    ];
		$rparam['originalOrderInfo'] = $originalOrderInfo;
		$refundOrderInfo=[
		    'refund_seqno'=>'R'.$order['trade_no'],
		    'refund_time'=>date('YmdHis'),
		    'refund_amount'=>round($order['refundmoney'],2),
		    
		    ];
		$rparam['refundOrderInfo'] = $refundOrderInfo;
		$pyeeRefundInfos = [
		    'payee_id'=>$channel['appmchid'],
		    'payee_type'=>'MERCHANT',
		    'payee_accttype'=>'MCHOWN',
		    'payee_refund_amount'=>round($order['refundmoney'],2),
		    ];
		$rparam['pyeeRefundInfos'] = $pyeeRefundInfos;
		$refundMethods = [
		    'method'=>'ALIPAY_NATIVE',
		    'amount'=>round($order['refundmoney'],2),
		    
		    ];
		$rparam['refundMethods'] = $refundMethods;    
		require_once PAY_ROOT."inc/LLianPayClient.php";
		$client = new LLianPayClient();
		$result = $client->sendRequest($apiurl,json_encode($payParams));
		if($result['ret_code']==0000){
    	     //发起支付返回
    	   //  var_dump($result);
    	    return ['code'=>0, 'trade_no'=>$result['txn_seqno'], 'refund_fee'=>$order['refundmoney']];
    	   
    	 }else{
    	    return ['code'=>-1, 'msg'=>$result['ret_msg']?$result['ret_msg']:'返回数据解析失败'];
    	 }
// 		$param = [
// 			'mchNo' => $channel['appmchid'],
// 			'appId' => $channel['appid'],
// 			'payOrderId' => $order['api_trade_no'],
// 			'mchRefundNo' => 'R'.$order['trade_no'],
// 			'refundAmount' => round($order['refundmoney']*100),
// 			'currency' => 'cny',
// 			'refundReason' => '申请退款',
// 			'reqTime' => self::getMillisecond(),
// 			'version' => '1.0',
// 			'signType' => 'MD5',
// 		];

// 		$param['sign'] = self::make_sign($param, $channel['appkey']);

// 		$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);

// 		$result = json_decode($data, true);

// 		if (isset($result['code']) && $result['code'] == 0) {
// 			if($result['data']['errMsg']){
// 				return ['code'=>-1, 'msg'=>'['.$result['data']['errCode'].']'.$result['data']['errMsg']];
// 			}elseif($result['data']['error']){
// 				return ['code'=>-1, 'msg'=>$result['data']['error']];
// 			}
// 			return ['code'=>0, 'trade_no'=>$result['data']['refundOrderId'], 'refund_fee'=>$order['refundmoney']];
// 		} else {
// 			return ['code'=>-1, 'msg'=>$result['msg']?$result['msg']:'返回数据解析失败'];
// 		}
	}
	
	//退款回调
	static public function refundnotify(){
	    global $channel, $order;
	    $sq = $_POST['refund_seqno'];
	    
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $clientip, $conf;
		if(empty($channel) || empty($bizParam))exit();
		$type = $bizParam['type'];
		if($type == 'alipay'){
			$entryType = 'ALIPAY_CASH';
		}elseif($type == 'wxpay'){
			$entryType = 'WX_CASH';
		}elseif($type == 'bank'){
			$entryType = 'BANK_CARD';
		}

		$apiurl = $channel['appurl'].'api/transferOrder';
		$param = [
			'mchNo' => $channel['appmchid'],
			'appId' => $channel['appid'],
			'mchOrderNo' => $bizParam['out_biz_no'],
			'ifCode' => $type,
			'entryType' => $entryType,
			'amount' => round($bizParam['money']*100),
			'currency' => 'cny',
			'accountNo' => $bizParam['payee_account'],
			'accountName' => $bizParam['payee_real_name'],
			'clientIp' => $clientip,
			'transferDesc' => $bizParam['transfer_desc'],
			'notifyUrl' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
			'reqTime' => self::getMillisecond(),
			'version' => '1.0',
			'signType' => 'MD5',
		];

		$param['sign'] = self::make_sign($param, $channel['appkey']);

		$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);

		$result = json_decode($data, true);

		if (isset($result['code']) && $result['code'] == 0) {
			if($result['data']['errMsg']){
				return ['code'=>-1, 'errcode'=>$result['data']['errCode'], 'msg'=>'['.$result['data']['errCode'].']'.$result['data']['errMsg']];
			}elseif($result['data']['error']){
				return ['code'=>-1, 'msg'=>$result['data']['error']];
			}
			if($result['data']['state'] == 2){
				$status = 1;
			}else{
				$status = 0;
			}
			return ['code'=>0, 'status'=>$status, 'orderid'=>$result['data']['transferId'], 'paydate'=>date('Y-m-d H:i:s')];
		} else {
			return ['code'=>-1, 'msg'=>$result['msg']?$result['msg']:'返回数据解析失败'];
		}
	}

	//转账查询
	static public function transfer_query($channel, $bizParam){
		if(empty($channel) || empty($bizParam))exit();

		$apiurl = $channel['appurl'].'api/transfer/query';
		$param = [
			'mchNo' => $channel['appmchid'],
			'appId' => $channel['appid'],
			'transferId' => $bizParam['orderid'],
			'reqTime' => self::getMillisecond(),
			'version' => '1.0',
			'signType' => 'MD5',
		];

		$param['sign'] = self::make_sign($param, $channel['appkey']);

		$data = get_curl($apiurl, json_encode($param), 0, 0, 0, 0, 0, ['Content-Type: application/json']);

		$result = json_decode($data, true);

		if (isset($result['code']) && $result['code'] == 0) {
			if($result['data']['state'] == 2){
				$status = 1;
			}elseif($result['data']['state'] == 1){
				$status = 0;
			}else{
				$status = 2;
			}
			$paydate = date('Y-m-d H:i:s', intval($result['data']['successTime']/1000));
			if($result['data']['errCode'] && $result['data']['errMsg']){
				$errmsg = '['.$result['data']['errCode'].']'.$result['data']['errMsg'];
			}
			return ['code'=>0, 'status'=>$status, 'amount'=>$result['data']['amount'], 'paydate'=>$paydate, 'errmsg'=>$errmsg];
		} else {
			return ['code'=>-1, 'msg'=>$result['msg']?$result['msg']:'返回数据解析失败'];
		}
	}

	static public function transfernotify(){
		global $channel;

		if(isset($_POST['sign'])){
			$arr = $_POST;
		}elseif(isset($_GET['sign'])){
			$arr = $_GET;
		}else{
			return ['type'=>'html','data'=>'no data'];
		}

		$sign = self::make_sign($arr,$channel['appkey']);

		if($sign===$arr["sign"]){
			if($arr['state'] == 2){
				$status = 1;
			}elseif($arr['state'] == 1){
				$status = 0;
			}else{
				$status = 2;
			}
			if($arr['errCode'] && $arr['errMsg']){
				$errmsg = '['.$arr['errCode'].']'.$arr['errMsg'];
			}
			processTransfer($arr['mchOrderNo'], $status, $errmsg);
			return ['type'=>'html','data'=>'success'];
		}else{
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

}
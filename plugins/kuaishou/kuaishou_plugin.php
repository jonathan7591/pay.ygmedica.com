<?php

class kuaishou_plugin
{
	static public $info = [
		'name'        => 'kuaishou', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '快手支付', //支付插件显示名称
		'author'      => '快手', //支付插件作者
		'link'        => '', //支付插件作者链接
		'types'       => ['alipay','wxpay','qqpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'transtypes'  => ['alipay'], //支付插件支持的转账方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '快手号',
				'type' => 'input',
				'note' => '',
			],
			
			'own_channel' => [
				'name' => '是否自有渠道',
				'type' => 'select',
				'options' => [0=>'否',1=>'是'],
			],
		],
		'select_alipay' => [
			'1' => 'H5支付',
		],
		'select_wxpay' => [
			'1' => 'H5支付',
		],
		'select_bank' => [
			'1' => '网银支付',
			'2' => '快捷支付',
			'3' => '云闪付扫码',
		],
		'note' => '无需提供密钥', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename, $submit2;

		/*if(!empty($conf['localurl_alipay']) && !strpos($conf['localurl_alipay'],$_SERVER['HTTP_HOST'])){
			return ['type'=>'jump','url'=>$conf['localurl_alipay'].'pay/submit/'.TRADE_NO.'/'];
		}*/
		
		if($order['typename']=='alipay'){
			if(in_array('1',$channel['apptype']) && checkmobile()){
				if(checkwechat()){
					if(!$submit2){
						return ['type'=>'jump','url'=>'/pay/submit/'.TRADE_NO.'/'];
					}
					return ['type'=>'page','page'=>'wxopen'];
				}
				if(checkalipay()){
					return ['type'=>'jump','url'=>'/pay/alipaywap/'.TRADE_NO.'/'];
				}
				return self::mobilepay('27-3');
			}elseif(in_array('3',$channel['apptype'])){
			    return ['type'=>'jump','url'=>'/pay/fkfpay/'.TRADE_NO.'/'];
			}
			else{
				return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='wxpay'){
		    //	file_put_contents('sb.txt',json_encode($order));
			if(checkwechat() && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && in_array('1',$channel['apptype'])){
				if(checkalipay()){
					if(!$submit2){
						return ['type'=>'jump','url'=>'/pay/submit/'.TRADE_NO.'/'];
					}
					return ['type'=>'page','page'=>'wxopen'];
				}
				if(checkwechat()){
					return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
				}
				return self::mobilepay('26-2');
			}elseif(in_array('3',$channel['apptype'])){
			    return ['type'=>'jump','url'=>'/pay/fkfpay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}
	}
	


	//H5支付
	static private function mobilepay($payType, $aggregatePay = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/PayApp.class.php");

		$apiurl = 'https://www.99bill.com/mobilegateway/recvMerchantInfoAction.htm';
		//$apiurl = 'https://sandbox.99bill.com/mobilegateway/recvMerchantInfoAction.htm';

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$params = [
			'inputCharset' => '1',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'bgUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'version' => 'mobile1.0',
			'language' => '1',
			'signType' => '4',
			'merchantAcctId' => $channel['appid'] . '01',
			'orderId' => TRADE_NO,
			'orderAmount' => strval($order['realmoney'] * 100),
			'orderTime' => date('YmdHis'),
			'productName' => $ordername,
			'payType' => $payType
		];
		if($aggregatePay) $params['aggregatePay'] = $aggregatePay;
		if($channel['own_channel'] == 1){
			$params['extDataType'] = 'NB2';
			$params['extDataContent'] = '<NB2>'.json_encode(['customAuthNetInfo'=>['own_channel'=>'1']]).'</NB2>';
		}
		$params['signMsg'] = $client->generateSign($params);
	
		$params['terminalIp'] = $clientip;
		$params['tdpformName'] = $conf['sitename'];
	    file_put_contents('kq.txt',$params);
		$html_text = '<form action="'.$apiurl.'" method="post" id="dopay">';
		foreach($params as $k => $v) {
			$v = htmlentities($v, ENT_QUOTES | ENT_HTML5);
			$html_text .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\" />\n";
		}
		$html_text .= '<input type="submit" value="正在跳转"></form><script>document.getElementById("dopay").submit();</script>';

		return ['type'=>'html','data'=>$html_text];
	}

	//获取H5支付链接
	static private function mobilepayurl($payType, $aggregatePay = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/PayApp.class.php");

		$apiurl = 'https://www.99bill.com/mobilegateway/recvMerchantInfoAction.htm';
		//$apiurl = 'https://sandbox.99bill.com/mobilegateway/recvMerchantInfoAction.htm';

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$params = [
			'inputCharset' => '1',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'bgUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'version' => 'mobile1.0',
			'language' => '1',
			'signType' => '4',
			'merchantAcctId' => $channel['appid'] . '01',
			'orderId' => TRADE_NO,
			'orderAmount' => strval($order['realmoney'] * 100),
			'orderTime' => date('YmdHis'),
			'productName' => $ordername,
			'payType' => $payType
		];
		if($aggregatePay) $params['aggregatePay'] = $aggregatePay;
		if($channel['own_channel'] == 1){
			$params['extDataType'] = 'NB2';
			$params['extDataContent'] = '<NB2>'.json_encode(['customAuthNetInfo'=>['own_channel'=>'1']]).'</NB2>';
		}
		$params['signMsg'] = $client->generateSign($params);
		$params['terminalIp'] = $clientip;
		$params['tdpformName'] = $conf['sitename'];
       
		$res = $client->curl($apiurl, http_build_query($params));
		 file_put_contents('kq3.txt',$res);
		if(strpos($res[1], '确认支付') !== false){
			$cookie = '';
			preg_match_all('/Set-Cookie: (.*?);/i', $res[0], $match);
			foreach($match[1] as $v){
				$cookie .= $v.'; ';
			}
			if(preg_match('/name=\"selectCheckBox\" value=\"(.*?)\"/i', $res[1], $match)){
				$type = $match[1];
				if($type == 'weiXinWapBox'){
					$url = 'https://www.99bill.com/mobilegateway/weixinWapPrePay.htm';
					$res = $client->curl($url, '', $cookie);
					$arr = json_decode($res[1], true);
					if(isset($arr['openlink'])){
						return $arr['openlink'];
					}else{
						echo $res[1];exit;
					}
				}elseif($type == 'zhiFuBaoBox'){
					$url = 'https://www.99bill.com/mobilegateway/alicsbPay.htm';
					$res = $client->curl($url, '', $cookie);
					$arr = json_decode($res[1], true);
					file_put_contents('kq7.txt',$cookie);
					if(isset($arr['qrcode'])){
						return $arr['qrcode'];
					}else{
						echo $res[1];exit;
					}
				}else{
					throw new Exception('未知的支付类型 '.$type);
				}
			}else{
				throw new Exception('支付页面解析失败');
			}
		}else{
			echo $res[1];exit;
		}
	}
	


	//当面付
	static private function qrcode(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/PayApp.class.php");

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$head = [
			'version' => '1.0.0',
			'messageType' => 'A7007',
			'memberCode' => $channel['appid'],
			'externalRefNumber' => TRADE_NO,
		];
		if(!empty($channel['appmchid'])){
			$head['memberCode'] = $channel['appmchid'];
			$head['vendorMemberCode'] =  $channel['appid'];
		}
		$body = [
			'merchantId' => $channel['merchant_id'],
			'terminalId' => $channel['terminal_id'],
			'cur' => 'CNY',
			'amount' => strval($order['realmoney'] * 100),
			'tr3Url' => $conf['localurl'] . 'pay/notifys/' . TRADE_NO . '/',
			'qrType' => '00',
			'terminalIp' => $clientip,
		];

		$result = $client->execute($head, $body);
		if($result['bizResponseCode'] == '0000'){
			\lib\Payment::updateOrderCombine(TRADE_NO);
			return $result['qrCode'];
		}else{
			throw new Exception('['.$result['bizResponseCode'].']'.$result['bizResponseMessage']);
		}
	}

	static public function alipay(){
		global $channel, $siteurl;

		if(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::qrcode();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
		}else{
			$code_url = $siteurl.'pay/alipaywap/'.TRADE_NO.'/';
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	static public function alipaywap(){
		try{
			$jump_url = self::mobilepayurl('27-3');
			
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}
		return ['type'=>'jump','url'=>$jump_url];
	}

	static public function wxpay(){
		global $channel, $siteurl, $device, $mdevice;
        
		if(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::qrcode();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}elseif($channel['appwxmp']>0){
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		}else{
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}

		if($mdevice == 'wechat' || checkwechat()){
			return ['type'=>'jump','url'=>$code_url];
		} elseif ($device == 'mobile' || checkmobile()) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	static public function wxwappay(){
		try{
			$jump_url = self::mobilepayurl('26-2');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}
		return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$jump_url];
	}
	
	
	

	static public function bank(){
		global $channel, $siteurl;

		if(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::qrcode();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}else{
			$code_url = $siteurl.'pay/submit/'.TRADE_NO.'/';
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//微信公众号
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];

		try{
			$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
			$openid = $tools->GetOpenid();
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;

		$aggregatePay = 'appId='.$wxinfo['appid'].',openId='.$openid.',limitPay=0';
		return self::mobilepay('26-1', $aggregatePay);
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
		require(PAY_ROOT."inc/PayApp.class.php");
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		
		$apiurl = 'https://www.99bill.com/mobilegateway/miniProgramPay.htm';
		$params = [
			'inputCharset' => '1',
			'bgUrl' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
			'version' => 'mobile1.0',
			'language' => '1',
			'signType' => '4',
			'merchantAcctId' => $channel['appid'] . '01',
			'orderId' => TRADE_NO,
			'orderAmount' => strval($order['realmoney'] * 100),
			'orderTime' => date('YmdHis'),
			'productName' => $ordername,
			'aggregatePay' => 'appId='.$wxinfo['appid'].',openId='.$openid.',limitPay=0',
			'payType' => '26-3'
		];
		$params['signMsg'] = $client->generateSign($params);
		$params['terminalIp'] = $clientip;
		$params['tdpformName'] = $conf['sitename'];

		$response = get_curl($apiurl, http_build_query($params));
		$result = json_decode($response, true);
		if(isset($result['responseCode']) && $result['responseCode']=='00'){
			exit(json_encode(['code'=>0, 'data'=>$result['payInfo']]));
		}elseif(isset($result['ResponseMsg'])){
			exit('{"code":-1,"msg":"'.$result['ResponseMsg'].'"}');
		}else{
			exit('{"code":-1,"msg":"返回内容解析失败"}');
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require(PAY_ROOT."inc/PayApp.class.php");
		
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifyNotify($_GET);

		if($verify_result) {//验证成功
			if ($_GET['payResult'] == '10') {
				if($_GET['orderId'] == TRADE_NO){
					processNotify($order, $_GET['dealId']);
				}
			}
			$redirecturl = $siteurl.'pay/return/'.TRADE_NO.'/';
			return ['type'=>'html','data'=>'<result>1</result><redirecturl>'.$redirecturl.'</redirecturl>'];
		}
		else {
			return ['type'=>'html','data'=>'<result>0</result>'];
		}
	}
	
	
	//飞快付回调
	static public function fkfnotify(){
	    global $channel, $order;
        $json = file_get_contents('php://input');
        $jsons = $_REQUEST;
        // file_put_contents('fkotify.txt',$json,FILE_APPEND);
        // file_put_contents('fknotifys.txt',$jsons,FILE_APPEND);
		require(PAY_ROOT."inc/PayApp.class.php");
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
// 		file_put_contents('fkfnotify.txt',$json);
		//var_dump($order);
		if($_POST['processFlag']=='0' && $_POST['responseCode']=='00'){
		    processNotify($order, $_POST['RRN']);
		}
// 		$redirecturl = $siteurl.'pay/return/'.TRADE_NO.'/';
		return ['type'=>'html','data'=>'0'];
	}

	//当面付异步回调
	static public function notifys(){
		global $channel, $order;

		require(PAY_ROOT."inc/PayApp.class.php");
		
		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		try{
			$response = $client->notifyProcess($result);
		}catch(Exception $ex){
			return ['type'=>'html','data'=>$ex->getMessage()];
		}

		if($result['body']['orderStatus'] == 'S'){
			if($result['head']['externalRefNumber'] == TRADE_NO){
				processNotify($order, $result['body']['idOrderCtrl'], $result['body']['thirdPartyBuyerId']);
			}
		}

		return ['type'=>'html','data'=>$response];
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//查单
	static public function query(){
		global $channel, $order;

		require(PAY_ROOT."inc/PayApp.class.php");

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		if($order['combine'] == 1){ //当面付
			$head = [
				'version' => '1.0.0',
				'messageType' => 'A7006',
				'memberCode' => $channel['appid'],
				'externalRefNumber' => 'QUE'.$order['trade_no'],
			];
			if(!empty($channel['appmchid'])){
				$head['memberCode'] = $channel['appmchid'];
				$head['vendorMemberCode'] =  $channel['appid'];
			}
			$body = [
				'merchantId' => $channel['merchant_id'],
				'terminalId' => $channel['terminal_id'],
				'idOrderCtrl' => $order['api_trade_no'],
			];
		}else{
			$head = [
				'version' => '1.0.0',
				'messageType' => 'F0003',
				'memberCode' => $channel['appid'],
				'externalRefNumber' => 'QUE'.$order['trade_no'],
			];
			if(!empty($channel['appmchid'])){
				$head['memberCode'] = $channel['appmchid'];
				$head['vendorMemberCode'] =  $channel['appid'];
			}
			$body = [
				'merchantAcctId' => $channel['appid'] . '01',
				'queryType' => '0',
				'queryMode' => '1',
				'orderId' => $order['trade_no'],
			];
		}

		try{
			$result = $client->execute($head, $body);
			print_r($result);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>$ex->getMessage()];
		}
	}
	
	static public function query_fkf(){
	    global $channel, $order;
	    require(PAY_ROOT."inc/PayApp.class.php");

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$apiurl = "https://hat.99bill.com/polymerizes-order/order/query";
		$params = [
	       "memberCode"=>$channel['appid'],
	       "requestTime"=>date('YmdHis'),
	       "merchantId"=>$channel['fkf_merchant_id'],
	       "terminalId"=>$channel['fkf_terminal_id'],
	       "origTxnType"=>"PUR",
	       "externalTraceNo"=>$order['trade_no'],
	       ];
	   $params['secretInfo'] = $client->generateFkfSign($params,$channel['fkf_key']);
	   	try{
			$result = $client->kq_curl($apiurl, json_encode($params));
			print_r($result);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>$ex->getMessage()];
		}
	}

	//退款
	static public function refund($order){
		global $channel, $conf;
		if(empty($order))exit();

		require(PAY_ROOT."inc/PayApp.class.php");

		$client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$head = [
			'version' => '1.0.0',
			'messageType' => 'F0001',
			'memberCode' => $channel['appid'],
			'externalRefNumber' => $order['refund_no'],
		];
		$body = [
			'merchantAcctId' => $channel['appid'],
			'txnType' => 'bill_drawback_api_1',
			'amount' => strval($order['refundmoney'] * 100),
			'entryTime' => substr($order['trade_no'], 0, 14),
			'orgOrderId' => $order['trade_no'],
		];

		try{
			$result = $client->execute($head, $body);
			if($result['bizResponseCode'] == '0000'){
				return ['code'=>0];
			}else{
				return ['code'=>-1, 'msg'=>'['.$result['bizResponseCode'].']'.$result['bizResponseMessage']];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}
	
	
	//飞快付退款
	static function refund_fkf($order){
	   global $channel, $conf;
	   if(empty($order))exit(); 
	   require(PAY_ROOT."inc/PayApp.class.php");
	   $client = new \kuaiqian\PayApp($channel['appid'], $channel['appkey'], $channel['appsecret']);
	   $apiurl = "https://hat.99bill.com/polymerizes-order/order/refund";
	   $params = [
	       "memberCode"=>$channel['appid'],
	       "requestTime"=>date('YmdHis'),
	       "merchantName"=>"潮礼科技",
	       "merchantId"=>$channel['fkf_merchant_id'],
	       "terminalId"=>$channel['fkf_terminal_id'],
	       "origIdBiz"=>$order['api_trade_no'],
	       "amt"=>$order['refundmoney'],
	       "termTxnTime"=>date('Y-m-d H:i:s'),
	       "externalTraceNo"=>$order['trade_no']
	       ];
	   $params['secretInfo'] = $client->generateFkfSign($params,$channel['fkf_key']);
	   try {
	        $result = $client->kq_curl($apiurl,json_encode($params));
	        if($result['responseCode']=='00'){
	            if($result['txnFlg']=='S'){
	               	return ['code'=>0];
	            }else{
	                return ['code'=>-1, 'msg'=>'['.$result['responseCode'].']'.$result['responseMessage']];
	            }
	        }else{
	           return ['code'=>-1, 'msg'=>'['.$result['responseCode'].']'.$result['responseMessage']]; 
	        }
	   } catch (Exception $e) {
	       return ['code'=>-1, 'msg'=>$ex->getMessage()];
	   }
	  
	   
	   
	}








}
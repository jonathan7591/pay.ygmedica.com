<?php

//http://39.106.84.215:8181/docs/saas/saas-1fdsisa23tp3f
class haipay_plugin
{
	static public $info = [
		'name'        => 'haipay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '海科聚合支付', //支付插件显示名称
		'author'      => '海科融通', //支付插件作者
		'link'        => 'https://www.hkrt.cn/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'accessid' => [
				'name' => 'accessid',
				'type' => 'input',
				'note' => '',
			],
			'accesskey' => [
				'name' => '接入秘钥',
				'type' => 'input',
				'note' => '',
			],
			'agent_no' => [
				'name' => '服务商编号',
				'type' => 'input',
				'note' => '',
			],
			'merch_no' => [
				'name' => '商户编号',
				'type' => 'input',
				'note' => '',
			],
			'pn' => [
				'name' => '终端号',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && $channel['appwxa']>0){
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
			if($mdevice=='wechat' && $channel['appwxmp']>0){
				return self::wxjspay();
			}elseif($device=='mobile' && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//预下单
	static private function prepay($pay_type, $pay_mode, $sub_openid = null, $sub_appid = null){
		global $siteurl, $conf, $channel, $order, $ordername, $clientip;
       
		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$params = [
			'agent_no' => $channel['agent_no'],
			'merch_no' => $channel['merch_no'],
			'pay_type' => $pay_type,
			'pay_mode' => $pay_mode,
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'pn' => $channel['pn'],
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		 
		if($sub_openid) $params['openid'] = $sub_openid;
		if($sub_appid) $params['appid'] = $sub_appid;
		if($pay_type == 'WX'){
			$params['extend_params'] = ['body'=>$ordername];
		}elseif($pay_type == 'ALI'){
			$params['extend_params'] = ['subject'=>$ordername];
		}
        log_debug(json_encode($params,JSON_UNESCAPED_SLASHES),'haipay');
		$client = new HaiPayClient($channel['accessid'], $channel['accesskey'],false);
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->payRequest('/api/v2/pay/pre-pay', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['trade_no']);
			return $result;
		});
	}

	//付款码支付
	static private function micropay(){
		global $siteurl, $conf, $channel, $order, $ordername, $clientip;

		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$params = [
			'accessid' => $channel['accessid'],
			'merch_no' => $channel['merch_no'],
			'auth_code' => $order['auth_code'],
			'out_trade_no' => TRADE_NO,
			'total_amount' => $order['realmoney'],
			'pn' => $channel['pn'],
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		if($order['typename'] == 'wxpay'){
			$params['extend_params'] = ['body'=>$ordername];
		}elseif($order['typename'] == 'alipay'){
			$params['extend_params'] = ['subject'=>$ordername];
		}
		
		$client = new HaiPayClient($channel['accessid'], $channel['accesskey'],false);
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			$result = $client->payRequest('/api/v2/pay/passive-pay', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['trade_no']);
			return $result;
		});
	}

	//支付宝扫码支付
	static public function alipay(){
		try{
			$result = self::prepay('ALI', 'NATIVE');
			$code_url = $result['ali_qr_code'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//支付宝JS支付
	static public function alipayjs(){
		global $conf;
		if(!isset($_GET['userid'])){
			$redirect_uri = '/pay/alipayjs/'.TRADE_NO.'/';
			return ['type'=>'jump','url'=>'/user/oauth.php?state='.urlencode(authcode($redirect_uri, 'ENCODE', SYS_KEY))];
		}

		$blocks = checkBlockUser($_GET['userid'], TRADE_NO);
		if($blocks) return $blocks;

		try{
			$result = self::prepay('ALI', 'JSAPI', $_GET['userid']);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'alipay_jspay','data'=>['alipay_trade_no'=>$result['ali_trade_no'], 'redirect_url'=>$redirect_url]];
	}

	//微信扫码支付
	static public function wxpay(){
		global $siteurl;
		/*try{
			$result = self::prepay('WX', 'NATIVE');
			$code_url = $result['code_url'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}*/
		$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';

		if(checkwechat()){
			return ['type'=>'jump','url'=>$code_url];
		} elseif (checkmobile()) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信公众号支付
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

		try{
			$result = self::prepay('WX','JSAPI',$openid,$wxinfo['appid']);
			$pay_info = $result['wc_pay_data'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$pay_info, 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');

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

		try{
			$result = self::prepay('WX','JSAPI',$openid,$wxinfo['appid']);
			$pay_info = $result['wc_pay_data'];
		}catch(Exception $ex){
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败 '.$ex->getMessage()]));
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($pay_info, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
		try{
			$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$result = self::prepay('UNIONQR', 'NATIVE');
			$code_url = $result['uniqr_qr_code'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$arr = json_decode($json,true);
		if(!$arr) return ['type'=>'html','data'=>'No data'];

		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$client = new HaiPayClient($channel['accessid'], $channel['accesskey'],false);

		if($client->verify($arr)){
			if($arr['trade_status'] == '1'){
				$out_trade_no = $arr['out_trade_no'];
				$api_trade_no = $arr['trade_no'];
				$bill_trade_no = $arr['bank_trade_no'];
				$money = $arr['order_amount'];
				$buyer = $arr['openid'];
	
				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $api_trade_no, $buyer, $bill_trade_no);
				}
			}
			return ['type'=>'json','data'=>['return_code'=>'SUCCESS']];
		}else{
			return ['type'=>'json','data'=>['return_code'=>'FAIL', 'return_msg'=>'SIGN ERROR']];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel, $clientip;
		if(empty($order))exit();

		require_once PAY_ROOT.'inc/HaiPayClient.php';

		$params = [
			'agent_no' => $channel['agent_no'],
			'merch_no' => $channel['merch_no'],
			'trade_no' => $order['api_trade_no'],
			'out_refund_no' => $order['refund_no'],
			'refund_amount' => $order['refundmoney'],
			'pn' => $channel['pn'],
		];
		
		try{
			$client = new HaiPayClient($channel['accessid'], $channel['accesskey'],false);
			$result = $client->payRequest('/api/v2/pay/refund', $params);
			return ['code'=>0, 'trade_no'=>$result['refund_no'], 'refund_fee'=>$result['refund_amount']];
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

}
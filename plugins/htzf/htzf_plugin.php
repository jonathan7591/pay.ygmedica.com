<?php

class htzf_plugin
{
	static public $info = [
		'name'        => 'htzf', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '昊天支付', //支付插件显示名称
		'author'      => '昊天支付', //支付插件作者
		'link'        => 'http://doc.jiangyinhaotian.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => 'MD5密钥',
				'type' => 'input',
				'note' => '',
			],
			'terminalType' => [
				'name' => '交易类型',
				'type' => 'select',
				'options' => [1=>'门店模式',2=>'终端模式'],
			],
			'shopId' => [
				'name' => '门店Id',
				'type' => 'input',
				'note' => '',
			],
			'sn' => [
				'name' => '终端sn',
				'type' => 'input',
				'note' => '',
			],
			'nonceStr' => [
				'name' => '随机字符串',
				'type' => 'input',
				'note' => '',
			],
		],
		'select_wxpay' => [
			'1' => '扫码支付',
			'2' => 'H5支付',
			'3' => '公众号支付',
		],
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => 'H5支付',
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	const API_URL = 'http://apis.jiangyinhaotian.com/open/Pay/unifiedOrder';

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice;

		if($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			return self::wxpay();
		}
	}

	static private function make_sign($param, $name, $key){
		ksort($param);
		$signstr = '';
	
		foreach($param as $k => $v){
			if(in_array($k, $name) && $v!==null && $v!==''){
			  if ($k == 'tradeInfo') {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE); // 避免转义中文字符
                }
				$signstr .= $k.'='.$v.'&';
			}
		}
		$signstr .= 'key='.$key;
 		
		$sign = strtoupper(md5($signstr));
		return $sign;
	}

	//通用创建订单
	static private function addOrder($path){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
        $headers = [
           'Content-Type' => 'application/json'
        ];
		$param = [
			'developerId' => $channel['appid'], //appid
			'terminalType' => $channel['terminalType'],
			'payAmount' => $order['realmoney']*100,
			'payType' =>$path,
			'outTradeId' => TRADE_NO,
			'body' => $ordername,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'timestamp' => time(),
			'nonceStr'=>$channel['nonceStr'],
			'clientIP' => $clientip
		];
		//判断交易类型
		if($channel['terminalType']==1){
		    $param['shopId'] = $channel['shopId'];
		}else{
		     $param['sn'] = $channel['sn'];
		}
		
		$sign_param = ['developerId','terminalType','payAmount','payType','body','outTradeId','timestamp','notifyUrl','nonceStr','clientIP','is_raw','sub_appid','sub_openid','buyer_id','shopId','sn','tradeInfo'];
		$param['sign'] = self::make_sign($param, $sign_param, $channel['appkey']);
		log_debug(json_encode($param),'htzf');
        // file_put_contents('qq.txt',json_encode($param).PHP_EOL,FILE_APPEND);
		$response = get_curl(self::API_URL, json_encode($param),0,0,0,0,0,$headers);
		$result = json_decode($response, true);
       
       
		if(isset($result["errCode"]) && $result["errCode"]==0){
			return $result['payUrl'];
		}else{
			throw new Exception($result["errMsg"]?$result["errMsg"]:'返回数据解析失败');
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		global $channel, $device;
		if(in_array('2',$channel['apptype']) && (checkmobile() || $device=='mobile')){
			try{
				$result = self::addOrder('/api/alipay/h5');
				$h5_url = $result['h5_url'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$h5_url];
		}else{
			try{
				$code_img_url = self::addOrder('ali_pay');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'支付宝下单失败！'.$ex->getMessage()];
			}
// 			$code_url = 'data:image/png;base64,'.base64_encode(get_curl($code_img_url));
           
			return ['type'=>'jump','url'=>$code_img_url];
		}
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $device, $mdevice;
		if(in_array('3',$channel['apptype']) && (checkwechat() || $mdevice=='wechat')){
			try{
				$result = self::addOrder('wx_pay');
				$jump_url = $result;
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$jump_url];
		}
		elseif(in_array('2',$channel['apptype']) && (checkmobile() || $device=='mobile')){
			try{
				$jump_url = self::addOrder('/api/wxpay/jump_h5');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$jump_url];
		}
		elseif(in_array('1',$channel['apptype'])){
			try{
				$result = self::addOrder('wx_pay');
				$code_url = $result;
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}
		elseif(in_array('3',$channel['apptype'])){
			try{
				$result = self::addOrder('wx_pay');
				$code_url = $result['payUrl'];
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}
		if (checkmobile()) {
			return ['type'=>'jump','url'=>$code_url];
		} else {
			return ['type'=>'jump','url'=>$code_url];
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
			$extend = ['sub_appid' => $wxinfo['appid'], 'user_id' => $openid];
			$result = self::qrcode('WECHAT', '51', $extend);
			$pay_info = ['appId'=>$result['app_id'],'timeStamp'=>$result['time_stamp'],'nonceStr'=>$result['nonce_str'],'package'=>$result['package'],'paySign'=>$result['pay_sign'],'signType'=>$result['sign_type']];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>json_encode($pay_info), 'redirect_url'=>$redirect_url]];
	}
	//异步回调
	static public function notify(){
		global $channel, $order;
		$input = file_get_contents("php://input");
        // file_put_contents('htzf.txt',$input.PHP_EOL,FILE_APPEND);
		$arr = json_decode($input,true);
		$sign_param = ['developerId','terminalType','payAmount','payType','body','outTradeId','timestamp','notifyUrl','nonceStr','clientIP','is_raw','sub_appid','sub_openid','buyer_id','shopId','sn','tradeInfo','timeStamp'];
		$sign = self::make_sign($arr, $sign_param, $channel['appkey']);

		if($sign==$arr["sign"]){
		    $tradeInfo = $arr["tradeInfo"];
			if($tradeInfo['payStatus'] == 'SUCCESS'){
				$out_trade_no = $tradeInfo['outTradeId'];
				$trade_no = $tradeInfo['T_outTradeId'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $trade_no, $tradeInfo['transactionId']);
				}
				return ['type'=>'json','data'=>array('result'=>'SUCCESS')];
			}else{
				return  ['type'=>'json','data'=>array('result'=>'fail','msg'=>'获取支付状态失败')];
			}
		}else{
			return  ['type'=>'json','data'=>array('result'=>'fail','msg'=>'验签失败','sign'=>$sign)];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//支付成功页面
	static public function ok(){
		return ['type'=>'page','page'=>'ok'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();
        $headers = [
           'Content-Type' => 'application/json'
        ];
// 		if($order['type'] == 2){
// 			$path = '/api/wxpay/refund_order';
// 		}else{
// 			$path = '/api/alipay/refund_order';
// 		}
		$param = [
			'developerId' => $channel['appid'], //appid
			'terminalType' => $channel['terminalType'],
		    'outTradeId'=>$order['trade_no'],
		    'refundFee'=>$order['refundmoney']*100,
		    'timestamp'=>time(),
		    'nonceStr'=>'apTnFFv8eZJKOhsS2GKZKo4KRzw9CNju',
		    'refundNo'=>$order['refund_no'],
		];
		//判断交易类型
		if($channel['terminalType']==1){
		    $param['shopId'] = $channel['shopId'];
		}else{
		     $param['sn'] = $channel['sn'];
		}
		
		$sign_param = ['developerId','terminalType','payAmount','payType','body','outTradeId','timestamp','notifyUrl','nonceStr','clientIP','is_raw','sub_appid','sub_openid','buyer_id','shopId','sn','tradeInfo','timeStamp','refundNo','refundFee'];
		$param['sign'] = self::make_sign($param, $sign_param, $channel['appkey']);

		$response = get_curl('http://apis.jiangyinhaotian.com/open/Pay/refund', json_encode($param),0,0,0,0,0,$headers);
		$result = json_decode($response, true);
        // file_put_contents('rf.txt',$response);
		if(isset($result["errCode"]) && $result["errCode"]==0){
		    $refundInfo = $result['refundInfo'];
		    if($refundInfo['refundStatus']==2){
		       	return ['code'=>0, 'trade_no'=>$order['data']['out_trade_no'], 'refund_fee'=>$order['refundmoney']]; 
		    }else{
		        return ['code'=>-1, 'msg'=>$result["refundMessage"]?$result["refundMessage"]:'返回数据解析失败'];
		    }
		
		}else{
			return ['code'=>-1, 'msg'=>$result["errMsg"]?$result["errMsg"]:'返回数据解析失败'];
		}
	}
}
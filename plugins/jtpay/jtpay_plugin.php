<?php

class jtpay_plugin
{
	static public $info = [
		'name'        => 'jtpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '吉特支付', //支付插件显示名称
		'author'      => '吉特支付', //支付插件作者
		'link'        => 'http://jitepay.com/', //支付插件作者链接
		'types'       => ['alipay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '直连商户商户号',
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

	const API_URL = 'https://api.jitepay.com/v3/';

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


	//通用创建订单
	static private function addOrder($path){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
		$headers = [
            'Content-Type: application/json; charset=UTF-8', // 明确声明 JSON 类型
            'Accept: application/json'
        ];
		$param = [
		    'channel' =>'alipay_qr',
			'appid' => $channel['appid'], //appid
			'mchid' => $channel['appmchid'],
			'description'=>$ordername,
			'amount' => array('total'=>$order['realmoney'],'currency'=>'CNY'),
			'outTradeNo' => TRADE_NO,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		    'orderType'=>'ON_LINE',
		    'sceneInfo'=>array('payerClientIp'=>$clientip),
		    'payer'=>array('openid'=>time().rand(1000,9999))
		];
		log_debug(json_encode($param,JSON_UNESCAPED_SLASHES),'jt');
        // file_put_contents('qq.txt',json_encode($param).PHP_EOL,FILE_APPEND);
		$response = get_curl(self::API_URL.'pay/transactions/hkrt/native', json_encode($param,JSON_UNESCAPED_SLASHES),0,0,0,0,0,$headers);
		$result = json_decode($response, true);
       
        log_debug("返回数据：".$response,'jt');
		if(isset($result["code"])){
		    throw new Exception($result["msg"]?$result["msg"]:'返回数据解析失败');
		}else{
			return $result['qr_code'];
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
// /			$code_url = 'data:image/png;base64,'.base64_encode(get_curl($code_img_url));
           return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_img_url];
// 			return ['type'=>'jump','url'=>$code_img_url];
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
		header('Content-Type: application/json');
		$input = file_get_contents("php://input");
         log_debug("异步返回数据：".$input,'jt');
		$arr = json_decode($input,true);
// 		$tradeInfo = $arr["tradeInfo"];
			if($arr['trade_state'] == 'SUCCESS'){
				$out_trade_no = $arr['out_trade_no'];
				$trade_no = $arr['trade_no'];

				if ($out_trade_no == TRADE_NO) {
					processNotify($order, $trade_no,$arr['in_trade_no']);
				}
				echo(json_encode(array('resultCode'=>'SUCCESS','resultMsg'=>"回调成功")));
				// return ['type'=>'json','data'=>array('resultCode'=>'SUCCESS','resultMsg'=>"回调成功")];
			}else{
			    echo(json_encode(array('resultCode'=>'fail','resultMsg'=>"获取支付状态失败")));
				// return  ['type'=>'json','data'=>array('resultCode'=>'fail','resultMsg'=>'获取支付状态失败')];
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
		global $channel,$conf;;
		if(empty($order))exit();
       $headers = [
            'Content-Type: application/json; charset=UTF-8', // 明确声明 JSON 类型
            'Accept: application/json'
        ];
		$param = [
		    'outTradeNo'=>$order['trade_no'],
		    'amount'=>array("refund"=>$order['refundmoney'],"total"=>$order['refundmoney'],"currency"=>"CNY"),
		    'notifyUrl' => $conf['localurl'].'pay/refundnotify/'.TRADE_NO.'/',
		    'outRefundNo'=>$order['refund_no'],
		];
		
		$response = get_curl(self::API_URL.'refund/domestic/hkrt/refunds', json_encode($param,JSON_UNESCAPED_SLASHES),0,0,0,0,0,$headers);
		log_debug("退款请求数据：".json_encode($param,JSON_UNESCAPED_SLASHES),'jt');
		$result = json_decode($response, true);
		log_debug("退款返回数据：".$response,'jt');
        // file_put_contents('rf.txt',$response);
        if (isset($result['code'])) {
            // code...
            return ['code'=>-1,  'msg'=>$result['msg']?$result['msg']:'返回数据解密失败']; 
        }
		return ['code'=>0, 'trade_no'=>$order['data']['out_trade_no'], 'refund_fee'=>$order['refundmoney']]; 
		    
	}
	
		//退款异步回调
	static public function refundnotify(){
		global $channel, $order;
	    $input = file_get_contents("php://input");
	    log_debug("退款异步返回数据：".$input,'jt');
			if($_POST['trade_state'] == 'SUCCESS'){
				return ['type'=>'html','data'=>'200'];
			}else{
				return ['type'=>'html','data'=>'status_error'];
			}
	
	}
}
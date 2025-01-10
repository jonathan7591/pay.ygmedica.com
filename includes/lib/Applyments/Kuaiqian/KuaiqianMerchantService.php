<?php

namespace lib\Applyments\Kuaiqian;

require_once PLUGIN_ROOT.'kuaiqian/inc/PayApp.class.php';

/**
 * 快钱商户进件服务
 * 网页版进件：https://web.99bill.com/webapp/offline-product-feed-new/index.html
 */
class KuaiqianMerchantService extends \kuaiqian\PayApp
{
    public function __construct($memberCode, $merchat_key_pwd, $ssl_cert_pwd)
    {
        parent::__construct($memberCode, $merchat_key_pwd, $ssl_cert_pwd);
    }

    private function requestApi($messageType, $params, $out_biz_no = null){
        if(!$out_biz_no) $out_biz_no = date("YmdHis").rand(11111,99999);
        $head = [
            'version' => '1.0.0',
			'messageType' => $messageType,
			'memberCode' => $this->member_code,
			'externalRefNumber' => $out_biz_no,
        ];
        $this->gateway_url = 'https://umgw.99bill.com/umgw-boss/common/distribute.html';
        $result = $this->execute($head, $params);
        if($result['bizResponseCode'] == '0000'){
            return $result;
        }else{
            throw new \Exception('['.$result['bizResponseCode'].']'.$result['bizResponseMessage']);
        }
    }

    /**
     * 新商户进件
     * @param $params 进件参数
     * @return mixed
     */
    public function submitNew($out_biz_no, $params)
    {
        return $this->requestApi('BS001', $params, $out_biz_no);
    }

    /**
     * 订单审批状态查询
     * @param $orderId 订单编号
     * @return mixed
     */
    public function query($orderId)
    {
        $params = [
            'orderId' => $orderId
        ];
        return $this->requestApi('BS002', $params);
    }

    /**
     * 合同查询
     * @param $orderId 订单编号
     * @return mixed
     */
    public function queryContract($orderId)
    {
        $params = [
            'orderId' => $orderId
        ];
        return $this->requestApi('BS003', $params);
    }

    /**
     * 合同签约
     * @param $orderId 订单编号
     * @return mixed
     */
    public function signContract($orderId)
    {
        $params = [
            'orderId' => $orderId
        ];
        return $this->requestApi('BS004', $params);
    }

    /**
     * 老商户变更进件
     * @param $params 参数
     * @return mixed
     */
    public function modify($params)
    {
        return $this->requestApi('BS005', $params);
    }

    /**
     * 图片上传
     * @param $file_path 文件路径
     * @param $file_name 文件名
     * @return string
     */
    public function uploadImage($file_path, $file_name)
    {
        $params = [
            'fileBuffer' => base64_encode(file_get_contents($file_path)),
            'fileName' => $file_name
        ];
        $result = $this->requestApi('BS000', $params);
        return $result['fssId'];
    }
}
<?php

namespace lib\Applyments\WxpayEC;
use WeChatPay\V3\BaseService;

/**
 * 微信支付平台收付通进件
 * @see https://pay.weixin.qq.com/docs/partner/products/ecommerce/apilist.html
 */
class WxECApplymentService extends BaseService
{
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     * 提交申请单
     * @param $params 进件参数
     * @return mixed
     */
    public function submit($params)
    {
        $path = '/v3/ecommerce/applyments/';
        return $this->execute('POST', $path, $params, true);
    }

    /**
     * 查询申请状态
     * @param $applyment_id 申请单号
     * @param $out_request_no 业务申请编号
     * @return mixed
     */
    public function query($applyment_id = null, $out_request_no = null)
    {
        if($applyment_id){
            $path = '/v3/ecommerce/applyments/'.$applyment_id.'/';
        }else{
            $path = '/v3/ecommerce/applyments/out-request-no/'.$out_request_no.'/';
        }
        return $this->execute('GET', $path);
    }

    /**
     * 修改结算账户
     * @param $sub_mchid 子商户号
     * @param $params 请求参数
     * @return mixed
     */
    public function modifySettlement($sub_mchid, $params)
    {
        $path = '/v3/apply4sub/sub_merchants/'.$sub_mchid.'/modify-settlement';
        return $this->execute('POST', $path, $params, true);
    }

    /**
     * 查询结算账户
     * @param $sub_mchid 子商户号
     * @return mixed
     */
    public function querySettlement($sub_mchid)
    {
        $path = '/v3/apply4sub/sub_merchants/'.$sub_mchid.'/settlement';
        return $this->execute('GET', $path);
    }

    /**
     * 查询结算账户修改申请状态
     * @param $sub_mchid 子商户号
     * @return mixed
     */
    public function querySettlementApplication($sub_mchid, $application_no)
    {
        $path = '/v3/apply4sub/sub_merchants/'.$sub_mchid.'/application/'.$application_no;
        return $this->execute('GET', $path);
    }

    /**
     * 图片上传
     * @param $file_path 文件路径
     * @param $file_name 文件名
     * @return string
     */
    public function uploadImage($file_path, $file_name)
    {
        $path = '/v3/merchant/media/upload';
        $result = $this->upload($path, $file_path, $file_name);
        return $result['media_id'];
    }

    /**
     * 查询二级商户账户实时余额
     * @param $sub_mchid 子商户号
     * @return mixed
     */
    public function queryBalance($sub_mchid)
    {
        $path = '/v3/ecommerce/fund/balance/'.$sub_mchid;
        return $this->execute('GET', $path);
    }

    /**
     * 查询电商平台账户实时余额
     * @return mixed
     */
    public function queryPlatBalance($account_type)
    {
        $path = '/v3/merchant/fund/balance/'.$account_type;
        return $this->execute('GET', $path);
    }

    /**
     * 二级商户预约提现
     * @param $params 请求参数
     * @return mixed
     */
    public function withdraw($params)
    {
        $path = '/v3/ecommerce/fund/withdraw';
        return $this->execute('POST', $path, $params);
    }

    /**
     * 二级商户查询预约提现状态
     * @param $withdraw_id 微信支付预约提现单号
     * @param $out_request_no 商户预约提现单号
     * @return mixed
     */
    public function queryWithdraw($sub_mchid, $withdraw_id = null, $out_request_no = null)
    {
        $params = [
            'sub_mchid' => $sub_mchid
        ];
        if($withdraw_id){
            $path = '/v3/ecommerce/fund/withdraw/'.$withdraw_id.'/';
        }else{
            $path = '/v3/ecommerce/fund/withdraw/out-request-no/'.$out_request_no.'/';
        }
        return $this->execute('GET', $path, $params);
    }

    /**
     * 提交注销申请单
     */
    public function cancelApply($params)
    {
        $path = '/v3/ecommerce/account/cancel-applications';
        return $this->execute('POST', $path, $params);
    }

    /**
     * 查询注销单状态
     * @return mixed
     */
    public function queryCancelApply($out_apply_no)
    {
        $path = '/v3/ecommerce/account/cancel-applications/out-apply-no/'.$out_apply_no;
        return $this->execute('GET', $path);
    }

    /**
     * 注销申请图片上传
     * @param $file_path 文件路径
     * @param $file_name 文件名
     * @return string
     */
    public function uploadCancelApplyImage($file_path, $file_name)
    {
        $url = self::$GATEWAY . '/v3/ecommerce/account/cancel-applications/media';
        if (!file_exists($file_path)) {
            throw new \Exception("文件不存在");
        }
        $meta = [
            'file_name' => $file_name,
            'file_digest' => hash_file("sha256", $file_path)
        ];
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $mime_type = self::mime_content_type($file_ext);
        $meta_json = json_encode($meta);
        $params = [
            'file' => new \CURLFile($file_path, $mime_type, $file_name),
            'meta' => $meta_json
        ];
        
        $authorization = $this->getAuthorization('POST', $url, $meta_json);
        $header[] = 'Accept: application/json';
        $header[] = 'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization;
        [$httpCode, $header, $response] = $this->curl('POST', $url, $header, $params);
        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode <= 299) {
            if (!$this->checkResponseSign($response, $header)) {
                throw new \Exception("微信支付返回数据验签失败");
            }
            return $result['media_id'];
        }
        throw new \WeChatPay\V3\WeChatPayException($result, $httpCode);
    }

    private static function mime_content_type($ext)
    {
        $mime_types = [
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
        ];
        return $mime_types[$ext];
    }

    /**
     * 提交已注销商户号可用余额提现申请单
     */
    public function riskWithdrawApply($params)
    {
        $path = '/v3/mch_operate/risk/withdrawl-apply';
        return $this->execute('POST', $path, $params);
    }

    /**
     * 查询提现申请单状态
     * @param $withdraw_id 微信支付预约提现单号
     * @param $out_request_no 商户预约提现单号
     * @return mixed
     */
    public function queryRiskWithdrawApply($applyment_id = null, $out_request_no = null)
    {
        if($applyment_id){
            $path = '/v3/mch_operate/risk/withdrawl-apply/applyment-id/'.$applyment_id.'/';
        }else{
            $path = '/v3/mch_operate/risk/withdrawl-apply/out-request-no/'.$out_request_no.'/';
        }
        return $this->execute('GET', $path);
    }
}
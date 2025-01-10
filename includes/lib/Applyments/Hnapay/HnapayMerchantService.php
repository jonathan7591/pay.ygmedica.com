<?php

namespace lib\Applyments\Hnapay;

require_once PLUGIN_ROOT.'xsy/lib/PayClient.php';

/**
 * 新生易合作方商户入网接口
 * @see https://www.yuque.com/zhaohanying/egbhoy/cn6hrmyu7gkntvnw
 */
class HnapayMerchantService extends \xsy\PayClient
{
    protected $gateway_url = 'https://gateway-hpx.hnapay.com/merchant';
    
    public function __construct($appid, $public_key, $private_key, $isTest = false)
    {
        parent::__construct($appid, $public_key, $private_key, $isTest);
        if($isTest) $this->gateway_url = 'https://gateway-hpxtest1.hnapay.com/merchant';
    }

    /**
     * 商户进件
     * @param $params 进件参数
     * @return mixed
     */
    public function apply($params)
    {
        return $this->request('/merchant/apply', $params);
    }

    /**
     * 商户补件
     * @param $params 进件参数
     * @return mixed
     */
    public function patch($params)
    {
        return $this->request('/merchant/patch', $params);
    }

    /**
     * 商户信息修改
     * @param $params 进件参数
     * @return mixed
     */
    public function modify($params)
    {
        return $this->request('/merchant/modify', $params);
    }

    /**
     * 商户费率设置
     * @param $params 设置参数
     * @return mixed
     */
    public function setRate($params)
    {
        return $this->request('/merchant/setRate', $params);
    }

    /**
     * 商户进件结果查询
     * @param $requestId 订单编号
     * @return mixed
     */
    public function queryApply($requestId)
    {
        $params = [
            'requestId' => $requestId
        ];
        return $this->request('/merchant/queryApplyResult', $params);
    }

    /**
     * 商户修改结果查询
     * @param $requestId 订单编号
     * @return mixed
     */
    public function queryModify($requestId)
    {
        $params = [
            'requestId' => $requestId
        ];
        return $this->request('/merchant/queryModifyResult', $params);
    }

    /**
     * 商户信息查询
     * @param $merchantNo 商户编号
     * @return mixed
     */
    public function queryInfo($merchantNo)
    {
        $params = [
            'merchantNo' => $merchantNo
        ];
        return $this->request('/merchant/queryInfo', $params);
    }

    /**
     * 微信子商户参数配置
     * @param $params 设置参数
     * @return mixed
     */
    public function wxConfigSet($params)
    {
        return $this->request('/merchant/wxConfigSet', $params);
    }

    /**
     * 微信子商户参数配置查询
     * @param $merchantNo 商户编号
     * @return mixed
     */
    public function wxConfigQuery($merchantNo)
    {
        $params = [
            'merchantNo' => $merchantNo
        ];
        return $this->request('/merchant/wxConfigQuery', $params);
    }

    /**
     * 图片上传
     * @param $picture_type 图片类型
     * @param $file_path 文件路径
     * @param $file_name 文件名
     * @return string
     */
    public function uploadImage($picture_type, $file_path, $file_name)
    {
        $params = [
            'orgNo' => $this->appid,
            'pictureType' => $picture_type,
            'file' => new \CURLFile($file_path, null, $file_name)
        ];
        $response = get_curl($this->gateway_url.'/merchant/uploadPicture', $params);
        $result = json_decode($response, true);

        if(isset($result['code']) && $result['code']=='0000'){
            return $result['respData']['pictureName'];
        }else{
            throw new \Exception($result['msg']?$result['msg']:'返回数据解析失败');
        }
    }
}
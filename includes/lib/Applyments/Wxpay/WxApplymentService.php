<?php

namespace lib\Applyments\Wxpay;
use WeChatPay\V3\BaseService;

/**
 * 微信支付特约商户进件
 * @see https://pay.weixin.qq.com/docs/partner/products/contracted-merchant-application/apilist.html
 */
class WxApplymentService extends BaseService
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
        $path = '/v3/applyment4sub/applyment/';
        return $this->execute('POST', $path, $params, true);
    }

    /**
     * 查询申请状态
     * @param $applyment_id 申请单号
     * @param $business_code 业务申请编号
     * @return mixed
     */
    public function query($applyment_id = null, $business_code = null)
    {
        if($applyment_id){
            $path = '/v3/applyment4sub/applyment/applyment_id/'.$applyment_id.'/';
        }else{
            $path = '/v3/applyment4sub/applyment/business_code/'.$business_code.'/';
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
     * 提交商户开户意愿确认申请单
     * @param $params 进件参数
     * @return mixed
     */
    public function confirmSubmit($params)
    {
        $path = '/v3/apply4subject/applyment/';
        return $this->execute('POST', $path, $params, true);
    }
    
    /**
     * 撤销商户开户意愿确认申请单
     * @param $business_code 业务申请编号
     * @return mixed
     */
    public function cancelConfirm($business_code)
    {
        $path = '/v3/apply4subject/applyment/'.$business_code.'/cancel';
        return $this->execute('POST', $path);
    }

    /**
     * 查询申请单审核结果
     * @param $applyment_id 申请单编号
     * @param $business_code 业务申请编号
     * @return mixed
     */
    public function queryConfirm($applyment_id = null, $business_code = null)
    {
        $path = '/v3/apply4subject/applyment';
        $params = [];
        if($applyment_id){
            $params['applyment_id'] = $applyment_id;
        }
        if($business_code){
            $params['business_code'] = $business_code;
        }
        return $this->execute('GET', $path);
    }

    /**
     * 获取商户开户意愿确认状态
     * @param $sub_mchid 子商户号
     * @return mixed
     */
    public function merchantState($sub_mchid)
    {
        $path = '/v3/apply4subject/applyment/merchants/'.$sub_mchid.'/state';
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
     * 视频上传
     * @param $file_path 文件路径
     * @param $file_name 文件名
     * @return string
     */
    public function uploadVideo($file_path, $file_name)
    {
        $path = '/v3/merchant/media/video_upload';
        $result = $this->upload($path, $file_path, $file_name);
        return $result['media_id'];
    }
}
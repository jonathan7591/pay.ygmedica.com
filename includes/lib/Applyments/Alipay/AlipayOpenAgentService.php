<?php
namespace lib\Applyments\Alipay;

use Alipay\AlipayService;

/**
 * 支付宝代开发核心API
 * @see https://opendocs.alipay.com/isv/04ejah
 */
class AlipayOpenAgentService extends AlipayService
{
    /**
     * @param $config 支付宝配置信息
     */
    public function __construct($config)
    {
        if(isset($config['app_auth_token'])) unset($config['app_auth_token']);
        parent::__construct($config);
    }

    /**
     * ISV邀约即授权页面创建
     * @param $isv_biz_id ISV业务申请单ID
     * @param $isv_return_url ISV回跳地址
     * @return mixed html
     */
    function inviteOrderCreate($isv_biz_id, $isv_return_url){
        $apiName = 'alipay.open.invite.order.create';
        $bizContent = [
            'isv_biz_id' => $isv_biz_id,
            'isv_return_url' => $isv_return_url,
        ];
        return $this->aopPageExecute($apiName, $bizContent);
    }

    /**
     * 查询签约申请单状态
     * @param $isv_biz_id ISV业务申请单ID
     * @return mixed
     */
    function inviteOrderQuery($isv_biz_id){
        $apiName = 'alipay.open.invite.order.query';
        $bizContent = [
            'isv_biz_id' => $isv_biz_id,
        ];
        return $this->aopExecute($apiName, $bizContent);
    }


    /**
     * 查询商户某个产品的签约状态
     * @param $pid 商户账号
     * @param $product_codes 产品码
     * @return mixed
     */
    function signStatusQuery($pid, $product_codes, $appAuthToken){
        $apiName = 'alipay.open.agent.signstatus.query';
        $bizContent = [
            'pid' => $pid,
            'product_codes' => $product_codes,
        ];
        $this->appAuthToken = $appAuthToken;
        return $this->aopExecute($apiName, $bizContent);
    }
    
    /**
     * 开启代商户签约、创建应用事务
     * @param $bizContent
     * @return mixed
     */
    function create($bizContent){
        $apiName = 'alipay.open.agent.create';
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 代签约当面付产品
     * @param $bizContent
     * @return mixed
     */
    function facetofaceSign($bizContent){
        $apiName = 'alipay.open.agent.facetoface.sign';
        return $this->aopExecute($apiName, null, $bizContent);
    }

    /**
     * 代签约APP支付产品
     * @param $bizContent
     * @return mixed
     */
    function mobilepaySign($bizContent){
        $apiName = 'alipay.open.agent.mobilepay.sign';
        return $this->aopExecute($apiName, null, $bizContent);
    }

    /**
     * 代签约产品通用接口
     * @param $bizContent
     * @return mixed
     */
    function commonSign($bizContent){
        $apiName = 'alipay.open.agent.common.sign';
        return $this->aopExecute($apiName, null, $bizContent);
    }

    /**
     * 提交代商户签约、创建应用事务
     * @param $batch_no 事务编号
     * @return mixed
     */
    function confirm($batch_no){
        $apiName = 'alipay.open.agent.confirm';
        $bizContent = [
            'batch_no' => $batch_no,
        ];
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 代商户签约，提交信息确认接口
     * @param $batch_no 事务编号
     * @return mixed
     */
    function commonConfirm($batch_no){
        $apiName = 'alipay.open.agent.commonsign.confirm';
        $bizContent = [
            'batch_no' => $batch_no,
        ];
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 取消代商户签约、创建应用事务
     * @param $batch_no 事务编号
     * @return mixed
     */
    function cancel($batch_no){
        $apiName = 'alipay.open.agent.cancel';
        $bizContent = [
            'batch_no' => $batch_no,
        ];
        return $this->aopExecute($apiName, $bizContent);
    }

     /**
     * 查询申请单状态
     * @param $batch_no 事务编号
     * @return mixed
     */
    function orderQuery($batch_no){
        $apiName = 'alipay.open.agent.order.query';
        $bizContent = [
            'batch_no' => $batch_no,
        ];
        return $this->aopExecute($apiName, $bizContent);
    }

}
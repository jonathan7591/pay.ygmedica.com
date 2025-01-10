<?php
namespace lib\Applyments\AlipayDirect;

use Alipay\AlipayService;

/**
 * 支付宝直付通商户进件服务
 * @see https://opendocs.alipay.com/open/01ml5d
 */
class AlipayMerchantExpandService extends AlipayService
{
/**
     * @param $config 支付宝配置信息
     */
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     * 二级商户创建预校验咨询
     */
    function consult($bizContent){
        $apiName = 'ant.merchant.expand.indirect.zft.consult';
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 二级商户标准进件
     */
    function simplecreate($bizContent){
        $apiName = 'ant.merchant.expand.indirect.zft.simplecreate';
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 二级商户创建
     */
    function create($bizContent){
        $apiName = 'ant.merchant.expand.indirect.zft.create';
        return $this->aopExecute($apiName, $bizContent);
    }
    
    /**
     * 二级商户入驻进度查询
     */
    function orderQuery($order_id = null, $external_id = null){
        $apiName = 'ant.merchant.expand.indirect.zftorder.query';
        $bizContent = [];
        if ($order_id) {
            $bizContent['order_id'] = $order_id;
        }
        if ($external_id) {
            $bizContent['external_id'] = $external_id;
        }
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 二级商户修改
     */
    function modify($bizContent){
        $apiName = 'ant.merchant.expand.indirect.zft.modify';
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 二级商户结算信息修改
     */
    function settlementmodify($bizContent){
        $apiName = 'ant.merchant.expand.indirect.zft.settlementmodify';
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 个人商户限额升级
     */
    function upgrade($bizContent){
        $apiName = 'ant.merchant.expand.indirect.zft.upgrade';
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 二级商户作废
     */
    function delete($bizContent){
        $apiName = 'ant.merchant.expand.indirect.zft.delete';
        return $this->aopExecute($apiName, $bizContent);
    }

    /**
     * 图片上传
     * @param $file_path 文件路径
     * @param $file_name 文件名
     * @return string 图片资源标识
     */
    public function imageUpload($file_path, $file_name)
    {
        $image_type = array_pop(explode('.',$file_name));
        if (empty($image_type)) $image_type = 'png';
        $apiName = 'ant.merchant.expand.indirect.image.upload';
        $params = [
            'image_type' => $image_type,
            'image_content' => new \CURLFile($file_path, '', $file_name),
        ];
        $result = $this->aopExecute($apiName, null, $params);
        return $result['image_id'];
    }

    /**
     * 联行号关联分支行查询
     * @param $inst_id 顶级机构ID
     * @param $city 所在城市
     * @return array
     * @see https://opendocs.alipay.com/apis/0853v1
     */
    public function getBankBranches($inst_id, $city)
    {
        $apiName = 'alipay.financialnet.auth.pbcname.query';
        $bizContent = [
            'inst_id' => $inst_id,
            'city' => $city,
        ];
        $result = $this->aopExecute($apiName, $bizContent);
        return json_decode($result['pbc_query_result'], true);
    }
}
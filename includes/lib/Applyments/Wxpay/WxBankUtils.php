<?php

namespace lib\Applyments\Wxpay;
use WeChatPay\V3\BaseService;

/**
 * 微信支付银行组件
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3_partner/Offline/open/chapter11_2_4.shtml
 */
class WxBankUtils extends BaseService
{
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     * 获取对私银行卡号开户银行
     * @param $account_number 银行卡号
     * @return mixed
     */
    public function searchBanksByBankAccount($account_number)
    {
        $params = [
            'account_number' => $account_number
        ];
        $path = '/v3/capital/capitallhh/banks/search-banks-by-bank-account';
        return $this->execute('GET', $path, $params);
    }

    /**
     * 查询支持个人业务的银行列表
     * @return mixed
     */
    public function getPersonalBanks($offset = 0, $limit = 10)
    {
        $params = [
            'offset' => $offset,
            'limit' => $limit
        ];
        $path = '/v3/capital/capitallhh/banks/personal-banking';
        return $this->execute('GET', $path, $params);
    }

    /**
     * 查询所有支持个人业务的银行列表
     * @return mixed
     */
    public function getAllPersonalBanks()
    {
        $offset = 0;
        $banks = [];
        do{
            $result = $this->getPersonalBanks($offset, 200);
            $offset += 200;
            if($result['data']){
                $banks = array_merge($banks, $result['data']);
            }
            usleep(200000);
        }while(count($result['data']) == 200);
        return $banks;
    }

    /**
     * 查询支持对公业务的银行列表
     * @return mixed
     */
    public function getCorporateBanks($offset = 0, $limit = 10)
    {
        $params = [
            'offset' => $offset,
            'limit' => $limit
        ];
        $path = '/v3/capital/capitallhh/banks/corporate-banking';
        return $this->execute('GET', $path, $params);
    }

    /**
     * 查询所有支持对公业务的银行列表
     * @return mixed
     */
    public function getAllCorporateBanks()
    {
        $offset = 0;
        $banks = [];
        do{
            $result = $this->getCorporateBanks($offset, 200);
            $offset += 200;
            if($result['data']){
                $banks = array_merge($banks, $result['data']);
            }
            usleep(200000);
        }while(count($result['data']) == 200);
        return $banks;
    }

    /**
     * 查询省份列表
     * @return mixed
     */
    public function getProvinces()
    {
        $path = '/v3/capital/capitallhh/areas/provinces';
        $result = $this->execute('GET', $path);
        return $result['data'];
    }

    /**
     * 查询城市列表
     * @param $province_code 省份编码
     * @return mixed
     */
    public function getCities($province_code)
    {
        $path = '/v3/capital/capitallhh/areas/provinces/'.$province_code.'/cities';
        $result = $this->execute('GET', $path);
        return $result['data'];
    }

    /**
     * 查询所有省份和城市列表
     * @return array
     */
    public function getAllProvincesAndCities()
    {
        $provinces = $this->getProvinces();
        $result = [];
        foreach($provinces as $province){
            $children = [];
            $cities = $this->getCities($province['province_code']);
            foreach($cities as $city){
                $children[] = ['value' => $city['city_code'], 'label' => $city['city_name']];
            }
            $result[] = ['value' => $province['province_code'], 'label' => $province['province_name'], 'children' => $children];
        }
        return $result;
    }

    /**
     * 查询支行列表
     * @param $bank_alias_code 银行别名编码
     * @param $city_code 城市编码
     * @param int $offset
     * @param int $limit
     * @return mixed
     */
    public function getBankBranches($bank_alias_code, $city_code, $offset = 0, $limit = 10)
    {
        $params = [
            'city_code' => $city_code,
            'offset' => $offset,
            'limit' => $limit
        ];
        $path = '/v3/capital/capitallhh/banks/'.$bank_alias_code.'/branches';
        $result = $this->execute('GET', $path, $params);
        return $result;
    }

    /**
     * 查询所有支行列表
     * @param $bank_alias_code 银行别名编码
     * @param $city_code 城市编码
     * @return mixed
     */
    public function getAllBankBranches($bank_alias_code, $city_code)
    {
        $offset = 0;
        $limit = 200;
        $branches = [];
        $result = $this->getBankBranches($bank_alias_code, $city_code, $offset, $limit);
        if($result['data']){
            $branches = array_merge($branches, $result['data']);
        }
        while($result['total_count'] > $offset+$limit){
            $offset += $limit;
            try{
                $result = $this->getBankBranches($bank_alias_code, $city_code, $offset, $limit);
            }catch(\Exception $e){
            }
            if($result['data']){
                $branches = array_merge($branches, $result['data']);
            }
        }
        return $branches;
    }
}
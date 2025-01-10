<?php


class LLianPayYtSignature
{
    /**
     * 对数据先进行MD5处理，后使用私钥进行RSA加签
     * @param $data 待加签数据
     * @return string 返回签名
     */
         const merchant_private_key_path = PLUGIN_ROOT . 'lianlianytpay' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'hzj_rsa_private_key.pem';
        const merchant_public_key_path = PLUGIN_ROOT . 'lianlianytpay' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'hzj_rsa_public_key.pem';
        const llianpay_public_key_path = PLUGIN_ROOT . 'lianlianytpay' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'llpay_public_key.pem';
    public static function sign($data = '')
    {
        if (empty($data)) {
           
            return False;
        }

        $private_key = self::_get_pem_content(self::merchant_private_key_path);
       
        if (empty($private_key)) {
          
            return False;
        }

        $pkeyid = openssl_get_privatekey($private_key);
        if (empty($pkeyid)) {
           
            return False;
        }

        // 生成签名原串
        $signed_str = self::generateSignedStr($data);
        // Logger()->info("[加签处理中]：待签名源内容：" . $data);
        // Logger()->info("[加签处理中]：待签名源内容对应签名原串为：" . $signed_str);
        
        // 使用数据的签名原串值和私钥进行RSA加密
        $verify = openssl_sign($signed_str, $signature, $pkeyid, OPENSSL_ALGO_MD5);
        $result = base64_encode($signature);
        // Logger()->info("[加签处理中]，签名值为：" . $result);
        return $result;
    }

    /**
     * 利用连连公钥和进行验签
     * @param $data 待验证数据
     * @param $signature 签名值
     * @return -1:error验签异常 1:correct验证成功 0:incorrect验证失败
     */
    public static function checkSign($data = '', $signature = '')
    {
        if (empty($data) || empty($signature)) {
            // Logger()->error("[验签处理中]：待验签数据或签名值为空，请核实！");
            return False;
        }

        $public_key = self::_get_pem_content(self::llianpay_public_key_path);
        if (empty($public_key)) {
            // Logger()->error("[验签处理中]：验签公钥错误，请核实！");
            return False;
        }

        $pkeyid = openssl_get_publickey($public_key);
        if (empty($pkeyid)) {
            // Logger()->error("[验签处理中]：验签公钥错误，请核实！");
            return False;
        }

        // 生成签名原串
        $signed_str = self::generateSignedStr($data);        
        // Logger()->info("[验签处理中]：待验签数据为：" . $data);
        // Logger()->info("[验签处理中]：待签名源内容对应签名原串为：" . $signed_str);
        // Logger()->info("[验签处理中]：待验签名值为：" . $signature);

        // 使用数据的签名原串值和签名值进行RSA校验
        $ret = openssl_verify($signed_str, base64_decode($signature), $pkeyid, OPENSSL_ALGO_MD5);
        switch ($ret) {
            case 0:
                // Logger()->info("[验签处理中]：验签完成，验签结果为：错误");
                break;
            case 1:
                // Logger()->info("[验签处理中]：验签完成，验签结果为：正确");
                break;
            default:
                // Logger()->error("[验签处理中]：验签异常！");
                break;
        }
        return $ret;
    }

    /**
     * 生成待签名串, &符号拼接
     * @param $data 待加签数据
     * @return string 返回拼接后的字符串
     */
    public static function generateSignedStr($data)
    {
        //json 字符串转数组
        $data_array = json_decode($data, true);
        //除去待签名参数数组中的空值和签名参数
        $data_array_filter = array();
        foreach ($data_array as $key => $val) {
            if ($key == "sign" || $val == "")
                continue;
            else	
                $data_arraoy_filter[$key] = $data_array[$key];
        }

        //对待签名参数数组排序
        ksort($data_arraoy_filter);
        reset($data_arraoy_filter);

        // 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $result_str  = "";
        foreach ($data_arraoy_filter as $key => $val) {
            $result_str .= $key . "=" . $val."&";
        }
        //去掉最后一个&字符
        $result_str = substr($result_str, 0 , strlen($result_str) - 1);
        //如果存在转义字符，那么去掉转义
        //if (get_magic_quotes_gpc()) 
        //    $result_str = stripslashes($result_str);
        return $result_str;
    }

    private static function _get_pem_content($file_path)
    {
        $fullPath = $file_path;
            if (!file_exists($fullPath)) {
                throw new \Exception("文件不存在: " . $fullPath);
            }
            if (!is_readable($fullPath)) {
                throw new \Exception("文件不可读: " . $fullPath);
            }
            return file_get_contents($fullPath);
    }
}
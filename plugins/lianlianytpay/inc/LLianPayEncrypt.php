<?php


class LLianPayEncrypt
{
    /**
     * 使用连连公钥对请求数据进行加密
     * @param $json_data 请求数据 
     * @return string 返回加密后的值
     */
        const merchant_private_key_path = PLUGIN_ROOT . 'lianlianytpay' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'hzj_rsa_private_key.pem';
        const merchant_public_key_path = PLUGIN_ROOT . 'lianlianytpay' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'hzj_rsa_public_key.pem';
        const llianpay_public_key_path = PLUGIN_ROOT . 'lianlianytpay' . DIRECTORY_SEPARATOR . 'keys' . DIRECTORY_SEPARATOR . 'llpay_public_key.pem';
    public static function encryptGeneratePayload($json_data)
    {
        if (empty($json_data)) {
           
            return False;
        }

        $public_key = self::_get_pem_content(self::llianpay_public_key_path);
        //var_dump($json_data);
        if (empty($public_key)) {
           
            return False;
        }

        $pkeyid = openssl_get_publickey($public_key);

        $version = "lianpay1_0_1";
        $hmack_key = self::genLetterDigitRandom(32);
        $aes_key = self::genLetterDigitRandom(32);
	    $nonce = self::genLetterDigitRandom(8);
        return self::lianlianpayEncrypt($json_data, $pkeyid, $hmack_key, $version, $aes_key, $nonce);
    }

    private static function lianlianpayEncrypt($req, $public_key, $hmack_key, $version, $aes_key, $nonce) 
    {
        $B64hmack_key = self::rsaEncrypt($hmack_key, $public_key);
        $B64aes_key = self::rsaEncrypt($aes_key, $public_key);
        $B64nonce = base64_encode($nonce);
        $encry = self::aesEncrypt(utf8_decode($req), $aes_key, $nonce);
        $message = $B64nonce . "$" . $encry;
        $sign = hex2bin(hash_hmac("sha256", $message, $hmack_key));
        $B64sign = base64_encode($sign);
        return $version . '$' . $B64hmack_key . '$' . $B64aes_key . '$' . $B64nonce . '$' . $encry . '$' . $B64sign;
    }

    private static function rsaEncrypt($data,$public_key){
        openssl_public_encrypt($data, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING ); // 公钥加密
        return base64_encode($encrypted);
    }

    private static function aesEncrypt($data,$key,$nonce){
        return base64_encode(openssl_encrypt($data, "AES-256-CTR", $key, true, $nonce . "\0\0\0\0\0\0\0\1"));
    }

    private static function genLetterDigitRandom($size) {
        $allLetterDigit = array("0","1","2","3","4","5","6","7","8","9","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
        $randomSb = "";
        $digitSize = count($allLetterDigit)-1;
        for($i = 0; $i < $size; $i ++){
            $randomSb .= $allLetterDigit[rand(0,$digitSize)];
        }
        return $randomSb;
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
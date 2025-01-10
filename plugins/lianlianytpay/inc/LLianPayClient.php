<?php

require_once PAY_ROOT . "inc/LLianPayEncrypt.php";

class LLianPayClient
{
    public static function sendRequest($url, $content)
    {
        if (empty($url) || empty($content)) {
            throw new \Exception("请求URL或参数不能为空");
        }
         //file_put_contents('ll.txt',$content.$url.PHP_EOL,FILE_APPEND);
        //  $content['pay_load'] = LLianPayEncrypt::encryptGeneratePayload($content['pay_load']);
   
        // 拼接请求头
        $headers = [
            'timestamp: ' . date("YmdHis"),
            'Content-Type: application/json;charset=UTF-8',
            'Referer: https://opay.njhzjmh.com', // 请求来源
        ];
         
        // 发送请求
        $response = self::curlPost($url, [
            'body' => $content,
            'headers' => $headers,
            'verify' => false
        ]);
     
        // // 进行验签
        // if (isset($response['headers']['Signature-Data'])) {
        //     $isValid = LLianPayAccpSignature::checkSign($response['body'], $response['headers']['Signature-Data']);
        //     if ($isValid !== 1) {
        //         throw new \Exception("验签失败");
        //     }
        // }
        log_debug("接口返回".json_encode($response),'lianlianytpay');
        // file_put_contents(date('Ymd').'lresult.txt',$response.PHP_EOL,FILE_APPEND);
        return json_decode($response,true);
    }
    
    public static function curlPost($url, $bodys)
    {
    //   var_dump($url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
    
        curl_setopt($ch, CURLOPT_HTTPHEADER, $bodys['headers']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodys['body']);
        curl_setopt($ch, CURLOPT_NOBODY, false); // 保留响应体
    
        $responseBody = curl_exec($ch);
        if (curl_errno($ch) > 0) {
            $errmsg = curl_error($ch);
            curl_close($ch);
            throw new \Exception($errmsg, 0);
        }
        // var_dump($responseBody);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpStatusCode != 200) {
            curl_close($ch);
            throw new \Exception($responseBody ? $responseBody : 'http_code=' . $httpStatusCode, $httpStatusCode);
        }
         
        // 解析响应头
        //$responseHeaders = self::parseHeaders(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        //var_dump($responseHeaders);
        curl_close($ch);
        
       return $responseBody;
    }

    
}

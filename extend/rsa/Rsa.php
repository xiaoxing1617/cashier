<?php
namespace Rsa;
class Rsa {
    private $public_key;  //公钥
    private $private_key;  //私钥

    public function __construct($public_key,$private_key)
    {
        $this->public_key = $public_key;
        $this->private_key = $private_key;
    }
    /**
     * 获取私钥
     * @return bool|resource
     */
    private function getPrivateKey()
    {
        return "-----BEGIN PRIVATE KEY-----\n".$this->private_key."\n-----END PRIVATE KEY-----";
    }

    /**
     * 获取公钥
     * @return bool|resource
     */
    private function getPublicKey()
    {
        return "-----BEGIN PUBLIC KEY-----\n".$this->public_key."\n-----END PUBLIC KEY-----";
    }
    /**
     * 私钥加密
     * @param string $data
     * @return null|string
     */
    public function privEncrypt($data = '')
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_private_encrypt($data,$encrypted,$this->getPrivateKey()) ? base64_encode($encrypted) : null;
    }

    /**
     * 公钥加密
     * @param string $data
     * @return null|string
     */
    public function publicEncrypt($data = '')
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_public_encrypt($data,$encrypted,$this->getPublicKey()) ? base64_encode($encrypted) : null;
    }

    /**
     * 私钥解密
     * @param string $encrypted
     * @return null
     */
    public function privDecrypt($encrypted = '')
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return (openssl_private_decrypt(base64_decode($encrypted), $decrypted, $this->getPrivateKey())) ? $decrypted : null;
    }

    /**
     * 公钥解密
     * @param string $encrypted
     * @return null
     */
    public function publicDecrypt($encrypted = '')
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return (openssl_public_decrypt(base64_decode($encrypted), $decrypted, $this->getPublicKey())) ? $decrypted : null;
    }

    /**
     * 公钥加密的内容 - 验证
     * @param $prestr 需要验证的字符串
     * @param $sign 加密结果
     * return bool
     */
    public function publicKeyVerify($prestr='', $sign='') {
        $str = $this->privDecrypt($sign);  //私钥解密
        if($prestr===$str){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 私钥加密的内容 - 验证
     * @param $prestr 需要验证的字符串
     * @param $sign 加密结果
     * return bool
     */
    public function privateKeyVerify($prestr='', $sign='') {
        $str = $this->publicDecrypt($sign);  //公钥解密
        if($prestr===$str){
            return true;
        }else{
            return false;
        }
    }
}
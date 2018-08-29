<?php
/**
 * AES-256-CBC Encode/Decode
 * 如果加密时未提供IV，则生成16个字节的随机IV，附加在加密串之前返回，即 IV + EncryptString
 * 如果解密时未提供IV，则取待解密串的前16个字节做为IV，待解密串为16字节后
 * @copyright 2004 - 2017 Qinhe Co.,Ltd. (http://www.ispeak.cn/)
 * @since     2017/08/23
 * @author    iSpeak Dev Team <fair>
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Aes
{
    const IV_LENGTH = 16;

    protected $key = '';
    protected $driver = '';
    protected $method = 'AES-256-CBC';

    public function __construct($key = null)
    {
        $dirvers = array(
            'mcrypt' => defined('MCRYPT_DEV_URANDOM'),
            'openssl' => defined('OPENSSL_ZERO_PADDING'),
        );

        if (!$dirvers['mcrypt'] && !$dirvers['openssl']) {
            throw new \Exception('Unable to find an available encryption driver.');
        }
        $this->driver = $dirvers['openssl'] ? 'openssl' : 'mcrypt';

        if (null !== $key) {
            $this->setKey($key);
        }
    }

    public function __destruct()
    {
    }

    public function setKey($key)
    {
        if (0 !== ($key % 16)) {
            throw new \Exception('key length invalid');
        }
        $this->key = $key;
        if (16 === strlen($key)) {
            $this->method = 'AES-128-CBC';
        }

        return $this;
    }

    public function encode($data, $iv = null)
    {
        $foo = $this->driver . 'Encode';

        return $this->$foo($data, $iv);
    }

    public function decode($data, $iv = null)
    {
        $foo = $this->driver . 'Decode';

        return $this->$foo($data, $iv);
    }

    public function createRandomBytes($len)
    {
        return 'openssl' === $this->driver ? openssl_random_pseudo_bytes($len) : mcrypt_create_iv($len, MCRYPT_DEV_URANDOM);
    }

    protected function mcryptEncode($data, $iv = null)
    {
        $prepand = '';
        if (null === $iv) {
            $iv = mcrypt_create_iv(self::IV_LENGTH, MCRYPT_DEV_URANDOM);
            $prepand = $iv;
        }

        return base64_encode($prepand . mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->key, $this->pkcs7pad($data), MCRYPT_MODE_CBC, $iv));
    }

    protected function mcryptDecode($data, $iv = null)
    {
        $data = base64_decode($data);
        if (null === $iv) {
            $iv = substr($data, 0, self::IV_LENGTH);
            $data = substr($data, self::IV_LENGTH);
        }
        $result = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->key, $data, MCRYPT_MODE_CBC, $iv);

        return false === $result ? false : $this->pkcs7unpad($result);
    }

    protected function opensslEncode($data, $iv = null)
    {
        $prepand = '';
        if (null === $iv) {
            $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
            $prepand = $iv;
        }
        $result = openssl_encrypt($this->pkcs7pad($data), $this->method, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);

        return base64_encode($prepand . $result);
    }

    protected function opensslDecode($data, $iv = null)
    {
        $data = base64_decode($data);
        if (null === $iv) {
            $iv = substr($data, 0, self::IV_LENGTH);
            $data = substr($data, self::IV_LENGTH);
        }
        $result = openssl_decrypt($data, $this->method, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);

        return false === $result ? false : $this->pkcs7unpad($result);
    }

    protected function pkcs7pad($data, $blockSize = 32)
    {
        $pad = $blockSize - (strlen($data) % $blockSize);
        if ($pad === 0) {
            return $data;
        }

        return $data . str_repeat(chr($pad), $pad);
    }

    protected function pkcs7unpad($data, $blockSize = 32)
    {
        $pad = ord(substr($data, -1));
        if ($pad < 1 || $pad > $blockSize) {
            return $data;
        }

        return substr($data, 0, 0 - $pad);
    }
}

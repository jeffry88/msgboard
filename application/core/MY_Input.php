<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Input extends CI_Input
{
    static private $REQMETHOD = null;

    public function __construct()
    {
        parent::__construct();
        self::$REQMETHOD = strtoupper($_SERVER['REQUEST_METHOD']);
    }

    public function is_get()
    {
        return 'GET' === self::$REQMETHOD;
    }

    public function is_post()
    {
        return 'POST' === self::$REQMETHOD;
    }

    public function is_put()
    {
        return 'PUT' === self::$REQMETHOD;
    }

    public function is_delete()
    {
        return 'DELETE' === self::$REQMETHOD;
    }

    public function is_patch()
    {
        return 'PATCH' === self::$REQMETHOD;
    }

    public function is_head()
    {
        return 'HEAD' === self::$REQMETHOD;
    }

    public function proxy_ip_address()
    {
        if (false !== $this->ip_address) {
            return $this->ip_address;
        }
        $serverip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : (isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : '0.0.0.0');
        list($host,) = explode(':', $_SERVER['HTTP_HOST']);
        if ($host === $serverip) //IP直接访问
        {
            $this->ip_address = $_SERVER['REMOTE_ADDR'];
        } else {
            foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_X_CLIENT_IP', 'REMOTE_ADDR') as $header) {
                if (null !== ($spoof = $this->server($header))) {
                    sscanf($spoof, '%[^,]', $spoof);
                    if (false === $this->valid_ip($spoof, 'ipv4')) {
                        $spoof = null;
                    } else {
                        $this->ip_address = $spoof;
                        break;
                    }
                }
            }
        }
        if (false === $this->valid_ip($this->ip_address, 'ipv4')) {
            return $this->ip_address = '0.0.0.0';
        }

        return $this->ip_address;
    }

    public function valid_email($email)
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function valid_url($url)
    {
        return (bool)filter_var($url, FILTER_VALIDATE_URL);
    }

    public function valid_date($str)
    {
        if (false == preg_match('/^2\d{3}(?:-|\/)(?:0?\d|1[0-2])(?:-|\/)(?:0?\d|[1,2]\d|3[0,1])$/', $str)) {
            return false;
        }
        list($y, $m, $d) = preg_split('/-|\//', $str);

        return checkdate($m, $d, $y);
    }

    public function valid_datetime($str)
    {
        if (false == preg_match('/^(2\d{3}(?:-|\/)(?:0?\d|1[0-2])(?:-|\/)(?:0?\d|[1,2]\d|3[0,1])) (?:(?:[0-1]?[0-9])|(?:[2][0-3])):(?:[0-5]?[0-9])(?::(?:[0-5]?[0-9]))?$/', $str, $match)) {
            return false;
        }

        list($y, $m, $d) = preg_split('/-|\//', $match[1]);

        return checkdate($m, $d, $y);
    }

    public function valid_mobile($str)
    {
        return (bool)preg_match('/^1(3\d|5[012356789]|7[35678]|8\d|9[89])\d{8}$/', $str);
    }

    public function valid_sint32(&$str)
    {
        if (false === ctype_digit($str) || 10 < strlen($str)) {
            return false;
        }
        $num = intval($str);
        if (2147483648 > $num) {
            $str = $num;

            return true;
        }

        return false;
    }

    public function valid_uint32(&$str)
    {
        if (false === ctype_digit($str) || 10 < strlen($str)) {
            return false;
        }
        $num = intval($str);
        if (4294967296 > $num) {
            $str = $num;

            return true;
        }

        return false;
    }
}

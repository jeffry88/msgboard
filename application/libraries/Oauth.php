<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Oauth
{
    public function __construct()
    {
    }

    /*
     * return provider object
    */
    public function initialize($driver, $options = array())
    {
        $class = 'Oauth_' . strtoupper($driver);
        if (!file_exists($filepath = APPPATH . 'libraries/Oauth/Oauth_' . $driver . '.php')) {
            show_error('The oauth driver ' . $driver . ' not found.', 404);
        }
        include($filepath);

        return new $class($options);
    }
}

abstract class AOauth_Provider
{
    public $error = null;
    public $appid = '';
    public $appkey = '';

    abstract public function get_token($code);

    abstract public function get_info($token);

    abstract public function authorize($state = false, $return = false);

    abstract public function rest($method, $params = array(), $type = 'GET', $decode = true);

    abstract public function mrest($method, $params = array(), $type = 'GET');

    public function __construct()
    {
        $this->error = new stdClass;
    }

    public function __destruct()
    {
        $this->error = null;
    }

    protected function curl($url, $params = false, $type = 'GET', $cntimeout = 5, $timeout = 30, $curlopts = array())
    {
        $defaults = array(
            CURLOPT_FAILONERROR => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1)',
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $cntimeout
        );
        $options = $curlopts + $defaults;
        if (false === $ch = curl_init()) {
            return false;
        }
        curl_setopt_array($ch, $options);

        if ('GET' == $type) {
            if (!empty($params)) {
                $url = http_build_url($url, array('query' => is_string($params) ? $params : http_build_query($params)), HTTP_URL_JOIN_QUERY);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            if ('POST' == $type) {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
            }
            if (!empty($params)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
        }

        $res = array(
            'data' => curl_exec($ch),
            'errno' => curl_errno($ch),
            'error' => curl_error($ch),
            'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        return $res;
    }

    protected function mcurl(array $urls, $params = array(), $type = 'GET', $cntimeout = 5, $timeout = 30, $curlopts = array())
    {
        $defaults = array(
            CURLOPT_FAILONERROR => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1)',
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $cntimeout
        );
        $options = $curlopts + $defaults;

        $chs = array();
        if (false === $mh = curl_multi_init()) {
            return false;
        }
        if ('GET' == $type) {
            foreach ($urls as $k => $url) {
                $ch = curl_init();
                curl_setopt_array($ch, $options);
                if (isset($params[$k]) && !empty($params[$k])) {
                    $url = http_build_url($url, array('query' => is_string($params[$k]) ? $params[$k] : http_build_query($params[$k])), HTTP_URL_JOIN_QUERY);
                }
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                $chs["{$k}"] = $ch;
                curl_multi_add_handle($mh, $ch);
            }
        } else {
            foreach ($urls as $k => $url) {
                $ch = curl_init();
                curl_setopt_array($ch, $options);
                curl_setopt($ch, CURLOPT_URL, $url);
                if ('POST' == $type) {
                    curl_setopt($ch, CURLOPT_POST, true);
                } else {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
                }
                if (isset($params[$k]) && !empty($params[$k])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params[$k]);
                }
                $chs["{$k}"] = $ch;
                curl_multi_add_handle($mh, $ch);
            }
        }

        $running = null;
        do {
            $cme = curl_multi_exec($mh, $running);
        } while ($cme == CURLM_CALL_MULTI_PERFORM);
        while ($running && $cme == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $cme = curl_multi_exec($mh, $running);
                } while ($cme == CURLM_CALL_MULTI_PERFORM);
            }
        }

        $rs = array();
        foreach ($chs as $k => $ch) {
            $rs["{$k}"] = array(
                'data' => curl_multi_getcontent($ch),
                'errno' => curl_errno($ch),
                'error' => curl_error($ch),
                'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE)
            );
            curl_close($ch);
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);

        return $rs;
    }

    //return stdClass
    protected function parse_response($str)
    {
        if ('{' == substr($str, 0, 1)) {
            $response = json_decode($str);
        } elseif ('callback(' == substr($str, 0, 9)) //for qq error
        {
            $end = strrpos($str, ')') - 9;
            $response = json_decode(trim(substr($str, 9, $end)));
        } else //for qq
        {
            parse_str($str, $ary);
            $response = new stdClass;
            foreach ($ary as $k => $v) {
                $response->$k = $v;
            }
        }

        return $response;
    }
}

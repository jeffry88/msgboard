<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Oauth_WEIXIN extends AOauth_Provider
{
    private $authorize_url = 'https://open.weixin.qq.com/connect/qrconnect';
    private $token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
    private $rest_url = 'https://api.weixin.qq.com/sns';

    public function __construct($config = array())
    {
        parent::__construct();
        if (!empty($config)) {
            foreach ($config as $k => $v) {
                $this->$k = $v;
            }
        }
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function __get($item)
    {
        return isset($this->$item) ? $this->$item : null;
    }

    public function __set($item, $value)
    {
        $this->$item = $value;

        return true;
    }

    public function authorize($state = false, $return = false)
    {
        $ci = &get_instance();
        $params = array(
            'response_type' => 'code',
            'appid' => $this->appid,
            'scope' => 'snsapi_login',
            'state' => false == $state ? substr(md5(uniqid(mt_rand(), true)), 13, 8) : $state,
            'redirect_uri' => $ci->config->site_url($ci->uri->uri_string()));
        $uri = http_build_url($this->authorize_url, array('query' => http_build_query($params)));
        if (true == $return) {
            return $uri;
        }
        header('Location: ' . $uri);
        exit;
    }

    /*
     ** { "errcode":40029,"errmsg":"invalid code" }
     ** { "access_token":"ACCESS_TOKEN", "expires_in":7200, "refresh_token":"REFRESH_TOKEN","openid":"OPENID", "scope":"SCOPE" }
    */
    public function get_token($code)
    {
        $params = array(
            'grant_type' => 'authorization_code',
            'appid' => $this->appid,
            'secret' => $this->appkey,
            'code' => $code);
        $res = $this->curl($this->token_url, $params);
        if (false === $res) {
            $this->error->code = 503;
            $this->error->msg = 'libcurl not support';

            return false;
        }
        if (0 != $res['errno']) {
            $this->error->code = $res['errno'];
            $this->error->msg = !empty($res['error']) ? $res['error'] : $res['data'] );
			return false;
		}
        $data = $this->parse_response($res['data']);
        if (isset($data->errcode) || !isset($data->access_token)) {
            $this->error->code = $data->errcode;
            $this->error->msg = $data->errmsg;

            return false;
        }

        return $data;
    }

    /*
     * { 
     * "openid":"OPENID",
     * "nickname":"NICKNAME",
     * "sex":1,
     * "province":"PROVINCE",
     * "city":"CITY",
     * "country":"COUNTRY",
     * "headimgurl": "http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/0",
     * "privilege":[
     *   "PRIVILEGE1", 
     *   "PRIVILEGE2"
     * ],
     * "unionid": " o6_bmasdasdsad6_2sgVt7hMZOPfL"
     * }
    */
    public function get_info($token)
    {
        $params = array(
            'access_token' => $token->access_token,
            'openid' => $token->openid);
        $res = $this->curl($this->rest_url . '/userinfo', $params);
        if (false === $res) {
            $this->error->code = 503;
            $this->error->msg = 'libcurl not support';

            return false;
        }
        if (0 != $res['errno']) {
            $this->error->code = $res['errno'];
            $this->error->msg = !empty($res['error']) ? $res['error'] : $res['data'] );
			return false;
		}
        $data = $this->parse_response($res['data']);
        if (isset($data->errcode)) {
            $this->error->code = $data->errcode;
            $this->error->msg = $data->errmsg;

            return false;
        }

        return array(
            'uid' => $data->openid,//$data->unionid
            'name' => '',
            'nickname' => $data->nickname,
            'gender' => $data->sex,
            'avatar' => $data->headimgurl); //[0, 46, 64, 96, 132]
    }

    public function rest($uri, $params = array(), $type = 'GET', $decode = true)
    {
        $res = $this->curl($this->rest_url . $uri, $params, $type);
        if (false === $res) {
            $this->error->code = 503;
            $this->error->msg = 'libcurl not support';

            return false;
        }
        if (0 != $res['errno']) {
            $this->error->code = $res['errno'];
            $this->error->msg = !empty($res['error']) ? $res['error'] : $res['data'] );
			return false;
		}
        $data = $this->parse_response($res['data']);
        unset($res);
        if (isset($data->errcode)) {
            $this->error->code = $data->errcode;
            $this->error->msg = $data->errmsg;

            return false;
        }

        return true === $decode ? $data : $res['data'];
    }

    public function mrest($uri, $params = array(), $type = 'GET')
    {
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Oauth_WEIBO extends AOauth_Provider
{
    private $authorize_url = 'https://api.weibo.com/oauth2/authorize';
    private $token_url = 'https://api.weibo.com/oauth2/access_token';
    private $rest_url = 'https://api.weibo.com/2';

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
            'client_id' => $this->appid,
            'state' => false == $state ? substr(md5(uniqid(mt_rand(), true)), 13, 8) : $state,
            'redirect_uri' => $ci->config->site_url($ci->uri->uri_string())
        );
        if (isset($this->scope) && !empty($this->scope)) {
            $params['scope'] = $this->scope;
        }
        $uri = http_build_url($this->authorize_url, array('query' => http_build_query($params)));
        if (true == $return) {
            return $uri;
        }
        header('Location: ' . $uri);
        exit;
    }

    /*
     ** {"error":"HTTP METHOD is not suported for this request!","error_code":10021,"request":"/oauth2/access_token"}
     ** { "access_token": "ACCESS_TOKEN", "expires_in": 1234, "remind_in":"798114", "uid":"12341234" }
    */
    public function get_token($code)
    {
        $ci = &get_instance();
        $params = array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->appid,
            'client_secret' => $this->appkey,
            'code' => $code,
            'redirect_uri' => $ci->config->site_url($ci->uri->uri_string())
        );
        $res = $this->curl($this->token_url, http_build_query($params), 'POST');
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
        if (isset($data->error_code) || !isset($data->access_token)) {
            $this->error->code = $data->error_code;
            $this->error->msg = $data->error;

            return false;
        }

        return $data;
    }

    public function get_info($token)
    {
        $params = array(
            'access_token' => $token->access_token,
            'uid' => $token->uid
        );
        $res = $this->curl($this->rest_url . '/users/show.json', http_build_query($params));
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
        if (isset($data->error_code)) {
            $this->error->code = $data->error_code;
            $this->error->msg = $data->error;

            return false;
        }

        return array(
            'uid' => $data->id,
            'name' => $data->name,
            'nickname' => $data->screen_name,
            'gender' => 'm' == $data->gender ? 1 : ('f' == $data->gender ? 2 : 0),
            'avatar' => $data->avatar_large
        );
    }

    public function rest($uri, $params = array(), $type = 'GET', $decode = true)
    {
        $res = $this->curl($this->rest_url . $uri, http_build_query($params), $type);
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
        if (isset($data->error_code)) {
            $this->error->code = $data->error_code;
            $this->error->msg = $data->error;

            return false;
        }

        return true === $decode ? $data : $res['data'];
    }

    public function mrest($uri, $params = array(), $type = 'GET')
    {
    }
}

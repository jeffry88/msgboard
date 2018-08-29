<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Oauth_QQ extends AOauth_Provider
{
    private $authorize_url = 'https://graph.qq.com/oauth2.0/authorize';
    private $token_url = 'https://graph.qq.com/oauth2.0/token';
    private $rest_url = 'https://graph.qq.com';

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
            'redirect_uri' => $ci->config->site_url($ci->uri->uri_string()));
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
     ** callback( {"error":100019,"error_description":"code to access token error"} );
     ** access_token=FD36C11131C25E2AA0C2BAC2858B9B0A&expires_in=7776000&refresh_token=071351640F85F4DC6AE5ACF5B5239857
     ** callback( {"error":100019,"error_description":"code to access token error"} );
    */
    public function get_token($code)
    {
        $ci = &get_instance();
        $params = array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->appid,
            'client_secret' => $this->appkey,
            'code' => $code,
            'redirect_uri' => $ci->config->site_url($ci->uri->uri_string()));
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
        if (isset($data->error) || !isset($data->access_token)) {
            $this->error->code = $data->error;
            $this->error->msg = $data->error_description;

            return false;
        }

        return $data;
    }

    public function get_info($token)
    {
        if (false === ($openid = $this->get_openid($token->access_token))) {
            return false;
        }

        $params = array(
            'access_token' => $token->access_token,
            'oauth_consumer_key' => $this->appid,
            'openid' => $openid,
            'format' => 'json');
        $res = $this->curl($this->rest_url . '/user/get_user_info', $params);
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
        if (0 != $data->ret) {
            $this->error->code = $data->ret;
            $this->error->msg = $data->msg;

            return false;
        }

        return array(
            'uid' => $openid,
            'name' => '',
            'nickname' => $data->nickname,
            'gender' => '男' == $data->gender ? 1 : 2,
            'avatar' => $data->figureurl_2);
    }

    public function rest($uri, array $params = array(), $type = 'GET', $decode = true)
    {
        if (false === ($openid = $this->get_openid($token->access_token))) {
            return false;
        }
        $params['oauth_consumer_key'] = $this->appid;
        $params['openid'] = $openid;
        $params['format'] = 'json';

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
        if (0 != $data->ret) {
            $this->error->code = $data->ret;
            $this->error->msg = $data->msg;

            return false;
        }

        return true === $decode ? $data : $res['data'];
    }

    public function mrest($uri, $params = array(), $type = 'GET')
    {
    }

    private function get_openid($access_token)
    {
        static $openid = null;
        if (!isset($openid)) {
            $params = array('access_token' => $access_token);
            $res = $this->curl($this->rest_url . '/oauth2.0/me', $params);
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
            if (isset($data->error) || !isset($data->openid)) {
                $this->error->code = $data->error;
                $this->error->msg = $data->error_description;

                return false;
            }
            $openid = $data->openid;
            unset($data);
        }

        return $openid;
    }
}

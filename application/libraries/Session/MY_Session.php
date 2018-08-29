<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Session extends CI_Session
{
    public function __construct(array $params = array())
    {
        parent::__construct($params);
    }

    protected function _configure(&$params)
    {
        $expiration = config_item('sess_expiration');

        if (isset($params['cookie_lifetime'])) {
            $params['cookie_lifetime'] = (int)$params['cookie_lifetime'];
        } else {
            $params['cookie_lifetime'] = (!isset($expiration) && config_item('sess_expire_on_close'))
                ? 0 : (int)$expiration;
        }

        isset($params['cookie_name']) OR $params['cookie_name'] = config_item('sess_cookie_name');
        if (empty($params['cookie_name'])) {
            $params['cookie_name'] = ini_get('session.name');
        } else {
            ini_set('session.name', $params['cookie_name']);
        }

        isset($params['cookie_path']) OR $params['cookie_path'] = config_item('cookie_path');
        isset($params['cookie_domain']) OR $params['cookie_domain'] = config_item('cookie_domain');
        isset($params['cookie_secure']) OR $params['cookie_secure'] = (bool)config_item('cookie_secure');

        session_set_cookie_params(
            $params['cookie_lifetime'],
            $params['cookie_path'],
            $params['cookie_domain'],
            $params['cookie_secure'],
            true // HttpOnly; Yes, this is intentional and not configurable for security reasons
        );

        if (empty($expiration)) {
            $params['expiration'] = (int)ini_get('session.gc_maxlifetime');
        } else {
            $params['expiration'] = (int)$expiration;
            ini_set('session.gc_maxlifetime', $expiration);
        }

        $params['match_ip'] = (bool)(isset($params['match_ip']) ? $params['match_ip'] : config_item('sess_match_ip'));

        isset($params['save_path']) OR $params['save_path'] = config_item('sess_save_path');
        isset($params['sess_use_trans_sid']) OR $params['sess_use_trans_sid'] = config_item('sess_use_trans_sid');

        $this->_config = $params;

        if ($params['sess_use_trans_sid']) {
            ini_set('session.use_trans_sid', 1);
            ini_set('session.use_cookies', 0);
            ini_set('session.use_only_cookies', 0);
        } else {
            ini_set('session.use_trans_sid', 0);
            ini_set('session.use_cookies', 1);
            ini_set('session.use_only_cookies', 1);
        }
        ini_set('session.use_strict_mode', 1);
        ini_set('session.hash_function', 1);
        ini_set('session.hash_bits_per_character', 4);
    }
}

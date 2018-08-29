<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Session_memcached_driver extends CI_Session_memcached_driver
{
    public function __construct(&$params)
    {
        parent::__construct($params);
    }

    public function open($save_path, $name)
    {
        $this->_memcached = new Memcached('memcached_persistance_conn');
        $this->_memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true); // required for touch() usage
        $server_list = array();
        foreach ($this->_memcached->getServerList() as $server) {
            $server_list[] = $server['host'] . ':' . $server['port'];
        }

        if (!preg_match_all('#,?([^,:]+)\:(\d{1,5})(?:\:(\d+))?#', $this->_config['save_path'], $matches, PREG_SET_ORDER)) {
            $this->_memcached = null;
            log_message('error', 'Session: Invalid Memcached save path format: ' . $this->_config['save_path']);

            return $this->_fail();
        }

        foreach ($matches as $match) {
            // If Memcached already has this server (or if the port is invalid), skip it
            if (in_array($match[1] . ':' . $match[2], $server_list, true)) {
                log_message('debug', 'Session: Memcached server pool already has ' . $match[1] . ':' . $match[2]);
                continue;
            }

            if (!$this->_memcached->addServer($match[1], $match[2], isset($match[3]) ? $match[3] : 0)) {
                log_message('error', 'Could not add ' . $match[1] . ':' . $match[2] . ' to Memcached server pool.');
            } else {
                $server_list[] = $match[1] . ':' . $match[2];
            }
        }

        if (empty($server_list)) {
            log_message('error', 'Session: Memcached server pool is empty.');

            return $this->_fail();
        }

        return $this->_success;
    }
}

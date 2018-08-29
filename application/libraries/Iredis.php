<?php
/**
 * IRedis Library
 * @copyright    Copyright (c) 2004 - 2016, Qinhe Co.,Ltd. (http://www.ispeak.cn/)
 * @since    Date 2016-03-09
 * @author    iSpeak Dev Team <Fair>
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Iredis
{
    private $redis = null;
    private $host = 'tcp://127.0.0.1:6379'; //OR unix:/tmp/redis.sock
    private $timeout = 5;
    private $passwd = false;
    private $dbid = false;
    private $debug = false;

    public function __construct(array $config = null)
    {
        if (!class_exists('Redis')) {
            show_error('The Redis PECL extension has not been installed or enabled', 500);
        }

        if (false != $config) {
            return $this->initialize($config);
        }
    }

    public function __destruct()
    {
        if (is_object($this->redis)) {
            $this->redis->close();
            $this->redis = null;
        }
    }

    public function initialize(array $config)
    {
        foreach ($config as $key => $val) {
            if (isset($this->$key)) {
                $method = 'set_' . $key;
                if (method_exists($this, $method)) {
                    $this->$method($val);
                } else {
                    $this->$key = $val;
                }
            }
        }

        if (empty($this->host)) {
            show_error('The Host must be set to connect to Redis', 500);
        } elseif (false == ($infos = parse_url($this->host))) {
            show_error('The Host string not valid, you can use tcp://127.0.0.1:6379 OR unix:/tmp/redis.sock', 500);
        }
        $this->redis = new Redis();
        if ('unix' == $infos['scheme']) {
            $this->host = $infos['path'];
            $conn = $this->redis->pconnect($infos['path'], null, $this->timeout);
        } else {
            $this->host = $infos['host'];
            $conn = $this->redis->pconnect($infos['host'], isset($infos['port']) ? $infos['port'] : 6379, $this->timeout);
        }
        unset($infos);
        if (false === $conn) {
            $msg = sprintf('Unable to connect to Redis on %s', $this->host);
            if ($this->debug) {
                show_error($msg, 500);
            }
            log_message('error', $msg);

            return false;
        }
        if (false != $this->passwd && false === $this->redis->auth($this->passwd)) {
            $this->redis->close();

            $msg = sprintf('Unable to AUTH on Redis use %s', $this->passwd);
            if ($this->debug) {
                show_error($msg, 500);
            }
            log_message('error', $msg);

            return false;
        }
        if (false !== $this->dbid && false === $this->redis->select($this->dbid)) {
            $this->redis->close();

            $msg = sprintf('Unable to select Redis db %d', $this->dbid);
            if ($this->debug) {
                show_error($msg, 500);
            }
            log_message('error', $msg);

            return false;
        }

        return true;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array(array(&$this->redis, $name), $arguments);
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CI_Session_memcache_driver extends CI_Session_driver implements SessionHandlerInterface
{
    protected $_memcache = null;
    protected $_lock_key = null;
    protected $_key_prefix = 'ci_session:';

    public function __construct(&$params)
    {
        parent::__construct($params);
        if (empty($this->_config['save_path'])) {
            log_message('error', 'Session: No Memcached save path configured.');
        }
        if ($this->_config['match_ip'] === true) {
            $this->_key_prefix .= $_SERVER['REMOTE_ADDR'] . ':';
        }
    }

    public function open($save_path, $name)
    {
        if (false == preg_match_all('#,?([^,:]+)\:(\d{1,5})(?:\:(\d+))?#', $this->_config['save_path'], $matches, PREG_SET_ORDER)) {
            log_message('error', 'Session: Invalid Memcached save path format: ' . $this->_config['save_path']);

            return $this->_fail();
        }
        $this->_memcache = new Memcache();
        foreach ($matches as $match) {
            if (false === $this->_memcache->addServer($match[1], $match[2], true, isset($match[3]) ? $match[3] : 1)) {
                log_message('error', 'Could not add ' . $match[1] . ':' . $match[2] . ' to Memcached server pool.');
            }
        }
        $this->_memcache->setCompressThreshold(20000, 0.2);

        return $this->_success;
    }

    public function read($session_id)
    {
        if (isset($this->_memcache) && $this->_get_lock($session_id)) {
            $this->_session_id = $session_id;

            $session_data = (string)$this->_memcache->get($this->_key_prefix . $session_id);

            return $session_data;
        }

        return $this->_fail();
    }

    public function write($session_id, $session_data)
    {
        if (!isset($this->_memcache)) {
            return $this->_fail();
        } elseif ($session_id !== $this->_session_id) {
            if (false === $this->_release_lock() OR false === $this->_get_lock($session_id)) {
                return $this->_fail();
            }
            $this->_session_id = $session_id;
        }

        if (isset($this->_lock_key)) {
            $this->_memcache->replace($this->_lock_key, time(), 0, 300);
            if ($this->_memcache->set($this->_key_prefix . $session_id, $session_data, 0, $this->_config['expiration'])) {
                return $this->_success;
            }
        }

        return $this->_fail();
    }

    public function close()
    {
        if (isset($this->_memcache)) {
            isset($this->_lock_key) && $this->_memcache->delete($this->_lock_key);
            if (false === $this->_memcache->close()) {
                return $this->_fail();
            }
            $this->_memcache = null;

            return $this->_success;
        }

        return $this->_fail();
    }

    public function destroy($session_id)
    {
        if (isset($this->_memcache, $this->_lock_key)) {
            $this->_memcache->delete($this->_key_prefix . $session_id);
            $this->_cookie_destroy();

            return $this->_success;
        }

        return $this->_fail();
    }

    public function gc($maxlifetime)
    {
        return $this->_success;
    }

    protected function _get_lock($session_id)
    {
        if (isset($this->_lock_key)) {
            return $this->_memcache->replace($this->_lock_key, time(), 0, 300);
        }
        $lock_key = $this->_key_prefix . $session_id . ':lock';
        $attempt = 0;
        do {
            if ($this->_memcache->get($lock_key)) {
                sleep(1);
                continue;
            }
            if (false === $this->_memcache->set($lock_key, time(), 0, 300)) {
                log_message('error', 'Session: Error while trying to obtain lock for ' . $this->_key_prefix . $session_id);

                return false;
            }
            $this->_lock_key = $lock_key;
            break;
        } while ($attempt++ < 30);

        if ($attempt === 30) {
            log_message('error', 'Session: Unable to obtain lock for ' . $this->_key_prefix . $session_id . ' after 30 attempts, aborting.');

            return false;
        }
        $this->_lock = true;

        return true;
    }

    protected function _release_lock()
    {
        if (isset($this->_memcache, $this->_lock_key) && $this->_lock) {
            if (false === $this->_memcache->delete($this->_lock_key)) {
                log_message('error', 'Session: Error while trying to free lock for ' . $this->_lock_key);

                return false;
            }
            $this->_lock_key = null;
            $this->_lock = false;
        }

        return true;
    }
}

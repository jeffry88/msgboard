<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CI_Session_wincache_driver extends CI_Session_driver implements SessionHandlerInterface
{
    protected $_wincache = null;
    protected $_lock_key = null;
    protected $_key_prefix = 'ci_session:';

    public function __construct(&$params)
    {
        parent::__construct($params);
    }

    public function open($save_path, $name)
    {
        if (false === extension_loaded('wincache')) {
            log_message('error', 'Session: No Wincache supprot.');

            return $this->_fail();
        }
        $this->_wincache = true;

        return $this->_success;
    }

    public function read($session_id)
    {
        if (isset($this->_wincache) && $this->_get_lock($session_id)) {
            $this->_session_id = $session_id;

            return (string)wincache_ucache_get($this->_key_prefix . $session_id);
        }

        return $this->_fail();
    }

    public function write($session_id, $session_data)
    {
        if (!isset($this->_wincache)) {
            return $this->_fail();
        } elseif ($session_id !== $this->_session_id) {
            if (false === $this->_release_lock() OR false === $this->_get_lock($session_id)) {
                return $this->_fail();
            }
            $this->_session_id = $session_id;
        }

        if (isset($this->_lock_key)) {
            wincache_ucache_set($this->_lock_key, time(), 300);
            if (wincache_ucache_set($this->_key_prefix . $session_id, $session_data, $this->_config['expiration'])) {
                return $this->_success;
            }
        }

        return $this->_fail();
    }

    public function close()
    {
        if (isset($this->_wincache)) {
            isset($this->_lock_key) && wincache_ucache_delete($this->_lock_key);
            $this->_wincache = null;

            return $this->_success;
        }

        return $this->_fail();
    }

    public function destroy($session_id)
    {
        if (isset($this->_wincache, $this->_lock_key)) {
            wincache_ucache_delete($this->_key_prefix . $session_id);
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
            return wincache_ucache_set($this->_lock_key, time(), 300);
        }
        $lock_key = $this->_key_prefix . $session_id . ':lock';
        $attempt = 0;
        do {
            if (wincache_ucache_get($lock_key)) {
                sleep(1);
                continue;
            }
            if (false === wincache_ucache_set($lock_key, time(), 300)) {
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
        if (isset($this->_wincache, $this->_lock_key) && $this->_lock) {
            if (false === wincache_ucache_delete($this->_lock_key)) {
                log_message('error', 'Session: Error while trying to free lock for ' . $this->_lock_key);

                return false;
            }
            $this->_lock_key = null;
            $this->_lock = false;
        }

        return true;
    }
}

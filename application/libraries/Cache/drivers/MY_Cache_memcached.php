<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Cache_memcached extends CI_Cache_memcached
{
    protected $_memcache_conf = array(
        'default' => array(
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 1,
            'persistent_id' => null,
        )
    );

    protected function _setup_memcached()
    {
        $CI =& get_instance();
        $defaults = $this->_memcache_conf['default'];

        if ($CI->config->load('memcached', true, true)) {
            if (is_array($CI->config->config['memcached'])) {
                $this->_memcache_conf = array();
                foreach ($CI->config->config['memcached'] as $name => $conf) {
                    $this->_memcache_conf[$name] = $conf;
                }
            }
        }

        $connected = false;
        if (class_exists('Memcached', false)) {
            $this->_memcached = new Memcached($this->_memcache_conf['persistent_id']);
            $connected = 0 == count($this->_memcached->getServerList()) ? false : true;
        } elseif (class_exists('Memcache', false)) {
            $this->_memcached = new Memcache();
        } else {
            log_message('error', 'Failed to create object for Memcached Cache; extension not loaded?');

            return false;
        }
        if (false === $connected) {
            foreach ($this->_memcache_conf as $cache_server) {
                isset($cache_server['host']) OR $cache_server['host'] = $defaults['host'];
                isset($cache_server['port']) OR $cache_server['port'] = $defaults['port'];
                isset($cache_server['weight']) OR $cache_server['weight'] = $defaults['weight'];

                if (get_class($this->_memcached) === 'Memcache') {
                    $this->_memcached->addServer(
                        $cache_server['host'],
                        $cache_server['port'],
                        true,
                        $cache_server['weight']
                    );
                } else {
                    $this->_memcached->addServer(
                        $cache_server['host'],
                        $cache_server['port'],
                        $cache_server['weight']
                    );
                }
            }
        }

        return true;
    }

    /**
     * Fetch from cache
     *
     * @param   string $id Cache ID
     * @return  mixed   Data on success, FALSE on failure
     */
    public function get($id)
    {
        return $this->_memcached->get($id);
    }

    /**
     * Save
     *
     * @param   string $id Cache ID
     * @param   mixed  $data Data being cached
     * @param   int    $ttl Time to live
     * @param   bool   $raw Whether to store the raw value(unused)
     * @return  bool    TRUE on success, FALSE on failure
     */
    public function save($id, $data, $ttl = 60, $raw = false)
    {
        if ($this->_memcached instanceof Memcached) {
            return $this->_memcached->set($id, $data, $ttl);
        }
        if ($this->_memcached instanceof Memcache) {
            return $this->_memcached->set($id, $data, 0, $ttl);
        }

        return false;
    }
}

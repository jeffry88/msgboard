<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Memcached settings
| -------------------------------------------------------------------------
| Your Memcached servers can be specified below.
|
|	See: http://codeigniter.com/user_guide/libraries/caching.html#memcached
|
*/
$config = array(
    'default' => array(
        'hostname' => 'mem.cached.ispeak.cn',
        'port' => 11211,
        'weight' => 1,
    ),
);

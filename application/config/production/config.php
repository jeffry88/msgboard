<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['encryption_key'] = '';
$config['sess_driver'] = 'memcached';
$config['sess_cookie_name'] = 'ISSESSID';
$config['sess_expiration'] = 1800;
$config['sess_save_path'] = 'mem.cached.ispeak.cn:11211';
$config['sess_match_ip'] = true;
$config['sess_time_to_update'] = 300;
$config['sess_regenerate_destroy'] = false;

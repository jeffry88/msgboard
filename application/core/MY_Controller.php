<?php
defined('BASEPATH') OR exit('No direct script access allowed');
date_default_timezone_set('Etc/GMT-8');
define('TIMESTAMP', isset($_SERVER['REQUEST_TIME']) ? floatval($_SERVER['REQUEST_TIME']) : time());

class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function urlsafe_base64_encode($str, $encoded = false)
    {
        return strtr(false === $encoded ? base64_encode($str) : $str, '+/', '-_');
    }

    protected function urlsafe_base64_decode($str)
    {
        return base64_decode(strtr($str, '-_', '+/'));
    }

    //$operator = <、 lt、<=、 le、>、 gt、>=、 ge、==、 =、eq、 !=、<>、 ne
    protected function datetime_compare($date1, $date2, $operator = false)
    {
        try {
            $d1 = ($date1 instanceof DateTime) ? $date1 : (new DateTime($date1));
        } catch (Exception $e) {
            return false;
        }
        try {
            $d2 = ($date2 instanceof DateTime) ? $date2 : (new DateTime($date2));
        } catch (Exception $e) {
            return false;
        }
        switch ($operator) {
            case '<':
            case 'lt':
                return $d1 < $d2;
                break;
            case '<=':
            case 'le':
                return $d1 <= $d2;
                break;
            case '>':
            case 'gt':
                return $d1 > $d2;
                break;
            case '>=':
            case 'ge':
                return $d1 >= $d2;
                break;
            case '==':
            case '=':
            case 'eq':
                return $d1 == $d2;
                break;
            case '!=':
            case '<>':
            case 'ne':
                return $d1 != $d2;
                break;
            default:
                return $d1 == $d2 ? 0 : ($d1 > $d2 ? 1 : -1);
        }
    }

    protected function socket($url, $params = false, $method = 'GET', $cctimeout = 5, $timeout = 15)
    {
        if (false === ($urlinfo = @parse_url($url))) {
            return false;
        }
        if (!isset($urlinfo['host'])) {
            return false;
        }
        $host = sprintf('%s://%s',
            in_array($urlinfo['scheme'], array('tcp', 'udp', 'ssl', 'tls')) ? $urlinfo['scheme'] : ('https' == $urlinfo['scheme'] ? 'ssl' : 'tcp'),
            //FALSE != preg_match('/^\d+\.\d+.\d+.\d+$/', $urlinfo['host']) ? $urlinfo['host'] : gethostbyname($urlinfo['host'])
            $urlinfo['host']
        );
        if (!isset($urlinfo['port'])) {
            $urlinfo['port'] = in_array($urlinfo['scheme'], array('ssl', 'tls', 'https')) ? 443 : 80;
        }
        if (!isset($urlinfo['path'])) {
            $urlinfo['path'] = '/';
        }
        if (!isset($urlinfo['query'])) {
            $urlinfo['query'] = '';
        }
        if (function_exists('stream_socket_client')) {
            $fp = @stream_socket_client(sprintf('%s:%d', $host, $urlinfo['port']), $errno, $error, $cctimeout);
        } elseif (function_exists('fsockopen')) {
            $fp = @fsockopen($host, $urlinfo['port'], $errno, $error, $cctimeout);
        } else {
            return false;
        }
        if (false === $fp) {
            return false;
        }
        stream_set_timeout($fp, $timeout);
        stream_set_blocking($fp, 0);

        $body = '';
        $urlinfo['path'] = sprintf('%s?%s', $urlinfo['path'], $urlinfo['query']);
        if (false != $params) {
            $body = is_string($params) ? $params : http_build_query($params);
            if ('GET' == $method) {
                $urlinfo['path'] = sprintf('%s&%s', $urlinfo['path'], $body);
                $body = '';
            }
        }

        $data = array(
            sprintf('%s %s HTTP/1.1', $method, $urlinfo['path']),
            sprintf('Host: %s', $urlinfo['host']),
            'User-Agent: Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1)',
            'Content-Type: application/x-www-form-urlencoded',
            sprintf('Content-Length: %d', strlen($body)),
            "Connection: close\r\n",
            $body,
        );
        if (false === fwrite($fp, implode("\r\n", $data))) {
            fclose($fp);

            return false;
        }
        unset($data);

        //short wait for remote server transport
        usleep(30000);
        fclose($fp);

        return true;
    }

    protected function generateQueryPager($baseURL, $total, $limit, array $extend = null)
    {
        $params = $this->input->get();
        unset($params['page']);
        $query = http_build_query($params);
        unset($params);
        $this->load->library('pagination');
        $config = array(
            'base_url' => $baseURL . '?',
            'total_rows' => $total,
            'per_page' => $limit,
            'suffix' => '&amp;' . $query,
            'num_links' => 7,
            'uri_segment' => null,
            'use_page_numbers' => true,
            'page_query_string' => true,
            'query_string_segment' => 'page',
            'full_tag_open' => '<ul class="pagination"><li><span>记录数：' . $total . '</span></li>',
            'full_tag_close' => '</ul>',
            'first_link' => '&lt;&lt;',
            'last_link' => '&gt;&gt;',
            'first_tag_open' => '<li>',
            'first_tag_close' => '</li>',
            'last_tag_open' => '<li>',
            'last_tag_close' => '</li>',
            'next_tag_open' => '<li>',
            'next_tag_close' => '</li>',
            'prev_tag_open' => '<li>',
            'prev_tag_close' => '</li>',
            'num_tag_open' => '<li>',
            'num_tag_close' => '</li>',
            'cur_tag_open' => '<li class="active"><span>',
            'cur_tag_close' => '</span></li>',
        );
        $config['first_url'] = $config['base_url'] . $config['suffix'];
        if (!is_null($extend) && 0 < count($extend)) {
            $config = array_merge($config, $extend);
        }
        $this->pagination->initialize($config);

        return $this->pagination->create_links();
    }

    protected function create_pager($base_url, $total, $limit, $segment = 3, array $extend = NULL)
    {
        $this->load->library('pagination');
        $config = array(
            'base_url' => $base_url,
            'total_rows' => $total,
            'per_page' => $limit,
            'num_links' => 7,
            'uri_segment' => $segment,
            'use_page_numbers' => TRUE,
            'full_tag_open' => '<li><span>记录数：' . $total . '</span></li>',
            'full_tag_close' => '',
            'first_link' => '&lt;&lt;',
            'last_link' => '&gt;&gt;',
            'first_tag_open' => '<li>',
            'first_tag_close' => '</li>',
            'last_tag_open' => '<li>',
            'last_tag_close' => '</li>',
            'next_tag_open' => '<li>',
            'next_tag_close' => '</li>',
            'prev_tag_open' => '<li>',
            'prev_tag_close' => '</li>',
            'num_tag_open' => '<li>',
            'num_tag_close' => '</li>',
            'cur_tag_open' => '<li class="active"><span>',
            'cur_tag_close' => '</span></li>'
        );
        if ( ! is_null($extend) && 0 < count($extend))
        {
            $config = array_merge($config, $extend);
        }
        $this->pagination->initialize($config);

        return $this->pagination->create_links();
    }

    protected function create_query_pager($base_url, $total, $limit, array $extend = NULL)
    {
        $params = $this->input->get();
        unset($params['page']);
        $query = http_build_query($params);
        unset($params);
        $this->load->library('pagination');
        $config = array(
            'base_url' => $base_url . '?',
            'total_rows' => $total,
            'per_page' => $limit,
            'suffix' => '&amp;' . $query,
            'num_links' => 7,
            'uri_segment' => NULL,
            'use_page_numbers' => TRUE,
            'page_query_string' => TRUE,
            'query_string_segment' => 'page',
            'full_tag_open' => '<li><span>记录数：' . $total . '</span></li>',
            'full_tag_close' => '',
            'first_link' => '&lt;&lt;',
            'last_link' => '&gt;&gt;',
            'first_tag_open' => '<li>',
            'first_tag_close' => '</li>',
            'last_tag_open' => '<li>',
            'last_tag_close' => '</li>',
            'next_tag_open' => '<li>',
            'next_tag_close' => '</li>',
            'prev_tag_open' => '<li>',
            'prev_tag_close' => '</li>',
            'num_tag_open' => '<li>',
            'num_tag_close' => '</li>',
            'cur_tag_open' => '<li class="active"><span>',
            'cur_tag_close' => '</span></li>'
        );
        $config['first_url'] = $config['base_url'] . $config['suffix'];
        if ( ! is_null($extend) && 0 < count($extend))
        {
            $config = array_merge($config, $extend);
        }
        $this->pagination->initialize($config);

        return $this->pagination->create_links();
    }

}

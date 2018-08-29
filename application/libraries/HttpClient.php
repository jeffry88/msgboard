<?php
/**
 *
 * @copyright    Copyright (c) 2004 - 2018, Qinhe Co.,Ltd. (http://www.ispeak.cn/)
 * @since        2018/4/13
 * @author       iSpeak Dev Team <Fair>
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class HttpClient
{
    private $threads;
    private $defopts;
    private $curlopts;
    private $tasks;

    private $isMultiExec;
    private $multiParams;

    public function __construct()
    {
        $this->defopts = array(
            CURLOPT_FAILONERROR => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'HttpClient/1.0 (' . PHP_OS . ' ' . PHP_VERSION_ID . ')',
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_DNS_CACHE_TIMEOUT => 3600,
            CURLOPT_HTTPHEADER => array(
                'Expect:',
            ),
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        );
        $this->curlopts = $this->defopts;

        $this->tasks = array();
        $this->threads = 10;
        $this->isMultiExec = false;
        $this->multiParams = array();
    }

    public function __destruct()
    {
        $this->defopts = null;
        $this->curlopts = null;

        foreach ($this->tasks as $ch) {
            curl_close($ch);
        }
        $this->tasks = null;
        $this->multiParams = null;
    }

    public function setThreads($num)
    {
        $this->threads = $num;

        return $this;
    }

    public function setUserAgent($ua)
    {
        $this->curlopts[CURLOPT_USERAGENT] = $ua;

        return $this;
    }

    public function setConnectTimeout($timeout)
    {
        if (is_float($timeout)) {
            $this->curlopts[CURLOPT_CONNECTTIMEOUT_MS] = $timeout * 1000;
        } else {
            $this->curlopts[CURLOPT_CONNECTTIMEOUT] = $timeout;
        }

        return $this;
    }

    public function setExecuteTimeout($timeout)
    {
        if (is_float($timeout)) {
            $this->curlopts[CURLOPT_TIMEOUT_MS] = $timeout * 1000;
        } else {
            $this->curlopts[CURLOPT_TIMEOUT] = $timeout;
        }

        return $this;
    }

    public function addRequestHeader($header)
    {
        if (is_array($header)) {
            $this->curlopts[CURLOPT_HTTPHEADER] = array_merge($this->curlopts[CURLOPT_HTTPHEADER], $header);
        } else {
            $this->curlopts[CURLOPT_HTTPHEADER][] = $header;
        }

        return $this;
    }

    public function setRequestProxy($addr, $type = CURLPROXY_HTTP)
    {
        return $this->setRequestOptions(array(
            CURLOPT_PROXYTYPE => $type,
            CURLOPT_PROXY => $addr,
        ));
    }

    /**
     * @param array $options curl_setopt array
     * @return HttpClient
     */
    public function setRequestOptions(array $options)
    {
        $this->curlopts = $options + $this->curlopts;

        return $this;
    }

    /**
     * @param int   $option curl_setopt
     * @param mixed $value
     * @return HttpClient
     */
    public function setRequestOption($option, $value)
    {
        return $this->setRequestOptions(array($option => $value));
    }

    public function resetRequestOptions()
    {
        $this->curlopts = $this->defopts;

        return $this;
    }

    /**
     *
     * @param string            $url
     * @param string|array|null $params request arguments
     * @return bool|object
     * @throws Exception
     */
    public function get($url, $params = null)
    {
        if (null !== $params) {
            $args = is_string($params) ? $params : http_build_query($params);
            $url = http_build_url($url, array('query' => $args), HTTP_URL_JOIN_QUERY);
        }

        return $this->request($url);
    }

    /**
     *
     * @param string            $url
     * @param string|array|null $params request arguments
     * @param string            $method
     * @return HttpClient|stdClass
     * @throws Exception
     */
    public function request($url, $params = null, $method = 'GET')
    {
        if (false === ($ch = curl_init())) {
            throw new \Exception('CURL library init fail');
        }

        curl_setopt_array($ch, $this->curlopts);
        if (defined('CURLOPT_SAFE_UPLOAD')) {
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (null !== $params) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        if (true === $this->isMultiExec) {
            $tid = intval($ch);
            $this->tasks[$tid] = $ch;
            $this->multiParams[$tid] = $params;

            return $this;
        }

        $headers = $this->curlopts[CURLOPT_HTTPHEADER];
        $headers[] = 'Connection: close';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $res = new stdClass();
        $res->data = curl_exec($ch);
        $res->errno = curl_errno($ch);
        $res->error = curl_error($ch);
        $res->status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $res->url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $res->isSuccess = (bool)(200 <= $res->status && 300 > $res->status);
        $res->totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $res->lookupTime = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
        $res->connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        $res->params = $params;
        curl_close($ch);

        return $res;
    }

    /**
     * @param string $fieldname
     * @param string $filename
     * @param string $mimetype
     * @param string $postname
     * @return array
     */
    public function addFile($fieldname, $filename, $mimetype = 'application/octet-stream', $postname = null)
    {
        if (empty($postname)) {
            $postname = basename($filename);
        }
        if (class_exists('CURLFile')) {
            $cf = new \CURLFile($filename, $mimetype, $postname);
        } else {
            $fieldname = "{$fieldname}\"; filename=\"{$postname}\"\r\nContent-Type: {$mimetype}\r\n";
            $cf = '@' . $filename;
        }

        return array($fieldname => $cf);
    }

    /**
     *
     * @param string            $url
     * @param string|array|null $params request arguments
     * @return HttpClient|stdClass
     * @throws Exception
     */
    public function post($url, $params = null)
    {
        return $this->request($url, $params, 'POST');
    }

    /**
     *
     * @param string            $url
     * @param string|array|null $params request arguments
     * @return HttpClient|stdClass
     * @throws Exception
     */
    public function put($url, $params = null)
    {
        return $this->request($url, $params, 'PUT');
    }

    /**
     *
     * @param string            $url
     * @param string|array|null $params request arguments
     * @return HttpClient|stdClass
     * @throws Exception
     */
    public function patch($url, $params = null)
    {
        return $this->request($url, $params, 'PATCH');
    }

    /**
     *
     * @param string            $url
     * @param string|array|null $params request arguments
     * @return HttpClient|stdClass
     * @throws Exception
     */
    public function delete($url, $params = null)
    {
        return $this->request($url, $params, 'DELETE');
    }

    public function multi()
    {
        $this->isMultiExec = true;

        return $this;
    }

    /**
     * 当请求的URL主机名相同时，可以设置 CURLOPT_HTTP_VERSION 为 2或3，即 HTTP/1.1 或 HTTP/2.0
     * @return array
     * @throws Exception
     */
    public function exec()
    {
        if (false === $this->isMultiExec) {
            throw new \Exception('exec method only can be called after multi called');
        }
        $__tc = count($this->tasks);
        if (0 === $__tc) {
            throw new \Exception('request task is empty');
        }

        if (false === ($mch = curl_multi_init())) {
            throw new \Exception('CURL library init fail');
        }

        $max = min($__tc, $this->threads);
        for ($i = 0; $i < $max; $i++) {
            $ch = array_pop($this->tasks);
            curl_multi_add_handle($mch, $ch);
        }

        $ret = array();
        $running = null;
        do {
            curl_multi_exec($mch, $running);
            if (-1 === curl_multi_select($mch)) {
                usleep(100);
            }

            while ($returnInfo = curl_multi_info_read($mch, $queneNumber)) {
                $ch = $returnInfo['handle'];
                $tid = intval($ch);

                $res = new stdClass();
                $res->data = curl_multi_getcontent($ch);
                $res->errno = curl_errno($ch);
                $res->error = curl_error($ch);
                $res->status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $res->url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $res->isSuccess = (bool)(200 <= $res->status && 300 > $res->status);
                $res->totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
                $res->lookupTime = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
                $res->connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
                $res->params = $this->multiParams[$tid];
                curl_close($ch);
                curl_multi_remove_handle($mch, $ch);

                $ret[$tid] = $res;

                $__tc = count($this->tasks);
                for ($i = 0, $j = min($__tc, $max - $queneNumber); $i < $j; $i++) {
                    $ch = array_pop($this->tasks);
                    curl_multi_add_handle($mch, $ch);
                }
            }
        } while ($running);
        curl_multi_close($mch);
        $this->isMultiExec = false;
        $this->multiParams = array();

        ksort($ret);
        return array_values($ret);
    }
}

if (!function_exists('http_build_url')) {
    define('HTTP_URL_REPLACE', 1);
    define('HTTP_URL_JOIN_PATH', 2);
    define('HTTP_URL_JOIN_QUERY', 4);
    define('HTTP_URL_STRIP_USER', 8);
    define('HTTP_URL_STRIP_PASS', 16);
    define('HTTP_URL_STRIP_AUTH', 32);
    define('HTTP_URL_STRIP_PORT', 64);
    define('HTTP_URL_STRIP_PATH', 128);
    define('HTTP_URL_STRIP_QUERY', 256);
    define('HTTP_URL_STRIP_FRAGMENT', 512);
    define('HTTP_URL_STRIP_ALL', 1024);
    function http_build_url($url, $parts = array(), $flags = HTTP_URL_REPLACE, &$new_url = false)
    {
        $keys = array('user', 'pass', 'port', 'path', 'query', 'fragment');
        if ($flags & HTTP_URL_STRIP_ALL) {
            $flags |= HTTP_URL_STRIP_USER;
            $flags |= HTTP_URL_STRIP_PASS;
            $flags |= HTTP_URL_STRIP_PORT;
            $flags |= HTTP_URL_STRIP_PATH;
            $flags |= HTTP_URL_STRIP_QUERY;
            $flags |= HTTP_URL_STRIP_FRAGMENT;
        } elseif ($flags & HTTP_URL_STRIP_AUTH) {
            $flags |= HTTP_URL_STRIP_USER;
            $flags |= HTTP_URL_STRIP_PASS;
        }

        $parse_url = parse_url($url);
        if (isset($parts['scheme'])) {
            $parse_url['scheme'] = $parts['scheme'];
        }
        if (isset($parts['host'])) {
            $parse_url['host'] = $parts['host'];
        }
        if ($flags & HTTP_URL_REPLACE) {
            foreach ($keys as $key) {
                if (isset($parts[$key])) {
                    $parse_url[$key] = $parts[$key];
                }
            }
        } else {
            if (isset($parts['path']) && ($flags & HTTP_URL_JOIN_PATH)) {
                if (isset($parse_url['path'])) {
                    $parse_url['path'] = rtrim(str_replace(basename($parse_url['path']), '', $parse_url['path']), '/') . '/' . ltrim($parts['path'], '/');
                } else {
                    $parse_url['path'] = $parts['path'];
                }
            }

            if (isset($parts['query']) && ($flags & HTTP_URL_JOIN_QUERY)) {
                if (isset($parse_url['query'])) {
                    $parse_url['query'] .= '&' . $parts['query'];
                } else {
                    $parse_url['query'] = $parts['query'];
                }
            }
        }
        foreach ($keys as $key) {
            if ($flags & (int)constant('HTTP_URL_STRIP_' . strtoupper($key))) {
                unset($parse_url[$key]);
            }
        }

        $new_url = $parse_url;

        return ((isset($parse_url['scheme'])) ? $parse_url['scheme'] . '://' : '')
            . ((isset($parse_url['user'])) ? $parse_url['user'] . ((isset($parse_url['pass'])) ? ':' . $parse_url['pass'] : '') . '@' : '')
            . ((isset($parse_url['host'])) ? $parse_url['host'] : '')
            . ((isset($parse_url['port'])) ? ':' . $parse_url['port'] : '')
            . ((isset($parse_url['path'])) ? $parse_url['path'] : '')
            . ((isset($parse_url['query'])) ? '?' . $parse_url['query'] : '')
            . ((isset($parse_url['fragment'])) ? '#' . $parse_url['fragment'] : '');
    }
}

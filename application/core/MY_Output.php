<?php
defined('BASEPATH') OR exit('No direct script access allowed');
defined('JSON_UNESCAPED_UNICODE') OR define('JSON_UNESCAPED_UNICODE', 256);

class MY_Output extends CI_Output
{
    public $xml_root_node = 'response';

    public function __construct()
    {
        parent::__construct();
        $this->parse_exec_vars = false;
    }

    /**
     * @param string|array|object $data
     * @param string              $format
     * @param int                 $status
     * @param bool                $log_response
     */
    public function response($data, $format = 'json', $status = 200, $log_response = true)
    {
        ob_clean();
        $CI = &get_instance();
        $rtt = $CI->benchmark->elapsed_time('total_execution_time_start', 'total_execution_time_end');
        $method = $CI->input->method(true);
        $msg = $method . ' /' . $CI->uri->uri_string() . "[{$rtt}]\n";
        switch ($method) {
            case 'POST':
                $ct = $_SERVER['HTTP_CONTENT_TYPE'];
                if (false !== stripos($ct, 'www-form-urlencoded') || false !== stripos($ct, 'multipart/form-data')) {
                    $msg .= json_encode($_POST, JSON_UNESCAPED_UNICODE);
                } else {
                    $msg .= $CI->input->raw_input_stream;
                }
                break;
            case 'PUT':
                $msg .= $CI->input->raw_input_stream;
                break;
            case 'GET':
            default:
                $msg .= json_encode($_GET, JSON_UNESCAPED_UNICODE);
        }
        if (true === $log_response) {
            $msg .= "\n" . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        log_message('error', $msg);
        set_status_header($status);
        $sips = explode('.', $_SERVER['SERVER_ADDR']);
        header(sprintf('X-Server: %03d%03d', $sips[0], $sips[3]));
        call_user_func(array(&$this, $format), $data);
        ob_end_flush();
    }

    /**
     * @param string|array|object $data
     */
    public function json($data)
    {
        header('Content-Type: application/json');
        exit(is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function jsonp($callback, $data)
    {
        header('Content-Type: application/javascript');
        exit(sprintf('%s(%s)', $callback, is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE)));
    }

    public function xml(array $data, $rootElement = 'xml')
    {
        header('Content-Type: text/xml');
        exit(xmlEncode($data, $rootElement));
    }

    public function plain($text)
    {
        header('Content-Type: text/plain');
        exit($text);
    }

    public function captcha($code, $width = 50, $height = 25)
    {
        if (function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor($width, $height);
        } else {
            $im = imagecreate($width, $height);
        }
        $r = array(225, 255, 255, 223);
        $g = array(225, 236, 237, 255);
        $b = array(225, 236, 166, 125);
        $key = mt_rand(0, 3);

        $bgcolor = imagecolorallocate($im, $r[$key], $g[$key], $b[$key]);
        $bcolor = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        $scolor = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));

        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $bgcolor);
        imagerectangle($im, 0, 0, $width - 1, $height - 1, $bcolor);

        for ($i = 0; $i < 10; $i++) {
            $fcolor = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagearc($im, mt_rand(-10, $width), mt_rand(-10, $height), mt_rand(30, 300), mt_rand(20, 200), 55, 44, $fcolor);
        }
        for ($i = 0; $i < 25; $i++) {
            $fcolor = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $fcolor);
        }

        $len = mb_strlen($code);
        $font_size = $width / $len;
        $y = $height - 5;
        for ($i = 0; $i < $len; $i++) {
            imagettftext($im, $font_size, mt_rand(-15, 45), $i * $font_size + 2, $y, $scolor, SYSDIR . '/fonts/texb.ttf', $code{$i});
        }

        ob_clean();
        header('Content-type: image/png');
        imagepng($im);
        imagedestroy($im);
    }
}

if (!function_exists('xmlEncode')) {
    function xmlEncode($mixed, $rootTagName = 'xml')
    {
        function __xmlParse($mixed, \DOMElement $domElement, \DOMDocument $domDocument)
        {
            if (is_object($mixed)) {
                $mixed = get_object_vars($mixed);
            }
            if (is_array($mixed)) {
                foreach ($mixed as $index => $mixedElement) {
                    if (is_int($index)) {
                        if ($index === 0) {
                            $node = $domElement;
                        } else {
                            $node = $domDocument->createElement($domElement->tagName);
                            $domElement->parentNode->appendChild($node);
                        }
                    } elseif ('@' === substr($index, 0, 1)) // is attribute
                    {
                        foreach ($mixedElement as $attr => $val) {
                            $domElement->setAttribute($attr, $val);
                        }
                        unset($mixed[$index]);
                        continue;
                    } else {
                        $node = $domDocument->createElement($index);
                        $domElement->appendChild($node);
                    }
                    __xmlParse($mixedElement, $node, $domDocument);
                }
            } else {
                $mixed = is_bool($mixed) ? ($mixed ? 'true' : 'false') : $mixed;
                $domElement->appendChild($domDocument->createTextNode($mixed));
            }

            return $domDocument->saveXML();
        }

        $domDocument = new \DOMDocument('1.0', 'utf-8');
        $domDocument->formatOutput = true;
        $domElement = $domDocument->createElement($rootTagName);
        $domDocument->appendChild($domElement);

        return __xmlParse($mixed, $domElement, $domDocument);
    }
}

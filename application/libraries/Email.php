<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CI_Email
{
    public $useragent = 'CodeIgniter';
    public $mailpath = '/usr/sbin/sendmail';    // Sendmail path
    public $protocol = 'mail';    // mail/sendmail/smtp
    public $smtp_host = '';        // SMTP Server.  Example: mail.earthlink.net
    public $smtp_user = '';        // SMTP Username
    public $smtp_pass = '';        // SMTP Password
    public $smtp_port = '25';        // SMTP Port
    public $smtp_timeout = 5;        // SMTP Timeout in seconds
    public $smtp_crypto = '';        // SMTP Encryption. Can be null, tls or ssl.
    public $mailtype = 'text';    // text/html  Defines email formatting
    public $charset = 'utf-8';    // Default char set: iso-8859-1 or us-ascii
    public $priority = '3';        // Default priority (1 - 5)
    public $debug = false;

    private $multipart = 'alternative';  // mixed/related/alternative. Correspondence: html-attach/attach-inbody/text|html
    private $safe_mode = false;
    private $smtp_auth = false;
    private $smtp_connect = null;
    private $protocols = array('mail', 'sendmail', 'smtp');
    private $headers = array();
    private $attachment = array();
    private $debug_msg = array();
    private $recipients = array();
    private $header = '';
    private $body = '';
    private $subject = '';
    private $boundary = '';

    /**
     * Constructor - Sets Email Preferences
     *
     * The constructor can be passed an array of config values
     */
    public function __construct($config = array())
    {
        if (count($config) > 0) {
            $this->initialize($config);
        } else {
            $this->smtp_auth = ($this->smtp_user == '' AND $this->smtp_pass == '') ? false : true;
            $this->safe_mode = ((boolean)@ini_get("safe_mode") === false) ? false : true;
        }
        $this->boundary = sprintf('----_Boundary_%s_%s', strtoupper(uniqid()), strtoupper(uniqid()));

        log_message('debug', "Email Class Initialized");
    }

    /**
     * Initialize preferences
     *
     * @access    public
     * @param    array
     * @return    void
     */
    public function initialize($config = array())
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

        $this->smtp_auth = ($this->smtp_user == '' AND $this->smtp_pass == '') ? false : true;
        $this->safe_mode = ((boolean)@ini_get("safe_mode") === false) ? false : true;

        return $this;
    }

    /**
     * Set FROM
     *
     * @access    public
     * @param    string
     * @param    string
     * @return    void
     */
    public function from($from, $name = '')
    {
        if (preg_match('/^(.+?) \<(.+?)\>$/', $from, $match)) {
            $from = $match[1];
            empty($name) && $name = $match[2];
        }
        if ($name != '') {
            $name = $this->encode($name);
        }
        $this->set_header('From', $name . ' <' . $from . '>');
        $this->set_header('Return-Path', ' <' . $from . '>');

        return $this;
    }

    /**
     * Set Reply-to
     *
     * @access    public
     * @param    string
     * @param    string
     * @return    void
     */
    public function reply_to($replyto, $name = '')
    {
        if (preg_match('/^(.+?) \<(.+?)\>$/', $replyto, $match)) {
            $replyto = $match['1'];
            empty($name) && $name = $match[2];
        }
        if ($name != '') {
            $name = $this->encode($name);
        }
        $this->set_header('Reply-To', $name . ' <' . $replyto . '>');

        return $this;
    }

    /**
     * Set Recipients
     *
     * @access    public
     * @param    string
     * @return    void
     */
    public function to($to)
    {
        $to = $this->str2array($to);
        $to = $this->clean_email($to);
        $this->set_header('To', implode(', ', $to));
        $this->recipients = $to;

        return $this;
    }

    /**
     * Set CC
     *
     * @access    public
     * @param    string
     * @return    void
     */
    public function cc($cc)
    {
        $cc = $this->str2array($cc);
        $cc = $this->clean_email($cc);
        $this->set_header('Cc', implode(', ', $cc));
        $this->recipients = array_merge($this->recipients, $cc);

        return $this;
    }

    /**
     * Set BCC
     *
     * @access    public
     * @param    string
     * @param    string
     * @return    void
     */
    public function bcc($bcc)
    {
        $bcc = $this->str2array($bcc);
        $bcc = $this->clean_email($cc);
        $this->set_header('Bcc', implode(', ', $bcc));
        $this->recipients = array_merge($this->recipients, $bcc);

        return $this;
    }

    /**
     * Set Email Subject
     *
     * @access    public
     * @param    string
     * @return    void
     */
    public function subject($subject)
    {
        $subject = $this->encode($subject);
        $this->set_header('Subject', $subject);

        return $this;
    }

    /**
     * Set Body
     *
     * @access    public
     * @param    string
     * @return    void
     */
    public function message($body)
    {
        $this->body = chunk_split(base64_encode($body));

        return $this;
    }

    /**
     * Assign file attachments
     *
     * @access    public
     * @param    string
     * @return    void
     */
    public function attach($file, $name = '')
    {
        if (!is_file($file)) {
            $this->set_error_message('lang:email_attachment_missing', $file);

            return $this;
        }
        if (!is_readable($file)) {
            $this->set_error_message('lang:email_attachment_unreadable', $file);

            return $this;
        }
        if ($name == '') {
            $name = basename($file);
        }
        $this->attachment[] = array(
            'file' => $file,
            'name' => $name,
            'mime' => $this->get_mime_type($file)
        );

        return $this;
    }

    /**
     * Clean Extended Email Address: Joe Smith <joe@smith.com>
     *
     * @access    public
     * @param    string
     * @return    string
     */
    public function clean_email($email)
    {
        if (!is_array($email)) {
            if (preg_match('/\<(.*)\>/', $email, $match)) {
                return $match['1'];
            } else {
                return $email;
            }
        }

        $clean_email = array();
        foreach ($email as $addy) {
            if (empty($addy)) {
                continue;
            }
            if (preg_match('/\<(.*)\>/', $addy, $match)) {
                $clean_email[] = $match['1'];
            } else {
                $clean_email[] = $addy;
            }
        }

        return $clean_email;
    }

    /**
     * Set Mailtype
     *
     * @access    public
     * @param    string
     * @return    void
     */
    public function set_mailtype($type = 'text')
    {
        $this->mailtype = ($type == 'html') ? 'html' : 'text';

        return $this;
    }

    /**
     * Set Protocol
     *
     * @access    public
     * @param    string
     * @return    void
     */
    public function set_protocol($protocol = 'mail')
    {
        $this->protocol = (!in_array($protocol, $this->protocols, true)) ? 'mail' : strtolower($protocol);

        return $this;
    }

    /**
     * Set Priority
     *
     * @access    public
     * @param    integer
     * @return    void
     */
    public function set_priority($n = 3)
    {
        if (!is_numeric($n)) {
            $n = 3;
        } elseif ($n < 1 OR $n > 5) {
            $n = 3;
        }
        $this->priority = $n;

        return $this;
    }

    /**
     * Send Email
     *
     * @access    public
     * @return    bool
     */
    public function send()
    {
        if (!isset($this->headers['To']) AND !isset($this->headers['Bcc']) AND isset($this->headers['Cc'])) {
            $this->set_error_message('lang:email_no_recipients');

            return false;
        }

        if (count($this->attachment) > 0) {
            $this->multipart = 'mixed';
        }

        $this->build_headers();
        $this->build_message();
        switch ($this->protocol) {
            case 'mail':
                return $this->send_with_mail();
                break;
            case 'smtp':
                return $this->send_with_smtp();
                break;
            case 'sendmail':
                return $this->send_with_sendmail();
                break;
        }
    }

    /**
     * Get Debug Message
     *
     * @access    public
     * @return    string
     */
    public function print_debugger()
    {
        $msg = '';
        if (true == $this->debug && false != $this->debug_msg) {
            $msg = implode("\r\n", $this->debug_msg);
        }
        $msg .= sprintf("<pre>%s\n%s\n%s</pre>", $this->header, $this->subject, $this->body);

        return $msg;
    }

    /**
     * Add a Header Item
     *
     * @access    private
     * @param    string
     * @param    string
     * @return    void
     */
    private function set_header($header, $value)
    {
        $this->headers[$header] = $value;
    }

    /**
     * Build final headers
     *
     * @access    private
     * @param    string
     * @return    string
     */
    private function build_headers()
    {
        /*if (count($this->attachment) > 0)
        {
            $this->multipart = 'mixed';
        }*/
        $this->set_header('X-Sender', $this->clean_email($this->headers['From']));
        $this->set_header('X-Mailer', $this->useragent);
        $this->set_header('X-Priority', $this->priority);
        $this->set_header('Message-ID', $this->get_message_id());
        $this->set_header('Mime-Version', '1.0');
        $this->set_header('Date', $this->get_date());
        $this->set_header('Content-Type', sprintf("multipart/%s;\r\n\tboundary=\"%s\"", $this->multipart, $this->boundary));

        $tmp = $this->headers;
        if ('mail' == $this->protocol) {
            unset($tmp['To'], $tmp['Subject']);
        }
        foreach ($tmp as $k => $v) {
            $tmp[$k] = sprintf('%s: %s', $k, $v);
        }
        $this->header = implode("\r\n", $tmp);
        unset($tmp);
    }

    /**
     * Build Final Body and attachments
     *
     * @access    private
     * @return    void
     */
    private function build_message()
    {
        //message body
        $tmp = array();
        $tmp[] = 'This is a multi-part message in ' . $this->mailtype . ' format.';
        $tmp[] = '';
        $tmp[] = '--' . $this->boundary;
        $tmp[] = sprintf('Content-Type: text/%s; charset=%s', $this->mailtype, $this->charset);
        $tmp[] = 'Content-Transfer-Encoding: base64';
        $tmp[] = '';
        $tmp[] = $this->body;

        //attachment
        if (count($this->attachment) > 0) {
            foreach ($this->attachment as $attach) {
                $filedata = file_get_contents($attach['file']);
                $filename = $this->encode($attach['name']);
                //$tmp[] = '';
                $tmp[] = '--' . $this->boundary;
                $tmp[] = sprintf('Content-Type: %s; name="%s"', $attach['mime'], $filename);
                $tmp[] = sprintf('Content-Description: %s', $filename);
                $tmp[] = sprintf('Content-Disposition: attachment; filename="%s"; size=%f', $filename, strlen($filedata));
                $tmp[] = 'Content-Transfer-Encoding: base64';
                $tmp[] = '';
                $tmp[] = chunk_split(base64_encode($filedata));
            }
        }
        $tmp[] = '--' . $this->boundary . '--';
        $this->body = implode("\r\n", $tmp);
        unset($tmp);
    }

    private function send_with_sendmail()
    {
        $fp = @popen($this->mailpath . ' -oi -f ' . $this->clean_email($this->headers['From']) . ' -t', 'w');
        if ($fp === false OR $fp === null) {
            return false;
        }

        fputs($fp, $this->header);
        fputs($fp, $this->body);
        $status = pclose($fp);

        if (version_compare(PHP_VERSION, '4.2.3') == -1) {
            $status = $status >> 8 & 0xFF;
        }

        if ($status != 0) {
            $this->set_error_message('lang:email_exit_status', $status);
            $this->set_error_message('lang:email_no_socket');

            return false;
        }

        return true;
    }

    private function send_with_mail()
    {
        if (true == $this->safe_mode) {
            return mail($this->headers['To'], $this->headers['Subject'], $this->body, $this->header);
        }

        return mail($this->headers['To'], $this->headers['Subject'], $this->body, $this->header, '-f ' . $this->clean_email($this->headers['From']));
    }

    private function send_with_smtp()
    {
        if ($this->smtp_host == '') {
            $this->set_error_message('lang:email_no_hostname');

            return false;
        }
        if (false === $this->smtp_connect()) {
            return false;
        }
        $this->smtp_command('from', 250, $this->clean_email($this->headers['From']));
        foreach ($this->recipients as $to) {
            $this->smtp_command('to', 250, $to);
        }
        $this->smtp_command('data', 354);
        $res = $this->smtp_command($this->header . "\r\n" . preg_replace('/^\./m', '..$1', $this->body) . "\r\n.", 250);
        $this->smtp_command('quit');

        return $res;
    }

    private function smtp_command($cmd, $code = 0, $data = '')
    {
        switch ($cmd) {
            case 'hello':
                if ($this->smtp_auth) {
                    fwrite($this->smtp_connect, 'EHLO ' . $_SERVER['HTTP_HOST'] . "\r\n");
                } else {
                    fwrite($this->smtp_connect, 'HELO ' . $_SERVER['HTTP_HOST'] . "\r\n");
                }
                break;
            case 'starttls':
                fwrite($this->smtp_connect, "STARTTLS\r\n");
                break;
            case 'auth':
                fwrite($this->smtp_connect, "AUTH LOGIN\r\n");
                break;
            case 'from':
                fwrite($this->smtp_connect, 'MAIL FROM:<' . $data . ">\r\n");
                break;
            case 'to':
                fwrite($this->smtp_connect, 'RCPT TO:<' . $data . ">\r\n");
                break;
            case 'data':
                fwrite($this->smtp_connect, "DATA\r\n");
                break;
            case 'quit':
                fwrite($this->smtp_connect, "QUIT\r\n");
                break;
            default:
                fwrite($this->smtp_connect, $cmd . $data . "\r\n");
        }

        $reply = $this->get_smtp_data();
        $this->debug_msg[] = '<pre>' . $cmd . ': ' . $reply . '</pre>';

        if ($code != 0 && substr($reply, 0, 3) != $code) {
            $this->set_error_message('lang:email_smtp_error', $reply);

            return false;
        }
        if ($cmd == 'quit') {
            fclose($this->smtp_connect);
        }

        return true;
    }

    private function smtp_connect()
    {
        $host = sprintf('%s://%s', in_array($this->smtp_crypto, array('tcp', 'udp', 'ssl', 'tls')) ? $this->smtp_crypto : 'tcp', $this->smtp_host);

        if (function_exists('fsockopen')) {
            $this->smtp_connect = @fsockopen($host, $this->smtp_port, $errno, $error, $this->smtp_timeout);
        } elseif (function_exists('stream_socket_client')) {
            $this->smtp_connect = @stream_socket_client($host . $this->smtp_port, $errno, $error, $this->smtp_timeout);
        } else {
            $this->set_error_message('lang:email_smtp_error', 'socket function not support');

            return false;
        }

        if (!is_resource($this->smtp_connect)) {
            $this->set_error_message('lang:email_smtp_error', $errno . ' ' . $error);

            return false;
        }
        stream_set_blocking($this->smtp_connect, 1);

        $reply = $this->get_smtp_data();
        $this->set_error_message($reply);
        if (substr($reply, 0, 3) != '220') {
            $this->set_error_message('lang:email_smtp_error', $reply);
            fclose($this->smtp_connect);

            return false;
        }

        $res = $this->smtp_command('hello', 250);
        if (false === $res) {
            fclose($this->smtp_connect);

            return false;
        }
        if ($this->smtp_crypto == 'tls') {
            $res = $this->smtp_command('starttls', 220);
            stream_socket_enable_crypto($this->smtp_connect, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
        if (false === $res) {
            fclose($this->smtp_connect);

            return false;
        }

        if (false === $this->smtp_auth) {
            return true;
        }
        if (false == $this->smtp_user) {
            $this->set_error_message('lang:email_no_smtp_unpw');
            fclose($this->smtp_connect);

            return false;
        }
        if (false === $this->smtp_command('auth', 334)) {
            fclose($this->smtp_connect);

            return false;
        }
        if (false === $this->smtp_command(base64_encode($this->smtp_user), 334)) {
            fclose($this->smtp_connect);

            return false;
        }
        if (false === $this->smtp_command(base64_encode($this->smtp_pass), 235)) {
            fclose($this->smtp_connect);

            return false;
        }

        return true;
    }

    private function get_smtp_data()
    {
        $data = '';
        while ($str = fgets($this->smtp_connect, 512)) {
            $data .= $str;
            if (substr($str, 3, 1) == ' ') {
                break;
            }
        }

        return $data;
    }

    /**
     * Get the Message ID
     *
     * @access    private
     * @return    string
     */
    private function get_message_id()
    {
        $from = $this->headers['Return-Path'];
        $from = str_replace('>', '', $from);
        $from = str_replace('<', '', $from);

        return '<' . uniqid() . strstr($from, '@') . '>';
    }

    /**
     * Get content type (text/html/attachment)
     *
     * @access    private
     * @return    string
     */
    private function get_content_type()
    {
        if ($this->mailtype == 'html') {
            return count($this->attachment) == 0 ? 'html' : 'html-attach';
        } else {
            return count($this->attachment) == 0 ? 'plain' : 'plain-attach';
        }
    }

    /**
     * Set RFC 822 Date
     *
     * @access    private
     * @return    string
     */
    private function get_date()
    {
        return date('r', TIMESTAMP);
    }

    private function str2array($str)
    {
        if (!is_array($str)) {
            if (strpos($str, ',') !== false) {
                $str = preg_split('/[\s,]/', $str, -1, PREG_SPLIT_NO_EMPTY);
            } else {
                $str = trim($str);
                settype($str, "array");
            }
        }

        return $str;
    }

    private function encode($str, $patch = true)
    {
        return true === $patch ? sprintf('=?%s?B?%s?=', $this->charset, base64_encode($str)) : base64_encode($str);
    }

    /**
     * Mime Types
     *
     * @access    private
     * @param    string
     * @return    string
     */
    private function get_mime_type($file)
    {
        if (!is_file($file)) {
            return '';
        }
        if (function_exists('mime_content_type')) {
            return mime_content_type($file);
        }
        $mimes = array(
            'hqx' => 'application/mac-binhex40',
            'cpt' => 'application/mac-compactpro',
            'doc' => 'application/msword',
            'bin' => 'application/macbinary',
            'dms' => 'application/octet-stream',
            'lha' => 'application/octet-stream',
            'lzh' => 'application/octet-stream',
            'exe' => 'application/octet-stream',
            'class' => 'application/octet-stream',
            'psd' => 'application/octet-stream',
            'so' => 'application/octet-stream',
            'sea' => 'application/octet-stream',
            'dll' => 'application/octet-stream',
            'oda' => 'application/oda',
            'pdf' => 'application/pdf',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'smi' => 'application/smil',
            'smil' => 'application/smil',
            'mif' => 'application/vnd.mif',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'wbxml' => 'application/vnd.wap.wbxml',
            'wmlc' => 'application/vnd.wap.wmlc',
            'dcr' => 'application/x-director',
            'dir' => 'application/x-director',
            'dxr' => 'application/x-director',
            'dvi' => 'application/x-dvi',
            'gtar' => 'application/x-gtar',
            'php' => 'application/x-httpd-php',
            'php4' => 'application/x-httpd-php',
            'php3' => 'application/x-httpd-php',
            'phtml' => 'application/x-httpd-php',
            'phps' => 'application/x-httpd-php-source',
            'js' => 'application/x-javascript',
            'swf' => 'application/x-shockwave-flash',
            'sit' => 'application/x-stuffit',
            'tar' => 'application/x-tar',
            'tgz' => 'application/x-tar',
            'xhtml' => 'application/xhtml+xml',
            'xht' => 'application/xhtml+xml',
            'zip' => 'application/zip',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mpga' => 'audio/mpeg',
            'mp2' => 'audio/mpeg',
            'mp3' => 'audio/mpeg',
            'aif' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'ram' => 'audio/x-pn-realaudio',
            'rm' => 'audio/x-pn-realaudio',
            'rpm' => 'audio/x-pn-realaudio-plugin',
            'ra' => 'audio/x-realaudio',
            'rv' => 'video/vnd.rn-realvideo',
            'wav' => 'audio/x-wav',
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'css' => 'text/css',
            'html' => 'text/html',
            'htm' => 'text/html',
            'shtml' => 'text/html',
            'txt' => 'text/plain',
            'text' => 'text/plain',
            'log' => 'text/plain',
            'rtx' => 'text/richtext',
            'rtf' => 'text/rtf',
            'xml' => 'text/xml',
            'xsl' => 'text/xml',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'movie' => 'video/x-sgi-movie',
            'doc' => 'application/msword',
            'word' => 'application/msword',
            'xl' => 'application/excel',
            'eml' => 'message/rfc822'
        );
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';
    }

    /**
     * Set Message
     *
     * @access    private
     * @param    string
     * @return    string
     */
    private function set_error_message($msg, $val = '')
    {
        if (false === $this->debug) {
            return;
        }
        $CI = &get_instance();
        $CI->lang->load('email');
        if (substr($msg, 0, 5) != 'lang:' || false === ($line = $CI->lang->line(substr($msg, 5)))) {
            $this->debug_msg[] = str_replace('%s', $val, $msg);
        } else {
            $this->debug_msg[] = str_replace('%s', $val, $line);
        }
    }
}

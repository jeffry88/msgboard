<?php
defined('BASEPATH') OR exit('No direct script access allowed');
!defined('UPLOAD_ERR_EXTENSION') && define('UPLOAD_ERR_EXTENSION', 8);

class CI_Upload
{
    public $error = null;

    public $use_mongo = false;
    public $xss_clean = false;
    public $overwrite = false;
    public $encrypt_name = false;
    public $hash_folder = false; //是否自动在上传目录中增加HASH目录
    public $hash_deep = 2; //HASH目录深度
    public $allowed_types = '*';
    public $max_size = 0;
    public $max_width = 0;
    public $max_height = 0;
    public $upload_path = '';
    public $customer_filename = false; //自定义文件名，不含扩展名

    public $dbname = '';
    public $bucket = 'fs';
    public $host = '127.0.0.1';
    public $port = 27017;
    public $user = '';
    public $pass = '';
    public $debug = false;

    private $gridfs = null;
    private $mimes = array();
    private $file_temp = '';
    private $file_name = '';
    private $client_name = '';
    private $file_type = '';
    private $file_size = '';
    private $file_ext = '';
    private $image_width = 0;
    private $image_height = 0;
    private $file_id = '';

    public function __construct($config = array())
    {
        empty($config) OR $this->initialize($config, false);
        $this->_mimes =& get_mimes();

        log_message('debug', "Upload Class Initialized");
    }

    public function initialize(array $config = array())
    {
        $reflection = new ReflectionClass($this);
        $defaults = $reflection->getDefaultProperties();
        foreach (array_keys($defaults) as $key) {
            if ($key[0] === '_') {
                continue;
            }

            if (isset($config[$key])) {
                if ($reflection->hasMethod('set_' . $key)) {
                    $this->{'set_' . $key}($config[$key]);
                } else {
                    $this->$key = $config[$key];
                }
            } else {
                $this->$key = $defaults[$key];
            }
        }

        if (true === $this->use_mongo) {
            if (!class_exists('MongoClient')) {
                show_error('The MongoDB PECL extension has not been installed or enabled', 500);
            }
            if (empty($this->host)) {
                show_error('The Host must be set to connect to MongoDB', 500);
            }
            if (empty($this->dbname)) {
                show_error('The Database must be set to connect to MongoDB', 500);
            }
            $server = sprintf('mongodb://%s%s', $this->host, isset($this->port) && !empty($this->port) ? (':' . $this->port) : '');
            $options = array(
                'db' => $this->dbname
            );
            !empty($this->user) && $options['username'] = $this->user;
            !empty($this->pass) && $options['password'] = $this->pass;
            try {
                $client = new MongoClient($server, $options);
                $this->gridfs = $client->selectDB($this->dbname)->getGridFS($this->bucket);

                return true;
            } catch (MongoConnectionException $mce) {
                if ($this->debug) {
                    show_error('Unable to connect to MongoDB: ' . $e->getMessage(), 500);
                }
                log_message('error', sprintf('Unable to connect to MongoDB: %s', $e->getMessage()));

                return false;
            } catch (Exception $e) {
                if ($this->debug) {
                    show_error('Unable to select MongoDB: ' . $e->getMessage(), 500);
                }
                log_message('error', sprintf('Unable to select MongoDB: %s', $e->getMessage()));

                return false;
            }
        }
    }

    public function do_upload($field = 'file', $chunk = 0)
    {
        if (false === $this->prepare_updata($field)) {
            return false;
        }
        if (false === $this->validate_upload_path()) {
            return false;
        }
        if (false === ($out = @fopen($this->upload_path . $this->file_name, $chunk == 0 ? 'wb' : 'ab'))) {
            $this->error = 'open_output_stream_failed';

            return false;
        }
        if (false === ($in = @fopen($this->file_temp, 'rb'))) {
            $this->error = 'open_input_stream_failed';

            return false;
        }
        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }
        fclose($out);
        fclose($in);
        @unlink($this->file_temp);

        return true;
    }

    public function save2mongo($field = 'file', $append = array())
    {
        if (false === $this->prepare_updata($field)) {
            return false;
        }
        try {
            $append = array_merge(array(
                'filename' => $this->file_name,
                'width' => $this->image_width,
                'height' => $this->image_height
            ), $append);
            $this->file_id = (string)$this->gridfs->storeFile($this->file_temp, $append);
            @unlink($this->file_temp);

            return true;
        } catch (MongoException $e) {
            $this->error = 'file_store_failed';
            log_message('error', $e->getMessage());

            return false;
        }
    }

    public function data()
    {
        return array(
            'file_id' => $this->file_id,
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'client_name' => $this->client_name,
            'file_ext' => $this->file_ext,
            'file_size' => $this->file_size,
            'full_path' => $this->upload_path . $this->file_name,
            'is_image' => $this->is_image(),
            'image_width' => $this->image_width,
            'image_height' => $this->image_height
        );
    }

    private function clean_file_name($filename)
    {
        $bad = array(
            '<!--',
            '-->',
            '\'',
            '<',
            '>',
            '"',
            '&',
            '$',
            '=',
            ';',
            '?',
            '/',
            '%20',
            '%22',
            '%3c',        // <
            '%253c',    // <
            '%3e',        // >
            '%0e',        // >
            '%28',        // (
            '%29',        // )
            '%2528',    // (
            '%26',        // &
            '%24',        // $
            '%3f',        // ?
            '%3b',        // ;
            '%3d'        // =
        );
        $filename = preg_replace('/\s+/', '', str_replace($bad, '', $filename));

        return stripslashes($filename);
    }

    public function set_max_size($n)
    {
        $this->max_size = ((int)$n < 0) ? 0 : (int)$n;
    }

    public function set_allowed_types($types)
    {
        if (!is_array($types) && $types == '*') {
            $this->allowed_types = '*';

            return;
        }
        $this->allowed_types = explode('|', $types);
    }

    private function set_filename()
    {
        if (true == $this->encrypt_name) {
            return md5(uniqid(mt_rand(), true)) . '.' . $this->file_ext;
        }

        return false != $this->customer_filename ? ($this->customer_filename . '.' . $this->file_ext) : $this->client_name;
    }

    private function is_allowed_filetype($ignore_mime = false)
    {
        if ($this->allowed_types == '*') {
            return true;
        }

        if (!is_array($this->allowed_types) || count($this->allowed_types) == 0) {
            return false;
        }

        if (!in_array($this->file_ext, $this->allowed_types)) {
            return false;
        }

        // Images get some additional checks
        $image_types = array('gif', 'jpg', 'jpeg', 'png', 'jpe');
        if (in_array($this->file_ext, $image_types)) {
            if (@getimagesize($this->file_temp) === false) {
                return false;
            }
        }

        if ($ignore_mime === true) {
            return true;
        }

        if (isset($this->_mimes[$this->file_ext])) {
            return is_array($this->_mimes[$this->file_ext])
                ? in_array($this->file_type, $this->_mimes[$this->file_ext], true)
                : ($this->_mimes[$this->file_ext] === $this->file_type);
        }

        return false;
    }

    private function is_allowed_filesize()
    {
        if ($this->max_size != 0 && $this->file_size > $this->max_size) {
            return false;
        }

        return true;
    }

    private function is_allowed_dimensions()
    {
        if (false == $this->is_image()) {
            return true;
        }

        if (function_exists('getimagesize')) {
            $D = @getimagesize($this->file_temp);
            if (false == $D) {
                return false;
            }
            $this->image_width = $D[0];
            $this->image_height = $D[1];
            if ($this->max_width > 0 && $D[0] > $this->max_width) {
                return false;
            }
            if ($this->max_height > 0 && $D[1] > $this->max_height) {
                return false;
            }
        }

        return true;
    }

    private function is_image()
    {
        $mimes = array('image/jpg', 'image/jpe', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif');

        return (in_array($this->file_type, $mimes, true)) ? true : false;
    }

    private function prepare_updata($field)
    {
        if (!isset($_FILES[$field])) {
            $this->error = 'upload_no_file_selected';

            return false;
        }
        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $error = isset($_FILES[$field]['error']) ? $_FILES[$field]['error'] : UPLOAD_ERR_NO_FILE;
            switch ($error) {
                case UPLOAD_ERR_INI_SIZE:
                    $this->error = 'upload_file_exceeds_limit';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->error = 'upload_file_exceeds_form_limit';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->error = 'upload_file_partial';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->error = 'upload_no_file_selected';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->error = 'upload_no_temp_directory';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->error = 'upload_unable_to_write_file';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $this->error = 'upload_stopped_by_extension';
                    break;
                default:
                    $this->error = 'upload_no_file_selected';
            }

            return false;
        }
        $this->file_temp = $_FILES[$field]['tmp_name'];
        $this->file_size = $_FILES[$field]['size'];
        $this->client_name = $this->clean_file_name($_FILES[$field]['name']);
        $this->file_type = preg_replace('/^(.+?);.*$/', '$1', $_FILES[$field]['type']);
        $this->file_type = strtolower(trim(stripslashes($this->file_type), '"'));
        empty($this->file_ext) && ($this->file_ext = pathinfo($this->client_name, PATHINFO_EXTENSION));
        $this->file_ext = strtolower($this->file_ext);
        $this->file_name = $this->set_filename();

        if (false === $this->is_allowed_filetype(false)) {
            $this->error = 'upload_invalid_filetype';

            return false;
        }
        if (false === $this->is_allowed_filesize()) {
            $this->error = 'upload_invalid_filesize';

            return false;
        }
        if (false === $this->is_allowed_dimensions()) {
            $this->error = 'upload_invalid_dimensions';

            return false;
        }

        return true;
    }

    private function validate_upload_path()
    {
        if ($this->upload_path == '') {
            $this->error = 'upload_no_filepath';

            return false;
        }
        if (function_exists('realpath') AND @realpath($this->upload_path) !== false) {
            $this->upload_path = realpath($this->upload_path);
        }
        if (!@is_dir($this->upload_path)) {
            $this->error = 'upload_no_filepath';

            return false;
        }
        if (!is_really_writable($this->upload_path)) {
            $this->error = 'upload_not_writable';

            return false;
        }
        $this->upload_path = preg_replace("/(.+?)\/*$/", "\\1/", $this->upload_path);
        if (true == $this->hash_folder && 0 < $this->hash_deep) {
            if (!preg_match('/^[a-z0-9]+\.[a-z0-9]{3,4}$/', $this->file_name)) {
                $this->error = 'hash_folder_need_encrypt_filename';

                return false;
            }
            $md5str = str_split($this->file_name, $this->hash_deep);
            $subdir = implode(DIRECTORY_SEPARATOR, array_slice($md5str, 0, $this->hash_deep));

            $this->upload_path .= $subdir . DIRECTORY_SEPARATOR;
            if (!is_dir($this->upload_path) && false == @mkdir($this->upload_path, 0745, true)) {
                $this->error = 'upload_filepath_not_make';

                return false;
            }
        }
        if (false === $this->encrypt_name && file_exists($this->upload_path . $this->file_name)) {
            $this->error = 'upload_file_exists';

            return false;
        }

        return true;
    }
}

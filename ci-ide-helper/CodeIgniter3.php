<?php die();

/**
 * Add you custom models here that you are loading in your controllers
 *
 * <code>
 * $this->site_model->get_records()
 * </code>
 * Where site_model is the model Class
 *
 * property 处增加自己的类/模型
 * 将System/Core/CI_Controller|CI_Model标记为纯文本
 *
 * ---------------------- Models to Load ----------------------
 * @property CI_Benchmark        $benchmark
 * @property CI_Cache            $cache
 * @property CI_Config           $config
 * @property CI_Email            $email
 * @property CI_Encrypt          $encrypt
 * @property CI_Exceptions       $exceptions
 * @property CI_Upload           $upload
 * @property CI_Input            $input
 * @property CI_Loader           $load
 * @property CI_Pagination       $pagination
 * @property CI_Parser           $parser
 * @property CI_Security         $security
 * @property CI_Session          $session
 * @property CI_URI              $uri
 * @property CI_DB_forge         $dbforge
 * @property CI_DB_query_builder $db
 * @property CI_Profiler         $profiler
 * @property CI_Lang             $lang
 * @property CI_Zip              $zip
 *
 * @property
 * @property
 */
class PHPStormHelpers
{
}

class CI_Controller extends PHPStormHelpers
{
    public function __construct()
    {
    }
}

class CI_Model extends PHPStormHelpers
{
    public function __construct()
    {
    }
}


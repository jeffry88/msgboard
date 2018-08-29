<?php
/**
 * DemoModel Example
 * @since    Date
 * @author    iSpeak Dev Team <User>
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Demo_model extends MY_Model
{
    private $defdb = null;

    public function __construct()
    {
        parent::__construct();
        $this->defdb = $this->load->database('default', true);
    }

    public function __destruct()
    {
        if (is_object($this->defdb)) {
            $this->defdb->close();
            $this->defdb = null;
        }
    }

    /**
     * xxx
     */
    public function foo()
    {
        if (false === $this->defdb->conn_id) {
            $this->error = (object)$this->defdb->error();
            $this->defdb->close();

            return false;
        }
    }
}

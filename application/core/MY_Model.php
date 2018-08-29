<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Model extends CI_Model
{
    public $error;
    protected $queryFields;

    public function __construct()
    {
        $this->error = new stdClass;
        $this->queryFields = array();
    }

    public function __destruct()
    {
        $this->error = null;
        $this->queryFields = null;
    }

    protected function prepareFieldType($fields)
    {
        $this->queryFields = array();
        foreach ($fields as $field) {
            $k = $field->name;
            $this->queryFields[$k] = is_int($field->type) ? $field->type : strtoupper($field->type);
        }
    }

    protected function prefixFieldValue($field, $value)
    {
        /*
        BIT: 16
        TINYINT: 1
        BOOL: 1
        SMALLINT: 2
        MEDIUMINT: 9
        INTEGER: 3
        BIGINT: 8
        SERIAL: 8
        FLOAT: 4
        DOUBLE: 5
        DECIMAL: 246
        NUMERIC: 246
        FIXED: 246

        DATE: 10
        DATETIME: 12
        TIMESTAMP: 7
        TIME: 11
        YEAR: 13

        CHAR: 254
        VARCHAR: 253
        ENUM: 254
        SET: 254
        BINARY: 254
        VARBINARY: 253
        TINYBLOB: 252
        BLOB: 252
        MEDIUMBLOB: 252
        TINYTEXT: 252
        TEXT: 252
        MEDIUMTEXT: 252
        LONGTEXT: 252
        */
        if (isset($this->queryFields[$field])) {
            switch ($this->queryFields[$field]) {
                case 1:
                case 'TINYINT':
                case 2:
                case 'SMALLINT':
                case 3:
                case 'INTEGER':
                case 8:
                case 'BIGINT':
                case 'SERIAL':
                case 9:
                case 'MEDIUMINT':
                case 16:
                case 'BIT':
                    return intval($value);
                case 4:
                case 'FLOAT':
                case 5:
                case 'DOUBLE':
                case 246:
                case 'DECIMAL':
                case 'NUMERIC':
                case 'FIXED':
                    return floatval($value);
                default:
                    return $value;
            }
        }

        return $value;
    }
}

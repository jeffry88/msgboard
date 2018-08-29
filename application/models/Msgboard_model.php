<?php
/**
 *
 * @copyright 2004 - 2018 Qinhe Co.,Ltd. (http://www.ispeak.cn/)
 * @since     2018/8/27
 * @author    iSpeak Dev Team <dengdinghua>
 */
class Msgboard_model extends MY_Model
{
    private $defdb = null;


    public function __construct()
    {
        parent::__construct();
        //$this->defdb = $this->load->database('default', true);
        $this->load->database();
    }

    public function __destruct()
    {
        if (is_object($this->defdb)) {
            $this->defdb->close();
            $this->defdb = null;
        }
    }

    /**
     * 添加留言
     */
    public function setmessage($data)
    {
        if (false === $this->defdb->conn_id) {
            $this->error = (object)$this->defdb->error();
            $this->defdb->close();

            return false;
        }else{
            return $this->db->insert('messages',$data);
        }
    }

    /**
     * 删除留言
     */
    public function delmessage($id)
    {
        if (false === $this->defdb->conn_id) {
            $this->error = (object)$this->defdb->error();
            $this->defdb->close();

            return false;
        }else{
            $this->db->where('id',$id);
            $data = $this->db->delete('messages');
            //$this->getmessage($start,$limit);
            return $data;

        }
    }

    /**
     * 修改留言
     */
    public function updmessage($id,$message)
    {
        if (false === $this->defdb->conn_id) {
            $this->error = (object)$this->defdb->error();
            $this->defdb->close();

            return false;
        }else{
//            $this->db->where('user_name' , $user_name);
//            $data = $this->db->update('messages', $message);
//            return $data;
            $data = array(
                'id' => $id,
                'message' => $message,
            );

            $this->db->where('id', $id);
            $this->db->update('messages', $data);
        }
    }

    /**
     * 查询留言
     */
    public function getmessage($start,$limit)
    {

        if (false === $this->defdb->conn_id) {
            $this->error = (object)$this->defdb->error();
            $this->defdb->close();

            return false;
        }else{
//            $perPage = 5;
//            $offset=$this->uri->segment(3);
           // $this->db->limit($perPage,$offset);
            $this->db->limit($limit,$start);
            $this->db->select('id,user_name,message,time');
            $this->db->order_by("time","desc");
            $query = $this->db->get('messages');

            //$total_rows = $query->num_rows();
            $total_rows = $this->db->count_all_results('messages');
            $data['messages'] = $query->result_array();
            $data['total_rows'] = $total_rows;
            if($query)
            {
                return $data;

            }
            else
            {
                return "empty";
            }

        }
    }
}

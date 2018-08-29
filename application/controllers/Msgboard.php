<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 *
 * @copyright 2004 - 2018 Qinhe Co.,Ltd. (http://www.ispeak.cn/)
 * @since     2018/8/27
 * @author    iSpeak Dev Team <dengdinghua>
 */
class Msgboard extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        $limit = 4;
        $page = $this->input->get('page');
        if(empty($page)){
            $page = 0;
            $start = $page;
            //var_dump($start);
        }else{
            $start = $this->input->get('page') * $limit;
            //var_dump($start);
        }
        //$start = $this->input->get('page') * $limit;
        //var_dump($start);
        $this->load->model('Msgboard_model');
        $data = $this->Msgboard_model->getmessage($start,$limit);
       // var_dump($data['total_rows']);
        $this->load->vars('pager', $this->generateQueryPager($this->config->site_url('msgboard'), $data['total_rows'], $limit));
        $this->load->vars('messages', $data['messages']);
        $this->load->view("msgboard");
    }

    /**
     * 添加评论
     */
    public function setmessage()
    {

        $data = array(
            'user_name' =>$this->input->post('user_name'),
            'message' => $this-> input->post('message')
        );
        //var_dump($data);
        $this->load->model('Msgboard_model');
        $this->Msgboard_model->setmessage($data);
        //$this->load->view('msgboard',$data);
        redirect('/msgboard/', 'refresh');
//           $test = $this->input->post(data);
//           echo json_encode($test);
    }

    /**
     * 删除评论
     */
    public function delMessage()
    {
        $id = $this->input->post('id');
        //var_dump($user_name);
        //exit();
        $this->load->model('Msgboard_model');
        $data = $this->Msgboard_model->delmessage($id);
        if($data){
            //redirect('/msgboard/', 'refresh');
            return $data;
        }else{
            $data = "删除失败！";
            return $data;
        }

    }

    /**
     * 编辑评论
     */

    public function upMessage()
    {
        $id = $this->input->post('id');
        $message = $this->input->post('message');
        $this->load->model('Msgboard_model');
        $data = $this->Msgboard_model->updmessage($id,$message);
        if($data){
            return $data;
        }else{
            return false;
        }
    }
}

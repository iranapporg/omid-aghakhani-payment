<?php
defined('BASEPATH') OR exit('No direct script access allowed');

    /**
     * Class Payment_pay
     *
     * create table for tokens
     * CREATE TABLE `tbl_trans_token` (
    `token` varchar(50) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

    ALTER TABLE `tbl_trans_token`
    ADD PRIMARY KEY (`token`);
     */
    class Payment_pay extends CI_Driver {

        private $ci;
        private $parent;

        function __construct() {
            $this->ci       =   & get_instance();
            $this->ci->load->library('session');
            $this->parent   =  $this->ci->payment;
        }
		
        function pay($price) {

            if ($this->parent->is_rial)
                $price  =   $price / 10;

            $call_back  =   $this->parent->callback_url;
            if (strpos($call_back,'?') === FALSE)
                $call_back  .=  '?pay_type=pay.ir';
            else
                $call_back  .=  '&pay_type=pay.ir';

            if ($this->parent->payload != NULL)
                $call_back  .=  '&payload='.base64_encode(json_encode($this->parent->payload));

            $validation =   array('price' => $price,'timestamp' => time(),'mobile' => $this->parent->mobile);
            $validation['desc']     =   $this->parent->desc;
            $validation['email']    =   $this->parent->email;
            $validation['user_id']  =   $this->parent->user_id;
            $token  =   AUTHORIZATION::generateToken($validation);

            $call_back  .=  '&validation='.$token;

            $jsonData                   =   array();
            $jsonData['api']            =   $this->parent->merchant_id;
            $jsonData['amount']         =   $price;
            $jsonData['redirect']       =   $call_back;
            $jsonData['mobile']         =   $this->parent->mobile;
            $jsonData['description']    =   $this->parent->desc;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://pay.ir/pg/send');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            $res = json_decode(curl_exec($ch));
            curl_close($ch);

            if (isset($res->status)) {

                if ($res->status == 1) {

                    header('Location: https://pay.ir/pg/' . $res->token);

                } else {
                    return FALSE;
                }

            } else {
                return FALSE;
            }

        }

        function verify() {

            if (!$this->ci->input->get('validation'))
                return FALSE;

            $this->ci->config->set_item('token_timeout',10);
            $validation     =   AUTHORIZATION::validateTimestamp($this->ci->input->get('validation'));

            if (!$validation)
                return FALSE;

            if ($this->ci->input->get('status') != '1')
                return FALSE;

            $Price              =   $validation['price'];

            $jsonData           =   array();
            $jsonData['api']    =   $this->parent->merchant_id;
            $jsonData['token']  =   $this->ci->input->get('token');

            $check = @$this->ci->db->where('token',$this->ci->input->get('token'))->get('user_trans');
            if ($check->num_rows() != 0)
                return FALSE;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://pay.ir/pg/verify');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            $res = json_decode(curl_exec($ch));
            curl_close($ch);

            if (isset($res->status)) {

                if ($res->status == 1) {

                    $this->ci->db->set('token',$this->CI->input->get('token'))->insert('user_trans');

                    if ($this->parent->is_rial)
                        $this->parent->set_var('price',$Price * 10);
                    else
                        $this->parent->set_var('price',$Price);

                    $this->parent->set_var('mobile',$validation['mobile']);
                    $this->parent->set_var('desc',$validation['desc']);
                    $this->parent->set_var('email',$validation['email']);
                    $this->parent->set_var('order_id',$this->ci->input->get('token'));

                    if (isset($_GET['payload'])) {
                        $this->parent->set_var('payload',json_decode(base64_decode($_GET['payload']),TRUE));
                    }

                    if ($this->parent->auto_save == TRUE)
                        $this->parent->save_transaction($this->parent->user_id,$this->ci->input->get('token'),$Price,$validation['desc']);

                    return TRUE;

                } else {

                    if (isset($_GET['payload'])) {
                        $this->parent->set_var('payload',json_decode(base64_decode($_GET['payload']),TRUE));
                    }

                    return FALSE;
                }

            } else {

                if (isset($_GET['payload'])) {
                    $this->parent->set_var('payload',json_decode(base64_decode($_GET['payload']),TRUE));
                }

                return FALSE;
            }

        }

    }
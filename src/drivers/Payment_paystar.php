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
    class Payment_paystar extends CI_Driver {

        private $ci;
        private $parent;

        function __construct() {
            $this->ci       =   & get_instance();
            $this->ci->load->library('session');
            $this->parent   =  $this->ci->payment;
        }
		
        function pay($price) {

            $order_id = rand(10000,99999999999);

            $call_back  =   $this->parent->callback_url;
            if (strpos($call_back,'?') === FALSE)
                $call_back  .=  '?pay_type=paystar';
            else
                $call_back  .=  '&pay_type=paystar';

            if ($this->parent->payload != NULL) {
                $this->ci->session->set_tempdata('payload',$this->parent->payload);
            }

            $validation             =   array('price' => $price,'timestamp' => time(),'mobile' => $this->parent->mobile);
            $validation['desc']     =   $this->parent->desc;
            $validation['email']    =   $this->parent->email;
            $validation['user_id']  =   $this->parent->user_id;
            $validation['order_id'] =   $order_id;
            $validation['mobile']   =   $this->parent->mobile;
            $token  =   AUTHORIZATION::generateToken($validation);
            $this->ci->session->set_tempdata('token',$token,59 * 10);

            $call_back .= '&amount='.$price;

            $curl = curl_init();

            $data['amount'] = $price;
            $data['order_id'] = $order_id;
            $data['callback'] = $call_back;
            $data['sign'] = hash_hmac('sha512', "$price#$order_id#$call_back", $this->parent->key);
            $data['name'] = $this->parent->desc;
            $data['description'] = $this->parent->desc;
            $data['phone'] = $this->parent->mobile;

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://core.paystar.ir/api/pardakht/create',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer '.$this->parent->merchant_id,
                    'Content-Type: application/json'
                ),
            ));

            $response = json_decode(curl_exec($curl));

            curl_close($curl);

            if ($response->status != 1) {
                return $response;

            } else {
                $token = $response->data->token;

                echo 'در حال ورود به درگاه...';

                echo '<form id="form" action="https://core.paystar.ir/api/pardakht/payment" method="post">
                <input type="hidden" name="token" value="'.$token.'">
                </form>
                <script>
                document.getElementById("form").submit();
                </script>';

            }

        }

        function verify() {

            if (!$this->ci->session->tempdata('token'))
                return FALSE;

            $validation = $this->ci->session->tempdata('token');
            $validation = AUTHORIZATION::validateToken($validation);
            if ($validation == FALSE)
                return FALSE;

            $Price              =   $_GET['amount'];

            $jsonData           =   array();
            $jsonData['api']    =   $this->parent->merchant_id;
            $jsonData['token']  =   $this->ci->input->get('token');

            $pin = $this->parent->merchant_id;
            $url = 'http://core.paystar.ir/api/pardakht/verify/';

            if ($_POST['status'] == 1) {

                $fields = array(
                    'ref_num' => $_POST['ref_num'],
                    'amount' => $_GET['amount']
                );

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $h = array('Authorization: Bearer '.$pin, 'Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $result = json_decode(curl_exec($ch));
                curl_close($ch);

                $this->parent->set_var('price',$_GET['amount']);
                $this->parent->set_var('mobile',$validation->mobile);
                $this->parent->set_var('desc',$validation->desc);
                $this->parent->set_var('email',$validation->email);
                $this->parent->set_var('user_id',$validation->user_id);
                $this->parent->set_var('order_id',@$validation->order_id);

                if (isset($_GET['payload'])) {
                    $this->parent->set_var('payload',$_GET['payload']);
                }

                if ($this->parent->auto_save == TRUE)
                    $this->parent->save_transaction($this->parent->user_id,$result ['data']['ref_id'],$Price,$validation['desc']);

                return TRUE;

            } else {

                if (isset($_GET['payload'])) {
                    $this->parent->set_var('payload',json_decode(base64_decode($_GET['payload']),TRUE));
                }

                return FALSE;

            }

        }

    }
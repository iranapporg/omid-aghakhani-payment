<?php
defined('BASEPATH') OR exit('No direct script access allowed');

    class Payment_zarinpal extends CI_Driver {

        private $ci;
        private $parent;
		private $is_gate = FALSE;

        function __construct() {
            $this->ci       =   & get_instance();
            $this->ci->load->library('session');
            $this->parent   =  $this->ci->payment;
            include APPPATH.'libraries/Payment/drivers/nusoap.php';
        }

		function is_zarin_gate() {
			$this->is_gate	=	TRUE;
		}
		
        function pay($price) {

            $call_back  =   $this->parent->callback_url;
            if (strpos($call_back,'?') === FALSE)
                $call_back  .=  '?pay_type=zarinpal';
            else
                $call_back  .=  '&pay_type=zarinpal';

            if ($this->parent->payload != NULL)
                $call_back  .=  '&payload='.base64_encode(json_encode($this->parent->payload));

            $validation             =   array('price' => $price,'timestamp' => time(),'mobile' => $this->parent->mobile);
            $validation['desc']     =   $this->parent->desc;
            $validation['email']    =   $this->parent->email;
            $validation['user_id']  =   $this->parent->user_id;
            $token  =   AUTHORIZATION::generateToken($validation);

            $call_back  .=  '&validation='.$token;

            $data = array("merchant_id" => $this->parent->merchant_id,
                "amount" => $price,
                "callback_url" => $call_back,
                'description' => $this->parent->desc,
                'metadata' => ['mobile' => $this->parent->mobile,'email' => $this->parent->mobile]
            );

            $jsonData = json_encode($data);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));

            $result = curl_exec($ch);
            $err = curl_error($ch);
            $result = json_decode($result, true, JSON_PRETTY_PRINT);
            curl_close($ch);

            if ($err) {
                return FALSE;
            } else {
                if (empty($result['errors'])) {
                    if ($result['data']['code'] == 100) {
                        header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                        return TRUE;
                    } else {
                        return $result;
                    }
                } else {
                    return $result['errors'];
                }
            }

        }

        function verify() {

            if (!$this->ci->input->get('validation'))
                return FALSE;

            $this->ci->config->set_item('token_timeout',10);
            $validation     =   AUTHORIZATION::validateTimestamp($this->ci->input->get('validation'));

            if (!$validation)
                return FALSE;

            $validation     =   (array) $validation;
            $Price          =   $validation['price'];

            if (@$_GET['Status'] == 'OK') {

                $Authority = $_GET['Authority'];
                $data = array('merchant_id' => $this->parent->merchant_id, 'authority' => $Authority, 'amount' => $Price);
                $jsonData = json_encode($data);
                $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
                curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonData)
                ));

                $result = curl_exec($ch);
                curl_close($ch);
                $result = json_decode($result, true);

                if (!is_array($result)) {
                   return FALSE;
                }

                if (!isset($result['data']['code']))
                    return FALSE;

                if ($result['data']['code'] == 100) {

                    $this->parent->set_var('details',json_encode($result));

                    $this->parent->set_var('price',$Price);
                    $this->parent->set_var('mobile',$validation['mobile']);
                    $this->parent->set_var('desc',$validation['desc']);
                    $this->parent->set_var('email',$validation['email']);
                    $this->parent->set_var('user_id',$validation['user_id']);
                    $this->parent->set_var('order_id',$result ['data']['ref_id']);

                    if (isset($_GET['payload'])) {
                        $this->parent->set_var('payload',json_decode(base64_decode($_GET['payload']),TRUE));
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

            } else {

                if (isset($_GET['payload'])) {
                    $this->parent->set_var('payload',json_decode(base64_decode($_GET['payload']),TRUE));
                }

                return FALSE;

            }

        }

    }
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

	include_once 'system/libraries/Driver.php';
	
    /**
     * pay money in zarinpal and other payment method
     *
     * @property $mobile string get mobile on payment [accessible after verify payment]
     * @property $desc string get payment description [accessible after verify payment]
     * @property $email string get payment email [accessible after verify payment]
     * @property $payload array get attachment payload on user payment [accessible after verify payment]
     * @property $price int get user payment price [accessible after verify payment]
     * @property $order_id int get payment ref or order id [accessible after verify payment]
     * @property $auto_save bool get payment ref or order id [accessible after verify payment]
     * @property $user_id int save transaction for this user id [accessible after verify payment]
     * @property $details string get payment details
     *
	 * add timeout_payment config to config.php
     */
    class Payment extends CI_Driver_Library {

        private $CI;
        private $merchant_id;
        private $desc;
        private $email;
        private $mobile;
        private $callback_url;
        private $payload = NULL;
        private $price;
        private $key;
        private $order_id;
        private $auto_save = FALSE;
        private $user_id;
        private $is_rial = FALSE;
        private $details = '';

        function __construct() {
            $this->CI = & get_instance();
            $this->valid_drivers = array('zarinpal','pay','paystar');
            $this->CI->load->helper(array('authorization','jwt'));
            $this->CI->load->config('jwt');
        }

        function set_merchant_id($id) {
            $this->merchant_id  =   $id;
            return $this;
        }

        /**
         * after payment,save transaction in table
         * need to set user_id
         */
        function auto_save_transaction() {
            $this->auto_save  =   TRUE;
        }

        /**
         * set this when you need to save transaction data
         */
        function set_user_id($user_id) {
            $this->user_id  =   $user_id;
        }

        function set_description($desc) {
            $this->desc =   $desc;
            return $this;
        }

        function set_email($email) {
            $this->email =   $email;
            return $this;
        }

        /**
         * if payment driver need to key, set it here
         * @param $key
         * @return $this
         */
        function set_key($key) {
            $this->key =   $key;
            return $this;
        }

        function set_mobile($mobile) {
            $this->mobile   =   $mobile;
            return $this;
        }

        /**
         * attach array that converted to json and get it after paid
         * @param $payload_data
         * @return $this
         */
        function set_payload($payload_data) {
            $this->payload  =   $payload_data;
            return $this;
        }

        function set_callback_url($url) {
            $this->callback_url    =   $url;
            return $this;
        }

        function is_rial() {
            $this->is_rial  =   TRUE;
            return $this;
        }

        function generate_order_id($salt = '') {

            $unique_id  =    $salt.mt_rand(10000,999999999);
            return $unique_id;

        }

        /**
         * save payment log to table
         * ```
         * DROP TABLE IF EXISTS `tbl_user_trans`;
        CREATE TABLE IF NOT EXISTS `tbl_user_trans` (
        `pid` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` varchar(20) COLLATE utf8_bin DEFAULT NULL,
        `user_id` int(11) NOT NULL,
        `price` int(11) NOT NULL,
        `details` varchar(1000) COLLATE utf8_bin NOT NULL,
        `ip` varchar(15) COLLATE utf8_bin NOT NULL,
        `time` int(11) NOT NULL,
        `status` tinyint(1) NOT NULL DEFAULT '0',
        PRIMARY KEY (`pid`)
        ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
         * ```
         * @param $user_id
         * @param $order_id
         * @param $price
         * @param $details
         */
        function save_transaction($user_id,$order_id,$price,$details) {

            $this->CI->db->db_debug = FALSE;

            $this->CI->db->set('order_id',$order_id);
            $this->CI->db->set('user_id',$user_id);
            $this->CI->db->set('price',$price);
            $this->CI->db->set('details',$details);
            $this->CI->db->set('ip',$this->CI->input->ip_address());
            $this->CI->db->set('time',time());
            $this->CI->db->insert('user_trans');

            return $this->CI->db->insert_id();

        }

        function __get($child) {

            if (in_array($child,$this->valid_drivers)) {
                $ob = $this->load_driver($child);
                return $ob;
            } else {
                if (property_exists($this,$child))
                    return $this->$child;
                if ($child == 'price' || $child == 'payment_price')
                    return $this->price;
                else if ($child == 'order_id' || $child == 'ref_id')
                    return $this->order_id;
				else if ($child == 'details')
					return $this->details;
                return;
            }

        }

        function set_var($key,$value) {
            $this->$key =   $value;
        }

    }
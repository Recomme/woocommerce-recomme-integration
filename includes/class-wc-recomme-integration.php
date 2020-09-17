<?php
/**
 * WooCommerce Recomme Integration.
 *
 * @package  WC_Recomme_Integration
 * @category Integration
 * @author   Recomme
 */

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

if (!class_exists('WC_Recomme_Integration')) {
    class WC_Recomme_Integration extends WC_Integration {
        public function __construct() {
            global $woocommerce;
            
            $this->id                 = 'recomme';
            $this->method_title       = __('Recomme', 'recomme-woocommerce');
            $this->method_description = __('Swój klucz API znajdziesz tutaj: <a target="_blank" href="https://app.recomme.io/integrations">https://app.recomme.io/integrations</a>', 'recomme-woocommerce');

            // Load the settings.
            $this->init_form_fields();

            // Define user set variables.
            $this->api_key           = $this->get_option('api_key');
            $this->customer_key           = $this->get_option('customer_key');

            $this->status_to        = str_replace('wc-', '', $this->get_option('order_status'));
            $this->tracking_page    = $this->get_option('tracking_page');
            
            // Actions.
            add_action('woocommerce_update_options_integration_' . $this->id,   [$this, 'process_admin_options']);
            add_action('admin_notices',                                         [$this, 'check_plugin_requirements']);
            add_action('init',                                                  [$this, 'ref_code_cookie']);
            add_action('save_post',                                             [$this, 'add_ref_code']);
            add_action('woocommerce_order_status_' . $this->status_to,          [$this, 'submit_purchase'], 10, 1);

            // Filters.
            add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, [$this, 'sanitize_settings']);
        }

        public function init_form_fields() {

            $this->form_fields = [
                'api_key' => [
                    'title'             => __('API Key', 'recomme-woocommerce'),
                    'type'              => 'text',
                    'desc'              => __('You can find your API Access ID on https://app.recomme.pl/settings'),
                    'desc_tip'          => true,
                    'default'           => ''
                ],
                'customer_key' => [
                    'title'             => __('Customer Key', 'recomme-woocommerce'),
                    'type'              => 'text',
                    'desc'              => __('You can find your App ID on https://app.recomme.pl/settings', 'recomme-woocommerce'),
                    'desc_tip'          => true,
                    'default'           => ''
                ],
                'order_status' => [
                    'title'             => __('Process orders with status', 'recomme-woocommerce'),
                    'type'              => 'select',
                    'options'           => wc_get_order_statuses(),
                    'desc'              => __('Orders with this status are sent to ReferralCandy', 'recomme-woocommerce'),
                    'desc_tip'          => true,
                    'default'           => 'wc-completed'
                ],
             
            ];
        }

        public function sanitize_settings($settings) {
            return $settings;
        }

        private function is_option_enabled($option_name) {
            return $this->get_option($option_name) == 'yes'? true : false;
        }

        public function check_plugin_requirements() {
            $message = "<strong>RECOMME</strong>: Please make sure the following settings are configured for your integration to work properly:";
            $integration_incomplete = false;
            $keys_to_check = [
                'Klucz API' => $this->api_key,
                'Firma'        => $this->customer_key,
            ];

            foreach($keys_to_check as $key => $value) {
                if (empty($value)) {
                    $integration_incomplete = true;
                    $message .= "<br> - $key";
                }
            }

            $valid_statuses = array_keys(wc_get_order_statuses());
            if (!in_array($this->get_option('order_status'), $valid_statuses)) {
                $integration_incomplete = true;
                $message .= "<br> - Please re-select your preferred order status to be sent to us and save your settings";
            }

            if ($integration_incomplete == true) {
                printf('<div class="notice notice-warning"><p>%s</p></div>', $message);
            }
        }

        public function add_ref_code($post_id) {
            try {
                if (in_array(get_post($post_id)->post_type, ['shop_order', 'shop_subscription'])) {
                    if (is_admin() == false && isset($_COOKIE['recomme_r_code'])) {
                        update_post_meta($post_id, 'recomme_r_code',  $_COOKIE['recomme_r_code']);
                    }
                }
            } catch(Exception $e) {
                error_log($e);
            }
        }

        public function submit_purchase($order_id) {
            $rc_order = new Recomme_Order($order_id, $this);
            $rc_order->submit_purchase();
        }

        public function ref_code_cookie() {

            $days_to_keep_cookies = 28;
            if (isset($_GET['rcr']) && $_GET['rcr'] !== null) {
                $cookie_domain = preg_replace('/(http||https):\/\/(www\.)?/', '.', get_bloginfo('url'));
                setcookie('recomme_r_code', $_GET['rcr'], time() + (86400 * $days_to_keep_cookies), '/', $cookie_domain);
            }
        }
    }
}

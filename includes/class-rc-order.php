<?php
/**
 * WooCommerce Recomme Integration.
 *
 * @package  RC_Order
 * @category Integration
 * @author   Recomme
 */

class Recomme_Order {
    public $base_url = 'https://api.recomme.io';
    public $wc_pre_30 = false;
    public $api_key;
    public $customer_key;
    public $first_name;
    public $last_name;
    public $email;
    public $discount_code;
    public $total;
    public $currency;
    public $order_number;
    public $order_timestamp;
    public $browser_ip;
    public $user_agent;
    public $ref_code;

    public function __construct($wc_order_id, WC_Recomme_Integration $integration) {
        $this->wc_pre_30 = version_compare(WC_VERSION, '3.0.0', '<');
        $wc_order   = new WC_Order($wc_order_id);

        if ($this->wc_pre_30) {
            $this->order_timestamp = time();
            if (get_option('timezone_string') != null) {
                $timezone_string = get_option('timezone_string');
                $this->order_timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $wc_order->order_date, new DateTimeZone($timezone_string))->getTimestamp();
            }

            $this->first_name       = $wc_order->billing_first_name;
            $this->last_name        = $wc_order->billing_last_name;
            $this->email            = $wc_order->billing_email;
            $this->total            = $wc_order->get_total();
            $this->currency         = $wc_order->get_order_currency();
            $this->order_number     = $wc_order_id;
            $this->browser_ip       = $wc_order->customer_ip_address;
            $this->user_agent       = $wc_order->customer_user_agent;
            $this->ref_code         = get_post_meta($wc_order_id, 'recomme_r_code', true);
        } else {
            $order_data = $wc_order->get_data();

            $this->first_name       = $order_data['billing']['first_name'];
            $this->last_name        = $order_data['billing']['last_name'];
            $this->email            = $order_data['billing']['email'];
            $this->total            = $order_data['total'];
            $this->currency         = $order_data['currency'];
            $this->order_number     = $wc_order_id;
            $this->order_timestamp  = $order_data['date_created']->getTimestamp();
            $this->browser_ip       = $order_data['customer_ip_address'];
            $this->user_agent       = $order_data['customer_user_agent'];
            $this->ref_code         = $wc_order->get_meta('recomme_r_code', true, 'view');
        }

        $this->api_key           = $integration->api_key;
        $this->customer_key      = $integration->customer_key;

    }

    private function generate_post_fields($specific_keys = [], $additional_keys = []) {
        $post_fields = [
            'api_key'               => $this->api_id,
            'first_name'            => $this->first_name,
            'last_name'             => $this->last_name,
            'email'                 => $this->email,
            'order_timestamp'       => $this->order_timestamp,
            'browser_ip'            => $this->browser_ip,
            'user_agent'            => $this->user_agent,
            'invoice_amount'        => $this->total,
            'currency_code'         => $this->currency,
            'external_reference_id' => $this->order_number,
            'ref_code'              => $this->ref_code,
            'timestamp'             => time(),
        ];

        // only add referrer_id if present
        if ($this->ref_code != null) {
            $post_fields['ref_code'] = $this->ref_code;
        }

        // check if we need only specific post fields from the default
        if ($specific_keys != null && count($specific_keys) > 0) {
            $new_post_fields = [];
            foreach($post_fields as $field => $value) {
                if (in_array($field, $specific_keys)) {
                    $new_post_fields[$field] = $value;
                }
            }

            // only overwrite post fields if at least one key is retreived
            if ($new_post_fields != null && count($new_post_fields) > 0) {
                $post_fields = $new_post_fields;
            }
        }

        // check if there are additional keys we want to add to the payload
        if ($additional_keys != null && count($additional_keys) > 0) {
            $post_fields = array_merge($post_fields, $additional_keys);
        }

        // sort keys
        ksort($post_fields);

        return $post_fields;
    }

    public function submit_purchase() {
        $endpoint = join('/', [$this->base_url, 'purchase']);
        
        if (!empty($this->api_key)) {
            $response   = wp_safe_remote_post($endpoint, [
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => "Bearer {$this->api_key}",
                    'Customer'      => "{$this->customer_key}"
                ],
                'body'        => wp_json_encode($this->generate_post_fields()),
                // 'method'      => 'POST',
                'data_format' => 'body',
            ]);
            // echo "<pre>";print_r($response); exit;

            // error_log('Recomme API#purchase params: ' . print_r($params['body'], true));
            error_log('Recomme API#purchase response: ' . print_r(json_decode($response['body']), true));
        }
    }
}

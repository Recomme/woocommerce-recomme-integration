<?php
/**
 * Plugin Name: Recomme for WooCommerce
 * Plugin URI: https://github.com/Recomme/woocommerce
 * Description: RECOMME is an application for automate referal marketing 
 * Author: RECOMME
 * Author URI: https://www.recomme.io
 * Text Domain: woocommerce-recomme-integration
 * Version: 1.1.1
 * WC requires at least: 2.1
 * WC tested up to: 4.2
 * Tested up to: 5.5
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    die('Direct access is prohibited.');
}

if (preg_grep("/\/woocommerce.php$/", apply_filters('active_plugins', get_option('active_plugins'))) !== null) {
    if (!class_exists('WC_Recomme')) {
        class WC_Recomme {
            public function __construct() {
                add_action('plugins_loaded', array($this, 'init'));
            }

            public function init() {
                if (class_exists('WC_Integration')) {
                    autoload_classes();
                    add_filter('woocommerce_integrations', [$this, 'add_integration']);
                } else {
                    add_action('admin_notices', 'missing_prerequisite_notification');
                }

                load_plugin_textdomain('woocommerce-recomme', false, dirname(plugin_basename(__FILE__)) . '/languages/');
            }

            public function add_integration($integrations) {
                $integrations[] = 'WC_Recomme_Integration';

                return $integrations;
            }
        }

        $WC_Recomme = new WC_Recomme(__FILE__);
    }

    function autoload_classes() {
        $files = scandir(dirname(__FILE__) . '/includes');
        $valid_extensions = ['php'];
        foreach ($files as $index => $file) {
            if (in_array(pathinfo($file)['extension'], $valid_extensions)) {
                require_once('includes/' . pathinfo($file)['basename']);
            }
        }
    }

    function wc_recomme_plugin_activate() {
        add_option('wc_recomme_plugin_do_activation_redirect', true);
    }

    function wc_recomme_plugin_redirect() {
        if (get_option('wc_recomme_plugin_do_activation_redirect')) {
            delete_option('wc_recomme_plugin_do_activation_redirect');

            if (!isset($_GET['activate-multi'])) {
                $setup_url = admin_url("admin.php?page=wc-settings&tab=integration&section=recomme");
                wp_redirect($setup_url);

                exit;
            }
        }
    }

    function missing_prerequisite_notification() {
        $message = 'Recomme <strong>requires</strong> Woocommerce to be installed and activated';
        printf('<div class="notice notice-error"><p>%1$s</p></div>', $message);
    }

    function rc_plugin_links($links) {
        $rc_tab_url = "admin.php?page=wc-settings&tab=integration&section=recomme";
        $settings_link = "<a href='". esc_url( get_admin_url(null, $rc_tab_url) ) ."'>Settings</a>";

        array_unshift($links, $settings_link);

        return $links;
    }

    register_activation_hook(__FILE__, 'wc_recomme_plugin_activate');
    add_action('admin_init', 'wc_recomme_plugin_redirect');
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'rc_plugin_links');
}

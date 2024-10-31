<?php
/*
 * Plugin Name: OneShip Shipping for WooCommerce
 * Description: OneShip plugin for easy shipping method
 * Requires at least: 4.4
 * Requires PHP: 5.3.0
 * Author: OneShip
 * Author URI: https://oneship.io
 * Version: 1.0.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

define('ONESHIP_VERSION', '1.0.1');

if (!defined('WPINC')) {

    die;
}

include_once 'includes/oneship-registration.php';
/*
 * Check if WooCommerce is active
 */
// if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
if (!class_exists('WC_Integration_Oneship')) {
    class WC_Integration_Oneship
    {
        protected $endpoints;
        /**
         * Construct the plugin.
         */
        public function __construct()
        {
            add_action('init', array($this, 'register_session'));
            add_action('woocommerce_shipping_init', array($this, 'init'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
            add_action('wp_ajax_oauth_es', array($this, 'oauth_es_callback'));
            add_action('wp_ajax_es_disabled', array($this, 'es_disabled_callback'));
        }
        /**
         * Initialize the plugin.
         */
        public function init()
        {
            // start a session
            add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
            // Checks if WooCommerce is installed.
            if (class_exists('WC_Integration')) {
                // Include our integration class.
                include_once 'includes/oneship-shipping.php';
                // Register the integration.
                add_filter('woocommerce_shipping_methods', array($this, 'add_integration'));
            }
            $dismissedSetupNotice = get_user_meta(get_current_user_id(), 'dismissed_oneship-setup_notice');

            if (!$dismissedSetupNotice) {
                add_action('admin_notices', array($this, 'setup_notice'));
            }
        }
        /**
         * Add a new integration to WooCommerce.
         */
        public function add_integration($integrations)
        {
            $integrations[] = 'Oneship_Shipping_Method';
            return $integrations;
        }
        /**
         *  Register a session
         */
        public function register_session()
        {
            if (session_status() == PHP_SESSION_NONE && !headers_sent())
                session_start();
            session_write_close();
        }
        /**
         *  Add Settings link to plugin page
         */
        public function plugin_action_links($links)
        {
            return array_merge(
                $links,
                array('<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=oneship') . '"> ' . __('Settings', 'oneship') . '</a>')
            );
        }
        public function add_shipping_method($methods)
        {
            if (is_array($methods)) {
                $methods['oneship'] = 'Oneship_Shipping_Method';
            }
            return $methods;
        }
        public function oauth_es_callback()
        {
            $obj = new Oneship_Registration();
            echo $obj->sendRequest();
            die;
        }
        public function es_disabled_callback()
        {
            $option_name = 'es_access_token_' . get_current_network_id();
            update_option($option_name, '');
            echo 'success';
            wp_die();
        }

        public function setup_notice()
        {

            $hideNoticeUrl = esc_url(wp_nonce_url(add_query_arg('wc-hide-notice', 'oneship-setup'), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce'));

            $html = '<div id="message" class="updated woocommerce-message oneship-setup" style="padding:20px;position:relative;"><a class="woocommerce-message-close notice-dismiss" href="' . $hideNoticeUrl . '"></a><p>To start printing shipping labels with Oneship navigate to <a class="external-link" href="https://accounts.oneclub.vip/user/login?app=os" target="_blank">https://onship.io</a> and log in or sign up for a new account.</p><p>After logging in, configure your WooCommerce integration to initiate communication between Oneship and WooCommerce.</p><p>Once you\'ve connected your integrations, you can begin booking shipments for those orders</p></div>';

            echo wp_kses_post($html);
        }
    }
    $WC_Integration_Oneship = new WC_Integration_Oneship();
}

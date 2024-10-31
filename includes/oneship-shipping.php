<?php

/**
 * Class Oneship_Shipping_Method
 */
if (!class_exists('Oneship_Shipping_Method')) {
    class Oneship_Shipping_Method extends WC_Shipping_Method
    {
        protected $discount_for_item = 0;
        protected $control_discount = 0;
        protected $token;
        protected $shipping_class;
        /**
         * Constructor for your shipping class
         *
         * @access public
         * @param int $instance_id
         */
        public function __construct($instance_id = 0)
        {
            $this->id = 'oneship';
            $this->instance_id = empty($instance_id) ? 99 : absint($instance_id);
            $this->method_title = __('OneShip', 'oneship');
            $this->method_description = __('OneShip', 'oneship');

            $this->supports = array(

                'settings',
                'instance-settings',
                'instance-settings-modal',
            );

            $this->init();
            $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
            $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('OneShip', 'oneship');
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }
        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        function init()
        {
            // Load the settings API
            $this->init_form_fields();
            $this->init_settings();
            add_filter('woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50);
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            add_action('update_option_woocommerce_oneship_settings', array($this, 'clear_session'), 10, 2);

            //add_action('woocommerce_update_options_shipping_oneship', array($this, 'saveOptions'));
        }
        public static function add_settings_tab($settings_tabs)
        {
            $settings_tabs['shipping&section=oneship'] = __('Oneship', 'oneship-shipping');
            return $settings_tabs;
        }
        /**
         * Clear session when option save
         *
         * @access public
         * @return void
         */
        public function clear_session($old_value, $new_value)
        {
            $_SESSION['access_token'] = null;
        }



        /**
         * Notification when api key and secret is not set
         *
         * @access public
         * @return void
         */
        public function oneship_admin_notice()
        {
            $token = 'es_access_token_' . get_current_network_id();
            if (($this->get_option('es_api_key') == '' || $this->get_option('es_api_secret') == '') && (get_option($token) == '')) {
                echo '<div class="error">Please go to <bold>WooCommerce > Settings > Shipping > Oneship</bold> to add your API key and API Secret Or Access Token </div>';
            }
        }
        /**
         * Define settings field for this shipping
         * @return void
         */
        function init_form_fields()
        {
            // added: 4/9/2017
            // Display access token field to new customer, if api_key or secret already set for current customer, then
            // display api key and secret key information
            // new customer

            $token_fields = [];
            $token_fields['es_oauth_ajax'] = [
                'title' => __('Connect', 'oneship-shipping'),
                'type' => 'button',
                'description' => __('Click the "Connect" button to signup for a new account or login to 「OneShip」 . Once you\'re logged in, your <br/> WooCommerce store will automatically be connected to OneShip and orders will automatically start to appear'),
                "default" => "Connect my WooCommerce Store to OneShip"
            ];

            $this->form_fields = array_merge($token_fields, $this->form_fields);
            oauth_action_button_es();
            add_action('admin_enqueue_scripts', 'oauth_action_button_es');
        }
        public function generate_text_html($key, $data)
        {
            $field_key = $this->get_field_key($key);
            $defaults  = array(
                'title'             => '',
                'disabled'          => false,
                'class'             => '',
                'css'               => '',
                'placeholder'       => '',
                'type'              => 'text',
                'desc_tip'          => false,
                'description'       => '',
                'custom_attributes' => array(),
            );
            $data = wp_parse_args($data, $defaults);
            if (isset($data['type']) && ($data['type'] == 'button')) {
                $value = isset($data['default']) ? $data['default'] : '';
            } else {
                $value = esc_attr($this->get_option($key));
            }
            ob_start();
?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post($this->get_tooltip_html($data));
                                                                                                                    ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo wp_kses_post($this->get_custom_attribute_html($data));
                                                                                                                                                                                                                                                                                                                                                                                                                                        ?> />
                        <?php echo wp_kses_post($this->get_description_html($data));
                        ?>
                    </fieldset>
                </td>
            </tr>
<?php
            return ob_get_clean();
        }
    }
}
function oauth_action_button_es()
{
    wp_enqueue_script(
        'oauth_action_button_es',
        plugin_dir_url(__FILE__) . 'assets/js/admin/ajax_oauth_es.js',
        array('jquery'),
        '5.0.4'
    );
}

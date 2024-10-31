<?php

$ENV = 'prod';

$SECRET = [
    'dev' => 'oneship',
    'test' => 'oneship',
    'pre' => 'ceAwsDCGgsOSVGVI',
    'prod' => 'ceAwsDCGgsOSVGVI'
];


$ENDPOINT = array(
    'dev' => 'http://logistic-op-gw-dev.myshoplinedev.com/v20210901/logistics/auth/merchant/callback/dev/woocommerce',
    'test' => 'http://logistic-op-gw-test.myshoplinestg.com/v20210901/logistics/auth/merchant/callback/test/woocommerce',
    'sandbox' => 'http://logistic-op-gw-sandbox.myshoplinestg.com/v20210901/logistics/auth/merchant/callback/woocommerce',
    'pre' => 'https://logistic-op-gw-preview.inshopline.com/v20210901/logistics/auth/merchant/callback/woocommerce',
    'prod' => 'https://openapi.oneship.io/v20210901/logistics/auth/merchant/callback/woocommerce'
);


$REDIRECT_BASE = [
    'dev' => 'https://admin-dev.oneship.io',
    'test' => 'https://admin-test.oneship.io',
    'pre' => 'https://admin-preview.oneship.io',
    'prod' => 'https://admin.oneship.io'
];


if (!class_exists('Oneship_Registration')) {
    class Oneship_Registration
    {
        private $wpdb;
        private $woocommerce;
        protected $endpoint;
        private $secret;
        private $redirect_base;
        public function __construct()
        {
            global $wpdb;
            global $woocommerce;

            $this->wpdb = $wpdb;
            $this->woocommerce = $woocommerce;
            $this->endpoint = $GLOBALS['ENDPOINT'][$GLOBALS['ENV']];
            $this->secret = $GLOBALS['SECRET'][$GLOBALS['ENV']];
            $this->redirect_base = $GLOBALS['REDIRECT_BASE'][$GLOBALS['ENV']];
        }

        public function sendRequest()
        {
            $request = $this->_getOAuthInfo();
            $user_id = $this->getRandomUserId();
            $request['user_id'] = $user_id;
            $token = $this->_genToken($user_id, $this->secret);
            $path = $this->endpoint . '?token=' . $token . '&shop=' . get_site_url();
            $response = wp_remote_post($path, [
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array('Content-Type:application/json', 'Cache-Control: no-cache'),
                'body' => $request,
            ]);
            header("Content-Type: application/json");
            if (is_wp_error($response)) {
                return json_encode(['error' => 'Service temporarily unavailable', 'message' => $response->get_error_message()]);
            }
            try {
                $response = json_decode(wp_remote_retrieve_body($response));
                $response->redirect_url = $this->redirect_base . '/auth/bind?app_id=WOC01&shop=' . get_site_url() . '&wooTicket=' . $response->ticket;
                return json_encode($response);
            } catch (\Exception $exception) {
                return json_encode(['error' => $exception->getMessage()]);
            }
        }

        /**
         * @return array|bool
         */
        protected function _getOAuthInfo()
        {
            $apiSource = $this->createApiKeys();
            return $apiSource;
        }

        protected function createApiKeys()
        {
            $consumer_key = 'ck_' . wc_rand_hash();
            $consumer_secret = 'cs_' . wc_rand_hash();
            $key_permissions = 'read_write';
            $data = array(
                'user_id' => get_current_user_id(),
                'description' => 'Oneship Integration',
                'permissions' => $key_permissions,
                'consumer_key' => wc_api_hash($consumer_key),
                'consumer_secret' => $consumer_secret,
                'truncated_key' => substr($consumer_key, -7),
            );

            $table = $this->wpdb->prefix . 'woocommerce_api_keys';

            $this->wpdb->query("DELETE FROM $table WHERE description = 'Oneship Integration'");

            $this->wpdb->insert(
                $table,
                $data,
                array(
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                )
            );
            $key_id = $this->wpdb->insert_id;
            return ['consumer_key' => $consumer_key, 'consumer_secret' => $consumer_secret, 'key_id' => $key_id, 'key_permissions' => $key_permissions];
        }

        protected function getApiKeys()
        {
            $table = $this->wpdb->prefix . 'woocommerce_api_keys';
            $api = $this->wpdb->get_row(
                $this->wpdb->prepare("SELECT * FROM `{$table}` WHERE `description` = '%s' LIMIT 1", [
                    'Oneship Integration'
                ])
            );

            return $api;
        }

        protected function _getUserInfo()
        {
            $user = wp_get_current_user();

            $response['email'] = $user->user_email;
            $response['first_name'] = 'test'; //!empty($user->first_name) ? $user->first_name : $user->user_nicename;
            $response['last_name'] = 'test'; //!empty($user->last_name) ? $user->last_name : '';
            $response['mobile_phone'] = !empty($user->billing_phone) ? $user->billing_phone : '';

            return $response;
        }

        protected function _getCompanyInfo()
        {
            $response = array();
            $country = explode(':', get_option('woocommerce_default_country'));

            $response['name'] = get_option('blogname');
            $response['country_code'] = !empty($country[0]) ? $country[0] : '';

            return $response;
        }

        protected function _getStoreInfo()
        {
            $response = array();
            $response['platform_store_id'] = get_current_network_id();
            $response['name'] = get_option('blogname');
            $response['url'] = get_option('home');
            $response['wc_version'] = $this->woocommerce->version;

            return $response;
        }

        protected function _getAddressInfo()
        {
            $response = array();
            $country = explode(':', get_option('woocommerce_default_country'));
            $city = get_option('woocommerce_store_city');
            $postal_code = get_option('woocommerce_store_postcode');
            $line_1 = get_option('woocommerce_store_address');
            $line_2 = get_option('woocommerce_store_address_2');

            $response['state'] = !empty($country[1]) ? $country[1] : '';
            $response['city'] = !empty($city) ? $city : '';
            $response['postal_code'] = !empty($postal_code) ? $postal_code : '';
            $response['line_1'] = !empty($line_1) ? $line_1 : '';
            $response['line_2'] = !empty($line_2) ? $line_2 : '';

            return $response;
        }

        protected function _genToken($data, $secret)
        {
            $ctx = hash_init('sha256', HASH_HMAC, $secret);
            hash_update($ctx, $data);
            $hash = hash('md5', hash_final($ctx, true));
            return $hash;
        }

        protected function getRandomUserId()
        {
            return 'woc_' . mt_rand(1e9, 1e10 - 1);
        }
    }
}

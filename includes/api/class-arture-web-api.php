<?php
class Arture_Web_Api {

    private $arture_api_url;

    public function __construct() {
        $this -> arture_api_url = "https://www.arture.nl/wp-json/arture_plugins/v1";
    }

    private function curl_request( $request_url ) {

        // Create an instance of the logging unit
        $logging = new StoreContrl_WP_Connection_Logging();

        // Check whether the request url isn't usable
        if ( $request_url == '' || $request_url === null ) {
            return null;
        }

        // Create the api url
        $api_url = $this -> arture_api_url . $request_url;

        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
        );

        // Initialize the connection
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $api_url);
        curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'GET' );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        // Get the response from the api call
        $curl_response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Check for maintenance
        if( $http_code != '200' ){
            return 0;
        }

        // Check whether the request failed
        if ($curl_response === false) {
            $logging -> log_file_write( curl_error($curl) );
        }

        // Close the function
        curl_close($curl);

        // Create the output using the the response
        $output = json_decode($curl_response, true);

        // Check for JSON or array
        if ( empty($output) && !is_array($output) ) {
            $output = $curl_response;
        }

        // Save a message whenever this has been provided
        if ( isset($output['Message']) ){
            $logging -> log_file_write( 'StoreContrl | Message: ' .$output['Message'] );
        }

        return $output;
    }

    public function check_arture_api_key( $api_key ) {

        if ( $api_key == '' || $api_key === null || empty( $api_key ) ) {
            return false;
        }

        $arture_valid_subscription = get_transient('arture_valid_subscription');
        if ( !isset($arture_valid_subscription) || empty($arture_valid_subscription) ) {
            $current_screen = get_current_screen();
            if (!isset($current_screen) || $current_screen->base == 'toplevel_page_storecontrl-wp-connection-panel') {

                $siteurl = str_replace(array('http://', 'https://', 'www.'), '', get_bloginfo('siteurl'));
                $siteurl = str_replace(array('/'), '.', $siteurl);
                $request_url = '/validate/' . $api_key . '/' . $siteurl;
                $results = $this->curl_request($request_url);

                if( isset($results['addons']) && $results['addons'] ) {
                    update_option( 'storecontrl_creditcheques', '1' );
                }
                else{
                    update_option( 'storecontrl_creditcheques', '0' );
                }

                if( isset($results['valid']) ) {
                    set_transient('arture_valid_subscription', 'valid', (86400 * 7));
                    return true;
                }
                else{
                    return false;
                }
            }
        }
        else{
            return true;
        }
    }

    public function display_key_error_message() {

        if( get_current_screen()->base == 'toplevel_page_storecontrl-wp-connection-panel') {

            // Get the API key from the options
            $key = get_option('storecontrl_api_arture_key');

            // Check whether the saved key makes sense
            if( $this->check_arture_api_key($key) == false) {
                // Check whether the key contains the word "trial"
                if (substr($key, 0, 5) == 'trial') {
                    $this->create_message("error", __("You're trying to use a trial key that isn't active. Trial keys are active for a month, you're required to purchase a full license after this period has ended. Visit <a href='http://www.arture.nl/products' title='Arture'>our website</a> for more information.", "storecontrl-wp-connection-plugin"));
                } else {
                    $this->create_message("error", __("The Arture API key is incorrect or inactive.", "storecontrl-wp-connection-plugin"));
                }
            }
        }
    }

    private function create_message($status, $message) {
        // Check whether the values are entered
        if (isset($status) && !empty($status) && $status != '' && $status != null && isset($message) && !empty($message) && $message != '' && $message != null) {
            $status = strtolower($status);
            if ($status != "error" && $status != "success" && $status != "warning" && $status != "info") {
                $status = "info";
            }

            $class = 'notice notice-' . $status;
            printf('<div class="%1$s"><p>StoreContrl | %2$s</p></div>', esc_attr( $class ) ,$message);
        }
    }

    public function display_authorization_message() {

        $functions = new StoreContrl_WP_Connection_Functions();
        $storecontrl_api_url = $functions->getStoreContrlAPIURI();
        $storecontrl_api_images_url = get_option('storecontrl_api_images_url');
        $storecontrl_api_key = get_option('storecontrl_api_key');
        $storecontrl_api_secret = get_option('storecontrl_api_secret');

        // Check if connection available
        if(
            isset($storecontrl_api_url) && !empty($storecontrl_api_url) &&
            isset($storecontrl_api_images_url) && !empty($storecontrl_api_images_url) &&
            isset($storecontrl_api_key) && !empty($storecontrl_api_key) &&
            isset($storecontrl_api_secret) && !empty($storecontrl_api_secret)
        ) {
            $web_api = new StoreContrl_Web_Api();
            $response = $web_api->storecontrl_get_customer_types();
            if ( isset($response['Message']) && $response['Message'] == 'Authorization has been denied for this request.' ) {
                $this->create_message("warning", 'De API moet nog worden goedgekeurd door StoreContrl. Mail de API url door naar <a href="mailto:support@arture.nl" title="Direct mailen">support@arture.nl</a> en wij laten deze verifieren.');
            }
        }
    }

    /**
     * This function is used to display error messages relating to the shipping and payment mappings.
     */
    public function display_missing_mapping() {

        // Check if Woocommerce exist
        if ( class_exists( 'WooCommerce' ) ) {

            // Access the global WooCommerce variable
            global $woocommerce;

            // Get the mappings for the payment and the shipping methods
            $mappings_for_shipping = $this->get_option_value_from_dictionary($woocommerce->shipping->load_shipping_methods(), "storecontrl_wc_shipping_method_");
            $mappings_for_payment = $this->get_option_value_from_dictionary($woocommerce->payment_gateways->payment_gateways(), "storecontrl_wc_payment_method_");

            // Get the result of the mapping check for both mappings
            $shipping_result = $this->check_mapping($mappings_for_shipping, 'Controle | Niet alle Woocommerce verzendmethodes staan ook gekoppeld aan een StoreContrl verzendmethode');
            $payment_result = $this->check_mapping($mappings_for_payment, 'Controle | Niet alle Woocommerce betaalmethodes staan ook gekoppeld aan een StoreContrl betaalmethode');

            // Check both results
            if ($shipping_result !== true || $payment_result !== true) {
                // Create a variable that will store the message that will be posted to the user
                $message = "";

                // Check both results and add anything necessary to the message
                $message = $shipping_result !== true ? $message . $shipping_result : $message;
                $message = $payment_result !== true ? $message . $payment_result : $message;

                // Check whether there is a message
                if ($message != "") {
                    // Create the message box to tell the user about the problem
                    $this->create_message("error", $message);
                }
            }
        }
    }

    /**
     * Check whether mappings are available. Whenever a single mapping contains null, the function returns the result of a custom function. The function returns true otherwise.
     */
    private function check_mapping( $mappings, $on_false = null) {

        // Loop over the mapping
        foreach ( $mappings as $mapping ) {

            if( isset($mapping->enabled) && $mapping->enabled != 'yes' ) {
                continue;
            }

            // Check whether the mapping is missing
            if ( $mapping == "null" ) {
                // Check whether a false value has been provided
                if ( isset( $on_false ) || !empty( $on_false ) || $on_false != null || $on_false != '' ) {
                    // Return the provided value
                    return $on_false;
                } else {
                    // Return a default string
                    return "Error";
                }
            }
        }

        // The mappings are correct, return true
        return true;
    }

    /**
     * This function is used to create an array of options out of a dictionary. The new array shall contain the values of the key from the original dictionary
     */
    private function get_option_value_from_dictionary( $dictionary, $prequel ) {
        // Create an array that stores the eventual array
        $option_array = array();

        // Loop over the dictionary items
        foreach ( $dictionary as $key => $value ) {

            // Get the value of the option
            $option_value = get_option( $prequel . $key);

            // Add the key to the array
            array_push( $option_array, $option_value );
        }

        // Return the array
        return $option_array;
    }

}

<?php
class StoreContrl_Woocommerce_Functions {

    public function __construct() {
        add_action( 'woocommerce_thankyou', array($this, 'add_credit_cheque_data_to_order'), 10, 1 );
        add_action('woocommerce_thankyou', array($this, 'remove_used_credit_coupons'), 10, 1);

        add_filter('bulk_actions-edit-product', array($this, 'add_sc_bulk_action'), 10, 1);
        add_filter('handle_bulk_actions-edit-product', array($this, 'handle_sc_bulk_action'), 10, 3);
        add_action('admin_notices', array($this, 'show_bulk_succes_message'));
    }

    public function add_sc_bulk_action($bulk_actions){
        $bulk_actions['storecontrl-import'] = 'StoreContrl bulk import';
        return $bulk_actions;
    }

    public function handle_sc_bulk_action($redirect_url, $action, $post_ids){
        if ($action == 'storecontrl-import' && !empty($post_ids) ) {

            // Upload directory
            $upload = wp_upload_dir();
            $upload_dir = $upload['basedir'];
            $new_upload_dir = $upload_dir . '/storecontrl/imports';

            $logging = new StoreContrl_WP_Connection_Logging();
            $logging->log_file_write( 'Manual | Manual triggered bulk synchronisation for ' .count($post_ids). ' products.');

            $output = array();
            $web_api = new StoreContrl_Web_Api();
            foreach( $post_ids as $post_id ) {
                $sc_product_id = get_post_meta( $post_id, 'sc_product_id', true );
                $product = $web_api->curl_request("/Product/GetProductInfo/" . $sc_product_id, 'GET');

                $output[$product['product_id']] = $product;

                $api_functions = new StoreContrl_Web_Api_Functions();
                $response = $api_functions->set_product_variations_data($product['sku_list']);
                if( isset($response['variations']) ){
                    $output[$product['product_id']]['variations'] = $response['variations'];
                }
                if( isset($response['product_atributes']) ){
                    $output[$product['product_id']]['product_atributes'] = $response['product_atributes'];
                }
                unset($output[$product['product_id']]['sku_list']);
            }

            file_put_contents($new_upload_dir . '/products_batch_' . $_POST['sc_product_id'] . '.json', json_encode($output));

            $redirect_url = add_query_arg('storecontrl-import-started', count($post_ids), $redirect_url);
        }
        return $redirect_url;
    }

    public function show_bulk_succes_message(){
        if (!empty($_REQUEST['storecontrl-import-started'])) {
            $num_changed = (int) $_REQUEST['storecontrl-import-started'];
            printf('<div id="message" class="updated notice is-dismissable"><p>Bulk synchronisation started for %d products.</p></div>', $num_changed);
        }
    }

    public function register_plugin_metaboxes() {
        add_meta_box( 'storecontrl_product_options', 'StoreContrl', array($this, 'storecontrl_product_options_markup'), 'product', 'side', 'high' );
    }

    public function storecontrl_product_options_markup($object) {
        wp_nonce_field( basename( __FILE__ ), "meta-box-nonce" );

        $sc_product_id = get_post_meta( $object->ID, 'sc_product_id', true );

        if( isset($sc_product_id) && !empty($sc_product_id) ): ?>
            <table>
                <tr>
                    <td style="width: 40%;">ID</td>
                    <td><?php echo $sc_product_id; ?></td>
                </tr>
                <tr>
                    <td style="width: 40%;">Update</td>
                    <td><?php echo get_post_meta( $object->ID, 'latest_update', true ); ?></td>
                </tr>
            </table>
        <?php endif; ?>
        <br/>
        <button class="button button-primary button-large" id="storecontrl_synchronize_product" sc_product_id="<?php echo $sc_product_id; ?>" style="width: 100%; margin-bottom: 5px;"><?php echo __("Synchronize product", 'storecontrl-wp-connection'); ?> <img class="loading" style="display: none; width: 20px; margin: 4px; line-height: 22px; float: right;" src="<?php echo plugins_url( 'admin/img/loading.gif' , dirname(__FILE__ )); ?>"/></button>
        <?php
    }

    public function remove_used_credit_coupons($order_id) {

        if ( ! $order_id )
            return;

        $order = wc_get_order( $order_id );

        $coupon_codes = $order->get_coupon_codes();

        if( isset($coupon_codes) && !empty($coupon_codes) ){
            foreach( $coupon_codes as $coupon_code ){

                $args = array(
                    'post_title'    => $coupon_code,
                    'post_status'   => 'any',
                    'post_type'     => 'shop_coupon'
                );
                $coupon_query = new WP_Query( $args );
                if( isset($coupon_query->posts[0]) ) {

                    if( $coupon_query->posts[0]->post_content == 'StoreContrl spaarpunten coupon' ){
                        wp_delete_post($coupon_query->posts[0]->ID, true);
                    }

                }

            }
        }
    }

    public function add_credit_cheque_data_to_order( $order_id ){

        $couponcode = WC()->session->get('couponcode');
        if( isset($couponcode) && !empty($couponcode) ) {
            update_post_meta($order_id, 'sc_couponcode', $couponcode);
        }
        $coupondiscount = WC()->session->get('coupondiscount');
        if( isset($couponcode) && !empty($couponcode) ) {
            update_post_meta($order_id, 'sc_coupondiscount', $coupondiscount);
        }
    }

    public function sc_enqueue_plugin_scripts(){

        $storecontrl_creditcheques = get_option( 'storecontrl_creditcheques');

        if( isset($storecontrl_creditcheques) && $storecontrl_creditcheques == '1' ) {
            wp_enqueue_script('storecontrl', plugin_dir_url(__FILE__) . '../admin/js/storecontrl.js', array(), '1.0.0', true);
            wp_localize_script('storecontrl', 'storecontrl_object', array('ajaxurl' => admin_url('admin-ajax.php')));
        }
    }

    public function custom_woocommerce_product_settings() {
        add_filter( 'manage_product_posts_columns', array($this, 'add_custom_woocommerce_product_columns') );
        add_action( 'manage_product_posts_custom_column', array($this, 'custom_woocommerce_product_columns_content'), 10, 2 );
        add_filter( 'manage_edit-product_sortable_columns', array($this, 'custom_woocommerce_product_columns_register_sortable') );
        add_filter( 'pre_get_posts', array($this, 'custom_woocommerce_product_columns_orderby'), 10, 2 );
    }

    public function add_custom_woocommerce_product_columns( $columns ){

        // Iterate $columns
        $new_columns = array();
        foreach( $columns as $key => $value ){

            $new_columns[$key] = $value;

            if( $key == 'title' ){
                $new_columns['latest_update'] = 'StoreContrl update';
            }

        }

        return $new_columns;
    }

    public function custom_woocommerce_product_columns_content( $column, $post_id ) {
        switch ( $column ) {

            case 'latest_update' :
                echo get_post_meta( $post_id, 'latest_update', true );
                echo '<br/>';
                echo '<strong>ID: ' .get_post_meta( $post_id, 'sc_product_id', true ). '</strong>';
                break;
        }
    }

    // Register the column as sortable
    public function custom_woocommerce_product_columns_register_sortable( $columns ) {

        // Iterate $columns
        $new_columns = array();
        foreach( $columns as $key => $value ){

            $new_columns[$key] = $value;

            if( $key == 'title' ){
                $new_columns['latest_update'] = 'latest_update';
            }

        }

        return $new_columns;
    }

    public function custom_woocommerce_product_columns_orderby( $query ){
        if (!is_admin())
            return;
        $orderby = $query->get('orderby');
        switch ($orderby) {
            case 'latest_update':
                $query->set('meta_key', 'latest_update');
                $query->set('orderby', 'meta_value_num');
                break;
            default:
                break;
        }
    }

    public function create_storecontrl_new_order( $order_id, $response = false ) {

        // Check if "New order" option is enabled
        $storecontrl_wc_new_order = get_option( 'storecontrl_wc_new_order' );

        $web_api = new StoreContrl_Web_Api();
        $logging = new StoreContrl_WP_Connection_Logging();

        if( isset($storecontrl_wc_new_order) && $storecontrl_wc_new_order == '1' ) {

            global $woocommerce;

            $order = new WC_Order( $order_id );

            $logging->log_file_write('NewOrder | WebOrder status changed to: '.$order->get_status());

            $order_data = $order->get_data();

            if( $order->has_status( 'cancelled' )) {
                update_post_meta($order_id, 'order_returned_successfully_to_storecontrl', '0');
                $web_api->storecontrl_cancel_order($order_id);
            }

            // Check ones more if order is paid and completed
            if( $order->has_status( 'processing' ) || $order->has_status( 'completed' )) {

                $data = array();
                update_post_meta($order_id, 'order_returned_successfully_to_storecontrl', '0');

                $customer_exist = false;

                // Convert date to XML DateTime
                $order_date = $order->get_date_created();
                $timestamp = strtotime($order_date);
                $order_date = date('c', $timestamp);
                $order_date = strtok($order_date, '+');

                // Basic order data
                $data['internet_order_id'] = $order_data['id'];

                // Compatibility for plugin "Aangepaste bestelnummers voor WooCommerce"
                $custom_order_number = get_post_meta($order_data['id'], '_alg_wc_full_custom_order_number', true);
                if( isset($custom_order_number) && !empty($custom_order_number) ){
                    $data['internet_order_id'] = $custom_order_number;
                }

                // If customer exist
                if ($customer_exist) {

                    $data['customer_email'] = $order_data['billing']['email'];
                    $data['billing_address_id'] = '';
                    $data['deliver_address_id'] = '';
                }
                else {

                    // Customer
                    $data['customer']['name'] = str_replace(array('/', '&', '-'), ' ',$order_data['billing']['first_name']);
                    $data['customer']['surname'] = str_replace(array('/', '&', '-'), ' ',$order_data['billing']['last_name']);
                    $data['customer']['phone_number'] = $order_data['billing']['phone'];
                    $data['customer']['email'] = $order_data['billing']['email'];
                    $data['customer']['sex'] = 'unknown';
                    $data['customer']['customer_type_id'] = get_option("storecontrl_wc_customer_type");

                    // Billing
                    $data['customer']['billing_address']['name'] = str_replace(array('/', '&', '-'), ' ',$order_data['billing']['first_name']);
                    $data['customer']['billing_address']['surname'] = str_replace(array('/', '&', '-'), ' ',$order_data['billing']['last_name']);

                    // Check for housenumber
                    if (isset($order_data['billing']['address_2']) && !empty($order_data['billing']['address_2'])) {
                        $housenumber = $order_data['billing']['address_2'];
                    }
                    else {

                        if (!empty(preg_replace("/[^0-9]/", "", $order_data['billing']['address_1']))) {
                            $housenumber = preg_replace("/[^0-9]/", "", $order_data['billing']['address_1']);
                        }
                        else{
                            $housenumber = '';
                        }
                    }
                    $data['customer']['billing_address']['street'] = str_replace($housenumber, '', $order_data['billing']['address_1']);
                    $data['customer']['billing_address']['housenumber'] = $housenumber;
                    $data['customer']['billing_address']['zipcode'] = $order_data['billing']['postcode'];
                    $data['customer']['billing_address']['city'] = $order_data['billing']['city'];

                    // Check if country is already a code and not a full name
                    if (strlen($order_data['billing']['country']) == 2) {
                        $data['customer']['billing_address']['country_code'] = $order_data['billing']['country'];
                    } else {
                        $country_name = $order_data['billing']['country'];
                        $data['customer']['billing_address']['country_code'] = $this->get_country_code($country_name);
                    }

                    // Shipping
                    if (!empty($order_data['shipping']['first_name'])) {
                        $data['customer']['deliver_address']['name'] = str_replace(array('/', '&', '-'), ' ',$order_data['shipping']['first_name']);
                    } else {
                        $data['customer']['deliver_address']['name'] = str_replace(array('/', '&', '-'), ' ',$order_data['billing']['first_name']);
                    }

                    if (!empty($order_data['shipping']['last_name'])) {
                        $data['customer']['deliver_address']['surname'] = str_replace(array('/', '&', '-'), ' ',$order_data['shipping']['last_name']);
                    } else {
                        $data['customer']['deliver_address']['surname'] = str_replace(array('/', '&', '-'), ' ',$order_data['billing']['last_name']);
                    }

                    if (!empty($order_data['shipping']['address_1'])) {
                        $data['customer']['deliver_address']['street'] = $order_data['shipping']['address_1'];
                    } else {
                        $data['customer']['deliver_address']['street'] = $order_data['billing']['address_1'];
                    }

                    if (!empty($order_data['shipping']['address_2'])) {
                        $housenumber = $order_data['shipping']['address_2'];
                    }
                    else {

                        if (isset($order_data['shipping']['address_1']) && !empty($order_data['shipping']['address_1'])) {
                            if (!empty(preg_replace("/[^0-9]/", "", $order_data['shipping']['address_1']))) {
                                $housenumber = preg_replace("/[^0-9]/", "", $order_data['shipping']['address_1']);
                            }
                        } else {
                            if (isset($order_data['billing']['address_2']) && !empty($order_data['billing']['address_2'])) {
                                $housenumber = $order_data['billing']['address_2'];
                            } else {
                                if (!empty(preg_replace("/[^0-9]/", "", $order_data['billing']['address_1']))) {
                                    $housenumber = preg_replace("/[^0-9]/", "", $order_data['billing']['address_1']);
                                }
                            }
                        }
                    }
                    $data['customer']['deliver_address']['street'] = str_replace($housenumber, '', $data['customer']['deliver_address']['street']);
                    $data['customer']['deliver_address']['housenumber'] = $housenumber;

                    if (!empty($order_data['shipping']['postcode'])) {
                        $data['customer']['deliver_address']['zipcode'] = $order_data['shipping']['postcode'];
                    } else {
                        $data['customer']['deliver_address']['zipcode'] = $order_data['billing']['postcode'];
                    }

                    if (!empty($order_data['shipping']['city'])) {
                        $data['customer']['deliver_address']['city'] = $order_data['shipping']['city'];
                    } else {
                        $data['customer']['deliver_address']['city'] = $order_data['billing']['city'];
                    }

                    if (!empty($order_data['shipping']['country'])) {

                        // Check if country is already a code and not a full name
                        if (strlen($order_data['shipping']['country']) == 2) {
                            $data['customer']['deliver_address']['country_code'] = $order_data['shipping']['country'];
                        } else {
                            $country_name = $order_data['shipping']['country'];
                            $data['customer']['deliver_address']['country_code'] = $this->get_country_code($country_name);
                        }
                    } else {

                        // Check if country is already a code and not a full name
                        if (strlen($order_data['billing']['country']) == 2) {
                            $data['customer']['deliver_address']['country_code'] = $order_data['billing']['country'];
                        } else {
                            $country_name = $order_data['billing']['country'];
                            $data['customer']['deliver_address']['country_code'] = $this->get_country_code($country_name);
                        }
                    }
                }

                $data['orderdate'] = $order_date;

                if (isset($order_data['shipping_total']) && !empty($order_data['shipping_total'])) {
                    $shipping_cost = number_format((float)$order_data['shipping_total'], 2, '.', '');
                    $order_total = $order_data['total'];
                } else {
                    $shipping_cost = 0;
                    $order_total = $order_data['total'];
                }
                $data['order_total'] = number_format((float)$order_total, 2, '.', '');

                if( isset($order_data['customer_note']) && !empty($order_data['customer_note']) ){
                    $data['comments'] = $order_data['customer_note'];
                }

                $sc_couponcode = get_post_meta($order_data['id'], 'sc_couponcode', true);
                $sc_coupondiscount = get_post_meta($order_data['id'], 'sc_coupondiscount', true);
                if( isset($sc_couponcode) && !empty($sc_couponcode) && isset($sc_coupondiscount) && !empty($sc_coupondiscount) ){
                    $data['couponcode'] = $sc_couponcode;
                    $data['coupondiscount'] = number_format($sc_coupondiscount, 2, '.', '');
                }

                if( isset($order_data['fee_lines']) && !empty($order_data['fee_lines']) ){
                    foreach( $order_data['fee_lines'] as $fee_line ){
                        $fee_name = $fee_line->get_name();
                        $fee_name = strtolower($fee_name);
                        $fee_total = $fee_line->get_total();
                        $fee_total_tax = $fee_line->get_total_tax();

                        $fee_amount = $fee_total + $fee_total_tax;

                        if( in_array($fee_name, array('bulk discount', 'bulkkorting')) ){
                            $data['coupondiscount'] = abs(number_format($fee_amount, 2, '.', ''));
                        }

                        if(
                            strpos($fee_name, 'bezorging') !== false
                            ||
                            strpos($fee_name, 'levering') !== false
                            ||
                            strpos($fee_name, 'inpakken') !== false
                            ||
                            strpos($fee_name, 'toeslag') !== false
                            ||
                            strpos($fee_name, 'kaartje') !== false
                        ){
                            $shipping_cost += $fee_total;
                        }

                        if( $fee_name == 'gateway fee' ){
                            $shipping_cost += $fee_total;
                        }
                    }
                }

                if( !$data['coupondiscount'] || is_null($data['coupondiscount']) && empty($data['coupondiscount']) ){
                    unset($data['coupondiscount']);
                    unset($data['couponcode']);
                }

                if( isset($order_data['shipping_tax']) && $order_data['shipping_tax'] != 0 ){
                    $shipping_cost = (float)$shipping_cost + (float)$order_data['shipping_tax'];
                }
                $data['shipping_cost'] = $shipping_cost;

                $shipping_methods = $order->get_shipping_methods();
                if( isset($shipping_methods) && !empty($shipping_methods) ){
                    foreach ($shipping_methods as $shipping_method) {
                        $order_shipping_method = strtok($shipping_method->get_method_id(), ':');
                        $value = get_option("storecontrl_wc_shipping_method_" . $order_shipping_method);
                        $data['shipping_method'] = (int)$value;
                    }
                }

                // Set default if exist and no other method apply
                $storecontrl_wc_shipping_method_default = get_option( "storecontrl_wc_shipping_method_default" );
                if( isset($storecontrl_wc_shipping_method_default) && !empty($storecontrl_wc_shipping_method_default) && empty($data['shipping_method']) ){
                    $data['shipping_method'] = $storecontrl_wc_shipping_method_default;
                }

                // Set StoreContrl payment method id
                $wc_payment_gateways = $woocommerce->payment_gateways->payment_gateways();
                $order_payment_method = $order->get_payment_method();
                foreach ($wc_payment_gateways as $key => $wc_payment_gateway) {
                    if ($wc_payment_gateway->id === $order_payment_method) {
                        $data['payment_methods']['payment_method']['payment_id'] = get_option("storecontrl_wc_payment_method_" . $key);
                        $data['payment_methods']['payment_method']['partial_amount'] = number_format((float)$order_data['total'], 2, '.', '');
                    }
                }

                // Set default if exist and no other method apply
                $storecontrl_wc_payment_method_default = get_option( "storecontrl_wc_payment_method_default" );
                if( isset($storecontrl_wc_payment_method_default) && !empty($storecontrl_wc_payment_method_default) && empty($data['payment_methods']['payment_method']['payment_id']) ){
                    $data['payment_methods']['payment_method']['payment_id'] = $storecontrl_wc_payment_method_default;
                    $data['payment_methods']['payment_method']['partial_amount'] = number_format((float)$order_data['total'], 2, '.', '');
                }

                $regular_order_total = 0;
                $discount_amount = 0;
                $order_products = $order->get_items();
                foreach ($order_products as $order_product) {

                    $order_detail = array();

                    $product_data = $order_product->get_data();

                    // If variation ID not exist get it by title
                    if( !isset($product_data['variation_id']) || empty($product_data['variation_id']) ){
                        $args = array(
                            "post_type"   => "product_variation",
                            "post_parent" => $product_data['product_id'],
                            "s"           => $product_data['name']
                        );
                        $variation_post = get_posts( $args );

                        if( isset($variation_post[0]->ID) ){
                            $product_data['variation_id'] = $variation_post[0]->ID;
                        }
                    }

                    $storecontrl_size_id = (int)get_post_meta($product_data['variation_id'], 'storecontrl_size_id', true);
                    $retail_price = (float)get_post_meta($product_data['variation_id'], '_regular_price', true);
                    $retail_price = number_format($retail_price, 2, '.', '');

                    if( empty($storecontrl_size_id) ){
                        $data['order_total'] = ($data['order_total'] - $retail_price);
                        $data['payment_methods']['payment_method']['partial_amount'] = ($data['payment_methods']['payment_method']['partial_amount'] - $retail_price);
                        continue;
                    }

                    $order_detail['size_id'] = $storecontrl_size_id;
                    $order_detail['count'] = $product_data['quantity'];
                    $order_detail['retail_price'] = $retail_price;

                    if( isset($product_data['total_tax']) && $product_data['total_tax'] != 0 ){
                        $selling_price = $product_data['total'] + $product_data['total_tax'];
                    }
                    else{
                        $selling_price = $product_data['total'];
                    }

                    $selling_price = number_format(round($selling_price, 2) / $product_data['quantity'], 3, '.', '');

                    // Check if dfifference is lower than 1 cent
                    if( $retail_price != $selling_price ){
                        $difference_between = abs($retail_price - $selling_price );
                        if( $difference_between <= 0.01 ){
                            $selling_price = $retail_price;
                        }
                    }
                    $order_detail['selling_price'] = $selling_price;

                    if( isset($data['coupondiscount']) && !empty($data['coupondiscount']) ){
                        $order_detail['selling_price'] = $order_detail['retail_price'];
                    }

                    $regular_order_total += number_format($retail_price * $product_data['quantity'], 3, '.', '');
                    $product_discount = $retail_price - $selling_price;
                    $discount_amount += number_format($product_discount * $product_data['quantity'], 3, '.', '');

                    $data['order_details'][] = $order_detail;
                }

                if( $discount_amount > 0 ){
                    $data['discount_amount'] = $discount_amount;
                    $data['discount_percentage'] = number_format(($discount_amount * 100) / $regular_order_total, 2, '.', '');
                }
                elseif( isset($order_data['discount_total']) && !empty($order_data['discount_total']) ){
                    $discount_amount = $order_data['discount_total'] + $order_data['discount_tax'];
                    $data['discount_amount'] = number_format($discount_amount, 2, '.', '');
                }


                $logging = new StoreContrl_WP_Connection_Logging();
                if( !isset($data['order_details']) || empty($data['order_details']) ){
                    $logging->log_file_write('NewOrder | Order has no StoreContrl products');

                    // AJAX call
                    if( $response ){
                        return 'NewOrder | Order has no StoreContrl products';
                    }
                }

                // De korting kan nooit hoger zijn dan het order totaal!
                if( $data['order_total'] == '0.00' && isset($data['coupondiscount']) && isset($data['discount_amount']) ){
                    $data['coupondiscount'] = $data['discount_amount'];
                }

                // Convert data array to xml
                $functions = new StoreContrl_WP_Connection_Functions();
                $xml_data = $functions->array_to_xml($data, new SimpleXMLElement('<order/>'), 'order_detail');

                // Save order and customer in StoreContrl
                $results = $web_api->storecontrl_new_order($xml_data);

                // Check for already used discount rules
                $message = (string)$results;
                if( $message == 'Ordertotal does not match the sum of the detailtotals or paymentmethods, order is refused' ){
                    unset($data['couponcode']);
                    unset($data['coupondiscount']);
                    unset($data['discount_amount']);
                    unset($data['discount_percentage']);
                    $xml_data = $functions->array_to_xml($data, new SimpleXMLElement('<order/>'), 'order_detail');
                    $results = $web_api->storecontrl_new_order($xml_data);
                }

                $message = (string)$results;
                if ($results == 'Order processed') {
                    $message = 'Order processed: ' . $order_data['id'];
                    update_post_meta($order_id, 'order_returned_successfully_to_storecontrl', '1');
                } elseif ($results == 'OrderId already exists') {
                    update_post_meta($order_id, 'order_returned_successfully_to_storecontrl', '1');
                    $message = 'OrderId already exists: ' . $order_data['id'];
                }
                $logging->log_file_write('NewOrder | ' . $message);

                // AJAX call
                if( $response ){
                    return $results;
                }
            }
        }
    }

    public function get_country_code( $country_name ){

        $country_codes = array(
            'AF' => 'Afghanistan',
            'AX' => 'Aland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BQ' => 'Bonaire, Saint Eustatius and Saba',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'VG' => 'British Virgin Islands',
            'BN' => 'Brunei',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CW' => 'Curacao',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'CD' => 'Democratic Republic of the Congo',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'TL' => 'East Timor',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and McDonald Islands',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'CI' => 'Ivory Coast',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'XK' => 'Kosovo',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Laos',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'KP' => 'North Korea',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'CG' => 'Republic of the Congo',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SX' => 'Sint Maarten',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands',
            'KR' => 'South Korea',
            'SS' => 'South Sudan',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard and Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syria',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'VI' => 'U.S. Virgin Islands',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Minor Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VA' => 'Vatican',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        );

        $country_code = array_search( $country_name, $country_codes );

        return $country_code;
    }

    public function add_storecontrl_order_returned_column($columns) {
        $columns['storecontrl_feedback'] = __('StoreContrl terugkoppeling');
        return $columns;
    }

    public function add_storecontrl_order_returned_column_value($column, $order_id = '') {

        if ($column == 'storecontrl_feedback') {

            if( empty($order_id) ){
                global $post;
                $order_id = $post->ID;
                $order    = wc_get_order( $order_id );
            }
            else{
                if( is_object($order_id) ){
                    $order    = $order_id;
                    $order_id = $order->get_id();
                }
                else{
                    $order    = wc_get_order( $order_id );
                }
            }

            //$order    = wc_get_order( $post->ID );
            if (strlen(get_post_meta($order_id, 'order_returned_successfully_to_storecontrl', true)) > 0) {
                //ORDER HAS BEEN SENT TO STORECONTRL
                if (get_post_meta($order_id, 'order_returned_successfully_to_storecontrl', true) == '1') {
                    echo __("Succesvol teruggekoppeld");
                }
                else if ($order->has_status( 'processing') || $order->has_status( 'completed')) {
                    echo "<button name='resend_new_order_to_storecontrl' order_id='".$order_id."'>Verstuur opnieuw naar StoreContrl</button>";
                }
                else {
                    echo __("Wacht op order-status 'processing' of 'completed'");
                }
            }
            else if ($order->has_status( 'processing') || $order->has_status( 'completed')) {
                echo "<button name='resend_new_order_to_storecontrl' order_id='".$order_id."'>Verstuur opnieuw naar StoreContrl</button>";
            }
            else {
                echo __("Wacht op order-status 'processing' of 'completed'");
            }
        }
    }

    public function check_storecontrl_credit_cheque( ) {

        $credit_cheque = str_replace(' ', '', $_POST['credit_cheque']);

        // Get data from resulting URL
        $request_url = '/Discount/GetCreditChequeValue?code='.$credit_cheque;
        $args = array(
            'content_type' => 'application/json',
            'has_sessionId' => false
        );

        $web_api = new StoreContrl_Web_Api();
        $results = $web_api->curl_request( $request_url, 'GET', $args );

        if( isset($results) && $results != 'Invalid ChequeCode or CreditCheque inactive' ) {
            wp_send_json_success($results);
        }
        else {
            wp_send_json_error($results);
        }
    }

    public function add_storecontrl_cart_fee( $cart ){
        $storecontrl_creditcheques = get_option( 'storecontrl_creditcheques');
        if( isset($storecontrl_creditcheques) && $storecontrl_creditcheques == '1' ) {

            global $woocommerce;

            if (isset($_GET['cheque']) && !empty($_GET['cheque'])) {

                $credit_cheque = str_replace(' ', '', $_GET['cheque']);

                // Get data from resulting URL
                $request_url = '/Discount/GetCreditChequeValue?code=' . $credit_cheque;
                $args = array(
                    'content_type' => 'application/json',
                    'has_sessionId' => false
                );

                $web_api = new StoreContrl_Web_Api();
                $results = $web_api->curl_request($request_url, 'GET', $args);

                if (isset($results) && $results != 'Invalid ChequeCode or CreditCheque inactive') {
                    $discount = str_replace('ChequeValue: ', '', $results);
                    $discount = str_replace(',', '.', $discount);

                    // Check if coupon already exist
                    $args = array(
                        's'             => $_GET['cheque'],
                        'post_status'   => 'any',
                        'post_type'     => 'shop_coupon',
                        'fields'        => 'ids'
                    );
                    $coupon_query = new WP_Query( $args );
                    if( empty($coupon_query->posts) ) {

                        // Create a coupon programatically
                        $coupon = array(
                            'post_title' => $_GET['cheque'],
                            'post_content' => 'StoreContrl spaarpunten coupon',
                            'post_status' => 'publish',
                            'post_type' => 'shop_coupon'
                        );
                        $new_coupon_id = wp_insert_post($coupon);
                        update_post_meta($new_coupon_id, 'discount_type', 'fixed_cart');
                        update_post_meta($new_coupon_id, 'coupon_amount', $discount);
                        update_post_meta($new_coupon_id, 'individual_use', 'yes');
                        update_post_meta($new_coupon_id, 'product_ids', '');
                        update_post_meta($new_coupon_id, 'exclude_product_ids', '');
                        update_post_meta($new_coupon_id, 'usage_limit', '1');
                        update_post_meta($new_coupon_id, 'expiry_date', '');
                        update_post_meta($new_coupon_id, 'apply_before_tax', 'no');
                        update_post_meta($new_coupon_id, 'free_shipping', 'no');
                    }

                    WC()->cart->apply_coupon($_GET['cheque']);

                    $woocommerce->session->set('couponcode', $_GET['cheque']);
                    $woocommerce->session->set('coupondiscount', $discount);
                }
            }
        }
    }

    public function show_size_id_field_variation_data( $loop, $variation_data, $variation ) {
        echo '<div><p class="form-field _field form-row form-row-full"><label>StoreContrl ID: </label><button style="clear: both;display: block;padding: 2px 10px;background: #00adf2;border-radius: 2px;border: none;color: #fff;">'.get_post_meta( $variation->ID, 'storecontrl_size_id', true ).'</button></p></div>';
    }
}

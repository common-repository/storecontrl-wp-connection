<?php
class StoreContrl_Cronjob_Functions {

    private $functions;
    private $logging;
    private $storecontrl_api_images_url;
    private $storecontrl_api_ftp_user;
    private $storecontrl_api_ftp_password;
    private $storecontrl_api_url;
    private $cpu_count;

    public static $storecontrl_ftp_connection;
    public static $storecontrl_ftp_login;
    public static $storecontrl_ftp_pasv;
    public static $storecontrl_ftp_chdir;

    public function __construct() {

        $this->functions = new StoreContrl_WP_Connection_Functions();
        $this->logging = new StoreContrl_WP_Connection_Logging();

        $this->storecontrl_api_ftp_user = get_option('storecontrl_api_ftp_user');
        $this->storecontrl_api_ftp_password = get_option('storecontrl_api_ftp_password');

        $this->storecontrl_api_url = $this->functions->getStoreContrlAPIURI();
        $this->storecontrl_api_images_url = get_option('storecontrl_api_images_url');

        $hostname = gethostname();
        if( str_contains($hostname, 'Arture') ) {
            $this->cpu_count = $this->get_CPU_Core_Count();
        }
        else{
            $this->cpu_count = '';
        }

    }

    private function get_CPU_Core_Count() {
        return (int) shell_exec("cat /proc/cpuinfo | grep processor | wc -l");
    }

    public function storecontrl_url_handler() {



        // STAP 1 | Alleen handmatig uitvoeren bij start van webshop
        if( $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-total-synchronisation') ){
            $this->logging->log_file_write( 'INIT | Total Synchronisation' );
            $this->init_total_synchronisation();
            http_response_code(200);
        }

        // Check CPU load foreach 5 minute cronjob trigger
        if(
            $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-products')
            ||
            $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-changes')
            ||
            $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-stockchanges')
        ){
            if( !empty($this->cpu_count) ) {
                sleep(rand(5, 60));

                $try = 1;
                $CPUCount = $this->cpu_count;
                do {
                    $load = sys_getloadavg();
                    if ($load[0] > ($CPUCount * 2) - 1) {

                        if ($try == 3) {
                            $this->logging->log_file_write('Notice | CPU TO HIGH! - ' .$load[0]);
                            exit;
                        }

                        sleep(rand(30, 60));
                    } else {
                        break;
                    }
                    $try++;
                } while ($try < 4);

                if( $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-products') ){
                    $this->process_imported_storecontrl_batches();
                    http_response_code(200);
                }

                if( $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-changes') ){
                    $this->process_changes_wc_products();
                    http_response_code(200);
                }

                if( $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-stockchanges') ){

                    // Upload directory
                    $upload = wp_upload_dir();
                    $upload_dir = $upload['basedir'];
                    $directory = $upload_dir . '/storecontrl/imports';
                    $files = scandir($directory);

                    // Check for existing processing
                    if( count($files) <= 3) {
                        $this->update_wc_product_stock_changes();
                    }
                    http_response_code(200);
                }
            }
            else{
                if( $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-products') ){
                    $this->process_imported_storecontrl_batches();
                    http_response_code(200);
                }

                if( $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-changes') ){
                    $this->process_changes_wc_products();
                    http_response_code(200);
                }

                if( $this->endsWith($_SERVER["REQUEST_URI"], '/storecontrl-process-stockchanges') ){

                    // Upload directory
                    $upload = wp_upload_dir();
                    $upload_dir = $upload['basedir'];
                    $directory = $upload_dir . '/storecontrl/imports';
                    $files = scandir($directory);

                    // Check for existing processing
                    if( count($files) <= 3) {
                        $this->update_wc_product_stock_changes();
                    }
                    http_response_code(200);
                }
            }
        }

    }

    public function storecontrl_refresh_masterdata(){
        $this->storecontrl_set_masterdata();

        wp_send_json_success(
            array(
                'html' => '<div class="notice notice-success"><p>'. __( 'Masterdata saved', 'storecontrl-wp-connection-plugin' ). '</p></div>'
            )
        );
    }

    public function storecontrl_set_masterdata( $hide_logs = false ){

        global $wpdb;

        $storecontrl_masterdata = array();

        $web_api = new StoreContrl_Web_Api();
        $args = array(
            'content_type' => 'application/json',
            'has_sessionId' => false
        );

        // 1. Retrieve VAT rates by API-Call GetVatRates
        $VatRates = $web_api->curl_request("/Data/GetVatRates", 'GET', $args);
        $tax_rate_classes = $wpdb->get_results("SELECT * FROM {$wpdb->wc_tax_rate_classes} ORDER BY name;");
        $tax_rate_classes_names = wp_list_pluck( $tax_rate_classes, 'slug' );

        foreach( $VatRates as $vatRate ){

            $tax_class_name = 'StoreContrl ' .$vatRate['vat_rate_name'];
            $tax_class_slug = sanitize_title($tax_class_name);

            if( in_array($tax_class_slug, $tax_rate_classes_names) ){

                $key = array_search($tax_class_slug, array_column($tax_rate_classes, 'slug'));
                $vatRate['wc_vat_rate_id'] = $tax_rate_classes[$key]->tax_rate_class_id;
                $vatRate['_tax_class'] = $tax_class_slug;
                $storecontrl_masterdata['VatRates'][$vatRate['vat_rate_id']] = $vatRate;
            }
            else {
                // Create tax classs
                $tax_class = WC_Tax::create_tax_class($tax_class_name, $tax_class_slug);

                // Check if vat rate already exist
                if (!isset($tax_class->errors)) {

                    // Attached the tax_rate to tax_class
                    $tax_rate_data = array(
                        'tax_rate_country' => '*',
                        'tax_rate_state' => '*',
                        'tax_rate' => 100 * $vatRate['vat_rate_percentage'],
                        'tax_rate_name' => $vatRate['vat_rate_name'],
                        'tax_rate_priority' => 1,
                        'tax_rate_compound' => 0,
                        'tax_rate_shipping' => 0,
                        'tax_rate_order' => 0,
                        'tax_rate_class' => $tax_class_slug
                    );
                    $wc_vat_rate_id = WC_Tax::_insert_tax_rate($tax_rate_data);

                    $vatRate['wc_vat_rate_id'] = $wc_vat_rate_id;
                    $vatRate['_tax_class'] = $tax_class_slug;
                    $storecontrl_masterdata['VatRates'][$vatRate['vat_rate_id']] = $vatRate;

                }
            }

        }

        // 2. Retrieve master data by API-Call GetAllMasterdataInfo
        $MasterdataInfo = $web_api->curl_request("/Data/GetAllMasterdataInfo", 'GET', $args);

        // Check for error message
        if( isset($MasterdataInfo['Message']) ){
            wp_send_json_error(
                array(
                    'html' => '<div class="notice notice-error"><p>'. $MasterdataInfo['Message']. '</p></div>'
                )
            );
        }

        $MasterDataCategories = $this->MappAllMasterdataInfo( $MasterdataInfo );
        $storecontrl_masterdata['MasterdataInfo'] = $MasterDataCategories;

        if( !$hide_logs) $this->logging->log_file_write( 'GetAllMasterdataInfo | DONE' );

        // 3. Retrieve variation bar by API-Call GetAllVariationInfo
        $MasterDataVariations = $web_api->curl_request("/Variation/GetAllVariationInfo", 'GET', $args);
        $storecontrl_masterdata['VariationInfo'] = array();
        if( isset($MasterDataVariations) && !empty($MasterDataVariations) ){
            foreach( $MasterDataVariations as $MasterDataVariation ){
                $mapped_variations = array();
                foreach( $MasterDataVariation['variation_members'] as $variation ){
                    $mapped_variations[$variation['variation_member_id']] = $variation;
                    $storecontrl_masterdata['VariationInfo'][$variation['variation_member_id']] = $variation;

                    $variation_name = $this->generate_clean_variation_name($MasterDataVariation['variation_name']);
                    $mapped_variations[$variation['variation_member_id']]['variation_name'] = $variation_name;
                    $storecontrl_masterdata['VariationInfo'][$variation['variation_member_id']]['variation_name'] = $variation_name;
                }

                $storecontrl_masterdata['Attributes'][$MasterDataVariation['variation_id']] = array(
                    'variation_id'      => $MasterDataVariation['variation_id'],
                    'variation_name'    => $this->generate_clean_variation_name($MasterDataVariation['variation_name']),
                    'variation_members' => $mapped_variations
                );
            }
        }
        if( !$hide_logs) $this->logging->log_file_write( 'GetAllVariationInfo | DONE' );

        // 4. Retrieve discount information by product by API-Call GetAllDiscountInfo
        $discounts = $web_api->curl_request("/Discount/GetAllDiscountInfo", 'GET', $args);
        $mapped_discounts = array();
        if( isset($discounts) && !empty($discounts) ){
            foreach( $discounts as $discount ){
                $mapped_discounts[$discount['discount_id']] = $discount;
            }
        }
        $storecontrl_masterdata['DiscountInfo'] = $mapped_discounts;
        if( !$hide_logs) $this->logging->log_file_write( 'GetAllDiscountInfo | DONE' );

        // 5. Retrieve productinformation by API-Call GetAllWebshopProducts
        $products = $web_api->curl_request("/Product/GetAllWebshopProducts", 'GET', $args);
        if( !$hide_logs) $this->logging->log_file_write( 'GetAllWebshopProducts | ' .count($products) );

        if( isset($products) && !empty($products) ) {
            foreach( $products as $product ) {
                $output[$product['product_id']] = $product;
            }
        }

        // Save Masterdata
        $upload     = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $directory  = $upload_dir . '/storecontrl';
        file_put_contents($directory . '/MasterData.json', json_encode($storecontrl_masterdata));
        if( !$hide_logs) $this->logging->log_file_write( 'MasterData | SAVED' );

        return $output;
    }

    public function storecontrl_synchronize_product( $sc_product_id = '' )
    {

        // Upload directory
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $new_upload_dir = $upload_dir . '/storecontrl/imports';

        if( isset($_POST['sc_product_id']) && !empty($_POST['sc_product_id']) || !empty($sc_product_id) ) {

            if( isset($_POST['sc_product_id']) && !empty($_POST['sc_product_id']) ){
                $sc_product_id = $_POST['sc_product_id'];
            }

            $this->logging->log_file_write( 'Manual | Manual triggered synchronisation for product: ' .$sc_product_id);

            $web_api = new StoreContrl_Web_Api();
            $output = array();
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

            file_put_contents($new_upload_dir . '/products_batch_' . $sc_product_id . '.json', json_encode($output));
        }
    }

    public function init_total_synchronisation( $product_ids = array() ){

        // Set new cronjob time
        if( empty($product_ids) ) {
            update_option('storecontrl_init_sync', date('Y-m-d H:i'));
            $output = $this->storecontrl_set_masterdata( );
        }
        else{
            $output = $this->storecontrl_set_masterdata(true);
        }

        $web_api = new StoreContrl_Web_Api();

        //6. Retrieve sku information by API-Call GetAllSkuInfo
        $pagenumber = 1;
        $api_functions = new StoreContrl_Web_Api_Functions();
        do {
            $received_variations_count = 0;

            $barcode_as_sku = get_option('storecontrl_set_barcode_as_sku');
            if ($barcode_as_sku == 1 ) {
                $bulk_variations = $web_api->curl_request("/Sku/GetAllSkuInfo?pagesize=300&pagenumber=" . $pagenumber . "&sendEan=true&allSkuSync=true&groupSizesPerProduct=true", 'GET');
            }
            else{
                $bulk_variations = $web_api->curl_request("/Sku/GetAllSkuInfo?pagesize=300&pagenumber=".$pagenumber."&allSkuSync=true&groupSizesPerProduct=true", 'GET');
            }

            foreach( $bulk_variations as $sku_variations ) {

                if( $sku_variations['product_id'] == '-2147483648' ){
                    continue;
                }

                $response = $api_functions->set_product_variations_data($sku_variations['sku_list']);
                if( isset($output[$sku_variations['product_id']]['variations']) && !empty($output[$sku_variations['product_id']]['variations']) ){
                    $output[$sku_variations['product_id']]['variations'] = array_merge($output[$sku_variations['product_id']]['variations'], $response['variations']);
                }
                elseif( isset($response['variations']) ){
                    $output[$sku_variations['product_id']]['variations'] = $response['variations'];
                }
                else{
                    $this->logging->log_file_write( 'GetAllSkuInfo | Error no product variations found for: ' .$sku_variations['product_id'] );
                    $output[$sku_variations['product_id']]['variations'] = array();
                }

                if( isset($output[$sku_variations['product_id']]['product_atributes']) && !empty($output[$sku_variations['product_id']]['product_atributes']) ){
                    $output[$sku_variations['product_id']]['product_atributes'] = array_merge($output[$sku_variations['product_id']]['product_atributes'], $response['product_atributes']);
                }
                elseif( isset($response['product_atributes']) ){
                    $output[$sku_variations['product_id']]['product_atributes'] = $response['product_atributes'];
                }
                else{
                    $this->logging->log_file_write( 'GetAllSkuInfo | Error no product attributes found for: ' .$sku_variations['product_id'] );
                    $output[$sku_variations['product_id']]['product_atributes'] = array();
                }

                foreach( $sku_variations['sku_list'] as  $sku_variation ) {
                    $received_variations_count++;
                }
            }

            $pagenumber++;
        } while ($received_variations_count >= 100);
        if( empty($product_ids) ) $this->logging->log_file_write( 'GetAllSkuInfo | DONE' );

        // Process all products
        if( isset($output) && !empty($output) ) {

            // Upload directory
            $upload = wp_upload_dir();
            $upload_dir = $upload['basedir'];
            $new_upload_dir = $upload_dir . '/storecontrl/imports';

            $batch = 0;
            $i = 0;
            $total_sychronized_products = 0;
            $total_products = count($output);
            if( !empty($product_ids) ) $total_products = count($product_ids);
            $products_array = array();
            foreach( $output as $product ) {

                // Process only specific product(s) manual
                if( !empty($product_ids) && !in_array( $product['product_id'], $product_ids) ) {
                    continue;
                }

                $products_array[ $product['product_id'] ] = $product;
                $total_sychronized_products ++;

                // 50 producten per batch
                if ( ( ( $i + 1 ) % 50 == 0 ) || $i + 1 == $total_products ) {

                    // Save and encode array to json
                    $products_array = json_encode($products_array);

                    $success = file_put_contents($new_upload_dir . '/products_batch_' . $batch . '.json', $products_array);
                    if( $success === FALSE ){
                        $this->logging->log_file_write( 'ERROR | Product batch ' .$batch. ' not created!' );
                    }

                    $products_array = array();
                    $batch++;
                }

                $i++;

            }
        }

        if( empty($product_ids) ) {
            $this->logging->log_file_write( 'ProcessProducts | DONE' );
            wp_send_json_success(
                array(
                    'html' => '<div class="notice notice-success"><p>' . __('Manual triggered the init synchronization', 'storecontrl-wp-connection-plugin') . '</p></div>'
                )
            );
        }
    }

    public function process_imported_storecontrl_batches( ) {

        global $wpdb;

        date_default_timezone_set('Europe/Amsterdam');

        // Load classes
        $api_functions = new StoreContrl_Web_Api_Functions();

        // Check if cronjob function hasn't been doing anything in the past minute => restart function
        $processing_batch_time = get_option('processing_batch_time');
        $past_minute = time() - 60*6;
        if( (isset($processing_batch_time) && $processing_batch_time >= $past_minute) ){
            $this->logging->log_file_write( 'Product cronjob | Processing batch files already running' );
            return;
        }

        $this->logging->log_file_write( 'Product cronjob | Processing batches' );

        // Upload directory
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $directory = $upload_dir . '/storecontrl/imports';

        // NEW - Load newest batches first
        $files = scandir($directory);

        // Check if files exist ( always two files: . and .. )
        if( isset($files) && count($files) > 2 ) {

            $this::$storecontrl_ftp_connection = $this->GetFTPConnection();
            natcasesort( $files );

            // Iterate all batch files
            foreach ( $files as $file ) {
                if ( '.' === $file || '..' === $file || is_dir( $directory.'/'.$file ) ) {
                    continue;
                }

                // Skip temp batches
                if( strpos($file, 'TEMP') !== false ){
                    continue;
                }

                $this->logging->log_file_write( 'Cronjob | Process batch: ' . $file );


                // Delete orphaned product variations
                $table_name = $wpdb->prefix . 'posts';
                $wpdb->query("DELETE o FROM $table_name o LEFT OUTER JOIN $table_name r ON o.post_parent = r.ID WHERE r.id IS null AND o.post_type = 'product_variation'");

                // Open file
                $data_array = file_get_contents( $directory . '/' . $file );
                $results        = json_decode( $data_array );

                $file_finished = true;

                if( isset($results->Message) ){
                    $this->logging->log_file_write('ERROR | ' . $results->ExceptionMessage);
                }
                elseif ( json_last_error() == JSON_ERROR_NONE ) {

                    switch (true) {

                        // Product batch processing
                        case (strpos($file, 'products_added') !== false || strpos($file, 'products_batch') !== false || strpos($file, 'test_batch') !== false  ):
                            $products = $results;
                            $results = (array) $results;

                            // Check for full sync
                            $full_sync = false;
                            if( strpos($file, 'products_batch') !== false ){
                                $full_sync = true;
                            }

                            // Iterate products batch
                            $update_counter = 0;
                            $first_batch_product_log = true;
                            foreach ( $results as $key => $product ) {

                                // CPU load check
                                if( !empty($this->cpu_count) ) {
                                    $CPUCount = $this->cpu_count;
                                    $load = sys_getloadavg();
                                    if ($load[0] > ($CPUCount * 2) - 1) {
                                        $this->logging->log_file_write('Notice | Stop processing with load: ' . $load[0]);
                                        exit;
                                    }
                                }

                                // Object to array
                                $product = (array) $product;

                                // Check if product is already processed
                                if( isset($product['processed']) && $product['processed'] == 'true' ) {
                                    continue;
                                }

                                // Check if product is already processed
                                if( $first_batch_product_log ) {
                                    $this->logging->log_file_write( 'Product cronjob | Start by product: ' . $product['product_id'] );

                                    $first_batch_product_log = false;
                                }

                                // Check for empty batch
                                if( $product['product_id'] == '0' && count($results) == 1 ){
                                    $this->logging->log_file_write( 'ERROR | Empty product batch deleted!' );
                                    unlink($directory . '/' . $file);
                                }

                                // Save/Update product
                                $response = $api_functions->storecontrl_update_wc_product( $product, $full_sync );

                                // Check if product is processed
                                if( $response == 'true' ) {
                                    $update_counter++;
                                    $products->$key->processed = 'true';
                                    update_option( 'processing_batch_time', time() );
                                }

                                // Check if product is processed
                                if( $update_counter == 5 ) {
                                    $update_counter = 0;
                                    file_put_contents( $directory . '/' . $file, json_encode($products) );
                                }
                            }
                            break;

                        // Product batch processing
                        case (strpos($file, 'products_changed') !== false):
                            $products = $results;
                            $results = (array) $results;

                            // Iterate products batch
                            $first_batch_product_log = true;
                            foreach ( $results as $key => $product ) {

                                // CPU load check
                                if( !empty($this->cpu_count) ) {
                                    $CPUCount = $this->cpu_count;
                                    $load = sys_getloadavg();
                                    if ($load[0] > ($CPUCount * 2) - 1) {
                                        $this->logging->log_file_write('Notice | Stop processing with load: ' . $load[0]);
                                        exit;
                                    }
                                }

                                // Object to array
                                $product = (array) $product;

                                // Check if product is already processed
                                if( isset($product['processed']) && $product['processed'] == 'true' ) {
                                    continue;
                                }

                                // Check if product is already processed
                                if( $first_batch_product_log ) {
                                    $this->logging->log_file_write( 'Cronjob | Start by product: ' . $product['product_id'] );

                                    $first_batch_product_log = false;
                                }

                                // Check for empty batch
                                if( $product['product_id'] == '0' && count($results) == 1 ){
                                    $this->logging->log_file_write( 'ERROR | Empty product batch deleted!' );
                                    unlink($directory . '/' . $file);
                                }

                                // Save/Update product
                                $response = $api_functions->storecontrl_update_wc_product( $product );

                                // Check if product is processed
                                if( $response == 'true' ) {
                                    $products->$key->processed = 'true';
                                    file_put_contents( $directory . '/' . $file, json_encode($products) );

                                    // Update time after every succefull processed product
                                    $processing_batch_time = time();
                                    update_option( 'processing_batch_time', $processing_batch_time );
                                }
                            }
                            break;

                        // SKu Added batch processing
                        case (strpos($file, 'sku_added') !== false):

                            $all_variations = (array)$results;
                            $results = (array) $results;

                            // Iterate products batch
                            foreach ( $results as $key => $product_variations) {

                                // CPU load check
                                if( !empty($this->cpu_count) ) {
                                    $CPUCount = $this->cpu_count;
                                    $load = sys_getloadavg();
                                    if ($load[0] > ($CPUCount * 2) - 1) {
                                        $this->logging->log_file_write('Notice | Stop processing with load: ' . $load[0]);
                                        exit;
                                    }
                                }

                                $product_variations = (array)$product_variations;

                                // Check if product is already processed
                                if( isset($product_variations['processed']) && $product_variations['processed'] == 'true' ) {
                                    continue;
                                }

                                $response = $api_functions->storecontrl_update_wc_product_variation( $product_variations, 'GetSkuAdded');

                                // Check if product is processed or 10 times try to process
                                if( $response == 'true' || isset($product_variations['retry']) && $product_variations['retry'] == 12 ) {
                                    $all_variations[$key]->processed = 'true';
                                    file_put_contents( $directory . '/' . $file, json_encode($all_variations) );

                                    // Update time after every succefull processed product
                                    $processing_batch_time = time();
                                    update_option( 'processing_batch_time', $processing_batch_time );
                                }
                                else{
                                    $retry = 0;
                                    if( isset($product_variations['retry']) ) {
                                        $retry = $product_variations['retry'];
                                    }
                                    $retry++;
                                    $all_variations[$key]->retry = $retry;
                                    file_put_contents( $directory . '/' . $file, json_encode($all_variations) );

                                    $file_finished = false;
                                }

                            }
                            break;

                        // SKu Changed batch processing
                        case (strpos($file, 'sku_changed') !== false):

                            // CPU load check
                            if( !empty($this->cpu_count) ) {
                                $CPUCount = $this->cpu_count;
                                $load = sys_getloadavg();
                                if ($load[0] > ($CPUCount * 2) - 1) {
                                    $this->logging->log_file_write('Notice | Stop processing with load: ' . $load[0]);
                                    exit;
                                }
                            }

                            $all_variations = $results;
                            $results = (array) $results;

                            // Iterate products batch
                            foreach ( $results as $key => $product_variations) {

                                $product_variations = (array)$product_variations;

                                // Check if product is already processed
                                if( isset($product_variations['processed']) && $product_variations['processed'] == 'true' ) {
                                    continue;
                                }

                                $response = $api_functions->storecontrl_update_wc_product_variation($product_variations, 'GetSkuChanged');

                                // Check if product is processed
                                if( $response == 'true' ) {
                                    $all_variations[$key]->processed = 'true';
                                    file_put_contents( $directory . '/' . $file, json_encode($all_variations) );

                                    // Update time after every succefull processed product
                                    $processing_batch_time = time();
                                    update_option( 'processing_batch_time', $processing_batch_time );
                                }
                                else{
                                    $this->logging->log_file_write( 'Cronjob | SKU changed | Product ID not found: ' . $product_variations['product_id'] );
                                }
                            }
                            break;
                    }

                }
                else{
                    $this->logging->log_file_write( 'Cronjob | No results found in batch file.' );
                }

                // Only move if finished
                if( $file_finished ) {
                    // Move file to temp folder
                    $current_file_path = $directory . '/' . $file;
                    $temp_file_name = date('Y-m-d_H.i') . '_' . $file;
                    $new_file_path = $directory . '/temp/' . $temp_file_name;

                    if (rename($current_file_path, $new_file_path)) {
                        $this->logging->log_file_write('Product cronjob | Deleted batch: ' . $file);
                    } else {
                        $this->logging->log_file_write('Product cronjob | Unable to move/rename batch: ' . $file);
                    }
                }
                else{
                    $this->logging->log_file_write('Product cronjob | Batch retry because not all data has been processed: ' . $file);
                }
            }

            // Only ones after midnight
            if( date('H') == '02' ){

                // Remove old processed import files
                $this->delete_old_import_files();
            }

            // close FTP connection
            if( is_resource($this::$storecontrl_ftp_connection) ) {
                ftp_close($this::$storecontrl_ftp_connection);
            }
        }
        else{
            $this->logging->log_file_write( 'Product cronjob | No batch files to process' );
        }
    }

    public function delete_old_import_files(){

        // Temp directory
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $directory = $upload_dir . '/storecontrl/imports/temp/';
        $interval = strtotime('-14 days');

        /*** cycle through all files in the directory ***/
        foreach (glob($directory."*") as $file) {

            // delete if file is 72 hours (259200 seconds) old
            if( filemtime($file) <= $interval ){
                unlink($file);
            }
        }
    }

    public function update_sale_products(){
        $storecontrl_sale_category = get_option( 'storecontrl_sale_category' );

        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $directory = $upload_dir . '/storecontrl';
        $storecontrl_masterdata = array();
        if (file_exists($directory . '/MasterData.json')) {
            $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
            $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);
        }

        // Remove products form sale connected to expired discounts
        if( isset($storecontrl_masterdata['DiscountInfo']) && !empty($storecontrl_masterdata['DiscountInfo']) ){
            $expired_discounts = array();
            foreach( $storecontrl_masterdata['DiscountInfo'] as $discountInfo ){
                if( $discountInfo['date_until'] < date('Y-m-d') ){
                    $expired_discounts[] = $discountInfo['discount_id'];
                }
            }

            if( !empty($expired_discounts) ){

                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'fields' => 'ids',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_cat',
                            'terms' => $storecontrl_sale_category,
                            'operator' => 'IN'
                        )
                    ),
                    'meta_query' => array(
                        array(
                            'key'           => 'sc_discount_id',
                            'value'         => $expired_discounts,
                            'compare'       => 'IN'
                        )
                    ),
                );
                $query = new WP_Query($args);

                if( count($query->posts) > 0 ) {
                    $this->logging->log_file_write( 'Sale | Remove old sale products: ' . count($query->posts) );
                    foreach ($query->posts as $post_id) {
                        delete_post_meta($post_id, 'sc_discount_id');
                        wp_remove_object_terms($post_id, (int)$storecontrl_sale_category['term_id'], 'product_cat');
                    }
                }
            }
        }

        // Update planned Sale products
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => 250,
            'meta_query' => array(
                array(
                    'key'           => 'sc_discount_id',
                    'value'         => '',
                    'compare'       => '!='
                )
            ),
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'terms' => $storecontrl_sale_category,
                    'operator' => 'NOT IN'
                )
            ),
            'fields' => 'ids'
        );

        $query = new WP_Query( $args );
        if( count($query->posts) > 0 ) {
            $this->logging->log_file_write( 'Sale | Update planned sale products: ' . count($query->posts) );
            foreach ($query->posts as $post_id) {
                wp_set_object_terms($post_id, (int)$storecontrl_sale_category['term_id'], 'product_cat', true);
            }
        }
    }

    public function MappAllMasterdataInfo( $shop_categories ){

        $mapped_shop_categories = array();

        // Loop round each attribute
        foreach ( $shop_categories as $category_name => $attributes ){

            switch ($category_name) {
                case 'Group':
                    $wc_type    = 'taxonomy';
                    $wc_key     = 'product_cat';
                    break;
                case 'Type':
                    $wc_type    = 'taxonomy';
                    $wc_key     = 'product_cat';
                    break;
                case 'Subtype':
                    $wc_type    = 'taxonomy';
                    $wc_key     = 'product_cat';
                    break;
                default;
                    $wc_type    = 'taxonomy';
                    $wc_key     = strtolower($category_name);
                    break;
            }

            foreach ($attributes as $index => $attribute) {

                $mapped_shop_categories[$category_name][$attribute['masterdata_id']] = $attribute;
                $mapped_shop_categories[$category_name][$attribute['masterdata_id']]['wc_type'] = $wc_type;
                $mapped_shop_categories[$category_name][$attribute['masterdata_id']]['wc_key'] = $wc_key;

            }
        }

        return $mapped_shop_categories;
    }

    public function process_changes_wc_products() {

        global $wpdb;

        $web_api = new StoreContrl_Web_Api();
        $args = array(
            'content_type' => 'application/json',
            'has_sessionId' => false
        );
        $web_api->curl_request("/Data/StopSynchro", 'POST', $args);
        $sync_result = $web_api->curl_request("/Data/StartSynchro", 'POST', $args);

        if ($sync_result == 'Session started' ) {

            $api_functions = new StoreContrl_Web_Api_Functions();
            $api_functions->before_storecontrl_synchronize();

            $args = array(
                'content_type' => 'application/json',
                'has_sessionId' => true
            );
            $masterdata_changes = $web_api->curl_request("/Data/GetMasterdataChanges", "GET", $args);

            if( isset($masterdata_changes) && !empty($masterdata_changes) ) {

                $sync_round_identifier = time();

                // Get Masterdata
                $upload     = wp_upload_dir();
                $upload_dir = $upload['basedir'];
                $directory  = $upload_dir . '/storecontrl';
                $storecontrl_masterdata = array();
                if (file_exists($directory . '/MasterData.json')) {
                    $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
                    $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);
                }

                if (isset($storecontrl_masterdata['MasterdataInfo'])) {
                    $masterdata_changes = $this->MappAllMasterdataInfo( $masterdata_changes );
                    foreach( $masterdata_changes as  $group => $items ){
                        foreach( $items as $item ){
                            $storecontrl_masterdata['MasterdataInfo'][$group][$item['masterdata_id']] = $item;
                        }
                    }
                }

                file_put_contents($directory . '/MasterData.json', json_encode($storecontrl_masterdata));
                $this->logging->log_file_write( 'MasterData | SAVED ( Product changes )' );

                $web_api->curl_request("/Data/RetrievedMasterdataChanges", "GET", $args);
            }

            $args = array(
                'content_type' => 'application/json',
                'has_sessionId' => true
            );
            $changes = $web_api->curl_request("/Data/GetChanges", "GET", $args);

            echo '<pre>';
            print_r($changes);
            echo '</pre>';

            $api_calls = array();
            foreach ($changes as $change) {
                $api_calls[] = $change['api_name'];
            }
            if( isset($changes) && !empty($changes) ) {

                update_option('process_product_changes', date('Y-m-d H:i'));

                foreach ($changes as $change) {

                    $this->logging->log_file_write('Cronjob | API call: ' . $change['api_name'] . ' with amount records: ' . $change['record_count']);

                    switch ($change['api_name']) {

                        case 'GetVariationAdded':

                            $MasterDataVariations = $web_api->curl_request("/Variation/GetVariationAdded", "GET", $args);

                            // Get Masterdata
                            $upload = wp_upload_dir();
                            $upload_dir = $upload['basedir'];
                            $directory = $upload_dir . '/storecontrl';
                            $storecontrl_masterdata = array();
                            if (file_exists($directory . '/MasterData.json')) {
                                $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
                                $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);
                            }

                            if (isset($MasterDataVariations) && !empty($MasterDataVariations)) {

                                $i = 0;
                                $processed_ids = '';

                                foreach ($MasterDataVariations as $MasterDataVariation) {

                                    $mapped_variations = array();
                                    foreach ($MasterDataVariation['variation_members'] as $variation) {
                                        $mapped_variations[$variation['variation_member_id']] = $variation;
                                        $storecontrl_masterdata['VariationInfo'][$variation['variation_member_id']] = $variation;
                                        $mapped_variations[$variation['variation_member_id']]['variation_name'] = $this->generate_clean_variation_name($MasterDataVariation['variation_name']);
                                        $storecontrl_masterdata['VariationInfo'][$variation['variation_member_id']]['variation_name'] = $this->generate_clean_variation_name($MasterDataVariation['variation_name']);
                                    }

                                    $storecontrl_masterdata['Attributes'][$MasterDataVariation['variation_id']] = array(
                                        'variation_id' => $MasterDataVariation['variation_id'],
                                        'variation_name' => $this->generate_clean_variation_name($MasterDataVariation['variation_name']),
                                        'variation_members' => $mapped_variations
                                    );

                                    if ($i == 0) {
                                        $processed_ids .= '=' . $MasterDataVariation['variation_id'];
                                    } else {
                                        $processed_ids .= '&=' . $MasterDataVariation['variation_id'];
                                    }

                                    $i++;
                                }

                                file_put_contents($directory . '/MasterData.json', json_encode($storecontrl_masterdata));

                                // Remove processed ids
                                $custom_args = array(
                                    'content_type' => 'application/x-www-form-urlencoded',
                                    'has_sessionId' => true,
                                    'post_fields' => $processed_ids
                                );
                                $web_api->curl_request("/Variation/RemoveVariationAdded", "POST", $custom_args);
                            }

                            break;

                        case 'GetVariationChanged':

                            $MasterDataVariations = $web_api->curl_request("/Variation/GetVariationChanged", "GET", $args);

                            // Get Masterdata
                            $upload = wp_upload_dir();
                            $upload_dir = $upload['basedir'];
                            $directory = $upload_dir . '/storecontrl';
                            $storecontrl_masterdata = array();
                            if (file_exists($directory . '/MasterData.json')) {
                                $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
                                $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);
                            }

                            if (isset($MasterDataVariations) && !empty($MasterDataVariations)) {

                                $i = 0;
                                $processed_ids = '';

                                foreach ($MasterDataVariations as $MasterDataVariation) {

                                    $mapped_variations = array();
                                    foreach( $MasterDataVariation['variation_members'] as $variation ){
                                        $mapped_variations[$variation['variation_member_id']] = $variation;
                                        $storecontrl_masterdata['VariationInfo'][$variation['variation_member_id']] = $variation;
                                        $mapped_variations[$variation['variation_member_id']]['variation_name'] = $this->generate_clean_variation_name($MasterDataVariation['variation_name']);
                                        $storecontrl_masterdata['VariationInfo'][$variation['variation_member_id']]['variation_name'] = $this->generate_clean_variation_name($MasterDataVariation['variation_name']);
                                    }

                                    $storecontrl_masterdata['Attributes'][$MasterDataVariation['variation_id']] = array(
                                        'variation_id'      => $MasterDataVariation['variation_id'],
                                        'variation_name'    => $this->generate_clean_variation_name($MasterDataVariation['variation_name']),
                                        'variation_members' => $mapped_variations
                                    );

                                    if ($i == 0) {
                                        $processed_ids .= '=' . $MasterDataVariation['variation_id'];
                                    } else {
                                        $processed_ids .= '&=' . $MasterDataVariation['variation_id'];
                                    }

                                    $i++;
                                }
                                file_put_contents($directory . '/MasterData.json', json_encode($storecontrl_masterdata));

                                // Remove processed ids
                                $custom_args = array(
                                    'content_type' => 'application/x-www-form-urlencoded',
                                    'has_sessionId' => true,
                                    'post_fields' => $processed_ids
                                );
                                $web_api->curl_request("/Variation/RemoveVariationChanged", "POST", $custom_args);
                            }
                            break;

                        case 'GetVariationRemoved': // DONE
                            $variations = $web_api->curl_request("/Variation/GetVariationRemoved", "GET", $args);

                            if (isset($variations) && !empty($variations)) {

                                $i = 0;
                                $processed_ids = '';
                                foreach ($variations as $sku) {

                                    $existing_product_variation = $wpdb->get_results( $wpdb->prepare( "
                                      SELECT *
                                      FROM $wpdb->postmeta
                                      WHERE meta_key = 'storecontrl_size_id' AND meta_value = '%s'
                                      ORDER BY meta_id DESC
                                   ", $sku), 'ARRAY_A' );

                                    if( isset($existing_product_variation[0]['post_id']) ){
                                        wp_delete_post($existing_product_variation[0]['post_id'], true);
                                    }

                                    if ($i == 0) {
                                        $processed_ids .= '=' . $sku;
                                    } else {
                                        $processed_ids .= '&=' . $sku;
                                    }

                                    $i++;
                                }

                                // Remove processed ids
                                $custom_args = array(
                                    'content_type' => 'application/x-www-form-urlencoded',
                                    'has_sessionId' => true,
                                    'post_fields' => $processed_ids
                                );
                                $web_api->curl_request("/Variation/RemoveVariationRemoved", "POST", $custom_args);
                            }
                            break;

                        case 'GetDiscountAdded':
                            $discounts = $web_api->curl_request("/Discount/GetDiscountAdded", "GET", $args);

                            // Get Masterdata
                            $upload = wp_upload_dir();
                            $upload_dir = $upload['basedir'];
                            $directory = $upload_dir . '/storecontrl';
                            $storecontrl_masterdata = array();
                            if (file_exists($directory . '/MasterData.json')) {
                                $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
                                $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);
                            }

                            if (isset($discounts) && !empty($discounts)) {

                                $i = 0;
                                $processed_ids = '';
                                foreach ($discounts as $discount) {

                                    $storecontrl_masterdata['DiscountInfo'][$discount['discount_id']] = $discount;

                                    if ($i == 0) {
                                        $processed_ids .= '=' . $discount['discount_id'];
                                    } else {
                                        $processed_ids .= '&=' . $discount['discount_id'];
                                    }

                                    $this->logging->log_file_write( 'MasterData | Discount added with ID: ' .$discount['discount_id'] );

                                    $i++;
                                }

                                file_put_contents($directory . '/MasterData.json', json_encode($storecontrl_masterdata));

                                // Remove processed ids
                                $custom_args = array(
                                    'content_type' => 'application/x-www-form-urlencoded',
                                    'has_sessionId' => true,
                                    'post_fields' => $processed_ids
                                );
                                $web_api->curl_request("/Discount/RemoveDiscountAdded", "POST", $custom_args);
                            }

                            break;

                        case 'GetDiscountChanged':
                            $discounts = $web_api->curl_request("/Discount/GetDiscountChanged", "GET", $args);

                            // Get Masterdata
                            $upload = wp_upload_dir();
                            $upload_dir = $upload['basedir'];
                            $directory = $upload_dir . '/storecontrl';
                            $storecontrl_masterdata = array();
                            if (file_exists($directory . '/MasterData.json')) {
                                $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
                                $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);
                            }

                            if (isset($discounts) && !empty($discounts)) {

                                $i = 0;
                                $processed_ids = '';
                                foreach ($discounts as $discount) {

                                    $storecontrl_masterdata['DiscountInfo'][$discount['discount_id']] = $discount;

                                    if ($i == 0) {
                                        $processed_ids .= '=' . $discount['discount_id'];
                                    } else {
                                        $processed_ids .= '&=' . $discount['discount_id'];
                                    }

                                    $this->logging->log_file_write( 'MasterData | Discount changed with ID: ' .$discount['discount_id'] );

                                    $i++;
                                }

                                file_put_contents($directory . '/MasterData.json', json_encode($storecontrl_masterdata));

                                // Remove processed ids
                                $custom_args = array(
                                    'content_type' => 'application/x-www-form-urlencoded',
                                    'has_sessionId' => true,
                                    'post_fields' => $processed_ids
                                );
                                $web_api->curl_request("/Discount/RemoveDiscountChanged", "POST", $custom_args);
                            }

                            break;

                        case 'GetDiscountRemoved':
                            $discounts = $web_api->curl_request("/Discount/GetDiscountRemoved", "GET", $args);

                            // Get Masterdata
                            $directory = $upload_dir . '/storecontrl';
                            $storecontrl_masterdata = array();
                            if (file_exists($directory . '/MasterData.json')) {
                                $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
                                $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);
                            }

                            if (isset($discounts) && !empty($discounts)) {

                                $i = 0;
                                $processed_ids = '';
                                foreach ($discounts as $discount_id) {

                                    unset( $storecontrl_masterdata['DiscountInfo'][$discount_id] );

                                    if ($i == 0) {
                                        $processed_ids .= '=' . $discount_id;
                                    } else {
                                        $processed_ids .= '&=' . $discount_id;
                                    }

                                    $this->logging->log_file_write( 'MasterData | Discount removed with ID: ' .$discount_id );

                                    $i++;
                                }

                                file_put_contents($directory . '/MasterData.json', json_encode($storecontrl_masterdata));

                                // Remove processed ids
                                $custom_args = array(
                                    'content_type' => 'application/x-www-form-urlencoded',
                                    'has_sessionId' => true,
                                    'post_fields' => $processed_ids
                                );
                                $web_api->curl_request("/Discount/RemoveDiscountRemoved", "POST", $custom_args);
                            }

                            break;

                        case 'GetProductAdded':

                            // Upload directory
                            $upload = wp_upload_dir();
                            $upload_dir = $upload['basedir'];
                            $new_upload_dir = $upload_dir . '/storecontrl/imports';

                            $batch = 0;
                            do {
                                $products = $web_api->curl_request("/Product/GetProductAdded", "GET", $args);
                                $this->logging->log_file_write('GetProductAdded | Amount of added products: ' . count($products));
                                if (isset($products) && !empty($products)) {
                                    $i = 0;
                                    $processed_ids = '';
                                    $total_products = count($products);
                                    $products_array = array();
                                    foreach( $products as $product ) {

                                        $products_array[$product['product_id']] = $product;

                                        // 50 producten per batch
                                        if ( ( ( $i + 1 ) % 50 == 0 ) || $i + 1 == $total_products ) {

                                            if (isset($products_array) && count($products_array) > 0) {
                                                $products_array = json_encode($products_array);

                                                $success = file_put_contents($new_upload_dir . '/'.$sync_round_identifier.'_products_added_batch_' . $batch . '.json', $products_array);
                                                if ($success === FALSE) {
                                                    $this->logging->log_file_write('ERROR | Product batch ' . $batch . 'not created!');
                                                }

                                                $products_array = array();
                                                $batch++;
                                            } else {
                                                $this->logging->log_file_write('ERROR | Product batch/call has no products!');
                                            }
                                        }

                                        if ($i == 0) {
                                            $processed_ids .= '=' . $product['product_id'];
                                        } else {
                                            $processed_ids .= '&=' . $product['product_id'];
                                        }

                                        $i++;
                                    }

                                    // Remove processed ids
                                    $custom_args = array(
                                        'content_type' => 'application/x-www-form-urlencoded',
                                        'has_sessionId' => true,
                                        'post_fields' => $processed_ids
                                    );
                                    $web_api->curl_request("/Product/RemoveProductAdded", "POST", $custom_args);
                                }

                            } while (count($products) >= 100);
                            break;

                        case 'GetProductChanged':

                            // Upload directory
                            $upload = wp_upload_dir();
                            $upload_dir = $upload['basedir'];
                            $new_upload_dir = $upload_dir . '/storecontrl/imports';

                            $batch = 0;
                            do {
                                $products = $web_api->curl_request("/Product/GetProductChanged", "GET", $args);

                                $this->logging->log_file_write('GetProductAdded | Amount of changed products: ' . count($products));
                                if (isset($products) && !empty($products)) {

                                    $i = 0;
                                    $processed_ids = '';
                                    $total_products = count($products);
                                    $products_array = array();
                                    foreach( $products as $product ) {

                                        $products_array[$product['product_id']] = $product;

                                        // 50 producten per batch
                                        if ( ( ( $i + 1 ) % 50 == 0 ) || $i + 1 == $total_products ) {

                                            if (isset($products_array) && count($products_array) > 0) {
                                                $products_array = json_encode($products_array);

                                                $success = file_put_contents($new_upload_dir . '/'.$sync_round_identifier.'_products_changed_batch_' . $batch . '.json', $products_array);
                                                if ($success === FALSE) {
                                                    $this->logging->log_file_write('ERROR | Product batch ' . $batch . 'not created!');
                                                }

                                                $products_array = array();
                                                $batch++;
                                            } else {
                                                $this->logging->log_file_write('ERROR | Product batch/call has no products!');
                                            }
                                        }

                                        if ($i == 0) {
                                            $processed_ids .= '=' . $product['product_id'];
                                        } else {
                                            $processed_ids .= '&=' . $product['product_id'];
                                        }

                                        $i++;
                                    }

                                    // Remove processed ids
                                    $custom_args = array(
                                        'content_type' => 'application/x-www-form-urlencoded',
                                        'has_sessionId' => true,
                                        'post_fields' => $processed_ids
                                    );
                                    $web_api->curl_request("/Product/RemoveProductChanged", "POST", $custom_args);
                                }
                            } while (count($products) >= 100);
                            break;

                        case 'GetProductRemoved':

                            do {
                                $products = $web_api->curl_request("/Product/GetProductRemoved", "GET", $args);

                                if (isset($products) && !empty($products)) {

                                    $i = 0;
                                    $processed_ids = '';
                                    foreach ($products as $sku) {
                                        $post_id = $this->functions->custom_get_product_id_by_sku($sku);
                                        if (isset($post_id) && !empty($post_id)) {

                                            $this->logging->log_file_write('GetProductRemoved | Product removed from webshop ' . $post_id);
                                            wp_delete_post($post_id, false);
                                        }

                                        if ($i == 0) {
                                            $processed_ids .= '=' . $sku;
                                        } else {
                                            $processed_ids .= '&=' . $sku;
                                        }

                                        $i++;

                                        if( $i == 25 ){
                                            // Remove processed ids
                                            $custom_args = array(
                                                'content_type' => 'application/x-www-form-urlencoded',
                                                'has_sessionId' => true,
                                                'post_fields' => $processed_ids
                                            );
                                            $web_api->curl_request("/Product/RemoveProductRemoved", "POST", $custom_args);

                                            $i = 0;
                                            $processed_ids = '';
                                        }
                                    }

                                    // Remove processed ids
                                    if( isset($processed_ids) && !empty($processed_ids) ) {
                                        $custom_args = array(
                                            'content_type' => 'application/x-www-form-urlencoded',
                                            'has_sessionId' => true,
                                            'post_fields' => $processed_ids
                                        );
                                        $web_api->curl_request("/Product/RemoveProductRemoved", "POST", $custom_args);
                                    }
                                }
                            } while (count($products) >= 100);
                            break;

                        case 'GetSkuAdded':

                            // Upload directory
                            $upload = wp_upload_dir();
                            $upload_dir = $upload['basedir'];
                            $new_upload_dir = $upload_dir . '/storecontrl/imports';

                            $batch = 0;
                            do {
                                $processed_variations_count = 0;

                                $barcode_as_sku = get_option('storecontrl_set_barcode_as_sku');
                                if ($barcode_as_sku == 1 ) {
                                    $all_variations = $web_api->curl_request("/Sku/GetSkuAdded?groupSizesPerProduct=true&sendEan=true", "GET", $args);
                                }
                                else{
                                    $all_variations = $web_api->curl_request("/Sku/GetSkuAdded?groupSizesPerProduct=true", "GET", $args);
                                }

                                if (isset($all_variations) && !empty($all_variations)) {

                                    $success = file_put_contents($new_upload_dir . '/'.$sync_round_identifier.'_sku_added_batch_'.$batch.'.json', json_encode($all_variations));
                                    if ($success === FALSE) {
                                        $this->logging->log_file_write('ERROR | Sku added batch not created!');
                                    }
                                    else{
                                        $i = 0;
                                        $processed_ids = '';
                                        foreach( $all_variations as $product_variations ) {

                                            foreach( $product_variations['sku_list'] as  $product_variation ) {

                                                if ($i == 0) {
                                                    $processed_ids .= '=' . $product_variation['sku_id'];
                                                } else {
                                                    $processed_ids .= '&=' . $product_variation['sku_id'];
                                                }
                                                $processed_variations_count++;

                                                $i++;
                                            }
                                        }

                                        // Remove processed ids
                                        $custom_args = array(
                                            'content_type' => 'application/x-www-form-urlencoded',
                                            'has_sessionId' => true,
                                            'post_fields' => $processed_ids
                                        );
                                        $web_api->curl_request("/Sku/RemoveSkuAdded", "POST", $custom_args);
                                    }

                                    $batch++;
                                }
                            } while ($processed_variations_count >= 100);

                            break;

                        case 'GetSkuChanged':

                            // Upload directory
                            $upload = wp_upload_dir();
                            $upload_dir = $upload['basedir'];
                            $new_upload_dir = $upload_dir . '/storecontrl/imports';

                            $batch = 0;
                            do {
                                $processed_variations_count = 0;

                                $barcode_as_sku = get_option('storecontrl_set_barcode_as_sku');
                                if ($barcode_as_sku == 1 ) {
                                    $all_variations = $web_api->curl_request("/Sku/GetSkuChanged?groupSizesPerProduct=true&sendEan=true", "GET", $args);
                                }
                                else{
                                    $all_variations = $web_api->curl_request("/Sku/GetSkuChanged?groupSizesPerProduct=true", "GET", $args);
                                }

                                if (isset($all_variations) && !empty($all_variations)) {

                                    $success = file_put_contents($new_upload_dir . '/'.$sync_round_identifier.'_sku_changed_batch_'.$batch.'.json', json_encode($all_variations));
                                    if ($success === FALSE) {
                                        $this->logging->log_file_write('ERROR | Sku changed batch not created!');
                                    } else {
                                        $i = 0;
                                        $batch++;
                                        $processed_ids = '';
                                        foreach ($all_variations as $product_variations) {

                                            foreach ($product_variations['sku_list'] as $product_variation) {

                                                if ($i == 0) {
                                                    $processed_ids .= '=' . $product_variation['sku_id'];
                                                } else {
                                                    $processed_ids .= '&=' . $product_variation['sku_id'];
                                                }
                                                $processed_variations_count++;

                                                $i++;
                                            }
                                        }

                                        // Remove processed ids
                                        $custom_args = array(
                                            'content_type' => 'application/x-www-form-urlencoded',
                                            'has_sessionId' => true,
                                            'post_fields' => $processed_ids
                                        );
                                        $web_api->curl_request("/Sku/RemoveSkuChanged", "POST", $custom_args);
                                    }
                                }
                            } while ($processed_variations_count >= 100);

                            break;

                        case 'GetSkuRemoved':

                            do {
                                $variations = $web_api->curl_request("/Sku/GetSkuRemoved", "GET", $args);

                                if (isset($variations) && !empty($variations)) {

                                    $i = 0;
                                    $processed_ids = '';
                                    foreach ($variations as $sku) {
                                        if ($i == 0) {
                                            $processed_ids .= '=' . $sku;
                                        } else {
                                            $processed_ids .= '&=' . $sku;
                                        }

                                        $i++;

                                        if( $i == 25 ){
                                            // Remove processed ids
                                            $custom_args = array(
                                                'content_type' => 'application/x-www-form-urlencoded',
                                                'has_sessionId' => true,
                                                'post_fields' => $processed_ids
                                            );
                                            $web_api->curl_request("/Sku/RemoveSkuRemoved", "POST", $custom_args);

                                            $i = 0;
                                            $processed_ids = '';
                                        }
                                    }

                                    if( !empty($processed_ids) ){
                                        $custom_args = array(
                                            'content_type' => 'application/x-www-form-urlencoded',
                                            'has_sessionId' => true,
                                            'post_fields' => $processed_ids
                                        );
                                        $web_api->curl_request("/Sku/RemoveSkuRemoved", "POST", $custom_args);
                                    }
                                }
                            } while (count($variations) >= 100);
                            break;
                    }
                }
            }

            $web_api->curl_request("/Data/StopSynchro", 'POST', $args);
            $api_functions->after_storecontrl_synchronize();
        }
        else{
            $this->logging->log_file_write( 'ERROR | Session not started' );
        }

        // Reset sale products because they can be planned
        // Only ones after midnight
        if( date('H') == '01' ){
            $this->logging->log_file_write( 'Cronjob | Update sale products' );
            $this->update_sale_products();
        }

    }

    private function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }

    public function ConnectToFTPServer() {

        $this::$storecontrl_ftp_connection = ftp_connect($this->storecontrl_api_images_url, 21, 300);

        if( !$this::$storecontrl_ftp_connection ){
            $this->logging->log_file_write( 'Cronjob | Could not connect to FTP-server at '.$this->storecontrl_api_images_url );
        }

        $this::$storecontrl_ftp_login = ftp_login($this::$storecontrl_ftp_connection, $this->storecontrl_api_ftp_user, $this->storecontrl_api_ftp_password);
        if( !$this::$storecontrl_ftp_login ){
            $this->logging->log_file_write( 'Cronjob | Could not login to FTP-server. Error: '.error_get_last()['message'] );
        }

        $this::$storecontrl_ftp_pasv = ftp_pasv($this::$storecontrl_ftp_connection, true);
        if( !$this::$storecontrl_ftp_pasv ){
            $this->logging->log_file_write( 'Cronjob | Could not enable passive mode on the FTP-server. Error: '.error_get_last()['message'] );
        }

        $this::$storecontrl_ftp_chdir = ftp_chdir($this::$storecontrl_ftp_connection, '');
        if( !$this::$storecontrl_ftp_chdir ){
            $this->logging->log_file_write( 'Cronjob | Cannot access image folder on the FTP-server. Error: '.error_get_last()['message'] );
        }

        // Increase FTP timelimit to max execution time
        $max_execution_time = (int)ini_get('max_execution_time');
        if( !isset($max_execution_time) || $max_execution_time == 0 ){ $max_execution_time = 300; }
        ftp_set_option($this::$storecontrl_ftp_connection, FTP_TIMEOUT_SEC, $max_execution_time);
    }

    public function GetFTPConnection( $renew = false ) {
        if (!$this::$storecontrl_ftp_connection || $renew ) {
            $this->ConnectToFTPServer();
        }
        return $this::$storecontrl_ftp_connection;
    }

    public function update_wc_product_stock_changes() {
        $api_functions = new StoreContrl_Web_Api_Functions();
        $api_functions->storecontrl_synchronise_stock();
    }

    public function generate_clean_variation_name( $name ) {

        $name = str_replace(array(',', '\'', '.'), '', $name);
        $name = str_replace('#', '_', $name);
        $name = str_replace('ï', 'i', $name);
        $name = str_replace('é', 'e', $name);
        $name = str_replace('è', 'e', $name);
        $name = str_replace('ë', 'e', $name);
        $name = str_replace('&', '-', $name);

        return $name;
    }

}

<?php
/*
=================================================
	API functions
	1. Process products into Wordpress
=================================================
*/

class StoreContrl_Web_Api_Functions
{

    public $use_instock_variations;

    private $functions;
    private $storecontrl_api_ftp_user;
    private $storecontrl_api_ftp_password;
    private static $storecontrl_ftp_connection;
    private $storecontrl_masterdata;
    private $storecontrl_api_url;
    private $storecontrl_api_images_url;

    public function __construct()
    {
        $this->functions = new StoreContrl_WP_Connection_Functions();
        $this->use_instock_variations = get_option('storecontrl_use_instock_variations');
        $this->storecontrl_api_ftp_user = get_option('storecontrl_api_ftp_user');
        $this->storecontrl_api_ftp_password = get_option('storecontrl_api_ftp_password');
        $this->storecontrl_api_url = $this->functions->getStoreContrlAPIURI();
        $this->storecontrl_api_images_url = get_option('storecontrl_api_images_url');

        // Get Masterdata
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $directory = $upload_dir . '/storecontrl';
        $storecontrl_masterdata = array();
        if (file_exists($directory . '/MasterData.json')) {
            $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
            $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);

            // Refresh if not exist
            if( !isset($storecontrl_masterdata['VariationInfo']) || empty($storecontrl_masterdata['VariationInfo']) ){
                $cronjob_functions = new StoreContrl_Cronjob_Functions();
                $cronjob_functions->storecontrl_set_masterdata();

                $storecontrl_masterdata = file_get_contents($directory . '/MasterData.json');
                $storecontrl_masterdata = json_decode($storecontrl_masterdata, true);
            }

            $this->storecontrl_masterdata = $storecontrl_masterdata;
        } else {
            $this->storecontrl_masterdata = $storecontrl_masterdata;
        }
    }

    public function storecontrl_synchronise_stock()
    {
        $web_api = new StoreContrl_Web_Api();
        $logging = new StoreContrl_WP_Connection_Logging();

        // Upload directory
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $temp_upload_dir = $upload_dir . '/storecontrl/imports/temp';

        $total_sychronized_stock_changes = 0;

        // Get in batches of 300 products
        do {
            $i = 0;
            $processed_products = '';
            $results = $web_api->storecontrl_get_sku_stock_changes();

            if (isset($results) && is_array($results) && !empty($results)) {

                update_option('process_stock_changes', date('Y-m-d H:i'));

                foreach ($results as $product) {

                    if (isset($product['sku_id'])) {
                        $total_sychronized_stock_changes++;
                        $logging->log_file_write('StoreContrl | Update stock of SKU: ' . $product['sku_id']);

                        // Save new product stock values
                        $this->storecontrl_update_wc_product_stock($product);

                        if ($i == 0) {
                            $processed_products .= '=' . $product['sku_id'];
                        } else {
                            $processed_products .= '&=' . $product['sku_id'];
                        }
                        $i++;
                    }
                }

                // Save stock changes import as temp file for monitoring/debug
                file_put_contents($temp_upload_dir . '/stock_changes_' . date('Y-m-d_H-i') . '.json', json_encode($results));

                // The correctly processed products should be removed from the synchronization tables
                $web_api->storecontrl_remove_sku_stock_changes($processed_products);
            }

        } while (is_array($results) && count($results) > '280');

        $logging->log_file_write('StoreContrl | Synchronise stock changes completed with ' . $total_sychronized_stock_changes . ' products.');
        wp_die();
    }

    public function set_product_masterdata($product)
    {
        $logging = new StoreContrl_WP_Connection_Logging();

        $output = $product;
        $storecontrl_masterdata = $this->storecontrl_masterdata;
        $MasterDataCategories = $storecontrl_masterdata['MasterdataInfo'];

        // SET DISCOUNT IF AVAILABLE
        if (isset($product['discount_id']) && !empty($product['discount_id'])) {

            // Default
            $output['discount_percentage'] = '0';
            $output['discount_amount'] = '0';

            // Check if discount ID exist
            if (isset($storecontrl_masterdata['DiscountInfo'][$product['discount_id']])) {
                $product_discount = $storecontrl_masterdata['DiscountInfo'][$product['discount_id']];

                if (isset($product_discount['percentage']) && $product_discount['percentage'] > 0) {
                    $output['discount_percentage'] = $product_discount['percentage'];
                } elseif (isset($product_discount['amount']) && $product_discount['amount'] > 0) {
                    $output['discount_amount'] = $product_discount['amount'];
                } elseif (isset($product_discount['price']) && $product_discount['price'] > 0) {
                    $output['discount_fixed_price'] = $product_discount['price'];
                }

                $output['discount_date_from'] = substr($product_discount['date_from'], 0, 10) . ' 00:00';
                $output['discount_date_until'] = substr($product_discount['date_until'], 0, 10) . ' 23:45';
            } else {
                $logging->log_file_write('ERROR | Product Discount width ID ' . $product['discount_id'] . ' not found in Masterdata');
            }
        }

        // GET MASTERDATA BY ID
        if (isset($MasterDataCategories['Group'][$product['group_id']])) {
            $output['main_group'] = $MasterDataCategories['Group'][$product['group_id']]['masterdata_name'];
        }
        if (isset($MasterDataCategories['Type'][$product['type_id']])) {
            $output['main_type'] = $MasterDataCategories['Type'][$product['type_id']]['masterdata_name'];
        }
        if (isset($MasterDataCategories['Subtype'][$product['subtype_id']])) {
            $output['sub_group'] = $MasterDataCategories['Subtype'][$product['subtype_id']]['masterdata_name'];
        }

        if (isset($MasterDataCategories['Supplier'][$product['supplier_id']])) {
            $output['supplier'] = $MasterDataCategories['Supplier'][$product['supplier_id']]['masterdata_name'];
        }
        if (isset($MasterDataCategories['Color'][$product['color_id']])) {
            $output['color'] = $MasterDataCategories['Color'][$product['color_id']]['masterdata_name'];
        }
        if (isset($MasterDataCategories['Season'][$product['season_id']])) {
            $output['season'] = $MasterDataCategories['Season'][$product['season_id']]['masterdata_name'];
        }
        if (isset($MasterDataCategories['Brand'][$product['brand_id']])) {
            $output['brand'] = $MasterDataCategories['Brand'][$product['brand_id']]['masterdata_name'];
        }

        if (isset($storecontrl_masterdata['VatRates'][$product['vat_rate_id']])) {
            $output['_tax_class'] = $storecontrl_masterdata['VatRates'][$product['vat_rate_id']]['_tax_class'];
        }

        return $output;
    }

    public function before_storecontrl_synchronize()
    {
    }

    public function after_storecontrl_synchronize()
    {
    }

    public function set_product_variations_data($variations)
    {
        $logging = new StoreContrl_WP_Connection_Logging();

        $mapped_variations = $this->storecontrl_masterdata['VariationInfo'];

        $output = array();
        $ProcessedProductVariations = array();

        foreach ((array)$variations as $variation) {
            $variation = (array)$variation;
            $ProcessedProductVariations[] = $variation['sku_id'];

            // EACH PRODUCT CAN CONTAIN 2 VARIATIONS
            $product_variations = array();
            if (!empty($variation['variation_a_id'])) {
                if (isset($mapped_variations[$variation['variation_a_id']])) {
                    $product_variations[] = $mapped_variations[$variation['variation_a_id']];
                    $product_atributes[$mapped_variations[$variation['variation_a_id']]['variation_name']] = $mapped_variations[$variation['variation_a_id']]['variation_name'];
                } else {
                    $logging->log_file_write('ERROR | Variation A not found in mapping: ' . $variation['variation_a_id']);
                }
            }
            if (!empty($variation['variation_b_id'])) {
                if (isset($mapped_variations[$variation['variation_b_id']])) {
                    $product_variations[] = $mapped_variations[$variation['variation_b_id']];
                    $product_atributes[$mapped_variations[$variation['variation_b_id']]['variation_name']] = $mapped_variations[$variation['variation_b_id']]['variation_name'];
                } else {
                    $logging->log_file_write('ERROR | Variation B not found in mapping: ' . $variation['variation_b_id']);
                }
            }

            if(abs($variation['stock_total']) != $variation['stock_total']){
                $variation['stock_total'] = 0;
            }

            $output['variations'][] = array(
                "size_id" => $variation['sku_id'],
                "barcode" => $variation['barcode'],
                "weight" => $variation['weight'],
                "retail_price" => $variation['retail_price'],
                "stock" => $variation['stock_total'],
                "variation" => $product_variations,
            );

            $output['product_atributes'] = $product_atributes;
        }

        $output['ProcessedProductVariations'] = $ProcessedProductVariations;

        return $output;
    }

    public function storecontrl_update_wc_product_variation($product_variations, $change_api_name = '')
    {
        // Find product by SKU
        $product_id = $this->functions->custom_get_product_id_by_sku($product_variations['product_id']);

        // Check if product exist
        if ($product_id != 0) {

            // init CRUD
            $Product = new WC_Product_Variable( $product_id );

            // SET ALL MASTERDATA
            $output = $this->set_product_variations_data($product_variations['sku_list']);
            $product_variations['variations'] = $output['variations'];
            $product_variations['product_atributes'] = $output['product_atributes'];
            unset($product_variations['sku_list']);

            $product_attributes = $Product->get_attributes();
            if (isset($product_attributes) && !empty($product_attributes)) {

                $product_atributes = array();
                foreach( $product_variations['product_atributes'] as $product_atribute ){
                    $product_atributes[] = 'pa_' . $this->generate_clean_slug($product_atribute);
                }

                foreach ($product_attributes as $attribute_name => $attribute_data) {
                    $attribute_data = $attribute_data->get_data();
                    if( $attribute_data['is_variation'] && !in_array($attribute_name, $product_atributes) ){
                        unset($product_attributes[$attribute_name]);
                    }
                }
            }

            $existing_variation_attributes = $Product->get_variation_attributes();
            if (isset($existing_variation_attributes) && !empty($existing_variation_attributes)) {

                $new_clean_product_atributes = array();
                foreach ($product_variations['product_atributes'] as $variation_attribute_name) {
                    $new_clean_product_atributes[] = 'pa_' . $this->generate_clean_slug($variation_attribute_name);
                }

                $all_product_attributes = get_post_meta($product_id, '_product_attributes', true);
                foreach ($existing_variation_attributes as $variation_attribute_name => $variation_attribute_id) {
                    if (!in_array($variation_attribute_name, $new_clean_product_atributes)) {
                        unset($all_product_attributes[$variation_attribute_name]);
                        $this->functions->custom_update_post_meta($product_id, '_product_attributes', $all_product_attributes);
                    }
                }
            }

            // Add all attributes to the global size term for filtering ( As in the basic plugin )
            $product_variations_attributes = $this->insert_product_attributes('size', $product_variations['variations']);
            if( isset($product_variations_attributes) && is_array($product_variations_attributes) ){
                $product_attributes = array_merge($product_attributes, $this->insert_product_attributes('size', $product_variations['variations']));
                $product_attributes = array_merge($product_attributes, $this->insert_product_attributes($product_variations['product_atributes'], $product_variations['variations']));
                $this->insert_product_variations($product_id, $product_variations);
            }

            $Product->set_attributes($product_attributes);
            $Product->save();

            do_action('after_sc_variations_process', $product_id, $product_variations);

            return 'true';
        } else {
            return 'false';
        }
    }

    public function storecontrl_update_wc_product($product, $full_sync = false)
    {

        // Get all masterdata by product id's
        $product = $this->set_product_masterdata($product);

        $product_attributes = array();

        $product = apply_filters('before_sc_product_process', $product);

        // TEST => $product['product_id'] != 'SKU'
        if (false || empty($product['product_id'])) {
            return true;
        }

        $functions = new StoreContrl_WP_Connection_Functions();

        $product['removed_from_sale'] = false;

        // Find product by SKU
        $post_id = $functions->custom_get_product_id_by_sku($product['product_id'], $product);

        // DISCOUNT - percentage
        if (isset($product['discount_percentage']) && $product['discount_percentage'] > 0) {
            $regular_price = $product['retail_price'];
            $discount_price = $product['retail_price'] * (1 - $product['discount_percentage']);
            $discount_price = (float)$discount_price;
        } // DISCOUNT - amount
        elseif (isset($product['discount_amount']) && $product['discount_amount'] > 0) {
            $regular_price = $product['retail_price'];
            $discount_price = $product['retail_price'] - $product['discount_amount'];
            $discount_price = (float)$discount_price;
        } // DISCOUNT - Fixed price
        elseif (isset($product['discount_fixed_price']) && $product['discount_fixed_price'] > 0) {
            $regular_price = $product['retail_price'];
            $discount_price = $product['discount_fixed_price'];
            $discount_price = (float)$discount_price;
        } elseif (isset($product['msrp']) && $product['msrp'] > $product['retail_price']) {
            $regular_price = $product['retail_price'];
            $discount_price = NULL;
        } else {
            $regular_price = $product['retail_price'];
            $discount_price = NULL;
        }

        // Set to product array
        $product['from_price'] = (float)$regular_price;

        // Check if sale product
        $product['sale_product'] = false;
        if (isset($discount_price) && !empty($discount_price) && $discount_price != 0) {
            $product['sale_product'] = true;

            $discount_price = number_format($discount_price, 2, '.', '');
            $product['to_price'] = $discount_price;
        } else {
            // Check if current product is in sale
            $existing_sale_price = get_post_meta($post_id, '_sale_price', true);
            if (isset($existing_sale_price) && !empty($existing_sale_price)) {
                $product['removed_from_sale'] = true;
            }
        }

        // ADD PRODUCT
        if (!isset($post_id) || empty($post_id)) {

            if (!isset($product['comments']) || empty($product['comments'])) {
                $product['comments'] = '';
            }

            // Check whether we should put a new product on publish or concept
            $post_status = get_option("storecontrl_new_product_status") ? "publish" : "draft";

            // Create new woocommerce product
            $args = array(
                'post_title' => $product['product_name'],
                'post_content' => $product['comments'],
                'post_status' => $post_status,
                'post_type' => 'product'
            );

            // Check whether we should create an execrpt
            if (get_option("storecontrl_excerpt")) {
                $args['post_excerpt'] = wp_trim_excerpt($product['comments']);
            }

            $post_id = wp_insert_post($args);
            $cronjob_functions = new StoreContrl_Cronjob_Functions();
            $cronjob_functions->storecontrl_synchronize_product( $product['product_id'] );
        }

        $this->functions->custom_update_post_meta($post_id, 'msrp', $product['msrp']);
        $this->functions->custom_update_post_meta($post_id, 'sc_product_id', $product['product_id']);

        wp_set_object_terms( $post_id, 'variable', 'product_type' );

        // init CRUD
        $Product = new WC_Product_Variable( $post_id );

        $post_content = get_post_field('post_content', $post_id);
        $storecontrl_keep_product_description = get_option('storecontrl_keep_product_description');
        if (strlen($post_content) == 0) {
            //NO CONTENT SET YET. COPY THE STORECONTRL CONTENT
            $post_content = $product['comments'];
        } else {
            //THE POST ALREADY HAS CONTENT. CHECK WHETHER THE DESCRIPTION SHOULD BE KEPT
            if (isset($storecontrl_keep_product_description) && $storecontrl_keep_product_description) {
                //KEEP THE DESCRIPTION. USE THE POST CONTENT IN THE UPDATE SO THE CONTENT STAYS THE SAME
            } else {
                $post_content = $product['comments'];
            }
        }

        // Update existing woocommerce product
        $storecontrl_keep_product_title = get_option('storecontrl_keep_product_title');
        if (isset($storecontrl_keep_product_title) && $storecontrl_keep_product_title) {
            $Product->set_description($post_content);
        } else {
            $Product->set_name($product['product_name']);
            $Product->set_description($post_content);
        }

        // Check whether we should create a execrpt
        if (get_option("storecontrl_excerpt")) {
            $Product->set_short_description(wp_trim_excerpt($product['comments']));
        }

        //CHECK OR PRODUCT HAS THE TRASH STATUS. IF SO, UPDATE THE STATUS TO THE INITIAL STATUS
        if (get_post_status($post_id) == 'trash') {
            $storecontrl_keep_product_status = get_option('storecontrl_keep_product_status');
            if (!isset($storecontrl_keep_product_status) || !$storecontrl_keep_product_status) {
                $Product->set_status($post_status);
            }
        }

        // Set post meta
        $Product->set_regular_price($regular_price);
        if( empty($Product->get_sku()) && $Product->get_sku() != $product['product_id'] ){
            try {
                $Product->set_sku($product['product_id']);
            }
            catch ( WC_Data_Exception $exception ) {}
        }

        if(isset($product['_tax_class'])) {
            $Product->set_tax_class($product['_tax_class']);
        }

        $product_prices = array();
        if (isset($discount_price) && $discount_price != 0) {
            $product_prices[] = $discount_price;
            $product_prices[] = $regular_price;

            $_price = $discount_price;
            $Product->set_sale_price($discount_price);
            $this->functions->custom_update_post_meta($post_id, 'sc_discount_id', $product['discount_id']);
        } else {
            $product_prices[] = $regular_price;

            $_price = $regular_price;

            delete_post_meta($post_id, '_sale_price');
            delete_post_meta($post_id, '_min_variation_price');
            delete_post_meta($post_id, '_max_variation_sale_price');
            delete_post_meta($post_id, 'sc_discount_id');

            // Remove product from sale
            $sale_category = get_option('storecontrl_sale_category');
            if ((isset($sale_category['term_id']) && !empty($sale_category['term_id']))) {
                wp_remove_object_terms($post_id, (int)$sale_category['term_id'], 'product_cat');
            }
        }

        // Update product images if enabled en FTP settings are filled
        if (get_option('storecontrl_update_images') && isset($this->storecontrl_api_ftp_user) && isset($this->storecontrl_api_ftp_password)) {

            // Only update images if they empty or default updates from SC is on
            $storecontrl_keep_product_images = get_option('storecontrl_keep_product_images');
            if (!isset($storecontrl_keep_product_images) || !$storecontrl_keep_product_images || !has_post_thumbnail($post_id)) {
                $cronjob_functions = new StoreContrl_Cronjob_Functions();
                $this::$storecontrl_ftp_connection = $cronjob_functions->GetFTPConnection(true);
                $gallery_image_ids = $this->save_product_images($post_id, $product);
                $Product->set_gallery_image_ids($gallery_image_ids);
                ftp_close($this::$storecontrl_ftp_connection);
            }
        }

        // Loop variations and check for sale
        $handle = new WC_Product_Variable($post_id);
        $all_product_variations = $handle->get_children();

        if (isset($all_product_variations) && !empty($all_product_variations) && isset($product['variations'])) {

            // Bij een full sync controleren of het aantal variaties overeenkomt met aantal in Woocommerce; nee = opschonen
            if ($full_sync && count($all_product_variations) > count($product['variations'])) {

                // Array met alle nieuwe size_ids vanuit SToreContrl
                $new_product_size_list = array();
                foreach ($product['variations'] as $product_variation) {
                    $new_product_size_list[$product_variation->size_id] = $product_variation->size_id;
                }
            }

            $all_product_variations = $handle->get_children();
            foreach ($all_product_variations as $variation_post_id) {

                $storecontrl_size_id = get_post_meta($variation_post_id, 'storecontrl_size_id', true);

                // Als de variatie niet meer bestaat in StoreContrl dan verwijderen
                if( isset($new_product_size_list) && !empty($new_product_size_list) ) {
                    if (!in_array($storecontrl_size_id, $new_product_size_list)) {
                        $logging = new StoreContrl_WP_Connection_Logging();
                        $logging->log_file_write('DEBUG | Variation ' . $variation_post_id . ' deleted from product: ' . $post_id . ' beacuse variation don\'t exist in StoreContrl.');
                        wp_delete_post($variation_post_id, true);
                    }
                }

                if (isset($product['sale_product']) && $product['sale_product']) {

                    $variation_regular_price = (float)get_post_meta($variation_post_id, '_regular_price', true);
                    if (isset($product['discount_percentage']) && !empty($product['discount_percentage'])) {
                        $sale_price = $variation_regular_price * (1 - $product['discount_percentage']);
                        $sale_price = number_format($sale_price, 2, '.', '');
                        $Product->set_sale_price($sale_price);
                    } elseif (isset($product['discount_amount']) && !empty($product['discount_amount'])) {
                        $sale_price = $variation_regular_price - $product['discount_amount'];
                        $sale_price = number_format($sale_price, 2, '.', '');
                        $Product->set_sale_price($sale_price);
                    } elseif (isset($product['discount_fixed_price']) && !empty($product['discount_fixed_price'])) {
                        $sale_price = number_format($product['discount_fixed_price'], 2, '.', '');
                        $Product->set_sale_price($sale_price);
                    }

                    $product['discount_date_from'] = substr($product['discount_date_from'], 0, 10);
                    $product['discount_date_until'] = substr($product['discount_date_until'], 0, 10);
                    $Product->set_date_on_sale_from(strtotime($product['discount_date_from']));
                    $Product->set_date_on_sale_to(strtotime($product['discount_date_until']));
                } else {
                    $variation_regular_price = get_post_meta($variation_post_id, '_regular_price', true);
                    $Product->set_price($variation_regular_price);
                    $Product->set_regular_price($variation_regular_price);
                    $Product->set_sale_price('');

                    $Product->set_date_on_sale_from('');
                    $Product->set_date_on_sale_to('');
                }
            }
        }

        if (!isset($_price) || empty($_price)) {
            $_price = $product['retail_price'];
        }
        $Product->set_price($_price);

        // Check if import categories is enabled
        if (get_option('storecontrl_update_categories')) {

            $storecontrl_keep_product_categories = get_option('storecontrl_keep_product_categories');
            if (isset($storecontrl_keep_product_categories) && $storecontrl_keep_product_categories) {
                // Check if empty and process categories because nothing is manual set yet
                $existing_product_categories = wp_get_object_terms($post_id, 'product_cat');
                if (empty($existing_product_categories)) {
                    $category_ids = $this->save_product_categories($product);
                    $Product->set_category_ids($category_ids);
                }

                // KEEP EXISTING CATEGORIES
            } else {
                $category_ids = $this->save_product_categories($product);
                $Product->set_category_ids($category_ids);
            }
        }

        // Save woocommerce product tags
        $product_tags = explode(',', $product['tags']);
        wp_set_object_terms($post_id, array_values($product_tags), 'product_tag', false);

        // Save color as taxonomy or attribute
        $storecontrl_process_color = get_option('storecontrl_process_color');
        if (empty($storecontrl_process_color) || (isset($storecontrl_process_color) && $storecontrl_process_color == 'as_category')) {
            if (taxonomy_exists('product_color')) {
                wp_set_object_terms($post_id, $product['color'], 'product_color', false);
            }
        } elseif (empty($storecontrl_process_color) || (isset($storecontrl_process_color) && $storecontrl_process_color == 'skip')) {
            // Do nothing ;)
        } else {

            $attribute_taxonomy_id = $this->proccess_add_attribute(array(
                'attribute_name' => 'pa_color',
                'attribute_label' => ucfirst('Color'),
                'attribute_type' => 'text',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => true
            ));

            $Attribute = new WC_Product_Attribute();
            $Attribute->set_id( $attribute_taxonomy_id );
            $Attribute->set_name( 'pa_color' );
            $Attribute->set_options( [$product['color']] );
            $Attribute->set_visible(1);
            $Attribute->set_variation(0);
            $product_attributes['color'] = $Attribute;
        }

        // Save brand as taxonomy or attribute
        $storecontrl_process_brand = get_option('storecontrl_process_brand');
        if (empty($storecontrl_process_brand) || (isset($storecontrl_process_brand) && $storecontrl_process_brand == 'as_category')) {

            if (taxonomy_exists('product_brand')) {
                wp_set_object_terms($post_id, $product['brand'], 'product_brand', false);
            }
        } else {

            $attribute_taxonomy_id = $this->proccess_add_attribute(array(
                'attribute_name' => 'pa_brand',
                'attribute_label' => ucfirst('Brand'),
                'attribute_type' => 'text',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => true
            ));

            $Attribute = new WC_Product_Attribute();
            $Attribute->set_id($attribute_taxonomy_id);
            $Attribute->set_name( 'pa_brand' );
            $Attribute->set_options( [$product['brand']] );
            $Attribute->set_visible(1);
            $Attribute->set_variation(0);
            $product_attributes['brand'] = $Attribute;
        }

        // Save season as taxonomy or attribute
        $storecontrl_process_season = get_option('storecontrl_process_season');
        if (empty($storecontrl_process_season) || (isset($storecontrl_process_season) && $storecontrl_process_season == 'as_category')) {

            if (taxonomy_exists('product_lookbook')) {
                wp_set_object_terms($post_id, $product['season'], 'product_lookbook', false);
            } elseif (taxonomy_exists('product_season')) {
                wp_set_object_terms($post_id, $product['season'], 'product_season', false);
            }
        } elseif (empty($storecontrl_process_season) || (isset($storecontrl_process_season) && $storecontrl_process_season == 'skip')) {
            // Do nothing ;)
        } else {

            $attribute_taxonomy_id = $this->proccess_add_attribute(array(
                'attribute_name' => 'pa_season',
                'attribute_label' => ucfirst('Season'),
                'attribute_type' => 'text',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => true
            ));

            $Attribute = new WC_Product_Attribute();
            $Attribute->set_id($attribute_taxonomy_id);
            $Attribute->set_name( 'pa_season' );
            $Attribute->set_options( [$product['season']] );
            $Attribute->set_visible(1);
            $Attribute->set_variation(0);
            $product_attributes['season'] = $Attribute;
        }

        // Save supplier as attribute
        $storecontrl_process_supplier = get_option('storecontrl_process_supplier');
        if (isset($storecontrl_process_supplier) && $storecontrl_process_supplier) {

            $attribute_taxonomy_id = $this->proccess_add_attribute(array(
                'attribute_name' => 'pa_supplier',
                'attribute_label' => ucfirst('Supplier'),
                'attribute_type' => 'text',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => true
            ));

            $Attribute = new WC_Product_Attribute();
            $Attribute->set_id($attribute_taxonomy_id);
            $Attribute->set_name( 'pa_supplier' );
            $Attribute->set_options( [$product['supplier']] );
            $Attribute->set_visible(1);
            $Attribute->set_variation(0);
            $product_attributes['supplier'] = $Attribute;
        }

        // Save supplier_code as attribute
        $storecontrl_process_supplier_code = get_option('storecontrl_process_supplier_code');
        if (isset($storecontrl_process_supplier_code) && $storecontrl_process_supplier_code) {

            $attribute_taxonomy_id = $this->proccess_add_attribute(array(
                'attribute_name' => 'pa_supplier_code',
                'attribute_label' => ucfirst('Supplier_code'),
                'attribute_type' => 'text',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => true
            ));

            $Attribute = new WC_Product_Attribute();
            $Attribute->set_id($attribute_taxonomy_id);
            $Attribute->set_name( 'pa_supplier_code' );
            $Attribute->set_options( [$product['supplier_sku']] );
            $Attribute->set_visible(1);
            $Attribute->set_variation(0);
            $product_attributes['supplier_code'] = $Attribute;
        }

        // Save color_code as attribute
        $storecontrl_process_color_code = get_option('storecontrl_process_color_code');
        if (isset($storecontrl_process_color_code) && $storecontrl_process_color_code) {

            $attribute_taxonomy_id = $this->proccess_add_attribute(array(
                'attribute_name' => 'pa_color_code',
                'attribute_label' => ucfirst('Color_code'),
                'attribute_type' => 'text',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => true
            ));

            $Attribute = new WC_Product_Attribute();
            $Attribute->set_id($attribute_taxonomy_id);
            $Attribute->set_name( 'pa_color_code' );
            $Attribute->set_options( [$product['supplier_color_code']] );
            $Attribute->set_visible(1);
            $Attribute->set_variation(0);
            $product_attributes['color_code'] = $Attribute;
        }

        // Save sub_group as attribute
        $storecontrl_process_sub_group = get_option('storecontrl_process_sub_group');
        if (isset($storecontrl_process_sub_group) && $storecontrl_process_sub_group) {

            $attribute_taxonomy_id = $this->proccess_add_attribute(array(
                'attribute_name' => 'pa_subsoort',
                'attribute_label' => ucfirst('Subsoort'),
                'attribute_type' => 'text',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => true
            ));

            $Attribute = new WC_Product_Attribute();
            $Attribute->set_id($attribute_taxonomy_id);
            $Attribute->set_name( 'pa_subsoort' );
            $Attribute->set_options( [$product['sub_group']] );
            $Attribute->set_visible(1);
            $Attribute->set_variation(0);
            $product_attributes['subsoort'] = $Attribute;
        }

        // Functie voor het opschonen van product attributen voor variaties die niet meer worden gebruikt.
        $existing_product_attributes = get_post_meta($post_id, '_product_attributes', true);
        if (isset($existing_product_attributes) && !empty($existing_product_attributes)) {

            foreach ($existing_product_attributes as $existing_product_attribute) {
                if (!isset($existing_product_attribute['is_variation']) || $existing_product_attribute['is_variation'] != 1) {
                    continue;
                }

                if (isset($product['product_atributes'])) {
                    $product['product_atributes'] = (array)$product['product_atributes'];
                    if (count($product['product_atributes']) == 1) {
                        $variation_attribute = $this->generate_clean_slug(end($product['product_atributes']));
                        if ($existing_product_attribute['name'] != 'pa_' . $variation_attribute) {

                            unset($existing_product_attributes[$existing_product_attribute['name']]);
                            $this->functions->custom_update_post_meta($post_id, '_product_attributes', $existing_product_attributes);
                        }
                    } else {

                        foreach ($product['product_atributes'] as $variation_attribute) {
                            if ($existing_product_attribute['name'] != 'pa_' . $variation_attribute) {

                                unset($existing_product_attributes[$existing_product_attribute['name']]);
                                $this->functions->custom_update_post_meta($post_id, '_product_attributes', $existing_product_attributes);
                            }
                        }
                    }
                }
            }
        }

        // Save product variations
        if (isset($product['variations']) && !empty($product['variations'])) {

            // Add all attributes to the global size term for filtering ( As in the basic plugin )
            $product_attributes = array_merge($product_attributes, $this->insert_product_attributes('size', $product['variations']));
            $product_attributes = array_merge($product_attributes, $this->insert_product_attributes($product['product_atributes'], $product['variations']));
            $this->insert_product_variations($post_id, $product);
        }
        else{
            $current_product_attributes = $Product->get_attributes();
            foreach( $current_product_attributes as $attribute_key => $current_product_attribute ){
                $current_product_attributes[str_replace('pa_', '', $attribute_key)] = $current_product_attribute;
                unset($current_product_attributes[$attribute_key]);
            }
            $product_attributes = array_merge($current_product_attributes, $product_attributes );
        }

        $product_details = new WC_Product($post_id);
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $gallery_ids = $product_details->get_gallery_image_ids();
        if (!empty($thumbnail_id) || !empty($gallery_ids)) {
            //PRODUCT HAS THUMBNAIL (FEATURED IMAGE) OR GALLERY IMAGES
            //CHANGE POST STATUS TO THE INITIAL STATUS

            $storecontrl_keep_product_status = get_option('storecontrl_keep_product_status');
            if (!isset($storecontrl_keep_product_status) || !$storecontrl_keep_product_status) {
                $post_status = get_option("storecontrl_new_product_status") ? "publish" : "draft";
            }
        } else {
            //PRODUCT HAS NO THUMBNAIL (FEATURED IMAGE) OR GALLERY IMAGES
            //UPDATE THE POST STATUS
            $no_image_post_status = get_option("storecontrl_no_images_product_status") ? "publish" : "draft";

            $storecontrl_keep_product_status = get_option('storecontrl_keep_product_status');
            if (!isset($storecontrl_keep_product_status) || !$storecontrl_keep_product_status) {
                $post_status = $no_image_post_status;

                // LOG
                $logging = new StoreContrl_WP_Connection_Logging();
                $logging->log_file_write('Status | Post (' . $post_id . ') has no image. Updating post-status to: ' . $no_image_post_status);
            }
        }

        if( isset($post_status) ){
            $Product->set_status( $post_status );
        }

        $Product->set_attributes($product_attributes);
        $Product->save();

        // Set product type
        $this->functions->custom_update_post_meta($post_id, 'latest_update', date('Y-m-d H:i'));

        do_action('after_sc_product_process', $post_id, $product);

        return true;
    }

    public function save_product_categories($product)
    {

        // Define vars
        $product_categories = array();
        $product_tags = array();

        // Create main categories by tags
        $tags = explode(',', $product['tags']);

        // Iterate tags only when enabled
        $use_tags = get_option('storecontrl_use_tags');
        $category_tags = get_option('storecontrl_tags_categories');
        if (isset($tags) && count($tags) > 0 && $use_tags && (isset($category_tags) && !empty($category_tags))) {

            if (strpos($category_tags, ',') !== false) {
                $category_tags = explode(',', $category_tags);
            }
            $category_tags = (array)$category_tags;

            // Iterate tags
            foreach ($tags as $tag) {

                // Check whether the category or tag is empty
                if (!$this->is_empty_category($tag)) {

                    // Check for tags category structure
                    if( strpos($tag, '/') ){
                        $hierachy_category_tags = explode('/', $tag);

                        $parent_id = 0;
                        foreach( $hierachy_category_tags as $hierachy_category_tag ){
                            $category = term_exists(htmlentities(ucfirst($hierachy_category_tag)), 'product_cat', $parent_id);
                            if (isset($category) && !empty($category)) {
                                $category_id = $category['term_id'];
                            } else {
                                $args = array(
                                    'parent' => $parent_id
                                );
                                $category = wp_insert_term(ucfirst($hierachy_category_tag), 'product_cat', $args);
                                if( !is_wp_error($category)) {
                                    $category_id = $category['term_id'];
                                }
                            }
                            $parent_id = $category_id;
                            $product_categories[] = (int)$category_id;
                        }

                        continue;
                    }
                    else{
                        // Check if tag exist in options array
                        if (!in_array($tag, $category_tags)) {
                            continue;
                        }
                    }

                    // Save tags
                    $product_tag = term_exists(ucfirst($tag), 'product_tag');
                    if (isset($product_tag) && !empty($product_tag)) {
                        $product_tag = $product_tag['term_id'];
                    } else {
                        $product_tag = wp_insert_term(ucfirst($product['main_type']), 'product_tag');

                        // Check for WP ERROR
                        if (is_wp_error($product_tag)) {
                            // TODO: Log error
                        } else {
                            $product_tag = $product_tag['term_id'];
                        }
                    }
                    $product_tags[] = (int)$product_tag;

                    // Get/Save main tag as category
                    if( substr( $tag, 0, 1 ) === "-" || substr( $tag, 0, 1 ) === "--" ){
                        if (!$this->is_empty_category($product['main_group'])) {
                            // Get/Save main category
                            $main_category = term_exists(htmlentities(ucfirst($product['main_group'])), 'product_cat', 0);
                            if (isset($main_category) && !empty($main_category)) {
                                $main_category_id = $main_category['term_id'];
                            }
                            else{
                                continue;
                            }
                        }
                    }
                    else{
                        $main_category = get_terms(array('taxonomy' => 'product_cat', 'name' => ucfirst($tag), 'parent' => 0));
                        if (isset($main_category) && !empty($main_category)) {
                            $main_category_id = $main_category[0]->term_id;
                        } else {
                            $main_category = wp_insert_term(ucfirst($tag), 'product_cat');

                            // Check for WP ERROR
                            if (is_wp_error($main_category)) {
                                // TODO: Log error
                            } else {
                                $main_category_id = $main_category['term_id'];
                            }

                        }
                    }
                    $product_categories[$main_category_id] = (int)$main_category_id;

                    // Get/Save sub category
                    if( substr( $tag, 0, 1 ) === "-" && substr( $tag, 0, 2 ) !== "--" ){
                        $tag = str_replace('-', '', $tag);
                        $sub_category_2 = term_exists(htmlentities(ucfirst($tag)), 'product_cat', $main_category_id);
                        if (isset($sub_category_2) && !empty($sub_category_2)) {
                            $sub_category_id_2 = $sub_category_2['term_id'];
                        } else {
                            $args = array(
                                'parent' => $main_category_id
                            );
                            $sub_category_2 = wp_insert_term(ucfirst($tag), 'product_cat', $args);

                            // Check for WP ERROR
                            if (is_wp_error($sub_category_2)) {
                                // TODO: Log error
                            } else {
                                $sub_category_id_2 = $sub_category_2['term_id'];
                            }
                        }
                    }
                    else{
                        if (isset($product['main_type']) && !in_array(str_replace('.', '', strtolower($product['main_type'])), array('-', 'nvt', 'n.v.t', 'nvt.', 'n.v.t.', 'n-a', 'n/a', '*empty*'))) {
                            $sub_category_2 = term_exists(htmlentities(ucfirst($product['main_type'])), 'product_cat', $main_category_id);
                            if (isset($sub_category_2) && !empty($sub_category_2)) {
                                $sub_category_id_2 = $sub_category_2['term_id'];
                            } else {
                                $args = array(
                                    'parent' => $main_category_id
                                );
                                $sub_category_2 = wp_insert_term(ucfirst($product['main_type']), 'product_cat', $args);

                                // Check for WP ERROR
                                if (is_wp_error($sub_category_2)) {
                                    // TODO: Log error
                                } else {
                                    $sub_category_id_2 = $sub_category_2['term_id'];
                                }
                            }
                        }
                    }
                    $product_categories[$sub_category_id_2] = (int)$sub_category_id_2;

                    // Get/Save sub category
                    if( substr( $tag, 0, 2 ) === "--" ){
                        $tag = str_replace('--', '', $tag);
                        $sub_category_3 = term_exists(htmlentities(ucfirst($tag)), 'product_cat', $sub_category_id_2);
                        if (isset($sub_category_3) && !empty($sub_category_3)) {
                            $sub_category_id_3 = $sub_category_3['term_id'];
                        } else {
                            if (isset($sub_category_id_2)) {
                                $args = array(
                                    'parent' => $sub_category_id_2
                                );
                            }
                            $sub_category_3 = wp_insert_term(ucfirst($tag), 'product_cat', $args);

                            // Check for WP ERROR
                            if (is_wp_error($sub_category_3)) {
                                // TODO: Log error
                            } else {
                                $sub_category_id_3 = $sub_category_3['term_id'];
                            }
                        }
                        $product_categories[$sub_category_id_3] = (int)$sub_category_id_3;
                    }
                    else {
                        if (isset($product['sub_group']) && !in_array(str_replace('.', '', strtolower($product['sub_group'])), array('-', 'nvt', 'n.v.t', 'nvt.', 'n.v.t.', 'n-a', 'n/a', '*empty*'))) {
                            $sub_category_3 = term_exists(htmlentities(ucfirst($product['sub_group'])), 'product_cat', $sub_category_id_2);
                            if (isset($sub_category_3) && !empty($sub_category_3)) {
                                $sub_category_id_3 = $sub_category_3['term_id'];
                            } else {
                                if (isset($sub_category_id_2)) {
                                    $args = array(
                                        'parent' => $sub_category_id_2
                                    );
                                }
                                $sub_category_3 = wp_insert_term(ucfirst($product['sub_group']), 'product_cat', $args);

                                // Check for WP ERROR
                                if (is_wp_error($sub_category_3)) {
                                    // TODO: Log error
                                } else {
                                    $sub_category_id_3 = $sub_category_3['term_id'];
                                }
                            }
                        }
                    }
                    $product_categories[$sub_category_id_3] = (int)$sub_category_id_3;
                }
            }
        }

        // Check whether the main category is empty
        if (!$this->is_empty_category($product['main_group'])) {
            // Get/Save main category
            $main_category = term_exists(htmlentities(ucfirst($product['main_group'])), 'product_cat', 0);
            if (isset($main_category) && !empty($main_category)) {
                $main_category_id = $main_category['term_id'];
            } else {
                $main_category = wp_insert_term(ucfirst($product['main_group']), 'product_cat');
                // Check for WP ERROR
                if (is_wp_error($main_category)) {
                    // TODO: Log error
                } else {
                    $main_category_id = $main_category['term_id'];
                }
            }
            $product_categories[] = (int)$main_category_id;

            // Connect brand and category
            $category_brands = (array)get_term_meta($main_category_id, 'category_brands', true);
            $category_brands[$product['brand']] = $product['brand'];
            update_term_meta($main_category_id, 'category_brands', $category_brands );
        }

        if (!$this->is_empty_category($product['main_type'])) {
            // Get/Save sub category
            if (isset($main_category_id) && !empty($main_category_id)) {
                $args = array(
                    'parent' => $main_category_id
                );
                $sub_category_1 = term_exists(htmlentities(ucfirst($product['main_type'])), 'product_cat', $main_category_id);
            } else {
                $args = array();
                $sub_category_1 = term_exists(htmlentities(ucfirst($product['main_type'])), 'product_cat', 0); // Parent category
            }
            if (isset($sub_category_1) && !empty($sub_category_1)) {
                $sub_category_id_1 = $sub_category_1['term_id'];
            } else {
                $sub_category_1 = wp_insert_term(ucfirst($product['main_type']), 'product_cat', $args);
                // Check for WP ERROR
                if (is_wp_error($sub_category_1)) {

                    // Check whether the error relates to a term that already excists
                    if ($sub_category_1->get_error_code() == "term_exists") {
                        $sub_category_id_1 = $sub_category_1->get_error_data("term_exists");
                    } else {
                        $logging = new StoreContrl_WP_Connection_Logging();
                        $logging->log_file_write('WP error | Error creating new subterm');
                    }
                } else {
                    $sub_category_id_1 = $sub_category_1['term_id'];
                }
            }
            $product_categories[] = (int)$sub_category_id_1;

            // Connect brand and category
            $category_brands = (array)get_term_meta($sub_category_id_1, 'category_brands', true);
            $category_brands[$product['brand']] = $product['brand'];
            update_term_meta($sub_category_id_1, 'category_brands', $category_brands );

            // Check whether the sub group is empty
            if ( isset($product['sub_group']) && !$this->is_empty_category($product['sub_group'])) {
                // Get/Save sub category
                $sub_category_2 = term_exists(htmlentities(ucfirst($product['sub_group'])), 'product_cat', $sub_category_id_1);
                if (isset($sub_category_2) && !empty($sub_category_2)) {
                    $sub_category_id_2 = $sub_category_2['term_id'];
                } else {
                    $args = array(
                        'parent' => $sub_category_id_1
                    );
                    $sub_category_2 = wp_insert_term(ucfirst($product['sub_group']), 'product_cat', $args);
                    // Check for WP ERROR
                    if (is_wp_error($sub_category_2)) {
                        // TODO: Log error
                    } else {
                        $sub_category_id_2 = $sub_category_2['term_id'];
                    }
                }

                $product_categories[] = (int)$sub_category_id_2;

                // Connect brand and category
                $category_brands = (array)get_term_meta($sub_category_id_2, 'category_brands', true);
                $category_brands[$product['brand']] = $product['brand'];
                update_term_meta($sub_category_id_2, 'category_brands', $category_brands );
            }

        }

        // This category contains all subcategories and related products
        $additional_category = get_option('storecontrl_custom_category');
        $additional_category_excludes = get_option('storecontrl_custom_category_excludes');

        if (isset($additional_category_excludes) && !empty($additional_category_excludes)) {
            $additional_category_excludes = explode(',', $additional_category_excludes);
        } else {
            $additional_category_excludes = array();
        }

        if (isset($additional_category['term_id']) && !empty($additional_category['term_id'])) {

            // Check if main_group, sub_group or sub_type exist in excluded list
            if (
                in_array($product['main_group'], $additional_category_excludes) || in_array(strtolower($product['main_group']), $additional_category_excludes)
                ||
                isset($product['sub_group']) && (in_array($product['sub_group'], $additional_category_excludes) || in_array(strtolower($product['sub_group']), $additional_category_excludes))
                ||
                in_array($product['main_type'], $additional_category_excludes) || in_array(strtolower($product['main_type']), $additional_category_excludes)
            ) {
                // Product main group is found in excluded list and should not be added to the additional categorie
            } else {

                // Get/Save main category
                $main_category = term_exists((int)$additional_category['term_id'], 'product_cat');
                if (isset($main_category) && !empty($main_category)) {
                    $custom_main_id = (int)$additional_category['term_id'];
                    $product_categories[] = $custom_main_id;

                    // Check whether the category isn't empty
                    if (!$this->is_empty_category($product['main_group'])) {

                        // Check if default product_cat is equal to the main category
                        $default_product_cat = get_option('default_product_cat');
                        if ($custom_main_id != $default_product_cat) {
                            $main_category = term_exists(htmlentities(ucfirst($product['main_group'])), 'product_cat', $custom_main_id);
                        } else {
                            $main_category = term_exists(htmlentities(ucfirst($product['main_group'])), 'product_cat');
                        }

                        if (isset($main_category) && !empty($main_category)) {
                            $main_category_id = $main_category['term_id'];
                        } else {
                            $args = array(
                                'parent' => $custom_main_id
                            );
                            $main_category = wp_insert_term(ucfirst($product['main_group']), 'product_cat', $args);

                            if (is_wp_error($main_category)) {
                                $error_string = $main_category->get_error_message();
                                $logging = new StoreContrl_WP_Connection_Logging();
                                $logging->log_file_write('WP error | ' . $error_string);
                            } else {
                                $main_category_id = $main_category['term_id'];
                            }
                        }
                        $product_categories[] = (int)$main_category_id;

                        // Connect brand and category
                        $category_brands = (array)get_term_meta($main_category_id, 'category_brands', true);
                        $category_brands[$product['brand']] = $product['brand'];
                        update_term_meta($main_category_id, 'category_brands', $category_brands );
                    }

                    // Check whether the category isn't empty
                    if (!$this->is_empty_category($product['main_type'])) {
                        // Get/Save sub category
                        $sub_category_1 = term_exists(htmlentities(ucfirst($product['main_type'])), 'product_cat', $main_category_id);
                        if (isset($sub_category_1) && !empty($sub_category_1)) {
                            $sub_category_id_1 = $sub_category_1['term_id'];
                        } else {
                            $args = array(
                                'parent' => $main_category_id
                            );
                            $sub_category_1 = wp_insert_term(ucfirst($product['main_type']), 'product_cat', $args);

                            if (is_wp_error($sub_category_1)) {
                                $error_string = $sub_category_1->get_error_message();
                                $logging = new StoreContrl_WP_Connection_Logging();
                                $logging->log_file_write('WP error | ' . $error_string);
                            } else {
                                $sub_category_id_1 = $sub_category_1['term_id'];
                            }
                        }
                        $product_categories[] = (int)$sub_category_id_1;

                        // Connect brand and category
                        $category_brands = (array)get_term_meta($sub_category_1, 'category_brands', true);
                        $category_brands[$product['brand']] = $product['brand'];
                        update_term_meta($sub_category_1, 'category_brands', $category_brands );
                    }

                    // Get/Save sub category
                    if (!$this->is_empty_category($product['sub_group'])) {
                        $sub_category_2 = term_exists(htmlentities(ucfirst($product['sub_group'])), 'product_cat', $sub_category_id_1);
                        if (isset($sub_category_2) && !empty($sub_category_2)) {
                            $sub_category_id_2 = $sub_category_2['term_id'];
                        } else {
                            $args = array(
                                'parent' => $sub_category_id_1
                            );
                            $sub_category_2 = wp_insert_term(ucfirst($product['sub_group']), 'product_cat', $args);

                            if (is_wp_error($sub_category_2)) {
                                $error_string = $sub_category_2->get_error_message();
                                $logging = new StoreContrl_WP_Connection_Logging();
                                $logging->log_file_write('WP error | ' . $error_string);
                            } else {
                                $sub_category_id_2 = $sub_category_2['term_id'];
                            }
                        }
                        $product_categories[] = (int)$sub_category_id_2;

                        // Connect brand and category
                        $category_brands = (array)get_term_meta($sub_category_id_2, 'category_brands', true);
                        $category_brands[$product['brand']] = $product['brand'];
                        update_term_meta($sub_category_id_2, 'category_brands', $category_brands );
                    }

                }
            }
        }

        // This category sale products
        $sale_category = get_option('storecontrl_sale_category');
        if ((isset($sale_category['term_id']) && !empty($sale_category['term_id'])) && $product['sale_product']) {

            // Check if discount has a date in the future
            $current_date = date('Y-m-d');
            if(isset($product['discount_date_from']) && substr($product['discount_date_from'], 0, 10) > $current_date || substr($product['discount_date_until'], 0, 10) < $current_date) {
                // DON't ADD TO SALE
            } else {
                $product_categories[] = (int)$sale_category['term_id'];
            }
        }

        return array_values($product_categories);
    }

    private function is_empty_category($category_value)
    {
        if (!isset($category_value) || $category_value == null || empty($category_value) || in_array(strtolower($category_value), array('-', 'nvt', 'n.v.t', 'nvt.', 'n.v.t.', 'n-a', 'n/a', '*empty*'))) {
            return true;
        }
        return false;
    }

    public function insert_product_attributes($available_attributes, $variations = NULL )
    {
        // Check for product variations of product attributes
        $use_as_variation = false;
        if (is_array($available_attributes) || is_object($available_attributes)) {
            $available_attributes = (array)$available_attributes;
            $use_as_variation = true;
        }

        // Iterate available attributes
        foreach ((array)$available_attributes as $attribute) {
            $attribute_name = $this->generate_clean_slug($attribute);

            $attribute_taxonomy_id = $this->proccess_add_attribute(array(
                'attribute_name' => $attribute_name,
                'attribute_label' => ucfirst($attribute),
                'attribute_type' => 'text',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => true
            ));

            // Set up an array to store the current attributes values.
            $values = array();
            if (isset($variations) && !empty($variations) && is_array($variations)) {
                foreach ($variations as $variation) {
                    $variation = (array)$variation;
                    $value = $variation['variation'];

                    foreach ($value as $variation_member) {
                        $variation_member = (array)$variation_member;

                        $variation_member_name = str_replace('?', '', $variation_member['variation_member_name']);
                        if( isset($variation_member['alias_name']) && !empty(trim($variation_member['alias_name'])) ){
                            $variation_member_name = $variation_member['alias_name'];
                        }

                        if ($attribute == 'size') {
                            $values['Size'][] = $variation_member_name;
                        } else {
                            $values[$variation_member['variation_name']][] = $variation_member_name;
                        }
                    }
                }

                // Iterate values
                foreach ($values as $attribute_name => $terms) {

                    $attribute_name = $this->generate_clean_slug($attribute_name);
                    $terms = array_unique($terms); // Filter out duplicate values
                    $terms = array_values($terms);

                    $Attribute = new WC_Product_Attribute();
                    $Attribute->set_id($attribute_taxonomy_id);
                    $Attribute->set_name( 'pa_' . $attribute_name );
                    $Attribute->set_options( $terms );
                    $Attribute->set_visible(1);
                    if ($use_as_variation) {
                        $Attribute->set_variation(1);
                    }
                    else{
                        $Attribute->set_variation(0);
                    }
                    $product_attributes[$attribute_name] = $Attribute;
                }
            }
        }

        return $product_attributes;
    }

    public function proccess_add_attribute($attribute)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
        $attribute_id = $wpdb->get_results($wpdb->prepare("
              SELECT *
              FROM $table_name
              WHERE attribute_name = '%s'
        ", str_replace('pa_', '', $attribute['attribute_name'])), 'ARRAY_A');

        // Check if attribute taxonomy not exist
        if (!isset($attribute_id) || empty($attribute_id)) {

            $attribute_id = wc_create_attribute(
                array(
                    'name' => $attribute['attribute_label'],
                    'slug' => $attribute['attribute_name'],
                    'type' => 'text',
                    'order_by' => 'menu_order',
                    'has_archives' => 0,
                )
            );

            // Log error message as too lang attribute name
            if (isset($attribute_id->errors)) {
                foreach ($attribute_id->errors as $error) {
                    $logging = new StoreContrl_WP_Connection_Logging();
                    $logging->log_file_write('ERROR | ' . $error[0]);
                }
            }

            // Register as taxonomy.
            $taxonomy_name = wc_attribute_taxonomy_name($attribute['attribute_name']);
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
                apply_filters(
                    'woocommerce_taxonomy_args_' . $taxonomy_name,
                    array(
                        'labels' => array(
                            'name' => $attribute['attribute_label'],
                        ),
                        'hierarchical' => false,
                        'show_ui' => false,
                        'query_var' => true,
                        'rewrite' => false,
                    )
                )
            );
        }

        return $attribute_id;
    }

    public function insert_product_variations( $product_id, $product )
    {
        $logging = new StoreContrl_WP_Connection_Logging();

        $variations = $product['variations'];
        foreach ($variations as $index => $variation) {
            $variation = (array)$variation;
            $variation_title = 'Variation ' . $variation['size_id'];

            $args = array(
                'post_type'         => 'product_variation',
                'post_status'       => 'any',
                'posts_per_page'    => -1,
                'post_parent'       => $product_id,
                'meta_query'        => array(
                    array(
                        'key'       => 'storecontrl_size_id',
                        'value'     => $variation['size_id'],
                        'compare'   => '='
                    ),
                ),
            );
            $product_variation = new WP_Query($args);

            // Check if variation exist
            if( isset($product_variation->posts[0]->ID) && !empty($product_variation->posts[0]->ID) ){
                $product_variations_by_size_id = $product_variation->posts;
                $variation_post_id = $product_variation->posts[0]->ID;
                unset($product_variations_by_size_id[0]);

                // Check for duplicates by size_id and delete if found
                if( count($product_variations_by_size_id) > 0 ){
                    foreach( $product_variations_by_size_id as $product_variation_by_size_id ){
                        $logging->log_file_write('NOTICE | Duplicate variation delete by size_id: ' .$product_variation_by_size_id->ID. ' for post ID: ' .$product_id);
                        wp_delete_post($product_variation_by_size_id->ID);
                    }
                }
            }
            else{
                $args = array(
                    'post_title'    => $variation_title,
                    'post_name'     => 'product-' . $product_id . '-variation-' . $variation['size_id'],
                    'post_status'   => 'publish',
                    'post_parent'   => $product_id,
                    'post_type'     => 'product_variation',
                    'guid'          => home_url() . '/?product_variation=product-' . $product_id . '-variation-' . $index,
                );
                $variation_post_id = wp_insert_post($args);
            }

            $variation_attributes = array();
            foreach ($variation['variation'] as $variation_member) {
                $variation_member = (array)$variation_member;
                $variation_name = $this->generate_clean_slug($variation_member['variation_name']);

                $variation_member_name = str_replace('?', '', $variation_member['variation_member_name']);

                $variation_member['alias_name'] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $variation_member['alias_name']);
                $variation_member['alias_name'] = trim($variation_member['alias_name']);
                if( isset($variation_member['alias_name']) && !empty($variation_member['alias_name']) ){
                    $variation_member['alias_name'] = str_replace('?', '', $variation_member['alias_name']);
                    $variation_member_name = $variation_member['alias_name'];

                    $temp_alias_name = str_replace(' ', '', $variation_member['alias_name']);
                    if( !empty($temp_alias_name) ){
                        $variation_member_name = $variation_member['alias_name'];
                    }
                }

                // We need to insert the slug not the name into the variation post meta
                $attribute_term = get_term_by('name', $variation_member_name, 'pa_' . $variation_name);
                if ($attribute_term) {
                    $variation_attributes['attribute_pa_'.$variation_name] = $attribute_term->slug;
                } else {
                    $attribute_term = get_term_by('slug', $variation_member_name, 'pa_' . $variation_name);
                    if ($attribute_term) {
                        $variation_attributes['attribute_pa_'.$variation_name] = $attribute_term->slug;
                    }
                    else {
                        $attribute_term = wp_insert_term($variation_member_name, 'pa_' . $variation_name);
                        if( !is_wp_error($attribute_term)) {
                            $attribute_term = get_term($attribute_term['term_id'], 'pa_' . $variation_name);
                            $variation_attributes['attribute_pa_'.$variation_name] = $attribute_term->slug;
                        }
                    }
                }
            }

            $Variation = new WC_Product_Variation( $variation_post_id );
            $Variation->set_attributes($variation_attributes);
            $Variation->set_stock_quantity($variation['stock']);
            $Variation->set_manage_stock('yes');

            $barcode_as_sku = get_option('storecontrl_set_barcode_as_sku');
            if ($barcode_as_sku == 1 && !empty($variation['barcode'])) {
                if ($Variation->get_sku() != $variation['barcode']) {
                    try {
                        $Variation->set_sku($variation['barcode']);
                    }
                    catch ( WC_Data_Exception $exception ) {}
                }
            }

            $barcode_as_custom_field = get_option('storecontrl_link_barcode_to_field');
            if (isset($barcode_as_custom_field) && !empty($barcode_as_custom_field)) {
                foreach ($barcode_as_custom_field as $key => $metafield) {
                    $this->functions->custom_update_post_meta($variation_post_id, $metafield, $variation['barcode']);
                }
            }

            // Check for product update
            if (isset($product['product_name'])) {

                // Check for sale price
                if (isset($product['to_price']) && $product['to_price'] != 0) {

                    if (isset($variation['retail_price']) && $variation['retail_price'] > 0) {
                        //USE VARIATION AS PRICE
                        $regular_price = $variation['retail_price'];

                        // Check for negative discount number
                        if (isset($product['discount_percentage']) && $product['discount_percentage'] < 0) {
                            $product['discount_percentage'] = $product['discount_percentage'] * -1;
                        }

                        // DISCOUNT - percentage
                        if (isset($product['discount_percentage']) && $product['discount_percentage'] > 0) {
                            $discount_amount = $variation['retail_price'] * $product['discount_percentage'];
                            $discount_price = $variation['retail_price'] - $discount_amount;
                            $discount_price = number_format($discount_price, 2, '.', '');
                        } // DISCOUNT - amount
                        elseif (isset($product['discount_amount']) && $product['discount_amount'] > 0) {
                            $discount_price = $variation['retail_price'] - $product['discount_amount'];
                            $discount_price = number_format($discount_price, 2, '.', '');
                        } // DISCOUNT - Fixed price
                        elseif (isset($product['discount_fixed_price']) && $product['discount_fixed_price'] > 0) {
                            $regular_price = $variation['retail_price'];
                            $discount_price = $product['discount_fixed_price'];
                            $discount_price = number_format($discount_price, 2, '.', '');
                        } else {
                            $regular_price = $variation['retail_price'];
                            $discount_price = NULL;
                        }

                        $Variation->set_regular_price($regular_price);
                        $Variation->set_price($discount_price);
                        $Variation->set_sale_price($discount_price);
                    } else {
                        //USE PRODUCT GLOBAL PRICE
                        $Variation->set_regular_price($product['from_price']);
                        $Variation->set_price($product['to_price']);
                        $Variation->set_sale_price($product['to_price']);
                    }

                    $product['discount_date_from'] = substr($product['discount_date_from'], 0, 10);
                    $product['discount_date_until'] = substr($product['discount_date_until'], 0, 10);
                    $Variation->set_date_on_sale_from(strtotime($product['discount_date_from']));
                    $Variation->set_date_on_sale_to(strtotime($product['discount_date_until']));
                } else {

                    // Check if variation price is different than the product price
                    if (isset($product['from_price'])) {
                        $variation_price = $product['from_price'];
                        if ($product['from_price'] != $variation['retail_price'] && $variation['retail_price'] > 0) {
                            $variation_price = $variation['retail_price'];
                        }
                    } else {
                        $variation_price = $variation['retail_price'];
                    }

                    $Variation->set_regular_price(number_format($variation_price, 2, '.', ''));
                    $Variation->set_price(number_format($variation_price, 2, '.', ''));
                    $Variation->set_sale_price('');
                    $Variation->set_date_on_sale_from('');
                    $Variation->set_date_on_sale_to('');
                }
            } // Check for variation change
            else {

                $sc_discount_id = get_post_meta($product_id, 'sc_discount_id', true);

                if (isset($sc_discount_id) && !empty($sc_discount_id)) {

                    $storecontrl_masterdata = $this->storecontrl_masterdata;
                    $product_discount = $storecontrl_masterdata['DiscountInfo'][$sc_discount_id];

                    if (isset($product_discount['percentage']) && $product_discount['percentage'] > 0) {
                        $discount_amount = $variation['retail_price'] * $product_discount['percentage'];
                        $discount_price = $variation['retail_price'] - $discount_amount;
                        $discount_price = number_format($discount_price, 2, '.', '');
                    } // DISCOUNT - amount
                    elseif (isset($product_discount['amount']) && $product_discount['amount'] > 0) {
                        $discount_price = $variation['retail_price'] - $product_discount['amount'];
                        $discount_price = number_format($discount_price, 2, '.', '');
                    } // DISCOUNT - Fixed price
                    elseif (isset($product_discount['price']) && $product_discount['price'] > 0) {
                        $discount_price = $product_discount['price'];
                        $discount_price = number_format($discount_price, 2, '.', '');
                    }

                    $Variation->set_regular_price(number_format($variation['retail_price'], 2, '.', ''));
                    $Variation->set_price($discount_price);
                    $Variation->set_sale_price($discount_price);
                    $Variation->set_date_on_sale_from(strtotime(substr($product_discount['date_from'], 0, 10) . ' 00:00'));
                    $Variation->set_date_on_sale_to(strtotime(substr($product_discount['date_until'], 0, 10) . ' 23:45'));
                }
                else {
                    $Variation->set_regular_price(number_format($variation['retail_price'], 2, '.', ''));
                    $Variation->set_price(number_format($variation['retail_price'], 2, '.', ''));
                    $Variation->set_sale_price('');
                    $Variation->set_date_on_sale_from('');
                    $Variation->set_date_on_sale_to('');
                }
            }

            // Product was in SALE but no longer
            if (isset($product['removed_from_sale']) && $product['removed_from_sale']) {
                $Variation->set_regular_price(number_format($variation_price, 2, '.', ''));
                $Variation->set_price(number_format($variation_price, 2, '.', ''));
                $Variation->set_sale_price('');
                $Variation->set_date_on_sale_from('');
                $Variation->set_date_on_sale_to('');

                // Remove product from sale
                $sale_category = get_option('storecontrl_sale_category');
                if ((isset($sale_category['term_id']) && !empty($sale_category['term_id']))) {
                    wp_remove_object_terms($product_id, (int)$sale_category['term_id'], 'product_cat');
                }
            }

            // Remove sale dates from variation if zero stock otherwise it gets backorder status by WC
            if (isset($variation['stock']) && $variation['stock'] <= 0) {
                $_backorders = get_post_meta($variation_post_id, '_backorders', true);
                if ($_backorders != 'notify' && $_backorders != 'yes' ) {
                    $Variation->set_date_on_sale_from('');
                    $Variation->set_date_on_sale_to('');
                    $Variation->set_backorders('no');
                }
            }

            $Variation->save();
            $this->functions->custom_update_post_meta($variation_post_id, 'storecontrl_size_id', $variation['size_id']);
        }

        // Remove variations without a price
        $handle = new WC_Product_Variable($product_id);
        $all_product_variations = $handle->get_children();
        if (isset($all_product_variations) && !empty($all_product_variations)) {

            if (count($all_product_variations) > count($variations)) {

                // Array met alle nieuwe size_ids vanuit SToreContrl
                $new_product_size_list = array();
                foreach ($variations as $product_variation) {
                    $product_variation = (array)$product_variation;
                    $new_product_size_list[$product_variation['size_id']] = $product_variation['size_id'];
                }
            }
        }
    }

    public function save_product_images($post_id, $product)
    {

        $attachment_ids = array();

        $functions = new StoreContrl_WP_Connection_Functions();
        $logging = new StoreContrl_WP_Connection_Logging();

        $storecontrl_hide_featured_image_from_gallery = get_option('storecontrl_hide_featured_image_from_gallery');

        if (!isset($product['photos'][0]) || empty($product['photos'][0])) {
            delete_post_thumbnail($post_id);
        } else {
            sort($product['photos']);
            foreach ($product['photos'] as $key => $product_photo) {

                $attach_id = $functions->get_attachment_id_by_title($product_photo);

                // Check if attachment exist in DB but image not exist
                if (isset($attach_id) && !empty($attach_id)) {
                    $attachment_src_path = wp_get_attachment_image_src($attach_id, 'full');

                    // Check if file not exist in uploads directory ( new upload necessary )
                    if ( (isset($attachment_src_path[0]) && !$functions->is_url_exist($attachment_src_path[0]) ) || !$attachment_src_path ){
                        wp_delete_attachment($attach_id);
                        $attach_id = '';
                    }
                }

                // Only save new images
                if (!isset($attach_id) || empty($attach_id)) {

                    $image_name = $product_photo;
                    $upload_dir = wp_upload_dir(); // Set upload folder
                    $filename = $image_name; // Create image file name

                    // Full path to a remote file
                    $remote_path = '/' . $product['product_id'] . "/L/" . $product['photos'][$key];

                    // Check folder permission and define file location
                    if (wp_mkdir_p($upload_dir['path'])) {
                        $upload_path = $upload_dir['path'] . '/' . $filename;
                    } else {
                        $upload_path = $upload_dir['basedir'] . '/' . $filename;
                    }

                    // Check if file exist
                    $file_size = ftp_size($this::$storecontrl_ftp_connection, $remote_path);
                    if ($file_size != -1) {
                        if (ftp_get($this::$storecontrl_ftp_connection, $upload_path, $remote_path, FTP_BINARY)) {

                            $source = file_get_contents($upload_path);

                            if ($source !== false and !empty($source)) {

                                // Check image file type
                                $wp_filetype = wp_check_filetype($filename, null);

                                // Create the attachment
                                $attachment = array(
                                    'post_mime_type' => $wp_filetype['type'],
                                    'post_title' => sanitize_file_name($filename),
                                    'post_content' => '',
                                    'post_status' => 'inherit'
                                );
                                $attach_id = wp_insert_attachment($attachment, $upload_path, $post_id);

                                // Include image.php
                                require_once(ABSPATH . 'wp-admin/includes/image.php');

                                // Define attachment metadata
                                $attach_data = wp_generate_attachment_metadata($attach_id, $upload_path);

                                // Assign metadata to attachment
                                wp_update_attachment_metadata($attach_id, $attach_data);

                                // And finally assign first featured image to post
                                if ($key == 0) {

                                    // Hide first image form product gallery
                                    if (!isset($storecontrl_hide_featured_image_from_gallery) || !$storecontrl_hide_featured_image_from_gallery) {
                                        $attachment_ids[] = $attach_id;
                                    }
                                    set_post_thumbnail($post_id, $attach_id);
                                } else {
                                    $attachment_ids[] = $attach_id;
                                }
                            }
                        } else {
                            $logging->log_file_write('Images | Error downloading ' . $remote_path);
                        }
                    } else {
                        $logging->log_file_write('Images | Error file not found ' . $remote_path);
                    }
                } else {

                    if (isset($attach_id) && !empty($attach_id)) {

                        // And finally assign first featured image to post
                        if ($key == 0) {
                            // When enabled hide first image form product gallery
                            if (!isset($storecontrl_hide_featured_image_from_gallery) || !$storecontrl_hide_featured_image_from_gallery) {
                                $attachment_ids[] = $attach_id;
                            }
                            set_post_thumbnail($post_id, $attach_id);
                        } else {
                            $attachment_ids[] = $attach_id;
                        }
                    }
                }
            }

            return $attachment_ids;
        }
    }

    public function storecontrl_update_wc_product_stock($product_variation)
    {
        global $wpdb;

        $logging = new StoreContrl_WP_Connection_Logging();

        //removed limit 1 to form an array and loop through the array
        $existing_product_variation = $wpdb->get_results($wpdb->prepare("
	        SELECT *
	        FROM $wpdb->postmeta
	        WHERE meta_key = 'storecontrl_size_id' AND meta_value = '%s'
	        ORDER BY meta_id DESC
	     ", $product_variation['sku_id']), 'ARRAY_A');

        foreach ($existing_product_variation as $variation) {

            // Check if variation exist
            if (isset($variation['post_id']) && !empty($variation['post_id'])) {

                $variation_post_id = $variation['post_id'];
                $product_id = wp_get_post_parent_id($variation_post_id);

                $Variation = new WC_Product_Variation( $variation_post_id );

                if( isset($product_id) && is_int($product_id) ){
                    $Variation->set_parent_id($product_id);
                }
                else{
                    $logging->log_file_write('ERROR | Unkown parent product for varation: ' . $variation_post_id );
                    $wpdb->query($wpdb->prepare("DELETE * FROM $wpdb->postmeta WHERE meta_id = '%s'", $variation['meta_id']), 'ARRAY_A');
                    return;
                }

                if(abs($product_variation['stock_total']) != $product_variation['stock_total']){
                    $product_variation['stock_total'] = 0;
                }
                $Variation->set_stock_quantity($product_variation['stock_total']);
                $Variation->set_manage_stock('yes');

                // Remove sale dates from variation if zero stock otherwise it gets backorder status by WC
                if( $product_variation['stock_total'] == 0 ){
                    $_backorders = get_post_meta($variation_post_id, '_backorders', true);
                    if ($_backorders != 'notify' && $_backorders != 'yes' ) {
                        $Variation->set_date_on_sale_from();
                        $Variation->set_date_on_sale_to();
                        $Variation->set_backorders('no');
                    }
                }

                $Variation->save();

                $this->functions->custom_update_post_meta($variation_post_id, 'latest_update', date('Y-m-d H:i'));
            }
        }
    }

    public function generate_clean_slug($name)
    {

        $name = strtolower($name);
        $name = str_replace(' ', '_', $name);
        $name = str_replace('/', '_', $name);
        $name = str_replace('(', '', $name);
        $name = str_replace(')', '', $name);
        $name = str_replace('?', '', $name);
        $name = str_replace('!', '', $name);
        $name = str_replace('*', '', $name);
        $name = str_replace('"', '', $name);

        $slug = sanitize_title($name);

        return $slug;
    }
}

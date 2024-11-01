<?php
class StoreContrl_WP_Connection {

	protected $loader;

	public function __construct() {

		$this->load_dependencies();
		$this->define_admin_hooks();

    }

	private function load_dependencies() {

		// Admin
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin/class-storecontrl-wp-connection-admin.php';

		// Cronjob
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/cronjob/class-storecontrl-cronjob-functions.php';

		// Log
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/log/class-storecontrl-wp-connection-logging.php';

		// Default
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-storecontrl-wp-connection-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-storecontrl-wp-connection-functions.php';

		// StoreContrl API
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/api/class-storecontrl-web-api.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/api/class-storecontrl-web-api-functions.php';

		// Arture API
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/api/class-arture-web-api.php';

		// Woocommerce
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/woocommerce/class-storecontrl-woocommerce-functions.php';
	
		$this->loader = new StoreContrl_WP_Connection_Loader();
	}

	private function define_admin_hooks() {
		
		// Admin actions
		$admin = new StoreContrl_WP_Connection_Admin();
		$this->loader->add_action( 'admin_menu', $admin, 'add_storecontrl_wp_connection_admin_pages' );
		$this->loader->add_action( 'admin_init', $admin, 'display_storecontrl_wp_connection_panel_fields' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'admin_scripts_styles' );
        $this->loader->add_action( 'wp_ajax_check_storecontrl_api_connection', $admin, 'check_storecontrl_api_connection' );
        $this->loader->add_action( 'admin_notices', $admin, 'show_marketing_banners' );

        // Cronjobs
		$cronjob = new StoreContrl_Cronjob_Functions();
		$this->loader->add_action( 'parse_request', $cronjob, 'storecontrl_url_handler' );
        $this->loader->add_action( 'wp_ajax_storecontrl_total_synchronization', $cronjob, 'init_total_synchronisation' );
        $this->loader->add_action( 'wp_ajax_storecontrl_refresh_masterdata', $cronjob, 'storecontrl_refresh_masterdata' );
        $this->loader->add_action( 'wp_ajax_storecontrl_synchronize_product', $cronjob, 'storecontrl_synchronize_product' );

		// AJAX
		$this->loader->add_action( 'wp_ajax_send_support_email', $admin, 'send_support_email' );
        $this->loader->add_action( 'wp_ajax_resend_new_order_to_storecontrl', $admin, 'resend_new_order_to_storecontrl' );

        // Woocommerce
		$woocommerce = new StoreContrl_Woocommerce_Functions();
        $this->loader->add_action( 'admin_menu', $woocommerce, 'custom_woocommerce_product_settings' );
        $this->loader->add_action( 'woocommerce_before_cart', $woocommerce, 'add_storecontrl_cart_fee' );
        $this->loader->add_action( 'woocommerce_before_checkout_form', $woocommerce, 'add_storecontrl_cart_fee' );
        $this->loader->add_action( 'wp_enqueue_scripts', $woocommerce, 'sc_enqueue_plugin_scripts' );
        $this->loader->add_action( 'wp_ajax_check_storecontrl_credit_cheque', $woocommerce, 'check_storecontrl_credit_cheque' );
        $this->loader->add_action( 'wp_ajax_nopriv_check_storecontrl_credit_cheque', $woocommerce, 'check_storecontrl_credit_cheque' );
        $this->loader->add_action( 'woocommerce_order_status_changed', $woocommerce, 'create_storecontrl_new_order' );
        $this->loader->add_action( 'add_meta_boxes', $woocommerce, 'register_plugin_metaboxes' );

        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $woocommerce, 'add_storecontrl_order_returned_column' ) );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $woocommerce, 'add_storecontrl_order_returned_column_value' ), 10, 2);
        $this->loader->add_action( 'manage_edit-shop_order_columns', $woocommerce, 'add_storecontrl_order_returned_column' );
        add_action( 'manage_shop_order_posts_custom_column', array( $woocommerce, 'add_storecontrl_order_returned_column_value' ), 10, 1);

        add_action( 'woocommerce_product_after_variable_attributes', array($woocommerce, 'show_size_id_field_variation_data'), 10, 3 );

        // API Functions (arture)
        $arture_api = new Arture_Web_Api();
        $this->loader->add_action( 'admin_notices', $arture_api, 'display_key_error_message' );
        $this->loader->add_action( 'admin_notices', $arture_api, 'display_authorization_message' );
        $this->loader->add_action( 'admin_notices', $arture_api, 'display_missing_mapping' );

        // Logging
        $logging = new StoreContrl_WP_Connection_Logging();
        $this->loader->add_action( 'wp_ajax_get_log_file', $logging, 'get_log_file' );
        $this->loader->add_action( 'wp_ajax_get_batch_file', $logging, 'get_batch_file' );
	}

	public function run() {
		$this->loader->run();
	}
}

<?php
class StoreContrl_WP_Connection_Admin {

	private $functions;

	public function __construct() {

		$this->functions = new StoreContrl_WP_Connection_Functions();

		$plugin_basename = STORECONTRL_WP_CONNECTION_PLUGIN_BASENAME;

		add_filter( "plugin_action_links_$plugin_basename", array( $this, 'plugin_add_settings_link') );

		if(isset($_POST['btnDownloadLog'])) {
			$this->downloadLogFile();
		}
	}

	public function plugin_add_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=storecontrl-wp-connection-panel">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function admin_api_enqueue_plugin_styles() {
		wp_enqueue_style( 'boostrap', plugin_dir_url( __FILE__ ) . 'css/bootstrap.min.css', array(), '', false );
		wp_enqueue_style( 'bootstrap-glyphicons', '//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap-glyphicons.css', array(), '', false );
		wp_enqueue_style( 'boostrap-toggle', plugin_dir_url( __FILE__ ) . 'css/bootstrap-toggle.min.css', array(), '', false );
		wp_enqueue_style( 'timepicker', plugin_dir_url( __FILE__ ) . 'css/timepicker.css', array(), '', false );
		wp_enqueue_style( 'storecontrl-wp-connection-api', plugin_dir_url( __FILE__ ) . 'css/storecontrl-api-admin.css', array(), '', false );
	}

	public function admin_api_enqueue_plugin_scripts() {
		wp_enqueue_script( 'boostrap',  plugin_dir_url( __FILE__ ) . 'js/bootstrap.min.js', array(), '', false );
		wp_enqueue_script( 'boostrap-toggle',  plugin_dir_url( __FILE__ ) . 'js/bootstrap-toggle.min.js', array(), '', false );
		wp_enqueue_script( 'timepicker',  plugin_dir_url( __FILE__ ) . 'js/timepicker.min.js', array(), '', false );
		wp_enqueue_script( 'storecontrl-wp-connection-admin',  plugin_dir_url( __FILE__ ) . 'js/storecontrl-api-admin.js', array(), '', false );
	}

    public function admin_scripts_styles() {
        wp_enqueue_style( 'storecontrl-wp-connection-admin', plugin_dir_url( __FILE__ ) . 'css/storecontrl-admin.css', array(), '', false );
        wp_enqueue_script( 'storecontrl-admin',  plugin_dir_url( __FILE__ ) . 'js/storecontrl-admin.js', array(), '', false );
    }

	public function add_storecontrl_wp_connection_admin_pages() {
		$storecontrl_menu = add_menu_page('StoreContrl API', 'StoreContrl API', 'manage_options', 'storecontrl-wp-connection-panel', array( $this, 'storecontrl_wp_connection_settings_page' ), null, 99);

		add_action( 'admin_print_styles-' . $storecontrl_menu, array($this, 'admin_api_enqueue_plugin_styles') );
		add_action( 'admin_print_scripts-' . $storecontrl_menu, array($this, 'admin_api_enqueue_plugin_scripts') );
	}

	public function storecontrl_wp_connection_settings_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/storecontrl_wp_connection_settings_page.php';
	}

	public function display_storecontrl_wp_connection_panel_fields(){
        add_settings_section('default_section', 'API instellingen', null, 'storecontrl_api_options');
        add_settings_section('setup_section', 'Configuratie', null, 'storecontrl_setup_options');
        add_settings_section('cronjob_section', 'Cronjobs', null, 'storecontrl_cronjob_options');
        add_settings_section('import_section', 'Import instellingen', null, 'storecontrl_import_options');
        add_settings_section('woocommerce_section', 'Woocommerce', null, 'storecontrl_woocommerce_options');
        add_settings_section('debug_section', 'Status logs', null, 'storecontrl_debug_options');
        add_settings_section('addons_section', 'Add-Ons', null, 'storecontrl_addons_options');

		// Default section
        add_settings_field('storecontrl_api_arture_key', __('Arture API key', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_api_arture_key_element' ), 'storecontrl_api_options', 'default_section');
		add_settings_field('storecontrl_api_url', __('API url', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_api_url_element' ), 'storecontrl_api_options', 'default_section');
		add_settings_field('storecontrl_api_key', __('API key', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_api_key_element' ), 'storecontrl_api_options', 'default_section');
		add_settings_field('storecontrl_api_secret', __('API secret', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_api_secret_element' ), 'storecontrl_api_options', 'default_section');
		add_settings_field('storecontrl_api_images_url', __('FTP url', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_api_images_url_element' ), 'storecontrl_api_options', 'default_section');
		add_settings_field('storecontrl_api_ftp_user', __('FTP user', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_api_ftp_user_element' ), 'storecontrl_api_options', 'default_section');
		add_settings_field('storecontrl_api_ftp_password', __('FTP password', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_api_ftp_password_element' ), 'storecontrl_api_options', 'default_section');
        add_settings_field('storecontrl_ssl_verification', 'SSL verificatie',  array( $this, 'display_storecontrl_ssl_verification_element' ), 'storecontrl_api_options', 'default_section');
		
		// Default section
		add_settings_field('storecontrl_setup_info', __('API url', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_setup_info_element' ), 'storecontrl_setup_options', 'setup_section');

		// Import section
		add_settings_field('storecontrl_update_images', 'Product afbeeldingen',  array( $this, 'display_storecontrl_update_images_element' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_new_product_status', 'Product status',  array( $this, 'display_storecontrl_new_product_status_element' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_no_images_product_status', 'Product status bij geen afbeelding',  array( $this, 'display_storecontrl_no_images_product_status_element' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_excerpt', 'Korte omschrijving',  array( $this, 'display_storecontrl_excerpt_element' ), 'storecontrl_import_options', 'import_section');
		add_settings_field('storecontrl_hide_featured_image_from_gallery', 'Uitgelichte afbeelding',  array( $this, 'display_storecontrl_hide_featured_image_from_gallery_element' ), 'storecontrl_import_options', 'import_section');
		add_settings_field('storecontrl_update_categories', 'Product categorieën',  array( $this, 'display_storecontrl_update_categories_element' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_sale_category', 'Sale categorie',  array( $this, 'display_storecontrl_sale_category_element' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_keep_product_title', 'Behoud product titel',  array( $this, 'display_storecontrl_keep_product_title' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_keep_product_description', 'Behoud product omschrijving',  array( $this, 'display_storecontrl_keep_product_description' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_keep_product_categories', 'Behoud product categorieën',  array( $this, 'display_storecontrl_keep_product_categories' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_keep_product_status', 'Behoud product status',  array( $this, 'display_storecontrl_keep_product_status' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_keep_product_images', 'Behoud product afbeeldingen',  array( $this, 'display_storecontrl_keep_product_images' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_set_barcode_as_sku', 'Barcode als SKU',  array( $this, 'display_storecontrl_barcode_to_sku_element' ), 'storecontrl_import_options', 'import_section');

        add_settings_field('storecontrl_pro_settings', '',  array( $this, 'display_storecontrl_pro_settings_element' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_link_barcode_to_field', __('Link barcode to field(s)', 'storecontrl-wp-connection-plugin'),  array( $this, 'display_storecontrl_link_barcode_to_field_element' ), 'storecontrl_import_options', 'import_section');
        add_settings_field('storecontrl_use_tags', 'Gebruik SC tags als extra hoofdcategorie',  array( $this, 'display_storecontrl_use_tags_element' ), 'storecontrl_import_options', 'import_section');
		add_settings_field('storecontrl_tags_categories', '* lijst met tags',  array( $this, 'display_storecontrl_tags_categories_element' ), 'storecontrl_import_options', 'import_section');
		add_settings_field('storecontrl_custom_category', 'Extra hoofdcategorie *',  array( $this, 'display_storecontrl_custom_category_element' ), 'storecontrl_import_options', 'import_section');
		add_settings_field('storecontrl_custom_category_excludes', '* Categorieën uitsluiten',  array( $this, 'display_storecontrl_custom_category_exludes_element' ), 'storecontrl_import_options', 'import_section');

		// Woocommerce section
		add_settings_field('storecontrl_wc_customer_type', 'Type klant',  array( $this, 'display_storecontrl_wc_customer_type_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_wc_shipping_methods', 'Verzendmethodes',  array( $this, 'display_storecontrl_wc_shipping_methods_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_wc_payment_methods', 'Betaalmethodes',  array( $this, 'display_storecontrl_wc_payment_methods_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_wc_new_order', 'Nieuwe bestellingen',  array( $this, 'display_storecontrl_wc_new_order_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_wc_delete_product', 'Verwijder producten',  array( $this, 'display_storecontrl_wc_delete_product_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_process_color', 'Kleur',  array( $this, 'display_storecontrl_process_color_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
        add_settings_field('storecontrl_process_color_code', 'Kleurcode',  array( $this, 'display_storecontrl_process_color_code_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_process_brand', 'Merk',  array( $this, 'display_storecontrl_process_brand_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_process_season', 'Seizoen',  array( $this, 'display_storecontrl_process_season_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_process_supplier', 'Leverancier',  array( $this, 'display_storecontrl_process_supplier_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
		add_settings_field('storecontrl_process_supplier_code', 'Artikelnummer',  array( $this, 'display_storecontrl_process_supplier_code_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');
        add_settings_field('storecontrl_process_sub_group', 'Soort',  array( $this, 'display_storecontrl_process_sub_group_element' ), 'storecontrl_woocommerce_options', 'woocommerce_section');

		// Debug section
		add_settings_field('storecontrl_sync_dates', 'Cronjob status',  array( $this, 'display_storecontrl_sync_dates_element' ), 'storecontrl_debug_options', 'debug_section');
        add_settings_field('storecontrl_remaining_batches', 'Te verwerken batches',  array( $this, 'display_storecontrl_batches_element' ), 'storecontrl_debug_options', 'debug_section');
		add_settings_field('storecontrl_debug_logs', 'Logs',  array( $this, 'display_storecontrl_debug_logs_element' ), 'storecontrl_debug_options', 'debug_section');

        // Add-Ons section
        add_settings_field('storecontrl_creditcheques', __('Spaarpunten', 'storecontrl-wp-connection'),  array( $this, 'display_storecontrl_creditcheques_element' ), 'storecontrl_addons_options', 'addons_section');

        register_setting('storecontrl_api_options', 'storecontrl_api_url');
		register_setting('storecontrl_api_options', 'storecontrl_api_key');
		register_setting('storecontrl_api_options', 'storecontrl_api_secret');
		register_setting('storecontrl_api_options', 'storecontrl_api_images_url');
		register_setting('storecontrl_api_options', 'storecontrl_api_ftp_user');
		register_setting('storecontrl_api_options', 'storecontrl_api_ftp_password');
		register_setting('storecontrl_api_options', 'storecontrl_api_arture_key');
        register_setting('storecontrl_api_options', 'storecontrl_ssl_verification');

		global $woocommerce;

		// Iterate Woocommerce shipping methods
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_shipping_methods = $woocommerce->shipping->get_shipping_methods();
			foreach ( $wc_shipping_methods as $method_name => $wc_shipping_method ) {
				$value = "storecontrl_wc_shipping_method_" . $method_name;
				register_setting( 'storecontrl_woocommerce_options', $value );
			}
            register_setting( 'storecontrl_woocommerce_options', 'storecontrl_wc_shipping_method_default' );

			// Iterate Woocommerce shipping methods
			$wc_payment_methods = $woocommerce->payment_gateways->payment_gateways();
			foreach ( $wc_payment_methods as $method_name => $wc_payment_method ) {
				$value = "storecontrl_wc_payment_method_" . $method_name;
				register_setting( 'storecontrl_woocommerce_options', $value );
			}
            register_setting( 'storecontrl_woocommerce_options', 'storecontrl_wc_payment_method_default' );
		}
		register_setting('storecontrl_woocommerce_options', 'storecontrl_wc_customer_type');
		register_setting('storecontrl_woocommerce_options', 'storecontrl_wc_new_order');
		register_setting('storecontrl_woocommerce_options', 'storecontrl_wc_delete_order');
		register_setting('storecontrl_woocommerce_options', 'storecontrl_wc_delete_product');
		register_setting('storecontrl_woocommerce_options', 'storecontrl_process_color');
        register_setting('storecontrl_woocommerce_options', 'storecontrl_process_color_code');
		register_setting('storecontrl_woocommerce_options', 'storecontrl_process_brand');
		register_setting('storecontrl_woocommerce_options', 'storecontrl_process_season');
		register_setting('storecontrl_woocommerce_options', 'storecontrl_process_supplier');
		register_setting('storecontrl_woocommerce_options', 'storecontrl_process_supplier_code');
        register_setting('storecontrl_woocommerce_options', 'storecontrl_process_sub_group');

		register_setting('storecontrl_cronjob_options', 'storecontrl_synchronisation_time');

		register_setting('storecontrl_import_options', 'storecontrl_update_images');
		register_setting('storecontrl_import_options', 'storecontrl_new_product_status');
		register_setting('storecontrl_import_options', 'storecontrl_no_images_product_status');
		register_setting('storecontrl_import_options', 'storecontrl_excerpt');
		register_setting('storecontrl_import_options', 'storecontrl_hide_featured_image_from_gallery');
		register_setting('storecontrl_import_options', 'storecontrl_update_categories');
        register_setting('storecontrl_import_options', 'storecontrl_keep_product_title');
        register_setting('storecontrl_import_options', 'storecontrl_keep_product_description');
        register_setting('storecontrl_import_options', 'storecontrl_keep_product_categories');
        register_setting('storecontrl_import_options', 'storecontrl_keep_product_status');
        register_setting('storecontrl_import_options', 'storecontrl_keep_product_images');
        register_setting('storecontrl_import_options', 'storecontrl_set_barcode_as_sku');
        register_setting('storecontrl_import_options', 'storecontrl_link_barcode_to_field');
        register_setting('storecontrl_import_options', 'storecontrl_use_variation_alias');
		register_setting('storecontrl_import_options', 'storecontrl_use_tags');
		register_setting('storecontrl_import_options', 'storecontrl_tags_categories');
		register_setting('storecontrl_import_options', 'storecontrl_sale_category');
		register_setting('storecontrl_import_options', 'storecontrl_custom_category');
		register_setting('storecontrl_import_options', 'storecontrl_custom_category_excludes');

		$tags = get_option('storecontrl_tags_categories');
		if( isset($tags) && !empty($tags) ){
			$tags = explode(',', $tags);

			// Iterate tags
			foreach( $tags as $tag ){
				$field_name = 'storecontrl_tag_category_excludes_'.$tag;
				register_setting('storecontrl_import_options', $field_name);
			}
		}

        register_setting('storecontrl_addons_options', 'storecontrl_creditcheques');
	}


	/*
	=================================================
	    DEFAULT SECTION
	=================================================
    */
	public function display_storecontrl_api_url_element(){
		?>
        <input type='text' name='storecontrl_api_url' id='storecontrl_api_url' placeholder="https://CLIENTNAME.sc-cloud-01.nl:1443/WebApi" value='<?php echo $this->functions->getStoreContrlAPIURI(); ?>' style='min-width: 500px'/>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>Controleer of de url voldoet aan de volgende opbouw: https://CLIENTNAME.sc-cloud-01.nl:1443/WebApi</div>
		<?php
	}
	public function display_storecontrl_api_key_element(){
		?>
        <input type='text' name='storecontrl_api_key' id='storecontrl_api_key' placeholder="<?php echo __('Your API key', 'storecontrl-wp-connection-plugin'); ?>" value='<?php echo get_option('storecontrl_api_key'); ?>' style='min-width: 500px'/>
		<?php
	}
	public function display_storecontrl_api_secret_element(){
		?>
        <input type='text' name='storecontrl_api_secret' id='storecontrl_api_secret' placeholder="<?php echo __('Your API secret', 'storecontrl-wp-connection-plugin'); ?>" value='<?php echo get_option('storecontrl_api_secret'); ?>' style='min-width: 500px'/>
		<?php
	}
	public function display_storecontrl_api_images_url_element(){
		?>
        <input type='text' name='storecontrl_api_images_url' id='storecontrl_api_images_url' placeholder="ftp.sc-cloud-01.nl" value='<?php echo get_option('storecontrl_api_images_url'); ?>' style='min-width: 500px'/>
		<?php
	}
	public function display_storecontrl_api_ftp_user_element(){
		?>
        <input type='text' name='storecontrl_api_ftp_user' id='storecontrl_api_ftp_user' placeholder="<?php echo __('FTP user', 'storecontrl-wp-connection-plugin'); ?>" value='<?php echo get_option('storecontrl_api_ftp_user'); ?>' style='min-width: 500px'/>
		<?php
	}
	public function display_storecontrl_api_ftp_password_element(){
		?>
        <input type='text' name='storecontrl_api_ftp_password' id='storecontrl_api_ftp_password' placeholder="<?php echo __('FTP password', 'storecontrl-wp-connection-plugin'); ?>" value='<?php echo get_option('storecontrl_api_ftp_password'); ?>' style='min-width: 500px'/>
		<?php
	}
	public function display_storecontrl_api_arture_key_element(){
		?>
        <input type='text' name='storecontrl_api_arture_key' id='storecontrl_api_arture_key' placeholder="<?php echo __('Arture API key', 'storecontrl-wp-connection-plugin'); ?>" value='<?php echo get_option('storecontrl_api_arture_key'); ?>' style='min-width: 500px'/>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>Deze key wordt verstrekt bij de aanschaf van de plugin en is ook terug te vinden in je account op arture.nl</div>
        <?php
	}
    public function display_storecontrl_ssl_verification_element() {
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_ssl_verification" id="storecontrl_ssl_verification" value="1" <?php checked( '1', get_option( 'storecontrl_ssl_verification' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>Mocht er een SSL melding voorkomen in de logs bij het maken van de verbinding dan kan de volgende optie worden aangezet.</div>
        <?php
    }

	/*
	=================================================
	    SETUP SECTION
	=================================================
    */
	public function display_storecontrl_setup_info_element(){
		?>

        <strong><?php echo __("Let's sell some products!", 'storecontrl-wp-connection'); ?></strong>
        <p>Hier vind u een stap voor stap instructie over het instellen van de plugin. Het is belangrijk dat de stappen op volgorde worden gevolgd voor een stabiele installatie. Controlleer alle import- en Woocommerce instellingen voordat u begint met de syncronisatie met StoreContrl.</p>
        <p>Wilt u zeker weten dat dit correct gebeurd? Wij kunnen u hiermee van dienst zijn via onze <a href="https://www.arture.nl/product/wp-storecontrl-setup/" target="_blank">configuratie</a></p>.

        <h3 class="text-center"><?php echo __("Setup guide", 'storecontrl-wp-connection'); ?></h3>
        <div class="well" style="padding-bottom: 50px;">
            <ul id="check-list-box" class="list-group checked-list-box">
                <li class="list-group-item">API settings | Controleer je API gegevens. Nog geen activatie key? Schaf hem eenvoudig aan op <a href="https://www.arture.nl/product/storecontrl-woocommerce-premium/">Arture</a></li>
                <li class="list-group-item">API settings | Test je verbinding. Controleer de logs op de status tab voor eventuele foutmeldingen.</li>
                <li class="list-group-item">Import settings | Bepaal de instellingen voor het verwerken van de producten. Dit is webshop afhankelijk.</li>
                <li class="list-group-item">Woocommerce | Voor de order verwerking is het noodzakelijk om de juiste verzend en betaalmethodes te koppelen.</li>
                <li class="list-group-item">Setup | Volledige synchronisatie uitvoeren</li>
                <li class="list-group-item">Cronjobs | Stel de cronjob urls in bij je hosting partij zoals hieronder beschreven</li>
                <li class="list-group-item">Caching | Indien je gebruik maakt van een cache plugin de cronjob urls toevoegen aan de whitelist</li>
                <li class="list-group-item">LIVE | Bij livegang support@arture.nl hierover mailen voor de activatie van je koppeling</li>
                <li class="list-group-item">Woocommerce | Activeer de verwerking van de weborders</li>
                <li class="list-group-item">Vragen | Bekijk onze FAQ op https://www.arture.nl/support</li>
            </ul>

            <button class="btn btn-primary col-xs-12" id="storecontrl-refresh-masterdata">Refresh MasterData <img class="loading" style="display: none;" src="<?php echo plugins_url( 'admin/img/loading.gif' , dirname(__FILE__ )); ?>"/></button>
            <br/>
            <button class="btn btn-primary col-xs-12" id="storecontrl-total-synchronization">Volledige synchronisatie <img class="loading" style="display: none;" src="<?php echo plugins_url( 'admin/img/loading.gif' , dirname(__FILE__ )); ?>"/></button>
            <br/>
        </div>

        <table class="table-striped" style="width: 100%; border: 1px solid #00adf2; border-radius: 5px; margin-top: 15px;">
            <thead>
                <tr>
                    <th style="width: 60%">Cronjob url</th>
                    <th style="width: 10%">Interval</th>
                    <th style="width: 30%">Functie</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo site_url().'/storecontrl-process-products'; ?></td>
                    <td>*/5 * * * *</td>
                    <td>Verwerk product batches</td>
                </tr>
                <tr>
                    <td><?php echo site_url().'/storecontrl-process-changes'; ?></td>
                    <td>*/5 * * * *</td>
                    <td>Verwerk product wijzigingen</td>
                </tr>
                <tr>
                    <td><?php echo site_url().'/storecontrl-process-stockchanges'; ?></td>
                    <td>*/5 * * * *</td>
                    <td>Verwerk voorraad wijzigingen</td>
                </tr>
            </tbody>
        </table>
        * Most common command: curl -L <?php echo site_url().'/extendago-process-products'; ?> >/dev/null 2>&1
		<?php
	}


	/*
	=================================================
	    STATUS/DEBUG SECTION
	=================================================
    */
	public function display_storecontrl_sync_dates_element(){

        $storecontrl_init_sync = get_option('storecontrl_init_sync');
        $storecontrl_init_sync = ( isset($storecontrl_init_sync) && !empty($storecontrl_init_sync) )? $storecontrl_init_sync : '/';

        $process_product_changes = get_option('process_product_changes');
        $process_product_changes = ( isset($process_product_changes) && !empty($process_product_changes) )? $process_product_changes : '/';

        $process_stock_changes = get_option('process_stock_changes');
        $process_stock_changes = ( isset($process_stock_changes) && !empty($process_stock_changes) )? $process_stock_changes : '/';

        echo '<ul class="list-group">';
		echo '<li class="list-group-item"><span style="min-width: 255px; float: left;">Volledige synchronisatie</span>'.$storecontrl_init_sync.'</li>';
        echo '<li class="list-group-item"><span style="min-width: 255px; float: left;">Wijzigingen synchronisatie</span>'.$process_product_changes.'</li>';
        echo '<li class="list-group-item"><span style="min-width: 255px; float: left;">Voorraad synchronisatie</span>'.$process_stock_changes.'</li>';
		echo '</ul>';
	}

	public function display_storecontrl_batches_element() {
        $files = $this -> get_files_from_upload_dir('/storecontrl/imports');

        echo "<div style='max-height: 125px; overflow: scroll;'>";

        if( isset($files) && count($files) > 3 ){
            foreach($files as $file) {
                if (substr($file, -5) == '.json') {
                    ?> <a onclick="preventDefault();" href="#" class="batchFileLink" name="<?php echo $file; ?>" title="<?php echo $file; ?>"><?php echo $file; ?></a><br/> <?php
                }
            }
        }
        else{
            echo '<i>Er zijn momenteel geen openstaande batch bestanden die nog verwerkt moeten worden.</i>';
        }

        echo "</div><br/>";

        // Create a pre element that will contain the elements of files
        echo "<pre id='batch_container' style='display: none; max-height: 300px; overflow: scroll'></pre>";
    }

	public function display_storecontrl_debug_logs_element(){
        $files = $this -> get_files_from_upload_dir('/storecontrl/logs');

		$firstReadFile = '';
		$selectBox = "<select id='log_file_select'>";
		$count = 0;
		foreach($files as $file) {
			if($file[0] != '.') {
				$count++;
				if($count == 1) {
					$firstReadFile = $file;
				}
				$selectBox .= "<option value='" . $file . "'>" . str_replace(".txt", "", str_replace("logs_", "", $file)) . "</option>";
			}
		}
		$selectBox .= "</select>";

		// Display the form for selecting and downloading log files
		echo "<form method='post' action='class-storecontrl-wp-connection-admin.php'>" . $selectBox . "<button type='submit' class='button button-primary' id='btnDownloadLog' name='btnDownloadLog' value='" . $firstReadFile . "' style='margin-left: 10px;'>".__('Download Log File', 'storecontrl-wp-connection-plugin')."</button>";

		// Make some space
		echo "<br/><br/>";

		// Upload directory
		$upload = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		$directory = $upload_dir . '/storecontrl/logs';

		// NEW - Load newest product batches first
		$files = scandir($directory);
		$files = array_reverse($files);

		// Get first/newest log file
		$firstFile = $directory. '/' .$files[0];

		// Open file
		if( $file = fopen($firstFile, "r") ){

			$logs_array = array();
			while(!feof($file)) {
				$line = fgets($file);
				$col = explode(',',$line);

				foreach($col as $data) {
					$logs_array[] = "<li>". trim($data)."</li>";
				}
			}
			fclose($file);

			echo '<div id="log_field" style="overflow-y: auto; height:150px;background: #fff;border-radius: 5px;padding: 20px;"><ul style="margin: 0px;">';
			$logs_array = array_reverse($logs_array);
			foreach($logs_array as $log) {
				echo $log;
			}
			echo '</ul></div>';
		}
		else{
			$logging = new StoreContrl_WP_Connection_Logging();
			$logging->log_file_write( 'Product cronjob | Unable to open file' );
			
			die("Unable to open file!");
		}

	}

	/**
     * Get all the files from a certain path in the upload directory
	 */
	private function get_files_from_upload_dir($path) {
		// Specify the location of the batch files
		$basedir 		= wp_upload_dir();
		$directory 	    = $basedir['basedir'];
		$directory 	    = $directory . $path;

		// Read all files from the directory
		$files = scandir($directory, 1);

		// Return the files found
        return $files;
    }

	/*
	=================================================
	    IMPORT SECTION
	=================================================
    */
	public function display_storecontrl_update_images_element(){
		?>
        <label class="checkbox-inline">
            <input name="storecontrl_update_images" id="storecontrl_update_images" value="1" <?php checked( '1', get_option( 'storecontrl_update_images' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: Afbeeldingen worden bijgewerkt/verwerkt vanuit StoreContrl in Woocommerce. ( Aanbevolen )<br/>
            Nee: Er zullen geen afbeeldingen worden verwerkt in Woocommerce.
        </div>
		<?php
	}
	
	public function display_storecontrl_new_product_status_element(){
		?>
        <label class="checkbox-inline">
            <input name="storecontrl_new_product_status" id="storecontrl_new_product_status" value="1" <?php checked( '1', get_option( 'storecontrl_new_product_status' ) ); ?> data-toggle="toggle" data-on="Live" data-off="Draft" data-onstyle="success" type="checkbox"> <?php echo __('Live/Concept', 'storecontrl-wp-connection-plugin'); ?>
        </label>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>Betreft de status die een nieuwe product krijgt in Woocommerce.</div>
        <?php
	}

	public function display_storecontrl_no_images_product_status_element() {
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_no_images_product_status" id="storecontrl_no_images_product_status" value="1" <?php checked( '1', get_option( 'storecontrl_no_images_product_status' ) ); ?> data-toggle="toggle" data-on="Live" data-off="Draft" data-onstyle="success" type="checkbox"> <?php echo __('Live/Concept', 'storecontrl-wp-connection-plugin'); ?>
        </label>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>Betreft de status die een nieuwe product krijgt in Woocommerce waarbij er geen afbeelding is ingesteld binnnen StoreContrl.</div>
        <?php
    }

	public function display_storecontrl_excerpt_element(){
		?>
        <label class="checkbox-inline">
            <input name="storecontrl_excerpt" id="storecontrl_excerpt" value="1" <?php checked( '1', get_option( 'storecontrl_excerpt' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: De aanwezige omschrijving in StoreContrl wordt ook verwerkt, naast de verwerking in de content, als korte product omschrijving in Woocommerce.<br/>
            Nee: De korte product omschrijving in Woocommerce wordt niet gevuld. ( Aanbevolen )
        </div>
        <?php
	}

	public function display_storecontrl_hide_featured_image_from_gallery_element(){
		?>
        <label class="checkbox-inline">
            <input name="storecontrl_hide_featured_image_from_gallery" id="storecontrl_hide_featured_image_from_gallery" value="1" <?php checked( '1', get_option( 'storecontrl_hide_featured_image_from_gallery' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: De eerste afbeelding uit StoreContrl wordt niet toegevoegd aan de product gallery en alleen ingesteld als uitgelichte afbeelding. ( Aanbevolen )<br/>
            Nee: De eerste afbeelding uit StoreContrl wordt ook toegevoegd aan de product gallery.
        </div>
        <?php
	}

	public function display_storecontrl_update_categories_element(){
		?>
        <label class="checkbox-inline">
            <input name="storecontrl_update_categories" id="storecontrl_update_categories" value="1" <?php checked( '1', get_option( 'storecontrl_update_categories' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: De categorie structuur wordt automatisch aangemaakt/bijgewerkt vanuit StoreContrl zoals ingesteld per product.  ( Aanbevolen )<br/>
            Nee: De categorie structuur wordt niet verwerkt in Woocommerce.
        </div>
        <?php
	}

    public function display_storecontrl_keep_product_title(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_keep_product_title" id="storecontrl_keep_product_title" value="1" <?php checked( '1', get_option( 'storecontrl_keep_product_title' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: De product titel wordt niet bijgewerkt en kan handmatig worden gewijzigd in Woocommerce.<br/>
            Nee: De product titel wordt automatisch verwerkt vanuit StoreContrl. ( Aanbevolen )
        </div>
        <?php
    }

    public function display_storecontrl_keep_product_description(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_keep_product_description" id="storecontrl_keep_product_description" value="1" <?php checked( '1', get_option( 'storecontrl_keep_product_description' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: De product omschrijving wordt niet bijgewerkt en kan handmatig worden gewijzigd in Woocommerce.<br/>
            Nee: De product omschrijving wordt automatisch verwerkt vanuit StoreContrl. ( Aanbevolen )
        </div>
        <?php
    }

    public function display_storecontrl_keep_product_categories(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_keep_product_categories" id="storecontrl_keep_product_categories" value="1" <?php checked( '1', get_option( 'storecontrl_keep_product_categories' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: De product categorie structuur zal ongewijzigd blijven zodat deze aanpasbaar is binnen Woocommerce.<br/>
            Nee: De product categorie structuur wordt automatisch verwerkt vanuit StoreContrl. ( Aanbevolen )
        </div>
        <?php
    }

    public function display_storecontrl_keep_product_status(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_keep_product_status" id="storecontrl_keep_product_status" value="1" <?php checked( '1', get_option( 'storecontrl_keep_product_status' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: De product status zal ongewijzigd blijven ook bij producten met een concept status die voorraad krijgen zal de status concept blijven.<br/>
            Nee: De product status wordt automatisch bepaald vanuit StoreContrl. ( Aanbevolen )
        </div>
        <?php
    }

    public function display_storecontrl_keep_product_images(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_keep_product_images" id="storecontrl_keep_product_images" value="1" <?php checked( '1', get_option( 'storecontrl_keep_product_images' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info">
            Ja: De product afbeeldingen zullen ongewijzigd blijven en kunnen qua volgorde en extra afbeeldingen worden gewijzigd in Woocommerce.<br/>
            Nee: De product afbeeldingen worden automatisch bepaald vanuit StoreContrl. ( Aanbevolen )
        </div>
        <?php
    }

    public function display_storecontrl_barcode_to_sku_element(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_set_barcode_as_sku" id="storecontrl_set_barcode_as_sku" value="1" <?php checked( '1', get_option( 'storecontrl_set_barcode_as_sku' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('Zodra geactiveerd, de streepjescode van een variatie wordt ingesteld als de zichtbare SKU.', 'storecontrl-wp-connection-plugin'); ?></div>
        <?php
    }

    public function display_storecontrl_pro_settings_element(){
        ?>
            <h4>PRO instellingen</h4>
            <p>Onderstaande instellingen alleen gebruiken in zeer specifieke situaties. Neem hierover contact op met support@arture.nl mocht je hier vragen over hebben.</p>
        <?php
    }

    public function display_storecontrl_link_barcode_to_field_element(){

        $meta_key_map_array = array(
            'woo-add-gtin/woocommerce-gtin.php' => array('GTIN', 'hwp_var_gtin', 'naam')
        );

        $available_metakeys = array();

        foreach(get_plugins() as $key => $data){
            if (in_array($key, array_keys($meta_key_map_array))){
                $available_metakeys[] = $meta_key_map_array[$key];
            }
        }


        ?>
        <select multiple class="form-control" name="storecontrl_link_barcode_to_field[]" id="storecontrl_link_barcode_to_field" style="max-width: 50%">
            <?php
                $option = get_option( 'storecontrl_link_barcode_to_field' );

                foreach($available_metakeys as $key => $metakey_array){
                    if (!empty($option)) {
                        foreach ($option as $key => $option_value) {
                            if ($option_value == $metakey_array[1]) {
                                ?>
                                <option selected value="<?php echo $metakey_array[1]; ?>"><?php echo $metakey_array[0]; ?></option>
                                <?php
                            } else {
                                ?>
                                <option value="<?php echo $metakey_array[1]; ?>"><?php echo $metakey_array[0]; ?></option>
                                <?php
                            }
                        }
                    }
                    else{
                        ?>
                        <option value="<?php echo $metakey_array[1]; ?>"><?php echo $metakey_array[0]; ?></option>
                        <?php
                    }
                }
            ?>
        </select>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('Save the barcode in the above selected fields, hold down command on mac or ctrl on windows to select multiple fields', 'storecontrl-wp-connection-plugin'); ?></div>
        <?php
    }

    /* disabled for now */
    public function display_storecontrl_use_variation_alias_element(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_use_variation_alias" id="storecontrl_use_variation_alias" value="1" <?php checked( '1', get_option( 'storecontrl_use_variation_alias' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('When enabled; variation aliases will be used. Default no.', 'storecontrl-wp-connection-plugin'); ?></div>
        <?php
    }

	public function display_storecontrl_use_tags_element(){
		?>
        <label class="checkbox-inline">
            <input name="storecontrl_use_tags" id="storecontrl_use_tags" class="use_tags" value="1" <?php checked( '1', get_option( 'storecontrl_use_tags' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> Ja / Nee
        </label>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('When enabled; all product tags (existing in StoreContrl) given below will be used as maincategory.', 'storecontrl-wp-connection-plugin'); ?></div>
		<?php
	}

	public function display_storecontrl_tags_categories_element(){

		$tags = get_option('storecontrl_tags_categories');
		?>
        <input type='text' name='storecontrl_tags_categories' id='storecontrl_tags_categories' value='<?php echo $tags; ?>' style='min-width: 500px'/><br/>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('Comma seperated tags by StoreContrl tag name.', 'storecontrl-wp-connection-plugin'); ?></div>
        <hr>

		<?php
	}

	public function display_storecontrl_sale_category_element(){
		$options = get_option( 'storecontrl_sale_category' );
		?>
        <select name="storecontrl_sale_category[term_id]">
			<?php
			$product_categories = get_terms( array(
				'taxonomy' => 'product_cat',
				'hide_empty' => false,
			) );
			if( isset($product_categories) ){
				echo '<option>Select category (optional)</option>';
				foreach( $product_categories as $category ){
					echo '<option value="' . $category->term_id . '" ' . selected( $category->term_id, $options['term_id'] ) . '>' . $category->name . '</option>';
				}
			}
			?>
        </select>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>Indien een categorie is ingesteld zullen alle Sale producten vanuit StoreContrl hieraan gekoppeld worden.</div>
		<?php
	}

	public function display_storecontrl_custom_category_element(){
		$options = get_option( 'storecontrl_custom_category' );
		?>
        <select name="storecontrl_custom_category[term_id]">
			<?php
			$product_categories = get_terms( array(
				'taxonomy' => 'product_cat',
				'hide_empty' => false,
			) );
			if( isset($product_categories) ){
				echo '<option>Select category (optional)</option>';
				foreach( $product_categories as $category ){
					echo '<option value="' . $category->term_id . '" ' . selected( $category->term_id, $options['term_id'] ) . '>' . $category->name . '</option>';
				}
			}
			?>
        </select>
		<?php
	}

	public function display_storecontrl_custom_category_exludes_element(){
		?>
        <input type='text' name='storecontrl_custom_category_excludes' id='storecontrl_custom_category_excludes' value='<?php echo get_option('storecontrl_custom_category_excludes'); ?>' style='min-width: 500px'/><br/>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('Comma seperated main categories by StoreContrl name.', 'storecontrl-wp-connection-plugin'); ?></div>
		<?php
	}

	/*
	=================================================
	    WOOCOMMERCE SECTION
	=================================================
    */
	public function display_storecontrl_wc_customer_type_element(){

		$storecontrl_api_url = $this->functions->getStoreContrlAPIURI();
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
			$customer_types = $web_api->storecontrl_get_customer_types();
			if( !isset($customer_types) || empty($customer_types) || isset($customer_types['Message']) ){
				echo '<div class="alert alert-warning" role="alert">'. __( 'No StoreContrl customer types available!', 'storecontrl-wp-connection-plugin' ) . '</div>';
			}
			else{
				if( is_array($customer_types) ) {
					foreach ( $customer_types as $customer_type ):

						$value = get_option( "storecontrl_wc_customer_type" ); ?>
                        <div class="radio">
                            <label><input
                                        type="radio" <?php echo ( isset( $value ) && $value == $customer_type['customertype_id'] ) ? "checked" : ''; ?>
                                        value="<?php echo $customer_type['customertype_id']; ?>"
                                        name="storecontrl_wc_customer_type"><?php echo $customer_type['customertype_name']; ?>
                            </label>
                        </div>
					<?php endforeach;
				} ?>

                <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
					<?php echo __('Every order needs a StoreContrl customer type. The selected type will be used for sending orders to StoreContrl', 'storecontrl-wp-connection-plugin'); ?>
                </div>
				<?php
			}

		}
		else{
			echo '<div class="alert alert-warning" role="alert">'. __( 'Unable to activate this action. API settings are empty or wrong!', 'storecontrl-wp-connection-plugin' ) . '</div>';
		}
	}

	public function display_storecontrl_wc_shipping_methods_element(){

		global $woocommerce;

		if ( class_exists( 'WooCommerce' ) ) {
			$storecontrl_api_url        = get_option( 'storecontrl_api_url' );
			$storecontrl_api_images_url = get_option( 'storecontrl_api_images_url' );
			$storecontrl_api_key        = get_option( 'storecontrl_api_key' );
			$storecontrl_api_secret     = get_option( 'storecontrl_api_secret' );

			// Check if connection available
			if (
				isset( $storecontrl_api_url ) && ! empty( $storecontrl_api_url ) &&
				isset( $storecontrl_api_images_url ) && ! empty( $storecontrl_api_images_url ) &&
				isset( $storecontrl_api_key ) && ! empty( $storecontrl_api_key ) &&
				isset( $storecontrl_api_secret ) && ! empty( $storecontrl_api_secret )
			) {

				// Get woocommerce shipping methods
                $web_api          = new StoreContrl_Web_Api();
				$wc_shipping_methods = $woocommerce->shipping->load_shipping_methods();
				$shipping_methods = $web_api->storecontrl_get_shipping_methods();

				if ( ! isset( $wc_shipping_methods ) || empty( $wc_shipping_methods ) || isset($shipping_methods['Message']) ) {
					echo '<div class="alert alert-warning" role="alert">' . __( 'No Woocommerce shipping methods available!', 'storecontrl-wp-connection-plugin' ) . '</div>';
				} elseif ( ! isset( $shipping_methods ) || empty( $shipping_methods ) ) {
					echo '<div class="alert alert-warning" role="alert">' . __( 'No StoreContrl shipping methods available!', 'storecontrl-wp-connection-plugin' ) . '</div>';
				} else {
					?>
                    <table class="table-striped">
                        <thead>
                            <tr>
                                <th><?php echo __( 'Woocommerce shipping method', 'storecontrl-wp-connection-plugin' ); ?></th>
                                <th><?php echo __( 'StoreContrl shipping method', 'storecontrl-wp-connection-plugin' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
						<?php
						// Iterate Woocommerce shipping methods
						if ( is_array( $wc_shipping_methods ) ):
							foreach ( $wc_shipping_methods as $method_name => $wc_shipping_method ):

								$value = get_option( "storecontrl_wc_shipping_method_" . $method_name ); ?>
                                <tr>
                                    <td><?php echo $wc_shipping_method->method_title; ?></td>
                                    <td>
                                        <select class="storecontrl_wc_shipping_method" name="storecontrl_wc_shipping_method_<?php echo $method_name; ?>">
                                            <option value="null"><?php echo __( 'Select shipping method', 'storecontrl-wp-connection-plugin' ); ?></option>
											<?php
											// Iterate StoreContrl shipping methods
											if ( is_array( $shipping_methods ) ) {
												foreach ( $shipping_methods as $shipping_method ): ?>
                                                    <option value="<?php echo $shipping_method['shippingmethod_id']; ?>" <?php echo ( isset( $value ) && $value == $shipping_method['shippingmethod_id'] ) ? 'selected=selected' : ''; ?>><?php echo $shipping_method['shippingmethod_name']; ?></option>
												<?php endforeach;
											} ?>
                                        </select>
                                    </td>
                                </tr>
							<?php endforeach; ?>
                            <tr>
                                <td>Default / Bol.com / Zalando</td>
                                <td>
                                    <select class="storecontrl_wc_shipping_method" name="storecontrl_wc_shipping_method_default">
                                        <option value="null"><?php echo __( 'Select shipping method', 'storecontrl-wp-connection-plugin' ); ?></option>
                                        <?php
                                        $value = get_option( "storecontrl_wc_shipping_method_default" );
                                        // Iterate StoreContrl shipping methods
                                        if ( is_array( $shipping_methods ) ) {
                                            foreach ( $shipping_methods as $shipping_method ): ?>
                                                <option value="<?php echo $shipping_method['shippingmethod_id']; ?>" <?php echo ( isset( $value ) && $value == $shipping_method['shippingmethod_id'] ) ? 'selected=selected' : ''; ?>><?php echo $shipping_method['shippingmethod_name']; ?></option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </td>
                            </tr>
						<?php endif; ?>
                        </tbody>
                    </table>

                    <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
						<?php echo __( 'Every order needs a StoreContrl shipping method. Map all the existing Woocommerce shipping methods to an StoreContrl Woocommerce shipping method.', 'storecontrl-wp-connection-plugin' ); ?>
                    </div>
					<?php
				}

			} else {
				echo '<div class="alert alert-warning" role="alert">' . __( 'Unable to activate this action. API settings are empty or wrong!', 'storecontrl-wp-connection-plugin' ) . '</div>';
			}
		}
		else {
			echo '<div class="alert alert-warning" role="alert">' . __( 'Woocommerce not activated!', 'storecontrl-wp-connection-plugin' ) . '</div>';
		}
	}

	public function display_storecontrl_wc_payment_methods_element(){

		global $woocommerce;

		if ( class_exists( 'WooCommerce' ) ) {
            $storecontrl_api_url = $this->functions->getStoreContrlAPIURI();
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

                // Get woocommerce shipping methods
                $wc_payment_gateways = $woocommerce->payment_gateways->payment_gateways();

                $web_api = new StoreContrl_Web_Api();
                $payment_methods = $web_api->storecontrl_get_payment_methods();

                if( !isset($wc_payment_gateways) || empty($wc_payment_gateways) || isset($payment_methods['Message']) ){
                    echo '<div class="alert alert-warning" role="alert">'. __( 'No Woocommerce shipping methods available!', 'storecontrl-wp-connection-plugin' ) . '</div>';
                }
                elseif( !isset($payment_methods) || empty($payment_methods) ){
                    echo '<div class="alert alert-warning" role="alert">'. __( 'No StoreContrl shipping methods available!', 'storecontrl-wp-connection-plugin' ) . '</div>';
                }
                else{
                    ?>
                    <table class="table-striped">
                        <thead>
                        <tr>
                            <th><?php echo __( 'Woocommerce payment method', 'storecontrl-wp-connection-plugin' ); ?></th>
                            <th><?php echo __( 'StoreContrl payment method', 'storecontrl-wp-connection-plugin' ); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        // Iterate Woocommerce payment methods
                        if( is_array($wc_payment_gateways) ):
                            foreach( $wc_payment_gateways as $gateway_name => $wc_payment_gateway ):

	                            if( $wc_payment_gateway->enabled != 'yes' ) {
                                    continue;
	                            }

                                $value = get_option( "storecontrl_wc_payment_method_".$gateway_name ); ?>
                                <tr>
                                    <td><?php echo $wc_payment_gateway->title; ?></td>
                                    <td>
                                        <select class="storecontrl_wc_payment_method" name="storecontrl_wc_payment_method_<?php echo $gateway_name; ?>">
                                            <option value="null"><?php echo __( 'Select payment method', 'storecontrl-wp-connection-plugin' ); ?></option>
                                            <?php
                                            // Iterate StoreContrl payment methods
                                            if( is_array($payment_methods) ) {
                                                foreach ( $payment_methods as $payment_method ): ?>
                                                    <option value="<?php echo $payment_method['paymentmethod_id']; ?>" <?php echo ( isset( $value ) && $value == $payment_method['paymentmethod_id'] ) ? 'selected=selected' : ''; ?>><?php echo $payment_method['paymentmethod_name']; ?></option>
                                                <?php endforeach;
                                            } ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td>Default / Bol.com / Zalando</td>
                                <td>
                                    <select class="storecontrl_wc_payment_method" name="storecontrl_wc_payment_method_default">
                                        <option value="null"><?php echo __( 'Select payment method', 'storecontrl-wp-connection-plugin' ); ?></option>
                                        <?php
                                        $value = get_option( "storecontrl_wc_payment_method_default" );
                                        // Iterate StoreContrl shipping methods
                                        if( is_array($payment_methods) ) {
                                            foreach ( $payment_methods as $payment_method ): ?>
                                                <option value="<?php echo $payment_method['paymentmethod_id']; ?>" <?php echo ( isset( $value ) && $value == $payment_method['paymentmethod_id'] ) ? 'selected=selected' : ''; ?>><?php echo $payment_method['paymentmethod_name']; ?></option>
                                            <?php endforeach;
                                        } ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
                        <?php echo __('Every order needs a StoreContrl payment method. Map all the existing Woocommerce payment methods to an StoreContrl Woocommerce payment method.', 'storecontrl-wp-connection-plugin'); ?>
                    </div>
                    <?php
                }

            }
            else{
                echo '<div class="alert alert-warning" role="alert">'. __( 'Unable to activate this action. API settings are empty or wrong!', 'storecontrl-wp-connection-plugin' ) . '</div>';
            }
		}
		else {
			echo '<div class="alert alert-warning" role="alert">' . __( 'Woocommerce not activated!', 'storecontrl-wp-connection-plugin' ) . '</div>';
		}
	}

	public function display_storecontrl_wc_new_order_element(){

		global $woocommerce;

		if ( class_exists( 'WooCommerce' ) ) {
            // Get woocommerce shipping methods
            $wc_shipping_methods = $woocommerce->shipping->load_shipping_methods();

            $storecontrl_api_url = $this->functions->getStoreContrlAPIURI();
            $storecontrl_api_images_url = get_option('storecontrl_api_images_url');
            $storecontrl_api_key = get_option('storecontrl_api_key');
            $storecontrl_api_secret = get_option('storecontrl_api_secret');

            // Check if connection available
            if(
                isset($storecontrl_api_url) && !empty($storecontrl_api_url) &&
                isset($storecontrl_api_images_url) && !empty($storecontrl_api_images_url) &&
                isset($storecontrl_api_key) && !empty($storecontrl_api_key) &&
                isset($storecontrl_api_secret) && !empty($storecontrl_api_secret) &&
                isset($wc_shipping_methods) && !empty($wc_shipping_methods)
            ) {
                ?>
                <label class="checkbox-inline">
                    <input name="storecontrl_wc_new_order" value="1" <?php checked( '1', get_option( 'storecontrl_wc_new_order' ) ); ?> data-toggle="toggle" data-onstyle="success" type="checkbox"> <?php echo __( 'Enable/Disable', 'storecontrl-wp-connection-plugin' ); ?>
                </label>

                <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
                    <?php echo __('When enabled; new paid order(s) will automatically created in StoreContrl!', 'storecontrl-wp-connection-plugin'); ?>
                </div>
                <?php
            }
            else{
                echo '<div class="alert alert-warning" role="alert">'. __( 'Unable to activate this action. API settings are empty or wrong!', 'storecontrl-wp-connection-plugin' ) . '</div>';
            }
        }
        else {
            echo '<div class="alert alert-warning" role="alert">' . __( 'Woocommerce not activated!', 'storecontrl-wp-connection-plugin' ) . '</div>';
        }
	}

	public function display_storecontrl_wc_delete_order_element(){

		$storecontrl_api_url = $this->functions->getStoreContrlAPIURI();
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
			?>
            <label class="checkbox-inline">
                <input name="storecontrl_wc_delete_order" value="1" <?php checked( '1', get_option( 'storecontrl_wc_delete_order' ) ); ?> data-toggle="toggle" data-onstyle="success" type="checkbox"> <?php echo __('Enable/Disable', 'storecontrl-wp-connection-plugin'); ?>
            </label>

            <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('When enabled; deleting order(s) will automatically deleted in StoreContrl!', 'storecontrl-wp-connection-plugin'); ?></div>
			<?php
		}
		else{
			echo '<div class="alert alert-warning" role="alert">'. __( 'Unable to activate this action. API settings are empty or wrong!', 'storecontrl-wp-connection-plugin' ) . '</div>';
		}
	}

	public function display_storecontrl_wc_delete_product_element() { ?>

        <label class="checkbox-inline">
            <input name="storecontrl_wc_delete_product" value="1" <?php checked( '1', get_option( 'storecontrl_wc_delete_product' ) ); ?> data-toggle="toggle" data-onstyle="success" type="checkbox"> <?php echo __('Enable/Disable', 'storecontrl-wp-connection-plugin'); ?>
        </label>

        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('When enabled; deleted product(s) in StoreContrl will be put in the trash of WooCommerce!', 'storecontrl-wp-connection-plugin'); ?></div> <?php
    }

	public function display_storecontrl_process_color_element(){
		$storecontrl_process_color = get_option('storecontrl_process_color');
		?>

        <div class="radio">
            <label>
                <input type="radio" value="as_attribute" name="storecontrl_process_color" <?php echo ( isset($storecontrl_process_color) && $storecontrl_process_color == 'as_attribute')? 'checked=checked' : ''; ?>><?php echo __('Save value(s) as product attribute!', 'storecontrl-wp-connection-plugin'); ?>
            </label>
        </div>
        <div class="radio">
            <label>
                <input type="radio" value="as_category" name="storecontrl_process_color" <?php echo ( empty($storecontrl_process_color) || ( isset($storecontrl_process_color) && $storecontrl_process_color == 'as_category') )? 'checked=checked' : ''; ?>><?php echo __('Save value(s) as category! Be sure taxonomy "product_color" exist!', 'storecontrl-wp-connection-plugin'); ?>
            </label>
        </div>
		<?php
	}

	public function display_storecontrl_process_brand_element(){
		$storecontrl_process_brand = get_option('storecontrl_process_brand');
		?>

        <div class="radio">
            <label>
                <input type="radio" value="as_attribute" name="storecontrl_process_brand" <?php echo ( isset($storecontrl_process_brand) && $storecontrl_process_brand == 'as_attribute')? 'checked=checked' : ''; ?>><?php echo __('Save value(s) as product attribute!', 'storecontrl-wp-connection-plugin'); ?>
            </label>
        </div>
        <div class="radio">
            <label>
                <input type="radio" value="as_category" name="storecontrl_process_brand" <?php echo ( empty($storecontrl_process_brand) || ( isset($storecontrl_process_brand) && $storecontrl_process_brand == 'as_category') )? 'checked=checked' : ''; ?>><?php echo __('Save value(s) as category! Be sure taxonomy "product_brand" exist!', 'storecontrl-wp-connection-plugin'); ?>
            </label>
        </div>
		<?php
	}

	public function display_storecontrl_process_season_element(){
		$storecontrl_process_season = get_option('storecontrl_process_season');
		?>

        <div class="radio">
            <label>
                <input type="radio" value="as_attribute" name="storecontrl_process_season" <?php echo ( isset($storecontrl_process_season) && $storecontrl_process_season == 'as_attribute')? 'checked=checked' : ''; ?>>Seizoen opslaan als product eigenschap
            </label>
        </div>
        <div class="radio">
            <label>
                <input type="radio" value="as_category" name="storecontrl_process_season" <?php echo ( empty($storecontrl_process_season) || ( isset($storecontrl_process_season) && $storecontrl_process_season == 'as_category') )? 'checked=checked' : ''; ?>>Seizoen opslaan in de aanwezige taxonomy "product_season"
            </label>
        </div>
        <div class="radio">
            <label>
                <input type="radio" value="skip" name="storecontrl_process_season" <?php echo ( empty($storecontrl_process_season) || ( isset($storecontrl_process_season) && $storecontrl_process_season == 'skip') )? 'checked=checked' : ''; ?>>Seizoen niet verwerken
            </label>
        </div>
		<?php
	}

	public function display_storecontrl_process_supplier_element(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_process_supplier" value="1" <?php checked( '1', get_option( 'storecontrl_process_supplier' ) ); ?> data-toggle="toggle" data-onstyle="success" type="checkbox"> <?php echo __('Enable/Disable', 'storecontrl-wp-connection-plugin'); ?>
        </label>

        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('When enabled; the supplier will be saved as product attribute.', 'storecontrl-wp-connection-plugin'); ?></div>
	    <?php
    }

    public function display_storecontrl_process_supplier_code_element(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_process_supplier_code" value="1" <?php checked( '1', get_option( 'storecontrl_process_supplier_code' ) ); ?> data-toggle="toggle" data-onstyle="success" type="checkbox"> <?php echo __('Enable/Disable', 'storecontrl-wp-connection-plugin'); ?>
        </label>

        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('When enabled; the supplier_code will be saved as product attribute.', 'storecontrl-wp-connection-plugin'); ?></div>
        <?php
	}

    public function display_storecontrl_process_color_code_element(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_process_color_code" value="1" <?php checked( '1', get_option( 'storecontrl_process_color_code' ) ); ?> data-toggle="toggle" data-onstyle="success" type="checkbox"> <?php echo __('Enable/Disable', 'storecontrl-wp-connection-plugin'); ?>
        </label>

        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('When enabled; the color_code will be saved as product attribute.', 'storecontrl-wp-connection-plugin'); ?></div>
        <?php
    }

    public function display_storecontrl_process_sub_group_element(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_process_sub_group" value="1" <?php checked( '1', get_option( 'storecontrl_process_sub_group' ) ); ?> data-toggle="toggle" data-onstyle="success" type="checkbox"> <?php echo __('Enable/Disable', 'storecontrl-wp-connection-plugin'); ?>
        </label>

        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo __('When enabled; the sub_group will also ( default as product category ) be saved as product attribute.', 'storecontrl-wp-connection-plugin'); ?></div>
        <?php
    }

    /*
    =================================================
        ADD-ONS SECTION
    =================================================
    */
    public function display_storecontrl_creditcheques_element(){
        ?>
        <label class="checkbox-inline">
            <input name="storecontrl_creditcheques" id="storecontrl_creditcheques" value="1" <?php checked( '1', get_option( 'storecontrl_creditcheques' ) ); ?> data-toggle="toggle" data-on="Ja" data-off="Nee" data-onstyle="success" type="checkbox"> € 7,50 per maand
        </label>
        <div class="info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>Met de Spaarpunten Add-On voor Woocommerce haal je het maximale uit je webshop. Je klanten sparen automatisch bij iedere bestelling in de winkel of webshop. Via het Loyalty spaarprogramma van StoreContrl kan je eenvoudig de spaarpunten verzilveren. De unieke codes kan je genereren in je StoreContrl Cloud platform en versturen naar je klanten. Deze Add-On maakt het mogelijk voor klanten om deze direct op je website in te wisselen voor de ingestelde korting. Een unieke manier van eenvoud en klantenbinding.</div>
        <?php
    }

	public function downloadLogFile() {
		// Get information from the button
		$basedir 		= wp_upload_dir();
		$directory 	= $basedir['basedir'];
		$directory 	= $directory . '/storecontrl/logs';
		$file = sanitize_text_field($_POST['btnDownloadLog']);
		$file = $directory . "/" . $file;

		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.basename($file).'"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		readfile($file);
		exit;
	}

    public function resend_new_order_to_storecontrl() {
        if (isset($_POST['order_id'])) {
            $StoreContrlFunctions = new StoreContrl_Woocommerce_Functions();
            $response = $StoreContrlFunctions->create_storecontrl_new_order($_POST['order_id'], true);

            if (strlen(get_post_meta($_POST['order_id'], 'order_returned_successfully_to_storecontrl', true)) > 0) {
                //ORDER HAS BEEN SENT TO STORECONTRL
                if (get_post_meta($_POST['order_id'], 'order_returned_successfully_to_storecontrl', true) == '1') {
                    exit(json_encode(array("Status" => "Success")));
                }
                else{
                    exit(json_encode(
                        array(
                            "Status" => "Failed",
                            "Message" => $response
                        )
                    ));
                }
            }
        }
        exit(json_encode(array("Status" => "Failed")));
    }

    public function check_storecontrl_api_connection() {

        // Get data from resulting URL
        $request_url = '/Data/GetStores';
        $args = array(
            'content_type' => 'application/json',
            'has_sessionId' => false
        );

        $web_api = new StoreContrl_Web_Api();
        $results = $web_api->curl_request( $request_url, 'GET', $args );

        if( isset($results) && !empty($results) ){

            // Get the key from the post
            $provided_api_key = sanitize_text_field($_POST['key']);

            // Check for the presence of the key
            if (isset($provided_api_key) && !empty($provided_api_key) && (strlen($provided_api_key) == 12 || strlen($provided_api_key) == 17)) {
                // Create an inctance of the API functions
                $arture_api = new Arture_Web_Api();

                // Check whether the key is correct
                $key_check_result = $arture_api -> check_arture_api_key($provided_api_key);

                // Check whether we received a negative result
                $positive_result = $key_check_result === true;
            } else {
                $positive_result = false;
            }

            if($positive_result) {
                wp_send_json_success(
                    array(
                        'answer' => 'true',
                        'html' => '<div class="notice notice-success"><p>'. __( 'API settings are correct!', 'storecontrl-wp-connection-plugin' ) . '</p></div>'
                    )
                );
            } else {
                wp_send_json_success(
                    array(
                        'answer' => 'false',
                        'html' => '<div class="notice notice-error"><p>'. __( 'Arture key is incorrect!', 'storecontrl-wp-connection-plugin' ) . '</p></div>'
                    )
                );
            }


        }
        else{
            $dom = new DOMDocument();
            $dom->loadHTML($results);

            $xpath = new DOMXpath($dom);
            $result = $xpath->query('//h2');
            if ($result->length > 0) {
                $error = $result->item(0)->nodeValue;

                wp_send_json_success(
                    array(
                        'answer' => 'true',
                        'html' => '<div class="notice notice-error"><p>' .$error. '</p></div>'
                    )
                );
            }
            else{
                wp_send_json_success(
                    array(
                        'answer' => 'true',
                        'html' => '<div class="notice notice-error"><p>'. __( 'No connection available!', 'storecontrl-wp-connection-plugin' ). '</p></div>'
                    )
                );
            }
        }
    }

    public function show_marketing_banners()
    {
        $banner_showed = get_option('orderpickingapp1');
        if( (isset($banner_showed) && !empty($banner_showed)) || isset($_GET['skip']) ){
            update_option('orderpickingapp1', 'hide');
            return;
        }
        ?>
        <div class="arture_banner" style="background: #1b8fcc; border-radius: 5px; display: flex; width: 99%; color: #fff;">
            <div style="width: 30%; float: left;">
                <img style="max-width: 100%;" src="https://orderpickingapp.com/wp-content/uploads/2022/12/Logo-OPA-wit-e1671097850602.png"/>
            </div>
            <div style="width: 70%; float: right; padding: 20px;">
                <h2 style="color: #fff;">De nieuwe manier van orders verzamelen</h2>
                <ul class="cta-feature-list list-unstyled">
                    <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Maak geen onnodige dure fouten meer met verzamelen</li>
                    <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Creëer een efficiënte looproute door de winkel</li>
                    <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Bundel producten uit orders tijdens het verzamelen</li>
                    <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Barcodescanner, afbeelding en voorraadinformatie op je telefoon</li>
                    <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Android en Apple app</li>
                    <li class="d-flex align-items-center py-1"><span class="dashicons dashicons-yes"></span> Alle medewerkers kunnen helpen verzamelen</li>
                </ul>
                <p>Probeer de Orderpicking App nu 30 dagen vrijblijvend uit en ontdek de voordelen zelf.  Download de Woocommerce plugin in Wordpress en koppel de webshop aan ons portal en de app. Binnen 10 minuten start je met het besparen van veel tijd & kosten door onnodig verkeerd verzamelende en opgestuurde producten.</p>
                <a href="https://orderpickingapp.com/plans-and-pricing/" class="buttont button-primary" target="_blank">Meer informatie</a>
                <a href="?skip=orderpickingapp1" style="margin-left: 15px; color: #eee;">No interest</a>
                <br/>
                <i style="font-size: 12px; margin-top: 20px;">Een product van Arture B.V. | Trusted company</i>
            </div>
        </div>
        <?php
    }
}

<?php
class StoreContrl_WP_Connection_Functions {

    public function getStoreContrlAPIURI() {
        $storecontrl_api_uri = get_option('storecontrl_api_url');
        if (substr($storecontrl_api_uri, 0, 7) != "http://" && substr($storecontrl_api_uri, 0, 8) != "https://") {
            $storecontrl_api_uri = "https://" . $storecontrl_api_uri;
        }
        if (substr($storecontrl_api_uri, strlen($storecontrl_api_uri) - 7, 7) != '/WebApi') {
            $storecontrl_api_uri = rtrim($storecontrl_api_uri, "/") . "/WebApi";
        }
        return $storecontrl_api_uri;
    }

    function is_url_exist($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if($code == 200){
            $status = true;
        }else{
            $status = false;
        }
        curl_close($ch);
        return $status;
    }

    public function get_attachment_id_by_title( $org_title ){

        $title = str_replace('---', '-', $org_title);
        $title = str_replace('--', '-', $title);
        $title = str_replace('  ', '-', $title);
        $title = str_replace(' ', '-', $title);
        $title = str_replace('\'S', 'S', $title);
        $title = str_replace('&', '', $title);
        $title = str_replace('---', '-', $title);
        $title = str_replace('--', '-', $title);

        // Delete filenames started with a -
        if (strpos($title, '-') === 0) {
            $title = substr($title, 1);
        }

        $attachment = get_posts(
            array(
                'post_type'              => 'attachment',
                'title'                  => $title,
                'post_status'            => 'all',
                'numberposts'            => 1,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
            )
        );

        if ( isset($attachment[0]) ){
            $attach_id = $attachment[0]->ID;
        }
        else{
            return '';
        }

        return $attach_id;
    }

    public function custom_get_product_id_by_sku( $sku ) {

        $args = array(
            'post_type'     => 'product',
            'post_status'   => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' 		=> 'sc_product_id',
                    'value' 	=> $sku
                )
            ),
            'fields' => 'ids'
        );
        $product_query = new WP_Query($args);
        $product_ids = $product_query->posts;

        if( empty($product_ids) ){
            $args = array(
                'post_type' => 'product',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' 		=> '_sku',
                        'value' 	=> $sku
                    )
                ),
                'fields' => 'ids'
            );
            $product_query = new WP_Query($args);
            $product_ids = $product_query->posts;
        }

        // Remove duplicates based on SKU ( Unique identifier )
        if( count($product_ids) > 1 ){
            $logging = new StoreContrl_WP_Connection_Logging();
            $product_id = end($product_ids);

            $deleted_products = 1;
            foreach( $product_ids as  $duplicate_product_id ){
                if( $duplicate_product_id != $product_id && count($product_ids) > $deleted_products){
                    $logging->log_file_write( 'DEBUG | Duplicated product with sku: ' .$sku. ' is moved to trash.' );
                    wp_delete_post( $duplicate_product_id, false );
                    $deleted_products++;
                }
            }
        }
        elseif( isset($product_ids[0]) ){
            $product_id = $product_ids[0];
        }
        else{
            $product_id = 0;
        }

        return ( $product_id ) ? intval( $product_id ) : 0;
    }

	public function is_dir_empty($dir) {
		if (!is_readable($dir)) return NULL;
		$handle = opendir($dir);
		while (false !== ($entry = readdir($handle))) {
			if ( '.' != $entry && '..' != $entry && !is_dir( $dir.'/'.$entry ) ) {
				return FALSE;
			}
		}
		return TRUE;
	}

	public function custom_update_post_meta( $post_id, $meta_key, $meta_value )
    {

        $value = get_post_meta($post_id, $meta_key);
        if( is_array($value) && count($value) > 1) {

            global $wpdb;

            $duplicate_meta_entries = $wpdb->get_results($wpdb->prepare("
                   SELECT postmeta.meta_id
                   FROM $wpdb->postmeta AS postmeta
                   WHERE postmeta.post_id = '%s' AND postmeta.meta_key = '%s'
                   ORDER BY postmeta.meta_id DESC
                   LIMIT 100
                   OFFSET 1
               ", $post_id, $meta_key), 'ARRAY_A');

            $duplicate_meta_entries_list = array();
            foreach ($duplicate_meta_entries as $duplicate_meta_entry) {
                $duplicate_meta_entries_list[] = $duplicate_meta_entry['meta_id'];
            }

            $wpdb->get_results("
                 DELETE
                 FROM $wpdb->postmeta AS postmeta
                 WHERE postmeta.meta_id IN (" . implode(",", $duplicate_meta_entries_list) . ")
             ", 'ARRAY_A');
        }
        update_post_meta($post_id, $meta_key, $meta_value);
    }

	public function array_to_xml($array, SimpleXMLElement $xml, $child_name) {
		foreach ($array as $k => $v) {
			if(is_array($v)) {
				(is_int($k)) ? $this->array_to_xml($v, $xml->addChild($child_name), $v) : $this->array_to_xml($v, $xml->addChild(strtolower($k)), $child_name);
			} else {
				(is_int($k)) ? $xml->addChild($child_name, $v) : $xml->addChild(strtolower($k), $v);
			}
		}

		return $xml->asXML();
	}

	public function delete_orphaned_post_meta(){

		global $wpdb;

		$response = $wpdb->query(
			"
			DELETE pm FROM $wpdb->postmeta pm
			LEFT JOIN $wpdb->posts wp ON wp.ID = pm.post_id
			WHERE wp.ID IS NULL
			"
		);
		return $response;
	}
}

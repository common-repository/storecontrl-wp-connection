<?php
/*
=================================================
	StoreContrl Web API
	1. All available calls with params
=================================================
*/

class StoreContrl_Web_Api {

	private $storecontrl_api_key;
	private $storecontrl_api_secret;
	private $storecontrl_api_salt;
	private $storecontrl_api_signature;
	private $storecontrl_api_url;
	private $sessionid = '';
	private $functions;
	private $debug_email = '';

	public function __construct() {
	    $this->functions = new StoreContrl_WP_Connection_Functions();
		$this->storecontrl_api_key = get_option('storecontrl_api_key');
		$this->storecontrl_api_secret = get_option('storecontrl_api_secret');
		$this->storecontrl_api_url = $this->functions->getStoreContrlAPIURI();

        $this->storecontrl_api_url = str_replace('http://', 'https://', $this->storecontrl_api_url);

		// Check whether the url contains the right amount of characters
		if (strlen($this -> storecontrl_api_url) > 6) {
			// Check whether WebApi is included in the string
			if (substr($this -> storecontrl_api_url, strlen($this -> storecontrl_api_url) - 6) != "WebApi") {
				// Check whether the slash is present
				if(substr($this -> storecontrl_api_url, strlen($this -> storecontrl_api_url) - 1) != "/") {
					// Add it if it doesn't exist
					$this -> storecontrl_api_url = $this->storecontrl_api_url . "/";
				}

				// Add it if it doesn't exist
				$this -> storecontrl_api_url = $this -> storecontrl_api_url . "WebApi";
			}
		}

		if( isset($debug_email) && !empty($debug_email) ){
			$this->debug_email = $debug_email;
		}
		else{
			$this->debug_email = get_option( 'admin_email' );
		}

		$salt = mt_rand();
		$this->storecontrl_api_salt = $salt;
		$signature = hash_hmac('sha256', $salt, $this->storecontrl_api_secret, true);
		$encodedSignature = base64_encode($signature);
		$this->storecontrl_api_signature = urlencode($encodedSignature);
	}

	public function curl_request( $request_url, $method = 'GET', $args = array() ) {

        // Create an instance of the logging unit
        $logging = new StoreContrl_WP_Connection_Logging();

		// API full request url
		$api_url = $this->storecontrl_api_url.''.$request_url;

		// Define headers
		$headers = array();
        if( isset($args['content_type']) ){
            $headers[] = 'Content-Type: '.$args['content_type'];
        }
		$headers[] = "Apikey: ".$this->storecontrl_api_key;
		$headers[] = "Salt: ".$this->storecontrl_api_salt;
		$headers[] = "Signature: ".$this->storecontrl_api_signature;

		// Check for sessionid
		if( isset($this->sessionid) && !empty($this->sessionid) && isset($args['has_sessionId']) && !empty($args['has_sessionId']) ){
			$headers[] = "SessionId: ".$this->sessionid;
		}

		if( $method == 'POST' ){

			// Custom post_fields
			if( isset($args['post_fields']) && !empty($args['post_fields']) ){
				$post_fields = $args['post_fields'];
				$post_fields_amount = 1;
			}
			else{

				// Default post_fields
				$post_fields = 'Apikey='.$this->storecontrl_api_key.'&Salt='.$this->storecontrl_api_salt.'&Signature='.$this->storecontrl_api_signature;
				$post_fields_amount = 3;
			}

			// Start curl
			$curl = curl_init();

            $storecontrl_ssl_verification = get_option( 'storecontrl_ssl_verification' );
            if (isset($storecontrl_ssl_verification) && $storecontrl_ssl_verification) {
                curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');
            }

			curl_setopt( $curl, CURLOPT_URL, $api_url );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $curl, CURLOPT_HEADERFUNCTION,
				function($curl, $header) use(&$headers){
					$len    = strlen($header);
					$header = explode(':', $header, 2);
					if (count($header) < 2)
						return $len;

					$headers[strtolower(trim($header[0]))] = trim($header[1]);
					return $len;
				}
			);
			curl_setopt( $curl, CURLOPT_POST, $post_fields_amount);
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $post_fields);

			$curl_response = curl_exec($curl);

			if ($curl_response === false) {

                // Stop the function and return an error
                $logging -> log_file_write( curl_error($curl) );
			}

			// Clean up
			curl_close($curl);

			// Check for sessionid
			if( isset($headers['sessionid']) && !empty($headers['sessionid']) ){
				$this->sessionid = $headers['sessionid'];
			}
		}
        elseif( $method == 'PUT' ){

            // Custom post_fields
            if( isset($args['post_fields']) && !empty($args['post_fields']) ){
                $post_fields = $args['post_fields'];
                $post_fields_amount = 1;
            }
            else{

                // Default post_fields
                $post_fields = 'Apikey='.$this->storecontrl_api_key.'&Salt='.$this->storecontrl_api_salt.'&Signature='.$this->storecontrl_api_signature;
                $post_fields_amount = 3;
            }

            // Start curl
            $curl = curl_init();

            $storecontrl_ssl_verification = get_option( 'storecontrl_ssl_verification' );
            if (isset($storecontrl_ssl_verification) && $storecontrl_ssl_verification) {
                curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');
            }

            curl_setopt( $curl, CURLOPT_URL, $api_url );
            curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
            curl_setopt( $curl, CURLOPT_HEADERFUNCTION,
                function($curl, $header) use(&$headers){
                    $len    = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2)
                        return $len;

                    $headers[strtolower(trim($header[0]))] = trim($header[1]);
                    return $len;
                }
            );
            curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt( $curl, CURLOPT_POSTFIELDS, $post_fields);

            $curl_response = curl_exec($curl);

            if ($curl_response === false) {

                // Stop the function and return an error
                $logging -> log_file_write( curl_error($curl) );
            }

            // Clean up
            curl_close($curl);
        }
		else{

			// Start curl
			$curl = curl_init();

            $storecontrl_ssl_verification = get_option( 'storecontrl_ssl_verification' );
            if (isset($storecontrl_ssl_verification) && $storecontrl_ssl_verification) {
                curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');
            }

			curl_setopt( $curl, CURLOPT_URL, $api_url );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );

			curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
			$curl_response = curl_exec($curl);
			if ($curl_response === false) {

                // Stop the function and return an error
                $logging -> log_file_write( curl_error($curl) );
			}

			// Clean up
			curl_close($curl);
		}

		$output = json_decode($curl_response, true);

		// Check for JSON or array
		if( empty($output) && !is_array($output) ){
			$output = $curl_response;
		}
		// Log message if exist
		if( isset($output['Message']) ){

			// LOG
			$logging = new StoreContrl_WP_Connection_Logging();
            $logging->log_file_write( 'StoreContrl | Error url: ' .$api_url );
            $logging->log_file_write( 'StoreContrl | Error message: ' .$output['Message'] );
		}

		return $output;
	}

	/*
	=================================================
		Calls for synchronizing stock
		1. The call may be performed, once per minute.
	=================================================
	*/

	public function storecontrl_get_sku_stock_changes() {

		// Get data from resulting URL
		$request_url = '/Sku/GetSkuStockChanges';
		$args = array(
			'content_type' => 'application/json',
			'has_sessionId' => false
		);
		$results = $this->curl_request( $request_url, 'GET', $args );

		return  $results;
	}

	public function storecontrl_remove_sku_stock_changes( $processed_products = '') {

		// Get data from resulting URL
		$request_url = '/Sku/RemoveSkuStockChanges';
		$args = array(
			'content_type' => 'application/x-www-form-urlencoded',
			'has_sessionId' => false,
			'post_fields' 	=> $processed_products
		);
		$results = $this->curl_request( $request_url, 'POST', $args );

		return  $results;
	}


	/*
	=================================================
		[ Chapter 4 ] Calls needed for Catalog feed
		1. Max every 12 hours
	=================================================
	*/

	public function storecontrl_start_catalog_download() {

		// Get data from resulting URL
		$request_url = '/Data/StartCatalogDownload';
		$args = array(
			'content_type' => 'application/json',
			'has_sessionId' => false
		);
		$results = $this->curl_request( $request_url, 'POST', $args );

		return  $results;
	}

	public function storecontrl_get_catalog_products( $pageSize = '100', $lastSyncDate = '') {

		// Get data from resulting URL
		$request_url = '/Product/GetCatalog?pageSize='.$pageSize;

		if( isset($lastSyncDate) && !empty($lastSyncDate) ){
			$request_url .= '&lastSyncDate='.$lastSyncDate;
		}

		$args = array(
			'content_type' => 'application/json',
			'has_sessionId' => false
		);
		$results = $this->curl_request( $request_url, 'GET', $args );

		return  $results;
	}

	public function storecontrl_remove_processed_products( $processed_products = '') {

		// Post data to api url
		$request_url = '/Product/ProcessedProducts';
		$args = array(
			'content_type' => 'application/x-www-form-urlencoded',
			'has_sessionId' => false,
			'post_fields' 	=> $processed_products
		);
		$results = $this->curl_request( $request_url, 'POST', $args );

		return  $results;
	}

	public function storecontrl_new_order( $data ) {

		// Post data to api url
		$request_url = '/Order/NewOrder';
		$args = array(
			'content_type' => 'application/xml',
			'has_sessionId' => false,
			'post_fields' 	=> $data
		);
		$results = $this->curl_request( $request_url, 'POST', $args );

		return  $results;
	}

    public function storecontrl_cancel_order( $order_id ) {

        // Post data to api url
        $request_url = '/Order/CancelOrder/'.$order_id;
        $args = array(
            'content_type' => 'application/xml',
            'has_sessionId' => false,
            'post_fields' 	=> array(
                'order_id' => $order_id
            )
        );
        $results = $this->curl_request( $request_url, 'PUT', $args );

        return  $results;
    }

	/*
	=================================================
		Other Calls
		1. [GET] /Data/GetPaymentMethods
		2. [GET] /Data/GetCustomertypes
		3. [GET] /Data/GetVatRates
		4. [GET] /Data/GetShippingMethods
		5. [GET] /Data/GetStores
		6. [GET] /Discount/GetCreditChequeValue?code={chequecode}
		7. [GET] /Product/GetRemovedCatalogProducts
		8.
	=================================================
	*/

	public function storecontrl_get_payment_methods( ) {

		// Post data to api url
		$request_url = '/Data/GetPaymentMethods';
		$args = array(
			'content_type' => 'application/json',
			'has_sessionId' => false
		);
		$results = $this->curl_request( $request_url, 'GET', $args );

		return  $results;
	}

	public function storecontrl_get_customer_types( ) {

		// Post data to api url
		$request_url = '/Data/GetCustomertypes';
		$args = array(
			'content_type' => 'application/json',
			'has_sessionId' => false
		);
		$results = $this->curl_request( $request_url, 'GET', $args );

		return  $results;
	}

	public function storecontrl_get_shipping_methods( ) {

		// Post data to api url
		$request_url = '/Data/GetShippingMethods';
		$args = array(
			'content_type' => 'application/json',
			'has_sessionId' => false
		);
		$results = $this->curl_request( $request_url, 'GET', $args );

		return  $results;
	}

	public function storecontrl_get_stores( ) {

		// Post data to api url
		$request_url = '/Data/GetStores';
		$args = array(
			'content_type' => 'application/json',
			'has_sessionId' => false
		);
		$results = $this->curl_request( $request_url, 'GET', $args );

		return  $results;
	}
}

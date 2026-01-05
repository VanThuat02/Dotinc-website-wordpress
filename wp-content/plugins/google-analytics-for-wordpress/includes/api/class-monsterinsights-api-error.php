<?php

class MonsterInsights_API_Error extends WP_Error {
	public function __construct( $response_body ) {
		$error_data = $response_body['error'];

		$code = $error_data['code'];
		$message = $error_data['message'];
		$details = $error_data['details'];
		
		parent::__construct( $code, $message, $details );
	}
	
}
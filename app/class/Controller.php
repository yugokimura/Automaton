<?php

class Controller {

	protected $db;
	protected $request;
	protected $auth;
	protected $user;
	protected $csrf;
	protected $csrf_auth;
	protected $params;

	function __construct() {

		global $db;
		$this->db = $db;
		$this->request = $_REQUEST;
		$this->params = array();

		// API利用ユーザ認証	
		
	}
	
	function __destruct() {

	}


	public function encodeToken() {
		$tokenObject = new stdClass();
		$tokenObject->token = mt_rand(100000);
		$tokenObject->time  = time();
		$token = openssl_encrypt( json_encode($token), 'AES-128-ECB', CSRF_ENCRIPTION_KEY);		
	}

	public function decodeToken( $token ) {

		try {
			if( base64_decode($token, true) == false)
				throw new RuntimeException('token malformat', 400);

			$tokenObject = json_decode( openssl_decrypt( $token, 'AES-128-ECB', CSRF_ENCRIPTION_KEY) );

			if( is_null( $tokenObject ) ) 
				throw new RuntimeException('token malformat json', 400);

			if( !isset( $tokenObject->time ) )
				throw new RuntimeException('time param has not set', 400);

			if( !isset( $tokenObject->token ) )
				throw new RuntimeException('token param has not set', 400);

		} catch (RuntimeException $e ) {
			return false;
		}
	}


        public function assign($newName, $value) {
                $this->params[$newName] = $value;
        }

        public function renderView($template) {
                extract($this->params);
                include(TEMPLATE_DIR . $template);
        }

	public function renderObject() {
                $base = new stdClass();
		return $base;
	}

	public function renderJson($object) {
		header('Content-type: application/json');
		//$object->id = time();
		//$object->version = '1';
		print json_encode($object);
	}

	public function renderHtml($object) {
		header('Content-type: text/html');
		print $object->html;
	}

	public function renderJsonp($object, $callback) {
		header( 'Content-Type: text/javascript; charset=utf-8' );
		print $callback . "(" . json_encode($object) . ")";
	}

}

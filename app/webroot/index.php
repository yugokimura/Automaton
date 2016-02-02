<?php
require __DIR__ . '/../common.php';


if( !isset($_SERVER['REDIRECT_URL'] )){
	$_SERVER['REDIRECT_URL'] = null;
}

//print $_SERVER['REDIRECT_URL'];
//print_r($_REQUEST);
//exit;

// Select file from routes

try {
	if(isset( $conf->routes[ $_SERVER['REDIRECT_URL'] ] )) {


		if( !is_null($_SERVER['REDIRECT_URL']) && $route = $conf->routes[ $_SERVER['REDIRECT_URL'] ] ) {
			// include files
			call_user_func( array( class_factory($route->controller, array()) , $route->method) , $_REQUEST);
		} else {
			throw new Exception('URI doest not exist');
		}
	} else {
		throw new Exception('Contents does not exist');
	}

} catch( Exception $e ) {
	$object = new stdClass();
	$object->id = time();
	$object->version = API_VERSION;
	$object->is_error = true;
	$object->error = $e->getMessage();

	$app = new Controller();
	$app->renderJson($object);
}


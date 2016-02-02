<?php
// 0:　表示しない, -1:全て
error_reporting(-1);
ini_set('display_errors', 'On');

require __DIR__ . '/class/AutoLoader.php';
//require __DIR__ . '/../vendor/autoload.php';


$autoLoader = new AutoLoader();
$autoLoader->setIncludePath(__DIR__ . '/class');
$autoLoader->setIncludePath(__DIR__ . '/controller');
$autoLoader->setIncludePath(__DIR__ . '/controller/base');
$autoLoader->setIncludePath(__DIR__ . '/model');
$autoLoader->setIncludePath(__DIR__ . '/model/base');
$autoLoader->setIncludePath(__DIR__ . '/entity');
$autoLoader->setIncludePath(__DIR__ . '/entity/base');

// PARAM
define('VERSION', "1.0");

// ABSOLUTE DIR
define('CONFIG_ROUTE',	 __DIR__ . '/conf/route.xml');
define('CONFIG_DATABASE',__DIR__ . '/conf/database.xml');
define('BUILDER_ENTITY_PATH', __DIR__ . '/entity');
define('BUILDER_MODEL_PATH', __DIR__ . '/model');
define('BUILDER_CONTROLLER_PATH', __DIR__ . '/controller');
define('TEMPLATE_DIR', __DIR__ . '/view/');


// DIR
define('LOG_DIR', __DIR__ . '/log');



/**************************************************
 * Load Config
 **************************************************/

$env = new Enviroment();
$conf = new stdClass();
//apc_clear_cache(CONFIG_ROUTE);
//if( apc_exists(CONFIG_ROUTE) ) {
//	$conf->routes = apc_fetch(CONFIG_ROUTE);
//} else {
	$tmpRoutes = simplexml_load_file(CONFIG_ROUTE);
	$conf->routes = array();
	foreach( $tmpRoutes as $route ) {
	
		$routeObject = json_decode( json_encode($route) );

		// Controller名をキーに連想配列に格納
		$conf->routes[ $routeObject->path  ] =  $routeObject;

	}
	// キャッシュに√設定を保存
//	apc_store(CONFIG_ROUTE, $conf->routes);
//}

$db = array();
//apc_clear_cache(CONFIG_DATABASE);
//if( apc_exists(CONFIG_DATABASE) ) {
//	$db = apc_fetch(CONFIG_DATABASE);
//} else {
	$tmpDatabase = simplexml_load_file(CONFIG_DATABASE);
	$conf->database = array();
	foreach( $tmpDatabase as $database ) {
		if(isset($database['enviroment']) ) {
			if( $database['enviroment'] == $env->getEnviroment() ) {
				$dbObject = new stdClass();
				$dbObject->id = (string) $database->id;
				$dbObject->host = (string) $database->host;
				$dbObject->name = (string) $database->name;
				$dbObject->port = (int) $database->port;
				$dbObject->username = (string) $database->username;
				$dbObject->password = (string) $database->password;
				$db[ $dbObject->id ] = $dbObject;
			}
		}
	}
	$db = (object) $db;
//	apc_store(CONFIG_DATABASE, $db);
//}



/**************************************************
 * Log Factory
 **************************************************/
function log_factory($log, $logName = null) {
	if(is_null($logName))
		$logName = time();

	error_log(time() . "\t" . $log . "\n", 3, LOG_DIR . $logName . '.log');
}

/**************************************************
 * Class Factory
 **************************************************/
function class_factory($className) {
        if( func_num_args() <= 1 ) {
                return new $className;
        } else {
                $args = func_get_args();
                array_shift($args);
                $reflection = new ReflectionClass($className);
                return $reflection->newInstanceArgs($args);
        }
}

/**************************************************
 * PDO Factory
 **************************************************/
function pdo_factory($db, $params = array()) {
	try {
		$dsn = "mysql:host=" . $db->host . ";dbname=" . $db->name;
       		$pdo = new PDO($dsn, $db->username, $db->password, array(
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_STRINGIFY_FETCHES => false,
			PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET CHARACTER SET `utf8`'
		));

		return $pdo;
       	} catch (PDOException $e) {
        	exit('データベース接続失敗。'.$e->getMessage());
       	}

}

/**************************************************
 * Query Factory
 **************************************************/
function query_factory($query = '', $limitDepth = 100) {
	$result = new stdClass();
	$result->class = getObjectsClass($query, 0, $limitDepth);
	$result->array = getObjectsArray($query, 0, $limitDepth);
	return $result;
}

/**
 * クエリをクラス型で作成
 *
 **/
function query_class_factory($query = '', $limitDepth = 100) {
        return getObjectsClass($query, 0, $limitDepth);
}

function getObjectsClass($query, $currentDepth = 0, $limitDepth = 100) {
        if( !is_string($query) )
                return null;

        if(preg_match_all('/([0-9a-zA-Z_-]+)(\.(?:[0-9a-zA-Z_-]+)+\((?:(?:[^()]|(?R))*)\))*/', $query, $matches, PREG_SET_ORDER)){
                $currentDepth++;
                $objectsClass = new stdClass();
                foreach($matches as $objects){
                        //print $fields[1] . "\n";

			$objectsClass->{$objects[1]} = getAttributesClass($objects[0], $currentDepth, $limitDepth);
                }
                return $objectsClass;
        }
        return new stdClass(); // 何も無い場合は空クラスを返す
}
function getAttributesClass($query, $currentDepth = 0, $limitDepth = 100){
//        if(preg_match_all('/\.([0-9a-zA-Z_-]+)+\((([^()]|(?R))*)\)/', $query, $matches, PREG_SET_ORDER)) {
	if(preg_match_all('/\.([0-9a-zA-Z_-]+)+\((([^()]|(?R))*)\)/', $query, $matches, PREG_SET_ORDER)) {
                $currentDepth++;
                $attributesClass = new stdClass();
                foreach($matches as $attributes) {

                        switch($attributes[1]){
                                case 'in':
                                        $objectsClass = getObjectsClass($attributes[2], $currentDepth, $limitDepth);
                                        if( $objectsClass != null ){
						$attributesClass = (object) array_merge( (array) $attributesClass, (array) $objectsClass);
                                        }
                                        break;
                                default:
					$splits = preg_split("/[,]+/", $attributes[2], -1, PREG_SPLIT_NO_EMPTY);
					if($splits != null and count($splits) > 1) {
						$attributesClass->{$attributes[1]} = $splits;
					} else {
                                        	$attributesClass->{$attributes[1]} = $attributes[2];
					}
                                        break;
                        }
                }
                return $attributesClass;
        }
        return new stdClass();
}

/**
 * クエリを配列型で作成
 *
 **/
function query_array_factory($query = '', $limitDepth = 100) {
        return getObjectsArray($query, 0, $limitDepth);
}

function getObjectsArray($query, $currentDepth = 0, $limitDepth = 100) {
        if( !is_string($query) )
                return null;

        if(preg_match_all('/([0-9a-zA-Z_-]+)(\.(?:[0-9a-zA-Z_-]+)+\((?:(?:[^()]|(?R))*)\))*/', $query, $matches, PREG_SET_ORDER)){
                $currentDepth++;
                $objectArray = array();
                foreach($matches as $objects){
                        //print $fields[1] . "\n";

                        $object = new stdClass();    // フィールドオブジェクトを作成
                        $object->name = $objects[1]; // フィールド名を格納

                        $attributesArray = getAttributesArray($objects[0], $currentDepth, $limitDepth); // 属性配列を取得
                        if( $attributesArray != null ) {
                                $object->attributes = $attributesArray; // 属性が存在する場合は値を挿入
                        } else {
				$object->attributes = array(); // 属性が存在しない場合は空配列
			}
                        $objectArray[] = $object; //ベースオブジェクトの配列に格納
                }
                return $objectArray;
        }
        return array(); // 何も無い場合は空配列を返す
}

function getAttributesArray($query, $currentDepth = 0, $limitDepth = 100){
        if(preg_match_all('/\.([0-9a-zA-Z_-]+)+\((([^()]|(?R))*)\)/', $query, $matches, PREG_SET_ORDER)) {
                $currentDepth++;
                $attributesArray = array();
                foreach($matches as $attributes) {
                        //print $attributes[1] . "\n";

                        $attribute = new stdClass();
                        $attribute->name = $attributes[1];

                        switch($attributes[1]){
                                case 'in':
                                        $ObjectArray = getObjectsArray($attributes[2], $currentDepth, $limitDepth);
                                        if( $ObjectArray != null ){
                                                $attribute->value = $ObjectArray;
                                        }
                                        break;
                                default:
                                        $splits = preg_split("/[,]+/", $attributes[2], -1, PREG_SPLIT_NO_EMPTY);
                                        if($splits != null and count($splits) > 1 ) {
						$attribute->value = $splits;
					} else {
                                        	$attribute->value = $attributes[2];
					}
					break;
                        }

                        $attributesArray[] = $attribute;
                }
                return $attributesArray;
        }
        return array();
}

function randstr_factory($length = 8) {
    static $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJLKMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; ++$i) {
        $str .= $chars[mt_rand(0, 61)];
    }
    return $str;
}


function randint_factory($length = 8) {
    static $chars = '0123456789';
    $str = '';
    for ($i = 0; $i < $length; ++$i) {
	if($i == 0)
		$str .= $chars[mt_rand(1, 9)];
	else
		$str .= $chars[mt_rand(0,9)];
    }
    return intval($str);
}


function translateTo($lang) {


}

<?php
require_once __DIR__  . '/../common.php';

define('BASE_ENTITY_PATH', BUILDER_ENTITY_PATH . '/base/');
define('MY_ENTITY_PATH', BUILDER_ENTITY_PATH . '/');

define('BASE_MODEL_PATH', BUILDER_MODEL_PATH . '/base/');
define('MY_MODEL_PATH', BUILDER_MODEL_PATH . '/');

define('BASE_CONTROLLER_PATH', BUILDER_CONTROLLER_PATH . '/base/');
define('MY_CONTROLLER_PATH', BUILDER_CONTROLLER_PATH . '/');


$db_master = pdo_factory($db->slave, null);


$eb = new ControllerBuilder($db_master, $db->slave);
$eb->create();


class ControllerBuilder {

	private $pdo;
	private $db_info;

	function __construct($pdo = null, $db_info = null) {
		$this->pdo = $pdo;
		$this->db_info = $db_info;
	}

	function __destruct() {
		$this->pdo = null;
	}

	public function create() {
		
		$tableNameArray = $this->_getTables();
		if( !empty($tableNameArray) ) {
		
			$cols = $this->_getColumnStructure($tableNameArray);

			// controllerのbaseディレクトリ内のファイルを削除
         	       if(file_exists(BASE_CONTROLLER_PATH)) {
                	        $this->clearDirectory(BASE_CONTROLLER_PATH);
			} else {
                		if(!mkdir(BASE_CONTROLLER_PATH, 0764, true)) 
                        		die('Failed to create dir');
                	}

			// ファイル書き出し
			foreach($cols as $tableName => $obj) {
				// Entityの作成
				//$entityClass = $this->_createEntity($obj);

				//　Modelの作成
				//$modelClass  = $this->_createModel($obj);

				// Controllerの作成
				$this->_createController($obj);
			}

			$this->_createGetController($cols);
		}
	}

	private function _createGetController($objs) {		

		$tableNameArray = array_map(function($TableName) {
			$tablename = mb_strtolower($TableName);
$stringData = <<<EOL
					case '{$tablename}':
						\$response->{$tablename} = \$this->get{$TableName}(\$pdo_slave, \$field);
						break;
EOL;
			return $stringData;
		}, array_keys($objs));

		$tableNames = implode("\n", $tableNameArray);
		$lowerTableNameArray = array_map(function($TableName) {
			return mb_strtolower($TableName);
		}, array_keys($objs));

		////
		$getMethodArray = array();
		foreach($objs as $TableName => $tableObject ) {
			$tablename = mb_strtolower($TableName);
			$tableName = lcfirst($tableObject->table->camel);
			$createEntityArray = array();
			$createEntityArray = array_map(function($foreign) {
				$lcfirst= lcfirst($foreign->table->camel);
$string = <<<EOL
		\${$lcfirst}Entity = new {$foreign->table->camel}Entity();
EOL;
				return $string;
			}, $tableObject->foreign);

$createEntityArray[] = <<<EOL
        	\${$tableName}Entity = new {$tableObject->table->camel}Entity();
EOL;



		$createEntity = implode("\n", $createEntityArray);

$getMethodArray[] = <<<EOL
	public function get{$tableObject->table->camel}(\$pdo, \$field) {
	
		\$response = new stdClass();
		\$response->{$tablename} = new stdClass();
		\$response->is_error = false;

$createEntity
	
		return \$response;
	}
EOL;
		}

		$getMethods = implode("\n", $getMethodArray);

$baseClass = <<<EOL
<?php

class GetController extends Controller {

        function __construct() {
                parent::__construct();
        }

        function __destruct() {
                parent::__destruct();
        }

	public function index(\$queries) {
		\$this->execute(\$queries);
	}

	public function execute(\$queries) {

		\$response = \$this->createResponse();

		try {

		} catch (Exception \$e) {
			\$response->is_error = true;
			\$response->error = \$e->getMessage();
		}


	}

{$getMethods}

}
?>
EOL;

$myClass = <<<EOL
<?php

class MyGetController extends GetController {

        function __construct() {
                parent::__construct();
        }

        function __destruct() {
                parent::__destruct();
        }

	public function execute(\$queries) {
		parent::execute(\$queries);
	}
}
?>
EOL;

                $filePath = BASE_CONTROLLER_PATH . 'GetController.php';
                $this->_write($filePath, $baseClass);
                print $filePath . "\n";


                $filePath = MY_CONTROLLER_PATH . 'MyGetController.php';
                if( !file_exists($filePath) ) {
                	 $this->_write($filePath, $myClass);
                }
	}

        private function _createController($objs) {

$baseClass = <<<EOL
<?php


class {$objs->table->camel}Controller extends Controller {

        function __construct() {
                parent::__construct();
        }

        function __destruct() {
                parent::__destruct();
        }

	public function index(\$queries) {
		\$response = \$this->execute(\$queries);
	}

	public function execute(\$queries) {

		\$response = new stdClass();
		\$response->id = time();
		\$response->version = 1;
		\$response->is_error = false;

		try {

		} catch(Exception \$e) {
			\$response->is_error = true;
			\$response->error =\$e->getMessage();
		}

		return \$response;
	}
}
?>
EOL;

$myClass = <<<EOL
<?php

require_once __DIR__ . '/../common.php';

class My{$objs->table->camel}Controller extends {$objs->table->camel}Controller {

        function __construct() {
                parent::__construct();
        }

        function __destruct() {
                parent::__destruct();
        }
}
?>
EOL;


                $filePath = BASE_CONTROLLER_PATH . $objs->table->camel . 'Controller.php';
                $this->_write($filePath, $baseClass);
                print $filePath . "\n";


                $filePath = MY_CONTROLLER_PATH . 'My' . $objs->table->camel . 'Controller.php';
                if( !file_exists($filePath) ) {
                        $this->_write($filePath, $myClass);
                        print "\t" . $filePath . "\n";
                }


	}



	/**
	 *
	 */
	protected function _getRelation() {
	}

	/**
	 * DB内テーブル名一覧取得
	 *
	 * @return talbeNameArrray <Array>
	 * 	テーブル名の配列を返却
	 */
	private function _getTables() {
                $sth = $this->pdo->prepare('show tables');
                $sth->execute();

                $tableNameArray = array();
                while($record = $sth->fetch(PDO::FETCH_ASSOC) ) {
                        $tableNameArray[] = $record['Tables_in_' . $this->db_info->name];
                }
		return $tableNameArray;
	}

	private function _getCreateTables($tableNameArray = array()) {

		$tableHash = array();
		// テーブル名でループ
	        foreach($tableNameArray as $tableName) {
			
        	        $sql = 'show create table :tableName';
                        $sth = $this->pdo->prepare('show create table ' . $tableName);
                        $result = $sth->execute();

			$TableNameAndSqlHash = array();
                        while($record = $sth->fetch(PDO::FETCH_ASSOC) ) {
				// Table名をキーに、テーブル作成時のSQLを値として格納

				$TableNameAndSqlHash[ $record['Table'] ] = $record['Create Table'];
                        }
                }
	}

	/**
	 * 指定テーブルの構造を
	 */
	private function _getColumnStructure($tableNameArray = array()) {

		$tables = array();
		 foreach($tableNameArray as $tableName) {

			$sth = $this->pdo->prepare('show create table ' . $tableName);
			$result = $sth->execute();
			$foreignArray = array();
			while($table = $sth->fetch(PDO::FETCH_ASSOC) ) {
				if( preg_match_all('/FOREIGN\sKEY\s\(`(.*)`\)\sREFERENCES\s`(.*)`\s\(`(.*)`\)\s/i', $table['Create Table'], $matches, PREG_SET_ORDER) ) {
					foreach($matches as $match) {
						$foreign = new stdClass();		
						$foreign->key       = $match[1];
						$foreign->table     = new stdClass();
						$foreign->table->normal = $match[2];
						$foreign->table->camel  = $this->_convertSnakeToCamel($match[2]);
						$foreign->talbe_key = $match[3];
						$foreignArray[] = $foreign;
					}
				}
			}

			$sth = $this->pdo->prepare('show columns from ' . $tableName);
			$result = $sth->execute();

			$obj = new stdClass();
			$obj->table = new stdClass();
			$obj->table->snake = $tableName;
			$obj->table->camel = $this->_convertSnakeToCamel($tableName);
			$obj->data = array();   //カラム詳細
			$obj->cols = new stdClass();
			$obj->cols->snake = array();
			$obj->cols->camel = array();
			$obj->primaries  = new stdClass(); //プライマリーキーの配列
			$obj->primaries->snake = array();
			$obj->primaries->camel = array();
			$obj->primaries->data = array();
			$obj->foreign = $foreignArray;
			while($record =  $sth->fetch(PDO::FETCH_ASSOC) ) {

				$col = new stdClass();
				$col->snake      = $record['Field'];
				$col->camel      = $this->_convertSnakeToCamel($record['Field']);
				$col->lower      = mb_strtolower( $record['Field'] );
				$col->lcfirst    = lcfirst($record['Field']);
				$col->model      = $col->camel . 'Model';
				$col->entity     = $col->camel . 'Entity';
				$col->controller = $col->camel . 'Controller';

				$col->primary    = ($record['Key'] == 'PRI')? true : false;
				$col->null       = ($record['Null'] == 'NO')? false : true;
				$col->default    = $record['Default'];
				$col->extra      = $record['Extra'];
				$col->Type       = $record['Type'];
				$col->dataType   = "";
				$col->dataLength = "";
				if( preg_match_all('/(.*?)\((.*)\)/i', $col->Type, $matches, PREG_PATTERN_ORDER) ) {
					$col->dataType = $matches[1][0];
					$col->dataLength = $matches[2][0];
				}
				$obj->cols->snake[] = $col->snake;
				$obj->cols->camel[] = $col->camel;


				if( $col->primary ) {
					$obj->primaries->snake[] = $col->snake;
					$obj->primaries->camel[] = $col->camel;
					$obj->primaries->data[]  = $col;
				}
				$obj->data[] = $col;
 				
			}
			$tables[$tableName] = $obj;
		}
		return $tables;
	}

        private function _getPDOStatement($type) {
        
                $PDO_PARAM = 'PDO::PARAM_STR';
                switch($type){
                        case 'int' :
                        case 'bigint' :
                                $PDO_PARAM = 'PDO::PARAM_INT';
                                break;
                        case 'float' :
                        case 'double' :
                        case 'char' :
                        case 'varchar' :
                        case 'text' :
                        case 'datetime' :
                        case 'timestamp' :
                                $PDO_PARAM = 'PDO::PARAM_STR';
                                break;
                        default :
                }
                return $PDO_PARAM;
        }

        private function _write($path, $string) {
                file_put_contents($path, $string);
                //chmod($path, 0664);
        }


        private function clearDirectory($dir) {
                if (is_dir($dir)) {
                        $objects = scandir($dir);
                        foreach ($objects as $object) {
                                if ($object != "." && $object != "..") {
                                        unlink($dir."/".$object);
                                //      if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
                                }
                        }
                        //reset($objects);
                        //rmdir($dir);
                }
        }


	/**
	 * SnakeコードをCamelコードに変換
	 **/
	private function _convertSnakeToCamel($snakeCase) {
		$camelCase = '';
		$SplitColumnArray = explode('_',  $snakeCase);
                foreach($SplitColumnArray as $part) {
			$camelCase .= ucfirst($part);
		}
		return $camelCase;
	}
}



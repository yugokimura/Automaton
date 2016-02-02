<?php
require_once __DIR__  . '/../common.php';

define('BASE_ENTITY_PATH', BUILDER_ENTITY_PATH . '/base/');
define('MY_ENTITY_PATH', BUILDER_ENTITY_PATH . '/');

$db_master = pdo_factory($db->slave, null);


$eb = new EntityBuilder($db_master, $db->slave);
$eb->create();


class EntityBuilder {

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

			// entityのbaseディレクトリ内のファイルを削除
         	       if(file_exists(BASE_ENTITY_PATH)) {
                	        $this->clearDirectory(BASE_ENTITY_PATH);
			} else {
                		if(!mkdir(BASE_ENTITY_PATH, 0764, true)) 
                        		die('Failed to create dir');
                	}

			// ファイル書き出し
			foreach($cols as $tableName => $obj) {
				// Entityの作成
				$entityClass = $this->_createEntity($obj);
			}
		}
	}

	/**
	 * Entityの作成
	 *
	 **/
	private function _createEntity($objs) {

		$entity = new stdClass();
		$entity->members = array();
		$entity->getters = array();
		$entity->setters = array();

		###### FOREACH
		foreach($objs->data as $obj) {
$entity->members[] = <<<EOL
	protected \$$obj->snake;
EOL;
$entity->getters[] = <<<EOL
        public function get$obj->camel() {
                return \$this->$obj->snake;
        }

EOL;
$entity->setters[] = <<<EOL
       public function set$obj->camel(\$$obj->snake) {
                \$this->$obj->snake = \$$obj->snake;
        }

EOL;
		}
		###### END FOREACH

		// 文字列に変更
		$members = implode("\n", $entity->members);
		$getters = implode("\n", $entity->getters);
		$setters = implode("\n", $entity->setters);
		$columns = implode("\", \"", $objs->cols->snake);

		###### クラス作成
$baseClass = <<<EOL
<?php

class  {$objs->table->camel}Entity extends Entity {

	function __construct() {
		parent::__construct();
	}

	function __destruct() {
		parent::__destruct();
	}

	////////////////  Members
	protected \$columns = array("$columns");
$members

	/////////////// Getters
        public function getColumns() {
                return \$this->columns;
        }

$getters

	/////////////// Setters
       public function setColumns(\$columns) {
                \$this->columns = \$columns;
        }

$setters	

}
?>
EOL;

$myClass = <<<EOL
<?php

class My{$objs->table->camel}Entity extends {$objs->table->camel}Entity {

        function __construct() {
                parent::__construct();
        }

        function __destruct() {
                parent::__destruct();
        }
}
?>
EOL;

		$filePath = BASE_ENTITY_PATH . $objs->table->camel . 'Entity.php';
		$this->_write($filePath, $baseClass);
		print $filePath . "\n";

	
		$filePath = MY_ENTITY_PATH . 'My' . $objs->table->camel . 'Entity.php';
		if( !file_exists($filePath) ) {
			$this->_write($filePath, $myClass);
			print "\t" . $filePath . "\n";
		}
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
			// テーブル名をキーにテーブルのカラム情報ハッシュを格納
			$this->_getTableStructure( $TableNameAndSqlHash);
                }
	}

	/**
	 * 指定テーブルの構造を
	 */
	private function _getColumnStructure($tableNameArray = array()) {

		$tables = array();
		 foreach($tableNameArray as $tableName) {

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

			while($record =  $sth->fetch(PDO::FETCH_ASSOC) ) {

				$col = new stdClass();
				$col->snake = $record['Field'];
				$col->camel = $this->_convertSnakeToCamel($record['Field']);
				$col->primary = ($record['Key'] == 'PRI')? true : false;
				$col->null = ($record['Null'] == 'NO')? false : true;
				$col->default = $record['Default'];
				$col->extra = $record['Extra'];
				$col->Type = $record['Type'];
				$col->dataType = "";
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
					$obj->primaries->data[] = $col;
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



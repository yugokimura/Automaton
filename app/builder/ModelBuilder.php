<?php
require_once __DIR__  . '/../common.php';

define('BASE_MODEL_PATH', BUILDER_MODEL_PATH . '/base/');
define('MY_MODEL_PATH', BUILDER_MODEL_PATH . '/');



$db_master = pdo_factory($db->slave, null);
$eb = new ModelBuilder($db_master, $db->slave);
$eb->create();


class ModelBuilder {

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


                       if(file_exists(BASE_MODEL_PATH)) {
                                $this->clearDirectory(BASE_MODEL_PATH);
                        } else {
                                if(!mkdir(BASE_MODEL_PATH, 0764, true))
                                        die('Failed to create dir');
                        }

			// ファイル書き出し
			foreach($cols as $tableName => $obj) {

				//　Modelの作成
				$modelClass  = $this->_createModel($obj);
			}
		}
	}

	/**
	 * Modelの作成
	 **/	
	private function _createModel($objs) {

		$model = new stdClass();

		$updateColArray  = array_map(function($col) {
			return $col . ' = :' . $col;
		}, array_diff($objs->cols->snake, $objs->primaries->snake));

		$bindArray = array_map(function($data) {
			return '$sth->bindValue(\'' . ':' . $data->snake . "',\t\t\$entityQuery->get". $data->camel . "(),\t" . $this->_getPDOStatement($data->dataType) . ');';
		}, $objs->data);

                $bindPrimaryArray = array_map(function($data) {
                        return '$sth->bindValue(\'' . ':' . $data->snake . "',\t\t\$entityQuery->get". $data->camel . "(),\t" . $this->_getPDOStatement($data->dataType) . ');';
                }, $objs->primaries->data);

                $wherePrimaryArray = array_map(function($data) {
                        return $data->snake . ' = :' . $data->snake;
                }, $objs->primaries->data);

		$flexColumArray = array_map(function($data) {
			$columnArray = array();
			if( !$data->primary ) {
				$columnArray[] = 'if( $entityQuery->get' . $data->camel . '() !== null )';
				$columnArray[] = "\t" . '$columns[] = "' . $data->snake . '";' . "\n";
			} else {
				$columnArray[] = '$columns[] = "' . $data->snake . '";';
			}
			return implode("\n\t\t", $columnArray);
		}, $objs->data);

		$flexBindArray = array_map(function($data) {
                        $bindArray   = array();
			if( !$data->primary ) {
				$bindArray[] = 'if( $entityQuery->get' . $data->camel . '() !== null )';
				$bindArray[] = "\t" . '$sth->bindValue(\'' . ':' . $data->snake . "',\t\t\$entityQuery->get". $data->camel . "(),\t" . $this->_getPDOStatement($data->dataType) . ');' . "\n";
			} else {
				$bindArray[] = '$sth->bindValue(\'' . ':' . $data->snake . "',\t\t\$entityQuery->get". $data->camel . "(),\t" . $this->_getPDOStatement($data->dataType) . ');';
			}
			return implode("\n\t\t" , $bindArray);
		}, $objs->data);

                $insertCols   = implode(', ' , $objs->cols->snake);
                $insertValues = ':' . implode(', :', $objs->cols->snake);
                $updateCols   = implode(', ' , $updateColArray);
                $binds        = implode("\n\t\t", $bindArray);
                $bindsPrimary  = implode("\n\t\t", $bindPrimaryArray);
                $wheresPrimary = implode(" and ", $wherePrimaryArray);
		$flexColums = implode("\n\t\t", $flexColumArray);
		$flexBinds = implode("\n\t\t", $flexBindArray);

$model->insert = <<<EOL
        public function insert(\$entityQuery, \$entityOut = null) {

                \$columns = array();
                $flexColums
		\$column      = implode(', ' , \$columns);
		\$placeholder =  ':' . implode(', :', \$columns);

                \$sth = \$this->pdo->prepare('insert into ' . \$this->table . ' (' . \$column . ') values (' . \$placeholder . ')');
		$flexBinds

                \$sth->execute();
                return \$this->pdo->lastInsertId();
        }
EOL;
$model->insertBy = <<<EOL
	public function insertBy(\$entityQuery, \$entityOut = null) {
		\$sth = \$this->pdo->prepare('insert into ' . \$this->table . ' ($insertCols) values ($insertValues)');
		$binds

		\$sth->execute();
		return \$this->pdo->lastInsertId();
	}
EOL;

		if(isset($objs->primaries->camel)) {
		##### Primary処理

$model->update = <<<EOL
        public function update(\$entityQuery, \$entityOut = null) {

                \$columns = array();
                $flexColums
                \$updateArray = array_map(function(\$column) {
                        return \$column . ' = :' . \$column;
                }, \$columns);

                \$updates = implode(', ', \$updateArray);

                \$sth = \$this->pdo->prepare('update ' . \$this->table . ' set ' . \$updates . ' where $wheresPrimary');
                $flexBinds

                return \$sth->execute();
        }
EOL;
$model->updateByPrimary = <<<EOL
        public function updateByPrimary(\$entityQuery, \$entityOut = null) {
                \$sth = \$this->pdo->prepare('update ' . \$this->table . ' set $updateCols  where $wheresPrimary');
		$binds

		return \$sth->execute();
        }
EOL;
$model->replaceInto = <<<EOL
	public function replaceInto(\$entityQuery, \$entityOut = null) {
		\$sth = \$this->pdo->prepare('replace into ' . \$this->table . ' ($insertCols) values ($insertValues)');
		$binds

		return \$sth->execute();
	}
EOL;
$model->findByPrimary = <<<EOL
	public function findByPrimary(\$entityQuery, \$entityOut = null) {
		\$sth = \$this->pdo->prepare('select * from ' . \$this->table . ' where $wheresPrimary ' . \$this->_getOffset());
		$bindsPrimary

		\$sth->execute();

		if(!is_null(\$entityOut))
			\$entity = \$entityOut;

		return \$sth->fetchAll(PDO::FETCH_CLASS, \$entity);
	}
EOL;
$model->deleteByPrimary = <<<EOL
        public function deleteByPrimary(\$entityQuery, \$entityOut = null) {
                \$sth = \$this->pdo->prepare('delete from ' . \$this->table . ' where $wheresPrimary');
                $bindsPrimary

                return \$sth->execute();
        }
EOL;

$mode->delete = <<<EOL
	public function delete(\$entityQuery, \$entityOut = null) {
		return \$this->deleteByPrimary(\$entityQuery, \$entityOut);
	}
EOL;
		##### END Primary処理
		}

                ###### クラス作成
$baseClass = <<<EOL
<?php

class  {$objs->table->camel}Model extends Model {

        function __construct( \$pdo = null ) {
                parent::__construct( \$pdo );
        }

        function __destruct() {
                parent::__destruct();
        }

$model->insert

$model->update

$model->insertBy

$model->updateByPrimary

$model->replaceInto

$model->deleteByPrimary 

$model->delete
}
?>
EOL;


$myClass = <<<EOL
<?php

class  My{$objs->table->camel}Model extends {$objs->table->camel}Model {
	
        function __construct( \$pdo = null ) {
                parent::__construct( \$pdo );
        }

        function __destruct() {
                parent::__destruct();
        }

}
?>
EOL;

                $filePath = BASE_MODEL_PATH . $objs->table->camel . 'Model.php';
                $this->_write($filePath, $baseClass);
                print $filePath . "\n";


                $filePath = MY_MODEL_PATH . 'My' . $objs->table->camel . 'Model.php';
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



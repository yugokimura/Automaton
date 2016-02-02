<?php

require_once __DIR__ . '/../common.php';

class TestController extends Controller{


	function __construct() {
		parent::__construct();
	}

	function __destruct() {
		parent::__destruct();
	}

	function checkProcess() {

		try {
			$pdo_master = pdo_factory( $this->db->master );
			$model = new Model( $pdo_master );
			$model->setTableName('test');

			print_r( $Model->findAll('stdClass') ); // 結果を取得し表示
		} catch (Exception $e) {
			print_r($e);
		}
	}

}

$testController = new TestController();
$testController->checkProcess(); // Processテーブルを確認

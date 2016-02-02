<?php

require_once __DIR__ . '/../common.php';

class SampleController extends Controller {



        function __construct(){
                parent::__construct();
        }

	function __destruct() {
		parent::__destruct();
	}

	public function index( $query ) {
	
		$this->assign('title', 'サンプルタイトル');
		$this->assign('header', 'サンプルボディー');
		$this->renderView('sample_index.tpl');
	}
}

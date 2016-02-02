<?php

class Model {

	protected $pdo;
	protected $table;
	protected $entity;

	protected $limit;
	protected $page;
	protected $match;
	protected $cond;	
	protected $conditions = array();
	protected $binds = array();

	function __construct($pdo) {
		$this->pdo = $pdo;
                $this->table = preg_replace('/Model/', '', get_class($this));
                $this->entity = $this->table . 'Entity';
		$this->page = 1;
	}

	function __destruct() {
		$this->pdo = null;
	}

	protected function _getOffset(){
		if(isset($this->limit))
			return ' limit ' . ($this->page * $this->limit - $this->limit) . ', ' . $this->limit;
		else
			return;
	}

	public function findAll($entity = null) {

		$sth = $this->pdo->prepare('select * from ' . $this->table . $this->_getOffset());
		$sth->execute();

                if(!is_null($entity))
                        $this->entity = $entity;

		return $sth->fetchAll(PDO::FETCH_CLASS, $this->entity);
	}

	public function findBy($field, $value, $placeholder = PDO::PARAM_STR, $entity = null) {

		$conditions[] = " $field = :$field ";
		$binds[] = array(
			'field' => ":$field",
			"value" => $value,
			"placeholder" => $placeholder
		);
 
                $sth = $this->pdo->prepare('select * from ' . $this->table . " where $field = :$field " . $this->_getOffset());
		$sth->bindValue(":$field", $value, $placeholder);
		
                $sth->execute();

                if(!is_null($entity))
                        $this->entity = $entity;

                return $sth->fetchAll(PDO::FETCH_CLASS, $this->entity);

	}

	public function findFilter($fields, $entity = null ) {
		$sth = $this->pdo->prepare('select :fields  from ' . $this->table . " " . $this->_getOffset());
		$sth->bindValue(':fields', $fields);

                $sth->execute();

                if(!is_null($entity))
                        $this->entity = $entity;

                return $sth->fetchAll(PDO::FETCH_CLASS, $this->entity);
	}

	public function setTableName($table) {
		$this->table = $table;
	}

	public function getTableName() {
		return $this->table;
	}
	
	public function count() {
		$sth = $this->pdo->prepare('select count(*) from ' . $this->table);
		$sth->execute();

		return $sth->fetch(PDO::FETCH_ASSOC);
	}

	public function query($query, $values = array(), $entity = null) {
		$sth = $this->pdo->prepare($query);
		$sth->execute($values);

                if(!is_null($entity))
                        $this->entity = $entity;

		return $sth->fetchAll(PDO::FETCH_CLASS, $this->entity);
	}

        public function getId($stub = 'a') {

		if($this->table != 'Id') return false;

                $sth = $this->pdo->prepare('replace into ' . $this->table . ' (stub) values (:stub)');

                $sth->bindValue(':stub', $stub, PDO::PARAM_STR);

                $sth->execute();

                return $this->pdo->lastInsertId();
        }

	public function limit($limit) {
		$this->limit = $limit;
		return $this;
	}

	public function page($page) {
		$this->page = $page;
		return $this;
	}

	public function cond($cond) {
		$this->cond = $cond;
		return $this;
	}

	public function where() {
		$this->conditions[] = 'where';
	}

	public function _and(){
		$this->conditions[] = 'and';
	}

	public function _or() {
		$this->conditions[] = 'or';
	}

	public function eq($field, $value) {
		$this->conditions[] = "$field = :$field";
		$this->binds[] = array(
			'field' => ":$field",
			'value' => $value,
			'placeholder' => $placeholder
		);
		return $this;
	}

	public function match($field, $cond) {
		$this->match = ' where ' . $field . ' = \'' . $cond  . '\'';
		return $this;
	}
}


?>

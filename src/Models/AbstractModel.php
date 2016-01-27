<?php

namespace app\Models;

use app\Core\DB;

abstract class AbstractModel{
	protected $table;
	protected $primary = 'id';
	protected $columns = [];
	protected $sqlSelect = "select {select} {from} {join} {where} {groupBy} {having} {orderBy} {start} {length}";
	protected $sqlInsert = "insert into {insert} ({columns}) values ({values})";
	protected $sqlUpdate = "update {update} set {columns} {where}";

	private $paramCount = 0;
	private $paramsToBind = [];
	private $pdo;

	public function __construct(){
		if (empty($this->table))
			throw new InvalidConfigurationException("The property \$table must be assigned.");
		if(empty($this->columns))
			throw new InvalidConfigurationException("The property \$columns must be assigned.");
	}

	public function select(Array $columns = null){
		if(empty($columns)){
			$this->sqlSelect = str_replace('{select}', ' * ', $this->sqlSelect);
		} else {
			$this->sqlSelect = str_replace('{select}', implode(',', $columns), $this->sqlSelect);
		}

		return $this;
	}

	public function from($from = null){
		$this->sqlSelect = str_replace('{from}',' from '.(is_null($from)?$this->table:$from),$this->sqlSelect);
		return $this;
	}

	public function join($table, $on, Array $params = null){
		if(strpos($this->on, '?') !== false && !is_null($params)){
			$occurrences = substr_count($on, '?');
			if(count($params) != $occurrences)
				throw new \InvalidArgumentException("The number of parameters in \$on doesn't match the number of parameters in \$params");
			for($i = 0; $i < $occurrences; $i++){
					$this->paramsToBind[] = [':'.++$this->paramCount,$param[$i],$this->pdoType(gettype($param))];
			}
		}

		$this->sqlSelect = str_replace('{join}', $table.' on '.$on.' {join} ', $this->sqlSelect);

		return $this;
	}

	public function where($where, $param = null){
		if(strpos($this->sqlSelect,' where ') !== false){
			$where .= ' and ';
		}
		if(strpos($where,'?') !== false && is_null($param)){
			throw new \InvalidArgumentException("When a ? is specified in \$where clause, you should set a \$param too.");
		}
		else if(strpos($where,'?') !== false){
			$this->paramsToBind[] = [':'.++$this->paramCount,$param,$this->pdoType(gettype($param))];
		}
		$this->sqlSelect = str_replace('{where}', $where.' {where} ', $this->sqlSelect);

		return $this;
	}

	public function whereOr($where, $param = null){
		$where .= ' or ';
		if(strpos($where,'?') !== false && is_null($param)){
			throw new \InvalidArgumentException("When a ? is specified in \$where clause, you should set a \$param too.");
		}
		else if(strpos($where,'?') !== false){
			$this->paramsToBind[] = [':'.++$this->paramCount,$param,$this->pdoType(gettype($param))];
		}
		$this->sqlSelect = str_replace('{where}', $where.' {where} ', $this->sqlSelect);

		return $this;
	}

	public function groupBy($groupBy){
		$this->sqlSelect = str_replace('{groupBy}', $groupBy, $this->sqlSelect);
		return $this;
	}

	public function having($having, $param = null){
		if(strpos($having,'?') !== false && is_null($param)){
			throw new \InvalidArgumentException("When a ? is specified in \$having clause, you should set a \$param too.");
		}
		else if(strpos($having,'?') !== false){
			$this->paramsToBind[] = [':having',$param,$this->pdoType(gettype($param))];
		}
		$this->sqlSelect = str_replace('{having}', $having, $this->sqlSelect);

		return $this;
	}

	public function orderBy($orderBy){
		$this->sqlSelect = str_replace('{orderBy}', $orderBy, $this->sqlSelect);
		return $this;
	}

	public function offset($offset){
		$this->sqlSelect = str_replace('{start}', ' limit '.$offset.', ', $this->sqlSelect);
		return $this;
	}

	public function take($length){
		if(strpos($this->sqlSelect,' limit ') === false){
			$length .= ' limit '.$length;
		}
		$this->sqlSelect = str_replace('{length}', $length, $this->sqlSelect);
		return $this;
	}

	public function exec(){
		$this->prepareQuery();
		$this->pdo = DB::getInstance();
		$stmt = $this->pdo->prepare($this->sqlSelect);
		foreach($this->paramsToBind as $paramToBind){
			$stmt->bindParam($paramToBind[0],$paramToBind[1],$paramToBind[2]);
		}

		$stmt->execute();
		$resultSet = $stmt->fetchAll(\PDO::FETCH_OBJ);
		return $resultSet;
	}

	public function all(){
		$this->pdo = DB::getInstance();
		$stmt = $this->pdo->prepare("select * from $this->table");
		$stmt->execute();
		$resultSet = $stmt->fetchAll(\PDO::FETCH_OBJ);
		return $resultSet;
	}

	public function toSql(){
		$this->prepareQuery();
		return $this->sqlSelect;
	}

	public function find($id){
		$stmt = $this->pdo->prepare("select ".implode(',',$this->columns)." from $table where $primary = :primary limit 1");
		$stmt->bindParam(':primary',$id, $this->pdoType(gettype($id)));
		$stmt->execute();
		return $stmt->fetch(\PDO::FETCH_OBJ);
	}

	private function pdoType($phpType){
		switch($phpType){
			case "integer": return \PDO::PARAM_INT;
			case "boolean": return \PDO::PARAM_BOOL;
			case "double": return \PDO::PARAM_STR;
			case "string": return \PDO::PARAM_STR;
			case "NULL": return \PDO::PARAM_NULL;
		}
	}

	private function prepareQuery(){
		$this->sqlSelect = str_ireplace(['{select}', '{from}', '{join}', '{where}', '{groupBy}', '{having}', '{orderBy}' ,'{start}','{length}'],'',$this->sqlSelect);
	}
}

class InvalidConfigurationException extends \Exception{}
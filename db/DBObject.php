<?php
namespace chetch\db;

use \PDO as PDO;

class DBObject{
	protected static $dbh = null; //PDO object
	
	//to be set in initialised override ... every child class must have this declared
	protected static $config = array();
	
	const READ_MISSING_VALUES_ONLY = 1;
	const READ_ALL_VALUES = 2;
	
	private $rowdata;
	private $rowdataOriginal;
	private $id;
	
	public static function getConfig($key, $defaultVal = null){
		$class = static::class;
		if(!self::hasConfig())self::$config[$class] = array();
		
		return isset(self::$config[$class][$key]) ? self::$config[$class][$key] : $defaultVal;
	}
	
	public static function setConfig($key, $val){
		$class = static::class;
		if(!self::hasConfig())self::$config[$class] = array();
		
		self::$config[$class][$key] = $val;
	}
	
	public static function hasConfig(){
		$class = static::class;
		return isset(self::$config[$class]);
	}
	
	public static function dumpConfig(){
		var_dump(self::$config);
	}
	
	public static function setConnection($dbh){
		
		if(empty($dbh))throw new Exception("DBOject::setConnection No database supplied");
		
		self::$dbh = $dbh;
	}
	
	private static function init(){
		if(self::hasConfig())return;
		
		if(empty(self::$dbh))throw new Exception("DBOject::init No database supplied");
		
		static::initialise();
		
		//derived classes have to provide a table name in their initialise method
		if(empty(self::getConfig('TABLE_NAME')))throw new Exception("No table name in config");
		
		//from the table name we can get columns
		$t = self::getConfig('TABLE_NAME');
		$sql = "select column_name from information_schema.columns where table_name = '$t'";
		$q = self::$dbh->query($sql);
		$columns = array();
		while($row = $q->fetch()){
			$columns[] = $row['column_name'];
		}
		self::setConfig('TABLE_COLUMNS', $columns);
		
		//default SELECT_SQL if none is provided
		if(!self::getConfig('SELECT_SQL')){
			$t = self::getConfig('TABLE_NAME');
			self::setConfig('SELECT_SQL', "SELECT * FROM $t");
		}

		//run some validation checks on the SELECT_SQL
		if(stripos(self::getConfig('SELECT_SQL'), "SELECT") === false)throw new Exception("SELECT SQL ".self::getConfig('SELECT_SQL')." does not contain SELECT keyword");
		$keywords = array('WHERE','ORDER BY','GROUP BY');
		foreach($keywords as $kw){
			if(stripos(self::getConfig('SELECT_SQL'), $kw) !== false)throw new Exception("$kw is not allowed in SELECT SQL");
		}
		
		//now the SELECT SQL by ID, create default if none is provided
		if(empty(self::getConfig('SELECT_ROW_BY_ID_SQL'))){
			self::setConfig('SELECT_ROW_BY_ID_SQL',  self::getConfig('SELECT_SQL')." WHERE id=:id");
		}
		self::setConfig('SELECT_ROW_BY_ID_STATEMENT', self::$dbh->prepare(self::getConfig('SELECT_ROW_BY_ID_SQL')));
		
		//SELECT_ROW_FILTER is a way to select a row other than by id, not always necessary
		if(!empty(self::getConfig('SELECT_ROW_FILTER'))){
			$sql = self::createSelectSQL(self::getConfig('SELECT_SQL'), self::getConfig('SELECT_ROW_FILTER'), null);
			self::setConfig('SELECT_ROW_STATEMENT', self::$dbh->prepare($sql));
			self::setConfig('SELECT_ROW_PARAMS', self::extractBoundParameters($sql));
		}

		//default delete is normally enough
		if(empty(self::getConfig('DELETE_ROW_BY_ID_STATEMENT'))){
			$sql = "DELETE FROM $t WHERE id=:id LIMIT 1";
			self::setConfig('DELETE_ROW_BY_ID_STATEMENT', self::$dbh->prepare($sql));
		}
		
	}
	
	protected static function initialise(){
		throw new Exception("Child classes of DBObject must overwite DBObject::initialise method");
	}	
	
	public static function createInstance($rowdata = null, $readFromDB = self::READ_MISSING_VALUES_ONLY, $requireExistence = false){
		self::init();
		
		$inst = new static($rowdata);
		
		if($readFromDB){
			$inst->read($requireExistence);
			
			if($readFromDB == self::READ_MISSING_VALUES_ONLY && $rowdata){
				foreach($rowdata as $k=>$v){
					if($k == 'id')continue;
					$inst->set($k, $v);
				}
			}
		}
		
		return $inst;
	}
	
	public static function createInstanceFromID($id, $readFromDB = self::READ_MISSING_VALUES_ONLY, $requireExistence = true){
		$inst = static::createInstance(array('id'=>$id), $readFromDB, $requireExistence);
		return $inst;
	}
	
	public static function createCollection($params = null, $filter = null, $sort = null, $limit = null){
		self::init();
		
		$stmt = null;
		if($filter == null && $sort == null){
			if(empty(self::getConfig('SELECT_ROWS_STATEMENT')))throw new Exception("No SELECT ROWS statement set");
			$stmt = self::getConfig('SELECT_ROWS_STATEMENT');
		} else {
			$select = self::getConfig('SELECT_SQL');
			if(!$select)throw new Exception("DBObject::createCollection no SELECT_SQL present in config");
			$sql = self::createSelectSQL($select, $filter, $sort, $limit);
			
			if($params){
				//we make parameters passed commensurate with parameters listed in SQL (if possible)
				$bparams = self::extractBoundParameters($sql);
				$keys = array_keys($params);
				$p = array();
				for($i = 0; $i < count($bparams); $i++){
					$k = $bparams[$i];
					if(!in_array($k, $keys))throw new Exception("DBObject::createCollection $k is a bound paramater but is not specified in 'params'");
					$p[$k] = $params[$k];
				}
				$params = $p;
				
				//now we deal with array values
				foreach($params as $param=>$value){
					if(is_array($value)){
						unset($params[$param]);
						$replaceWith = "";
						for($i = 0; $i < count($value); $i++){
							$newParam = $param.$i;
							$replaceWith .= ($replaceWith ? "," : "").':'.$newParam;
							$params[$newParam] = $value[$i];	
						}
						$sql = str_replace(':'.$param, $replaceWith, $sql);
					}
				}
			}
			
			$stmt = self::$dbh->prepare($sql);
			
		}
		if(empty($stmt))throw new Exception("No statement for collection query");
		
		try{
			$stmt->execute($params);
		} catch (PDOException $e){
			throw $e;
		}
		$instances = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			$instances[] = self::createInstance($row, false);
		}
		return $instances;
	}
	
	public static function isValidFieldName($fieldName){
		return preg_match('/^[A-Za-z0-9_]+$/', $fieldName);
	}
	
	//Takes the SQL for a prepared statement and extracts the names of the parameters to be bound
	public static function extractBoundParameters($sql){
		$ar = explode(':', $sql.' ');
		$params = array();
		if(count($ar) <= 1)return $params;
		
		$endChars = array(" ", ",", ")");
		foreach ($ar as $ps){
			$endPos = false;
			for($i = 0; $i < count($endChars); $i++){
				$pos = strpos($ps, $endChars[$i]);
				if($pos !== false){
					$endPos = $endPos === false ? $pos : min($pos, $endPos);
				}
			}
			if($endPos !== false){
				$param = substr($ps, 0, $endPos);
				if(strpos($sql, ':'.$param) !== false){
					array_push($params, $param);
				}
			}
		}	
		return $params;
	}

	//take a list of fields/parameter names and check the validity
	//then and add ':' to prepare for binding for a statement and return both fields and bound parameters
	//used for generating SQL for prepared statements.
	private static function parseFieldList($fieldList, $asArray){
		if(is_string($fieldList))$fieldList = explode(',', $fieldList);
		$fieldStr = "";
		$delimiter = "-";
		foreach($fieldList as $fieldName){
			$fn = trim($fieldName);
			if(!self::isValidFieldName($fn))throw new Exception("$fn is not a valid field name");
			$fieldStr.= ($fieldStr ? "," : "").$delimiter.$fn;
		}
		
		$fieldList = str_replace($delimiter, '', $fieldStr);
		$paramNames = str_replace($delimiter, ':', $fieldStr);
		
		
		if($asArray){
			$fieldList = explode(',', $fieldList);
			$paramNames = explode(',', $paramNames);
		}
		
		return array('fields'=>$fieldList, 'params'=>$paramNames);
	}
	
	public static function createSelectSQL($select, $filter, $sort = null, $limit = null){
		
		$keywords = array('WHERE','ORDER BY','GROUP BY');
		foreach($keywords as $kw){
			if(stripos($select, $kw) !== false)throw new Exception("DBObject::createSelectSQL $kw is not allowed in select");
		}
		
		$sql2return = trim($select);
		if($filter)$sql2return.= " WHERE $filter";
		if($sort)$sql2return.= " ORDER BY $sort";
		if($limit)$sql2return.= " LIMIT $limit";
		
		return $sql2return;
	}
	
	public static function createInsertSQL($tableName, $fieldList){
		if(empty(self::$dbh))throw new Exception("Cannot create statement if database has not been set");
		$fl = self::parseFieldList($fieldList, false);
		$sql = "INSERT INTO $tableName (".$fl['fields'].") VALUES (".$fl['params'].")";
		return $sql;
	}
	
	public function createInsertStatement($fieldList){
		$sql = self::createInsertSQL(self::getConfig('TABLE_NAME'), $fieldList);
		return self::$dbh->prepare($sql);
	}
	
	public static function createUpdateSQL($tableName, $fieldList, $filter){
		if(empty(self::$dbh))throw new Exception("Cannot create statement if database has not been set");
		
		$fl = self::parseFieldList($fieldList, true);
		$sql = "";
		for($i = 0; $i < count($fl['fields']); $i++){
			$f = $fl['fields'][$i];
			$p = $fl['params'][$i];
			$sql.= ($sql ? "," : "")."$f=$p"; 
		}
		$sql = "UPDATE $tableName SET $sql WHERE $filter";
		return $sql;
	}
	
	public static function now(){
		if(empty(self::$dbh))throw new Exception("Database has not been set");
		$stmt = self::$dbh->query('SELECT NOW()');
		$row = $stmt->fetch();
		return $row[0];
	}
	
	public static function setUTC(){
		if(empty(self::$dbh))throw new Exception("Database has not been set");
		self::$dbh->query('SET time_zone = "+00:00"'); //UTC for all
	}
	
	public static function tz(){
		if(empty(self::$dbh))throw new Exception("Database has not been set");
		$sql = "SELECT @@session.time_zone";
		$stmt = self::$dbh->query($sql);
		$row = $stmt->fetch();
		$tz = $row[0];
		if(strtoupper($tz) == 'SYSTEM')$tz = date_default_timezone_get();
		if($tz == "+00:00")$tz = "UTC";
		return $tz;
	}
	
	public static function tzoffset(){
		if(empty(self::$dbh))throw new Exception("Database has not been set");
		$sql = "SELECT CONCAT(IF(NOW()>=UTC_TIMESTAMP,'+','-'),TIME_FORMAT(TIMEDIFF(NOW(),UTC_TIMESTAMP),'%H%m'))";
		$stmt = self::$dbh->query($sql);
		$row = $stmt->fetch();
		return $row[0];
	}
	
	
	/*
	 * Insatance methods
	 */
	
	public function __construct($rowdata = null){
		$this->rowdata = $rowdata;
		if($rowdata && isset($rowdata['id'])){
			$this->setID($rowdata['id']);
		}
	}
	
	public function setID($id){
		$this->set('id', $id);
		$this->bindR2V($this->id, 'id');
	}
	public function getID(){
		return $this->id;
	}
	
	public function set($field, $val){
		$this->setRowData(array($field=>$val));
	}
	
	public function get($field){
		return isset($this->rowdata[$field]) ? $this->rowdata[$field] : null;
	}
	
	public function setRowData($rd){
		if(empty($this->rowdata))$this->rowdata = array();
		foreach($rd as $k=>$v){
			$this->rowdata[$k] = $v;
			
		}
	}
	
	public function getRowData(){
		return $this->rowdata;
	}
	
	public function clearRowData($newRowData = null){
		$this->rowdata = array();
		$this->id = null;
		
		if($newRowData)$this->setRowData($newRowData);
	}
	
	//TODO: this doesn't need to be instance method
	protected function isEqual($fieldName, $value, $ar1, $ar2){
		if(gettype($ar1) != "array" || gettype($ar2) != "array")throw new Exception("DBObject::isEqual comparison must occur between arrays");
		
		$v1 = $ar1[$fieldName];
		$v2 = $ar2[$fieldName];
		$t1 = gettype($ar1[$fieldName]);
		$t2 = gettype($ar2[$fieldName]); 
		if($t1 == "object" || $t2 == "object")throw new Exception("DBObject::isEqual Cannot test for equality with objects");
		if($t1 == "array" || $t2 == "array"){
			if($t1 != $t2)throw new Exception("DBObject::isEqual if comparing arrays, both values have to be arrays.");
			if(count($v1) != count($v2))return false;
			if(count(array_intersect(array_keys($v1), array_keys($v2))) != count(array_keys($v1)))return false;
			
			foreach($v1 as $k=>$v){
				if(!isset($v2[$k]))
				if(!$this->isEqual($k, $v, $v1, $v2))return false;
			}
			return true;
		} else {
			return $ar1[$fieldName] == $ar2[$fieldName];
		}
		
	}
	
	public function isDirty(){
		if(!isset($this->rowdata))return false;
		if(!isset($this->rowdataOriginal))return true;
		
		foreach($this->rowdata as $k=>$v){
			if(!$this->isEqual($k, $v, $this->rowdata, $this->rowdataOriginal))return true;
		}	
		return false;
	}
	
	public function createUpdateStatement($fieldList){
		$filter = self::getConfig('TABLE_NAME').".id=".$this->id;
		$sql = self::createUpdateSQL(self::getConfig('TABLE_NAME'), $fieldList, $filter);
		return self::$dbh->prepare($sql);
	}
	
	public function read($requireExistence = false){
		$stmt = null;
		$params = null;
			
		if(!empty($this->id)){
			$stmt = self::getConfig('SELECT_ROW_BY_ID_STATEMENT');
			$params = array('id');
		} elseif(isset($this->rowdata) && self::getConfig('SELECT_ROW_STATEMENT')){
			$stmt = self::getConfig('SELECT_ROW_STATEMENT');
			$params = self::getConfig('SELECT_ROW_PARAMS');
		}
		if(empty($stmt)){
			return; //fail silently
		}
		try{ 
			$vals = array();
			foreach($params as $param){
				if(isset($this->rowdata[$param]))$vals[$param] = $this->rowdata[$param]; 
			}
			$stmt->execute($vals);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if($row){
				$this->rowdata = $row;
				if(!empty($this->rowdata['id'])){
					$this->setID($this->rowdata['id']);
				}
				$this->rowdataOriginal = $row;
			} elseif($requireExistence){
				throw new Exception("Cannot find row in database");
			}
			
		} catch (PDOException $e){
			//most likely rowdata doesn't match sql
			throw $e;
		}
	}
	
	public function write($readAgain = false){
		if(empty($this->rowdata))throw new Error("No row data to write");
		$stmt = null;
		$vals = $this->rowdata;
		unset($vals['id']); //just in case
		
		//ensure that only table values are written
		$columns = self::getConfig('TABLE_COLUMNS');
		foreach($vals as $k=>$v){
			if(!in_array($k, $columns))unset($vals[$k]);
		}
		
		if(empty($this->id)){ //insert
			$stmt = $this->createInsertStatement(array_keys($vals));
			$stmt->execute($vals);
			$this->setID(self::$dbh->lastInsertId());
		} else { //update
			$stmt = $this->createUpdateStatement(array_keys($vals));
			$stmt->execute($vals);
		}
		
		if($readAgain){
			$this->read();
		} else {
			$this->rowdataOriginal = $this->rowdata;
		}
		return $this->id;
	}
	
	public function add($rd = null){
		$this->setID(null);
		$id = $this->write();
		$this->setID(null);
		$this->clearRowData($rd);
		return $id;
	}
	
	public function delete(){
		if(empty($this->id)){
			$id = isset($this->rowdata['id']) && $this->rowdata['id'] ? $this->rowdata['id'] : null;
		} else {
			$id = $this->id;
		}
		if(empty($id))throw new Exception("No ID specified for delete");
		
		$vals = array('id'=>$id);
		$stmt = self::getConfig('DELETE_ROW_BY_ID_STATEMENT');
		$stmt->execute($vals);
		
		$this->id = null;
		$this->rowdata = null;
		return $id;
	}
}
?>
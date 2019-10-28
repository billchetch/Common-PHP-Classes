<?php
class DBObjectTest extends DBObject{
	
	public static function initialise(){
		$t = Config::get('TEST_TABLE');
		self::setConfig('TABLE_NAME', $t);
		self::setConfig('SELECT_SQL', "SELECT *, now() FROM $t");
		self::setConfig('SELECT_ROW_FILTER', "$t.test_text=':test_text'");
		
		echo "DBObjectTest::initialise\n";
		
	}
	
	private $timestamp;
	
	public function __construct($rowdata){
		parent::__construct($rowdata);
		
		$this->assignR2V($this->timestamp, 'timestamp');
	}
}
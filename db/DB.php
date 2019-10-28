<?php
class DB{
	
	private static $dbh; //database connection handle
	
	public static function connect($dbhost, $dbname, $un, $pw){
		self::$dbh = new PDO("$dbhost;dbname=$dbname", $un, $pw);
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		
		DBObject::setConnection(self::$dbh);
	}
	
	
}
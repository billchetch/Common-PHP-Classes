<?php
namespace chetch\db;

use \PDO as PDO;

class DB{
	
	private static $dbh; //database connection handle
	
	public static function connect($dbhost, $dbname, $un, $pw){
		self::$dbh = new PDO("$dbhost;dbname=$dbname", $un, $pw);
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		DBObject::setConnection(self::$dbh);
	}
	
	public static function setUTC(){
		if(empty(self::$dbh))throw new Exception("Database has not been set");
		self::$dbh->query('SET time_zone = "+00:00"'); //UTC for all
	}
}
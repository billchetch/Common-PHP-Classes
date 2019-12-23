<?php
namespace chetch\sys;

use \Exception as Exception;

class Notification extends \chetch\db\DBObject{
	
	public static function initialise(){
		$t = \chetch\Config::get('SYS_NOTIFICATIONS_TABLE', 'sys_notifications');
		self::setConfig('TABLE_NAME',  $t);
	}
	
	public static function createNotification(){
		
		
	}
}
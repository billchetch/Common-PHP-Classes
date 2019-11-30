<?php
namespace chetch\sys;

class Logger extends \chetch\db\DBObject{
	const LOG_TO_SCREEN = 1;
	const LOG_TO_DATABASE = 2;
	
	public static $lastLogName;
	public static $lastLogOptions;
	
	private $logOptions;
	
	public static function initialise(){
		$t = \chetch\Config::get('SYS_LOGS_TABLE', 'sys_logs');
		self::setConfig('TABLE_NAME', $t);
	}
	
	public static function setLog($logName = null, $logOptions = null){
		if($logName)self::$lastLogName = $logName;
		if($logOptions)self::$lastLogOptions = $logOptions;
	}
	
	public static function getLog($logName = null, $logOptions = null){
		
		if(!$logName && self::$lastLogName)$logName = self::$lastLogName;
		if(!$logName)throw new Exception("Logger::getLog no log name provided");
		if(!$logOptions && self::$lastLogOptions)$logOptions = self::$lastLogOptions;
		
		self::$lastLogName = $logName;
		
		$rd = array();
		$rd['log_name'] = $logName;
		
		$log = self::createInstance($rd);
		$log->setLogOptions($logOptions);
		
		return $log;
	}
	
	public function setLogOptions($logOptions){
		$this->logOptions = $logOptions;
		if($logOptions)self::$lastLogOptions = $logOptions;
	}
	
	public function start($logOptions = null){
		if($logOptions)$this->setLogOptions($logOptions);
		$entry = "Starting ".$this->get('log_name')." at ".self::now().' '.self::tzoffset();
		$this->info($entry);
	}
	
	public function info($entry){
		$this->logEntry('INFO', $entry);
	}
	
	public function warning($entry){
		$this->logEntry('WARNING', $entry);
	}
	
	public function exception($entry){
		$this->logEntry('EXCEPTION', $entry);
	}
	
	public function logEntry($type, $entry){
		$this->set('log_entry_type', $type);
		$this->set('log_entry', $entry);
		
		if(($this->logOptions & self::LOG_TO_SCREEN)){
			echo $this->get('log_entry_type').': '.$this->get('log_entry')."\n";
		}
		
		if(($this->logOptions & self::LOG_TO_DATABASE)){
			$logName = $this->get('log_name');
			$this->add(array('log_name'=>$logName));
		}
	}
	
}
?>
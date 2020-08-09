<?php
namespace chetch;

use \Exception as Exception;

//General utility functions
class Utils{
	
	static function distance($lat1, $lon1, $lat2, $lon2, $unit = "K") {
	
	  $theta = $lon1 - $lon2;
	  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
	  $dist = acos($dist);
	  $dist = rad2deg($dist);
	  $miles = $dist * 60 * 1.1515;
	  $unit = strtoupper($unit);
	
	  if ($unit == "K") {
	      return ($miles * 1.609344);
	  } else if ($unit == "N") {
	      return ($miles * 0.8684);
	  } else {
	      return $miles;
	  }
	}
	
	static public function dateDiff($d1, $d2){
		$dt1 = date('Y-m-d', strtotime($d1) + 3600);
		$dt2 = date('Y-m-d', strtotime($d2) + 3600);
		if($dt1 == $dt2)return 0;
		$diff = (strtotime($dt1) - strtotime($dt2)) / 86400;
		return round($diff);
	}
	
	static public function formatWithTimezone($dt, $tz, $format = 'Y-m-d H:i:s '){
		return date($format, strtotime($dt)).$tz;
	}
	
	static public function formatUTC($dt){
		return self::formatWithTimezone($dt, '+0000');
	}
	
	static public function convertToUTC(&$ar, $fields){
		if(is_string($fields))$fields = explode(',', $fields);
		foreach($fields as $f){
			if(isset($ar[$f]))$ar[$f] = self::formatUTC($ar[$f]);
		}
	}
	
	static public function timezoneOffsetInSecs($tz){
		if(strlen($tz) != 5)throw new Exception("$tz is not a recognised timezone offset");
		$h = (int)substr($tz, 1, 2);
		$m = (int)substr($tz, 3, 4);
		$secs = 3600*$h + 60*$m;
		if($tz[0] == '-')$secs = -$secs; //note: used square brackets instead of curly as curly is deprecated
		return $secs;
	}
	
	static public function isWindows(){
		return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
	}
}

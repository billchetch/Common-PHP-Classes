<?php
namespace chetch\network;

use chetch\Utils as Utils;
use chetch\Config as Config;
use \Exception as Exception;


class Network{
	static public function getLANIP($useServer = true){
		if($useServer && isset($_SERVER) && isset($_SERVER['SERVER_ADDR'])){
			$localIP = $_SERVER['SERVER_ADDR'];
		} else {
			$hostname = trim(exec("hostname -I"));
			$parts = explode(' ', $hostname);
			$localIP = $parts[0];
		}
		return $localIP;
	}
	
	static public function getWANIP(){
		$hasInternet = self::hasInternet();
		if($hasInternet){
			$url = Config::get('WAN_IP_URL', "http://bot.whatismyipaddress.com");
			$ip =  @file_get_contents($url);
			return $ip ? $ip :  "N/A";
		} else {
			return null;
		}
	}
	
	static public function hasInternet(){
		$testDomain = Config::get('PING_HAS_INTERNET_DOMAIN', 'google.com');
		try{
			$result = self::ping($testDomain, 1, 4000);
			return (isset($result['loss']) && $result['loss'] == 0);
		} catch (Exception $e){
			return false;
		}
	}
	
	static public function ping($domain, $count = 1, $waitInMs = 10000){
		if(empty($domain))throw new Exception("Network::ping No domain to Ping");
		$exec = '';
		$statistics = '';
		$returnCode = null;
		$waitInSecs = $waitInMs / 1000;
		if(Utils::isWindows()){
			$exec = "ping -n $count -w $waitInMs $domain";
			$statistics = "ping statistics for";
		} else {
			$exec = "ping -c $count -W $waitInSecs $domain";
			$statistics = "--- $domain ping statistics ---";
		}
		$output = array();
		exec($exec.' 2>&1', $output, $returnCode);
		
		if($returnCode != 0){
			$err = count($output) ? $output[0] : "Unknown error. Return code $returnCode";
			throw new \Exception($err);
		}
		
		$stats = null;
		for($i = 0; $i < count($output); $i++){
			//echo $output[$i]."\n";
			if(stripos($output[$i], $statistics) === 0){
				$stats = $output[$i + 1];
			}
		}
		if($stats){
			$ar = explode(',', $stats);
			$stats = array();
			$stats['transmitted'] = trim($ar[0]);
			$stats['received'] = trim($ar[1]);
			if(Utils::isWindows()){
				$stats['transmitted'] = trim(str_ireplace('packets: sent = ', '', $stats['transmitted']));
				$stats['received'] = trim(str_ireplace('received = ', '', $stats['received']));
				$stats['lost'] = $stats['transmitted'] - $stats['received'];
			} else {
				$stats['transmitted'] = trim(str_ireplace('packets transmitted', '', $stats['transmitted']));
				$stats['received'] = trim(str_ireplace('packets received', '', $stats['received']));
				$stats['lost'] = $stats['transmitted'] - $stats['received'];
			}
			$stats['loss'] = $stats['lost'] / $stats['transmitted'];
		}
		return $stats;
	}
	
	public static function getDefaultGatewayIP(){
		$exec = 'netstat -rn';
		$output = array();
		$returnCode;
		exec($exec.' 2>&1', $output, $returnCode);
		
		$headerFound = false;
		$idx1 = Utils::isWindows() ? 1 : 0;
		$idx2 = Utils::isWindows() ? 3 : 1;
		$key = Utils::isWindows() ? '0.0.0.0' : 'default';
		$gatewayIP = null;
		foreach($output as $l){
			$l = preg_replace('/\s+/', ' ',$l);
			$parts = explode(" ", $l);
			if(count($parts) <= $idx2)continue;
			if($headerFound){
				if($parts[$idx1] == $key){
					$gatewayIP = $parts[$idx2];
					break;
				}
			} else {
				$headerFound = $parts[$idx1] == 'Destination' && $parts[$idx2] == 'Gateway';
			}
		}
		
		return $gatewayIP;
	}
}
?>

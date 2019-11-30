<?php
namespace chetch\network;

class Network{
	static function getLANIP(){
		$localIP = gethostbyname(trim(exec("hostname")));
		return $localIP;
	}
	
	static function getWANIP(){
		return file_get_contents("http://bot.whatismyipaddress.com");
	}
}
?>
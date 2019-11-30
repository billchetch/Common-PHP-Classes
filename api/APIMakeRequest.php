<?php
namespace chetch\api;

class APIMakeRequest extends APIRequest{
	
	public static function createRequest($baseURL, $request, $method, $params = null, $readFromCache = false){
		$req = parent::createRequest($baseURL, $request, $method, $params, $readFromCache);
		return $req;
	}
	
	public function __construct($rowdata){
		parent::__construct($rowdata);
		
	}
	
	public function request(){
		//retrieve data
		$url = $this->get('base_url')."/".$this->get('request');
		$method = $this->get('method');
		$params = $this->params;
		
		
		if($params && $method == 'GET'){
			$qs = '';
			foreach($params as $k=>$v){
				$qs.= ($qs ? '&' : '')."$k=".urlencode($v);
			}
			$url = $url.'?'.$qs;
		}
		
		try{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url); 
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, \chetch\Config::get('CURLOPT_CONNECTTIMEOUT',30));
			curl_setopt($ch, CURLOPT_TIMEOUT, \chetch\Config::get('CURLOPT_TIMEOUT',30));
			curl_setopt($ch, CURLOPT_ENCODING, ''); //accept all encodings
			switch($method){
				case 'PUT':
					if(empty($params))throw new Exception("No data to PUT");
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
					break;
					
				case 'POST':
					if(empty($params))throw new Exception("No data to POST");
					curl_setopt($ch, CURLOPT_POST, 1);
        			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
					break;
			}
			
			$data = curl_exec($ch); 
		    $error = curl_error($ch);
		    $errno = curl_errno($ch);
		    $info = curl_getinfo($ch);
		    curl_close($ch);
		    
		    if($errno != 0){
		    	throw new Exception("cURL error: $error", $errno);
		    } else if($info['http_code'] >= 400){
		    	throw new Exception("HTTP Error ".$info['http_code'].' '.$data, $info['http_code']);
			} else {
				$this->set('data', $data);
				return $data;
	        }
		} catch (Exception $e){
			throw $e;
		}
	}
	
	
}
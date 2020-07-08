<?php
namespace chetch\api;

use chetch\api\APIException as APIException;
use \Exception as Exception;

class APIMakeRequest extends APIRequest{
	
	public static function createRequest($baseURL, $request, $method, $params = null, $payload = null, $readFromCache = false){
		$req = parent::createRequest($baseURL, $request, $method, $params, $payload, $readFromCache);
		return $req;
	}
	
	public static function createGetRequest($baseURL, $request, $params = null, $readFromCache = false){
		$req = parent::createRequest($baseURL, $request, 'GET', $params, null, $readFromCache);
		return $req;
	}

	public static function createPutRequest($baseURL, $request, $payload, $params = null, $readFromCache = false){
		$req = parent::createRequest($baseURL, $request, 'PUT', $params, $payload, $readFromCache);
		return $req;
	}
	
	public static function createPostRequest($baseURL, $request, $payload, $params = null, $readFromCache = false){
		$req = parent::createRequest($baseURL, $request, 'POST', $params, $payload, $readFromCache);
		return $req;
	}

	public static function createDeleteRequest($baseURL, $request, $params = null, $readFromCache = false){
		$req = parent::createRequest($baseURL, $request, 'DELETE', $params, null, $readFromCache);
		return $req;
	}
	
	public function __construct($rowdata){
		parent::__construct($rowdata);
		
	}
	
	protected function processResponse($data, $url){
		$this->set('data', $data);
		return json_decode($data, true);
	}
	
	public function request(){
		//retrieve data
		$url = $this->get('base_url')."/".$this->get('request');
		$method = $this->get('method');
		$params = $this->params;
		$payload = $this->payload;
		
		if($params){
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
			switch(strtoupper($method)){
				case 'GET':
					break;

				case 'PUT':
					if(empty($payload))throw new Exception("No data to PUT");
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
					break;
					
				case 'POST':
					if(empty($payload))throw new Exception("No data to POST");
					curl_setopt($ch, CURLOPT_POST, 1);
        				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
					break;

				case 'DELETE':
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
					break;
			}
			
			$data = curl_exec($ch); 
		    	$error = curl_error($ch);
		    	$errno = curl_errno($ch);
		    	$info = curl_getinfo($ch);
		    	curl_close($ch);
		    
		    	if($errno != 0){
		    		throw new APIException("cURL error: $error", $errno);
		    	} else if($info['http_code'] >= 400){
		    		throw new APIException("HTTP Error ".$info['http_code'].' '.$data, $info['http_code']);
			} else {
				return $this->processResponse($data, $url);
	        	}
		} catch (APIException $e){
			throw $e;
		} catch (Exception $e){
			throw $e; //TODO:: turn this in to an API Exception
		}
	}
	
	
}
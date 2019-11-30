<?php
namespace chetch\api;

use \Exception as Exception;

abstract class APIHandleRequest extends APIRequest{
	
	public static function createHandler($request, $method, $params = null, $readFromCache = self::READ_MISSING_VALUES_ONLY){
		$req = parent::createRequest("/", $request, $method, $params, $readFromCache);
		return $req;
	}
	
	public static function output($data2output){
		header('Content-Type: application/json');
		header('X-Server-Time: '.time());
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0"); // Proxies.
		
		echo is_array($data2output) ? json_encode($data2output) : $data2output;
	}
	
	public static function exception($e, $httpCode = 404, $httpMessage = "Not Found"){
		header("HTTP/1.0 $httpCode $httpMessage", true, $httpCode);
		$ex = array();
		$ex['message'] = $e->getMessage();
		$ex['error_code'] = $e->getCode();
		$ex['http_code'] = $httpCode;
		static::output($ex);
	}
	
	public function __construct($rowdata){
		parent::__construct($rowdata);
		
	}
	
	public function handle($output = true){
		
		try{
			$req = $this->get('request');
			$method = $this->get('method');
			$params = $this->params;
			
			$data = array();
			if($req == 'batch'){ //process a number of these in one go
				if(!$params)throw new Exception("APIHandeRequest::handle batch request but no params");
				if(!isset($params['requests']))throw new Exception("APIHandeRequest::handle batch request but no requests parameter set");
				$requests = explode(',', $params['requests']);
				foreach($requests as $req){
					$handler = static::createHandler($req, $method, $params);
					$data[$req] = $handler->handle(false);
				}
				static::output($data);
			} else {
				switch($method){
					case 'GET':
						$data = $this->processGetRequest($req, $params);
						break;
						
					case 'PUT':
						$data = $this->processPutRequest($req, $params);
						break;
				}
				
				$this->set('data', json_encode($data));
				if($output)static::output($data);
				return $data;
			}
		
		} catch (Exception $e){
			if($output){
				static::exception($e);	
			} else {
				throw $e;
			}
		}
	}
	
	protected function processGetRequest($request, $params){
		throw new Exception("Override APIHandleRequest::processGetRequest");	
	}
	
	protected function processPutRequest($request, $params){
		throw new Exception("Override APIHandleRequest::processPutRequest");
	}
	
	protected function processPostRequest($request, $params){
		throw new Exception("Override APIHandleRequest::processPostRequest");
	}
	
	protected function processDeleteRequest($request, $params){
		throw new Exception("Override APIHandleRequest::processDeleteRequest");
	}
}

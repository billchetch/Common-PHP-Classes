<?php
namespace chetch\api;

use chetch\api\APIException as APIException;
use \Exception as Exception;

abstract class APIHandleRequest extends APIRequest{
	const SOURCE_CACHE = 1;
	const SOURCE_DATABASE = 2;
	const SOURCE_REMOTE = 3;

	public static function createHandler($request, $method, $params = null, $payload = null, $readFromCache = self::READ_MISSING_VALUES_ONLY){
		$req = parent::createRequest("/", $request, $method, $params, $payload, $readFromCache);
		return $req;
	}
	
	public static function output($data2output){
		header('Content-Type: application/json');
		header('X-Server-Time: '.self::now());
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

	public static function about(){
		$data = array();
		$data['api_version'] = self::getConfig('API_VERSION', "1.0");
		$data['server_time'] = self::now();
		return $data;
	}
	
	public $source;

	public function __construct($rowdata){
		parent::__construct($rowdata);
		$this->source = \chetch\Config::get('API_SOURCE', self::SOURCE_DATABASE);
	}
	
	public function handle($output = true){
		
		try{
			$req = $this->get('request');
			$method = $this->get('method');
			$params = $this->params;
			$payload = $this->payload;
			
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
						$data = $this->processPutRequest($req, $params, $payload);
						break;

					case 'POST':
						$data = $this->processPostRequest($req, $params, $payload);
						break;

					case 'DELETE':
						$data = $this->processDeleteRequest($req, $params);
						break;
				}
				
				$this->set('data', json_encode($data));
				if($output)static::output($data);
				return $data;
			}
		
		} catch (Exception $e){
			$this->handleException($e);
		}
	}
	
	protected function handleException(Exception $e){
		throw $e;
	}

	protected function processGetRequest($request, $params){
		throw new Exception("Override APIHandleRequest::processGetRequest");	
	}
	
	protected function processGetResourceRequest($request, $params){
		$requestParts = explode('/', $request);
		$resourceType = $requestParts[1];
		$resourceDirectory = $requestParts[2];
		$resourceID = $requestParts[3];	

		$contentTypes = array();
		switch($resourceType){
			case 'image':
				$contentTypes['image/jpeg'] = array('jpg','jpeg');
				$contentTypes['image/png'] = array('png');
				break;

			case 'apk':
				$contentTypes['application/vnd.android.package-archive'] = array('apk');
				$contentTypes['application/java-archive'] = array('jar');
				break;
		}

		$resourcePathBase = getcwd()."\\resources\\$resourceDirectory\\";
		$resourcePaths = array();
		array_push($resourcePaths, $resourcePathBase.$resourceID);
		$this->addResourePaths($resourcePaths, $resourcePathBase, $resourceType, $resourceDirectory, $resourceID);
		
		$resourceFile = null;
		$headerInfo = array();
		foreach($resourcePaths as $resourcePath){
			foreach($contentTypes as $contentType=>$extensions){
				foreach($extensions as $extension){
					$filepath = $resourcePath.'.'.$extension;
					if(file_exists($filepath)){
						$resourceFile = $filepath;
						$headerInfo["Content-Type"] = $contentType;
						$headerInfo["Content-Length"] = filesize($resourceFile);
						break;
					}
				}
				if($resourceFile)break;
			}
			if($resourceFile)break;
		}

				
		if(!$resourceFile){
			throw new Exception("Unable to find resource $request");
		}
		
		foreach($headerInfo as $k=>$v){
			header("$k: $v");
		}

		readfile($resourceFile);
		die;
	}

	protected function addResourePaths(&$resourcePaths, $resourcePathBase, $resourceType, $resourceDirectory, $resourceID){
	
	}

	protected function processPutRequest($request, $params, $payload){
		throw new Exception("Override APIHandleRequest::processPutRequest");
	}
	
	protected function processPostRequest($request, $params, $payload){
		throw new Exception("Override APIHandleRequest::processPostRequest");
	}
	
	protected function processDeleteRequest($request, $params){
		throw new Exception("Override APIHandleRequest::processDeleteRequest");
	}
}

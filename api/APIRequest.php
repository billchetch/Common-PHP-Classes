<?php
namespace chetch\api;

use chetch\api\APIException as APIException;
use \Exception as Exception;

class APIRequest extends \chetch\db\DBObject{
	
	public static function initialise(){
		$t = \chetch\Config::get('API_REQUEST_TABLE', 'api_requests');
		self::setConfig('TABLE_NAME', $t);
		self::setConfig('SELECT_SQL', "SELECT *, now() - last_updated AS secs_old FROM $t");
		self::setConfig('SELECT_ROW_FILTER', "$t.base_url=':base_url' AND $t.request=':request' AND $t.method=':method'");
	}
	
	public static function createRequest($baseURL, $request, $method, $params = null, $payload = null, $readFromCache = false){
		if(empty($baseURL))throw new Exception("APIRequest::createRequest no base URL supplied");
		if(empty($request))throw new Exception("APIRequest::createRequest no request supplied");
		if(empty($method))throw new Exception("APIRequest::createRequest no method supplied");
		
		$r = array();
		$r['base_url'] = trim(strtolower($baseURL));
		$r['request'] =  trim(strtolower($request));
		$r['method'] = trim(strtoupper($method));
		
		$req = self::createInstance($r, $readFromCache);
		if($params){
			if(is_string($params)){ //we assume a query string
				$p = array(); //
				parse_str($params, $p);
				$req->setParams($p);
			} else {
				$req->setParams($params);
			}
		}

		if($payload){
			if(is_string($payload)){ //we assume this is JSON
				$req->setPayload(json_decode($payload, true));
			} else {
				$req->setPayload($payload);
			}
		}
		
		return $req;
		
	}
	
	/*
	* Instance fields and methods
	*/

	protected $params;
	protected $payload;
	
	public function __construct($rowdata){
		parent::__construct($rowdata);
		
	}
	
	public function setParams($p){
		$this->params = $p;
	}

	public function setPayload($p){
		$this->payload = $p;
	}
	
}
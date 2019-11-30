<?php
namespace chetch\sys;

class SysInfo extends \chetch\db\DBObject{
	
	public static function initialise(){
		$t = Config::get('SYS_INFO_TABLE', 'sys_info');
		self::setConfig('TABLE_NAME',  $t);
		self::setConfig('SELECT_ROW_FILTER', "data_name=:data_name");
	}
	
	private function sync($dataName){
		$dn = $this->get("data_name");
		if($dn != $dataName){
			$this->clearRowData(array("data_name"=>$dataName));
			$this->read();
		}
	}
	
	public function setData($dataName, $dataValue){
		$this->sync($dataName);
		
		if(is_array($dataValue)){
			$dataValue = json_encode($dataValue);
			$this->set('encoded', 1);	
		} else {
			$this->set('encoded', 0);
		}
		
		$this->set('data_value', $dataValue);
		$this->set('updated', self::now());
		
		return $this->write();
	}
	
	public function getData($dataName){
		$this->sync($dataName);
		
		if(!empty($this->get('encoded'))){
			return json_decode($this->get('data_value'), true);
		} else {
			return $this->get('data_value');
		}
	}
	
	public function clear($dataName, $delete = false){
		if($delete){
			//TODO: delete record
		} else {
			$this->setData($dataName, null);
		}
	}
}
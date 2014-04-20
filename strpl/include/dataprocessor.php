<?php
/****************************************************************************** 
* Streamplaner v 1.0                                                          *
* (c) 2014 by NoiSens Media - www.noisens.de                                  *
*                                                       dataprocessor.php     *
******************************************************************************/

require_once("xss_filter.php");

class DataProcessor{
	protected $connector;
	protected $config;
	protected $request;
	static public $action_param ="!nativeeditor_status";


	function __construct($connector,$config,$request){
		$this->connector= $connector;
		$this->config=$config;
		$this->request=$request;
	}
	

	function name_data($data){
		return $data;
	}

	protected function get_post_values($ids){
		$data=array(); 
		for ($i=0; $i < sizeof($ids); $i++)
			$data[$ids[$i]]=array();
		
		foreach ($_POST as $key => $value) {
			$details=explode("_",$key,2);
			if (sizeof($details)==1) continue;
			
			$name=$this->name_data($details[1]);
			$data[$details[0]][$name]=ConnectorSecurity::filter($value);
		}
			
		return $data;
	}
	protected function get_ids(){
		if (!isset($_POST["ids"]))
			throw new Exception("Incorrect incoming data, ID of incoming records not recognized");
		return explode(",",$_POST["ids"]);
	}
	
	protected function get_operation($rid){
		if (!isset($_POST[$rid."_".DataProcessor::$action_param]))
			throw new Exception("Status of record [{$rid}] not found in incoming request");
		return $_POST[$rid."_".DataProcessor::$action_param];
	}

	function process(){
		LogMaster::log("DataProcessor object initialized",$_POST);
		
		$results=array();

		$ids=$this->get_ids();
		$rows_data=$this->get_post_values($ids);
		$failed=false;
		
		try{
			if ($this->connector->sql->is_global_transaction())
				$this->connector->sql->begin_transaction();
			
			for ($i=0; $i < sizeof($ids); $i++) { 
				$rid = $ids[$i];
				LogMaster::log("Row data [{$rid}]",$rows_data[$rid]);
				$status = $this->get_operation($rid);
				
				$action=new DataAction($status,$rid,$rows_data[$rid]);
				$results[]=$action;
				$this->inner_process($action);
			}
			
		} catch(Exception $e){
			LogMaster::log($e);
			$failed=true;
		}
		
		if ($this->connector->sql->is_global_transaction()){
			if (!$failed)
				for ($i=0; $i < sizeof($results); $i++)
					if ($results[$i]->get_status()=="error" || $results[$i]->get_status()=="invalid"){
						$failed=true; 
						break;
					}
			if ($failed){
				for ($i=0; $i < sizeof($results); $i++)
					$results[$i]->error();
				$this->connector->sql->rollback_transaction();
			}
			else
				$this->connector->sql->commit_transaction();
		}
		
		$this->output_as_xml($results);
	}	
	
	protected function status_to_mode($status){
		switch($status){
			case "updated":
				return "update";
				break;
			case "inserted":
				return "insert";
				break;
			case "deleted":
				return "delete";
				break;
			default:
				return $status;
				break;
		}
	}

	protected function inner_process($action){
		
		if ($this->connector->sql->is_record_transaction())
				$this->connector->sql->begin_transaction();		
		
		try{
				
			$mode = $this->status_to_mode($action->get_status());
			if (!$this->connector->access->check($mode)){
				LogMaster::log("Access control: {$operation} operation blocked");
				$action->error();
			} else {
				$check = $this->connector->event->trigger("beforeProcessing",$action);
				if (!$action->is_ready())
					$this->check_exts($action,$mode);
				$check = $this->connector->event->trigger("afterProcessing",$action);
			}
		
		} catch (Exception $e){
			LogMaster::log($e);
			$action->set_status("error");
			if ($action)
				$this->connector->event->trigger("onDBError", $action, $e);
		}  
		
		if ($this->connector->sql->is_record_transaction()){
			if ($action->get_status()=="error" || $action->get_status()=="invalid")
				$this->connector->sql->rollback_transaction();		
			else
				$this->connector->sql->commit_transaction();		
		}
				
		return $action;
	}

	function check_exts($action,$mode){
		$old_config = new DataConfig($this->config);
		
		$this->connector->event->trigger("before".$mode,$action);
		if ($action->is_ready())
			LogMaster::log("Event code for ".$mode." processed");
		else {
			
			$sql = $this->connector->sql->get_sql($mode,$action);
			if ($sql){
				$this->connector->sql->query($sql);
			}
			else{
				$action->sync_config($this->config);
				if ($this->connector->model && method_exists($this->connector->model, $mode)){
					call_user_func(array($this->connector->model, $mode), $action);
					LogMaster::log("Model object process action: ".$mode);
				}
				if (!$action->is_ready()){
					$method=array($this->connector->sql,$mode);
					if (!is_callable($method))
						throw new Exception("Unknown dataprocessing action: ".$mode);
					call_user_func($method,$action,$this->request);
				}
			}
		}
		$this->connector->event->trigger("after".$mode,$action);
		
		$this->config->copy($old_config);
	}
	

	function output_as_xml($results){
		LogMaster::log("Edit operation finished",$results);
		ob_clean();
		header("Content-type:text/xml");
		echo "<?xml version='1.0' ?>";
		echo "<data>";
		for ($i=0; $i < sizeof($results); $i++)
			echo $results[$i]->to_xml();
		echo "</data>";
	}		
	
}


class DataAction{
	private $status; 
	private $id;
	private $data;
	private $userdata;
	private $nid;
	private $output;
	private $attrs;
	private $ready;
	private $addf;
	private $delf;
	
	

	function __construct($status,$id,$data){
		$this->status=$status;
		$this->id=$id;
		$this->data=$data;	
		$this->nid=$id;
		
		$this->output="";
		$this->attrs=array();
		$this->ready=false;
		
		$this->addf=array();
		$this->delf=array();
	}

	

	function add_field($name,$value){
		LogMaster::log("adding field: ".$name.", with value: ".$value);
		$this->data[$name]=$value;
		$this->addf[]=$name;
	}

	function remove_field($name){
		LogMaster::log("removing field: ".$name);
		$this->delf[]=$name;
	}
	

	function sync_config($slave){
		foreach ($this->addf as $k => $v)
			$slave->add_field($v);
		foreach ($this->delf as $k => $v)
			$slave->remove_field($v);
	}

	function get_value($name){
		if (!array_key_exists($name,$this->data)){
			LogMaster::log("Incorrect field name used: ".$name);
			LogMaster::log("data",$this->data);
			return "";
		}
		return $this->data[$name];
	}

	function set_value($name,$value){
		LogMaster::log("change value of: ".$name." as: ".$value);
		$this->data[$name]=$value;
	}

	function get_data(){
		return $this->data;
	}

	function get_userdata_value($name){
		return $this->get_value($name);
	}

	function set_userdata_value($name,$value){
		return $this->set_value($name,$value);
	}

	function get_status(){
		return $this->status;
	}

	function set_status($status){
		$this->status=$status;
	}

	function set_id($id) {
	    $this->id = $id;
	    LogMaster::log("Change id: ".$id);
	}

	function set_new_id($id) {
	    $this->nid = $id;
	    LogMaster::log("Change new id: ".$id);
	}		

	function get_id(){
		return $this->id;
	}

	function set_response_text($text){
		$this->set_response_xml("<![CDATA[".$text."]]>");
	}

	function set_response_xml($text){
		$this->output=$text;
	}

	function set_response_attribute($name,$value){
		$this->attrs[$name]=$value;
	}

	function is_ready(){
		return $this->ready;
	}	

	function get_new_id(){
		return $this->nid;
	}
	

	function error(){
		$this->status="error";
		$this->ready=true;
	}

	function invalid(){
		$this->status="invalid";
		$this->ready=true;
	}

	function success($id=false){
		if ($id!==false)
			$this->nid = $id;
		$this->ready=true;
	}

	function to_xml(){
		$str="<action type='{$this->status}' sid='{$this->id}' tid='{$this->nid}' ";
		foreach ($this->attrs as $k => $v) {
			$str.=$k."='".$v."' ";
		}
		$str.=">{$this->output}</action>";	
		return $str;
	}

	function __toString(){
		return "action:{$this->status}; sid:{$this->id}; tid:{$this->nid};";
	}
}
?>
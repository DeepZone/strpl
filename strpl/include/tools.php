<?php
/****************************************************************************** 
* Streamplaner v 1.0                                                          *
* (c) 2014 by NoiSens Media - www.noisens.de                                  *
*                                                               tools.php     *
******************************************************************************/


class EventMaster{
	private $events;
	private $master;
	private static $eventsStatic=array();


	function __construct(){
		$this->events=array();
		$this->master = false;
	}

	public function exist($name){
		$name=strtolower($name);
		return (isset($this->events[$name]) && sizeof($this->events[$name]));
	}

	public function attach($name,$method=false){
		
		if ($method === false){
			$this->master = $name;
			return;
		}
		
		$name=strtolower($name);
		if (!array_key_exists($name,$this->events))
			$this->events[$name]=array();
		$this->events[$name][]=$method;
	}
	
	public static function attach_static($name, $method){
		$name=strtolower($name);
		if (!array_key_exists($name,EventMaster::$eventsStatic))
			EventMaster::$eventsStatic[$name]=array();
		EventMaster::$eventsStatic[$name][]=$method;
	}
	
	public static function trigger_static($name, $method){
		$arg_list = func_get_args();
		$name=strtolower(array_shift($arg_list));
		
		if (isset(EventMaster::$eventsStatic[$name]))
			foreach(EventMaster::$eventsStatic[$name] as $method){
				if (is_array($method) && !method_exists($method[0],$method[1]))
					throw new Exception("Incorrect method assigned to event: ".$method[0].":".$method[1]);
				if (!is_array($method) && !function_exists($method))
					throw new Exception("Incorrect function assigned to event: ".$method);
				call_user_func_array($method, $arg_list);
			}
		return true;		
	}
	
	
	public function detach($name){
		$name=strtolower($name);
		unset($this->events[$name]);
	}

	public function trigger($name,$data){
		$arg_list = func_get_args();
		$name=strtolower(array_shift($arg_list));
		
		if (isset($this->events[$name]))
			foreach($this->events[$name] as $method){
				if (is_array($method) && !method_exists($method[0],$method[1]))
					throw new Exception("Incorrect method assigned to event: ".$method[0].":".$method[1]);
				if (!is_array($method) && !function_exists($method))
					throw new Exception("Incorrect function assigned to event: ".$method);
				call_user_func_array($method, $arg_list);
			}

		if ($this->master !== false)
			if (method_exists($this->master, $name))
				call_user_func_array(array($this->master, $name), $arg_list);

		return true;
	}
}


class AccessMaster{
	private $rules,$local;

	function __construct(){
		$this->rules=array("read" => true, "insert" => true, "update" => true, "delete" => true);
		$this->local=true;
	}

	public function allow($name){
		$this->rules[$name]=true;
	}

	public function deny($name){
		$this->rules[$name]=false;
	}
	

	public function deny_all(){
		$this->rules=array();
	}	
	

	public function check($name){
		if ($this->local){

		}
		if (!isset($this->rules[$name]) || !$this->rules[$name]){
			return false;
		}
		return true;
	}
}


class LogMaster{
	private static $_log=false;
	private static $_output=false;
	private static $session="";
	

	private static function log_details($data,$pref=""){
		if (is_array($data)){
			$str=array("");
			foreach($data as $k=>$v)
				array_push($str,$pref.$k." => ".LogMaster::log_details($v,$pref."\t"));
			return implode("\n",$str);
   		}
   		return $data;
	}

	public static function log($str="",$data=""){
		if (LogMaster::$_log){
			$message = $str.LogMaster::log_details($data)."\n\n";
			LogMaster::$session.=$message;
			error_log($message,3,LogMaster::$_log);			
		}
	}
	

	public static function get_session_log(){
		return LogMaster::$session;
	}
	

	public static function error_log($errn,$errstr,$file,$line,$context){
		LogMaster::log($errstr." at ".$file." line ".$line);
	}
	

	public static function exception_log($exception){
		LogMaster::log("!!!Uncaught Exception\nCode: " . $exception->getCode() . "\nMessage: " . $exception->getMessage());
		if (LogMaster::$_output){
			echo "<pre><xmp>\n";
			echo LogMaster::get_session_log();
			echo "\n</xmp></pre>";
		}
		die();
	}
	

	public static function enable_log($name,$output=false){
		LogMaster::$_log=$name;
		LogMaster::$_output=$output;
		if ($name){
			set_error_handler(array("LogMaster","error_log"),E_ALL);
			set_exception_handler(array("LogMaster","exception_log"));
			LogMaster::log("\n\n====================================\nLog started, ".date("d/m/Y h:i:s")."\n====================================");
		}
	}
}

?>
<?php
/****************************************************************************** 
* Streamplaner v 1.0                                                          *
* (c) 2014 by NoiSens Media - www.noisens.de                                  *
*                                                      base_connector.php     *
******************************************************************************/


require_once("tools.php");
require_once("db_common.php");
require_once("dataprocessor.php");
require_once("strategy.php");
require_once("update.php");


ini_set("output_buffering","On");
ob_start();

class OutputWriter{
	private $start;
	private $end;
	private $type;

	public function __construct($start, $end = ""){
		$this->start = $start;
		$this->end = $end;
		$this->type = "xml";
	}
	public function add($add){
		$this->start.=$add;
	}
	public function reset(){
		$this->start="";
		$this->end="";
	}
	public function set_type($add){
		$this->type=$add;
	}
	public function output($name="", $inline=true, $encoding=""){
		ob_clean();
		
		if ($this->type == "xml"){
			$header = "Content-type: text/xml";
			if ("" != $encoding)
				$header.="; charset=".$encoding;
			header($header);
		}
			
		echo $this->__toString();
	}
	public function __toString(){
		return $this->start.$this->end;
	}
}


class EventInterface{ 
	protected $request; 
	public $rules=array(); 
	

	public function __construct($request){
		$this->request = $request;
	}

	
	public function clear(){
		array_splice($rules,0);
	}

	public function index($name){
		$len = sizeof($this->rules);
		for ($i=0; $i < $len; $i++) { 
			if ($this->rules[$i]["name"]==$name)
				return $i;
		}
		return false;
	}
}

class SortInterface extends EventInterface{

	public function __construct($request){
		parent::__construct($request);
		$this->rules = &$request->get_sort_by_ref();
	}

	public function add($name,$dir){
		if ($dir === false)
			$this->request->set_sort($name);
		else
			$this->request->set_sort($name,$dir);
	}
	public function store(){
		$this->request->set_sort_by($this->rules);
	}
}

class FilterInterface extends EventInterface{
	
	public function __construct($request){
		$this->request = $request;
		$this->rules = &$request->get_filters_ref();
	}

	public function add($name,$value,$rule){
		$this->request->set_filter($name,$value,$rule);
	}
	public function store(){
		$this->request->set_filters($this->rules);
	}
}


class DataItem{
	protected $data; 
	protected $config;
	protected $index;
	protected $skip;
	protected $userdata;


	function __construct($data,$config,$index){
		$this->config=$config;
		$this->data=$data;
		$this->index=$index;
		$this->skip=false;
		$this->userdata=false;
	}


	function set_userdata($name, $value){
		if ($this->userdata === false)
			$this->userdata = array();

		$this->userdata[$name]=$value;
	}

	public function get_value($name){
		return $this->data[$name];
	}

	public function set_value($name,$value){
		return $this->data[$name]=$value;
	}

	public function get_id(){
		$id = $this->config->id["name"];
		if (array_key_exists($id,$this->data))
			return $this->data[$id];
		return false;
	}

	public function set_id($value){
		$this->data[$this->config->id["name"]]=$value;
	}

	public function get_index(){
		return $this->index;
	}

	public function skip(){
		$this->skip=true;
	}
	

	public function to_xml(){
		return $this->to_xml_start().$this->to_xml_end();
	}
	

	public function xmlentities($string) { 
   		return str_replace( array( '&', '"', "'", '<', '>', '’' ), array( '&amp;' , '&quot;', '&apos;' , '&lt;' , '&gt;', '&apos;' ), $string);
	}
	
	public function to_xml_start(){
		$str="<item";
		for ($i=0; $i < sizeof($this->config->data); $i++){ 
			$name=$this->config->data[$i]["name"];
			$db_name=$this->config->data[$i]["db_name"];
			$str.=" ".$name."='".$this->xmlentities($this->data[$name])."'";
		}
		
		if ($this->userdata !== false)
			foreach ($this->userdata as $key => $value){
				$str.=" ".$key."='".$this->xmlentities($value)."'";
			}

		return $str.">";
	}

	public function to_xml_end(){
		return "</item>";
	}
}





class Connector {
	protected $config;
	protected $request;
	protected $names;
	protected $encoding="utf-8";
	protected $editing=false;

	public static $filter_var="dhx_filter";
	public static $sort_var="dhx_sort";

	public $model=false;

	private $updating=false;
	private $db; 
	protected $dload;
	public $access;  
	protected $data_separator = "\n";
	
	public $sql;	
	public $event;	
	public $limit=false;
	
	private $id_seed=0; 
	protected $live_update = false; 
	protected $extra_output="";
	protected $options=array();
	protected $as_string = false; 
	protected $simple = false; 
	protected $filters;
	protected $sorts;
	protected $mix;
	

	public function __construct($db,$type=false, $item_type=false, $data_type=false, $render_type = false){
		$this->exec_time=microtime(true);

		if (!$type) $type="MySQL";
		if (class_exists($type."DBDataWrapper",false)) $type.="DBDataWrapper";
		if (!$item_type) $item_type="DataItem";
		if (!$data_type) $data_type="DataProcessor";
		if (!$render_type) $render_type="RenderStrategy";
		
		$this->names=array(
			"db_class"=>$type,
			"item_class"=>$item_type,
			"data_class"=>$data_type,
			"render_class"=>$render_type
		);
		$this->attributes = array();
		$this->filters = array();
		$this->sorts = array();
		$this->mix = array();
		
		$this->config = new DataConfig();
		$this->request = new DataRequestConfig();
		$this->event = new EventMaster();
		$this->access = new AccessMaster();

		if (!class_exists($this->names["db_class"],false))
			throw new Exception("DB class not found: ".$this->names["db_class"]);
		$this->sql = new $this->names["db_class"]($db,$this->config);
		$this->render = new $this->names["render_class"]($this);
		
		$this->db=$db;
		
		EventMaster::trigger_static("connectorCreate",$this);
	}


	protected function get_connection(){
		return $this->db;
	}

	public function get_config(){
		return new DataConfig($this->config);
	}
	
	public function get_request(){
		return new DataRequestConfig($this->request);
	}


	protected $attributes;
	public function add_top_attribute($name, $string){
		$this->attributes[$name] = $string;
	}


	public function useModel($model){
		$this->model = $model;
	}


	public function render_table($table,$id="",$fields=false,$extra=false,$relation_id=false){
		$this->configure($table,$id,$fields,$extra,$relation_id);
		return $this->render();
	}
	public function configure($table,$id="",$fields=false,$extra=false,$relation_id=false){
        if ($fields === false){
           
            $info = $this->sql->fields_list($table);
            $fields = implode(",",$info["fields"]);
            if ($info["key"])
                $id = $info["key"];
        }
		$this->config->init($id,$fields,$extra,$relation_id);
		if (strpos(trim($table), " ")!==false)
			$this->request->parse_sql($table);
		else
			$this->request->set_source($table);
	}
	
	public function uuid(){
		return time()."x".$this->id_seed++;
	}
	
	public function render_sql($sql,$id,$fields,$extra=false,$relation_id=false){
		$this->config->init($id,$fields,$extra,$relation_id);
		$this->request->parse_sql($sql);
		return $this->render();
	}

	public function render_array($data, $id, $fields, $extra=false, $relation_id=false){
		$this->configure("-",$id,$fields,$extra,$relation_id);
		$this->sql = new ArrayDBDataWrapper($data, $this->config);
		return $this->render();
	}

	public function render_complex_sql($sql,$id,$fields,$extra=false,$relation_id=false){
		$this->config->init($id,$fields,$extra,$relation_id);
		$this->request->parse_sql($sql, true);
		return $this->render();
	}	
	
	public function render_connector($config,$request){
		$this->config->copy($config);
		$this->request->copy($request);
		return $this->render();
	}	
	

	public function render(){
        $this->event->trigger("onInit", $this);
		EventMaster::trigger_static("connectorInit",$this);
		
		if (!$this->as_string)
			$this->parse_request();
		$this->set_relation();
		
		if ($this->live_update !== false && $this->updating!==false) {
			$this->live_update->get_updates();
		} else {
			if ($this->editing){
				$dp = new $this->names["data_class"]($this,$this->config,$this->request);
				$dp->process($this->config,$this->request);
			} else {
				if (!$this->access->check("read")){
					LogMaster::log("Access control: read operation blocked");
					echo "Access denied";
					die();
				}
				$wrap = new SortInterface($this->request);
				$this->apply_sorts($wrap);
				$this->event->trigger("beforeSort",$wrap);
				$wrap->store();
				
				$wrap = new FilterInterface($this->request);
				$this->apply_filters($wrap);
				$this->event->trigger("beforeFilter",$wrap);
				$wrap->store();

				if ($this->model && method_exists($this->model, "get")){
					$this->sql = new ArrayDBDataWrapper();
					$result = new ArrayQueryWrapper(call_user_func(array($this->model, "get"), $this->request));
					$out = $this->output_as_xml($result);
				} else {
					$out = $this->output_as_xml($this->get_resource());
				
				if ($out !== null) return $out;
			}

			}
		}
		$this->end_run();
	}

	protected function set_relation() {}

	protected function get_resource() {
		return $this->sql->select($this->request);
	}

	protected function safe_field_name($str){
		return strtok($str, " \n\t;',");
	}
	
	public function set_limit($limit){
		$this->limit = $limit;
	}
	

	public function limit($start, $count, $sort_field=false, $sort_dir=false){
		$this->request->set_limit($start, $count);
		if ($sort_field)
			$this->request->set_sort($sort_field, $sort_dir);
	}
	
	protected function parse_request_mode(){
		
        if (isset($_GET["editing"])){
			$this->editing=true;
        } else if (isset($_POST["ids"])){
			$this->editing=true;
			LogMaster::log('While there is no edit mode mark, POST parameters similar to edit mode detected. \n Switching to edit mode ( to disable behavior remove POST[ids]');
		} else if (isset($_GET['dhx_version'])){
			$this->updating = true;
        }
	}

	protected function parse_request(){
		
		if ($this->dload)
            $this->request->set_limit(0,$this->dload);
		else if ($this->limit)
			$this->request->set_limit(0,$this->limit);

        if (isset($_GET["posStart"]) && isset($_GET["count"])) {
            $this->request->set_limit($_GET["posStart"],$_GET["count"]);
        }

		$this->parse_request_mode();

        if ($this->live_update && ($this->updating || $this->editing)){
            $this->request->set_version($_GET["dhx_version"]);
            $this->request->set_user($_GET["dhx_user"]);
        }
		
		if (isset($_GET[Connector::$sort_var]))
			foreach($_GET[Connector::$sort_var] as $k => $v){
				$k = $this->safe_field_name($k);
				$this->request->set_sort($this->resolve_parameter($k),$v);
			}
				
		if (isset($_GET[Connector::$sort_var]))
			foreach($_GET[Connector::$filter_var] as $k => $v){
				$k = $this->safe_field_name($k);
				$this->request->set_filter($this->resolve_parameter($k),$v);
			}
			
		$key = ConnectorSecurity::checkCSRF($this->editing);
		if ($key !== "")
			$this->add_top_attribute(ConnectorSecurity::$security_var, $key);
		
	}

	protected function resolve_parameter($name){
		return $name;
	}

	protected function xmlentities($string) {
   		return str_replace( array( '&', '"', "'", '<', '>', '’' ), array( '&amp;' , '&quot;', '&apos;' , '&lt;' , '&gt;', '&apos;' ), $string);
	}
    
	public function getRecord($id){
		LogMaster::log("Retreiving data for record: ".$id);
		$source = new DataRequestConfig($this->request);
		$source->set_filter($this->config->id["name"],$id, "=");
		
		$res = $this->sql->select($source);
		
		$temp = $this->data_separator;
		$this->data_separator="";
		$output = $this->render_set($res);
		$this->data_separato=$temp;
		
		return $output;
	}

	protected function render_set($res){
		return $this->render->render_set($res, $this->names["item_class"], $this->dload, $this->data_separator, $this->config, $this->mix);
	}
	
	protected function output_as_xml($res){
		$result = $this->render_set($res);
		if ($this->simple) return $result;

		$start="<?xml version='1.0' encoding='".$this->encoding."' ?>".$this->xml_start();
		$end=$result.$this->xml_end();

		if ($this->as_string) return $start.$end;

		$out = new OutputWriter($start, $end);
		$this->event->trigger("beforeOutput", $this, $out);
		$out->output("", true, $this->encoding);
	}
	
	protected function end_run(){
		$time=microtime(true)-$this->exec_time;
		LogMaster::log("Done in {$time}s");
		flush();
		die();
	}
	
	public function set_encoding($encoding){
		$this->encoding=$encoding;
	}

	public function dynamic_loading($count){
		$this->dload=$count;
	}	
		
	public function enable_log($path=true,$client_log=false){
		LogMaster::enable_log($path,$client_log);
	}
	
	public function is_select_mode(){
		$this->parse_request_mode();
		return !$this->editing;
	}
	
	public function is_first_call(){
		$this->parse_request_mode();
		return !($this->editing || $this->updating || $this->request->get_start() || isset($_GET['dhx_no_header']));
		
	}
	
	protected function xml_start(){
		$attributes = "";

        if ($this->dload){
           
            if ($pos=$this->request->get_start())
                $attributes .= " pos='".$pos."'";
            else
                $attributes .= " total_count='".$this->sql->get_size($this->request)."'";
        }
		foreach($this->attributes as $k=>$v)
			$attributes .= " ".$k."='".$v."'";

		return "<data".$attributes.">";
	}

	protected function xml_end(){
		$this->fill_collections();
		if (isset($this->extra_output))
			return $this->extra_output."</data>";
		else
			return "</data>";
	}

	protected function fill_collections($list=""){
		foreach ($this->options as $k=>$v) { 
			$name = $k;
			$this->extra_output.="<coll_options for='{$name}'>";
			if (!is_string($this->options[$name]))
				$this->extra_output.=$this->options[$name]->render();
			else
				$this->extra_output.=$this->options[$name];
			$this->extra_output.="</coll_options>";
		}
	}

	public function set_options($name,$options){
		if (is_array($options)){
			$str="";
			foreach($options as $k => $v)
				$str.="<item value='".$this->xmlentities($k)."' label='".$this->xmlentities($v)."' />";
			$options=$str;
		}
		$this->options[$name]=$options;
	}


	public function insert($data) {
		$action = new DataAction('inserted', false, $data);
		$request = new DataRequestConfig();
		$request->set_source($this->request->get_source());
		
		$this->config->limit_fields($data);
		$this->sql->insert($action,$request);
		$this->config->restore_fields($data);
		
		return $action->get_new_id();
	}
	
	public function delete($id) {
		$action = new DataAction('deleted', $id, array());
		$request = new DataRequestConfig();
		$request->set_source($this->request->get_source());
		
		$this->sql->delete($action,$request);
		return $action->get_status();
}

	public function update($data) {
		$action = new DataAction('updated', $data[$this->config->id["name"]], $data);
		$request = new DataRequestConfig();
		$request->set_source($this->request->get_source());

		$this->config->limit_fields($data);
		$this->sql->update($action,$request);
		$this->config->restore_fields($data);
		
		return $action->get_status();
	}

	public function enable_live_update($table, $url=false){
		$this->live_update = new DataUpdate($this->sql, $this->config, $this->request, $table,$url);
        $this->live_update->set_event($this->event,$this->names["item_class"]);
		$this->event->attach("beforeOutput", 		Array($this->live_update, "version_output"));
		$this->event->attach("beforeFiltering", 	Array($this->live_update, "get_updates"));
		$this->event->attach("beforeProcessing", 	Array($this->live_update, "check_collision"));
		$this->event->attach("afterProcessing", 	Array($this->live_update, "log_operations"));
	}

	public function asString($as_string) {
		$this->as_string = $as_string;
	}

	public function simple_render() {
		$this->simple = true;
		return $this->render();
	}

	public function filter($name, $value = false, $operation = '=') {
		$this->filters[] = array('name' => $name, 'value' => $value, 'operation' => $operation);
	}

	public function clear_filter() {
		$this->filters = array();
		$this->request->set_filters(array());
	}

	protected function apply_filters($wrap) {
		for ($i = 0; $i < count($this->filters); $i++) {
			$f = $this->filters[$i];
			$wrap->add($f['name'], $f['value'], $f['operation']);
		}
	}

	public function sort($name, $direction = false) {
		$this->sorts[] = array('name' => $name, 'direction' => $direction);
	}

	protected function apply_sorts($wrap) {
		for ($i = 0; $i < count($this->sorts); $i++) {
			$s = $this->sorts[$i];
			$wrap->add($s['name'], $s['direction']);
		}
	}

	public function mix($name, $value, $filter=false) {
		$this->mix[] = Array('name'=>$name, 'value'=>$value, 'filter'=>$filter);
	}
}



class OptionsConnector extends Connector{
	protected $init_flag=false;
	public function __construct($res,$type=false,$item_type=false,$data_type=false){
		if (!$item_type) $item_type="DataItem";
		if (!$data_type) $data_type=""; 
		parent::__construct($res,$type,$item_type,$data_type);
	}

	public function render(){
		if (!$this->init_flag){
			$this->init_flag=true;
			return "";
		}
		$res = $this->sql->select($this->request);
		return $this->render_set($res);
	}
}

class DistinctOptionsConnector extends OptionsConnector{

	public function render(){
		if (!$this->init_flag){
			$this->init_flag=true;
			return "";
		}
		$res = $this->sql->get_variants($this->config->text[0]["db_name"],$this->request);
		return $this->render_set($res);
	}
}

?>
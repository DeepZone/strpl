<?php
/****************************************************************************** 
* Streamplaner v 1.0                                                          *
* (c) 2014 by NoiSens Media - www.noisens.de                                  *
*                                                      data_connector.php     *
******************************************************************************/

require_once("base_connector.php");

class CommonDataProcessor extends DataProcessor{
	protected function get_post_values($ids){
		if (isset($_GET['action'])){
			$data = array();
			if (isset($_POST["id"])){
				$dataset = array();
				foreach($_POST as $key=>$value)
					$dataset[$key] = ConnectorSecurity::filter($value);

				$data[$_POST["id"]] = $dataset;
			}
			else
				$data["dummy_id"] = $_POST;
			return $data;
		}
		return parent::get_post_values($ids);
	}
	
	protected function get_ids(){
		if (isset($_GET['action'])){
			if (isset($_POST["id"]))
				return array($_POST['id']);
			else
				return array("dummy_id");
		}
		return parent::get_ids();
	}
	
	protected function get_operation($rid){
		if (isset($_GET['action']))
			return $_GET['action'];
		return parent::get_operation($rid);
	}
	
	public function output_as_xml($results){
		if (isset($_GET['action'])){
			LogMaster::log("Edit operation finished",$results);
			ob_clean();
			$type = $results[0]->get_status();
			if ($type == "error" || $type == "invalid"){
				echo "false";
			} else if ($type=="insert"){
				echo "true\n".$results[0]->get_new_id();
			} else 
				echo "true";
		} else
			return parent::output_as_xml($results);
	}
};


class CommonDataItem extends DataItem{

	function to_xml(){
		if ($this->skip) return "";
		return $this->to_xml_start().$this->to_xml_end();
	}

	function to_xml_start(){
		$str="<item id='".$this->get_id()."' ";
		for ($i=0; $i < sizeof($this->config->text); $i++){ 
			$name=$this->config->text[$i]["name"];
			$str.=" ".$name."='".$this->xmlentities($this->data[$name])."'";
		}

		if ($this->userdata !== false)
			foreach ($this->userdata as $key => $value)
				$str.=" ".$key."='".$this->xmlentities($value)."'";

		return $str.">";
	}
}



class DataConnector extends Connector{

	public function __construct($res,$type=false,$item_type=false,$data_type=false,$render_type=false){
		if (!$item_type) $item_type="CommonDataItem";
		if (!$data_type) $data_type="CommonDataProcessor";

		$this->sections = array();

		if (!$render_type) $render_type="RenderStrategy";
		parent::__construct($res,$type,$item_type,$data_type,$render_type);

	}

	protected $sections;
	public function add_section($name, $string){
		$this->sections[$name] = $string;
	}

	
	
	protected function parse_request(){
		if (isset($_GET['action'])){
			$action = $_GET['action'];
		
			if ($action == "get"){
				
				if (isset($_GET['id'])){
					
					$this->request->set_filter($this->config->id["name"],$_GET['id'],"=");
				} else {
					
				}
			} else {
				
				$this->editing = true;
			}
		} else {
			if (isset($_GET['editing']) && isset($_POST['ids']))
				$this->editing = true;			
			
			parent::parse_request();
		}
	
		if (isset($_GET["start"]) && isset($_GET["count"]))
			$this->request->set_limit($_GET["start"],$_GET["count"]);

	}


	protected function xml_start(){
		$start = parent::xml_start();

		foreach($this->sections as $k=>$v)
			$start .= "<".$k.">".$v."</".$k.">\n";
		return $start;
	}
};

class JSONDataConnector extends DataConnector{

	public function __construct($res,$type=false,$item_type=false,$data_type=false,$render_type=false){
		if (!$item_type) $item_type="JSONCommonDataItem";
		if (!$data_type) $data_type="CommonDataProcessor";
		if (!$render_type) $render_type="JSONRenderStrategy";
		$this->data_separator = ",\n";
		parent::__construct($res,$type,$item_type,$data_type,$render_type);
	}


	public function set_options($name,$options){
		if (is_array($options)){
			$str=array();
			foreach($options as $k => $v)
				$str[]='{"id":"'.$this->xmlentities($k).'", "value":"'.$this->xmlentities($v).'"}';
			$options=implode(",",$str);
		}
		$this->options[$name]=$options;
	}


	protected function fill_collections($list=""){
		$options = array();
		foreach ($this->options as $k=>$v) { 
			$name = $k;
			$option="\"{$name}\":[";
			if (!is_string($this->options[$name]))
				$option.=substr($this->options[$name]->render(),0,-2);
			else
				$option.=$this->options[$name];
			$option.="]";
			$options[] = $option;
		}
		$this->extra_output .= implode($this->data_separator, $options);
	}

	protected function resolve_parameter($name){
		if (intval($name).""==$name)
			return $this->config->text[intval($name)]["db_name"];
		return $name;
	}

	protected function output_as_xml($res){
		$json = $this->render_set($res);
		if ($this->simple) return $json;
		$result = json_encode($json);

		$this->fill_collections();
		$is_sections = sizeof($this->sections) && $this->is_first_call();
		if ($this->dload || $is_sections || sizeof($this->attributes) || !empty($this->extra_data)){

			$attributes = "";
			foreach($this->attributes as $k=>$v)
				$attributes .= ", \"".$k."\":\"".$v."\"";

			$extra = "";
			if (!empty($this->extra_output))
				$extra .= ', "collections": {'.$this->extra_output.'}';

			$sections = "";
			if ($is_sections){
				
				foreach($this->sections as $k=>$v)
					$sections .= ", \"".$k."\":".$v;
			}

			$dyn = "";
			if ($this->dload){
				
				if ($pos=$this->request->get_start())
					$dyn .= ", \"pos\":".$pos;
				else
					$dyn .= ", \"pos\":0, \"total_count\":".$this->sql->get_size($this->request);
			}
			if ($attributes || $sections || $this->extra_output || $dyn) {
				$result = "{ \"data\":".$result.$attributes.$extra.$sections.$dyn."}";
			}
		}

		
		if ($this->as_string) return $result;

		
		$out = new OutputWriter($result, "");
		$out->set_type("json");
		$this->event->trigger("beforeOutput", $this, $out);
		$out->output("", true, $this->encoding);
		return null;
	}
}

class JSONCommonDataItem extends DataItem{

	function to_xml(){
		if ($this->skip) return "";
		
		$data = array(
			'id' => $this->get_id()
		);
		for ($i=0; $i<sizeof($this->config->text); $i++){
			$extra = $this->config->text[$i]["name"];
			$data[$extra]=$this->data[$extra];
		}

		if ($this->userdata !== false)
			foreach ($this->userdata as $key => $value)
				$data[$key]=$value;

		return $data;
	}
}



class JSONOptionsConnector extends JSONDataConnector{
	protected $init_flag=false;
	public function __construct($res,$type=false,$item_type=false,$data_type=false){
		if (!$item_type) $item_type="JSONCommonDataItem";
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


class JSONDistinctOptionsConnector extends JSONOptionsConnector{
	
	public function render(){
		if (!$this->init_flag){
			$this->init_flag=true;
			return "";
		}
		$res = $this->sql->get_variants($this->config->text[0]["db_name"],$this->request);
		return $this->render_set($res);
	}
}



class TreeCommonDataItem extends CommonDataItem{
	protected $kids=-1;

	function to_xml_start(){
		$str="<item id='".$this->get_id()."' ";
		for ($i=0; $i < sizeof($this->config->text); $i++){ 
			$name=$this->config->text[$i]["name"];
			$str.=" ".$name."='".$this->xmlentities($this->data[$name])."'";
		}
		
		if ($this->userdata !== false)
			foreach ($this->userdata as $key => $value)
				$str.=" ".$key."='".$this->xmlentities($value)."'";

		if ($this->kids === true)
			$str .=" dhx_kids='1'";
		
		return $str.">";
	}

	function has_kids(){
		return $this->kids;
	}

	function set_kids($value){
		$this->kids=$value;
	}
}


class TreeDataConnector extends DataConnector{
	protected $parent_name = 'parent';

	
	public function __construct($res,$type=false,$item_type=false,$data_type=false,$render_type=false){
		if (!$item_type) $item_type="TreeCommonDataItem";
		if (!$data_type) $data_type="CommonDataProcessor";
		if (!$render_type) $render_type="TreeRenderStrategy";
		parent::__construct($res,$type,$item_type,$data_type,$render_type);
	}


	protected function parse_request(){
		parent::parse_request();

		if (isset($_GET[$this->parent_name]))
			$this->request->set_relation($_GET[$this->parent_name]);
		else
			$this->request->set_relation("0");

		$this->request->set_limit(0,0); 
	}


	protected function xml_start(){
		return "<data parent='".$this->request->get_relation()."'>";
	}	
}


class JSONTreeDataConnector extends TreeDataConnector{

	public function __construct($res,$type=false,$item_type=false,$data_type=false,$render_type=false){
		if (!$item_type) $item_type="JSONTreeCommonDataItem";
		if (!$data_type) $data_type="CommonDataProcessor";
		if (!$render_type) $render_type="JSONTreeRenderStrategy";
		parent::__construct($res,$type,$item_type,$data_type,$render_type);
	}

	protected function output_as_xml($res){
		$result = $this->render_set($res);
		if ($this->simple) return $result;

		$data = array();
		$data["parent"] = $this->request->get_relation();
		$data["data"] = $result;

        $this->fill_collections();
        if (!empty($this->options))
            $data["collections"] = $this->options;

		$data = json_encode($data);

		
		if ($this->as_string) return $data;

		
		$out = new OutputWriter($data, "");
		$out->set_type("json");
		$this->event->trigger("beforeOutput", $this, $out);
		$out->output("", true, $this->encoding);
	}


    public function set_options($name,$options){
        if (is_array($options)){
            $str=array();
            foreach($options as $k => $v)
                $str[]=Array("id"=>$this->xmlentities($k), "value"=>$this->xmlentities($v));//'{"id":"'.$this->xmlentities($k).'", "value":"'.$this->xmlentities($v).'"}';
            $options=$str;
        }
        $this->options[$name]=$options;
    }


    protected function fill_collections($list=""){
        $options = array();
        foreach ($this->options as $k=>$v) {
            $name = $k;
            if (!is_array($this->options[$name]))
                $option=$this->options[$name]->render();
            else
                $option=$this->options[$name];
            $options[$name] = $option;
        }
        $this->options = $options;
        $this->extra_output .= "'collections':".json_encode($options);
    }

}


class JSONTreeCommonDataItem extends TreeCommonDataItem{

	function to_xml_start(){
		if ($this->skip) return "";

		$data = array( "id" => $this->get_id() );
		for ($i=0; $i<sizeof($this->config->text); $i++){
			$extra = $this->config->text[$i]["name"];
			if (isset($this->data[$extra]))
				$data[$extra]=$this->data[$extra];
		}

		if ($this->userdata !== false)
			foreach ($this->userdata as $key => $value)
				$data[$key]=$value;

		if ($this->kids === true)
			$data["dhx_kids"] = 1;

		return $data;
	}

	function to_xml_end(){
		return "";
	}
}
?>
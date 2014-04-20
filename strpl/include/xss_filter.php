<?php

/****************************************************************************** 
* Streamplaner v 1.0                                                          *
* (c) 2014 by NoiSens Media - www.noisens.de                                  *
*                                                          xss_filter.php     *                  ******************************************************************************/

class dhx_externalinput_clean {

    static function basic($string, $filterIn = array("Tidy","Dom","Striptags"), $filterOut = "none") {
        $string = self::tidyUp($string, $filterIn);
        $string = str_replace(array("&amp;", "&lt;", "&gt;"), array("&amp;amp;", "&amp;lt;", "&amp;gt;"), $string);
        
        $string = preg_replace('#(&\#*\w+)[\x00-\x20]+;#u', "$1;", $string);
        $string = preg_replace('#(&\#x*)([0-9A-F]+);*#iu', "$1$2;", $string);

        $string = html_entity_decode($string, ENT_COMPAT, "UTF-8");
        
        $string = preg_replace('#(<[^>]+[\x00-\x20\"\'\/])(on|xmlns)[^>]*>#iUu', "$1>", $string);
        
        $string = preg_replace('#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iUu', '$1=$2nojavascript...', $string);
        $string = preg_replace('#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iUu', '$1=$2novbscript...', $string);
        $string = preg_replace('#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*-moz-binding[\x00-\x20]*:#Uu', '$1=$2nomozbinding...', $string);
        $string = preg_replace('#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*data[\x00-\x20]*:#Uu', '$1=$2nodata...', $string);
        

        $string = preg_replace('#(<[^>]+[\x00-\x20\"\'\/])style[^>]*>#iUu', "$1>", $string);

        $string = preg_replace('#</*\w+:\w[^>]*>#i', "", $string);
        
        do {
            $oldstring = $string;
            $string = preg_replace('#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>#i', "", $string);
        } while ($oldstring != $string);
        
        return self::tidyUp($string, $filterOut);
    }
    
    static function tidyUp($string, $filters) {
        if (is_array($filters)) {
            foreach ($filters as $filter) {
                $return = self::tidyUpWithFilter($string, $filter);
                if ($return !== false) {
                    return $return;
                }
            }
        } else {
            $return = self::tidyUpWithFilter($string, $filters);
        }
        if ($return === false) {
            return self::tidyUpModuleStriptags($string);
        } else {
            return $return;
        }
    }
    
    static private function tidyUpWithFilter($string, $filter) {
        if (is_callable(array("self", "tidyUpModule" . $filter))) {
            return call_user_func(array("self", "tidyUpModule" . $filter), $string);
        }
        return false;
    }
    
    static private function tidyUpModuleStriptags($string) {
        
        return strip_tags($string);
    }
    
    static private function tidyUpModuleNone($string) {
        return $string;
    }
    
    static private function tidyUpModuleDom($string) {
        $dom = new domdocument();
        @$dom->loadHTML("<html><body>" . $string . "</body></html>");
        $string = '';
        foreach ($dom->documentElement->firstChild->childNodes as $child) {
            $string .= $dom->saveXML($child);
        }
        return $string;
    }
    
    static private function tidyUpModuleTidy($string) {
        if (class_exists("tidy")) {
            $tidy = new tidy();
            $tidyOptions = array("output-xhtml" => true, 
                                 "show-body-only" => true, 
                                 "clean" => true, 
                                 "wrap" => "350", 
                                 "indent" => true, 
                                 "indent-spaces" => 1,
                                 "ascii-chars" => false, 
                                 "wrap-attributes" => false, 
                                 "alt-text" => "", 
                                 "doctype" => "loose", 
                                 "numeric-entities" => true, 
                                 "drop-proprietary-attributes" => true,
                                 "enclose-text" => false,
                                 "enclose-block-text" => false
 
            );
            $tidy->parseString($string, $tidyOptions, "utf8");
            $tidy->cleanRepair();
            return (string) $tidy;
        } else {
            return false;
        }
    }
}

define("DHX_SECURITY_SAFETEXT",  1);
define("DHX_SECURITY_SAFEHTML", 2);
define("DHX_SECURITY_TRUSTED", 3);

class ConnectorSecurity{
    static public $xss = DHX_SECURITY_SAFETEXT;
    static public $security_key = false;
    static public $security_var = "dhx_security";

    static private $filterClass = null;
    static function filter($value, $mode = false){
        if ($mode === false)
            $mode = ConnectorSecurity::$xss;

        if ($mode == DHX_SECURITY_TRUSTED)
            return $value;
        if ($mode == DHX_SECURITY_SAFETEXT)
            return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        if ($mode == DHX_SECURITY_SAFEHTML){
            if (ConnectorSecurity::$filterClass == null)
                ConnectorSecurity::$filterClass = new dhx_externalinput_clean();
            return ConnectorSecurity::$filterClass->basic($value);
        }
        throw new Error("Invalid security mode:"+$mode);
    }

    static function CSRF_detected(){
        LogMaster::log("[SECURITY] Possible CSRF attack detected", array(
            "referer" => $_SERVER["HTTP_REFERER"],
            "remote" => $_SERVER["REMOTE_ADDR"]
        ));
        LogMaster::log("Request data", $_POST);
        die();
    }
    static function checkCSRF($edit){
        if (ConnectorSecurity::$security_key){
            if (!isset($_SESSION)) 
                @session_start();

            if ($edit=== true){
                if (!isset($_POST[ConnectorSecurity::$security_var]))
                    return ConnectorSecurity::CSRF_detected();
                $master_key = $_SESSION[ConnectorSecurity::$security_var];
                $update_key = $_POST[ConnectorSecurity::$security_var];
                if ($master_key != $update_key)
                    return ConnectorSecurity::CSRF_detected();

                return "";
            }
            if (!array_key_exists(ConnectorSecurity::$security_var,$_SESSION)){
                $_SESSION[ConnectorSecurity::$security_var] = md5(uniqid());
            }

            return $_SESSION[ConnectorSecurity::$security_var];
        }

        return "";
    }

}
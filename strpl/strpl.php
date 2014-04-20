<?php
/****************************************************************************** 
* Streamplaner v 1.0                                                          *
* (c) 2014 by NoiSens Media - www.noisens.de                                  *
*                                                                             *
******************************************************************************/


	include ('include/config.php');
	include ('include/scheduler_connector.php');

    $res=mysql_connect($mysql_server,$mysql_user,$mysql_pass); 

    mysql_select_db($mysql_db); 

	$scheduler = new schedulerConnector($res);
	//$scheduler->enable_log("log.txt",true);
	$scheduler->render_table("events","event_id","start_date,end_date,event_name,details");
?>
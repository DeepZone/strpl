<?php
/****************************************************************************** 
* Streamplaner v 1.0                                                          *
* (c) 2014 by NoiSens Media - www.noisens.de                                  *
*                                                                             *
*                                                  DB Konfiguration           *
******************************************************************************/


// Datenbankkonfiguration
$mysql_server = getenv('MYSQL_SERVER') ?: 'localhost';
$mysql_user   = getenv('MYSQL_USER')   ?: 'root';
$mysql_pass   = getenv('MYSQL_PASS')   ?: '';
$mysql_db     = getenv('MYSQL_DB')     ?: 'strpl';

?>
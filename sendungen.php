<?php
/****************************************************************************** 
* Streamplaner v 1.0                                                          *
* (c) 2014 by NoiSens Media - www.noisens.de                                  *
* Datei für den öffentlichen Bereich zum Einbinden in die Internetseite       *
******************************************************************************/


//**************************
// NICHT Ändern            *
//**************************


require __DIR__ . '/strpl/include/config.php';

$mysqli = new mysqli($mysql_server, $mysql_user, $mysql_pass, $mysql_db);
if ($mysqli->connect_errno) {
    die('Die Datenbank konnte nicht erreicht werden!');
}

$sql = "SELECT * FROM `events` WHERE start_date >= NOW()";
$abfrageergebnis = $mysqli->query($sql);
$anzahl = $abfrageergebnis ? $abfrageergebnis->num_rows : 0;

?>

<table width="878" cellspacing="0" class="tablesorter"> 
			<thead> 
				<tr> 
   				<th width="120" style="text-align: left">Datum</th> 
                    <th width="263" style="text-align: left">Sendung</th> 	
    				<th width="67" style="text-align: left">Von</th> 
    				<th width="70" style="text-align: left">Bis</th> 
    				 <th width="346" style="text-align: left">Was</th> 
    				
				</tr> 
			</thead> 

<?php while ($datensatz = $abfrageergebnis && ($datensatz = $abfrageergebnis->fetch_assoc())) {

$start = $datensatz['start_date'];
$end = $datensatz['end_date'];

$zeitvon = date("H:i",strtotime($start));
$zeitbis = date("H:i",strtotime($end));
$datum = date("d.m.Y",strtotime($start));
?>

  			<tbody> 
				<tr> 
                    <td><?php echo $datum; ?></td>
                        <td><?php echo htmlspecialchars($datensatz['event_name']); ?></td>
                        <td><?php echo $zeitvon; ?></td>
                                <td><?php echo $zeitbis; ?></td>
                    <td><?php echo htmlspecialchars($datensatz['details']); ?></td>
               </tr> <?php } $mysqli->close(); ?>
           </tbody>
</table>
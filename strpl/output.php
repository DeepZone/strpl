<?
include "../includes/db.read.inc.php";

$conID = mysql_connect( $host, $user, $pass ) or die( "Die Datenbank konnte nicht erreicht werden!" );
if ($conID)
   {
	   mysql_select_db( $db, $conID );
      }


$sql = "SELECT * FROM `events` WHERE start_date >= NOW()";
$abfrageergebnis = mysql_query( $sql, $conID );
$anzahl = mysql_num_rows( $abfrageergebnis );





echo "<p>Es sind <strong>" .$anzahl. "</strong> Sendungen im Plan! ";
?>


<a href="javascript:E1397942029();">Im Streamplan eintragen.</a><br><br></p>

<table width="878" cellspacing="0" class="tablesorter"> 
			<thead> 
				<tr> 
   				<th width="120" style="text-align: left">Datum</th> 
                    <th width="263" style="text-align: left">Sendung</th> 	
    				<th width="67" style="text-align: left">Von</th> 
    				<th width="70" style="text-align: left">Bis</th> 
    			<!--	 <th width="346" style="text-align: left">Was</th> -->
    				
				</tr> 
			</thead> 
<?php while ($datensatz = mysql_fetch_array( $abfrageergebnis )) { 


$start = $datensatz['start_date'];
$end = $datensatz['end_date'];




$zeitvon = date("H:i",strtotime($start));
$zeitbis = date("H:i",strtotime($end));
$datum = date("d.m.Y",strtotime($start));

?>
  			<tbody> 
				<tr> 
                    <td><? echo "$datum"; ?></td>
   		 <?php echo "<td>" .$datensatz['event_name']. "</td>"; ?>	
    	    		<td><? echo "$zeitvon"; ?></td> 
    				<td><? echo "$zeitbis"; ?></td> 
          <?php // echo "<td>" .$datensatz['details']. "</td>"; ?> 
   

                 </tr>
 <?php } ?>
 </tbody>
 </table>
<?php mysql_close($conID); ?>

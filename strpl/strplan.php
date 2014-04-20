<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><head>
	<meta http-equiv="Content-type" content="text/html; charset=utf-8">
	<title>Streamplaner</title>
</head>


<script src="js/strpl.js" type="text/javascript" charset="utf-8"></script>
<link rel="stylesheet" href="style.css" type="text/css" title="no title" charset="utf-8">


<style type="text/css" media="screen">
	html, body{
		margin:0px;
		padding:0px;
		height:100%;
		overflow:hidden;
	}	
</style>


<script type="text/javascript" charset="utf-8">
	function init() {
		scheduler.config.xml_date="%Y-%m-%d %H:%i";
        scheduler.config.prevent_cache = true;
		scheduler.config.lightbox.sections=[	
			{name:"description", height:43, map_to:"text", type:"textarea" , focus:true},
			{name:"location", height:140, type:"textarea", map_to:"details" },
			{name:"time", height:72, type:"time", map_to:"auto"}
		]
		scheduler.config.first_hour=10;
		scheduler.locale.labels.section_location="Sendungsbeschreibung";
		scheduler.config.details_on_create=true;
		scheduler.config.details_on_dblclick=true;
		scheduler.init('scheduler_here',new Date(),"week");
        scheduler.setLoadMode("day");
		scheduler.load("strpl.php?uid="+scheduler.uid());
		var dp = new dataProcessor("strpl.php");
		dp.init(scheduler);
	}
</script>



<body onload="init();">
	<div id="scheduler_here" class="dhx_cal_container" style='width:100%; height:100%;'>
		<div class="dhx_cal_navline">
			<div class="dhx_cal_prev_button">&nbsp;</div>
			<div class="dhx_cal_next_button">&nbsp;</div>
			<div class="dhx_cal_today_button"></div>
			<div class="dhx_cal_date"></div>
			<div class="dhx_cal_tab" name="day_tab" style="right:204px;"></div>
			<div class="dhx_cal_tab" name="week_tab" style="right:140px;"></div>
			<div class="dhx_cal_tab" name="month_tab" style="right:76px;"></div>
		</div>
		<div class="dhx_cal_header">
		</div>
		<div class="dhx_cal_data">
		</div>		
	</div>

</body>
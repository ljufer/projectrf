<?php
include('../../main/config.php');
include("../../pChart/class/pData.class.php");
include("../../pChart/class/pDraw.class.php");
include("../../pChart/class/pImage.class.php");
require_once( 'DB.php' );

$db = DB::connect( "mysql://$dbuser:$dbpass@$dbhost/$dbname" );
ifError($db);
$agency = $_GET["agency"];
$report_name = $_GET["report_name"];
$scan_start = $_GET["scan_start"];
$scan_end = $_GET["scan_end"]; 
$byVuln = $_GET["byVuln"];

$os_sql = "SELECT DISTINCT
			nessus_tags.operating_system
		  FROM
			nessus_results
		  INNER JOIN nessus_tags ON nessus_results.tagID = nessus_tags.tagID
		  INNER JOIN nessus_tmp_family ON nessus_results.pluginFamily = nessus_tmp_family.pluginFamily
		  INNER JOIN nessus_tmp_hosts ON nessus_results.host_name = nessus_tmp_hosts.host_name
		  WHERE
			nessus_results.agency = '$agency' AND 
			nessus_results.report_name = '$report_name' AND
			nessus_results.scan_start = '$scan_start' AND
			nessus_results.scan_end = '$scan_end'
		  ";
$os_result = $db->query($os_sql);
ifError($os_result);
while ($os_row = $os_result->fetchRow(DB_FETCHMODE_ASSOC)){
	$operating_system = $os_row["operating_system"];
	$exec_os[$operating_system] = array(critical => "0", high => "0", medium => "0", low => "0", info => "0");
}
$sql = "SELECT 
			nessus_tags.operating_system,
			nessus_results.severity,
			nessus_results.cveList
		  FROM
			nessus_results
		  INNER JOIN nessus_tags ON nessus_results.tagID = nessus_tags.tagID
		  INNER JOIN nessus_tmp_family ON nessus_results.pluginFamily = nessus_tmp_family.pluginFamily
		  INNER JOIN nessus_tmp_hosts ON nessus_results.host_name = nessus_tmp_hosts.host_name
		  WHERE
			nessus_results.agency = '$agency' AND 
			nessus_results.report_name = '$report_name' AND
			nessus_results.scan_start = '$scan_start' AND
			nessus_results.scan_end = '$scan_end'
		  ";
$result = $db->query($sql);
ifError($result);

while($row = $result->fetchRow(DB_FETCHMODE_ASSOC)){
	$severity = $row["severity"];
	$operating_system = $row["operating_system"];
	$cveList = explode(",", $row["cveList"]);
	$cveCount = count($cveList) - 1;
	if($byVuln == "plugin"){
		switch ($severity) {
			case "4":
				$exec_os[$operating_system]["critical"]++;
				break;
			case "3":
				$exec_os[$operating_system]["high"]++;
				break;
			case "2":
				$exec_os[$operating_system]["medium"]++;
				break;
			case "1":
				$exec_os[$operating_system]["low"]++;
				break;
			case "0":
				$exec_os[$operating_system]["info"]++;
				break;
		}
	}
	if($byVuln=="cve"){
		switch ($severity) {
			case "4":
				$exec_os[$operating_system]["critical"]=$exec_os[$operating_system]["critical"]+$cveCount;
				break;
			case "3":
				$exec_os[$operating_system]["high"]=$exec_os[$operating_system]["high"]+$cveCount;
				break;
			case "2":
				$exec_os[$operating_system]["medium"]=$exec_os[$operating_system]["medium"]+$cveCount;
				break;
			case "1":
				$exec_os[$operating_system]["low"]=$exec_os[$operating_system]["low"]+$cveCount;
				break;
			case "0":
				$exec_os[$operating_system]["info"]=$exec_os[$operating_system]["info"]+$cveCount;
				break;
		}
	}
}
uasort($exec_os, 'sortByHigh');

$highArray = array();
$mediumArray = array();
$lowArray = array();
$osArray = array();
$patterns = array();
$patterns[0] = '/Microsoft/i';
$patterns[1] = '/Service Pack/i';
$patterns[2] = '/Windows/i';
$patterns[3] = '/Linux/i';
$patterns[4] = '/Enterprise/i';
$patterns[5] = '/Standard/i';
$patterns[6] = '/2003/i';
$patterns[7] = '/2008/i';
$patterns[8] = '/\(English\)/i';
$patterns[9] = '/\([a-zA-Z]+\)/';
$replacements = array();
$replacements[0] = 'MS';
$replacements[1] = 'SP';
$replacements[2] = 'Win';
$replacements[3] = 'Lnx';
$replacements[4] = 'Ent';
$replacements[5] = 'Std';
$replacements[6] = '03';
$replacements[7] = '08';
$replacements[8] = '';
$replacements[9] = '';
foreach ($exec_os as $key1 => $value1){
	$criticalArray[] = $value1["critical"];
	$highArray[] = $value1["high"];
	$mediumArray[] = $value1["medium"];
	$lowArray[] = $value1["low"];
	$key1 = preg_replace($patterns, $replacements, $key1);
	$osArray[] = $key1;
}
//only keeping top 3 vulnerable OSes
$cA = array_slice($criticalArray,0,3);
$hA = array_slice($highArray,0,3);
$mA = array_slice($mediumArray,0,3);
$lA = array_slice($lowArray,0,3);
$osA = array_slice($osArray,0,3);

$myData = new pData();
$myData->loadPalette("../../pChart/palettes/nessus.color",TRUE);
$myData->addPoints($cA,"Serie1");
$myData->setSerieDescription("Serie1","Critical");
$myData->setSerieOnAxis("Serie1",0);

$myData->addPoints($hA,"Serie2");
$myData->setSerieDescription("Serie2","High");
$myData->setSerieOnAxis("Serie2",0);

$myData->addPoints($mA,"Serie3");
$myData->setSerieDescription("Serie3","Medium");
$myData->setSerieOnAxis("Serie3",0);

$myData->addPoints($lA,"Serie4");
$myData->setSerieDescription("Serie4","Low");
$myData->setSerieOnAxis("Serie4",0);

$myData->addPoints($osA,"Absissa");
$myData->setAbscissa("Absissa");

$myData->setAxisPosition(0,AXIS_POSITION_LEFT);
$myData->setAxisName(0,"# Vulnerabilities");
$myData->setAxisUnit(0,"");

$myPicture = new pImage(800,250,$myData); 
$Settings = array("R"=>220, "G"=>220, "B"=>220);
$myPicture->drawFilledRectangle(0,0,800,250,$Settings);

$myPicture->drawRectangle(0,0,799,249,array("R"=>162,"G"=>181,"B"=>205));

$myPicture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>50,"G"=>50,"B"=>50,"Alpha"=>20));

$myPicture->setFontProperties(array("FontName"=>"../../pChart/fonts/Forgotte.ttf","FontSize"=>14));
$TextSettings = array("Align"=>TEXT_ALIGN_MIDDLEMIDDLE
, "R"=>0, "G"=>0, "B"=>0);
$myPicture->drawText(350,25,"Vulnerability Count by OS Distribution",$TextSettings);

$myPicture->setShadow(FALSE);
$myPicture->setGraphArea(50,50,775,210);
$myPicture->setFontProperties(array("R"=>0,"G"=>0,"B"=>0,"FontName"=>"../../pChart/fonts/pf_arma_five.ttf","FontSize"=>6));

$Settings = array("Pos"=>SCALE_POS_LEFTRIGHT
, "Mode"=>SCALE_MODE_START0
, "LabelingMethod"=>LABELING_ALL
, "GridR"=>0, "GridG"=>0, "GridB"=>0, "GridAlpha"=>50, "TickR"=>0, "TickG"=>0, "TickB"=>0, "TickAlpha"=>50, "LabelRotation"=>0, "ScaleSpacing"=>50, "CycleBackground"=>1, "DrawXLines"=>1, "DrawSubTicks"=>1, "SubTickR"=>255, "SubTickG"=>0, "SubTickB"=>0, "SubTickAlpha"=>50, "DrawYLines"=>ALL);
$myPicture->drawScale($Settings);

$myPicture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>50,"G"=>50,"B"=>50,"Alpha"=>10));

$Config = array("DisplayValues"=>1, "AroundZero"=>1);
$myPicture->drawBarChart($Config);

$Config = array("FontR"=>0, "FontG"=>0, "FontB"=>0, "FontName"=>"../../pChart/fonts/pf_arma_five.ttf", "FontSize"=>6, "Margin"=>6, "Alpha"=>30, "BoxSize"=>5, "Style"=>LEGEND_NOBORDER
, "Mode"=>LEGEND_HORIZONTAL
);
$myPicture->drawLegend(573,16,$Config);

$myPicture->stroke();


function sortByHigh($a, $b) { 
	return strnatcmp($b['critical'], $a['critical']); 
} // sort alphabetically by name 

function ifError($error)
{
	if (PEAR::isError($error)) {
		echo 'Standard Message: ' . $error->getMessage() . "</br>";
		echo 'Standard Code: ' . $error->getCode() . "</br>";
		echo 'DBMS/User Message: ' . $error->getUserInfo() . "</br>";
		echo 'DBMS/Debug Message: ' . $error->getDebugInfo() . "</br>";
		exit;
	}
}
?>
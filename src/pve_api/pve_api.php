<?php
$currently_busy   = 0;

# Convert Object to Array
function convert_object_to_array($data) {
    if (is_object($data)) {
        $data = get_object_vars($data);
    }
    if (is_array($data)) {
        return array_map(__FUNCTION__, $data);
    }
    else {
        return $data;
    }
};

    function startbusy($msg) {
    
      global $currently_busy;

      # Allow for a repeated call; Do nothing.
      if ($currently_busy)
        return;

      echo "<div id='outer_loaddiv'>\n";
      echo "<center><div id='inner_loaddiv'>\n";
      echo "<b>$msg</b> ...<br>\n";
      echo "This will take a few moments; please be <em>patient</em>.<br>\n";
      echo "</div>\n";
      echo "<iframe id='busy' src='https://giphy.com/embed/KG4PMQ0jyimywxNt8i' width='480' height='480' frameBorder='0'></iframe>" .
           "<span id='loading'> Working ...</span>";
      echo "<br><br>\n";
      echo "</center>\n";
      echo "</div>\n";
      flush();
      $currently_busy = 1;
   } 


   function stopbusy() {
   
      global $currently_busy;

      if (!$currently_busy)
        return;

      echo "<script type='text/javascript' language='javascript'>\n";
      echo "ClearBusyIndicators('<center><b>Done!</b></center>');\n";
      echo "</script>\n";
      flush();
      $currently_busy = 0;
      sleep(1);
    }



?>
<?php

# PVE API Login
function api_pve_login($pve_server,$pve_port,$pve_user,$pve_pass)
{	
# Curl Start
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://$pve_server:$pve_port/api2/json/access/ticket");
curl_setopt($ch, CURLOPT_POSTFIELDS, "username=$pve_user&password=$pve_pass");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
#curl_setopt($ch, CURLINFO_HEADER_OUT, true);
$ch_output = curl_exec($ch);
# DEBUG
$debug = "0"; # 0 = No DEBUG / 1 = Yes DEBUG
if($debug == "0") 
{
# No DEBUG!
} 
else 
{
$curlInfo = curl_getinfo($ch);
$reasonPhrase = curl_error($ch);
echo "<b>Header Code<br></b>";
echo "<pre>";
print_r($curlInfo);
echo "</pre>";
};
# DEBUG Ende
curl_close($ch);
# Output Array
$array_api = ( convert_object_to_array( json_decode( $ch_output ) ) );
return $array_api;
}
?>

<?php
# PVE API
function api_pve_con($pve_server,$pve_port,$pve_api,$typ,$params,$pve_ticket,$pve_CSRFPreventionToke) # $Typ = POST or GET
{		
# Curl Start	
$headers = ["CSRFPreventionToken: $pve_CSRFPreventionToke"];
$ch = curl_init();

switch ($typ) {
case "GET":
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
$action_postfields_string = "?". http_build_query($params);
break;
		
case "PUT":
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
$action_postfields_string = http_build_query($params);
curl_setopt($ch, CURLOPT_POSTFIELDS, $action_postfields_string);
$action_postfields_string = http_build_query([]);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
break;

case "POST":
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
$action_postfields_string = http_build_query($params);
curl_setopt($ch, CURLOPT_POSTFIELDS, $action_postfields_string);
$action_postfields_string = http_build_query([]);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
break;

case "DELETE":
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
break;
}

curl_setopt($ch, CURLOPT_URL, "https://$pve_server:$pve_port/$pve_api".$action_postfields_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, "PVEAuthCookie=$pve_ticket");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	

$ch_output = curl_exec($ch);
# DEBUG
$debug = "0"; # 0 = No DEBUG / 1 = Yes DEBUG
if($debug == "0") 
{
# No DEBUG!
} 
else 
{
$curlInfo = curl_getinfo($ch);
$reasonPhrase = curl_error($ch);
echo "<b>Header Code<br></b>";
echo "<pre>";
print_r($curlInfo);
echo "</pre>";
};
# DEBUG Ende
curl_close($ch);
# Output Array
$array_api = ( convert_object_to_array( json_decode( $ch_output ) ) );
return $array_api;
};
?>

<?  
/* 
Package: busAlert.me 
File: busAlert.php 
(c) 2011 Brooke Dukes All Rights Reserved

This is the main file of busAlert.me it retiveves the SMS message and inserts the data into the database. 
You must edit config.php before this file will work
*/

// DO NOT EDIT THIS FILE UNLESS YOU KNOW WHAT YOU ARE DOING  //

require("busAlert_config.php");

//get the incoming text message
$sms_from = $_REQUEST['From'];
$sms_body = $_REQUEST['Body'];
$sms_id = $_REQUEST['SmsSid'];

//break apart the text message. Should be in the format STOP_NUMBER(req) ROUTE_NUMBER(req) ALERT_TIME, TYPE
$sms_body_params = explode(" ",$sms_body);
//stop number from SMS (REQUIRED)
$stop_number = $sms_body_params[0]; 
//route number from SMS (REQUIRED)
$route_number = $sms_body_params[1];
//alert time from SMS (OPTIONAL NUMERIC deault:5 min)
$alert_time = ((!isset($sms_body_params[2]) || !is_numeric($sms_body_params[2])) ? $alert_time : $sms_body_params[2]); 
//alert type from SMS (OPTIONAL TXT(0) CALL(1) deault:TXT(0))
$alert_type = ((isset($sms_body_params[3]) && strtolower($sms_body_params[3])=="txt") ? $alert_type : ((isset($sms_body_params[3]) && strtolower($sms_body_params[3]) =="call")? "1": "0")); 
 //see if they want to be notified or buses now or after alert time (OPTIONAL default now)
$alert_after_time = ((isset($sms_body_params[4]) && (strtolower($sms_body_params[4]) =="now")) ? time() : ((isset($sms_body_params[4]) && strtolower($sms_body_params[4]) =="next") ? time() + $alert_time*60  : time()));  

//get the onebus route data from the onebus api
$jsonurl = "http://api.onebusaway.org/api/where/arrivals-and-departures-for-stop/1_$stop_number.json?key=$onebus_api&version=2";
$json = file_get_contents($jsonurl,0,null,null);
$json_output = json_decode($json,true);

//return an array of ids for the stop number
$json_route_id_array= $json_output[data][references][stops][0][routeIds];

//get a list of route ID for that stop 
$route_id = array();
if(is_array($json_route_id_array)){
		foreach($json_route_id_array as $key => $value){
			list($v,$k) = explode('_',$value);
			$route_id[$k] = $v;
			echo($route_id[$route_number]);
		}
}
//check for errors
$errmsg_arr = array();

//if stop number is empty or not numeric 
if((!is_numeric($stop_number)) || (empty($stop_number))){
	$errmsg_arr[] = 'Invalid stop number';
	$errflag = true;	
	}
//if route number is empty or not numeric 
if((!is_numeric($route_number)) || (empty($route_number))) {
	$errmsg_arr[] = 'Invalid route number';
	$errflag = true;
	}
//if that route dosen't serivice that stop
if(!isset($route_id[$route_number])){
	$errmsg_arr[] = 'Invaild route for stop number';
	$errflag = true;
	}
if(!$errflag) { //if we already have an error we don't need to check the database for a dupicate record
//check to see if the user has already stored this route and stop
	$qry = "SELECT count(*) AS c FROM `" .$table_name."` WHERE `sms_from`='$sms_from' AND `stop_number`='$stop_number' AND `route_number`='$route_number' AND `alert_status`='0'";
	$result = mysql_query($qry);
	if($result) {
		$result_array = mysql_fetch_assoc($result);
		if($result_array['c'] > 0) {
			$errmsg_arr[] = 'You have already requested to be alerted for this route';
			$errflag = true;
		}
	@mysql_free_result($result);
	}
	else {
		die("Query failed");
	}
}


//tell the user their was an error
if(($errflag) && is_array($errmsg_arr) && count($errmsg_arr) > 0 ) {
	$before_msg = 'BusAlert.Me error: ';
	
	foreach($errmsg_arr as $errmsg) {
		$errmsg =  implode(', ', $errmsg_arr);
	}
	$msg_to_send = $before_msg.$errmsg;
	if(!empty($sms_from)){
	$response = $client->request("/$twilio_ApiVersion/Accounts/$twilio_Sid/SMS/Messages",
		"POST", array(
			"To" => $sms_from,
			"From" => $twilio_number,
			"Body" => "$msg_to_send"
		));
	if($response->IsError)
		
            echo "Error: {$response->ErrorMessage}";
        }	
    exit;
}

//insert the data into the database
$qry = "INSERT INTO ".$table_name." (`twil_id`, `sms_from`,`stop_number`,`route_number`,`alert_time`,`alert_after_time`,`alert_type`,`date`) VALUES('$sms_id','$sms_from','$stop_number','$route_number','$alert_time','$alert_after_time','$alert_type',DATE_SUB(NOW(), INTERVAL 2 HOUR))";
$result = mysql_query($qry) or die(mysql_error());
mysql_close($conn);

?>

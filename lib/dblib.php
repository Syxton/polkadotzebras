<?php
/***************************************************************************
* dblib.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 1.7.6
***************************************************************************/

if(!isset($LIBHEADER)){ include ('header.php'); }
$DBLIB = true;


function reconnect(){
global $CFG;
    if ($CFG->dbtype == "mysqli" && function_exists('mysqli_connect')) {
        //mysqli is installed
        $CFG->dbtype = "mysqli";        
        $conn = mysqli_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
        mysqli_select_db($conn, $CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . mysqli_errno($conn) . ") " . mysqli_error($conn));
    }else{
        $CFG->dbtype = "mysql";        
        $conn = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
        mysql_select_db($CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . mysql_errno() . ") " . mysql_error());   
    } 
return $conn;
}

$conn = reconnect();

if($CFG->dbtype == "mysqli"){
    require('dblib_mysqli.php');
}else{
    require('dblib_mysql.php');
}

function get_db_row($SQL, $type = false){
global $CFG;
    $type = get_mysql_array_type($type);
	if($result = get_db_result($SQL)){
		return fetch_row($result, $type);
	}
    return false;
}

function get_db_field($field, $from, $where) {
global $CFG;
	$SQL = "SELECT $field FROM $from WHERE $where LIMIT 1";
    
	if($result = get_db_result($SQL)){
		$row = fetch_row($result);
		return $row[$field];
	}
	return false;
}

function authenticate($username, $password) {
global $CFG, $USER;
	$time = get_timestamp();
	
	//SQL Creation
	$SQL = "SELECT * FROM users WHERE email='$username' AND password='".md5($password)."'";

	if (!$user = get_db_row($SQL)) {
		//Password recovery
		if($user = get_db_row("SELECT * FROM users WHERE email='$username' AND alternate='$password'")){
			$ip = $_SERVER['REMOTE_ADDR'];
            $_SESSION['userid'] = $user['userid'];
			execute_db_sql("UPDATE users SET ip='$ip', last_activity='$time' WHERE userid='" . $user['userid'] . "'");
			return $user;
		}else{
			//Log
			log_entry("user", $username, "Failed Login");
			return false;
		}
	} else {
		//First login switch temp password for actual password
		if (!empty($user['temp']) && strlen($user['temp']) > 0) {
			execute_db_sql("UPDATE users SET password='" . $user['temp'] . "', temp='' WHERE userid='" . $user['userid'] . "'");
            //Email new password to the email address.
            $USER = new stdClass();
    		$USER->userid = $user['userid'];
    		$USER->fname = $user['fname'];
    		$USER->lname = $user['lname'];
    		$USER->email = $user['email'];
            $FROMUSER = new stdClass();
    		$FROMUSER->fname = $CFG->sitename;
    		$FROMUSER->lname = '';
    		$FROMUSER->email = $CFG->siteemail;
    		$message = '
    			<p><font face="Tahoma"><font size="3" color="#993366">Dear <strong>' . $user['fname'] . ' ' . $user['lname'] . '</strong>,</font><br />
    			</font></p>
    			<blockquote>
    			<p><font size="3" face="Tahoma"><strong>' . $CFG->sitename . '</strong> has recieved notification that you have activated your account.&nbsp; The temporary password you used to log in has now been replaced with the original password you used when you signed up.</font></p>
    			</blockquote>
    			<p>&nbsp;</p>
    			<p><font face="Tahoma"><strong><font size="3" color="#666699">Enjoy the site,</font></strong></font></p>
    			<p><font size="3" face="Tahoma"><em>' . $CFG->siteowner . ' </em></font><font size="3" face="Tahoma" color="#ff0000">&lt;' . $CFG->siteemail . '</font><font face="Tahoma"><font size="3" color="#ff0000">&gt;</font></font></p>
    			<p>&nbsp;</p>';
                $subject = $CFG->sitename . ' Account Activation';
                send_email($USER,$FROMUSER,false,$subject, $message);
                send_email($FROMUSER,$FROMUSER,false,$subject, $message);
		}
        
        //Set first activity time
        if(!$user["first_activity"]){
            execute_db_sql("UPDATE users SET first_activity=".$time." WHERE userid='" . $user['userid'] . "'");
        }
        
		$ip = $_SERVER['REMOTE_ADDR'];
        $_SESSION['userid'] = $user['userid'];
		execute_db_sql("UPDATE users SET ip='$ip', last_activity='$time', alternate='' WHERE userid='" . $user['userid'] . "'");

		//Log
		log_entry("user", $user['userid'], "Login");
		return $user;
	}
}

function key_login($key){
global $CFG, $USER;
	if($userfound = get_db_row("SELECT * FROM users WHERE userkey='$key'")){
		$time = get_timestamp();
		$USER->userid = $userfound['userid'];
        $_SESSION['userid'] = $userfound['userid'];
		log_entry("user", $userfound['userid'], "Login");
	}	
}

function copy_db_row($row, $table, $variablechanges){
global $USER, $CFG, $MYVARS;
	$paired = explode(",", $variablechanges);
	$newkey = $newvalue = array();
	$keylist = $valuelist = "";
    $i=0;
	while(isset($paired[$i])){
		$split = explode("=", $paired[$i]);
		$newkey[$i] = $split[0];
		$newvalue[$i] = $split[1];
		$i++;
	}

	$keys = array_keys($row);
    foreach($keys as $key){
		$found = array_search($key, $newkey);
		$keylist .= $keylist == "" ? $key : "," . $key;
		if($found === false){
			$valuelist .= $valuelist == "" ? "'" . $row[$key] . "'" : ",'" . $row[$key] . "'";
		}else{
			$valuelist .= $valuelist == "" ? "'" . $newvalue[$found] . "'" : ",'" . $newvalue[$found] . "'";
		}        
    }
	$SQL = "INSERT INTO $table ($keylist) VALUES($valuelist)";
	return execute_db_sql($SQL);
}

function is_unique($table, $where){
	if(get_db_count("SELECT * FROM $table WHERE $where")){ return true; }
	return false;
}

function even($var){
	return (!($var & 1));
}

function senderror($message){
    $message=preg_replace(array("\r,\t,\n"),"",$message);
    error_log($message);
    die($message);    
}

function log_entry($feature = null, $info = null, $description = null, $debug = null){
global $CFG, $USER, $PAGE;
	$timeline = get_timestamp();
	$ip = $_SERVER['REMOTE_ADDR'];
	$userid = is_logged_in() ? $USER->userid : 0;

	if(!$userid && $description == "Login"){
		$userid = $info;
		$info = null;
	}
	//$SQL = "INSERT INTO logfile (userid,ip,pageid,timeline,feature,info,description,debug) VALUES($userid,'$ip',$pageid,$timeline,'".addslashes($feature)."','".addslashes($info)."','".addslashes($description)."','".addslashes($debug)."')";
	//execute_db_sql($SQL);
}

?>
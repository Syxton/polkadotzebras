<?php

unset($CFG);

$CFG = new stdClass();

//Website info
$CFG->sitename 	= 'Company Name';
$CFG->siteemail = 'test@email.com';
$CFG->sitefooter = '1234 My Address';
$CFG->logo 	= 'logo.png';

$CFG->active = true;

//Sandbox
$CFG->paypallink = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
//$CFG->paypallink = 'https://www.paypal.com/cgi-bin/webscr';
$CFG->paypal_merchant_account = 'test@email.com';
$CFG->paypal_auth = '';
$CFG->paypal_user = 'test@email.com';
$CFG->paypal_pass = 'password';
$CFG->paypal_sig = 'longuglystring';
$CFG->shippingperitem = '1.00';

//Database connection variables
$CFG->dbtype    = 'mysql';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'mydbname';
$CFG->dbuser    = 'mydbuser';
$CFG->dbpass    = 'mydbpassword';

//Random 64 character salt
$CFG->salt    = '';

//Directory variables
$CFG->directory = 'website/folder';
$CFG->wwwroot   = $CFG->directory ? 'http://localhost/'.$CFG->directory : 'http://localhost';
$CFG->docroot   = dirname(__FILE__);
$CFG->dirroot   = $CFG->docroot;

//Userfile path
$CFG->userfilespath = substr($CFG->docroot,0,strrpos($CFG->docroot,'/'));

//Cookie variables in seconds
$CFG->timezone = 'America/New_York';
date_default_timezone_set($CFG->timezone);
?>
<?php

unset($CFG);

$CFG = new stdClass();

//Website info
$CFG->sitename 	= '';
$CFG->siteemail = '';
$CFG->sitefooter = '';
$CFG->logo 	= 'logo.png';

$CFG->active = true;

//Sandbox
$CFG->paypallink = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
//$CFG->paypallink = 'https://www.paypal.com/cgi-bin/webscr';
$CFG->paypal_merchant_account = '';
$CFG->paypal_auth = '';
$CFG->paypal_user = '';
$CFG->paypal_pass = '';
$CFG->paypal_sig = '';
$CFG->shippingperitem = '1.00';

//Database connection variables
$CFG->dbtype    = 'mysqli'; //mysql or mysqli
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'polkadotzebras';
$CFG->dbuser    = 'root';
$CFG->dbpass    = '';

//Random 64 character salt
$CFG->salt    = '';

//Directory variables
$CFG->directory = '';
$CFG->wwwroot   = $CFG->directory ? 'http://localhost/'.$CFG->directory : 'http://localhost';
$CFG->docroot   = dirname(__FILE__);
$CFG->dirroot   = $CFG->docroot;

//Userfile path
$CFG->userfilespath = substr($CFG->docroot,0,strrpos($CFG->docroot,'/'));

//Cookie variables in seconds
$CFG->timezone = 'America/Indianapolis';
date_default_timezone_set($CFG->timezone);
?>
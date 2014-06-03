<?php
if(!isset($CFG)){ include_once('../config.php'); }
include($CFG->dirroot.'/lib/header.php');

// STEP 1: Read POST data
 
// reading posted data from directly from $_POST causes serialization 
// issues with array data in POST
// reading raw POST data from input stream instead. 
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();
foreach ($raw_post_array as $keyval) {
  $keyval = explode ('=', $keyval);
  if (count($keyval) == 2)
     $myPost[$keyval[0]] = urldecode($keyval[1]);
}
// read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-validate';
if(function_exists('get_magic_quotes_gpc')) {
   $get_magic_quotes_exists = true;
} 
foreach ($myPost as $key => $value) {        
   if($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) { 
        $value = urlencode(stripslashes($value)); 
   } else {
        $value = urlencode($value);
   }
   $req .= "&$key=$value";
}
 
// STEP 2: Post IPN data back to paypal to validate
 
$ch = curl_init($CFG->paypallink);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
 
// In wamp like environments that do not come bundled with root authority certificates,
// please download 'cacert.pem' from "http://curl.haxx.se/docs/caextract.html" and set the directory path 
// of the certificate as shown below.
curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
if( !($res = curl_exec($ch)) ) {
    // error_log("Got " . curl_error($ch) . " when processing IPN data");
    curl_close($ch);
    exit;
}
curl_close($ch);
 
// STEP 3: Inspect IPN validation result and act accordingly
$req = str_replace("&", "||", $req);  // Make it a nice list in case we want to email it to ourselves for reporting
if (strcmp ($res, "VERIFIED") == 0) {
    // check whether the payment_status is Completed
    // check that txn_id has not been previously processed
    // check that receiver_email is your Primary PayPal email
    // check that payment_amount/payment_currency are correct
    // process payment
    
    
    // assign posted variables to local variables
    $item_name = $_POST['item_name'];
    $item_number = $_POST['item_number'];
    $payment_status = $_POST['payment_status'];
    $payment = $_POST['mc_gross'];
    $payment_currency = $_POST['mc_currency'];
    $txn_id = $_POST['txn_id'];
    $custom = $_POST['custom'];
    $receiver_email = $_POST['receiver_email'];
    $payer_email = $_POST['payer_email'];
    
    if ($_SERVER['REQUEST_METHOD'] != "POST"){
        die("No Post Variables");    
    }
    
    if($receiver_email != $CFG->paypal_merchant_account){
        die("Not a Merchant Match");
    }
    
    execute_db_sql("INSERT INTO ipn (transactionid,ipn,timelog) VALUES('$txn_id','VERIFIED||$req','".time()."')");
 
    if(get_db_row("SELECT * FROM orders WHERE paypal_transactionid='$txn_id'")){
        die("Not a Merchant Match");
    }
    
    if(!empty($custom)){
        if($temp_orders = get_db_result("SELECT * FROM temp_orders WHERE tempid='$custom'")){
            while($temp_order = fetch_row($temp_orders)){
                $orderid = execute_db_sql("INSERT INTO orders (orderdate,price,paid_date,paid,payer_email,paypal_transactionid) VALUES('".$temp_order["orderdate"]."','".$temp_order["price"]."','".time()."','$payment','$payer_email','$txn_id')");
                if($temp_order_carts = get_db_result("SELECT * FROM temp_orders_cart WHERE tempid='$custom'")){
                    while($temp_order_cart = fetch_row($temp_order_carts)){
                        execute_db_sql("INSERT INTO orders_cart (orderid,creationid,quantity,price) VALUES('$orderid','".$temp_order_cart["creationid"]."','".$temp_order_cart["quantity"]."','".$temp_order_cart["price"]."')");      
                        execute_db_sql("UPDATE creations SET bought=1 WHERE creationid='".$temp_order_cart["creationid"]."'");
                    }
                }     
            }
        } 
        execute_db_sql("DELETE FROM temp_orders WHERE tempid='$custom'");
        execute_db_sql("DELETE FROM temp_orders_cart WHERE tempid='$custom'");
        $req .= "||orderid=$orderid";
    } 
    
    // Place the transaction into the database
    // Mail yourself the details
    mail($CFG->paypal_merchant_account, "POLKA DOT ZEBRA ORDER: YAY MONEY!", make_order_message($req), "From: ".$CFG->paypal_merchant_account);
    
    mail($receiver_email, "POLKA DOT ZEBRA ORDER", "Thank you for your order! \n". make_order_message($req), "From: ".$CFG->paypal_merchant_account);

} else if (strcmp ($res, "INVALID") == 0) {
    execute_db_sql("INSERT INTO ipn (transactionid,ipn,timelog) VALUES('$txn_id','INVALID||$req','".time()."')");
    // log for manual investigation
}
//
//
//// Check to see there are posted variables coming into the script
//if ($_SERVER['REQUEST_METHOD'] != "POST")
//    die("No Post Variables");
//// Initialize the $req variable and add CMD key value pair
//$req = 'cmd=_notify-validate';
//// Read the post from PayPal
//foreach ($_POST as $key => $value) {
//    $value = urlencode(stripslashes($value));
//    $req .= "&$key=$value";
//}
//
//// Now Post all of that back to PayPal's server using curl, and validate everything with PayPal
//// We will use CURL instead of PHP for this for a more universally operable script (fsockopen has issues on some environments)
//$url = $CFG->paypallink;
//$curl_result = $curl_err = '';
//$ch = curl_init();
//curl_setopt($ch, CURLOPT_URL, $url);
//curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//curl_setopt($ch, CURLOPT_POST, 1);
//curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
//curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", "Content-Length: " . strlen($req)));
//curl_setopt($ch, CURLOPT_HEADER, 0);
//curl_setopt($ch, CURLOPT_VERBOSE, 1);
//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
//curl_setopt($ch, CURLOPT_TIMEOUT, 30);
//$curl_result = @curl_exec($ch);
//$curl_err = curl_error($ch);
//curl_close($ch);
//
//$req = str_replace("&", "||", $req);  // Make it a nice list in case we want to email it to ourselves for reporting
//// Check that the result verifies
//if (strpos($curl_result, "VERIFIED") !== false) {
//    $req .= "\n\nPaypal Verified OK";
//} else {
//    $req .= "\n\nData NOT verified from Paypal!";
//    mail($CFG->paypal_merchant_account, "IPN interaction not verified", "$req", "From: ".$CFG->paypal_merchant_account);
//    exit();
//}      
//
//execute_db_sql("INSERT INTO ipn (transactionid,ipn,timelog) VALUES('".$_POST['txn_id']."','$req','".time()."')");
//                    
///* CHECK THESE 4 THINGS BEFORE PROCESSING THE TRANSACTION, HANDLE THEM AS YOU WISH
//  1. Make sure that business email returned is your business email
//  2. Make sure that the transaction?s payment status is ?completed?
//  3. Make sure there are no duplicate txn_id
//  4. Make sure the payment amount matches what you charge for items. (Defeat Price-Jacking) */
//
//// Check Number 1 ------------------------------------------------------------------------------------------------------------
//$receiver_email = $_POST['receiver_email'];
//if ($receiver_email != $CFG->paypal_merchant_account) {
////handle the wrong business url
//    exit(); // exit script
//}
//// Check number 2 ------------------------------------------------------------------------------------------------------------
//if ($_POST['payment_status'] != "Completed") {
//    // Handle how you think you should if a payment is not complete yet, a few scenarios can cause a transaction to be incomplete
//}
//
//// Check number 3 ------------------------------------------------------------------------------------------------------------
//$this_txn = $_POST['txn_id'];
//if(get_db_row("SELECT * FROM orders WHERE paypal_transactionid='$this_txn'")){
//    exit();
//}
//// Check number 4 ------------------------------------------------------------------------------------------------------------
//// END ALL SECURITY CHECKS NOW IN THE DATABASE IT GOES ------------------------------------
//////////////////////////////////////////////////////
//// Homework - Examples of assigning local variables from the POST variables
//$txn_id = $_POST['txn_id'];
//$payer_email = $_POST['payer_email'];
//$custom = $_POST['custom'];
//$payment = $_POST["mc_gross"];
//if(!empty($custom)){
//    if($temp_orders = get_db_result("SELECT * FROM temp_orders WHERE tempid='$custom'")){
//        while($temp_order = fetch_row($temp_orders)){
//            $orderid = execute_db_sql("INSERT INTO orders (orderdate,price,paid_date,paid,payer_email,paypal_transactionid) VALUES('".$temp_order["orderdate"]."','".$temp_order["price"]."','".time()."','$payment','$payer_email','$txn_id')");
//            if($temp_order_carts = get_db_result("SELECT * FROM temp_orders_cart WHERE tempid='$custom'")){
//                while($temp_order_cart = fetch_row($temp_order_carts)){
//                    execute_db_sql("INSERT INTO orders_cart (orderid,creationid,quantity,price) VALUES('$orderid','".$temp_order_cart["creationid"]."','".$temp_order_cart["quantity"]."','".$temp_order_cart["price"]."')");      
//                    execute_db_sql("UPDATE creations SET bought=1 WHERE creationid='".$temp_order_cart["creationid"]."'");
//                }
//            }     
//        }
//    } 
//    execute_db_sql("DELETE FROM temp_orders WHERE tempid='$custom'");
//    execute_db_sql("DELETE FROM temp_orders_cart WHERE tempid='$custom'");
//    $req .= "||orderid=$orderid";
//} 
//
//// Place the transaction into the database
//// Mail yourself the details
//mail($CFG->paypal_merchant_account, "POLKA DOT ZEBRA ORDER: YAY MONEY!", make_order_message($req), "From: ".$CFG->paypal_merchant_account);
//
//mail($receiver_email, "POLKA DOT ZEBRA ORDER", "Thank you for your order! \n". make_order_message($req), "From: ".$CFG->paypal_merchant_account);
//
?>
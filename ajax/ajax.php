<?php
/***************************************************************************
* ajax.php - Main backend ajax script.  Usually sends off to feature libraries.
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/24/2012
* Revision: 2.9.7
***************************************************************************/

include('header.php');
session_start(); // start up your PHP session!
callfunction();

//Make an order
function order(){
global $CFG, $MYVARS;
    $order = !empty($MYVARS->GET["order"]) ? $MYVARS->GET["order"] : false; 
    
    $cart = unserialize(Urldecode($order));   
    
    $totals = cart_totals();
    
    $price = number_format(($totals["price"] + ($totals["amount"] * $CFG->shippingperitem)),2);

    if($tempid = execute_db_sql("INSERT INTO temp_orders (orderdate,price) VALUES('".time()."','$price')")){
         foreach($cart as $bow){
            if($creation = get_db_row("SELECT * FROM creations WHERE bowid='".$bow["bowid"]."'")){ //CreationID exists
                $creationid = $creation["creationid"];
                execute_db_sql("UPDATE creations SET popularity='".($creation["popularity"]+1)."' WHERE creationid='$creationid'");    
            }else{
                $data = '';
                foreach($bow["options"] as $options){
                    if(!empty($options["choiceid"])){
                        $data .= empty($data) ? '' : '||';
                        $data .= get_name(array("type" => "option_instance", "id" => $options["id"])).'::'.get_name(array("type" => "choices", "id" => $options["choiceid"]));           
                    }
                }
                $creationid = execute_db_sql("INSERT INTO creations (bowid,styleid,name,price,image,data,resell,popularity) VALUES('".$bow["bowid"]."','".$bow["styleid"]."','".get_name(array("type" => "styles", "id" => $bow["styleid"]))."','".$bow["price"]."','','$data','0','1')");
            }
            execute_db_sql("INSERT INTO temp_orders_cart (tempid,creationid,quantity,price) VALUES('$tempid','$creationid','".$bow["amount"]."','".$bow["price"]."')");
        } 
        unset($_SESSION['CART']);
        echo $tempid;
    }       
}

//See list of orders
function orders(){
    $search_array[0] = new stdClass(); $search_array[1] = new stdClass(); $search_array[2] = new stdClass(); $search_array[3] = new stdClass(); $search_array[4] = new stdClass();
    
    $search_array[0]->name = 'Order #'; $search_array[0]->value = 'orderid';
    $search_array[1]->name = 'Order Date'; $search_array[1]->value = 'orderdate';
    $search_array[2]->name = 'Email'; $search_array[2]->value = 'payer_email';
    $search_array[3]->name = 'Paypal Transaction ID'; $search_array[3]->value = 'paypal_transactionid';
    $search_array[4]->name = 'Paid Date'; $search_array[4]->value = 'paid_date';
    
    echo '<h2>Orders</h2>
            <div id="dialog" style="word-wrap: break-word;"></div>
            <strong>Unshipped Orders</strong><br />
            <div style="height : 200px; overflow : auto;">'.display_orders("SELECT * FROM orders WHERE shipped=0 ORDER BY orderdate").'</div>
            <br /><strong>Search Orders</strong><br />
            <input class="fields" type="text" id="search" name="search" /> '.make_select_from_array("searchby",$search_array,"value","name","fields").'<input type="button" value="Search" onclick="
                $.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'search_orders\', values: $(\'.fields\').serializeArray() },
                  success: function(data) { $(\'#search_results\').html(data); refresh_all(); }
                });
            " />
            <div id="search_results" style="height : 200px; overflow : auto;"></div>';    
}

//View order
function view_order(){
global $CFG, $MYVARS;
    $orderid = !empty($MYVARS->GET["orderid"]) ? $MYVARS->GET["orderid"] : false; 
    $returnme = '
            <div class="builder_steps_container">
                <table class="builder_steps"><tr>
                    <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'orders\' },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />
                    </td>
                    <td style="text-align:center;">
                        <h2>Order #'.$orderid.'</h2>   
                    </td>
                    <td style="text-align:right;width:15%">                      
                    </td>
                </tr></table>
            </div>';
            
    if($orderid){
        if($result = get_db_result("SELECT c.*, o.quantity FROM creations c JOIN orders_cart o ON o.creationid = c.creationid WHERE o.orderid='$orderid'")){
            while($row = fetch_row($result)){
                $image = empty($row["image"]) ? get_image(array("type" => "styles", "id" => $row["styleid"])) : get_image(array("type" => "creations", "id" => $row["creationid"]));
                $custom = empty($row["resell"]) ? 'Custom' : 'Pre-Designed';
                $returnme .= '<table style="width:100%;text-align:left"> 
                                <tr style="vertical-align:top;">
                                    <td rowspan="4" style="width:125px;"><img style="height:120px;width:120px;" src="'.$image.'_small.jpg" /></td>
                                    <td><strong>Creation Info:</strong></td>
                                    <td style="border: 1px solid gainsboro;">
                                        '.display_creations("SELECT * FROM creations WHERE creationid='".$row["creationid"]."'").'
                                    </td>
                                </tr>
                                <tr style="vertical-align:top;">
                                    <td style="width:110px;"><strong>Quantity:</strong></td>
                                    <td>'.$row["quantity"].'</td>
                                </tr>
                                <tr style="vertical-align:top;">
                                    <td><strong>Designed:</strong></td>
                                    <td>'.$custom.'</td>
                                </tr>
                                <tr style="vertical-align:top;">
                                    <td><strong>Bow Description:</strong></td>
                                    <td>'.make_bow_description($row["creationid"],true).'</td>
                                </tr></table><br />';        
            }
        }  
        echo $returnme;  
    }else{
        echo "None found";
    }      
}

//Search orders
function search_orders(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $optionid = $price = $returnme = "";
    $select_text = "Please select a value.";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "search":
                    $search = mysql_real_escape_string($field["value"]);
                    break;
                case "searchby":
                    $searchby = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    switch ($searchby) {
        case "orderdate":
        case "paid_date":
            $from = strtotime($search);
            $to = $from + 86400;
            $search = "$searchby >= $from AND $searchby <= $to";
            break;
        case "orderid":
        case "paypal_transactionid":
            $search = "$searchby = '$search'";
            break;
        default:
            $search = "$searchby LIKE '%$search%'";
            break;
    }      
    $SQL = "SELECT * FROM orders WHERE $search ORDER BY orderid";
    echo display_orders($SQL);
}

function creations(){
    $search_array[0] = new stdClass(); $search_array[1] = new stdClass();
    $search_array[0]->name = 'Style Name'; $search_array[0]->value = 'style';
    $search_array[1]->name = 'Creation Name'; $search_array[1]->value = 'name';
    
    echo '<h2>Manage Creations</h2>
            <strong>Search</strong><br />
            <input class="fields" type="text" id="search" name="search" /> '.make_select_from_array("searchby",$search_array,"value","name","fields").'<input type="button" value="Search" onclick="
                $.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'search_creations\', values: $(\'.fields\').serializeArray() },
                  success: function(data) { $(\'#search_results\').html(data); refresh_all(); }
                });
            " />
            <div id="search_results" style="height : 200px; overflow : auto;"></div>';
}

function toggle_link(){
global $CFG, $MYVARS;
    $field = empty($MYVARS->GET["field"]) ? false : $MYVARS->GET["field"];   
    $value = empty($MYVARS->GET["value"]) ? false : $MYVARS->GET["value"];
    $id = empty($MYVARS->GET["id"]) ? false : $MYVARS->GET["id"]; 
    $type = empty($MYVARS->GET["type"]) ? false : $MYVARS->GET["type"];
    
    $value = empty($value) ? "1" : "0";
    switch ($type) {
        case "creations":
            $SQL = "UPDATE creations SET $field='$value' WHERE creationid='$id'";
            break;
        case "orders":
            $SQL = "UPDATE orders SET $field='$value' WHERE orderid='$id'";
            break;
    }
    
    execute_db_sql($SQL);
    
    $yesno = empty($value) ? "No" : "Yes";
    $returnme = '  <a href="javascript: void(0)" onclick="
                        var $t = $(this);
                        $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'toggle_link\',field: \''.$field.'\', value: \''.$value.'\', type: \''.$type.'\', id: \''.$id.'\' },
                          success: function(data) { $t.parent().html(data); }
                        });
                    ">
                    '.$yesno.'
                    </a>';
    echo $returnme;
}

function search_creations(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $optionid = $price = $returnme = "";
    $select_text = "Please select a value.";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "search":
                    $search = mysql_real_escape_string($field["value"]);
                    break;
                case "searchby":
                    $searchby = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    switch ($searchby) {
        case "orderdate":
        case "paid_date":
            $from = strtotime($search);
            $to = $from + 86400;
            $search = "$searchby >= $from AND $searchby <= $to";
            break;
        case "orderid":
        case "style":
            $search = "s.name LIKE '%$search%'";
            break;
        case "creationid":
            $search = "c.creationid = '$search'";
            break;
        default:
            $search = "c.$searchby LIKE '%$search%'";
            break;
    }      
    $SQL = "SELECT c.* FROM creations c JOIN styles s on s.styleid = c.styleid WHERE $search ORDER BY c.name";
    echo display_creations($SQL);
}

function display_orders($SQL){
    $returnme = '';
    if($result = get_db_result($SQL)){
        $returnme .= '  <table class="orders_table">
                        <tr class="orders_table_row_headers">
                            <td>View</td>
                            <td>Order #</td>
                            <td>Order Date</td>
                            <td>Price</td>
                            <td>Email</td>
                            <td>Paid</td>
                            <td>Paid Date</td>
                            <td>Paypal Transaction ID</td>
                            <td>Shipped</td>
                        </tr>';
        $i = 0;
        while($row = fetch_row($result)){
            $class = $i % 2 ? 'orders_table_row_a' : 'orders_table_row_b';
            $shipped = empty($row["shipped"]) ? "No" : "Yes";
            $returnme .= '  <tr class="'.$class.'">
                                <td>
                                    <a href="javascript: void(0)" onclick="
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'view_order\',orderid: \''.$row["orderid"].'\' },
                                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                        });
                                    ">
                                    '.get_icon('magnifier').'
                                    </a>
                                </td>
                                <td>
                                    '.$row["orderid"].'
                                </td>
                                <td>
                                    '.date("m/d/Y",$row["orderdate"]).'
                                </td>
                                <td>
                                    $'.$row["price"].'
                                </td>
                                <td>
                                    '.$row["payer_email"].'
                                </td>
                                <td>
                                    $'.$row["paid"].'
                                </td>
                                <td>
                                    '.date("m/d/Y",$row["paid_date"]).'
                                </td>
                                <td>
                                    <a href="javascript:
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/paypal_transactions.php\',
                                          data: { action: \'\', transid: \''.$row["paypal_transactionid"].'\', startdate: \''.date("m/d/Y",$row["paid_date"]).'\' },
                                          success: function(data) { 
                                            $(\'#dialog\').html(data); 
                                            $(\'#dialog\').dialog({
                                                modal: true,
                                                height: 400,
                                                width: 500,
                                                buttons: {
                                                    Ok: function() {
                                                        $( this ).dialog(\'close\');
                                                    }
                                                }
                                            }); 
                                          }
                                        });
                                    ">'.$row["paypal_transactionid"].'</a>
                                </td>
                                <td>
                                    <span>
                                    <a href="javascript: void(0)" onclick="
                                        var $t = $(this);
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'toggle_link\',field: \'shipped\', value: \''.$row["shipped"].'\', type: \'orders\', id: \''.$row["orderid"].'\' },
                                          success: function(data) { $t.parent().html(data); }
                                        });
                                    ">
                                    '.$shipped.'
                                    </a>
                                    </span>
                                </td>
                            </tr>';
            $i++;    
        }
        $returnme .= '</table>';
    }else{
        $returnme = '<br /><div style="text-align:center"><strong>None Found</strong></div>';
    }
    return $returnme;    
}

function display_creations($SQL){
    $returnme = '';

    if($result = get_db_result($SQL)){
        $returnme .= '  <table class="orders_table">
                        <tr class="orders_table_row_headers">
                            <td style="width: 70px;">Creation #</td>
                            <td>Name</td>
                            <td style="width: 150px;">Style</td>
                            <td style="width: 40px;">Image</td>
                            <td style="width: 70px;">Price</td>
                            <td style="width: 40px;">Resell</td>
                            <td style="width: 70px;">Bought</td>
                            <td style="width: 70px;">Popularity</td>
                        </tr>';
        $i = 0;
        while($row = fetch_row($result)){
            $class = $i % 2 ? 'orders_table_row_a' : 'orders_table_row_b';
            $resell = empty($row["resell"]) ? "No" : "Yes";
            $image = empty($row["image"]) ? "Add" : "Edit";

            $returnme .= '  <tr class="'.$class.'">
                                <td>
                                    '.$row["creationid"].'
                                </td>
                                <td>
                                    <span class="edit_creation" style="display: inline" id="name" rel="'.$row["creationid"].'">'.get_name(array("type" => "creations", "id" => $row["creationid"])).'</span>
                                    
                                </td>
                                <td>
                                    '.get_name(array("type" => "styles", "id" => $row["styleid"])).'
                                </td>
                                <td>
                                    <a href="javascript: void(0)" onclick="
                                        var $t = $(this);
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'add_edit_creation_image\', id: \''.$row["creationid"].'\' },
                                          success: function(data) { $(\'#admin_display\').html(data); }
                                        });
                                    ">
                                    '.$image.'
                                    </a>
                                </td>
                                <td>
                                    <span class="edit_creation" style="display: inline" id="price" rel="'.$row["creationid"].'">$'.number_format($row["price"],2).'</span>
                                </td>
                                <td>
                                    <span>
                                    <a href="javascript: void(0)" onclick="
                                        var $t = $(this);
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'toggle_link\',field: \'resell\', value: \''.$row["resell"].'\', type: \'creations\', id: \''.$row["creationid"].'\' },
                                          success: function(data) { $t.parent().html(data); }
                                        });
                                    ">
                                    '. $resell .'
                                    </a>
                                    </span>
                                </td>
                                <td>
                                    '. $row["bought"] .'
                                </td>
                                <td>
                                    '. $row["popularity"] .'
                                </td>
                            </tr>';
            $i++;    
        }
        $returnme .= '</table>';
    }else{
        $returnme = '<br /><div style="text-align:center"><strong>None Found</strong></div>';
    }
    return $returnme;    
}

function save_creation_edit(){
global $CFG, $MYVARS;
    $field = empty($MYVARS->GET["id"]) ? false : $MYVARS->GET["id"];
    $value = empty($MYVARS->GET["value"]) ? false : $MYVARS->GET["value"];
    $creationid = empty($MYVARS->GET["creationid"]) ? false : $MYVARS->GET["creationid"];
    
    $oldvalue = get_db_field("$field","creations","creationid='$creationid'");
    if(!empty($field) && !empty($value) && !empty($creationid)){
        switch ($field) {
            case "name":
                $value = trim(mysql_real_escape_string($value));
                break;
            case "price":
                $value = mysql_real_escape_string($value);
                $value = str_replace("$","",$value);
                break;
        }  
      
        if(($field == "name") || ($field == "price" && is_numeric($value))){
            if($creationid){
                $SQL = "UPDATE creations SET $field='$value' WHERE creationid='$creationid'";
                execute_db_sql($SQL);
                if($field == "price"){
                    echo "$" . number_format($value,2);
                }else{
                    echo $value;    
                }
                exit();
            }
        }  
    }else{
        if($field == "price"){
            echo "$" . number_format($oldvalue,2);
        }else{
            echo $oldvalue;    
        }   
    }       
}

function add_edit_creation_image(){
global $CFG, $MYVARS;
    $id = empty($MYVARS->GET["id"]) ? false : $MYVARS->GET["id"]; 

    if($id){
        $creation = get_db_row("SELECT * FROM creations WHERE creationid='$id'");
        $image = $creation["image"];    
    }

    $returnme = '
            <div class="builder_steps_container">
                <table class="builder_steps"><tr>
                    <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'creations\' },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />
                    </td>
                    <td style="text-align:center;">
                        <h2>Add/Edit Creation Image</h2>   
                    </td>
                    <td style="text-align:right;width:15%">                      
                    </td>
                </tr></table>
            </div>';
    $image = empty($image) ? 'images/none.gif' : get_image(array("type" => "creations", "id" => $id)).'_large.jpg';                   
    $returnme .= '
        <link rel="stylesheet" href="'.$CFG->wwwroot.'/css/classicTheme/style.css" type="text/css" media="all" /> 
        <div class="ui-corner-all" id="update_image" style="border: 1px solid silver;margin: 15px;display:inline-block;margin-left:auto;margin-right:auto;">
            <img src="'.$CFG->wwwroot.'/'.$image.'" style="height:160px;width:160px;" />
        </div>
        <br />
        <strong>Expected Size: 160px x 160px &nbsp;File Type: .jpg</strong><br /><br />
        <input class="fields" type="hidden" name="creationid" id="creationid" value="'.$id.'" />
        <div id="uploader"></div>
        <div id="error"></div>';
    
    $returnme .= '  <script type="text/javascript">
                        $("#uploader").ajaxupload({
                            "url": "ajax/upload.php",
                            "remotePath": "../files/creations/'.$id.'/",
                            "maxFiles": 1,
                            "allowExt": ["jpg"],
                            "editFilename": true,
                            "finish": function(filesName){
                                console.log(filesName);
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'add_edit_creation_image_save\', filename: filesName[0], values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#update_image\').html(data); refresh_all(); }
                                });         
                            }
                        });
                    </script>';
                
    echo $returnme;    
}

function add_edit_creation_image_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $filename = empty($MYVARS->GET["filename"]) ? false : $MYVARS->GET["filename"];
    
    $creationid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "creationid":
                    $creationid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $creationid = empty($creationid) ? false : $creationid;  
    if(!empty($filename) && !empty($creationid)){
        
        $file = prepare_files("creations",$creationid,$filename);

        if($creationid){
            $SQL = "UPDATE creations SET image='$file' WHERE creationid='$creationid'";
            execute_db_sql($SQL);
        }
    } 
    echo '<img src="'.$CFG->wwwroot.'/'.get_image(array("type" => "creations", "id" => $creationid)).'_large.jpg" style="height:160px;width:160px;" />';
}

function signin(){
global $CFG, $MYVARS;
    $username = !empty($MYVARS->GET["username"]) ? $MYVARS->GET["username"] : false;
    $password = !empty($MYVARS->GET["password"]) ? $MYVARS->GET["password"] : false;
    
    if(authenticate($username,$password)){
        echo "true";
    }else{
        echo "Incorrect username or password.";
    }
}

function signout(){
global $CFG, $MYVARS;
    unset($_SESSION['admin']);
}

function builder_reset(){
    echo get_builder();    
}

function remove_from_cart(){
global $CFG, $MYVARS;
    $webid = !empty($MYVARS->GET["value"]) ? $MYVARS->GET["value"] : false;
    if(remove_bow($webid)){
        echo cart_bar_contents();   
    }else{
        echo "false";
    }
}

function update_popular(){
global $CFG, $MYVARS;
    $styleid = !empty($MYVARS->GET["styleid"]) ? $MYVARS->GET["styleid"] : false;   
    echo get_popular($styleid);
}

function builder_step1(){
global $CFG;
    $returnme = reset_button();
    $returnme .= '<p><strong>Select a style of bow to begin.</strong></p>';
    
    //Choose a style of bow
    if($result = get_db_result("SELECT * FROM styles WHERE active=1 ORDER BY sort")){
        while($row = fetch_row($result)){
            $returnme .= '  <button style="margin:3px;" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'builder_step2\',styleid: \''.$row["styleid"].'\' },
                                  success: function(data) { $(\'#builder\').html(data); refresh_all(); }
                                });
                                
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'update_popular\',styleid: \''.$row["styleid"].'\' },
                                  success: function(data) { $(\'#popular\').html(data); refresh_all(); }
                                });
                            ">
                                <div class="ui-corner-all" style="margin-left:auto;margin-right:auto;background-size: 100%;height:160px;width:160px;background-image: url(\''.get_image(array("type" => "styles", "id" => $row["styleid"])).'_small.jpg\')"></div>
                                '.stripslashes($row["name"]).'<br /><span style="font-size:10px">Base Price: $'.$row["price"].'</span>
                            </button>'; 
        }
    } 
    echo $returnme;    
}

function builder_step2(){
global $CFG, $MYVARS;
    $styleid = !empty($MYVARS->GET["styleid"]) ? $MYVARS->GET["styleid"] : false;
    $creationid = !empty($MYVARS->GET["creationid"]) ? $MYVARS->GET["creationid"] : false;
    
    $returnme = reset_button();
    $options = "";
     
    if(!empty($creationid)){
        $creation = get_db_row("SELECT * FROM creations WHERE creationid='$creationid'");
        $styleid = $creation["styleid"];  
        $product_image = get_image(array("type" => "creations", "id" => $creation["creationid"])); 
        $style = get_db_row("SELECT * FROM styles WHERE styleid='$styleid'");
        $price = $creation["price"];
        $returnme .= '<p><strong>Purchase a pre-made bow design.</strong></p>';
        $name = get_name(array("type" => "creations","id" => $creation["creationid"])) . "&nbsp;&nbsp;(<em>" . get_name(array("type" => "styles","id" => $creation["styleid"])) . ' style</em>)';
    }else{
        $style = get_db_row("SELECT * FROM styles WHERE styleid='$styleid'");
        $product_image = get_image(array("type" => "styles", "id" => $style["styleid"]));  
        $name = get_name(array("type" => "styles","id" => $styleid));
        $price = $style["price"];
        $returnme .= '<p><strong>Build your custom bow from the options below.</strong></p>';  
        //Choose from the options
        if($result = get_db_result("SELECT o.select_text,s.* FROM options o JOIN styles_options s ON s.optionid = o.optionid WHERE s.styleid='$styleid' AND s.parentoptionid=0 ORDER BY s.sort")){
            while($row = fetch_row($result)){
                $options .= make_option($row,$style);
            }
        } 
    }
    
    $create = empty($creationid) && logged_in() ? '<button style="float:right;font-size:10px;display:inline-block;" onclick="
        var ishidden = $(\'#view_cart,.selector\').accordion(\'option\', \'active\');
        $(\'#created\').hide(\'pulsate\',\'slow\');
        $.ajax({
          type: \'POST\',
          url: \'ajax/ajax.php\',
          data: { action: \'admin_create\',styleid: \''.$styleid.'\', values: get_options() },
          success: function(data) {  }
        });
    ">Create</button> ' : '';
    
    $price = '<div id="price" class="price ui-corner-all"><strong style="display: inline-block;padding-top: 4px;">Price: $<span>'.$price.'</span></strong><input type="hidden" value="'.$price.'" />
    '.$create.'<button style="float:right;font-size:10px;display:inline-block;" onclick="
        var ishidden = $(\'#view_cart,.selector\').accordion(\'option\', \'active\');
        $(\'#added\').hide(\'pulsate\',\'slow\');
        $.ajax({
          type: \'POST\',
          url: \'ajax/ajax.php\',
          data: { action: \'builder_step3\',creationid: \''.$creationid.'\', styleid: \''.$styleid.'\', values: get_options() },
          success: function(data) { $(\'#cart\').html(data); $(\'#cart:hidden\').show(); refresh_all(); if(ishidden === 0){ $(\'#view_cart,.selector\').accordion(\'option\', \'active\', 0 ); } }
        });
    ">Add to Cart</button><span id="added" style="vertical-align: bottom;display:none;color:red;font-weight:bold;padding: 5px 10px;">Item Added to Cart</span><span id="created" style="vertical-align: bottom;display:none;color:red;font-weight:bold;padding: 5px 10px;">Creation Added</span><div style="clear:both"></div></div>
    ';
    
    //Template
    $returnme .= '  <div class="product">
                        <h2>'.$name.'</h2>
                        <div style="display: inline-block;width: 300px;margin: 10px;">
                            <div class="ui-corner-all product_image" style="filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src=\''.$product_image.'_large.jpg\',sizingMethod=\'scale\');background-size: 100%;background-image: url(\''.$product_image.'_large.jpg\')"></div>
                            '.get_popular_images($styleid,$creationid).'
                        </div>
                        <div class="options_div">'.$price.'<div class="product_description">'.$style["description"].'</div>'.$options.'</div>
                    </div>
    <script type="text/javascript">
        update_price();
    </script>';
    echo $returnme;    
}

function get_popular_images($styleid,$creationid = false){
    $returnme = "";
    if(!$creationid && $result = get_db_result("SELECT * FROM creations WHERE styleid='$styleid' AND image != '' ORDER BY popularity LIMIT 12")){
        while($row = fetch_row($result)){
            $returnme .= '<img id="creation_'.$row["creationid"].'" class="creation_images" src="'.get_image(array("type" => "creations", "id" => $row["creationid"])).'_small.jpg" />'; 
            $creationimage = get_image(array("type" => "creations", "id" => $row["creationid"]));
            $styleimage = get_image(array("type" => "styles", "id" => $row["styleid"]));
            $returnme .= '
            <script type="text/javascript">
            $("#creation_'.$row["creationid"].'").hover(
                function(){ 
                    $(".product_image").css("background-image","url('.$creationimage.'_large.jpg)");
                },function(){ 
                    $(".product_image").css("background-image","url('.$styleimage.'_large.jpg)"); 
                } 
            );
            </script>';   
        }

    }
    return $returnme;    
}

function admin_create(){
global $CFG, $MYVARS;
    $styleid = !empty($MYVARS->GET["styleid"]) ? $MYVARS->GET["styleid"] : false;  
    $values = !empty($MYVARS->GET["values"]) ? $MYVARS->GET["values"] : false;
    $options_array = array();
    
    $options = explode("||",$values);
    foreach($options as $option){
        if(!empty($option[1])){
            $option = explode("_",$option);
            $option = explode("::", $option[1]);
            $id = $option[0];
            $choiceid = $option[1];
            $options_array[] = array("id" => $id, "choiceid" => $choiceid);             
        }
    }
    $bow[] = array("bowid" => md5(serialize(array("styleid" => $styleid, "options" => $options_array))),"amount" => "1", "styleid" => $styleid, "options" => $options_array);            

    $bow = make_price($bow); 
    
    $bow = $bow[0];

    $data = '';
    foreach($bow["options"] as $options){
        if(!empty($options["choiceid"])){
            $data .= empty($data) ? '' : '||';
            $data .= get_name(array("type" => "option_instance", "id" => $options["id"])).'::'.get_name(array("type" => "choices", "id" => $options["choiceid"]));           
        }
    }
    
    if($creationid = get_db_row("SELECT * FROM creations WHERE bowid='".$bow["bowid"]."'")){
            
    }else{
        $creationid = execute_db_sql("INSERT INTO creations (bowid,styleid,name,price,image,data,resell,popularity) VALUES('".$bow["bowid"]."','".$bow["styleid"]."','".get_name(array("type" => "styles", "id" => $bow["styleid"]))."','".$bow["price"]."','','$data','0','1')");   
    }   
}

function builder_step3(){
global $CFG, $MYVARS;
    $styleid = !empty($MYVARS->GET["styleid"]) ? $MYVARS->GET["styleid"] : false;  
    $creationid = !empty($MYVARS->GET["creationid"]) ? $MYVARS->GET["creationid"] : false;
    $values = !empty($MYVARS->GET["values"]) ? $MYVARS->GET["values"] : false;
    $options_array = array();
    
    if($creationid){
       $creation = get_db_row("SELECT * FROM creations WHERE creationid='$creationid'"); 
       $bow[] = array("bowid" => $creation["bowid"],"amount" => "1", "styleid" => $creation["styleid"], "options" => $options_array, "creationid" => $creationid); 
    }else{    
        $options = explode("||",$values);
        foreach($options as $option){
            if(!empty($option[1])){
                $option = explode("_",$option);
                $option = explode("::", $option[1]);
                $id = $option[0];
                $choiceid = $option[1];
                $options_array[] = array("id" => $id, "choiceid" => $choiceid);             
            }
        }
        $bow[] = array("bowid" => md5(serialize(array("styleid" => $styleid, "options" => $options_array))),"amount" => "1", "styleid" => $styleid, "options" => $options_array);            
    }

    $bow = make_price($bow); 

    add_to_cart($bow[0]);
    
    echo cart_bar_contents();   
}

function make_option($option,$style){
global $CFG, $MYVARS;
    $choice_prices = $script = $show = "";
    $styleimage = get_image(array("type" => "styles", "id" => $style["styleid"]));
    $returnme = '<div id="option_'.$option["id"].'_div"><select id="option_'.$option["id"].'" name="option_'.$option["id"].'" class="imageselect" >';
    if($choicelists = get_db_result("SELECT * FROM options_choices WHERE optionid='".$option["optionid"]."' ORDER BY sort")){
        $selected = "";
        if(!$option["required"]){ $returnme .= '<option value="0" data-imagesrc="" data-description="Not Required"><strong>'.stripslashes($option["name"]).': None</option>'; }
        
        while($choicelist = fetch_row($choicelists)){
            if($choices = get_db_result("SELECT c.*,l.sort,l.defaultsetting FROM choices c JOIN choicelists_choices l ON l.choiceid = c.choiceid WHERE l.choicelistid='".$choicelist["choicelistid"]."' ORDER BY l.sort")){
                while($choice = fetch_row($choices)){
                    if($option["required"] && $choice["defaultsetting"] == "1"){ $selected = 'selected="selected"'; }else{ $selected = ""; }
                    $imghvr = $image = "";
                    if(!empty($choice["image"])){
                        $img = get_image(array("type" => "choices", "id" => $choice["choiceid"]));
                        $image = $CFG->wwwroot.'/'.$img.'_small.jpg';
                        $imghvr = $CFG->wwwroot.'/'.$img.'_large.jpg';   
                    }
                    $price = ($choice["price"] + $option["price"]) == 0 ? "" : (($choice["price"] + $option["price"]) > 0 ? 'Price: + $'.number_format(($choice["price"] + $option["price"]),2) : 'Price: - $'.number_format(str_replace("-","",($choice["price"] + $option["price"])),2));
                    $choice_prices .= '<input type="hidden" id="option_'.$option["id"].'_choice_'.$choice["choiceid"].'" value="'.($choice["price"] + $option["price"]).'" />';
                    $returnme .= '<option '.$selected.' value="'.$choice["choiceid"].'" data-imagesrc="'.$image.'" data-imagehvr="'.$imghvr.'" data-imageorg="'.$styleimage.'_large.jpg" data-imagetgt=".product_image" data-description="'.$choice["description"].'<br />'.$price.'"><strong>'.ucwords(stripslashes($option["name"])).': </strong>'.ucwords(stripslashes($choice["name"])).'</option>';
                }
            }
       }
    } 
    $returnme .= '</select>'.$choice_prices;
    
    $show = make_sub_options($option, $style, $returnme, $script, $show);
    
    $returnme .= '</div>';
    
    $optionimage = get_image(array("type" => "option_instance", "id" => $option["id"]));
    $optionimage = empty($optionimage) ? $styleimage : $optionimage;
            
    $returnme .= '<script type="text/javascript">
        $("#option_'.$option["id"].'").ddslick({
            imagePosition:"right",
            height: "164px",
            width: "300px",
            selectText: "'.$option["select_text"].'",
            onSelected: function(selectedData){
                '.$show.'
                update_price();
            }
        });
        
        $("#option_'.$option["id"].'").hover(
            function(){ 
                $(".product_image").css("background-image","url('.$optionimage.'_large.jpg)");
            },function(){ 
                $(".product_image").css("background-image","url('.$styleimage.'_large.jpg)"); 
            } 
        );
        '.$script.'
    </script>';
    
    return $returnme;
}

function make_sub_options($option, $style, &$returnme, &$script){
global $CFG, $MYVARS;
$show = "";
    //check for sub_options 
    if($suboptions = get_db_result("SELECT o.select_text,o.image,s.* FROM options o JOIN styles_options s ON s.optionid = o.optionid WHERE s.styleid='".$style["styleid"]."' AND s.parentoptionid=".$option["id"]." ORDER BY s.sort")){
        while($suboption = fetch_row($suboptions)){
            $styleimage = get_image(array("type" => "styles", "id" => $style["styleid"]));
            
            $returnme .= '<div id="option_'.$suboption["id"].'_div" style="margin-left: 25px;"><select id="option_'.$suboption["id"].'" name="option_'.$suboption["id"].'" class="imageselect" >';
            $choice_prices = "";
            if($choicelists = get_db_result("SELECT * FROM options_choices WHERE optionid='".$suboption["optionid"]."' ORDER BY sort")){
                $selected = "";
                if(!$suboption["required"]){ $returnme .= '<option value="0" data-imagesrc="" data-description="Not Required"><strong>'.stripslashes($suboption["name"]).': None</option>'; }
                while($choicelist = fetch_row($choicelists)){
                    if($choices = get_db_result("SELECT c.*,l.sort,l.defaultsetting FROM choices c JOIN choicelists_choices l ON l.choiceid = c.choiceid WHERE l.choicelistid='".$choicelist["choicelistid"]."' ORDER BY l.sort")){
                        while($choice = fetch_row($choices)){
                            if($suboption["required"] && $choice["defaultsetting"] == "1"){ $selected = 'selected="selected"'; }else{ $selected = ""; }
                            $imghvr = $image = "";
                            if(!empty($choice["image"])){
                                $img = get_image(array("type" => "choices", "id" => $choice["choiceid"]));
                                $image = $CFG->wwwroot.'/'.$img.'_small.jpg';
                                $imghvr = $CFG->wwwroot.'/'.$img.'_large.jpg';   
                            }
                            $price = ($choice["price"] + $suboption["price"]) == 0 ? "" : (($choice["price"] + $suboption["price"]) > 0 ? 'Price: + $'.number_format(($choice["price"] + $suboption["price"]),2) : 'Price: - $'.number_format(str_replace("-","",($choice["price"] + $suboption["price"])),2));
                            $choice_prices .= '<input type="hidden" id="option_'.$suboption["id"].'_choice_'.$choice["choiceid"].'" value="'.($choice["price"] + $suboption["price"]).'" />';
                            $returnme .= '<option '.$selected.' value="'.$choice["choiceid"].'" data-imagesrc="'.$image.'" data-imagehvr="'.$imghvr.'" data-imageorg="'.$styleimage.'_large.jpg" data-imagetgt=".product_image" data-description="'.$choice["description"].'<br />'.$price.'"><strong>'.ucwords(stripslashes($suboption["name"])).': </strong>'.ucwords(stripslashes($choice["name"])).'</option>';
                        }
                    }
               }
            } 
            $returnme .= '</select>'.$choice_prices;  
            
            $show .= make_sub_options($suboption, $style, $returnme, $script);  
            
            $returnme .= '</div>';
            
            $optionimage = get_image(array("type" => "option_instance", "id" => $suboption["id"]));
            $optionimage = empty($optionimage) ? $styleimage : $optionimage;
            
            $script = '
                        $("#option_'.$suboption["id"].'").ddslick({
                            imagePosition:"right",
                            height: "164px",
                            width: "300px",
                            selectText: "'.$suboption["select_text"].'",
                            onSelected: function(selectedData){
                                '.$show.'
                                update_price();
                            }
                        });
                        
                        $("#option_'.$suboption["id"].'").hover(
                            function(){ 
                                $(".product_image").css("background-image","url('.$optionimage.'_large.jpg)"); 
                            },function(){ 
                                $(".product_image").css("background-image","url('.$styleimage.'_large.jpg)"); 
                            } 
                        );
                        
                        ' . $script;
                                   
            if(!empty($suboption["parentchoiceid"])){ //only show on a certain choiceid
                $script .= '
                    if($("#option_'.$suboption["parentoptionid"].'").data("ddslick").selectedData.value != "'.$suboption["parentchoiceid"].'"){
                        $("#option_'.$suboption["id"].'_div").hide();        
                    }
                ';
                
                $show .= '
                    if(selectedData.selectedData.value == "'.$suboption["parentchoiceid"].'"){
                        $("#option_'.$suboption["id"].'_div").show();    
                    }else{
                        $("#option_'.$suboption["id"].'_div").hide();     
                    }
                ';                
            }else{ //show as soon as a selection is made if it hasn't already been defaulted to
                $script .= '
                    if($("#option_'.$suboption["parentoptionid"].'").data("ddslick").selectedData.value == "0"){
                        $("#option_'.$suboption["id"].'_div").hide();        
                    }
                ';
                
                $show .= '
                    if(selectedData.selectedData.value != 0){
                        $("#option_'.$suboption["id"].'_div").show();    
                    }else{
                        $("#option_'.$suboption["id"].'_div").hide(); 
                    }
                ';                 
            }      
        }
    }   
    return $show;
}

function style_builder(){
global $CFG, $MYVARS;
    $returnme = '';
    
    $returnme .= '
    <h2>Style Builder</h2>
        <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'style_builder_step_1\' },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Create New Style</a><br />
        <br />';
    
    if($result = get_db_result("SELECT * FROM styles ORDER BY sort")){
        $returnme .= make_select("styleid",$result,"styleid","name","fields").'
        <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'style_builder_step_1\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Edit</a> <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'style_builder_styles_sorter\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Resort</a> <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure?\')){
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'delete_style\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); } } ); }
        ">Delete</a>';        
    }
    
    echo $returnme;    
}

function delete_style(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $styleid = $price = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "styleid":
                    $styleid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    $styleid = !empty($styleid) ? $styleid : false; 
    if(!empty($styleid)){
        execute_db_sql("DELETE FROM styles WHERE styleid='$styleid'");
        execute_db_sql("DELETE FROM styles_options WHERE styleid='$styleid'");
        destroy($CFG->dirroot.'/files/styles/'.$styleid.'/');
    }    
    
    style_builder();
}

function style_builder_styles_sorter(){
global $CFG, $MYVARS;
    $returnme = ""; $i = 1;
    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'style_builder\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });" 
                                /></td>
                        <td style="text-align:center;">
                        <h2>Resort Styles</h2>
                        </td>
                        <td style="text-align:right;width:15%">                       
                            <a href="javascript: void(0);" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'style_builder_styles_sorter_alphabetize\' },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });"
                            ">A-Z</a>
                        </td>
                    </tr></table>
                </div>';
                
    if($result = get_db_result("SELECT * FROM styles ORDER BY sort")){
        $numrows = mysql_num_rows($result);
        while($style = fetch_row($result)){
            $up = $i > 1 ? '<a href="javascript: void(0);" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'style_builder_styles_sorter_resort\', id: \''.$style["styleid"].'\', direction: \'up\' },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });"
            ">'.get_icon("up").'</a>' : '';
            $down = $i < $numrows ? '<a href="javascript: void(0);" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'style_builder_styles_sorter_resort\', id: \''.$style["styleid"].'\', direction: \'down\' },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });"
            ">'.get_icon("down").'</a>' : ''; 
            $returnme .= '<table style="border:1px solid silver;width:50%;padding:3px;margin: 3px auto;"><tr><td style="width:40px">'.$up.$down.'</td><td style="text-align:left;width:64px;"><img style="width:64px;height:64px;" src="'.get_image(array("type" => "styles","id" => $style["styleid"])).'_small.jpg"</td><td style="text-align:left">'.stripslashes($style["name"]).'</td></tr></table>';  
            $i++;     
        }
        echo $returnme;
    }
}

function style_builder_styles_sorter_alphabetize(){
    resort("styles","styleid","1=1",false,false,"name");        
    echo style_builder_styles_sorter();    
}

function style_builder_styles_sorter_resort(){
global $CFG, $MYVARS;    
    $id = empty($MYVARS->GET["id"]) ? false : $MYVARS->GET["id"]; 
    $direction = empty($MYVARS->GET["direction"]) ? false : $MYVARS->GET["direction"];  
    
    if(!empty($id) && !empty($direction)){
        resort("styles","styleid","1=1",$id,$direction);        
    }
    echo style_builder_styles_sorter();
}

function style_builder_step_1(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $active_array[0] = new stdClass(); $active_array[1] = new stdClass();
    $active_array[0]->value = 0;
    $active_array[0]->display = "No";
    $active_array[1]->value = 1;
    $active_array[1]->display = "Yes";
    
    $name = $styleid = $price = $returnme = $active = $description = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "styleid":
                    $styleid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    $styleid = !empty($styleid) ? $styleid : false; 

    if($styleid){
        $style = get_db_row("SELECT * FROM styles WHERE styleid='$styleid'");
        $name = stripslashes($style["name"]); 
        $description = stripslashes($style["description"]); 
        $active = $style["active"];
        $price = $style["price"];  
    }
    
    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'style_builder\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });" 
                                /></td>
                        <td style="text-align:center;">
                        <h2>Step 1</h2>
                        </td>
                        <td style="text-align:right;width:15%">
                            <input type="button" value="Next" onclick="if($(\'#name\').val().length){
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'style_builder_step_1_save\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { if(data == \'false\'){ $(\'#error\').html(\'Error occurred!\'); }else{ 
                                $(\'#styleid\').val(data);
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'style_builder_step_2\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                }); 
                              }}
                            });}else{ alert(\'Must fill in name.\'); }" />                        
                        </td>
                    </tr></table>
                </div>';
    
    $returnme .= '
            <div style="display:inline-block;text-align:left;">
            <input class="fields" type="hidden" name="styleid" id="styleid" value="'.$styleid.'" />
            <label class="inputlabel">Style Name: </label><input class="fields" type="text" name="name" id="name" value="'.$name.'" /><br />
            <label class="inputlabel">Base Price: </label><input class="fields" type="text" name="price" id="price" value="'.$price.'" /><br />
            <label class="inputlabel">Active: </label>'.make_select_from_array("active",$active_array,"value","display","fields",$active).'<br />
            <label class="inputlabel">Description: </label><textarea class="fields tinymce" name="description" id="description">'.$description.'</textarea><br />
            </div>
            <div id="error"></div>
                    <script type="text/javascript" src="'.$CFG->wwwroot.'/scripts/tinymce/jscripts/tiny_mce/jquery.tinymce.js"></script>
        <script type="text/javascript">
        $(function() {
                $(\'textarea.tinymce\').tinymce({
                        // Location of TinyMCE script
                        script_url : \''.$CFG->wwwroot.'/scripts/tinymce/jscripts/tiny_mce/tiny_mce.js\',

                        // General options
                        theme : "advanced",
                        plugins : "style,table,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",

                        // Theme options
                        theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,fontselect,fontsizeselect",
                        theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,code,|,forecolor,backcolor",
                        theme_advanced_toolbar_location : "top",
                        theme_advanced_toolbar_align : "left",
                        theme_advanced_statusbar_location : "bottom",
                        theme_advanced_resizing : true,

                        // Drop lists for link/image/media/template dialogs
                        template_external_list_url : "lists/template_list.js",
                        external_link_list_url : "lists/link_list.js",
                        external_image_list_url : "lists/image_list.js",
                        media_external_list_url : "lists/media_list.js",
                });
        });
        </script>
            ';
                
    echo $returnme;    
}

function style_builder_step_1_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $price = $styleid = $description = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "styleid":
                    $styleid = mysql_real_escape_string($field["value"]);
                    break;
                case "name":
                    $name = trim(mysql_real_escape_string($field["value"]));
                    break;
                case "description":
                    $description = trim(mysql_real_escape_string($field["value"]));
                    break;
                case "price":
                    $price = mysql_real_escape_string($field["value"]);
                    break;
                case "active":
                    $active = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }
    
    $styleid = empty($styleid) ? false : $styleid;  
    if(!empty($name) && !empty($price) && is_numeric($price)){
        if($styleid){
            $SQL = "UPDATE styles SET name='$name',description='$description',active='$active',price='$price' WHERE styleid='$styleid'";
            execute_db_sql($SQL);
            echo $styleid;
            exit();
        }else{
            $SQL = "INSERT INTO styles (name,description,price,active) VALUES('$name','$description','$price','$active')";
            if($styleid = execute_db_sql($SQL)){ //Added successfully
                resort("styles","styleid");
                echo $styleid;
                exit();
            }            
        }
    } 
    echo "false";
}

function style_builder_step_2(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $styleid = $returnme = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "styleid":
                    $styleid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }
    if($styleid){
        $style = get_db_row("SELECT * FROM styles WHERE styleid='$styleid'");
        $image = $style["image"];    
    }
    
    $returnme .= '
            <div class="builder_steps_container">
                <table class="builder_steps"><tr>
                    <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'style_builder_step_1\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />
                    </td>
                    <td style="text-align:center;">
                         <h2>Step 2</h2>
                    </td>
                    <td style="text-align:right;width:15%">
                        <input type="button" value="Next" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'style_builder_step_3\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />                        
                    </td>
                </tr></table>
            </div>';
            
    $image = empty($image) ? 'images/none.gif' : get_image(array("type" => "styles", "id" => $styleid)).'_small.jpg';
    $returnme .= '
            <link rel="stylesheet" href="'.$CFG->wwwroot.'/css/classicTheme/style.css" type="text/css" media="all" /> 
            <div class="ui-corner-all" id="update_image" style="border: 1px solid silver;margin: 15px;display:inline-block;margin-left:auto;margin-right:auto;">
                <img src="'.$CFG->wwwroot.'/'.$image.'" style="height:160px;width:160px;" />
            </div>
            <br /><strong>Expected Size: 400px x 400px &nbsp;File Type: .jpg</strong><br /><br />
            <input class="fields" type="hidden" name="styleid" id="styleid" value="'.$styleid.'" />
            <div id="uploader"></div>
            <div id="error"></div>        
    ';
    
    $returnme .= '  <script type="text/javascript">
                        $("#uploader").ajaxupload({
                            "url": "ajax/upload.php",
                            "remotePath": "../files/styles/'.$styleid.'/",
                            "maxFiles": 1,
                            "allowExt": ["jpg"],
                            "editFilename": true,
                            "finish": function(filesName){
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'style_builder_step_2_save\', filename: filesName[0], values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#update_image\').html(data); refresh_all(); }
                                });         
                            }
                        });
                    </script>';
                
    echo $returnme;    
}

function style_builder_step_2_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $filename = empty($MYVARS->GET["filename"]) ? false : $MYVARS->GET["filename"];
    
    $styleid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "styleid":
                    $styleid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }   
    $styleid = empty($styleid) ? false : $styleid;  
    if(!empty($filename) && !empty($styleid)){
        
        $file = prepare_files("styles",$styleid,$filename);
        
        if($styleid){
            $SQL = "UPDATE styles SET image='$file' WHERE styleid='$styleid'";
            execute_db_sql($SQL);
        }
    } 
    echo '<img src="'.$CFG->wwwroot.'/'.get_image(array("type" => "styles", "id" => $styleid)).'_small.jpg" style="height:160px;width:160px;" />';
}

function style_builder_step_3($styleid = false){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $returnme = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "styleid":
                    $styleid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }   

    $returnme .= '
        <div class="builder_steps_container">
            <table class="builder_steps"><tr>
                <td style="text-align:left;width:15%">
                    <input type="button" value="Back" onclick="
                        $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'style_builder_step_2\', values: $(\'.fields\').serializeArray() },
                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });" 
                    />
                </td>
                <td style="text-align:center;">
                    <h2>Step 3</h2>
                </td>
                <td style="text-align:right;width:15%">                       
                </td>
            </tr></table>
        </div>';
    
    $returnme .= '
        <div class="drag_builder ui-corner-all" id="drag_builder">
        <input class="fields" type="hidden" name="styleid" id="styleid" value="'.$styleid.'" /> 
            <a href="javascript: void(0)" onclick="
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'style_builder_option_editor\', parentid: \'0\', styleid: \''.$styleid.'\' },
                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                    });
                ">'.get_icon("add").'</a> Add Option
            '.style_builder_options_tree($styleid).'
        </div><div id="error"></div>';
                
    echo $returnme;
}

function style_builder_resort(){
global $CFG, $MYVARS;
    $id = empty($MYVARS->GET["id"]) ? false : $MYVARS->GET["id"]; 
    $styleid = empty($MYVARS->GET["styleid"]) ? false : $MYVARS->GET["styleid"];
    $direction = empty($MYVARS->GET["direction"]) ? false : $MYVARS->GET["direction"];  
    $parentoptionid = empty($MYVARS->GET["parentoptionid"]) ? "" : "AND parentoptionid='".$MYVARS->GET["parentoptionid"]."'";
    
    if(!empty($id) && !empty($styleid) && !empty($direction)){
        resort("styles_options","id","styleid='$styleid' $parentoptionid",$id,$direction);        
    }
    
    style_builder_step_3($styleid); 
}

function style_builder_options_tree($styleid, &$count = 0, $suboption = 0){
    $returnme = ""; $i=1;
    if($result = get_db_result("SELECT s.* FROM options o JOIN styles_options s ON s.optionid = o.optionid WHERE s.styleid='$styleid' AND s.parentoptionid='$suboption' ORDER BY s.sort")){
        $numrows = mysql_num_rows($result);
        while($row = fetch_row($result)){
            $count++;

            $up = $i > 1 ? '<a href="javascript: void(0);" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'style_builder_resort\', styleid: \''.$styleid.'\', parentoptionid: \''.$suboption.'\', direction: \'up\', id: \''.$row["id"].'\' },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });"
            ">'.get_icon("up").'</a>' : '';
            $down = $i < $numrows ? '<a href="javascript: void(0);" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'style_builder_resort\', styleid: \''.$styleid.'\', parentoptionid: \''.$suboption.'\', direction: \'down\', id: \''.$row["id"].'\' },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });"
            ">'.get_icon("down").'</a>' : '';
                
            $returnme .= '
              <input type="hidden" class="fields" id="'.$count.'" name="'.$count.'" value="'.$row["id"].'" />
              <div id="'.$row["id"].'" class="ui-helper-reset style_options_builder ui-corner-all">
                <div class="option_view">'.$up.' '.$down.'
                    <table style="width:100%">
                        <tr>
                            <td>
                                <strong>'.stripslashes($row["name"]).'</strong> <a href="javascript: void(0)" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'style_builder_option_editor\', parentid: \''.$suboption.'\', styleid: \''.$styleid.'\', id: \''.$row["id"].'\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });"
                                ">'.get_icon("wrench").'</a> 
                            </td>
                            <td style="width: 50px; text-align:right">
                                <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure?\')){
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'style_builder_option_delete\', styleid: \''.$styleid.'\', id: \''.$row["id"].'\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); } } ); }
                                ">'.get_icon("delete").'</a>
                            </td>
                            
                        </tr>
                    </table>
                      
                    <div class="ui-helper-reset ui-corner-all subarea">
                         <table style="width:100%">
                            <tr>
                                <td style="width: 20px; text-align:left">
                                    <a href="javascript: void(0)" onclick="
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'style_builder_option_editor\', parentid: \''.$row["id"].'\', styleid: \''.$styleid.'\' },
                                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                        });
                                    ">'.get_icon("add").'</a> Add Sub Option
                                </td>
                            </tr>
                        </table>                               
                    	'.style_builder_options_tree($styleid,$count,$row["id"]).'
                    </div>
                </div>
            </div>';
            $i++;        
        }   
    }
    return $returnme;    
}

function style_builder_option_editor(){
global $CFG, $MYVARS;
    $styleid = !empty($MYVARS->GET["styleid"]) ? $MYVARS->GET["styleid"] : false;  
    $id = !empty($MYVARS->GET["id"]) ? $MYVARS->GET["id"] : false; 
    $parentid = !empty($MYVARS->GET["parentid"]) ? $MYVARS->GET["parentid"] : false;
    $returnme = "";
    $returnme = $subselect = "";
    $required_array[0]->value = 0;
    $required_array[0]->display = "No";
    $required_array[1]->value = 1;
    $required_array[1]->display = "Yes";
    
    $subselect = '<input type="hidden" class="fields" id="parentchoiceid" name="parentchoiceid" value="0" />';
    
    if($id){ //Editing Option
        $option = get_db_row("SELECT * FROM styles_options WHERE id='$id'");    
        $name = stripslashes($option["name"]);
        $price = $option["price"];
        $required = $option["required"];
        $parentchoiceid = $option["parentchoiceid"];
        $SQL = "SELECT * FROM options ORDER BY name";
        $option_list = '<label class="inputlabel">Option: </label>'.make_select("optionid",get_db_result($SQL),"optionid","name","fields",$option["optionid"]);
    }else{ //Adding Option
        $parentchoiceid = $name = $required = $price = "";
        $SQL = "SELECT * FROM options ORDER BY name";
        $option_list = '<label class="inputlabel">Option: </label>'.make_select("optionid",get_db_result($SQL),"optionid","name","fields");      
    } 

    if($parentid){
        $parent = get_db_row("SELECT * FROM styles_options WHERE id='$parentid'");
        $SQL = "SELECT * FROM choices a JOIN choicelists_choices b ON b.choiceid = a.choiceid WHERE b.choicelistid IN (SELECT choicelistid FROM options_choices WHERE optionid='".$parent["optionid"]."') ORDER BY sort";
        $subselect = '<label class="inputlabel">Show If Parent Is: </label>'.make_select("parentchoiceid",get_db_result($SQL),"choiceid","name","fields",$parentchoiceid,"",true,"1","","Any selection");   
    }

    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                            <input type="button" value="Back" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'style_builder_step_3\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });" 
                            />
                        </td>
                        <td style="text-align:center;">
                            <h2>Upload Image</h2>    
                        </td>
                        <td style="text-align:right;width:15%">
                        <input type="button" value="Save" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'style_builder_option_save\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });" 
                            />                     
                        </td>
                    </tr></table>
                </div>';
                
    if($id){  // Only allow images if editing not new
        $image = get_image(array("type" => "styles", "id" => $styleid));
        $image = empty($image) ? 'images/none.gif' : $image.'_small.jpg';
        $returnme .= '
                    <link rel="stylesheet" href="'.$CFG->wwwroot.'/css/classicTheme/style.css" type="text/css" media="all" /> 
                    <div class="ui-corner-all" id="update_image" style="border: 1px solid silver;margin: 15px;display:inline-block;margin-left:auto;margin-right:auto;">
                        <img src="'.$CFG->wwwroot.'/'.$image.'" style="height:160px;width:160px;" />
                    </div>
                    <br /><strong>Expected Size: 400px x 400px &nbsp;File Type: .jpg</strong><br /><br />
                    <div id="uploader"></div>
                    
                    <script type="text/javascript">
                    $("#uploader").ajaxupload({
                        "url": "ajax/upload.php",
                        "remotePath": "../files/optioninst/'.$id.'/",
                        "maxFiles": 1,
                        "allowExt": ["jpg"],
                        "editFilename": true,
                        "finish": function(filesName){
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'update_optioninst_image\', filename: filesName[0], values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#update_image\').html(data); }
                            });         
                        }
                    });
                </script>';
    }   
     
    $returnme .= '<div style="text-align:left;padding:15px;display: inline-block;">
                    <input type="hidden" class="fields" id="styleid" name="styleid" value="'.$styleid.'" />
                    <input type="hidden" class="fields" id="id" name="id" value="'.$id.'" />
                    <input type="hidden" class="fields" id="parentoptionid" name="parentoptionid" value="'.$parentid.'" />
                    '.$option_list.'<br />
                    <label class="inputlabel">Name: </label><input type="text" id="name" name="name" class="fields" value="'.$name.'" /><br />
                    <label class="inputlabel">Base Price: </label><input class="fields" type="text" name="price" id="price" value="'.$price.'" /><br />
                    <label class="inputlabel">Required: </label>'.make_select_from_array("required",$required_array,"value","display","fields",$required).'<br />
                    '.$subselect.'
                </div><div id="error"></div>';     

    echo $returnme;
}

function update_optioninst_image(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $filename = empty($MYVARS->GET["filename"]) ? false : $MYVARS->GET["filename"];
    
    $id = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "id":
                    $id = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $id = empty($id) ? false : $id;  
    if(!empty($filename) && !empty($id)){
        
        $file = prepare_files("optioninst",$id,$filename);
        
        if($id){
            $SQL = "UPDATE styles_options SET image='$file' WHERE id='$id'";
            execute_db_sql($SQL);
        }
    } 
    echo '<img src="'.$CFG->wwwroot.'/'.get_image(array("type" => "option_instance", "id" => $id)).'_small.jpg" style="height:160px;width:160px;" />';
}

function style_builder_option_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $styleid = $price = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "styleid":
                    $styleid = mysql_real_escape_string($field["value"]);
                    break;
                case "id":
                    $id = mysql_real_escape_string($field["value"]);
                    break;   
                case "required":
                    $required = mysql_real_escape_string($field["value"]);
                    break;        
                case "optionid":
                    $optionid = mysql_real_escape_string($field["value"]);
                    break;
                case "name":
                    $name = mysql_real_escape_string($field["value"]);
                    break;
                case "price":
                    $price = mysql_real_escape_string($field["value"]);
                    break;
                case "parentchoiceid":
                    $parentchoiceid = mysql_real_escape_string($field["value"]);
                    break;
                case "parentoptionid":
                    $parentoptionid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }  
    
    $price = empty($price) ? "0" : $price;
    
    if(!empty($name) && !empty($optionid) && !empty($styleid) && is_numeric($price)){
        if(!empty($id)){ //Update
            $SQL = "UPDATE styles_options SET name='$name',price='$price',required='$required',optionid='$optionid',parentchoiceid='$parentchoiceid',parentoptionid='$parentoptionid' WHERE id='$id'";
            execute_db_sql($SQL);
        }else{ //Save New
            $SQL = "INSERT INTO styles_options (styleid,optionid,name,price,required,parentchoiceid,parentoptionid) VALUES('$styleid','$optionid','$name','$price','$required','$parentchoiceid','$parentoptionid')";
            execute_db_sql($SQL);    
            resort("styles_options","id","styleid='$styleid'");
        }
    } 
    
    style_builder_step_3($styleid);
}

function style_builder_option_delete(){
global $CFG, $MYVARS;
    $styleid = !empty($MYVARS->GET["styleid"]) ? $MYVARS->GET["styleid"] : false;  
    $id = !empty($MYVARS->GET["id"]) ? $MYVARS->GET["id"] : false;
      
    if(!empty($styleid) && !empty($id)){
        $SQL = "DELETE FROM styles_options WHERE id='$id'";
        execute_db_sql($SQL);

        $SQL = "DELETE FROM styles_options WHERE parentoptionid='$id'";
        execute_db_sql($SQL);
        
        resort("styles_options","id","styleid='$styleid'");
    } 
    
    style_builder_step_3($styleid);
}

//
//
// OPTION BUILDER
//
//

function option_builder(){
global $CFG, $MYVARS;
    $returnme = '';
    
    $returnme .= '
    <h2>Option Builder</h2>
        <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'option_builder_step_1\' },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Create New Option</a><br />
        <br />';
    if($result = get_db_result("SELECT * FROM options ORDER BY name")){
        $returnme .= make_select("optionid",$result,"optionid","name","fields").'
        <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'option_builder_step_1\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Edit</a> <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure?\')){
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'option_builder_delete\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); } } ); }
        ">Delete</a>';
    }
    
    echo $returnme;    
}

function option_builder_step_1(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $optionid = $price = $returnme = "";
    $select_text = "Please select a value.";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "optionid":
                    $optionid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    $optionid = !empty($optionid) ? $optionid : false; 
    
    $select_text = "Please select a value.";
    if($optionid){
        $option = get_db_row("SELECT * FROM options WHERE optionid='$optionid'");
        $name = $option["name"]; 
        $select_text = $option["select_text"];  
    }

    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                                <input type="button" value="Back" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'option_builder\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });" 
                                /></td>
                        <td style="text-align:center;">
                             <h2>Step 1</h2>
                        </td>
                        <td style="text-align:right;width:15%">
                            <input type="button" value="Next" onclick="if($(\'#name\').val().length){
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'option_builder_step_1_save\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { if(data == \'false\'){ $(\'#error\').html(\'Error occurred!\'); }else{ 
                                $(\'#optionid\').val(data);
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'option_builder_step_2\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                }); 
                              }}
                            }); }else{ alert(\'Must fill in name.\'); }" />                        
                        </td>
                    </tr></table>
                </div>';
                    
    $returnme .= '
            <div style="display:inline-block;text-align:left;">
            <input class="fields" type="hidden" name="optionid" id="optionid" value="'.$optionid.'" /><br />
            <label class="inputlabel">Option Name: </label><input class="fields" type="text" name="name" id="name" value="'.$name.'" /><br />
            <label class="inputlabel">List Text: </label><input class="fields" type="text" name="select_text" id="select_text" value="'.$select_text.'" /><br />
            </div>
            <div id="error"></div>';
     
    echo $returnme;    
}

function option_builder_step_1_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $price = $optionid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "optionid":
                    $optionid = mysql_real_escape_string($field["value"]);
                    break;
                case "name":
                    $name = mysql_real_escape_string($field["value"]);
                    break;
                case "select_text":
                    $select_text = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $optionid = empty($optionid) ? false : $optionid;  
    
    if(!empty($name) && !empty($select_text)){
        if(!empty($optionid)){
            $SQL = "UPDATE options SET name='$name',select_text='$select_text' WHERE optionid='$optionid'";
            execute_db_sql($SQL);
            echo $optionid;
            exit();
        }else{
            $SQL = "INSERT INTO options (name,select_text) VALUES('$name','$select_text')";
            if($optionid = execute_db_sql($SQL)){ //Added successfully
                echo $optionid;
                exit();
            }            
        }
    } 
    echo "false";
}

function option_builder_delete(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $optionid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "optionid":
                    $optionid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $optionid = empty($optionid) ? false : $optionid;  
    
    if(!empty($optionid)){
        execute_db_sql("DELETE FROM options WHERE optionid='$optionid'");
        execute_db_sql("DELETE FROM options_choices WHERE optionid='$optionid'");
        resort("options_choices","id","optionid='$optionid'");
        destroy($CFG->dirroot.'/files/options/'.$optionid.'/');
    }
    
    option_builder();
}

function option_builder_step_2(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $optionid = $returnme = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "optionid":
                    $optionid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }

    if($optionid){
        $style = get_db_row("SELECT * FROM options WHERE optionid='$optionid'");
        $image = $style["image"];    
    }

    $returnme .= '
            <div class="builder_steps_container">
                <table class="builder_steps"><tr>
                    <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'option_builder_step_1\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />
                    </td>
                    <td style="text-align:center;">
                        <h2>Step 2</h2>
                    </td>
                    <td style="text-align:right;width:15%">
                        <input type="button" value="Next" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'option_builder_step_3\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />                        
                    </td>
                </tr></table>
            </div>';
    
    $image = empty($image) ? 'images/none.gif' : get_image(array("type" => "options", "id" => $optionid)).'_small.jpg';    
    $returnme .= '
            <link rel="stylesheet" href="'.$CFG->wwwroot.'/css/classicTheme/style.css" type="text/css" media="all" /> 
            <div class="ui-corner-all" id="update_image" style="border: 1px solid silver;margin: 15px;display:inline-block;margin-left:auto;margin-right:auto;">
                <img src="'.$CFG->wwwroot.'/'.$image.'" style="height:160px;width:160px;" />
            </div>
            <br /><strong>Expected Size: 400px x 400px &nbsp;File Type: .jpg</strong><br /><br />
            <input class="fields" type="hidden" name="optionid" id="optionid" value="'.$optionid.'" />
            <div id="uploader"></div>
            <div id="error"></div>';
    
    $returnme .= '  <script type="text/javascript">
                        $("#uploader").ajaxupload({
                            "url": "ajax/upload.php",
                            "remotePath": "../files/options/'.$optionid.'/",
                            "maxFiles": 1,
                            "allowExt": ["jpg"],
                            "editFilename": true,
                            "finish": function(filesName){
                                console.log(filesName);
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'option_builder_step_2_save\', filename: filesName[0], values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#update_image\').html(data); refresh_all(); }
                                });         
                            }
                        });
                    </script>';
                
    echo $returnme;    
}

function option_builder_step_2_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $filename = empty($MYVARS->GET["filename"]) ? false : $MYVARS->GET["filename"];
    
    $optionid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "optionid":
                    $optionid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $optionid = empty($optionid) ? false : $optionid;  
    if(!empty($filename) && !empty($optionid)){
        
        $file = prepare_files("options",$optionid,$filename);

        if($optionid){
            $SQL = "UPDATE options SET image='$file' WHERE optionid='$optionid'";
            execute_db_sql($SQL);
        }
    } 
    
    echo '<img src="'.$CFG->wwwroot.'/'.get_image(array("type" => "options", "id" => $optionid)).'_small.jpg" style="height:160px;width:160px;" />';
}

function option_builder_step_3($optionid = false){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $returnme = "";
    if(empty($optionid) && !empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "optionid":
                    $optionid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    $returnme .= '
            <div class="builder_steps_container">
            <table class="builder_steps"><tr>
                <td style="text-align:left;width:15%">
                    <input type="button" value="Back" onclick="
                        $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'option_builder_step_2\', values: $(\'.fields\').serializeArray() },
                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });" 
                    />
                </td>
                <td style="text-align:center;">
                    <h2>Step 3</h2>
                </td>
                <td style="text-align:right;width:15%">                       
                </td>
            </tr></table>
        </div>';   

    $returnme .= '
        <div class="drag_builder ui-corner-all" id="drag_builder">
            <input class="fields" type="hidden" name="optionid" id="optionid" value="'.$optionid.'" /> 
            <a href="javascript: void(0)" onclick="
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'option_builder_option_editor\', optionid: \''.$optionid.'\' },
                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                    });
                ">'.get_icon("add").'</a> Add Choicelist
            '.option_builder_options_tree($optionid).'
        </div><div id="error"></div>';
                
    echo $returnme;
}

function option_builder_resort(){
global $CFG, $MYVARS;
    $id = empty($MYVARS->GET["id"]) ? false : $MYVARS->GET["id"]; 
    $optionid = empty($MYVARS->GET["optionid"]) ? false : $MYVARS->GET["optionid"];
    $direction = empty($MYVARS->GET["direction"]) ? false : $MYVARS->GET["direction"];  
    
    if(!empty($id) && !empty($optionid) && !empty($direction)){
        resort("options_choices","id","optionid='$optionid'",$id,$direction);        
    }
    
    option_builder_step_3($optionid); 
}

function option_builder_options_tree($optionid, &$count = 0, $suboption = 0){
    $returnme = ""; $i=1;
    if($result = get_db_result("SELECT c.*,n.name FROM options o JOIN options_choices c ON c.optionid = o.optionid JOIN choicelists n ON n.choicelistid = c.choicelistid WHERE c.optionid='$optionid' ORDER BY sort")){
        $numrows = mysql_num_rows($result);
        while($row = fetch_row($result)){
                $count++;
                
                $up = $i > 1 ? '<a href="javascript: void(0);" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'option_builder_resort\', optionid: \''.$optionid.'\', direction: \'up\', id: \''.$row["id"].'\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });"
                ">'.get_icon("up").'</a>' : '';
                $down = $i < $numrows ? '<a href="javascript: void(0);" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'option_builder_resort\', optionid: \''.$optionid.'\', direction: \'down\', id: \''.$row["id"].'\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });"
                ">'.get_icon("down").'</a>' : '';
                
                $returnme .= '
                  <input type="hidden" class="fields" id="'.$count.'" name="'.$count.'" value="'.$row["id"].'" />
                  <div id="'.$row["id"].'" class="ui-helper-reset style_options_builder ui-corner-all">
                    <div class="option_view">'.$up.' '.$down.'
                        <table style="width:100%">
                            <tr>
                                <td>
                                    <strong>'.$row["name"].'</strong> <a href="javascript: void(0)" onclick="
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'option_builder_option_editor\', optionid: \''.$row["optionid"].'\', id: \''.$row["id"].'\' },
                                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                        });"
                                    ">'.get_icon("wrench").'</a> 
                                </td>
                                <td style="width: 50px; text-align:right">
                                    <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure?\')){
                                         $.ajax({
                                              type: \'POST\',
                                              url: \'ajax/ajax.php\',
                                              data: { action: \'option_builder_choicelist_delete\', optionid: \''.$row["optionid"].'\', id: \''.$row["id"].'\' },
                                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); } } ); }
                                    ">'.get_icon("delete").'</a>
                                </td>
                            </tr>
                        </table>
                    </div>
            </div>';  
            $i++;      
        }   
    }
    return $returnme;    
}

function option_builder_option_editor(){
global $CFG, $MYVARS;
    $optionid = !empty($MYVARS->GET["optionid"]) ? $MYVARS->GET["optionid"] : false;  
    $id = !empty($MYVARS->GET["id"]) ? $MYVARS->GET["id"] : false; 
    
    $returnme = "";

    if($id){ //Editing Option
        $option = get_db_row("SELECT * FROM options_choices WHERE id='$id'");    
        $SQL = "SELECT * FROM choicelists ORDER BY name";
        $choice_list = '<label class="inputlabel">Choicelist: </label>'.make_select("choicelistid",get_db_result($SQL),"choicelistid","name","fields",$option["choicelistid"]);
    }else{ //Adding Option
        $SQL = "SELECT * FROM choicelists ORDER BY name";
        $choice_list = '<label class="inputlabel">Choicelist: </label>'.make_select("choicelistid",get_db_result($SQL),"choicelistid","name","fields");      
    } 

    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                            <input type="button" value="Back" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'option_builder_step_3\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });" 
                            />
                        </td>
                        <td style="text-align:center;">
                            
                        </td>
                        <td style="text-align:right;width:15%">
                        <input type="button" value="Save" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'option_builder_choicelist_save\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });" 
                            />                     
                        </td>
                    </tr></table>
                </div>';
                     
    $returnme .= '  <div style="text-align:left;padding:15px;display: inline-block;">
                        <input type="hidden" class="fields" id="optionid" name="optionid" value="'.$optionid.'" />
                        <input type="hidden" class="fields" id="id" name="id" value="'.$id.'" />
                        '.$choice_list.'<br />
                    </div><div id="error"></div>';     

    echo $returnme;
}

function option_builder_choicelist_delete(){
global $CFG, $MYVARS;
    $optionid = !empty($MYVARS->GET["optionid"]) ? $MYVARS->GET["optionid"] : false;  
    $id = !empty($MYVARS->GET["id"]) ? $MYVARS->GET["id"] : false; 
  
    if(!empty($optionid) && !empty($id)){
        $SQL = "DELETE FROM options_choices WHERE id='$id'";
        execute_db_sql($SQL);
        resort("options_choices","id","optionid='$optionid'");
    } 

    option_builder_step_3($optionid);
}

function option_builder_choicelist_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $optionid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "optionid":
                    $optionid = mysql_real_escape_string($field["value"]);
                    break;
                case "id":
                    $id = mysql_real_escape_string($field["value"]);
                    break;           
                case "choicelistid":
                    $choicelistid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }
    if(!empty($optionid) && !empty($choicelistid)){
        if(!empty($id)){ //Update
            $SQL = "UPDATE options_choices SET optionid='$optionid',choicelistid='$choicelistid' WHERE id='$id'";
            execute_db_sql($SQL);
        }else{ //Save New
            $SQL = "INSERT INTO options_choices (optionid,choicelistid) VALUES('$optionid','$choicelistid')";
            resort("options_choices","id","optionid='$optionid'");
            execute_db_sql($SQL);    
        }
    } 

    option_builder_step_3($optionid);
}


//
//
// CHOICELIST BUILDER
//
//

function choicelist_builder(){
global $CFG, $MYVARS;
    $returnme = '';
    
    $returnme .= '
    <h2>Choicelist Builder</h2>
        <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'choicelist_builder_step_1\' },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Create New Choicelist</a><br />
        <br />';
        if($result = get_db_result("SELECT * FROM choicelists ORDER BY name")){
            $returnme .= make_select("choicelistid",$result,"choicelistid","name","fields").'
            <a href="javascript: void(0)" onclick="
                $.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'choicelist_builder_step_1\', values: $(\'.fields\').serializeArray() },
                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                });
            ">Edit</a> <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure?\')){
                $.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'choicelist_builder_delete\', values: $(\'.fields\').serializeArray() },
                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); } } ); }
            ">Delete</a>';    
        }
    
    echo $returnme;    
}

function choicelist_builder_step_1(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $choicelistid = $returnme = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choicelistid":
                    $choicelistid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    $choicelistid = !empty($choicelistid) ? $choicelistid : false; 
    
    if($choicelistid){
        $choicelist = get_db_row("SELECT * FROM choicelists WHERE choicelistid='$choicelistid'");
        $name = $choicelist["name"];  
    }

    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'choicelist_builder\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });" 
                                /></td>
                        <td style="text-align:center;">
                            <h2>Step 1</h2>    
                        </td>
                        <td style="text-align:right;width:15%">
                            <input type="button" value="Next" onclick="if($(\'#name\').val().length){
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'choicelist_builder_step_1_save\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { if(data == \'false\'){ $(\'#error\').html(\'Error occurred!\'); }else{ 
                                $(\'#choicelistid\').val(data);
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'choicelist_builder_step_2\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                }); 
                              }}
                            }); }else{ alert(\'Must fill in name.\'); }" />                        
                        </td>
                    </tr></table>
                </div>';
                    
    $returnme .= '<input class="fields" type="hidden" name="choicelistid" id="choicelistid" value="'.$choicelistid.'" /><br />
                <label class="inputlabel">Choicelist Name: </label><input class="fields" type="text" name="name" id="name" value="'.$name.'" /><br />
                <div id="error"></div>';

    echo $returnme;    
}

function choicelist_builder_step_1_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $choicelistid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choicelistid":
                    $choicelistid = mysql_real_escape_string($field["value"]);
                    break;
                case "name":
                    $name = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $choicelistid = empty($choicelistid) ? false : $choicelistid;  
    
    if(!empty($name)){
        if(!empty($choicelistid)){
            $SQL = "UPDATE choicelists SET name='$name' WHERE choicelistid='$choicelistid'";
            execute_db_sql($SQL);
            echo $choicelistid;
            exit();
        }else{
            $SQL = "INSERT INTO choicelists (name) VALUES('$name')";
            if($choicelistid = execute_db_sql($SQL)){ //Added successfully
                echo $choicelistid;
                exit();
            }            
        }
    } 
    echo "false";
}

function choicelist_builder_delete(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $choicelistid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choicelistid":
                    $choicelistid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $choicelistid = empty($choicelistid) ? false : $choicelistid;  
    
    if(!empty($choicelistid)){
        execute_db_sql("DELETE FROM choicelists WHERE choicelistid='$choicelistid'");
        execute_db_sql("DELETE FROM choicelists_choices WHERE choicelistid='$choicelistid'");
        resort("choicelists_choices","id","choicelistid='$choicelistid'");
    }
    
    choicelist_builder();
}

function choicelist_builder_step_2($choicelistid = false){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $returnme = "";
    
    if(empty($choicelistid) && !empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choicelistid":
                    $choicelistid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }
   
    $returnme .= '
            <div class="builder_steps_container">
                <table class="builder_steps"><tr>
                    <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'choicelist_builder_step_1\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />
                    </td>
                    <td style="text-align:center;">
                        <h2>Step 2</h2>
                    </td>
                    <td style="text-align:right;width:15%">                       
                    </td>
                </tr></table>
            </div>';
            
    $returnme .= '
            <div class="drag_builder ui-corner-all" id="drag_builder">
                <input class="fields" type="hidden" name="choicelistid" id="choicelistid" value="'.$choicelistid.'" /> 
                <a href="javascript: void(0)" onclick="
                        $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'choicelist_builder_option_editor\', choicelistid: \''.$choicelistid.'\' },
                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });
                    ">'.get_icon("add").'</a> Add Choice
                '.choicelist_builder_choice_tree($choicelistid).'
            </div><div id="error"></div>';
     
    echo $returnme;
}

function choicelist_builder_resort(){
global $CFG, $MYVARS;
    $id = empty($MYVARS->GET["id"]) ? false : $MYVARS->GET["id"]; 
    $choicelistid = empty($MYVARS->GET["choicelistid"]) ? false : $MYVARS->GET["choicelistid"];
    $direction = empty($MYVARS->GET["direction"]) ? false : $MYVARS->GET["direction"];  
    
    if(!empty($id) && !empty($choicelistid) && !empty($direction)){
        resort("choicelists_choices","id","choicelistid='$choicelistid'",$id,$direction);        
    }
    
    choicelist_builder_step_2($choicelistid); 
}

function choicelist_builder_choice_tree($choicelistid, &$count = 0, $suboption = 0){
    $returnme = ""; $i=1;
    if($result = get_db_result("SELECT c.*,o.name FROM choices o JOIN choicelists_choices c ON c.choiceid = o.choiceid WHERE c.choicelistid='$choicelistid' ORDER BY sort")){
        $numrows = mysql_num_rows($result);
        while($row = fetch_row($result)){
                $count++;
                
                $up = $i > 1 ? '<a href="javascript: void(0);" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'choicelist_builder_resort\', choicelistid: \''.$choicelistid.'\', direction: \'up\', id: \''.$row["id"].'\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });"
                ">'.get_icon("up").'</a>' : '';
                $down = $i < $numrows ? '<a href="javascript: void(0);" onclick="
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'choicelist_builder_resort\', choicelistid: \''.$choicelistid.'\', direction: \'down\', id: \''.$row["id"].'\' },
                                      success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                    });"
                ">'.get_icon("down").'</a>' : '';
                
                $returnme .= '
                  <input type="hidden" class="fields" id="'.$count.'" name="'.$count.'" value="'.$row["id"].'" />
                  <div id="'.$row["id"].'" class="ui-helper-reset style_choicelists_builder ui-corner-all">
                    <div class="option_view">'.$up.' '.$down.'
                        <table style="width:100%">
                            <tr>
                                <td>
                                    <strong>'.$row["name"].'</strong> <a href="javascript: void(0)" onclick="
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'choicelist_builder_option_editor\', choicelistid: \''.$row["choicelistid"].'\', id: \''.$row["id"].'\' },
                                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                        });"
                                    ">'.get_icon("wrench").'</a> 
                                </td>
                                <td style="width: 50px; text-align:right">
                                    <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure?\')){
                                         $.ajax({
                                              type: \'POST\',
                                              url: \'ajax/ajax.php\',
                                              data: { action: \'choicelist_builder_choice_delete\', choicelistid: \''.$row["choicelistid"].'\', id: \''.$row["id"].'\' },
                                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); } } ); }
                                    ">'.get_icon("delete").'</a>
                                </td>
                            </tr>
                        </table>
                    </div>
            </div>';   
            $i++;     
        }   
    }
    return $returnme;    
}

function choicelist_builder_option_editor(){
global $CFG, $MYVARS;
    $choicelistid = !empty($MYVARS->GET["choicelistid"]) ? $MYVARS->GET["choicelistid"] : false;  
    $id = !empty($MYVARS->GET["id"]) ? $MYVARS->GET["id"] : false; 

    $default_array[0]->value = 0;
    $default_array[0]->display = "No";
    $default_array[1]->value = 1;
    $default_array[1]->display = "Yes";
        
    $returnme = "";
    if($id){ //Editing Option
        $choicelist_choice = get_db_row("SELECT * FROM choicelists_choices WHERE id='$id'");    
        $SQL = "SELECT * FROM choices WHERE choiceid NOT IN (SELECT choiceid FROM choicelists_choices WHERE choicelistid='$choicelistid' AND choiceid!='".$choicelist_choice["choiceid"]."') ORDER BY name";
        $choice_list = '<label class="inputlabel">Choice: </label>'.make_select("choiceid",get_db_result($SQL),"choiceid","name","fields",$choicelist_choice["choiceid"],"",false,"1","","",false);
        $default_list = '<label class="inputlabel">Default Choice: </label>'.make_select_from_array("defaultsetting",$default_array,"value","display","fields",$choicelist_choice["defaultsetting"]);
    }else{ //Adding Option
        $SQL = "SELECT * FROM choices WHERE choiceid NOT IN (SELECT choiceid FROM choicelists_choices WHERE choicelistid='$choicelistid') ORDER BY name";
        $choice_list = '<label class="inputlabel">Choice: </label>'.make_select("choiceid",get_db_result($SQL),"choiceid","name","fields",false,"",false,"10","","",false,true);   
        $default_list = '<label class="inputlabel">Default Choice: </label>'.make_select_from_array("defaultsetting",$default_array,"value","display","fields");  
    } 

    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                            <input type="button" value="Back" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'choicelist_builder_step_2\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });" 
                            />
                        </td>
                        <td style="text-align:center;">
                            
                        </td>
                        <td style="text-align:right;width:15%">
                        <input type="button" value="Save" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'choicelist_builder_choice_save\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });" 
                            />                     
                        </td>
                    </tr></table>
                </div>';
                     
    $returnme .= '  <div style="text-align:left;padding:15px;display: inline-block;">
                        <input type="hidden" class="fields" id="choicelistid" name="choicelistid" value="'.$choicelistid.'" />
                        <input type="hidden" class="fields" id="id" name="id" value="'.$id.'" />
                        '.$choice_list.'<br />
                        '.$default_list.'<br />
                    </div><div id="error"></div>';     

    echo $returnme;
}

function choicelist_builder_choice_delete(){
global $CFG, $MYVARS;
    $choicelistid = !empty($MYVARS->GET["choicelistid"]) ? $MYVARS->GET["choicelistid"] : false;  
    $id = !empty($MYVARS->GET["id"]) ? $MYVARS->GET["id"] : false; 
  
    if(!empty($choicelistid) && !empty($id)){
        $SQL = "DELETE FROM choicelists_choices WHERE id='$id'";
        execute_db_sql($SQL);
        resort("choicelists_choices","id","choicelistid='$choicelistid'");
    } 

    choicelist_builder_step_2($choicelistid);
}

function choicelist_builder_choice_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $choicelistid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choicelistid":
                    $choicelistid = mysql_real_escape_string($field["value"]);
                    break;
                case "choiceid":
                    $choiceid[] = mysql_real_escape_string($field["value"]);
                    break;
                case "defaultsetting":
                    $defaultsetting = mysql_real_escape_string($field["value"]);
                    break;
                case "id":
                    $id = mysql_real_escape_string($field["value"]);
                    break;           
            }   
        }
    }
    if(!empty($choicelistid) && !empty($choiceid)){
        if(!empty($defaultsetting)){ //Make sure other choices are NOT default
            $SQL = "UPDATE choicelists_choices c SET c.defaultsetting=0 WHERE c.choicelistid='$choicelistid'";
            execute_db_sql($SQL);    
        }
        if(!empty($id)){ //Update
            $SQL = "UPDATE choicelists_choices c SET c.choicelistid='$choicelistid',c.choiceid='".$choiceid[0]."',c.defaultsetting='$defaultsetting' WHERE c.id='$id'";
            execute_db_sql($SQL);
        }else{ //Save New
            $sort = get_db_count("SELECT * FROM choicelists_choices WHERE choicelistid='$choicelistid'");
            foreach($choiceid as $chid){
                $sort++;
                $SQL = "INSERT INTO choicelists_choices (choicelistid,choiceid,defaultsetting,sort) VALUES('$choicelistid','$chid','$defaultsetting','$sort')";
                execute_db_sql($SQL);                 
            }
            resort("choicelists_choices","id","choicelistid='$choicelistid'");  
        }
    } 

    choicelist_builder_step_2($choicelistid);
}

//
//
// CHOICE BUILDER
//
//

function choice_builder(){
global $CFG, $MYVARS;
    $returnme = '';
    
    $returnme .= '
    <h2>Choice Builder</h2>
        <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'choice_builder_step_1\' },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Create New Choice</a><br />
        <br />';
        
    if($result = get_db_result("SELECT * FROM choices ORDER BY name")){
        $returnme .= make_select("choiceid",$result,"choiceid","name","fields ui-helper-reset",false,"",true).'
        <br /><a href="javascript: void(0)" onclick="if($(\'#choiceid\').val()){
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'choice_builder_step_1\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            }); }
        ">Edit</a> <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure?\')){
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'choice_builder_delete\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); } } ); }
        ">Delete</a>';   
        
        $returnme .= '
        <script type="text/javascript">
	(function( $ ) {
		$.widget( "ui.combobox", {
			_create: function() {
				var input,
					self = this,
					select = this.element.hide(),
					selected = select.children( ":selected" ),
					value = selected.val() ? selected.text() : "",
					wrapper = this.wrapper = $( "<span>" )
						.addClass( "ui-combobox" )
						.insertAfter( select );

				input = $( "<input>" )
					.appendTo( wrapper )
					.val( value )
					.addClass( "ui-state-default ui-combobox-input" )
					.autocomplete({
						delay: 0,
						minLength: 0,
						source: function( request, response ) {
							var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
							response( select.children( "option" ).map(function() {
								var text = $( this ).text();
								if ( this.value && ( !request.term || matcher.test(text) ) )
									return {
										label: text.replace(
											new RegExp(
												"(?![^&;]+;)(?!<[^<>]*)(" +
												$.ui.autocomplete.escapeRegex(request.term) +
												")(?![^<>]*>)(?![^&;]+;)", "gi"
											), "<strong>$1</strong>" ),
										value: text,
										option: this
									};
							}) );
						},
						select: function( event, ui ) {
							ui.item.option.selected = true;
							self._trigger( "selected", event, {
								item: ui.item.option
							});
						},
						change: function( event, ui ) {
							if ( !ui.item ) {
								var matcher = new RegExp( "^" + $.ui.autocomplete.escapeRegex( $(this).val() ) + "$", "i" ),
									valid = false;
								select.children( "option" ).each(function() {
									if ( $( this ).text().match( matcher ) ) {
										this.selected = valid = true;
										return false;
									}
								});
								if ( !valid ) {
									// remove invalid value, as it didn\'t match anything
									$( this ).val( "" );
									select.val( "" );
									input.data( "autocomplete" ).term = "";
									return false;
								}
							}
						}
					})
					.addClass( "ui-widget ui-widget-content ui-corner-left" );

				input.data( "autocomplete" )._renderItem = function( ul, item ) {
					return $( "<li></li>" )
						.data( "item.autocomplete", item )
						.append( "<a>" + item.label + "</a>" )
						.appendTo( ul );
				};

				$( "<a>" )
					.attr( "tabIndex", -1 )
					.attr( "title", "Show All Items" )
					.appendTo( wrapper )
					.button({
						icons: {
							primary: "ui-icon-triangle-1-s"
						},
						text: false
					})
					.removeClass( "ui-corner-all" )
					.addClass( "ui-corner-right ui-combobox-toggle" )
					.click(function() {
						// close if already visible
						if ( input.autocomplete( "widget" ).is( ":visible" ) ) {
							input.autocomplete( "close" );
							return;
						}

						// work around a bug (likely same cause as #5265)
						$( this ).blur();

						// pass empty string as value to search for, displaying all results
						input.autocomplete( "search", "" );
						input.focus();
					});
			},

			destroy: function() {
				this.wrapper.remove();
				this.element.show();
				$.Widget.prototype.destroy.call( this );
			}
		});
	})( jQuery );

	$(function() {
		$( "#choiceid" ).combobox();
	});
	</script>

        '; 
    }
    
    echo $returnme;    
}

function choice_builder_step_1(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $returnme = "";
    $name = $choiceid = $price = $description = "";

    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choiceid":
                    $choiceid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    $choiceid = !empty($choiceid) ? $choiceid : false; 

    if($choiceid){
        $choice = get_db_row("SELECT * FROM choices WHERE choiceid='$choiceid'");
        $name = $choice["name"]; 
        $description = $choice["description"]; 
        $price = $choice["price"];  
    }

    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                            <input type="button" value="Back" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'choice_builder\' },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });" 
                            /></td>
                        <td style="text-align:center;">
                            <h2>Step 1</h2>
                        </td>
                        <td style="text-align:right;width:15%">
                            <input type="button" value="Next" onclick="if($(\'#name\').val().length){
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'choice_builder_step_1_save\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { if(data == \'false\'){ $(\'#error\').html(\'Error occurred!\'); }else{ 
                                $(\'#choiceid\').val(data);
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'choice_builder_step_2\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                }); 
                              }}
                            }); }else{ alert(\'Must fill in name.\'); }" />                        
                        </td>
                    </tr></table>
                </div>';
                    
    $returnme .= '
            <input class="fields" type="hidden" name="choiceid" id="choiceid" value="'.$choiceid.'" /><br />
            <label class="inputlabel">Choice Name: </label><input class="fields" type="text" name="name" id="name" value="'.$name.'" /><br />
            <label class="inputlabel">Base Price: </label><input class="fields" type="text" name="price" id="price" value="'.$price.'" /><br />
            <label class="inputlabel">Description: </label><textarea class="fields" name="description" id="description" >'.$description.'</textarea><br />
            <div id="error"></div>';
    
    echo $returnme;    
}

function choice_builder_step_1_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $price = $choiceid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choiceid":
                    $choiceid = mysql_real_escape_string($field["value"]);
                    break;
                case "name":
                    $name = mysql_real_escape_string($field["value"]);
                    break;
                case "description":
                    $description = mysql_real_escape_string($field["value"]);
                    break;
                case "price":
                    $price = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $choiceid = empty($choiceid) ? false : $choiceid;  
    $price = empty($price) ? "0" : $price;  
    $description = empty($description) ? "" : $description;  
        
    if(!empty($name) && is_numeric($price)){
        if(!empty($choiceid)){
            $SQL = "UPDATE choices SET name='$name',price='$price',description='$description' WHERE choiceid='$choiceid'";
            execute_db_sql($SQL);
            echo $choiceid;
            exit();
        }else{
            $SQL = "INSERT INTO choices (name,price,description) VALUES('$name','$price','$description')";
            if($choiceid = execute_db_sql($SQL)){ //Added successfully
                echo $choiceid;
                exit();
            }            
        }
    } 
    echo "false";
}

function choice_builder_delete(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $choiceid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choiceid":
                    $choiceid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $choiceid = empty($choiceid) ? false : $choiceid;  
    
    if(!empty($choiceid)){
        execute_db_sql("DELETE FROM choices WHERE choiceid='$choiceid'");
        execute_db_sql("DELETE FROM choicelists_choices WHERE choiceid='$choiceid'");
        destroy($CFG->dirroot.'/files/choices/'.$choiceid.'/');
    }
    
    choice_builder();
}

function choice_builder_step_2(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $returnme = $choiceid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choiceid":
                    $choiceid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }

    if($choiceid){
        $choice = get_db_row("SELECT * FROM choices WHERE choiceid='$choiceid'");
        $image = $choice["image"];    
    }

    $returnme .= '
            <div class="builder_steps_container">
                <table class="builder_steps"><tr>
                    <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'choice_builder_step_1\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />
                    </td>
                    <td style="text-align:center;">
                        <h2>Step 2</h2>   
                    </td>
                    <td style="text-align:right;width:15%">                      
                    </td>
                </tr></table>
            </div>';
    $image = empty($image) ? 'images/none.gif' : get_image(array("type" => "choices", "id" => $choiceid)).'_large.jpg';                   
    $returnme .= '
        <link rel="stylesheet" href="'.$CFG->wwwroot.'/css/classicTheme/style.css" type="text/css" media="all" /> 
        <div class="ui-corner-all" id="update_image" style="border: 1px solid silver;margin: 15px;display:inline-block;margin-left:auto;margin-right:auto;">
            <img src="'.$CFG->wwwroot.'/'.$image.'" style="height:160px;width:160px;" />
        </div>
        <br />
        <strong>Expected Size: 160px x 160px &nbsp;File Type: .jpg</strong><br /><br />
        <input class="fields" type="hidden" name="choiceid" id="choiceid" value="'.$choiceid.'" />
        <div id="uploader"></div>
        <div id="error"></div>';
    
    $returnme .= '  <script type="text/javascript">
                        $("#uploader").ajaxupload({
                            "url": "ajax/upload.php",
                            "remotePath": "../files/choices/'.$choiceid.'/",
                            "maxFiles": 1,
                            "allowExt": ["jpg"],
                            "editFilename": true,
                            "finish": function(filesName){
                                console.log(filesName);
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'choice_builder_step_2_save\', filename: filesName[0], values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#update_image\').html(data); refresh_all(); }
                                });         
                            }
                        });
                    </script>';
                
    echo $returnme;    
}

function choice_builder_step_2_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $filename = empty($MYVARS->GET["filename"]) ? false : $MYVARS->GET["filename"];
    
    $choiceid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "choiceid":
                    $choiceid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $choiceid = empty($choiceid) ? false : $choiceid;  
    if(!empty($filename) && !empty($choiceid)){
        
        $file = prepare_files("choices",$choiceid,$filename,"64");

        if($choiceid){
            $SQL = "UPDATE choices SET image='$file' WHERE choiceid='$choiceid'";
            execute_db_sql($SQL);
        }
    } 
    echo '<img src="'.$CFG->wwwroot.'/'.get_image(array("type" => "choices", "id" => $choiceid)).'_large.jpg" style="height:160px;width:160px;" />';
}



//
//
// PAGE BUILDER
//
//

function page_builder(){
global $CFG, $MYVARS;
    $returnme = '';
    
    $returnme .= '
    <h2>Page Builder</h2>
        <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'page_builder_step_1\' },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Create New Page</a><br />
        <br />';
    if($result = get_db_result("SELECT * FROM pages ORDER BY name")){
        $returnme .= make_select("pageid",$result,"pageid","name","fields").'
        <a href="javascript: void(0)" onclick="
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'page_builder_step_1\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
            });
        ">Edit</a> <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure?\')){
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'page_builder_delete\', values: $(\'.fields\').serializeArray() },
              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); } } ); }
        ">Delete</a>';    
    }
    
    echo $returnme;    
}

function page_builder_step_1(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $pageid = $returnme = "";

    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "pageid":
                    $pageid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }        
    }

    $pageid = !empty($pageid) ? $pageid : false; 

    if($pageid){
        $page = get_db_row("SELECT * FROM pages WHERE pageid='$pageid'");
        $name = $page["name"]; 
    }

    $returnme .= '
                <div class="builder_steps_container">
                    <table class="builder_steps"><tr>
                        <td style="text-align:left;width:15%">
                            <input type="button" value="Back" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'page_builder\' },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                });" 
                            />
                        </td>
                        <td style="text-align:center;">
                            <h2>Step 1</h2>
                        </td>
                        <td style="text-align:right;width:15%">
                            <input type="button" value="Next" onclick="if($(\'#name\').val().length){
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'page_builder_step_1_save\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { if(data == \'false\'){ $(\'#error\').html(\'Error occurred!\'); }else{ 
                                $(\'#pageid\').val(data);
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'page_builder_step_2\', values: $(\'.fields\').serializeArray() },
                                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                                }); 
                              }}
                            }); }else{ alert(\'Must fill in name.\'); }" />                        
                        </td>
                    </tr></table>
                </div>';
                    
    $returnme .= '
            <input class="fields" type="hidden" name="pageid" id="pageid" value="'.$pageid.'" /><br />
            <label class="inputlabel">Page Name: </label><input class="fields" type="text" name="name" id="name" value="'.$name.'" /><br />
            <div id="error"></div>';
    
    echo $returnme;    
}

function page_builder_step_1_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $name = $pageid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "pageid":
                    $pageid = mysql_real_escape_string($field["value"]);
                    break;
                case "name":
                    $name = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $pageid = empty($pageid) ? false : $pageid;    
    $description = empty($description) ? "" : $description;  
        
    if(!empty($name)){
        if(!empty($pageid)){
            $SQL = "UPDATE pages SET name='$name' WHERE pageid='$pageid'";
            execute_db_sql($SQL);
            echo $pageid;
            exit();
        }else{
            $SQL = "INSERT INTO pages (name,contents) VALUES('$name','')";
            if($pageid = execute_db_sql($SQL)){ //Added successfully
                echo $pageid;
                exit();
            }            
        }
    } 
    echo "false";
}

function page_builder_delete(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $pageid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "pageid":
                    $pageid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    $pageid = empty($pageid) ? false : $pageid;  
    
    if(!empty($pageid)){
        $page = get_db_row("SELECT * FROM pages WHERE pageid='$pageid'");
        if(empty($page["builder"])){
            execute_db_sql("DELETE FROM pages WHERE pageid='$pageid'");
            destroy($CFG->dirroot.'/files/pages/'.$pageid.'/');    
        }
    }
    
    page_builder();
}

function page_builder_step_2($pageid = false){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $returnme = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "pageid":
                    $pageid = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }

    if($pageid){
        $page = get_db_row("SELECT * FROM pages WHERE pageid='$pageid'");
        $contents = $page["contents"];    
    }
    
    $_SESSION['pageid'] = $pageid;
    $_SESSION['dir'] = $CFG->dirroot;
    $_SESSION['www'] = $CFG->wwwroot;
    recursive_mkdir($CFG->dirroot . '/files/pages/'.$pageid);

    $returnme .= '
            <div class="builder_steps_container">
                <table class="builder_steps"><tr>
                    <td style="text-align:left;width:15%">
                        <input type="button" value="Back" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'page_builder_step_1\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />
                    </td>
                    <td style="text-align:center;">
                        <h2>Step 2</h2>
                    </td>
                    <td style="text-align:right;width:15%">
                        <input type="button" value="Save" onclick="
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'page_builder_step_2_save\', values: $(\'.fields\').serializeArray() },
                              success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });" 
                        />                        
                    </td>
                </tr></table>
            </div>';
                    
    $returnme .= '
        <script type="text/javascript" src="'.$CFG->wwwroot.'/scripts/tinymce/jscripts/tiny_mce/jquery.tinymce.js"></script>
        <script type="text/javascript">
        $(function() {
                $(\'textarea.tinymce\').tinymce({
                        // Location of TinyMCE script
                        script_url : \''.$CFG->wwwroot.'/scripts/tinymce/jscripts/tiny_mce/tiny_mce.js\',

                        // General options
                        theme : "advanced",
                        plugins : "imageupload,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",

                        // Theme options
                        theme_advanced_buttons1 : "save,newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect",
                        theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,imageupload,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
                        theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
                        theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,pagebreak",
                        theme_advanced_toolbar_location : "top",
                        theme_advanced_toolbar_align : "left",
                        theme_advanced_statusbar_location : "bottom",
                        theme_advanced_resizing : true,

                        // Example content CSS (should be your site CSS)
                        //content_css : "css/styles.css",

                        // Drop lists for link/image/media/template dialogs
                        template_external_list_url : "lists/template_list.js",
                        external_link_list_url : "lists/link_list.js",
                        external_image_list_url : "lists/image_list.js",
                        media_external_list_url : "lists/media_list.js",
                });
        });
        </script>

        <form method="post" action="save_page()">
                <input class="fields" type="hidden" name="pageid" id="pageid" value="'.$pageid.'" />
                <textarea id="content" name="content" class="tinymce fields" style="width:100%;">
                    '.stripslashes($contents).'
                </textarea>
        </form>    
        <div id="error"></div>';
                    
    echo $returnme;    
}

function page_builder_step_2_save(){
global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    
    $pageid = "";
    if(!empty($fields)){
        foreach($fields as $field){
            switch ($field["name"]) {
                case "pageid":
                    $pageid = mysql_real_escape_string($field["value"]);
                    break;
                case "content":
                    $content = mysql_real_escape_string($field["value"]);
                    break;
            }   
        }
    }    
    
    $pageid = empty($pageid) ? false : $pageid; 
    $content = empty($content) ? '' : $content;
    if(!empty($content) && !empty($pageid)){

        if($pageid){
            $SQL = "UPDATE pages SET contents='$content' WHERE pageid='$pageid'";
            execute_db_sql($SQL);
        }
    } 
    page_builder_step_1($pageid);
}

function function_template(){
global $CFG, $MYVARS;
    $returnme = '';
    
    $returnme .= '
    
    ';
    
    echo $returnme;    
}
?>
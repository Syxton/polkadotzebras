<?php
/***************************************************************************
* pagelib.php - Page function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/13/2012
* Revision: 3.0.8
***************************************************************************/

if(!isset($LIBHEADER)){ include ('header.php'); }
$PAGELIB = true;

function callfunction(){
global $MYVARS;
    if(empty($_POST["aslib"])){
        //Retrieve from Javascript
        $postorget = isset($_POST["action"]) ? $_POST : false;
        if(empty($MYVARS)){ $MYVARS = new stdClass(); }
        $MYVARS->GET = !$postorget && isset($_GET["action"]) ? $_GET : $postorget;
        if(function_exists($MYVARS->GET["action"])){
        	$action = $MYVARS->GET["action"];
        	$action(); //Go to the function that was called.
        }else{ echo get_page_error_message("no_function",array($MYVARS->GET["action"])); }    
    }
}

function postorget(){
global $MYVARS;
	//Retrieve from Javascript
	$postorget = isset($_GET["action"]) ? $_GET : $_POST;
	$postorget = isset($postorget["action"]) ? $postorget : "";
	$MYVARS->GET = $postorget;
	if($postorget != ""){
		return $postorget["action"];
	}
	return false;
}

function make_select($name, $values, $valuename, $displayname, $class = "", $selected = false, $onchange = "", $leadingblank = false, $size=1, $style="", $leadingblanktitle="",$excludevalue=false,$multiple=false){
	$multiple = empty($multiple) ? "" : 'multiple="multiple"';
    $returnme = '<select class="'.$class.'" '.$multiple.' size="'.$size.'" id="' . $name . '" name="' . $name . '" ' . $onchange . ' style="'.$style.'" >';
	if($leadingblank){ $returnme .= '<option value="">'.$leadingblanktitle.'</option>'; }
	if($values){
		while($row = fetch_row($values)){
			if(!$excludevalue || ($excludevalue && $excludevalue != $row[$valuename])){
				$returnme .= $row[$valuename] == $selected ? '<option value="' . $row[$valuename] . '" selected="selected">' . stripslashes($row[$displayname]) . '</option>' : '<option value="' . $row[$valuename] . '">' . stripslashes($row[$displayname]) . '</option>';
			}
		}
	}
	$returnme .= '</select>';
	return $returnme;
}

function make_select_from_array($name, $values, $valuename, $displayname, $class = "", $selected = false, $width = "", $onchange = "", $leadingblank = false, $size=1, $style="",$leadingblanktitle="",$excludevalue=false,$multiple=false){
	$multiple = empty($multiple) ? "" : 'multiple="multiple"';
    $returnme = '<select class="'.$class.'" '.$multiple.' size="'.$size.'" id="' . $name . '" name="' . $name . '" ' . $onchange . ' ' . $width . ' style="'.$style.'">';
	if($leadingblank){ $returnme .= '<option value="">'.$leadingblanktitle.'</option>';}
	foreach($values as $value){
		if(!$excludevalue || ($excludevalue && $excludevalue != $value->$valuename)){
			$returnme .= $value->$valuename == $selected ? '<option value="' . $value->$valuename . '" selected="selected">' . stripslashes($value->$displayname) . '</option>' : '<option value="' . $value->$valuename . '">' . stripslashes($value->$displayname) . '</option>';
		}	   
	}
    
	$returnme .= '</select>';
	return $returnme;
}

function get_icon($icon){
    global $CFG;
    return '<img style="background:0;" src="'.$CFG->wwwroot . "/images/icons/$icon.png".'" />';    
}

function get_name($vars){
    $name = "";
    if(!empty($vars["type"]) && !empty($vars["id"])){
        switch($vars["type"]){
            case "styles":
                $name = get_db_row("SELECT * FROM styles WHERE styleid='".$vars["id"]."'");
                $name = $name["name"];
            break;
            case "creations":
                $name = get_db_row("SELECT * FROM creations WHERE creationid='".$vars["id"]."'");
                $name = $name["name"];
            break;
            case "options":
                $name = get_db_row("SELECT * FROM options WHERE optionid='".$vars["id"]."'");
                $name = $name["name"];
            break;
            case "option_instance":
                $name = get_db_row("SELECT * FROM styles_options WHERE id='".$vars["id"]."'");
                $name = $name["name"];
            break;
            case "choices":
                $name = get_db_row("SELECT * FROM choices WHERE choiceid='".$vars["id"]."'");
                $name = $name["name"];
            break;
            case "pages":
                $name = get_db_row("SELECT * FROM pages WHERE pageid='".$vars["id"]."'");
                $name = $name["name"];
            break;
        }        
    }  
    return ucwords($name);
}

function get_image($vars){
    $image = "";
    if(!empty($vars["type"]) && !empty($vars["id"])){
        switch($vars["type"]){
            case "styles":
                $image = get_db_row("SELECT * FROM styles WHERE styleid='".$vars["id"]."'");
                $image = empty($image["image"]) ? false : 'files/styles/'.$vars["id"].'/'.$image["image"];
            break;
            case "options":
                $image = get_db_row("SELECT * FROM options WHERE optionid='".$vars["id"]."'");
                $image = empty($image["image"]) ? false : 'files/options/'.$vars["id"].'/'.$image["image"];
            break;
            case "option_instance":
                $image = get_db_row("SELECT * FROM styles_options WHERE id='".$vars["id"]."'");
                if(empty($image["image"])){
                    $image = get_db_row("SELECT * FROM options WHERE optionid='".$image["optionid"]."'");
                    $image = empty($image["image"]) ? false : 'files/options/'.$image["optionid"].'/'.$image["image"];    
                }else{
                    $image = empty($image["image"]) ? false : 'files/optioninst/'.$vars["id"].'/'.$image["image"];    
                }
            break;
            case "choices":
                $image = get_db_row("SELECT * FROM choices WHERE choiceid='".$vars["id"]."'");
                $image = empty($image["image"]) ? false : 'files/choices/'.$vars["id"].'/'.$image["image"];
            break;
            case "creations":
                $image = get_db_row("SELECT * FROM creations WHERE creationid='".$vars["id"]."'");
                $image = empty($image["image"]) ? false : 'files/creations/'.$vars["id"].'/'.$image["image"];
            break;
        }        
    }  
    return $image;
}

function get_pages($pageselected = "2"){
    $returnme = $menutext = $menucontents = "";
    $selected = false;
    if($result = get_db_result("SELECT * FROM pages WHERE builder=0 ORDER BY sort")){
        $i=1;
        while($row = fetch_row($result)){
            $selected = !$selected && (!$pageselected || $pageselected == $row["pageid"]) ? true : false;
            
            $checked = $selected ? 'checked="checked"' : '';
            $menutext .= '<input type="radio" id="menu'.$i.'" name="menu" '.$checked.' onclick="var c = $(this).attr(\'id\'); $(\'.page_styles:visible\').hide(\'blind\',500,function(){ $(\'.\' + c).show(\'blind\',500); });" /><label for="menu'.$i.'">'.stripslashes($row["name"]).'</label>';  
            
            $visible = $selected ? '' : 'display:none;';
            $menucontents .= '<div class="page_styles menu'.$i.'" style="'.$visible.'" id="page'.$i.'">'.stripslashes($row["contents"]).'</div>'; 
            
            $selected = false;
            $i++;
        }
    }
    $menucontents .= '<div class="page_styles menu admin" style="display:none;" id="page_admin">'.admin_page().'</div>'; 
        
    $returnme = '<div id="menu" class="optionbuttons">
    	<div id="menu_buttons">'.$menutext.'</div>
    	'.$menucontents.'
    </div>';

return $returnme;   
}

function resort($table,$idname,$where="1=1",$id=false,$direction=false,$alphabetize=false){
    $i = 1;
    $alphabetize = empty($alphabetize) ? "sort" : $alphabetize;
    if($result = get_db_result("SELECT * FROM $table WHERE $where ORDER BY $alphabetize")){
        while($row = fetch_row($result)){
            $SQL = "UPDATE $table SET sort='$i' WHERE $idname='".$row[$idname]."'";
            execute_db_sql($SQL);
            $i++;
        }
    }

    if(!empty($id)){
        if($direction == "up"){
            $oldsort = get_db_field("sort",$table,"$idname='$id'");
            $oldrow = get_db_row("SELECT * FROM $table WHERE $where AND sort='".($oldsort-1)."'");
            execute_db_sql("UPDATE $table SET sort='".$oldrow["sort"]."' WHERE $idname='$id'");
            execute_db_sql("UPDATE $table SET sort='".$oldsort."' WHERE $idname='".$oldrow[$idname]."'");        
        }elseif($direction == "down"){
            $oldsort = get_db_field("sort",$table,"$idname='$id'");
            $oldrow = get_db_row("SELECT * FROM $table WHERE $where AND sort='".($oldsort+1)."'");
            execute_db_sql("UPDATE $table SET sort='".$oldrow["sort"]."' WHERE $idname='$id'");
            execute_db_sql("UPDATE $table SET sort='".$oldsort."' WHERE $idname='".$oldrow[$idname]."'"); 
        }        
    }
}

function admin_page(){
global $CFG;
    $returnme = "";
    $returnme .= '  <div style="width:270px;margin-right:auto;margin-left:auto;">
                        <table>
                            <tr>
                                <td>Username</td><td><input type="text" id="username" /></td>
                            </tr>
                            <tr>
                                <td>Password</td><td><input type="password" id="password" /></td>
                            </tr>
                            <tr>
                                <td colspan="2" style="text-align: center;">
                                    <input type="button" style="font-size:12px" onclick="
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'signin\', username: $(\'#username\').val(), password: $(\'#password\').val() },
                                          success: function(data) { if(data == \'true\'){ window.location = \''.$CFG->wwwroot.'/index.php?admin=1\'; }else{ $(\'#error\').html(data); } }
                                        });
                                    " value="Sign In" />
                                </td>
                            </tr>
                        </table>
                        <div id="error"></div>
                    </div>';
    return $returnme;    
}

function get_admin_page(){
    return '<div>
                <div id="admin_menu">
                <a href="javascript: void(0)" onclick="$.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'orders\' },
                      success: function(data) { $(\'#admin_display\').html(data); }
                    });" >Orders</a><br />
                <a href="javascript: void(0)" onclick="$.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'creations\' },
                      success: function(data) { $(\'#admin_display\').html(data); }
                    });" >Manage Creations</a><br />
                <a href="javascript: void(0)" onclick="$.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'page_builder\' },
                      success: function(data) { $(\'#admin_display\').html(data); }
                    });" >Page Builder</a><br />
                    <a href="javascript: void(0)" onclick="$.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'style_builder\' },
                      success: function(data) { $(\'#admin_display\').html(data); }
                    });" >Style Builder</a><br />
                <a href="javascript: void(0)" onclick="$.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'option_builder\' },
                      success: function(data) { $(\'#admin_display\').html(data); }
                    });" >Option Builder</a><br />
                <a href="javascript: void(0)" onclick="$.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'choicelist_builder\' },
                      success: function(data) { $(\'#admin_display\').html(data); }
                    });" >Choicelist Builder</a><br />
                <a href="javascript: void(0)" onclick="$.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'choice_builder\' },
                      success: function(data) { $(\'#admin_display\').html(data); }
                    });" >Choice Builder</a>
                </div>
                <div id="admin_display">
                    <strong>Welcome to the Admin area!</strong>
                </div>
            </div>';    
}

function reset_button(){
    return '<button style="float:left;font-size:10px;margin:4px;" onclick="
        $.ajax({
          type: \'POST\',
          url: \'ajax/ajax.php\',
          data: { action: \'builder_reset\' },
          success: function(data) { $(\'#builder\').html(data); refresh_all(); }
        });
        $.ajax({
          type: \'POST\',
          url: \'ajax/ajax.php\',
          data: { action: \'update_popular\' },
          success: function(data) { $(\'#popular\').html(data); refresh_all(); }
        });
    ">Start Over</button>';    
}

function get_builder(){
    $returnme = "";
    if($result = get_db_result("SELECT * FROM pages WHERE builder=1")){
        while($row = fetch_row($result)){
            $returnme .= '<div class="builder_page">'.stripslashes($row["contents"]).'</div>'; 
        }
    } 
    
    $returnme .= '<button style="margin: 15px;" onclick="
        $.ajax({
          type: \'POST\',
          url: \'ajax/ajax.php\',
          data: { action: \'builder_step1\' },
          success: function(data) { $(\'#builder\').html(data); refresh_all(); }
        });
    ">Bow Builder</button>';
    
return $returnme;       
}

function get_cart(){
    $cart = empty($_SESSION['CART']) ? false : $_SESSION['CART'];  
    if(!empty($cart)){
        return '<div id="cart">'.cart_bar_contents().'</div>';
    }else{
        return '<div style="display:none" id="cart"></div>';
    }  
}

function cart_bar_contents(){
global $CFG;
    $returnme = "";
    $totals = cart_totals();
    $returnme .= '<span class="cart_bar" style="width: 15%;color:white"><strong>polka &bull; dot &bull; zebras</strong></span>';
    $returnme .= '<span class="cart_bar" style="width: 55%;"><div id="view_cart" class="accordion">
                    <h3><a href="#">View Cart</a></h3>
                    <div>'.make_cart().'</div>
                    </div></span>';
    $items = $totals["amount"] == '1' ? "item" : "items";
    $returnme .= '  <span class="cart_bar cart_bar_right" style="width: 30%;">
                        <span id="loading" style="vertical-align: top;display: none;">Loading...</span>
                        <span id="cart_link" style="vertical-align:top">
                            '.get_icon("basket").' '.make_paypal_link().'
                            <span style="vertical-align:top">
                                &nbsp;('.$totals["amount"].' '.$items.') &nbsp;$'.number_format($totals["price"],2).' + s&h
                            </span>
                        </span>
                    </span>';
    return $returnme;
}

function logged_in(){
    $loggedin = empty($_SESSION['admin']) ? false : true; 
    return $loggedin;   
}

function make_paypal_link(){
global $CFG;
    $returnme = "";
    $cart = empty($_SESSION['CART']) ? array() : $_SESSION['CART'];
    $i = 1;
    $bowcount = $price = 0;
    $returnme .= '<form id="paypalform" style="display:inline" action="'.$CFG->paypallink.'" method="post" name="paypal_form" id="paypal_form">
                    <input type="hidden" name="cmd" value="_cart" />
                    <input type="hidden" id="custom" name="custom" value="" />
                    <input type="hidden" name="upload" value="1" />
                    <input type="hidden" name="cpp_logo_image" value="'.$CFG->wwwroot.'/images/paypal_logo.png" />
                    <input type="hidden" name="notify_url" value="'.$CFG->wwwroot.'/scripts/ipn.php" />
                    <input type="hidden" name="return" value="'.$CFG->wwwroot.'" />
                    <input type="hidden" name="rm" value="2" />
                    <input type="hidden" name="cbt" value="Return to '.$CFG->sitename.'" />
                    <input type="hidden" name="business" value="'.$CFG->paypal_merchant_account.'" />
                    <input type="hidden" name="cancel_return" value="'.$CFG->wwwroot.'" />
                    ';
    foreach($cart as $bow){
        if(!empty($bow["creationid"])){
           $returnme .= '<input type="hidden" name="item_name_'.$i.'" value="'.get_name(array("type" => "creations", "id" => $bow["creationid"])).'" />
                        <input type="hidden" name="item_number_'.$i.'" value="Pre-Made" />'; 
        }else{
            $returnme .= '<input type="hidden" name="item_name_'.$i.'" value="'.get_name(array("type" => "styles", "id" => $bow["styleid"])).'" />
                        <input type="hidden" name="item_number_'.$i.'" value="Custom" />';    
        }
        
        $returnme .= '  <input type="hidden" name="amount_'.$i.'" value="'.$bow["price"].'" />
                        <input type="hidden" name="quantity_'.$i.'" value="'.$bow["amount"].'" />';
        $bowcount += $bow["amount"];
        $price += ($bow["amount"] * $bow["price"]);
        $i++;
    } 
    $shipping = ($bowcount * $CFG->shippingperitem);
    $returnme .= '  <input type="hidden" name="shipping_1" value="'.$shipping.'" />
                    <a style="color: white;vertical-align: top;font-weight: bold;" href="javascript: void(0);" 
                    onclick="';
                    
    //Activate Check Out button                
    if($CFG->active){ $returnme .= 'if(confirm(\'Order ($'.number_format($price,2).') + Shipping ($'.number_format($shipping,2).') = Total ($'.number_format(($shipping + $price),2).')\n\nClick OK to pay for your order.\')){ 
                                        $(\'#loading\').show(); $(\'#cart_link\').hide(); 
                                        $.ajax({
                                          type: \'POST\',
                                          url: \'ajax/ajax.php\',
                                          data: { action: \'order\',order: \''.Urlencode(serialize($_SESSION['CART'])).'\' },
                                          success: function(data) { if(jQuery.isNumeric(data)){ $(\'#custom\').val(data); $(\'#paypalform\').submit(); }else{ $(\'#loading\').hide(); $(\'#cart_link\').show(); } }
                                        }); }'; }
                                    
    $returnme .= '  ">Check Out</a>
                </form>';
    return $returnme;
}

function make_price($b){
    $price = 0; $bows = array();
    foreach($b as $bow){
        if(!empty($bow["creationid"])){
            $price = get_db_field("price","creations","creationid='".$bow["creationid"]."'");    
        }else{
            //Base price of bow style
            $price += get_db_field("price","styles","styleid='".$bow["styleid"]."'");  
            
            //Each option and choice
            foreach($bow["options"] as $option){
                if(!empty($option["choiceid"])){
                    $price += get_db_field("price","styles_options","id='".$option["id"]."'");
                    $price += get_db_field("price","choices","choiceid='".$option["choiceid"]."'");    
                }
            }            
        }

        $bow["price"] = number_format($price,2);
        $bows[] = $bow;  ;
    }   
    return $bows;         
}

function add_to_cart($newbow){
    $newcart = array(); $new = true;
    $cart = empty($_SESSION['CART']) ? array() : $_SESSION['CART'];
    foreach($cart as $bows){
        if($bows["bowid"] == ($newbow["bowid"])){ //Same bow, update amount
            $bows["amount"] += $newbow["amount"];     
            $new = false;    
        }
        $newcart[] = $bows;
    }
    if($new){ $newcart[] = $newbow; }

    $_SESSION['CART'] = $newcart;        
}

function remove_bow($bowid){
    $newcart = array(); $new = true;
    $cart = empty($_SESSION['CART']) ? array() : $_SESSION['CART'];
    foreach($cart as $bows){
        if($bows["bowid"] == $bowid){ //Same bow, update amount
            $bows["amount"]--;         
        }
         if(!empty($bows["amount"])){
            $newcart[] = $bows;
         }
    }
    
    $_SESSION['CART'] = $newcart;     
    if(empty($newcart)){
        return false;
    }else{
        return true;
    }
}

function make_cart(){
    $returnme = "";
    $cart = empty($_SESSION['CART']) ? array() : $_SESSION['CART'];
    $i = 1;
    foreach($cart as $bow){
        $returnme .= cart_bow_info($bow);
        $i++;
    }
    return $returnme;
}

function cart_bow_info($bow){
global $CFG;
    $name = empty($bow["creationid"]) ? get_name(array("type" => "styles", "id" => $bow["styleid"])) : get_name(array("type" => "creations", "id" => $bow["creationid"]));
    $returnme = '
    <div>
        <div class="cart_style ui-corner-all ui-state-highlight ">
            <div style="display:inline-block">
                <a href="javascript: void(0)" onclick="
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'remove_from_cart\', value: $(this).attr(\'rel\') },
                      success: function(data) { if(data == \'false\'){ $(\'#cart\').hide(); $(\'#cart\').html(\'\'); }else{ $(\'#cart\').html(data); refresh_all(); $(\'#view_cart,.selector\').accordion(\'option\', \'active\', 0 ); } }
                    });
                " rel="'.$bow["bowid"].'">'.get_icon("minus").'</a>&nbsp;&nbsp;
                <strong>'.$name.'</strong></div>
            <div style="display:inline-block;float: right"><strong>Quantity: '.$bow["amount"].'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cost: $'.number_format(($bow["price"] * $bow["amount"]),2).'</strong></div>
        </div>';
    foreach($bow["options"] as $options){
        if(!empty($options["choiceid"])){
            $returnme .= '
            <div class="cart_options"><strong>'.get_name(array("type" => "option_instance", "id" => $options["id"])).':</strong> '.get_name(array("type" => "choices", "id" => $options["choiceid"])).'</div>';           
        }
    }
    
    $returnme .= '        
    </div><br />';
    return $returnme;        
}

function cart_totals(){
    $price = $amount = 0;
    $cart = empty($_SESSION['CART']) ? array() : $_SESSION['CART'];
    
    foreach($cart as $bow){
        $price += ($bow["price"] * $bow["amount"]);
        $amount += $bow["amount"];   
    }
    return array("amount" => $amount, "price" => $price);    
}

function get_popular($styleid = false){
    $style = !empty($styleid) ? "AND styleid='$styleid'" : "";
    $returnme = '<h2>Popular Creations</h2><div style="width: 100%;position: relative;margin-right: auto;margin-left: auto;"><div class="carousel" id="carousel" style="width:90%;margin-right: auto;margin-left: auto;">';
    $SQL = "SELECT * FROM creations WHERE resell=1 $style AND image != '' ORDER BY popularity DESC LIMIT 30";
    if($result = get_db_result($SQL)){
        while($row = fetch_row($result)){
            $returnme .= '<a href="javascript: void(0)" style="text-decoration:none;width:33% !important;bottom: 10px;max-width: 200px;"><div class="popular_wrap" style="margin:3px;position:relative;height: 99%;" onclick="
                                $.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'builder_step2\',creationid: \''.$row["creationid"].'\' },
                                  success: function(data) { $(\'#builder\').html(data); refresh_all(); }
                                });
                            ">
                                <img style="right: 10%;bottom: 45px;width: 80%;position: absolute;" src="'.get_image(array("type" => "creations", "id" => $row["creationid"])).'_small.jpg" />
                                <div class="popular ui-corner-all">
                                <strong>'.stripslashes($row["name"]).'</strong><br /><span style="font-size:11px">Price: $'.$row["price"].'</span>
                                </div>
                            </div></a>'; 
        }            
    }
    $returnme .= '
        </div>    
        <a href="javascript: void(0)" onclick="setTimeout(function(){ refresh_all(); },500)" id="ui-carousel-prev" style="left:0;"></a>
        <a href="javascript: void(0)" onclick="setTimeout(function(){ refresh_all(); },500)" id="ui-carousel-next" style="right:0;"></a>
    </div>
    '; 

    if(!empty($result) && mysql_num_rows($result) >= 3){
        return $returnme;    
    }elseif(!empty($styleid)){
        return get_popular();   
    }   
    
    return "";
}

function make_order_message($vars){
    $vars = explode("||",$vars);
    $cart = $payment = $address_name = $address_street = $address_city = $address_country = $address_zip = $address_state = $transactionid = $orderid = $payer_email = "";
    
    foreach($vars as $var){
        $var = explode("=",$var);
        switch ($var[0]) {
            case "mc_gross":
                $payment = urldecode($var[1]);
                break;
            case "address_name":
                $address_name = urldecode($var[1]);
                break;
            case "address_street":
                $address_street = urldecode($var[1]);
                break;
            case "address_city":
                $address_city = urldecode($var[1]);
                break;
            case "address_state":
                $address_state = urldecode($var[1]);
                break;
            case "address_country":
                $address_country = urldecode($var[1]);
                break;
            case "address_zip":
                $address_zip = urldecode($var[1]);
                break;
            case "txn_id":
                $transactionid = urldecode($var[1]);
                break;
            case "orderid":
                $orderid = urldecode($var[1]);
                break;
            case "payer_email":
                $payer_email = urldecode($var[1]);
                break;
            case "mc_shipping":
                $shipping = urldecode($var[1]);
                break;
        } 
    }
    
    
    if(!empty($orderid)){
        $order = get_db_row("SELECT * FROM orders WHERE orderid='$orderid'"); 
        if($result = get_db_result("SELECT * FROM orders_cart WHERE orderid='$orderid'")){
            while($row = fetch_row($result)){
                $bow_description = make_bow_description($row["creationid"]);
$cart .= $bow_description."\nQuantity: ".$row["quantity"]."
Unit Price: $".number_format($row["price"],2)."
Total Item Price: $".(number_format(($row["price"]*$row["quantity"]),2))."\n";    
            }
        }        
    }
    
$template = "Order ID: #$orderid 
Transaction ID: $transactionid 
Buyer Email: $payer_email
Buyer Name: $address_name 
Address:   $address_street 
                $address_city, $address_state 
                $address_zip ($address_country) 

-- ORDER --$cart
Shipping: + $".number_format($shipping,2)."
Total Order: $".$order["price"]."
Payment: $".number_format($payment,2);

    return $template;
}

function make_bow_description($creationid,$full = false){
$returnme = $options = "";
    if($result = get_db_result("SELECT * FROM creations WHERE creationid='$creationid'")){
        while($row = fetch_row($result)){
            if(!empty($row["data"])){
            $data = explode("||",$row["data"]);
                foreach($data as $option){
                    $option = explode("::",$option);
                    $options .= $full ? '<br /><strong>'.$option[0].':</strong> '.$option[1] : "\n".$option[0].": ".$option[1];
                }                
            }

            $returnme .= $full ? "<strong>Style:</strong> ".get_name(array("type" => "styles", "id" => $row["styleid"]))."$options" : "\nStyle: ".get_name(array("type" => "styles", "id" => $row["styleid"]))."$options";    
        }
    }     
    return $returnme;
}

function display_paypal_transaction($tx){
    $returnme = '<table>
                    <tr><td>Transaction ID:</td><td>'.urldecode($tx["TRANSACTIONID"]).'</td></tr>
                    <tr><td>Transaction Status:</td><td>'.urldecode($tx["PAYMENTSTATUS"]).'</td></tr>
                    <tr><td>Buyer Name:</td><td>'.urldecode($tx["FIRSTNAME"]).' '.urldecode($tx["LASTNAME"]).'</td></tr>
                    <tr><td>Buyer Email:</td><td>'.urldecode($tx["EMAIL"]).'</td></tr>
                    <tr><td style="vertical-align: top;">Buyer Address:</td><td>'.urldecode($tx["SHIPTONAME"]).'<br />'.urldecode($tx["SHIPTOSTREET"]).'<br />'.urldecode($tx["SHIPTOCITY"]).', '.urldecode($tx["SHIPTOSTATE"]).' '.urldecode($tx["SHIPTOZIP"]).'</td></tr>
                    <tr><td>Amout:</td><td>'.urldecode($tx["AMT"]).' '.urldecode($tx["CURRENCYCODE"]).'</td></tr>
                </table>';
    
    return $returnme;    
}

function check_and_run_upgrades(){
    $version = get_db_field("version","version","version != ''");
    if(!$version){
        $version = 20120924;
    	execute_db_sql("CREATE TABLE `version` (`version` VARCHAR( 16 ) NOT NULL , INDEX ( `version` ) ) ENGINE = MYISAM ;");
        execute_db_sql("INSERT INTO  `version` (`version`) VALUES ('$version');");
                
    }
   	
	$thisversion = '20121004';
	if($version < $thisversion){ # = new version number.  If this is the first...start at 1
		$SQL = "ALTER TABLE  `creations` ADD  `bought` INT NOT NULL DEFAULT  '0' AFTER  `resell` , ADD INDEX (  `bought` ) ";
        execute_db_sql($SQL);

		execute_db_sql("UPDATE version SET version='$thisversion'");
	}
        
    //	$thisversion = YYYYMMDD;
    //	if($version < $thisversion){ //# = new version number.  If this is the first...start at 1
    //		$SQL = "";
    //		if(execute_db_sql($SQL)) //if successful upgrade
    //		{
    //			execute_db_sql("UPDATE version SET version='$thisversion'");
    //		}
    //	}
}
?>
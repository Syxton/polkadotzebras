<?php
/***************************************************************************
* index.php
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/8/2012
* Revision: 1.0.1
***************************************************************************/

if(!isset($CFG)){ include_once ('config.php'); }

include_once ($CFG->dirroot . '/lib/header.php');

check_and_run_upgrades();

//Start Page
include ('header.html');
  
//Main Layout
echo '
<div id="thankyou"></div>
<span id="debug"></span>
<div class="main ui-corner-all">
    <div id="admin_link">';

    if(logged_in()){
        echo '  <a id="admin" href="javascript: void(0)" onclick="
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'signout\' },
                      success: function(data) { window.location = \''.$CFG->wwwroot.'\'; }
                    });
                " >Sign Out</a>';
        if(empty($_GET["admin"])){
            echo '<br />
                    <a id="admin" href="javascript: void(0)" onclick="
                        window.location = \''.$CFG->wwwroot.'/index.php?admin=1\'    
                    " >Admin Page</a>';
        }else{
            echo '<br />
                    <a id="admin" href="javascript: void(0)" onclick="
                        window.location = \''.$CFG->wwwroot.'\'    
                    " >Back to Frontpage</a>';
        }
    }else{
        echo '<a id="admin" href="javascript: void(0)" onclick="var c = $(this).attr(\'id\'); $(\'.page_styles:visible\').hide(\'blind\',500,function(){ $(\'.\' + c).show(\'blind\',500); });" >Sign In</a>';
    }
    echo '</div>';
if(!empty($_GET["admin"]) && logged_in()){ //admin page
    echo '
        <div id="logo">
            <img src="'.$CFG->wwwroot.'/images/logo.png" class="logo" alt="" onclick="document.location = \''.$CFG->wwwroot.'\'" />
        </div>
        <div id="page">    
            <div style="height:100px;"></div>
            '.get_admin_page().'
        </div>
    </div>';     
    //End Page
    include ('admin_footer.html');
}else{ //front page
    echo get_cart().'
        <div id="logo">
            <img src="'.$CFG->wwwroot.'/images/logo.png" class="logo" alt="" onclick="document.location = \''.$CFG->wwwroot.'\'" />
        </div>
        <div id="page">
            '.get_pages().'
        </div>
        <div id="popular">
            '.get_popular().'
        </div>
        <div id="builder">
            '.get_builder().'
        </div>
    </div>';    
    //End Page
    include ('footer.html');
}

if(!empty($_POST["txn_id"])){
    echo '
    <script type="text/javascript">
        $("#thankyou").html("Thank you for your order!");
        $("#thankyou").show().delay(10000).fadeOut();
    </script>';    
}
?>
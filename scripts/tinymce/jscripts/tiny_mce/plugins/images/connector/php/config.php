<?php
session_start();
//Site root dir
define('DIR_ROOT', $_SESSION['dir'] );
//Images dir (root relative)
define('DIR_IMAGES','/files/pages/'.$_SESSION['pageid']);
//Files dir (root relative)
define('DIR_FILES','/files/pages/'.$_SESSION['pageid']);

//Width and height of resized image
define('WIDTH_TO_LINK', 500);
define('HEIGHT_TO_LINK', 500);

//Additional attributes class and rel
define('CLASS_LINK', 'lightview');
define('REL_LINK', 'lightbox');

//date_default_timezone_set('Asia/Yekaterinburg');
?>

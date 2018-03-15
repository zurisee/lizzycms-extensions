<?php
/*
**  Lizzy Entry Point
**
**  all requests pass through this file
*/

ob_start();
date_default_timezone_set('CET');

require_once '_lizzy/lizzy.class.php';

$website = new Lizzy();
$out = $website->render();

exit( $out );

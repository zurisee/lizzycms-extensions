<?php
/*
**  Lizzy Entry Point
**
**  all requests pass through this file
*/

date_default_timezone_set('CET');

if (!file_exists('_lizzy/')) {
	die("Error: incomplete installation,\npossibly you need to install/rename the Lizzy folder to '_lizzy/'");
}
require_once '_lizzy/lizzy.class.php';

$website = new Lizzy();
exit( $website->render() );

<?php
/*
**  Lizzy Entry Point
**
**  all requests pass through this file
*/

date_default_timezone_set('CET');

require_once '_lizzy/lizzy.class.php';

$website = new Lizzy();
exit( $website->render() );

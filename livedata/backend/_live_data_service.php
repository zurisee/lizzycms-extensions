<?php
/*
 *  Backend for live-data() macro
 */


define('SYSTEM_PATH',           '../../../');
define('PATH_TO_APP_ROOT',      SYSTEM_PATH.'../');
define('DEFAULT_POLLING_TIME', 	60);		 // s
define('MAX_POLLING_TIME', 	    90);		 // s
define('POLLING_INTERVAL', 	330000);		 // us

ob_start();

require_once SYSTEM_PATH . 'backend_aux.php';
require_once SYSTEM_PATH . 'datastorage2.class.php';
require_once SYSTEM_PATH . 'ticketing.class.php';
require_once SYSTEM_PATH . 'extensions/livedata/backend/live_data_service.class.php';

$serv = new LiveDataService();
$response = $serv->getChangedData();
lzyExit($response);


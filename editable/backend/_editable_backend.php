<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Ajax Service for dynamic data, in particular 'editable' data
 * http://localhost/Lizzy/_lizzy/_ajax_server.php?lock=ed1
 *
 *  Protocol:

conn
	GET:	?conn=list-of-editable-fields

upd
	GET:	?upd=time

lock
	GET:	?lock=id

unlock
	GET:	?unlock=id

save
	GET:	?save=id
    POST:   text => data

get
	GET:	?get=id

reset
	GET:	?reset

log
	GET:	?log=message

info
	GET:	?info

getfile
	GET:	?getfile=filename

*/

define('SYSTEM_PATH', 		'../../../');		                    //
define('PATH_TO_APP_ROOT', 	SYSTEM_PATH.'../');		                // root folder of web app

define('LOCK_TIMEOUT', 		120);	                                // max time till field is automatically unlocked
define('MAX_URL_ARG_SIZE',  255);
if (!defined('MKDIR_MASK')) {
    define('MKDIR_MASK', 0700);
}

ob_start();

require_once SYSTEM_PATH.'backend_aux.php';
require_once SYSTEM_PATH.'datastorage2.class.php';
require_once SYSTEM_PATH.'ticketing.class.php';
require_once SYSTEM_PATH.'extensions/livedata/backend/live_data_service.class.php';
require_once SYSTEM_PATH.'extensions/editable/backend/editable_backend.class.php';

$appRoot = preg_replace('/_lizzy\/.*$/', '', getcwd());

$serv = new EditableBackend();
$serv->execute();



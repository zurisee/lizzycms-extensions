<?php

define('SYSTEM_PATH', 		'../');
define('PATH_TO_APP_ROOT', 	'../../');		                            // root folder of web app
date_default_timezone_set('CET');

error_reporting(E_ALL | E_STRICT);
require('UploadHandler.php');
require_once SYSTEM_PATH.'backend_aux.php';
require_once SYSTEM_PATH.'datastorage2.class.php';
require_once SYSTEM_PATH.'ticketing.class.php';

session_start();

$activityRestrectedTo = $_SESSION['lizzy']['isRestrictedPage'];
$loggedInUser = $_SESSION['lizzy']['user'];
if ($activityRestrectedTo && ($loggedInUser != $activityRestrectedTo)) {   // check whether upload initiated by logged in user
    mylog("Upload-Server: unauthorized user tried to upload a file.");
    exit('Error: not logged in');
}

$tickRec = getUploadParameters();

$pagePath = $tickRec["pagePath"];
$dataPath = $tickRec["uploadPath"];
$appRootUrl = $tickRec['appRootUrl']; // e.g. http://localhost/myapp/
$absAppRoot = $appRoot;
session_abort();

$options = array(
		'upload_dir' => $absAppRoot.$dataPath,
		'upload_url' => $appRootUrl.$dataPath,
	);

$reqMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : false;
if ($reqMethod == 'DELETE') {
    $filename = isset($_GET['file']) ? $_GET['file'] : '';
    $file = $absAppRoot.$dataPath.$filename;
    $dest = $absAppRoot.$dataPath.'.#recycleBin/';
    if (!file_exists($dest)) {
        mkdir($dest, 0777, true);
    }
    $timestamp = date('Y-m-d_H.i.s_');
    copy($file, $dest.$timestamp.$filename);
    mylog("_upload_server.php: [$dataPath] file received (".var_r($_POST).")".var_r($options));
//    exit;
}

mylog("_upload_server.php: [$dataPath] file received (".var_r($_POST).")".var_r($options));
$upload_handler = new UploadHandler($options);



function getUploadParameters()
{
    $ticketRec = [];
    if (!isset($_POST['lzy-upload'])) {
        exit;
    }
    $dataRef = $_POST['lzy-upload'];
    if ($dataRef &&preg_match('/^[A-Z0-9]{4,20}$/', $dataRef)) {     // dataRef (=ticket hash) available
        $ticketing = new Ticketing();
        $ticketRec = $ticketing->consumeTicket($dataRef);
    }
    return $ticketRec;
}

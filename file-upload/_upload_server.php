<?php

date_default_timezone_set('CET');

error_reporting(E_ALL | E_STRICT);
require('UploadHandler.php');

session_start();

$activityRestrectedTo = $_SESSION['lizzy']['isRestrictedPage'];
$loggedInUser = $_SESSION['lizzy']['user'];
if ($activityRestrectedTo && ($loggedInUser != $activityRestrectedTo)) {   // check whether upload initiated by logged in user
    mylog("Upload-Server: unauthorized user tried to upload a file.");
    exit('Error: not logged in');
}

$pagePath = $_SESSION['lizzy']['pagePath'];

if (isset($_SESSION['lizzy'][$pagePath]['uploadPath'])) {   // case upload-macro: suggests a path (that needs to be verified)
    $allowedPaths = $_SESSION['lizzy'][$pagePath]['uploadPath'];
    if (isset($_POST['form-upload-path'])) {
        $dataPath = $_POST['form-upload-path'];
        if (strpos($allowedPaths, $dataPath) === false) {       // illegal path received
            mylog("Upload-Server: illegal path for upload rejected: 'illegal path received'");
            exit('illegal path');
        }

    } elseif (isset($_SESSION['lizzy']['pathToPage'])) {        // case editor/upload -> page path
        $dataPath = $_SESSION['lizzy']['pathToPage'];

    } else {        // case unknown: use default 'upload/'
        $dataPath = 'upload/';
    }

} else {
    $dataPath = 'upload/';
}
$appRootUrl = $_SESSION['lizzy']['appRootUrl']; // e.g. http://localhost/myapp/
$absAppRoot = $_SESSION['lizzy']['absAppRoot']; // e.g. /Volumes/Data/Localhost/myapp/
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


function mylog($text)
{
    file_put_contents('../../.#logs/log.txt', date('Y-m-d H:i:s') . "  $text\n", FILE_APPEND);

}

function var_r($var)
{
    return str_replace("\n", '', var_export($var, true));
}
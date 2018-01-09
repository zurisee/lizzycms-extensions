<?php


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
$appRootUrl = $_SESSION['lizzy']['appRootUrl'];
$absAppRoot = $_SESSION['lizzy']['absAppRoot'];
session_abort();

$options = array(
		'upload_dir' => $absAppRoot.$dataPath,
		'upload_url' => $appRootUrl.$dataPath,
	);
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
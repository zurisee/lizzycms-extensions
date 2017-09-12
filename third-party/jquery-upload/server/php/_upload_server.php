<?php
/*
 * jQuery File Upload Plugin PHP Example
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * https://opensource.org/licenses/MIT
 */

define('MAX_URL_ARG_SIZE', 127);
 
error_reporting(E_ALL | E_STRICT);
require('UploadHandler.php');

session_start();

if (isset($_SESSION['currPagePath'])) {
    $dataPath = $_SESSION['currPagePath'];
} else {
    $dataPath = 'assets/';
}
session_abort();

$rootPath = preg_replace('|_lizzy/.*|', '',  __FILE__ );
$appRootPath = substr($rootPath, strlen($_SERVER['DOCUMENT_ROOT']));

if (!isset( $_SERVER['HTTP_REFERER'] )) {
    die("no value for \$_SERVER['HTTP_REFERER'] available");
}
$url = parse_url($_SERVER['HTTP_REFERER']);
$uploadDir = $rootPath.$dataPath;
$uploadUrl = $url['scheme'].'://'.$url['host'].$appRootPath.$dataPath;
$options = array(
		'upload_dir' => $uploadDir,
		'upload_url' => $uploadUrl,
	);
$upload_handler = new UploadHandler($options);


//-----------------------------------
function safeStr($str) {
	if (preg_match('/^\s*$/', $str)) {
		return '';
	}
	$str = substr($str, 0, MAX_URL_ARG_SIZE);	// restrict size to safe value
	$str = preg_replace('/[^[:print:]À-ž]/m', '#', $str);
	return $str;
} // safeStr

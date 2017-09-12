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
define('UPLOAD_DIR', 'data/uploads/');
define('TARGETPATH_FILE', '../../../data/upload-path.txt');

error_reporting(E_ALL | E_STRICT);
require('UploadHandler.php');

$reqPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
$rootPath = preg_replace('|_lizzy/.*|', '', $_SERVER['SCRIPT_FILENAME']);
//$pagePath = trunkPath($rootPath).'pages'.$reqPath;
//$pagePath = $rootPath.'pages/'.trunkPath($reqPath, -1);
$pagePath = 'pages/'.trunkPath($reqPath, -1);

if (file_exists(TARGETPATH_FILE)) {
	$targetPath = file_get_contents(TARGETPATH_FILE);
	if (strpos($targetPath, '~page/') === 0) {
		$uploadDir = str_replace('~page/', $pagePath, $targetPath);
	} else {
		$uploadDir = trunkPath($_SERVER['SCRIPT_FILENAME'], 3) . $targetPath;
	}
} else {
	$uploadDir = trunkPath($_SERVER['SCRIPT_FILENAME'], 3) . UPLOAD_DIR;
}
//$referer = $_SERVER['HTTP_REFERER'];
$url = parse_url($_SERVER['HTTP_REFERER']);
//$reqUrl = $url['scheme'].'://'.$url['host'];
//$rootUrl = $_SERVER['SCRIPT_FILENAME'];
$appRootPath = substr($rootPath, strlen($_SERVER['DOCUMENT_ROOT']));
//$rootUrl = $url['scheme'].'://'.$url['host'].$appRootPath;
$uploadUrl = $url['scheme'].'://'.$url['host'].$appRootPath.$uploadDir;
$uploadDir = $rootPath.$uploadDir;
$upload_handler = new UploadHandler(['upload_dir' => $uploadDir, 'upload_url' => $uploadUrl ]);


//-----------------------------------------------------------------------------
function trunkPath($path, $n = 1)
{
	if ($n>0) {
		$path = ($path[strlen($path)-1] == '/') ? rtrim($path, '/') : dirname($path);
		return implode('/', explode('/', $path, -$n)).'/';
	} else {
		$path = ($path[0] == '/') ? substr($path,1) : $path;
		$parray = explode('/', $path);
		return implode('/', array_splice($parray, -$n));
	}
} // trunkPath
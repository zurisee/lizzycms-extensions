<?php

use chillerlan\QRCode\{QRCode, QROptions};


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');

	$text = $this->getArg($macroName, 'text', "Text to be converted into a QR-Code.", '');
	$id = $this->getArg($macroName, 'id', "ID to be applied to output image", '');
	$class = $this->getArg($macroName, 'class', "Class to be applied to output image", '');
    $cacheFile = $this->getArg($macroName, 'cacheFile', "Name of file to be used for caching.", '');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

	if (!$cacheFile) {
		$cacheFile = '~page/'.CACHE_PATH.translateToFilename($text, false).'.tmp';
	}
	$cacheFile = resolvePath($cacheFile, true);
	$qrCode = '';
	
	if ($text && ($text != 'help')) {
	    if (!file_exists($cacheFile)) {
            $qr = new QRCode();
            $qrCode = $qr->render($text);

            $id = $id ? " id='$id'" : '';
            $class = " class='lzy-qr-code $class'";
            $qrCode = "<img src='$qrCode'$id$class />";
            preparePath($cacheFile);
            file_put_contents($cacheFile, $qrCode);

        } else {
            $qrCode = file_get_contents($cacheFile);
        }
	}

	return $qrCode;
});


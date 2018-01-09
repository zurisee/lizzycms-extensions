<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');

    $file = $this->getArg($macroName, 'file', 'Identifies the file to be included', '');

    $file = resolvePath($file, true);
	$str = getFile($file);
	return $str;
});

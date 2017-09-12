<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $width = $this->getArg($macroName, 'width', '', '');

    $width = ($width) ? " style='width:$width'" : '';
	$str = "<span class='lizzy-h-space'$width></span>";
	return $str;
});

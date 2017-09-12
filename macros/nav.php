<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$args = $this->getArgsArray($macroName);

	if ($this->siteStructure) {
		$out = $this->siteStructure->render($inx, $this->page, $args);
	} else {
		$out = '';
	}
	return $out;
});




<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $args = $this->getArgsArray($macroName);

    $out = '';
    foreach ($args as $arg) {
        $s = $this->getVariable($arg);
        if ($s) {
            $arg = $s;
        }
        $out .= $arg;
    }

	return $out;
});

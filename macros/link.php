<?php

// @info: Renders a link.

require_once SYSTEM_PATH.'create-link.class.php';


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $args = $this->getArgsArray($macroName, false, ['href','text','type','class','title','target','subject','body']);


    $cl = new CreateLink();

    $str = $cl->render($args);

    $this->optionAddNoComment = true;
	return $str;
});

<?php
// @info: -> one line description of macro <-


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
//    $macroName = basename(__FILE__, '.php');
//    $arg1 = $this->getArg($macroName, 'argName1', 'Help-text', 'default value');
//    $this->optionAddNoComment = true; // if you want to suppress comments around the output code
//    $this->compileMd = true;  // if macro out shall be markdown compiled

	return '';
});

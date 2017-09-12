<?php

$page->addCssFiles('EDITABLE_CSS');
$page->addJQFiles('EDITABLE');


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $id = $this->getArg($macroName, 'id', '', '');
    $touch = $this->getArg($macroName, 'touch', '', 'auto');

    if (!$id) {
		$id = 'editable_field'.$inx;
	} else {
		$id = translateToIdentifier($id);
	}
	
	if ($inx == 1) {
		$this->page->addJq("\t\$('.editable').editable({ 'touchMode': '$touch' });\n");
	}

    $dbFile = $this->config->dataPath.$this->page->pageName.'.yaml';
    $db = new DataStorage($dbFile);
    $val = $db->read($id);
    return "<div id='$id' class='editable'>$val</div>";
});

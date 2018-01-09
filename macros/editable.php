<?php

//$page->addCssFiles('EDITABLE_CSS');
//$page->addJQFiles('EDITABLE');


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $id = $this->getArg($macroName, 'id', 'Defines the element ID, default is "editable_field{index}"', '');
    $showButton = $this->getArg($macroName, 'showButton', '[false|true|auto] Defines whether the submit button is displayed. auto means it will be displayed on ly on touch devices', 'auto');    //??? -> change to showButton or showButtons?
    $dataPath = $this->getArg($macroName, 'dataPath', '<code>path</code> (optional) defines the path where to store data (default is page folder)', '');

    if ($id) {
        $id = translateToIdentifier($id);
    } else {
        $id = 'editable_field'.$inx;
	}
	
	if ($inx == 1) {
		$this->page->addJq("\t\$('.editable').editable({ 'showButton': '$showButton' });\n");
	}
	$pagePath = $GLOBALS['globalParams']['pagePath'];
    if ($dataPath) {
        $dataPath1 = resolvePath($dataPath);
        if (file_exists($dataPath1)) {
            if (is_file($dataPath1)) {
                $dbFile = $dataPath1;
            } elseif (is_dir($dataPath1)) {
                $dbFile = $dataPath1.'editable.yaml';
            } else {
                fatalError("Error: folder to store editable data does not exist.");
            }
        } elseif (file_exists(basename($dataPath1))) {
            $dbFile = $dataPath1.'editable.yaml';
        } else {
            fatalError("Error: folder to store editable data does not exist.");
        }
        $_SESSION['lizzy']['db'][$pagePath] = $dbFile;
    } else {
        $_SESSION['lizzy']['db'][$pagePath] = '';
        $dbFile = $GLOBALS['globalParams']['pathToPage'].'editable.yaml';
    }
    $db = new DataStorage($dbFile);
    $val = $db->read($id);
    return "<div id='$id' class='editable'>$val</div>";
});

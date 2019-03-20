<?php

// @info: Create a Table From Data.


define('ORD_A', 	ord('a'));
require_once SYSTEM_PATH.'datastorage.class.php';
require_once SYSTEM_PATH.'htmltable.class.php';

global $tableCounter;
$tableCounter = 0;

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $options = $this->getArgsArray($macroName, false);

    if (isset($options[0]) && ($options[0] == 'help')) {
        return renderHelp($this, $macroName);
    }

    $dataSource = $this->getArg($macroName, 'dataSource', '', '');
    $file = resolvePath($dataSource, true);
    if ($dataSource && !file_exists($file)) {
        return "<div>Error: Datasource-File '$dataSource' not found.</div>\n";
    }

    $dataTable = new HtmlTable($this->page, $inx, $options);
	$table = $dataTable->render();
	
	return $table;
});




function renderHelp($page, $macroName)
{
    $dataTable = new HtmlTable($page, 0, 'help');
    $help = $dataTable->render('help');
    foreach ($help as $helpText) {
        $page->getArg($macroName, $helpText['option'], $helpText['text']);
    }
    return '';
}


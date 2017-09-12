<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Macro(): creates a table based on div elements (rather than table)
 *
 *	Note: if class is set to 'editable' the table elements are automatically made editable
*/

require_once SYSTEM_PATH.'datastorage.class.php';
//$ds = new DataStorage('data/mytabledata2.yaml');
//$ds->convert('data/mytabledata2.yaml', 'json');
//exit;

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $id = $this->getArg($macroName, 'id', '', '');
    $dataSource = $this->getArg($macroName, 'dataSource', '', '');
    $class = $this->getArg($macroName, 'class', '', '');
//    $cols = $this->getArg($macroName, 'cols', '', '');
//    $rows = $this->getArg($macroName, 'rows', '', '');

    if (!$dataSource) {
		$dataSource = '~page/'.$id.'.yaml';
	}
	$srcFile = resolvePath($dataSource);
	$ds = new DataStorage($srcFile);
	$data = $ds->read();
	
	if (!$data) {
		return "<p>`no data found for` '$id'</p>";
	}

	$table = renderDivTable($data, $id, $class);
	
	return $table;
});


//----------------------------------------------------------------------
function renderDivTable($data, $id, $tdClass = '')
{
	if (!isset($data[0])) {
		return '';
	}
	if ($tdClass) {
		$tdClass = " class='$tdClass'";
	}
	$nRows = sizeof($data);
	$nCols = sizeof($data[0]);

	$out = "\t<div id='$id' class='divtable'>\n\t\t  <div class='row'>\n";
	for ($col=0; $col<$nCols; $col++) {
		$out .= "\t\t\t<div class='hdrCell'>{$data[0][$col]}</div>\n";
	}
	$out .= "\t\t  </div>\n";
	
	for ($row=1; $row<$nRows; $row++) {
		$out .= "\t\t  <div class='row'>\n";
		for ($col=0; $col<$nCols; $col++) {
			$out .= "\t\t\t<div$tdClass>{$data[$row][$col]}</div>\n";
		}
		$out .= "\t\t  </div>\n";
	}
	
	$out .= "\t</div>\n";
	return $out;
} // renderDivTable

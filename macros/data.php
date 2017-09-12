<?php

/*
** For manipulating the embedding page, use $page:
**		$page->addHead('');
**		$page->addCssFiles('');
**		$page->addCss('');
**		$page->addJsFiles('');
**		$page->addJs('');
**		$page->addJqFiles('');
**		$page->addJq('');
**		$page->addBody_end_injections('');
**		$page->addMessage('');
**		$page->addPageReplacement('');
**		$page->addOverride('');
**		$page->addOverlay('');
*/

require_once SYSTEM_PATH.'datastorage.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function ()
{
    global $dataSrc, $currRecNo, $data;
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

    $src = $this->getArg($macroName, 'dataSrc', 'Name of source-file, e.g. "data.csv"; -> returns number records found');
    $nRecs = $this->getArg($macroName, 'numberOfRecords', '"true" returns the number of records');
    $recNo = $this->getArg($macroName, 'recNo', '"next" or explicit record-number; -> no return value');
    $field = $this->getArg($macroName, 'field', 'Name of data-field to be returned; -> returns field-value');
    $altField = $this->getArg($macroName, 'altField', 'Name of alternative field, if primary field is empty');

    $str = '';
    if ($src) {
        $dataSrc = $src;

        $dataSrc = resolvePath($dataSrc, true);
        if (!fileExists($dataSrc)) {
            die("Error: file not found: '$dataSrc'");
        }

        $ds = new DataStorage($dataSrc);

        $data = $ds->read();
        return '';
    }
    if ($nRecs) {
        if (isset($data)) {
            return sizeof($data) - 1;
        } else {
            die("Error: no data source has been specified");
        }
    }


    if ($recNo) {
        if (stripos($recNo, 'next') !== false) {
            if ($currRecNo === null) {
                $currRecNo = 1;
            } else {
                $currRecNo++;
            }
        } else {
            $currRecNo = $recNo;
        }
        return "\n<!-- Rec: $currRecNo -->";
    }


    if (!isset($currRecNo)) {
        $currRecNo = 0;
    }

    if ($field) {
        if (!isset($data)) {
            die("Error: no data source specified");
        }
        $_data = $data[$currRecNo];
        $fIndex = findField($data, $field);
        if ($fIndex !== false) {
            $str = $_data[$fIndex];
        } else {
            $str = "Field '$field' is unknown.";
        }

        if ($altField && !$str) {
            $fIndex = findField($data, $altField);
            if ($fIndex !== false) {
                $str = $_data[$fIndex];
            } else {
                $str = "Field '$field' is unknown.";
            }

        }
    }
	return trim($str);
});



function findField($data, $fieldName)
{
    foreach ($data[0] as $n => $name) {
        if (stripos($name, $fieldName) !== false) {
            return $n;
        }
    }
    return false;
} // findField
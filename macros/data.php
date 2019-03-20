<?php

// @info: Provides sequential access to data-tables.


require_once SYSTEM_PATH.'datastorage.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function ()
{
    global $dataSrc, $currRecNo, $data;
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $src = $this->getArg($macroName, 'dataSrc', 'Name of source-file, e.g. "data.csv"; -> returns number records found');
    $nRecs = $this->getArg($macroName, 'nRecs', '"true" returns the number of records');
    $recNo = $this->getArg($macroName, 'recNo', '"next" or explicit record-number; -> no return value');
    $field = $this->getArg($macroName, 'field', 'Name of data-field to be returned; -> returns field-value');
    $altField = $this->getArg($macroName, 'altField', 'Name of alternative field, if primary field is empty');
    $quiet = $this->getArg($macroName, 'quiet', 'Omit HTML comment indicating currRecNo');

    if ($src == 'help') {
        return '';
    }

    $str = '';
    if ($src) {
        $dataSrc = resolvePath($src, true);
        if (!fileExists($dataSrc)) {
            fatalError("Error: file not found: '$dataSrc'", 'File: '.__FILE__.' Line: '.__LINE__);
        }

        $ds = new DataStorage($dataSrc);

        $data = $ds->read();
    }

    if ($nRecs) {
        if (isset($data)) {
            $str = sizeof($data) - 1;
        } else {
            fatalError("Error: no data source has been specified", 'File: '.__FILE__.' Line: '.__LINE__);
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
        if (!$quiet) {
            $str .= "\n<!-- Rec: $currRecNo -->";
        }
    }


    if (!isset($currRecNo)) {
        $currRecNo = 0;
    }

    if ($field) {
        if (!isset($data)) {
            fatalError("Error: no data source specified", 'File: '.__FILE__.' Line: '.__LINE__);
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
        if (strcasecmp($name, $fieldName) == 0) {
            return $n;
        }
    }
    return false;
} // findField
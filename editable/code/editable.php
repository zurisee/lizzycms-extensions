<?php

// @info: Renders a field that can be modified by the user. Changes are persistent and can immediately be seen by other visitors.

define('DEFAULT_EDITABLE_DATA_FILE', 'editable.yaml');

$macroName = basename(__FILE__, '.php');

$page->addModules(['~sys/extensions/editable/css/editable.css', '~sys/extensions/editable/js/editable.js']);

$msg = $this->getVariable('lzy-editable-temporarily-locked');
$msg = str_replace("\n", '\\n', $msg);
$page->addJs("var lzy_editable_msg = '$msg';");
$this->readTransvarsFromFile( resolvePath("~ext/$macroName/config/vars.yaml"), false, true);


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	if ($_GET) {
	    return '';
    }

    $args['id'] = $this->getArg($macroName, 'id', 'Defines the base-Id applied to editable field(s).<br />If omitted, "lzy-editable-field{index}" is used.', '');
    $args['class'] = $this->getArg($macroName, 'class', 'Class name that will be applied to editable fields', '');
    $args['showButton'] = $this->getArg($macroName, 'showButton', '[false|true|auto] Defines whether the submit button is displayed (default: true).<br />"auto" means it will be displayed only on touch devices.', true);
    $args['dataSource'] = $this->getArg($macroName, 'dataSource', '<code>path</code> (optional) defines path&filename where to store data (default: page folder)', '');
    $args['dataKey'] = $this->getArg($macroName, 'dataKey', '(optional) If dataSource points to a .csv file, this argument defines the coordinates of the data element to be selected', '');
//    $args['protectedCells'] = $this->getArg($macroName, 'protectedCells', '(optional) E.g. ""', '');
    $args['protectedCells'] = '';

    $args['nCols'] = $this->getArg($macroName, 'nCols', '[number] If set, creates a table with N columns <br /><strong>Note</strong>: If using nCols or nRows, any arguments of table() macro may be applied in addition to the list here.', 1);
    $args['nRows'] = $this->getArg($macroName, 'nRows', '[number] If set, creates a table with N rows, rather than just one field', 1);
    $args['headers'] = $this->getArg($macroName, 'headers', 'A list of headers (sep. by "|")  used as header column, e.g. "[Aa|Bb|Cc]"', '');
    $args['showRowNumbers'] = $this->getArg($macroName, 'showRowNumbers', '[true|false] Adds an index number to every row (default: true)', true);
    $args['useRecycleBin'] = $this->getArg($macroName, 'useRecycleBin', '[true|false] If true, previous values will be saved in a recycle bin rather than discared (default: false)', false);
    $args['freezeFieldAfter'] = $this->getArg($macroName, 'freezeFieldAfter', '[seconds] If set, non-empty fields will be frozen after given number of seconds', '');
    $liveData = $args['liveData'] = $this->getArg($macroName, 'liveData', 'If true, data values are immediately updated if the database on the host is modified.', false);
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);


    if (($args['id'] == 'help') || ($args['id'] === false)) {
        return '';
    }

    if (($inx === 1) && $liveData) {
        require_once SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';
        $this->page->addModules('~ext/livedata/js/live_data.js');
    }

    $args = $this->getArgsArray($macroName); // get all args, some of which are passed through to htmltable.class
    $args = prepareArguments($args, $inx);
    $args = prepareDataSource($args, $inx);

    // create or obtain ticket from previous session:
    $ticketing = new Ticketing(['hashSize' => 8, 'defaultType' => 'editable', 'defaultValidityPeriod' => 900]);
    $rec[$inx-1] = [
        'dataSrc' => $args['dataFile'],
        'useRecycleBin' => $args['useRecycleBin'],
        'freezeFieldAfter' => $args['freezeFieldAfter'],
        'useRecycleBin' => $args['useRecycleBin'],
    ];
    $ticket = $ticketing->createTicket($rec, 99999);
    $ticket = "$ticket:".($inx-1);

    if (($args['nCols'] > 1) || ($args['nRows'] > 1)) {
        $out = renderTable($this->lzy, $inx, $args, $ticket);

    } else {
        $out = renderEditableField($args, $ticket);
    }

    if ($liveData) {
        $out .= renderLiveUpdate($this->lzy, $args);
    }
    return $out;
}); // editable




function renderLiveUpdate($lzy, $args ) {
    $ldArgs = [
        'dataSource' => $args['dataSource'],
        'dataSelector' => $args['dataKey'],
        'targetSelector' => '_'.$args['id'],
        'output' => false,
	];
    $ld = new LiveData($lzy, $ldArgs);
    $attrib = $ld->render( true );

    $str = "\t<span$attrib></span><!-- live-data -->\n";
    return $str;
} // renderLiveUpdate




//---------------------------------------------------------------
function renderEditableField($args, $ticket)
{
    $db = &$args['db'];
    $id = isset($args['id']) ? $args['id'] : false;
    if ($args['dataKey']) {
        $val = $db->readElement($args['dataKey']);
        $args['dataKey'] = " data-lzy-cell='{$args['dataKey']}'";
    } else {
        $val = $db->readElement($id);
        $args['dataKey'] = '';
    }

    $modifTime = $db->lastModifiedElement($id);
    $lockedClass = $db->isElementLocked($id) ? ' lzy-locked' : '';
    if ($val && $args['freezeThreshold'] && ($modifTime < $args['freezeThreshold'])) {
        $out = "<div id='$id' class='lzy-editable-frozen{$args['class']}' title='{{ lzy-editable-field-frozen }}'>$val</div>";;
    } else {
        $out = "<div id='$id' class='lzy-editable {$args['showButtonClass']}{$args['class']}$lockedClass' data-lzy-editable='$ticket'{$args['dataKey']}>$val</div>";
    }
    return $out;
} // renderEditableField



//---------------------------------------------------------------
function renderTable($lzy, $inx, $args, $ticket)
{
    require_once SYSTEM_PATH.'htmltable.class.php';

    $options = $args;
    $options['cellClass'] = 'lzy-editable';
    $options['tableDataAttr'] = "lzy-editable=$ticket";
    $options['includeCellRefs'] = true;
    $options['cellMask'] = $args['protectedCells'];
    $options['cellMaskedClass'] = 'lzy-non-editable';
    unset($options['dataSource']);

    $tbl = new HtmlTable($lzy, $inx, $options);
    $out = $tbl->render();
    return $out;
} // renderTable




//---------------------------------------------------------------
function prepareArguments($args, $inx)
{
    if ($args['id']) {
        $args['id'] = translateToIdentifier($args['id']);
    } else {
        $args['id'] = 'lzy-editable-field' . $inx;
    }
    $args['class'] = ($args['class']) ? " {$args['class']}" : '';

    $args['freezeThreshold'] = ($args['freezeFieldAfter']) ? time() - intval($args['freezeFieldAfter']) : 0;

    $args['showButtonClass'] = '';
    if ($args['showButton'] === 'auto') {
        $args['showButtonClass'] = ' lzy-editable-auto-show-button';
    } elseif ($args['showButton']) {
        $args['showButtonClass'] = ' lzy-editable-show-button';
    }

//    $args = prepareProtectedCellsArray($args);

    $args['nCols'] = intval($args['nCols']);
    $args['nRows'] = intval($args['nRows']);

    return $args;
} // prepareArguments




//---------------------------------------------------------------
function prepareProtectedCellsArray($args)
{
    $protectedCells = [];
    if ($args['protectedCells']) {
        $protCells = $args['protectedCells'];
        $delim = (substr_count($protCells, ',') > substr_count($protCells, '|')) ? ',' : '|';
        $elems = parseArgumentStr($protCells, $delim);
        $protectedCells = array_fill(0, $args['nRows'], array_fill(0, $args['nCols'], false));;
        foreach ($elems as $elem) {
            if (!trim($elem)) {
                continue;
            }
            $param = substr($elem, 1);
            $paramArr = parseNumbersetDescriptor($param);
            switch ($elem[0]) {
                case 'r':
                    while ($r = array_shift($paramArr)) {
                        $r = intval($param) - 1;
                        $protectedCells[$r] = array_fill(0, $args['nCols'], true);
                    }
                    break;
                case 'c':
                    while ($c = array_shift($paramArr)) {
                        $c = intval($c) - 1;
                        for ($r = 0; $r < $args['nRows']; $r++) {
                            $protectedCells[$r][$c] = true;
                        }
                    }
                    break;
                case 'e':
                    list($c, $r) = preg_split('/\D/', $param);
                    $r = intval($r) - 1;
                    $c = intval($c) - 1;
                    $protectedCells[$r][$c] = true;
                    break;
            }
        }
    }
    $args['protectedCells'] = $protectedCells;
    return $args;
} // prepareProtectedCellsArray




//---------------------------------------------------------------
function prepareDataSource($args, $inx)
{
    if ($args['dataSource']) {
        if (preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*$/', $args['dataKey'], $m)) {
            $args['dataKey'] = (intval($m[1]) - 1) . ',' . (intval($m[2]) - 1);
            $args['id'] = 'lzy-editable-field' . $inx;
        }
        $dataSource1 = resolvePath($args['dataSource'], true);
        if (file_exists($dataSource1)) {
            if (is_file($dataSource1)) {
                $args['dataFile'] = $dataSource1;
            } elseif (is_dir($dataSource1)) {
                $args['dataFile'] = $dataSource1 . DEFAULT_EDITABLE_DATA_FILE;
            } else {
                fatalError("Error: folder to store editable data does not exist.");
            }
        } elseif (file_exists(basename($dataSource1))) {    // folder exists, but not the file
            $args['dataFile'] = $dataSource1 . DEFAULT_EDITABLE_DATA_FILE;
        } else {
            preparePath($dataSource1);
            touch($dataSource1);
            $args['dataFile'] = $dataSource1;
        }
    } else {
        $args['dataFile'] = $GLOBALS['globalParams']['pageFolder'] . DEFAULT_EDITABLE_DATA_FILE;
    }
    $args['db'] = new DataStorage2($args['dataFile']);
    return $args;
} // prepareDataSource

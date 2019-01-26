<?php

// @info: Renders a field that can be modified by the user. Changes are persistent and can immediately be seen by other visitors.
$macroName = basename(__FILE__, '.php');

$page->addModules('EDITABLE');

$msg = $this->getVariable('lzy-editable-temporarily-locked');
$page->addJs("var lzy_editable_msg = '$msg';");

$_SESSION['lizzy']['pagePath'] = $GLOBALS['globalParams']['pagePath'];
$_SESSION['lizzy']['pagesFolder'] = $GLOBALS['globalParams']['pagesFolder'];

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $args['id'] = $this->getArg($macroName, 'id', 'Defines the base-Id applied to editable field(s).<br />If omitted, "lzy-editable-field{index}" is used.', '');
    $args['class'] = $this->getArg($macroName, 'class', 'Class name that will be applied to editable fields', '');
    $args['showButton'] = $this->getArg($macroName, 'showButton', '[false|true|auto] Defines whether the submit button is displayed (default: true).<br />"auto" means it will be displayed only on touch devices.', 'true');
    $args['dataPath'] = $this->getArg($macroName, 'dataPath', '<code>path</code> (optional) defines the path where to store data (default: page folder)', '');

    $args['tableCols'] = $this->getArg($macroName, 'tableCols', '[number] If set, creates a table with N columns, rather than just one field', '1');
    $args['tableRows'] = $this->getArg($macroName, 'tableRows', '[number] If set, creates a table with N rows, rather than just one field', '1');
    $args['tableColHeaders'] = $this->getArg($macroName, 'tableColHeaders', 'A list of headers (sep. by "|")  used as header column, e.g. "[Aa|Bb|Cc]"', '');
    $args['showRowNumbers'] = $this->getArg($macroName, 'showRowNumbers', '[true|false] Adds an index number to every row (default: true)', 'true');

    $freezeFieldAfter = $this->getArg($macroName, 'freezeFieldAfter', '[seconds] If set, non-empty fields will be frozen after given number of seconds', '');

    if ($args['id'] == 'help') {
        return '';
    }
    if ($args['id']) {
        $args['id'] = translateToIdentifier($args['id']);
    } else {
        $args['id'] = 'lzy-editable-field'.$inx;
    }
    $args['class'] = ($args['class']) ? " {$args['class']}" : '';

    $args['freezeThreshold'] = ($freezeFieldAfter) ? time()-$freezeFieldAfter : 0;

    $args['showButtonClass'] = '';
    if ($args['showButton'] == 'auto') {
        $args['showButtonClass'] = ' lzy-editable-auto-show-button';
    } else  if ($args['showButton'] == 'true') {
        $args['showButtonClass'] = ' lzy-editable-show-button';
    }
    $args['showRowNumbers'] = ($args['showRowNumbers'] != 'false');

	$pagePath = $GLOBALS['globalParams']['pagePath'];
    if ($args['dataPath']) {
        $dataPath1 = resolvePath($args['dataPath']);
        if (file_exists($dataPath1)) {
            if (is_file($dataPath1)) {
                $args['dbFile'] = $dataPath1;
            } elseif (is_dir($dataPath1)) {
                $args['dbFile'] = $dataPath1.'editable.yaml';
            } else {
                fatalError("Error: folder to store editable data does not exist.");
            }
        } elseif (file_exists(basename($dataPath1))) {
            $args['dbFile'] = $dataPath1.'editable.yaml';
        } else {
            fatalError("Error: folder to store editable data does not exist.");
        }
        $_SESSION['lizzy']['db'][$pagePath] = $args['dbFile'];
    } else {
        $args['dbFile'] = $GLOBALS['globalParams']['pathToPage'].'editable.yaml';
        $_SESSION['lizzy']['db'][$pagePath] = $args['dbFile'];
    }
    $args['db'] = new DataStorage($args['dbFile']);     // insert current value
    if ($freezeFieldAfter) {
        $args['db']->write("_meta_/{$args['id']}/lzy-editable-freeze-after", $freezeFieldAfter);
    }

    if (($args['tableCols'] > 1) || ($args['tableRows'] > 1)) {
        $out = renderTable($args);
        $this->invocationCounter[$macroName] = $inx;

    } else {
        $val = $args['db']->read($args['id']);
        $modifTime = $args['db']->lastModified($args['id']);
        $lockedClass = ($args['db']->isLocked($args['id'])) ? ' lzy-locked' : '';
        if ($val && $args['freezeThreshold'] && ($modifTime < $args['freezeThreshold'])) {
            $out = "<div id='{$args['id']}' class='lzy-editable-frozen{$args['class']}'>$val</div>";;
        } else {
            $out = "<div id='{$args['id']}' class='lzy-editable {$args['showButtonClass']}{$args['class']}$lockedClass' data-storage-path='{$args['dbFile']}'>$val</div>";;
        }
    }

    return $out;
});



//---------------------------------------------------------------
function renderTable($args) {

    $out = '';
    $nCols = $args['tableCols'];
    $nRows = $args['tableRows'];
    if ($args['tableColHeaders']) {
        $colHeaders = explode('|', $args['tableColHeaders']);
        $s = sizeof($colHeaders);
        if ($s < $args['tableCols']) {
            $colHeaders = array_merge($colHeaders, array_fill($s, $args['tableCols'], '&nbsp;'));
        }
        $out .= "\t<div class='lzy-editable-hdr-row'>\n";
        if ($args['showRowNumbers'] && ($nRows > 1)) {
            $out .= "\t<div class='lzy-editable-row-nr'></div>";
        }
        for ($c = 0; $c < $nCols; $c++) {
            $colHdr = $colHeaders[$c];
            $out .= "<div class='lzy-editable-col-hdr lzy-editable-col'><span>$colHdr</span></div>";
        }
        $out .= "\t</div> <!-- /lzy-editable-hdr-row -->\n";
    }

    for ($r=0; $r < $nRows; $r++) {
        $out .= "\t<div class='lzy-editable-row'>\n";
        if ($args['showRowNumbers'] && ($nRows > 1)) {
            $out .= "\t<div class='lzy-editable-row-nr'>" . ($r + 1) . "</div>";
        }
        for ($c = 0; $c < $nCols; $c++) {
            $id1 = $args['id']."_$r-$c";
            $val = $args['db']->read($id1);
            $val = htmlentities($val);
            $modifTime = $args['db']->lastModified($id1);
            if ($val && $args['freezeThreshold'] && ($modifTime < $args['freezeThreshold'])) {
                $out .= "<div id='$id1' class='lzy-editable-frozen lzy-editable-col{$args['class']}'>$val</div>";;
            } else {
                $out .= "<div id='$id1' class='lzy-editable lzy-editable-col{$args['showButtonClass']}{$args['class']}' data-storage-path='{$args['dbFile']}'>$val</div>";
            }
        }
        $out .= "\t</div> <!-- /lzy-editable-row -->\n";
    }
    return $out;
}
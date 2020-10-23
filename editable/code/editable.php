<?php

// @info: Renders a field that can be modified by the user. Changes are persistent and can immediately be seen by other visitors.

define('DEFAULT_EDITABLE_DATA_FILE', 'editable.yaml');

$macroName = basename(__FILE__, '.php');

require_once SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';
require_once SYSTEM_PATH.'extensions/editable/code/editable.class.php';

$page->addModules(['~sys/extensions/editable/css/editable.css', '~sys/extensions/editable/js/editable.js']);
//$page->addModules('~sys/extensions/livedata/js/live_data.js');

$msg = $this->getVariable('lzy-editable-temporarily-locked');
$msg = str_replace("\n", '\\n', $msg);
$page->addJs("var lzy_editable_msg = '$msg';");
$this->readTransvarsFromFile( resolvePath("~ext/$macroName/config/vars.yaml"), false, true);


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

	if ($_GET) {
	    return '';
    }

    $help = $this->getArg($macroName, 'id', 'Defines the base-Id applied to editable field(s).<br />If omitted, "lzy-editable-field{index}" is used.', '');

    if (($help == 'help') || ($help === false)) {
        $this->getArg($macroName, 'class', 'Class name that will be applied to editable fields', '');
        $this->getArg($macroName, 'showButton', '[false|true|auto] Defines whether the submit button is displayed (default: true).<br />"auto" means it will be displayed only on touch devices.', true);
        $this->getArg($macroName, 'dataSource', '<code>path</code> (optional) defines path&filename where to store data (default: page folder)', '');
        $this->getArg($macroName, 'dataKey', '(optional) ???', '');

        $this->getArg($macroName, 'dataSelector', 'Name of the element(s) to be visualized. Use format "A|B|C" to specify a list of names.', false);
        $this->getArg($macroName, 'dynamicArg', '[r=#recId] If defined, permits to let an element in the page select dynamically select data. '.
            'In this case elementName would be defined as "{r},status", "r" being the placeholder of a value retrieved from "#recId"', false);
        $this->getArg($macroName, 'targetSelector', '(optional) Id of DOM element(s). If not specified, id will be derived from elementName.  Use format "A|B|C" to specify a list of ids.', false);

        //        $this->getArg($macroName, 'protectedCells', '(optional) E.g. ""', '');

        $this->getArg($macroName, 'nCols', '[number] If set, creates a table with N columns <br /><strong>Note</strong>: If using nCols or nRows, any arguments of table() macro may be applied in addition to the list here.', 1);
        $this->getArg($macroName, 'nRows', '[number] If set, creates a table with N rows, rather than just one field', 1);
        $this->getArg($macroName, 'headers', 'A list of headers (sep. by "|")  used as header column, e.g. "[Aa|Bb|Cc]"', '');
        $this->getArg($macroName, 'showRowNumbers', '[true|false] Adds an index number to every row (default: true)', true);
        $this->getArg($macroName, 'useRecycleBin', '[true|false] If true, previous values will be saved in a recycle bin rather than discared (default: false)', false);
        $this->getArg($macroName, 'freezeFieldAfter', '[seconds] If set, non-empty fields will be frozen after given number of seconds', '');
        $this->getArg($macroName, 'liveData', 'If true, data values are immediately updated if the database on the host is modified.', false);
        $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);
        return '';
    }
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    $args = $this->getArgsArray($macroName); // get all args, some of which are passed through to htmltable.class
    $edbl = new Editable( $this->lzy, $args );
    $out = $edbl->render();

//    if ($liveData) {
//        $out .= renderLiveUpdate($this->lzy, $args);
//    }
    return $out;
}); // editable




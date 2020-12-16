<?php

// @info: Renders a field that can be modified by the user. Changes are persistent and can immediately be seen by other visitors.

define('DEFAULT_EDITABLE_DATA_FILE', 'editable.yaml');

$macroName = basename(__FILE__, '.php');

require_once SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';
require_once SYSTEM_PATH.'extensions/editable/code/editable.class.php';

$this->readTransvarsFromFile( resolvePath("~ext/$macroName/config/vars.yaml"), false, true);

$GLOBALS['lizzy']['editableLiveDataInitialized'] = false;

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $help = $this->getArg($macroName, 'id', 'Defines the base-Id applied to editable field(s).<br />If omitted, "lzy-editable-field{index}" is used.', '');

    if (($help == 'help') || ($help === false)) {
        $this->getArg($macroName, 'class', 'Class name that will be applied to editable fields', '');
        $this->getArg($macroName, 'showButton', '[false|true|auto] Defines whether the submit button is displayed (default: true).<br />"auto" means it will be displayed only on touch devices.', true);
        $this->getArg($macroName, 'dataSource', '<code>path</code> (optional) defines path&filename where to store data (default: page folder)', '');

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
        $this->getArg($macroName, 'permission', '[true|false|loggedin|privileged|admins] If set, defines who can edit values.', true);
        $this->getArg($macroName, 'multiline', 'If true, .', false);
        $this->getArg($macroName, 'output', 'If true, .', false);
        $this->getArg($macroName, 'liveData', 'If true, data values are immediately updated if the database on the host is modified.', false);
        $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);
        return '';
    }
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    // check editing permission:
    $permission = $this->getArg($macroName, 'permission', '.', true);
    if ($permission && ($permission !== true)) {
        $permission = $this->lzy->auth->checkPrivilege($permission);
    }

    // load modules on first run only:
    if ($this->invocationCounter[$macroName] === 0) {
        $this->page->addModules('~sys/extensions/editable/css/editable.css');
        if ($permission) {
            $this->page->addModules('~sys/extensions/editable/js/editable.js');
        }
    }

    // option liveData:
    $liveData = $this->getArg($macroName, 'liveData', '', false);
    if ($liveData && !$GLOBALS['lizzy']['editableLiveDataInitialized']) {
        $this->page->addModules('~sys/extensions/livedata/js/live_data.js');
        $jq = <<<EOT
if ($('[data-lzy-data-ref]').length) {
    initLiveData();
}

EOT;
        $this->page->addJq($jq);
        $GLOBALS['lizzy']['editableLiveDataInitialized'] = true;
    }

    $args = $this->getArgsArray($macroName); // get all args, some of which are passed through to htmltable.class
    $args['permission'] = $permission;
    $edbl = new Editable( $this->lzy, $args );
    $out = $edbl->render();

    return $out;
}); // editable




<?php

// @info: Renders a field that can be modified by the user. Changes are persistent and can immediately be seen by other visitors.

$macroName = basename(__FILE__, '.php');

require_once SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';
require_once SYSTEM_PATH.'extensions/editable/code/editable.class.php';

$this->readTransvarsFromFile( resolvePath("~ext/$macroName/" .LOCALES_PATH. "vars.yaml"), false, true);

$GLOBALS['lizzy']['editableLiveDataInitialized'] = false;

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $help = $this->getArg($macroName, 'id', 'Defines the base-Id applied to editable field(s).<br />If omitted, "lzy-editable-field{index}" is used.', '');

    if (($help == 'help') || ($help === false)) {
        $this->getArg($macroName, 'class', 'Class name that will be applied to editable fields', '');
        $this->getArg($macroName, 'showButtons', '[false|true|auto] Defines whether submit and cancel buttons are displayed (default: true).<br />"auto" means it will be displayed only on touch devices.', true);
        $this->getArg($macroName, 'dataSource', '<code>path</code> (optional) defines path&filename where to store data (default: page folder)', '');

        $this->getArg($macroName, 'dataSelector', 'Name of the element(s) to be visualized. Use format "A|B|C" to specify a list of names.', false);
        $this->getArg($macroName, 'targetSelector', '(optional) Id of DOM element(s). If not specified, id will be derived from elementName.  Use format "A|B|C" to specify a list of ids.', false);

        //        $this->getArg($macroName, 'protectedCells', '(optional) E.g. ""', '');

        $this->getArg($macroName, 'nFields', '[number] If set, creates multiple editable elements.', 1);
        $this->getArg($macroName, 'useRecycleBin', '[true|false] If true, previous values will be saved in a recycle bin rather than discared (default: false)', false);
        $this->getArg($macroName, 'freezeFieldAfter', '[seconds] If set, non-empty fields will be frozen after given number of seconds', '');
        $this->getArg($macroName, 'editableBy', '[true|false|loggedin|privileged|admins] If set, defines who can edit values.', true);
        $this->getArg($macroName, 'multiline', 'If true, the editable input element allows users to enter multiple lines (-> textarea).', false);
        $this->getArg($macroName, 'allowMultiLine', 'If true, user can invoke multi-line editing by double-clicking the editable field.', false);
        $this->getArg($macroName, 'output', 'If false, no editable fields will be rendered. Instead, a data-reference is returned. Thus, some other module such as Table() can render the fields autonomiously.', false);
        $this->getArg($macroName, 'liveData', 'If true, data values are immediately updated if the database on the host is modified.', false);
        $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);
        return '';
    }
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    $args = $this->getArgsArray($macroName); // get all args, some of which are passed through to htmltable.class
    $edbl = new Editable( $this->lzy, $args );
    $out = $edbl->render();

    return $out;
}); // editable




<?php
require_once SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';

$page->addModules('~ext/livedata/js/live_data.js');

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

	// how to get access to macro arguments:
    $file = $this->getArg($macroName, 'dataSource', 'Defines the data-source from which to retrieve the data element(s)', '');
    $this->getArg($macroName, 'dataSelector', 'Name of the element(s) to be visualized. Use format "A|B|C" to specify a list of names.', false);
    $this->getArg($macroName, 'targetSelector', '(optional) Id of DOM element(s). If not specified, id will be derived from elementName.  Use format "A|B|C" to specify a list of ids.', false);
    $this->getArg($macroName, 'pollingTime', '(optional) Polling time, i.e. the time the server waits for new data before giving up and responding with "No new data".', false);
    $this->getArg($macroName, 'output', '[true|false] If false, no visible output is rendered, only the mechanism is set up. So, you need to create the DOM elements corresponding to targetSelectors by other means.', true);
    $this->getArg($macroName, 'initJs', '[true|false] If true, the update mechanism will be started. (Default: true)', true);
    $this->getArg($macroName, 'callback', '(optional) If defined, the js function will be called before updating the correspondint target value.', false);
    $this->getArg($macroName, 'postUpdateCallback', '(optional) If defined, the js function will be called after updating the correspondint target value.', false);
    $this->getArg($macroName, 'watchdog', '[true|false] If true, a watchdog routine will be running in the background, '.
        'regularly checking whether communication with the server is still working. Default: true.', true);
    $this->getArg($macroName, 'timeout', '[time] After timing out, live updates will stop and the window will be frozen. '.
        'Time defined as string, e.g. "1hour". Default: 10min.', '10min');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    if ($file === 'help') {
        return '';
    }

    if (!$file) {
        $this->compileMd = true;
        return "**Error**: argument ``file`` not specified.";
    }

    $args = $this->getArgsArray($macroName);
    $ld = new LiveData($this->lzy, $args);

    $str = $ld->render();

    if (!isset($_SESSION['lizzy']['ajaxServerAbort'])) {
        $_SESSION['lizzy']['ajaxServerAbort'] = false;
    }
    $this->optionAddNoComment = true;
	return $str;
});

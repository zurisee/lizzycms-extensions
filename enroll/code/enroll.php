<?php

require_once SYSTEM_PATH.'extensions/enroll/code/enrollment.class.php';

$macroName = basename(__FILE__, '.php');
$this->readTransvarsFromFile( resolvePath("~ext/$macroName/" .LOCALES_PATH. "vars.yaml"), false, true);

define('ENROLL_LOG_FILE', 'enroll.log.csv');
define('ENROLL_DATA_FILE', 'enroll.yaml');

$GLOBALS['enroll_form_created']['std'] = false;

$page->addModules('~ext/enroll/js/enroll.js, ~ext/enroll/css/_enroll.css, POPUPS');

//resetScheduleFile();

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $h = $this->getArg($macroName, 'nNeeded', 'Number of fields in category "needed"', 1);
    $this->getArg($macroName, 'nReserve', 'Number of fields in category "reserve" -> will be visualized differently', 0);
    $this->getArg($macroName, 'listname', 'Any word identifying the enrollment list', "Enrollment-List$inx");
    $this->getArg($macroName, 'header', 'Optional header describing the enrollment list', false);
    $this->getArg($macroName, 'file', 'The file in which to store enrollment data. Default: "&#126;page/enroll.yaml". ', null);
    $this->getArg($macroName, 'globalFile', 'The file to be used by all subsequent instances of enroll(). Default: false. ', false);
    $this->getArg($macroName, 'logAgentData', "[true,false] If true, logs visitor's IP and browser info (illegal if not announced to users)", false);
    $this->getArg($macroName, 'freezeTime', "[false, 0, seconds, ISO-datetime] Defines how long, resp. until when a user can delete/modify his/her entry. 'false' means forever, '0' means not modifiable at all. Duration is specified as a number of seconds. Deadline as an ISO datetime (default: 86400 = 1 day)", 86400);
    $this->getArg($macroName, 'editable', '[true|false] If true, users can modify their (custom field-) entries', false);
    $this->getArg($macroName, 'hideNames', "[true|false|initials] If true, names are not revealed, i.e. presented as '****'.", false);
    $this->getArg($macroName, 'unhideNamesForGroup', '[group] If set, names are shown to users of given group(s), to others they remain hidden.', false);
    $this->getArg($macroName, 'notify', 'Activates notification of designated persons, either upon user interactions or in regular intervals. See <a href="https://getlizzy.net/macros/extensions/enroll/">documentation</a> for details.', false);
    $this->getArg($macroName, 'notifyFrom', 'See <a href="https://getlizzy.net/macros/extensions/enroll/">documentation</a> for details.', '');
    $this->getArg($macroName, 'scheduleAgent', 'Specifies user code to assemble and send notfications. See <a href="https://getlizzy.net/macros/extensions/enroll/">documentation</a> for details.', 'scheduleAgent.php');
    $tooltip = $this->getArg($macroName, 'tooltips', 'Show content of custom elements in tooltips.', false);

    $this->getArg($macroName, 'n_needed', 'Synonym for "nNeeded"', false);
    $this->getArg($macroName, 'n_reserve', 'Synonym for "nReserve"', false);

    if ($h === 'help') {
        return '';
    }
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    if ($h === 'info') {
        $out = $this->translateVariable('lzy-enroll-info', true);
        $this->compileMd = true;
        return $out;
    }

    if ($tooltip) {
        $this->page->addModules( 'TOOLTIPSTER' );
        $this->page->addJq('$(\'.tooltipster\').tooltipster();');
    }

    $args = $this->getArgsArray($macroName);

    $enroll = new Enrollment($this->lzy, $inx, $args);
    $out = $enroll->render();
	return $out;
});




function resetScheduleFile()
{
    $thisSrc = $GLOBALS['lizzy']['pathToPage'];
    $file = SCHEDULE_FILE;
    $schedule = getYamlFile($file);
    $modified = false;
    foreach ($schedule as $i => $rec) {
        $src = $rec['src'];
        if ($src === $thisSrc) {
            unset($schedule[$i]);
            $modified = true;
        }
    }
    if ($modified) {
        $schedule = array_values($schedule);
        writeToYamlFile($file, $schedule);
    }
}

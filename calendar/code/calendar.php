<?php

require_once ( dirname(__FILE__).'/calendar.class.php');
// to do: handle multiple invocations -> load resources only once

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name


$this->page->addCssFiles('~sys/extensions/calendar/third-party/fullcalendar/fullcalendar.min.css,'.
                         '~sys/extensions/calendar/third-party/fullcalendar/fullcalendar.print.min.css@print');

$this->page->addJsFiles('MOMENT');

$lang = $this->config->lang;
$this->page->addJqFiles("~sys/extensions/calendar/third-party/fullcalendar/fullcalendar.min.js,");
$localeFile = "~sys/extensions/calendar/third-party/fullcalendar/locale/$lang.js";
$localeFile1 = resolvePath($localeFile);
if (file_exists(resolvePath($localeFile))) {
    $this->page->addJqFiles($localeFile);
}
$this->page->addJqFiles("~sys/extensions/calendar/js/calendar.js");
$this->page->addCssFiles("~sys/extensions/calendar/css/_calendar.css");

$this->readTransvarsFromFile(resolvePath("~ext/$macroName/config/vars.yaml"));



$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $source = $this->getArg($macroName, 'file', 'File path where data shall be fetched from (and stored if in editing mode).', 'calendar.yaml');
    $this->getArg($macroName, 'calEditingPermission', '[all|group name(s)] Defines, who will be able to add and modify calendar entries.', false);
    $this->getArg($macroName, 'fields', '[comma-separated-list] Comma separated list of (custom-) fields, e.g. "fields:\'title,topic,speaker,moderator,start,end,location,comment,category,time\',"', false);
    $this->getArg($macroName, 'defaultEventDuration', '[allday|hh:mm] In month and year view: defines the default duration of new events.', false);
    $this->getArg($macroName, 'publish', '[true|filepath] If given, the calendar will be exported to designated file. The file will be place in ics/ if not specified explicitly.', false);
    $this->getArg($macroName, 'publishCallback', '[string] Provide name of a script in code/ to render output for the \'description\' field of events. Script name must start with "-" (to distinguish from other types of scripts).', false);
    $this->getArg($macroName, 'output', '[true|false] If false, no output will be rendered (useful in conjunction with publish).', true);
    $this->getArg($macroName, 'tooltips', 'Name of event property that shall be shown in a tool-tip. If "true", all will be shown.', true);
    $this->getArg($macroName, 'categories', 'A (comma separated) list of supported categories.', '');
    $this->getArg($macroName, 'showCategories', '[comma-separated-list] A comma separated list of categories - only events carrying that category will be presented.', '');
    $this->getArg($macroName, 'defaultPrefix', 'Prefix applied to event titles (if no category-specific prefix is defined). E.g. "[XY] ".', '');
    $this->getArg($macroName, 'categoryPrefixes', '[string|comma-separated-list] Category-specific prefixes for events.', '');
    $this->getArg($macroName, 'defaultView', '[week,month,year] Defines the initial view when the widget is presented for the very first time', '');
    $this->getArg($macroName, 'headerButtons', '[week,month,year] Defines which buttons (to select views) will be displayed and in which order (ltr)', '');
    $this->getArg($macroName, 'buttonLabels', '[comma-separated-list] Overrides labels of the view buttons, provide as many as you need in the following order: [list,month,week,day,today]', '');

    if ($source == 'help') {
        return '';
    }

    $args = $this->getArgsArray($macroName);

    $cal = new LzyCalendar($this->lzy, $inx, $args);
    $out = $cal->render();
    return $out;
});


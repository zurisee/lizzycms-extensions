<?php

require_once ( dirname(__FILE__).'/calendar.class.php');

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name


$this->page->addCssFiles('~sys/extensions/calendar/third-party/fullcalendar/main.css,');

$this->page->addJsFiles('MOMENT');

$lang = $this->config->lang;
$this->page->addJqFiles("~sys/extensions/calendar/third-party/fullcalendar/main.js,");
$localeFile = "~sys/extensions/calendar/third-party/fullcalendar/locales/$lang.js";
$localeFile1 = resolvePath($localeFile);
if (file_exists(resolvePath($localeFile))) {
    $this->page->addJqFiles($localeFile);
}
$this->page->addJqFiles("~sys/extensions/calendar/js/calendar.js");
$this->page->addCssFiles("~sys/extensions/calendar/css/_calendar.css");

$this->readTransvarsFromFile(resolvePath("~ext/$macroName/config/vars.yaml"), true, true);


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $source = $this->getArg($macroName, 'file', 'File path where data shall be fetched from (and stored if in editing mode).', 'calendar.yaml');
    $this->getArg($macroName, 'editingPermission', '[all|group name(s)] Defines, who will be able to add and modify calendar entries.', false);
    $this->getArg($macroName, 'fields', '[comma-separated-list] Comma separated list of (custom-) fields, e.g. "fields:\'title,topic,speaker,moderator,start,end,location,comment,category,time\',"', false);
    $this->getArg($macroName, 'defaultEventDuration', '[allday|minutes] In month and year view: defines the default duration of new events.', false);
    $this->getArg($macroName, 'class', 'Class applied to wrapper div.', '');
    $this->getArg($macroName, 'options', '[light] For further options, currently just "light" -> light color scheme for categories', '');
    $this->getArg($macroName, 'publish', '[true|filepath] If given, the calendar will be exported to designated file. The file will be place in ics/ if not specified explicitly.', false);
    $this->getArg($macroName, 'publishCallback', '[string] Provide name of a script in code/ to render output for the \'description\' field of events. Script name must start with "-" (to distinguish from other types of scripts).', false);
    $this->getArg($macroName, 'output', '[true|false] If false, no output will be rendered (useful in conjunction with publish).', true);
    $this->getArg($macroName, 'tooltips', 'Name of event property that shall be shown in a tool-tip. If "true", all will be shown.', false);
    $this->getArg($macroName, 'categories', '[comma-separated-list] A list of supported categories.', '');
    $this->getArg($macroName, 'showCategories', '[comma-separated-list] A comma separated list of categories - only events carrying that category will be presented.', '');
    $this->getArg($macroName, 'prefix', 'Prefix applied to event titles (if no category-specific prefix is defined). E.g. "[XY] ".', '');
    $this->getArg($macroName, 'categoryPrefixes', '[string|comma-separated-list] Category-specific prefixes for events.', '');
    $this->getArg($macroName, 'defaultView', '[week,month,year] Defines the initial view when the widget is presented for the very first time', '');
    $this->getArg($macroName, 'headerLeftButtons', '[next,prev,today] Defines which buttons (to select views) will be displayed and in which order (ltr)', 'prev,today,next');
    $this->getArg($macroName, 'headerRightButtons', '[day,week,month,year] Defines which buttons (to select views) will be displayed and in which order (ltr)', 'week,month,year');
    $this->getArg($macroName, 'businessHours', '[hh:mm - hh:mm] Defines business hours (white background), default: "08:00 - 17:00"', '');
    $this->getArg($macroName, 'visibleHours', '[hh:mm - hh:mm] Defines hours shown in week view, default: "08:00 - 17:00"', '');
    $this->getArg($macroName, 'freezePast', '[true,false,(!)group] If true, events in the past can no longer be edited, default: false', false);
    $this->getArg($macroName, 'editPermissionWarning', '[true,false] If true, user sees warning if trying to edit calendar with insufficient permission, default: false', false);
    $this->getArg($macroName, 'eventTitleRequired', '[true,false] Defines whether title field a new event must be defined, default: true', true);
    $this->getArg($macroName, 'fullCalendarOptions', '[string] Will be passed through to the FullCalendar object (see https://fullcalendar.io/docs#toc)', '');
    $this->getArg($macroName, 'useRecycleBin', '[true,false] If true, changes to calendar data (via editing mode) don\'t overrite old data but send a copy to a recycle bin. (Default: false)', false);
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    if ($source === 'help') {
        return '';
    }

    $args = $this->getArgsArray($macroName);

    $cal = new LzyCalendar($this->lzy, $inx, $args);
    $out = $cal->render();
    return $out;
});


<?php

if (!defined('CALENDAR_BACKEND')) { define('CALENDAR_BACKEND', '~sys/extensions/calendar/backend/_cal-backend.php'); }

// to do: handle multiple invocations -> load resources only once

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name


$this->page->addCssFiles('~sys/extensions/calendar/third-party/fullcalendar/fullcalendar.min.css');
        //    '~sys/extensions/calendar/third-party/fullcalendar/fullcalendar.print.min.css');

$this->page->addJsFiles('MOMENT');

$lang = $this->config->lang;
$this->page->addJqFiles("~sys/extensions/calendar/third-party/fullcalendar/fullcalendar.min.js,");
$localeFile = resolvePath("~sys/extensions/calendar/third-party/fullcalendar/locale/$lang.js");
if (file_exists($localeFile)) {
    $this->page->addJqFiles($localeFile);
}
$this->page->addJqFiles("~sys/extensions/calendar/js/calendar.js");
$this->page->addCssFiles("~sys/extensions/calendar/css/_calendar.css");

$this->readTransvarsFromFile(resolvePath("~ext/$macroName/config/vars.yaml"));



$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $lang = $this->config->lang;

    $source = $this->getArg($macroName, 'file', 'File path where data shall be fetched and stored.', 'calendar.yaml');

    $edPermitted = $this->getArg($macroName, 'calEditingPermission', '[all|group name(s)] Defines, who will be able to add and modify calendar entries.', false);

    $publish = $this->getArg($macroName, 'publish', 'If set, Lizzy will switch to calendar publishing mode (using given name). Use a calendar app to subscribe to this calendar.', false);

    $tooltips = $this->getArg($macroName, 'tooltips', 'Name of event property that shall be showed in a tool-tip.', '');

    $tags = $this->getArg($macroName, 'tags', 'A (comma separated) list of tags.', '');

    if ($source == 'help') {
        return '';
    }
    $source = resolvePath($source, true);
    if ($publish) {
        $domain = $this->getArg($macroName, 'domain', 'Domain info that will be included in the published calendar.', $this->lzy->pageUrl);
        exit( renderICal($source, $publish, $domain) );
    }
    if ($tooltips) {
        $tooltips = <<<EOT
            element.qtip({
              content: event.$tooltips
            });

EOT;
        $this->page->addCssFiles('~sys/extensions/calendar/third-party/qtip/jquery.qtip.min.css');
        $this->page->addJqFiles("~sys/extensions/calendar/third-party/qtip/jquery.qtip.min.js,");
    }


    $backend = CALENDAR_BACKEND;
    $viewMode = (isset($_SESSION['lizzy']['calMode'])) ? $_SESSION['lizzy']['calMode'] : 'agendaWeek';

    if (($edPermitted == 'all') || ($edPermitted == '*')) {
        $edPermitted = true;
    } elseif ($edPermitted) {
        $edPermitted = $this->lzy->auth->checkAdmission($edPermitted);
    }
    $edPermStr = $edPermitted?'true':'false';

    $js = '';
    if ($inx == 1) {
        $js = <<<EOT
var calBackend = '$backend';
var calLang = '$lang';
var lzyCal = [];
var calEditingPermission = false;
var calDefaultView = 'month';
EOT;
    }

    $js .= <<<EOT

lzyCal[$inx] = {
    tooltips: '$tooltips',
    calDefaultView: '$viewMode',
    calEditingPermission: $edPermStr
};

EOT;
    $this->page->addJs($js);

    $popupForm = renderDefaultCalPopUpForm($source, $tags);
//    $popupForm = renderDefaultCalPopUpForm($source);
    if (function_exists('loadCustomCalPopup')) {
        loadCustomCalPopup($inx, $this->lzy, $popupForm, $edPermitted);

    } else {
        loadDefaultPopup($inx, $this->lzy, $popupForm, $edPermitted);
    }


    $edClass = $edPermitted ? ' class="lzy-calendar lzy-cal-editing"' : ' class="lzy-calendar"';
    $str = "<div id='lzy-calendar$inx'$edClass data-lzy-cal-inx='$inx'></div>";
    $_SESSION['lizzy']['cal'][$inx] = $source;
	return $str;
});



function renderDefaultCalPopUpForm($source, $tags)
{
    $backend = CALENDAR_BACKEND;

    $tagsCombo = '';
    if ($tags) {
        $tags = explode(',', $tags);
        foreach ($tags as $tag) {
            $tag = trim($tag);
            $value = translateToIdentifier($tag);
            $tagsCombo .= "\t<option value='$value'>$tag</option>\n";
        }
        $tagsCombo = <<<EOT
<label for="lzy_cal_tags">{{ lzy-cal-tags-label }}</label>
<select id="lzy_cal_tags" name="tags">
    <option value="">{{ lzy-cal-tags-all }}</option>
$tagsCombo
</select>

EOT;
    }

    // render default popup-form:
    $popupForm = <<<EOT

        <h1><span id="lzy-cal-new-event-header">{{ lzy-cal-new-entry }}</span><span id="lzy-cal-modify-event-header">{{ lzy-cal-modif-entry }}</span></h1>
        <form id='lzy-calendar-default-form' method='post' action="$backend">
            <input type='hidden' id='lzy-inx' name='inx' value='' />
            <input type='hidden' id='lzy-rec-id' name='rec-id' value='' />
            <input type='hidden' id='lzy-data-src' name='data-src' value='$source' />
            <input type='hidden' id='lzy-allday' name='allday' value='' />

<!-- innerForm -->
 
            <div class='field-wrapper field-type-textarea'>
                <label for='lzy_cal_comment' class="lzy-invisible">{{ lzy-cal-comment }}</label>
                <textarea id='lzy_cal_comment' name='comment' placeholder='{{ lzy-cal-comment-placeholder }}'></textarea>
            </div>
$tagsCombo
            <div id="lzy_cal_delete_entry" class='field-wrapper lzy_cal_delete_entry' style="display:none">
                <label for="lzy-cal-delete-entry-checkbox">
                    <input type='checkbox' id="lzy-cal-delete-entry-checkbox" />
                {{ lzy-cal-delete-entry }}</label>
                <input type='button' value='{{ lzy-cal-delete-entry-now }}' id="lzy_btn_delete_entry" class='lzy-button form-button' style="display: none"/>
            </div>
            
            <div class="lzy-cal-form-buttons">
                <input type='submit' id='lzy-calendar-default-submit' value='{{ Save }}' class='lzy-button form-button' />
                <input type='reset' id='lzy-calendar-default-cancel' value='{{ Cancel }}' class='lzy-button form-button' />
            </div>
        
        </form>

EOT;


    $innerForm = <<<EOT
            <div class='field-wrapper field-type-text lzy-cal-event'>
                <label for='lzy_cal_event_name' class="lzy-invisible">{{ lzy-cal-event }}</label>
                <input type='text' id='lzy_cal_event_name' name='title' required aria-required='true'  placeholder='{{ lzy-cal-event-placeholder }}' />
            </div><!-- /field-wrapper -->

            <div class='field-wrapper field-type-text lzy-cal-location'>
                <label for='lzy_cal_event_location' class="lzy-invisible">{{ lzy-cal-location }}</label>
                <input type='text' id='lzy_cal_event_location' name='location'  placeholder='{{ lzy-cal-location-placeholder }}' />
            </div><!-- /field-wrapper -->
            
            <fieldset>
                <legend>{{ lzy-cal-legend-from }}</legend>
                <div class='field-wrapper field-type-date lzy-cal-start-date'>
                    <label for='lzy_cal_start_date' class="lzy-invisible">{{ lzy-cal-date }}</label>
                    <input type='date' id='lzy_cal_start_date' name='start-date' placeholder='z.B. 1.1.1970' value='' />
                    <label for='lzy_cal_start_time' class="lzy-invisible">{{ lzy-cal-time }}</label>
                    <input type='time' id='lzy_cal_start_time' name='start-time' placeholder='08:00' value='' />
                </div><!-- /field-wrapper -->
            </fieldset>

            <fieldset>
                <legend>{{ lzy-cal-legend-till }}</legend>
                <div class='field-wrapper field-type-date lzy-cal-end-date'>
                    <label for='lzy_cal_end_date' class="lzy-invisible">{{ lzy-cal-date }}</label>
                    <input type='date' id='lzy_cal_end_date' name='end-date' placeholder='z.B. 1.1.1970' value='' />
                    <label for='lzy_cal_end_time' class="lzy-invisible">{{ lzy-cal-time }}</label>
                    <input type='time' id='lzy_cal_end_time' name='end-time' placeholder='09:00' value='' />
                </div><!-- /field-wrapper -->
            </fieldset>

EOT;

        return ['outerForm' => $popupForm, 'innerForm' => $innerForm];
} // renderDefaultCalPopUpForm



function loadDefaultPopup($inx, $lzy, $popupForm, $calEditingPermission)
{
    if ($calEditingPermission) {
        $form = str_replace('<!-- innerForm -->', $popupForm['innerForm'], $popupForm['outerForm']);

        // render popup related arguments:
        $args = [
            'text' => $form,
            'class' => 'lzy-cal-popup',
            'draggable' => true,
            'triggerSource' => 'none',
            'triggerEvent' => 'none',
            'closeOnBgClick' => true,
            'width' => '20em',
        ];

        $lzy->page->addPopup($args);
    }

} // renderDefaultPopup




function renderICal($source, $name, $domain)
{
    $ds = new DataStorage2(['dataFile'=> $source]);
    $data = $ds->read();


    $vCalendar = new \Eluceo\iCal\Component\Calendar($domain);

    foreach ($data as $key => $rec) {
        $vEvent = new \Eluceo\iCal\Component\Event();
        $vEvent
            ->setDtStart(new \DateTime($rec['start']))
            ->setDtEnd(new \DateTime($rec['end']))
            ->setSummary($rec['title'])
            ->setLocation($rec['location'])
            ->setDescription($rec['comment'])
            ->setUniqueId("$name$key")
        ;
        $vCalendar->addComponent($vEvent);
    }
    header('Content-Type: text/calendar; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$name.ics\"");
    $out = $vCalendar->render();
    return $out;
} // renderICal


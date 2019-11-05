<?php

if (!defined('CALENDAR_BACKEND')) { define('CALENDAR_BACKEND', '~sys/extensions/calendar/backend/_cal-backend.php'); }


// to do: handle multiple invocations -> load resources only once

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name


$this->page->addCssFiles('~sys/extensions/calendar/third-party/fullcalendar/fullcalendar.min.css');
        //    '~sys/extensions/calendar/third-party/fullcalendar/fullcalendar.print.min.css');

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

    $source = $this->getArg($macroName, 'file', 'File path where data shall be fetched and stored.', 'calendar.yaml');
    $this->getArg($macroName, 'calEditingPermission', '[all|group name(s)] Defines, who will be able to add and modify calendar entries.', false);
    $this->getArg($macroName, 'publish', 'If set, Lizzy will switch to calendar publishing mode (using given name). Use a calendar app to subscribe to this calendar.', false);
//    $this->getArg($macroName, 'tooltips', 'Name of event property that shall be showed in a tool-tip.', '');
    $this->getArg($macroName, 'categories', 'A (comma separated) list of supported categories.', '');
    $this->getArg($macroName, 'showCategories', 'A (comma separated) list of categories - only events carrying that category will be presented.', '');
    $this->getArg($macroName, 'domain', 'Domain info that will be included in the published calendar.', $this->lzy->pageUrl);
    $this->getArg($macroName, 'icalPrefix', 'Prefix for iCal events. If a comma-separated list is supplied, elements are interpreted per category.', $this->lzy->pageUrl);

    if ($source == 'help') {
        return '';
    }

    $args = $this->getArgsArray($macroName);

    $cal = new LzyCalendar($this->lzy, $inx, $args);
    $out = $cal->render();
    return $out;
});



class LzyCalendar
{
    public function __construct($lzy, $inx, $args)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
        $this->inx = $inx;
        $this->lang = $this->lzy->config->lang;
        $this->source = $args['file'];
        $this->edPermitted = $args['calEditingPermission'];
        $this->publish = $args['publish'];
//        $this->tooltips = $args['tooltips'];
        $this->category = $args['categories'];
        $categories = array_map('trim', explode(',', $this->category));

        $this->showCategories = $args['showCategories'];
        $this->domain = $args['domain'];
        $icalPrefix = $args['icalPrefix'];
        if (strpos($icalPrefix, ',') !== false) {
            $this->icalPrefix = explode(',', $icalPrefix);
            $this->icalPrefix = array_combine($categories, explode(',', $icalPrefix));
        } else {
            $this->icalPrefix = $icalPrefix;
        }
        $this->timezone = new DateTimeZone($_SESSION['lizzy']['systemTimeZone']);

    }


    public function render()
    {
        $inx = $this->inx;
        $this->source = resolvePath($this->source, true);
        if ($this->publish) {
            exit( $this->renderICal() );
        }
//        if ($this->tooltips) {
//            $tooltips = <<<EOT
//            element.qtip({
//              content: event.{$this->tooltips}
//            });
//
//EOT;
//            $this->page->addCssFiles('~sys/extensions/calendar/third-party/qtip/jquery.qtip.min.css');
//            $this->page->addJqFiles("~sys/extensions/calendar/third-party/qtip/jquery.qtip.min.js,");
//        }


        $backend = CALENDAR_BACKEND;
        $viewMode = (isset($_SESSION['lizzy']['calMode'])) ? $_SESSION['lizzy']['calMode'] : 'agendaWeek';

        if (($this->edPermitted === 'all') || ($this->edPermitted === '*')) {
            $this->edPermitted = true;
        } elseif ($this->edPermitted) {
            $this->edPermitted = $this->lzy->auth->checkAdmission($this->edPermitted);
        }
        $edPermStr = $this->edPermitted?'true':'false';

        $js = '';
        if ($inx == 1) {
            $js = <<<EOT
var calBackend = '$backend';
var calLang = '{$this->lang}';
var lzyCal = [];
var calEditingPermission = false;
var calDefaultView = 'month';
EOT;
        }

        $js .= <<<EOT

lzyCal[$inx] = {
    calDefaultView: '$viewMode',
    calEditingPermission: $edPermStr
};

EOT;
//        $js .= <<<EOT
//
//lzyCal[$inx] = {
//    tooltips: '{$this->tooltips}',
//    calDefaultView: '$viewMode',
//    calEditingPermission: $edPermStr
//};
//
//EOT;
        $this->page->addJs($js);

        if ($this->edPermitted) {
            $popupForm = $this->renderDefaultCalPopUpForm();
            if (function_exists('loadCustomCalPopup')) {
                loadCustomCalPopup($inx, $this->lzy, $popupForm, $this->edPermitted);

            } else {
                $this->loadDefaultPopup($popupForm);
            }
        }

        $defaultDate = (isset($_SESSION['lizzy']['defaultDate']) && $_SESSION['lizzy']['defaultDate']) ? $_SESSION['lizzy']['defaultDate']: date('Y-m-d');
        // fix a peculiarity of fullcalendar: round up 1 week to make sure the same month is displayed as before
        if ($viewMode == 'month') {
            $t = strtotime('+1 week', strtotime($defaultDate));
            $defaultDate = date('Y-m-d', $t);
        }

        $edClass = $this->edPermitted ? ' class="lzy-calendar lzy-cal-editing"' : ' class="lzy-calendar"';
        $str = "<div id='lzy-calendar$inx'$edClass data-lzy-cal-inx='$inx' data-lzy-cal-start='$defaultDate'></div>";
        $_SESSION['lizzy']['cal'][$inx] = $this->source;
        $_SESSION['lizzy']['calShowCategories'][$inx] = $this->showCategories;
        return $str;
    }


    private function renderDefaultCalPopUpForm()
    {
        $backend = CALENDAR_BACKEND;
        $inx = $this->inx;
        $category = $this->category;

        $categoryCombo = '';
        if ($category) {
            $categories = explode(',', $category);
            foreach ($categories as $tag) {
                $tag = trim($tag);
                $value = translateToIdentifier($tag);
                $categoryCombo .= "\t<option value='$value'>$tag</option>\n";
            }
            $categoryCombo = <<<EOT
    <label for="lzy_cal_category">{{ lzy-cal-category-label }}</label>
    <select id="lzy_cal_category" name="category">
        <option value="">{{ lzy-cal-category-all }}</option>
    $categoryCombo
    </select>

EOT;
        }

        // render default popup-form:
        $popupForm = <<<EOT
    
            <h1><span id="lzy-cal-new-event-header">{{ lzy-cal-new-entry }}</span><span id="lzy-cal-modify-event-header">{{ lzy-cal-modif-entry }}</span></h1>
            <form id='lzy-calendar-default-form' method='post' action="$backend">
                <input type='hidden' id='lzy-inx' name='inx' value='' />
                <input type='hidden' id='lzy-rec-id' name='rec-id' value='' />
                <input type='hidden' id='lzy-allday' name='allday' value='' />
    
    <!-- innerForm -->
     
                <div class='field-wrapper field-type-textarea'>
                    <label for='lzy_cal_comment' class="lzy-invisible">{{ lzy-cal-comment }}</label>
                    <textarea id='lzy_cal_comment' name='comment' placeholder='{{ lzy-cal-comment-placeholder }}'></textarea>
                </div>
    $categoryCombo
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



    private function loadDefaultPopup($popupForm)
    {
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

        $this->lzy->page->addPopup($args);
    } // renderDefaultPopup



    private function renderICal()
    {
        require dirname(__FILE__) . '/utils.php';

        $ds = new DataStorage2(['dataFile'=> $this->source]);
        $data = $ds->read();

        $recsToShow = $this->filterCalRecords($data);

        $vCalendar = new \Eluceo\iCal\Component\Calendar($this->domain);

        foreach ($recsToShow as $key => $rec) {
            if (is_array($this->icalPrefix)) {
                $category = $rec['category'];
                $prefix = isset($this->icalPrefix[$category]) ? $this->icalPrefix[$category]: '[]';
            } else {
                $prefix = $this->icalPrefix;
            }
            $vEvent = new \Eluceo\iCal\Component\Event();
            $vEvent
                ->setDtStart(new \DateTime($rec['start']))
                ->setDtEnd(new \DateTime($rec['end']))
                ->setSummary($prefix.$rec['title'])
//                ->setSummary($rec['title'])
                ->setLocation($rec['location'])
                ->setDescription($rec['comment'])
                ->setUniqueId("{$this->publish}$key")
            ;
            $vCalendar->addComponent($vEvent);
        }
        $filename = translateToFilename($this->publish, false);
        header('Content-Type: text/calendar; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename.ics\"");
        $out = $vCalendar->render();
        return $out;
    } // renderICal




    public function filterCalRecords($data)
    {
        if (!$this->showCategories) {
            return $data;
        }

        $categoriesToShow = ',' . str_replace(' ', '', $this->showCategories) . ',';

        // Accumulate an output array of event data arrays.
        $output_arrays = array();
        foreach ($data as $i => $rec) {

            // Convert the input array into a useful Event object
            $event = new Event($rec, $this->timezone);

            // check for category:
            if ($categoriesToShow && isset($event->properties["category"]) && $event->properties["category"]) {
                $eventsCategory = explode(',', $event->properties["category"]);
                foreach ($eventsCategory as $evTag) {
                    if ($evTag && (stripos($categoriesToShow, ",$evTag,") === false)) {
                        continue 2;
                    }
                    $output_arrays[] = array_merge($event->toArray(), ['i' => $i]);
                }
            }

//            // If the event is in-bounds, add it to the output
//            if ($event->isWithinDayRange($this->rangeStart, $this->rangeEnd)) {
//                $output_arrays[] = array_merge($event->toArray(), ['i' => $i]);
//            }
        }
        return $output_arrays;
    } // filterCalRecords


} // class LzyCalendar


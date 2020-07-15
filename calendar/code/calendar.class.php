<?php

if (!defined('CALENDAR_BACKEND')) { define('CALENDAR_BACKEND', '~sys/extensions/calendar/backend/_cal-backend.php'); }
define('DEFAULT_EVENT_DURATION', 120); // in minutes


class LzyCalendar
{
    public function __construct($lzy, $inx, $args)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
        $this->inx = $inx;
        $this->lang = $this->lzy->config->lang;
        $this->source = $args['file'];
        $this->edPermitted = isset($args['editingPermission']) ? $args['editingPermission'] : false;
        $this->fields = isset($args['fields']) ? $args['fields']: false;
        if ($this->fields) {
            $this->fields = explodeTrim(',', $this->fields);
        } else {
            $this->fields = [];
        }

        // Whether to publish the calendar (i.e. save in an .ics file):
        $this->publish = isset($args['publish']) ? $args['publish']: false;
        $this->publishCallback = isset($args['publishCallback']) ? $args['publishCallback']: false;

        // Whether to suppress out generation (e.g. if only used to publish .ics)
        $this->output = isset($args['output']) ? $args['output']: true;

        // Tooltips:
        $tooltips = isset($args['tooltips']) ? $args['tooltips']: false;
        $this->tooltips = $tooltips? 'true': 'false';
        if ($tooltips) {
            $lzy->page->addModules('QTIP');
        }

        // Categories:
        $this->category = isset($args['categories']) ? $args['categories']: '';
        $categories = explodeTrim(',', $this->category);
        $this->showCategories = isset($args['showCategories']) ? $args['showCategories']: '';
        $this->domain = isset($args['domain']) ? $args['domain']: $_SERVER["HTTP_HOST"];
        $this->eventTitleRequired = isset($args['eventTitleRequired']) ? $args['eventTitleRequired']: true;

        // Prefixes:
        $n = sizeof($categories);
        $defaultCatPrefix = isset($args['defaultPrefix']) ? $args['defaultPrefix']: '';
        $catPrefixes = isset($args['categoryPrefixes']) ? $args['categoryPrefixes']: '';
        if (strpos($catPrefixes, ',') !== false) {
            $catPrefixes = explode(',', "$catPrefixes,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,");
            $catPrefixes = array_slice($catPrefixes, 0, $n);
            $catPrefixAssoc = array_combine($categories, $catPrefixes);
            $this->categoryPrefixes = $catPrefixAssoc;
            $this->prefixJson = json_encode($catPrefixAssoc);
            $this->defaultCatPrefix = $defaultCatPrefix? $defaultCatPrefix: $catPrefixes[0];
        } else {
            $this->categoryPrefixes = [];
            $this->prefixJson = '{}';
            $this->defaultCatPrefix = $defaultCatPrefix? $defaultCatPrefix: '';
        }

        if (!isset($_SESSION['lizzy']['cal'][$inx])) {
            $_SESSION['lizzy']['cal'][$inx] = [];
        }

        // Default View:
        $defaultView = isset($args['defaultView']) ? $args['defaultView']: 'month';
        if (strpos($defaultView, 'week') !== false) {
            $this->defaultView = 'timeGridWeek';
        } elseif (strpos($defaultView, 'year') !== false) {
            $this->defaultView = 'listYear';
        } else {
            $this->defaultView = 'dayGridMonth';
        }

        // Default Event Duration
        $this->defaultEventDuration = isset($args['defaultEventDuration']) ? $args['defaultEventDuration']: false;

        // Timezone:
        $this->timezone = new DateTimeZone($_SESSION['lizzy']['systemTimeZone']);

        // UI:
        $this->headerLeftButtons = isset($args['headerLeftButtons']) ? $args['headerLeftButtons']: false;
        $this->headerRightButtons = isset($args['headerRightButtons']) ? $args['headerRightButtons']: false;
        $this->buttonLabels = isset($args['buttonLabels']) ? $args['buttonLabels']: false;

    } // __construct




    public function render()
    {
        $inx = $this->inx;
        $this->source = resolvePath($this->source, true);
        $calSession = &$_SESSION['lizzy']['cal'][$inx];
        $calSession['calShowCategories'] = '';

        // export ics file if requested:
        if ($this->publish) {
            $this->publishICal();
        }

        // suppress output if requested:
        if ($this->output === false) {
            return '';
        }

        $backend = CALENDAR_BACKEND;
        if (isset($calSession['initialDate'])) {
            $this->initialDate = $calSession['initialDate'];
        } else {
            $this->initialDate = date('Y-m-d');
            $calSession['initialDate'] = $this->initialDate;
        }
        $viewMode = (isset($calSession['calMode'])) ? $calSession['calMode'] : $this->defaultView;

        if (($this->edPermitted === 'all') || ($this->edPermitted === '*') || ($this->edPermitted === true)) {
            $this->edPermitted = true;
        } elseif ($this->edPermitted) {
            $this->edPermitted = $this->lzy->auth->checkAdmission($this->edPermitted);
        }
        $edPermStr = $this->edPermitted?'true':'false';

        $defaultEventDuration = DEFAULT_EVENT_DURATION;
        if ($this->defaultEventDuration) {
            if (stripos($this->defaultEventDuration, 'allday') !== false) {
                $defaultEventDuration = "'allday'";
            } else {
                $defaultEventDuration = intval($this->defaultEventDuration);
            }
        }

        // header buttons:
        $headerLeftButtons = $this->headerLeftButtons . ($this->edPermitted? ' addEventButton': '');
        $headerRightButtons = '';
        if ($this->headerRightButtons) {
            $hBs = explodeTrim(',', $this->headerRightButtons);
            $hBsAvailable = ['day' => 'timeGridDay', 'week' => 'timeGridWeek','month' => 'dayGridMonth','year' => 'listYear'];
            foreach ($hBs as $key => $hb) {
                if (isset($hBsAvailable[$hb])) {
                    $headerRightButtons .= "{$hBsAvailable[$hb]},";
                } else {
                    $headerRightButtons .= "$hb,";
                }
            }
            $headerRightButtons = rtrim($headerRightButtons, ',');
        } else {
            $headerRightButtons = 'timeGridWeek,dayGridMonth,listYear';
        }

        // inject js code into page body:
        $js = '';
        if ($inx == 1) {
            $js = <<<EOT
var calBackend = '$backend';
var calLang = '{$this->lang}';
var lzyCal = [];
EOT;
        }

        $js .= <<<EOT

lzyCal[$inx] = {
    initialView: '$viewMode',
    initialDate: '{$this->initialDate}',
    editingPermission: $edPermStr,
    catPrefixes: {$this->prefixJson},
    catDefaultPrefix: '{$this->defaultCatPrefix}',
    tooltips: {$this->tooltips},
    defaultEventDuration: $defaultEventDuration,
    headerLeftButtons: '$headerLeftButtons',
    headerRightButtons: '$headerRightButtons',
    buttonLabels: {
        prev: '{{ lzy-cal-label-prev }}',
        next: '{{ lzy-cal-label-next }}',
        prevYear: '{{ lzy-cal-label-prev-year }}',
        nextYear: '{{ lzy-cal-label-next-year }}',
        year: '{{ lzy-cal-label-year }}',
        month: '{{ lzy-cal-label-month }}',
        week: '{{ lzy-cal-label-week }}',
        day: '{{ lzy-cal-label-day }}',
        today: '{{ lzy-cal-label-today }}',
        list: '{{ lzy-cal-label-list }}',
    },
    calLabels: {
        weekText: '{{ lzy-cal-label-week-nr }}',
        allDayText: '{{ lzy-cal-label-allday }}',
        moreLinkText: '{{ lzy-cal-label-more-link }}',
        noEventsText: '{{ lzy-cal-label-empty-list }}',
    },
};

EOT;
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
        $calSession['page'] = $this->source;
        $this->renderFieldNames();

        return $str;
    } // render




    private function renderFieldNames()
    {
        $fieldNames = '';
        if (!isset($this->fields['summary'])) {
            $this->fields['summary'] = 'title';
        }
        foreach ($this->fields as $field) {
            if (!($flabel = $this->lzy->trans->translateVariable($field))) {
                $flabel = ucfirst($field);
            }
            $fieldNames .= "'$field':'$flabel', ";
        }
        $fieldNames = rtrim($fieldNames, ', ');
        $js = "lzyCal[{$this->inx}]['fieldLabels'] = { $fieldNames };";
        $this->page->addJs($js);
    } // renderFieldNames




    private function renderDefaultCalPopUpForm()
    {
        $backend = CALENDAR_BACKEND;
        $inx = $this->inx;
        $categoryCombo = $this->prepareCategoryCombo();
        $customFields = $this->prepareCustomFields();
        $required = $this->eventTitleRequired? ' required': '';

        // render default popup-form:
        $popupForm = <<<EOT
    
        <h1><span id="lzy-cal-new-event-header">{{ lzy-cal-new-entry }}</span><span id="lzy-cal-modify-event-header">{{ lzy-cal-modif-entry }}</span></h1>
        <form id='lzy-calendar-default-form' method='post' action="$backend">
            <input type='hidden' id='lzy-inx' name='inx' value='$inx' />
            <input type='hidden' id='lzy-rec-id' name='rec-id' value='' />
            <input type='hidden' id='lzy-allday' name='allday' value='' />

$categoryCombo

            <div class='field-wrapper field-type-text lzy-cal-event'>
                <label for='lzy_cal_event_name' class="lzy-cal-label">{{ lzy-cal-event-title }}:</label>
                <input type='text' id='lzy_cal_event_name' class="lzy-cal-field" name='title'$required placeholder='{{^ lzy-cal-event-placeholder }}' />
            </div><!-- /field-wrapper -->
           
           
            <div id="lzy_cal_allday_event" class='field-wrapper lzy_cal_allday_event'>
                <label for="lzy-cal-allday-event-checkbox">{{ lzy-cal-allday-event }}:
                    <input type='checkbox' id="lzy-cal-allday-event-checkbox" />
                </label>
            </div><!-- /field-wrapper -->
            

            <fieldset>
                <legend>{{ lzy-cal-legend-from }}</legend>
                <div class='field-wrapper field-type-date lzy-cal-start-date'>
                    <label for='lzy_cal_start_date' class="lzy-cal-label">{{ lzy-cal-start-date-label }}</label>
                    <input type='date' id='lzy_cal_start_date' name='start-date' placeholder='{{^ lzy-cal-start-date-placeholder }}' value='' />
                    <label for='lzy_cal_start_time' class="lzy-cal-label">{{ lzy-cal-start-time-label }}</label>
                    <input type='time' id='lzy_cal_start_time' name='start-time' placeholder='{{^ lzy-cal-start-time-placeholder }}' value='' />
                </div><!-- /field-wrapper -->
            </fieldset>

            <fieldset>
                <legend>{{ lzy-cal-legend-till }}</legend>
                <div class='field-wrapper field-type-date lzy-cal-end-date'>
                    <label for='lzy_cal_end_date' class="lzy-cal-label">{{ lzy-cal-end-date-label }}</label>
                    <input type='date' id='lzy_cal_end_date' name='end-date' placeholder='{{^ lzy-cal-end-date-placeholder }}' value='' />
                    <label for='lzy_cal_end_time' class="lzy-cal-label">{{ lzy-cal-end-time-label }}</label>
                    <input type='time' id='lzy_cal_end_time' name='end-time' placeholder='{{^ lzy-cal-end-time-placeholder }}' value='' />
                </div><!-- /field-wrapper -->
            </fieldset>

 
$customFields
 
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
        return $popupForm;
    } // renderDefaultCalPopUpForm




    private function prepareCategoryCombo()
    {
        $category = $this->category;

        $categoryCombo = '';
        if ($category) {
            $categories = explode(',', $category);
            if (sizeof($categories) > 1) {
                foreach ($categories as $cat) {
                    $cat = trim($cat);
                    $categoryCombo .= "\t<option value='$cat'>$cat</option>\n";
                }
                $categoryCombo = <<<EOT
    <label for="lzy_cal_category" class="lzy_cal_category-label">{{ lzy-cal-category-label }}:</label>
    <select id="lzy_cal_category" class="lzy_cal_category" name="category">
        <option value="">{{ lzy-cal-category-all }}</option>
    $categoryCombo
    </select>

EOT;
            } else {
                $cat = trim($category);
                $categoryCombo = <<<EOT
    <div class="lzy_cal_category-field"><span class="lzy-cal-category-label">{{ lzy-cal-category-label }}: </span><span class="lzy-cal-category lzy-cal-category-value">$cat</span></div>
    <input type="hidden" class="lzy_cal_category" name="category" value="{$categories[0]}" />
EOT;
            }
        }
        return $categoryCombo;
    } // prepareCategoryCombo




    private function prepareCustomFields()
    {
        $stdElems = ['end','source','start','title','category','time'];

        $out = '';
        foreach ($this->fields as $field) {
            if (in_array($field, $stdElems)) {
                continue;
            }
            if ($field === 'comment') {
                $div = <<<EOT

            <div class='field-wrapper field-type-textarea'>
                <label for='lzy_cal_comment' class="lzy-cal-label">{{ comment }}:</label>
                <textarea id='lzy_cal_comment' class="lzy-cal-field" name='comment' placeholder='{{ lzy-cal-comment-placeholder }}'></textarea>
            </div><!-- /field-wrapper -->

EOT;

            } else {
                $div = <<<EOT

            <div class='field-wrapper field-type-text lzy-cal-$field'>
                <label for='lzy_cal_event_$field' class="lzy-cal-label">{{ $field }}:</label>
                <input type='text' id='lzy_cal_event_$field' class="lzy-cal-field" name='$field'  placeholder='{{^ lzy-cal-$field-placeholder }}' />
            </div><!-- /field-wrapper -->

EOT;
            }
            $out .= $div;
        }
        return $out;
    } // prepareCustomFields




    private function loadDefaultPopup($popupForm)
    {
        // render popup related arguments:
        $args = [
            'text' => $popupForm,
            'class' => 'lzy-cal-popup',
            'draggable' => true,
            'triggerSource' => 'none',
            'triggerEvent' => 'none',
            'closeOnBgClick' => true,
            'width' => '20em',
        ];

        $this->lzy->page->addPopup($args);
    } // renderDefaultPopup



    private function publishICal()
    {
        // get destination filename:
        $destFile = $this->publish;
        if (is_bool($destFile)) {
            $destFile = '~/ics/'.base_name($this->source, false).'.ics';

        } elseif ($destFile[0] !== '~') {
            $destFile = "~/ics/$destFile.ics";
        }
        if (!fileExt($destFile)) {
            $destFile .= '.ics';
        }
        $destFile = resolvePath($destFile);

        // get modif time of destination file:
        if (!file_exists($destFile)) {
            preparePath($destFile);
            if (!is_writable(dirname($destFile))) {
                die("Error: file '$destFile' is not writable.");
            }
            touch($destFile);
            $lastExported = 0;
        } else {
            $lastExported = filemtime($destFile);
        }

        // check db:
        $ds = new DataStorage2(['dataFile'=> $this->source]);
        $lastUpdated = intval($ds->lastDbModified());

        // publishCallback:
        if ($this->publishCallback) {
            $publishCallback = base_name($this->publishCallback, false);
            $publishCallback = '-' . ltrim($publishCallback, '-') . '.php';
            $publishCallbackCode = USER_CODE_PATH . $publishCallback;
            if (file_exists($publishCallbackCode)) {
                require_once $publishCallbackCode;  // -> must define function 'iCalDescriptionCallback($rec)'
            } else {
                $this->publishCallback = false;
            }
        }

        // update if outdated:
        if ($lastUpdated > $lastExported) {
            $out = $this->prepareICal( $ds );
            file_put_contents($destFile, $out);
            writeLog("calendar exported to '$destFile'");
        }
    } // publishICal



    private function prepareICal( $ds )
    {
        require_once '_lizzy/extensions/calendar/third-party/fullcalendar/php/utils.php';
        $timezone = isset($_SESSION['lizzy']['systemTimeZone']) ? $_SESSION['lizzy']['systemTimeZone'] : 'UTC';
        date_default_timezone_set($timezone);

        $data = $ds->read();

        $recsToShow = $this->filterCalRecords($data);

        $vCalendar = new \Eluceo\iCal\Component\Calendar($this->domain);
        $name = base_name($this->publish, false);

        foreach ($recsToShow as $key => $rec) {
            if ($this->categoryPrefixes) {
                $category = $rec['category'];
                $prefix = isset($this->categoryPrefixes[$category]) ? $this->categoryPrefixes[$category]: $this->defaultCatPrefix;
            } else {
                $prefix = $this->defaultCatPrefix;
            }
            // determine UID (unique id that is invariable even if calendar changes):
            $uid = (isset($rec['_uid']) && $rec['_uid'])? $rec['_uid']: strtotime($rec['start']);
            $uid = "lzy-cal-$name-{$rec['category']}-$uid";

            // prepare description:
            if ($this->publishCallback && function_exists('iCalDescriptionCallback')) {
                $description = iCalDescriptionCallback($rec);
            } else {
                $description = isset($rec['comment'])? $rec['comment']: '';
                $description = preg_replace('|<a href=["\'](.*?)["\'].*?>.*?</a>|', "$1", $description);
            }


            // assemble iCalendar entry:
            $vEvent = new \Eluceo\iCal\Component\Event();
            $vEvent
                ->setDtStart(new \DateTime($rec['start']))
                ->setDtEnd(new \DateTime($rec['end']))
                ->setSummary($prefix.$rec['title'])
                ->setLocation( isset($rec['location'])? $rec['location']: '' )
                ->setDescription( $description )
                ->setUniqueId($uid)
            ;
            $vCalendar->addComponent($vEvent);
        }
        return $vCalendar->render();
    } // prepareICal




    public function filterCalRecords($data)
    {
        if (!$this->showCategories) {
            return $data;
        }

        $categoriesToShow = ',' . str_replace(' ', '', $this->showCategories) . ',';

        // Accumulate an output array of event data arrays.
        $output_arrays = array();
        if (!$data) {
            return [];
        }

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
                    $output_arrays[$i] = array_merge($event->toArray(), ['i' => $i]);
                }
            }
        }
        return $output_arrays;
    } // filterCalRecords

} // class LzyCalendar


<?php

if (!defined('CALENDAR_BACKEND')) { define('CALENDAR_BACKEND', '~sys/extensions/calendar/backend/_cal-backend.php'); }
define('DEFAULT_EVENT_DURATION', 120); // in minutes

$GLOBALS['lizzy']['calInitialized'] = false;

class LzyCalendar
{
    public function __construct($lzy, $inx, $args)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
        $this->inx = $inx;
        $this->tickHash = '';
        $this->lang = $this->lzy->config->lang;
        $this->source = $args['file'];
        $this->editingPermission = isset($args['editingPermission']) ? $args['editingPermission'] : false;

        $this->fields = isset($args['fields']) ? $args['fields']: false;
        if ($this->fields) {
            $this->fields = explodeTrim(',', $this->fields);
        } else {
            $this->fields = [];
        }

        $this->class = isset($args['class']) ? $args['class']: false;
        $this->options = isset($args['options']) ? $args['options']: false;
        if (strpos($this->options, 'light') !== false) {
            $this->class .= ' lzy-calendar-light';
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

        // Event overlap:
        $this->eventOverlap = isset($args['eventOverlap']) ? $args['eventOverlap']: true;
        $this->eventOverlap = $this->eventOverlap? 'true': 'false';

        // Categories:
        $this->category = isset($args['categories']) ? $args['categories']: '';
        $this->categories = $categories = explodeTrim(',', $this->category);
        $this->showCategories = isset($args['showCategories']) ? $args['showCategories']: '';

        // special case '_users_' for categories:
        $cats = $this->category;
        if (preg_match_all('|<em>(.*?)</em>|', $cats, $m)) { // '_' translated to '<em>' by MD compiler
            foreach ($m[1] as $i => $group) {
                if (($group === 'all') || ($group === 'users')) {
                    $group = '';
                    $args['sort'] = 'a';
                }
                $users = $this->lzy->auth->getListOfUsers( $group );
                $cats = str_replace($m[0][$i], $users, $cats);
            }

            $cats = rtrim(str_replace(',,', ',', $cats), ',');
            $categories = explodeTrim(',', $cats);

            if ($args['sort']) {
                if (($args['sort'] === true) || ($args['sort'] && ($args['sort'][0] !== 'd'))) {
                    sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
                } else {
                    rsort($categories, SORT_NATURAL | SORT_FLAG_CASE);
                }
            }

            $this->categories = $categories;
            $args['categoryPrefixes'] = $this->category;
            $js = <<<'EOT'

const lzyLoggedinUser = '{{ user }}';
function openPostCalPopupHandler() {
    $('#lzy-calendar-default-form [value="' + lzyLoggedinUser + '"]').prop('selected', true);
}

EOT;
            $this->page->addJs( $js );
        }

        $this->domain = isset($args['domain']) ? $args['domain']: $_SERVER["HTTP_HOST"];
        $this->eventTitleRequired = isset($args['eventTitleRequired']) ? $args['eventTitleRequired']: true;

        // Prefixes:
        $n = sizeof($categories);
        $defaultCatPrefix = isset($args['prefix']) ? $args['prefix']: '';
        $defaultCatPrefix = isset($args['defaultPrefix']) && $args['defaultPrefix'] ? $args['defaultPrefix']: $defaultCatPrefix;
        $catPrefixes = isset($args['categoryPrefixes']) ? $args['categoryPrefixes']: '';
        if (strpos($catPrefixes, ',') !== false) {
            $categoryKeys = array_map(function($e) {
                $e = preg_replace('/\s+/', '-', $e);
                $e = preg_replace('/[^\w-]/', '', $e);
                return $e;
            }, $categories);
            $catPrefixes = explode(',', "$catPrefixes,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,");
            $catPrefixes = array_slice($catPrefixes, 0, $n);
            $catPrefixAssoc = array_combine($categoryKeys, $catPrefixes);
            $catPrefixAssoc[''] = '';
            $this->categoryPrefixes = $catPrefixAssoc;
            $this->prefixJson = json_encode($catPrefixAssoc);
            $this->defaultCatPrefix = $defaultCatPrefix? $defaultCatPrefix: $catPrefixes[0];
        } else {
            $this->categoryPrefixes = [];
            $this->prefixJson = '{}';
            $this->defaultCatPrefix = $defaultCatPrefix? $defaultCatPrefix: '';
        }


        if (!isset($_SESSION['lizzy']['cal'][$inx]) || !is_array($_SESSION['lizzy']['cal'][$inx])) {
            $_SESSION['lizzy']['cal'][$inx] = [];
        }
        $this->calSession = &$_SESSION['lizzy']['cal'][$inx];

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

        // Recycle bin
        $this->useRecycleBin = isset($args['useRecycleBin']) ? $args['useRecycleBin']: false;

        // UI:
        $this->headerLeftButtons = isset($args['headerLeftButtons']) ? $args['headerLeftButtons']: false;
        $this->headerRightButtons = isset($args['headerRightButtons']) ? $args['headerRightButtons']: false;
        $this->buttonLabels = isset($args['buttonLabels']) ? $args['buttonLabels']: false;
        $this->dayStart = @$args['dayStart'] ? $args['dayStart']: '08:00';
        $this->dayEnd = @$args['dayEnd'] ? $args['dayEnd']: '20:00';
        $this->freezePast = @$args['freezePast'] ? $args['freezePast']: false;

        $this->checkAndFixData();
    } // __construct




    public function render()
    {
        $inx = $this->inx;
        $this->source = resolvePath($this->source, true);

        $calRec = [];
        $calRec['inx'] = $inx;
        $calRec['dataSource'] = $this->source;
        $calRec['calShowCategories'] = $this->showCategories;
        $calRec['useRecycleBin'] = $this->useRecycleBin;
        $calRec['freezePast'] = $this->freezePast;

        // export ics file if requested:
        if ($this->publish) {
            $this->publishICal();
        }

        // suppress output if requested:
        if ($this->output === false) {
            return '';
        }

        $backend = CALENDAR_BACKEND;
        if (isset($this->calSession['initialDate'])) {
            $this->initialDate = $this->calSession['initialDate'];
        } else {
            $this->initialDate = date('Y-m-d');
            $this->calSession['initialDate'] = $this->initialDate;
        }
        $viewMode = (isset($this->calSession['calMode'])) ? $this->calSession['calMode'] : $this->defaultView;


        // handle editing permissions:
        $edPermitted0 = $this->editingPermission;
        $edPermitted = false;
        $creatorOnlyPermission = 'false';
        $calRec['creatorOnlyPermission'] = false;

        if (($edPermitted0 === 'all') || ($edPermitted0 === '*') || ($edPermitted0 === true)) {
            $edPermitted = true;
            $this->createPermission = true;

        } elseif (is_string($edPermitted0)) {
            if (strpos($edPermitted0, 'user:') !== false) {
                $groups = explodeTrim(',', $edPermitted0);
                foreach ($groups as $group) {
                    if (strpos($group, 'user:') !== false) {
                        $group = str_replace('user:', '', $group);
                        if ($this->lzy->auth->checkAdmission($group)) {
                            $creatorOnlyPermission = "'{$_SESSION["lizzy"]["user"]}'";
                            $edPermitted = true;
                            $calRec['creatorOnlyPermission'] = $_SESSION["lizzy"]["user"];
                        }
                    } else {
                        if ($this->lzy->auth->checkAdmission($group)) {
                            $creatorOnlyPermission = 'false';
                            $edPermitted = true;
                            $calRec['creatorOnlyPermission'] = false;
                            break;
                        }
                    }
                }

            } else {
                $edPermitted = $this->lzy->auth->checkAdmission($edPermitted0);
            }
        }
        $edPermStr = $edPermitted? 'true': 'false';
        $this->edPermitted = $edPermitted;

        // Get Default Event Duration
        $defaultEventDuration = DEFAULT_EVENT_DURATION;
        if ($this->defaultEventDuration) {
            if (stripos($this->defaultEventDuration, 'allday') !== false) {
                $defaultEventDuration = "'allday'";
            } else {
                $defaultEventDuration = intval($this->defaultEventDuration);
            }
        }


        // header buttons:
        $headerLeftButtons = $this->headerLeftButtons;
        if ($this->edPermitted && strpos($this->headerLeftButtons, 'add') !== false) {
            $headerLeftButtons = preg_replace('/\w*add\w*/', '', $headerLeftButtons);
            $headerLeftButtons .= 'addEventButton';
        }
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
        if (!$GLOBALS['lizzy']['calInitialized']) {
            $GLOBALS['lizzy']['calInitialized'] = true;
            $userRec = $this->lzy->auth->getUserRec();
            if (isset($userRec['calCatetoryPermission'])) {
                if (!$userRec['calCatetoryPermission'] || ($userRec['calCatetoryPermission'] === 'self')) {
                    $calCatPermission = $userRec['username'];
                } else {
                    $calCatPermission = $userRec['calCatetoryPermission'];
                }
            } else {
                $calCatPermission = '';
            }

            $calRec['calCatPermission'] = $calCatPermission;

            $tck = new Ticketing(['defaultType' => 'cal', 'defaultMaxConsumptionCount' => 99]);
            $hash = $tck->createTicket($calRec);
            $this->tickHash = $hash;

            $js = <<<EOT

var calBackend = '$backend';
var calLang = '{$this->lang}';
var calSubmitBtnLabel = ['{{ Save }}', '{{ lzy-cal-delete-entry-now }}'];
var lzyCal = [];
EOT;
        }
        $categories = "['" . implode("','", $this->categories) . "']";
        $js .= <<<EOT

lzyCal[$inx] = {
    ref: '$hash',
    initialView: '$viewMode',
    initialDate: '{$this->initialDate}',
    editingPermission: $edPermStr,
    creatorOnlyPermission: $creatorOnlyPermission,
    categories: $categories,
    calCatPermission: '$calCatPermission',
    catPrefixes: {$this->prefixJson},
    catDefaultPrefix: '{$this->defaultCatPrefix}',
    calDayStart: '{$this->dayStart}',
    calDayEnd: '{$this->dayEnd}',
    freezePast: '{$this->freezePast}',
    tooltips: {$this->tooltips},
    eventOverlap: {$this->eventOverlap},
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

        $str = '';

        $class = $this->class? " {$this->class}": '';
        $edClass = $this->edPermitted ? ' lzy-cal-editing' : '';

        $str .= "\t<div id='lzy-calendar$inx' class='lzy-calendar$class$edClass' data-lzy-cal-inx='$inx'></div>\n";
        $this->renderFieldNames();

        // render edit form:
        if ($this->edPermitted) {
            $popupForm = $this->renderDefaultCalPopUpForm();
            if (function_exists('loadCustomCalPopup')) {
                loadCustomCalPopup($inx, $this->lzy, $popupForm, $this->edPermitted);

            } else {
                $this->lzy->page->addModules( 'POPUPS' );
                $popup = <<<EOT

    <div id='lzy-cal-popup-template-$inx' class='lzy-cal-popup-template' style="display: none">
        <div>
$popupForm
        </div>
    </div><!-- /lzy-cal-popup-template -->

EOT;
                $this->page->addBodyEndInjections( $popup );
            }
        }

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
    
        <form id='lzy-calendar-default-form' method='post' action="$backend">
            <input type='hidden' id='lzy-inx' name='inx' value='$inx' />
            <input type='hidden' id='lzy-cal-ref' name='lzy-cal-ref' value='$this->tickHash' />
            <input type='hidden' id='lzy-rec-id' name='rec-id' value='' />
            <input type='hidden' id='lzy-allday' name='allday' value='' />

$categoryCombo

            <div class='lzy-field-wrapper lzy-field-type-text lzy-cal-event'>
                <label for='lzy_cal_event_name' class="lzy-cal-label">{{ lzy-cal-event-title }}:</label>
                <input type='text' id='lzy_cal_event_name' class="lzy-cal-field" name='title'$required placeholder='{{^ lzy-cal-event-placeholder }}' />
            </div><!-- /lzy-field-wrapper -->
           
           
            <div id="lzy_cal_allday_event" class='lzy-field-wrapper lzy_cal_allday_event'>
                <label for="lzy-cal-allday-event-checkbox">{{ lzy-cal-allday-event }}:</label>
                <input type='checkbox' id="lzy-cal-allday-event-checkbox" />
            </div><!-- /lzy-field-wrapper -->
            

            <fieldset class="lzy-cal-fieldset">
                <legend>{{ lzy-cal-legend-from }}</legend>
                <div class='lzy-field-type-date lzy-cal-start-date'>
                    <label for='lzy_cal_start_date' class="lzy-cal-label">{{ lzy-cal-start-date-label }}</label>
                    <input type='date' id='lzy_cal_start_date' name='start-date' placeholder='{{^ lzy-cal-start-date-placeholder }}' value='' />
                    <label for='lzy_cal_start_time' class="lzy-cal-label">{{ lzy-cal-start-time-label }}</label>
                    <input type='time' id='lzy_cal_start_time' name='start-time' placeholder='{{^ lzy-cal-start-time-placeholder }}' value='' />
                </div><!-- /lzy-field-wrapper -->
            </fieldset>

            <fieldset class="lzy-cal-fieldset">
                <legend>{{ lzy-cal-legend-till }}</legend>
                <div class='lzy-field-type-date lzy-cal-end-date'>
                    <label for='lzy_cal_end_date' class="lzy-cal-label">{{ lzy-cal-end-date-label }}</label>
                    <input type='date' id='lzy_cal_end_date' name='end-date' placeholder='{{^ lzy-cal-end-date-placeholder }}' value='' />
                    <label for='lzy_cal_end_time' class="lzy-cal-label">{{ lzy-cal-end-time-label }}</label>
                    <input type='time' id='lzy_cal_end_time' name='end-time' placeholder='{{^ lzy-cal-end-time-placeholder }}' value='' />
                </div><!-- /lzy-field-wrapper -->
            </fieldset>

 
$customFields
 
            <div id="lzy_cal_delete_entry" class='lzy-field-wrapper lzy_cal_delete_entry' style="display:none">
                <label for="lzy-cal-delete-entry-checkbox">
                    <input type='checkbox' id="lzy-cal-delete-entry-checkbox" />
                {{ lzy-cal-delete-entry }}</label>
            </div>
            
            <div class="lzy-cal-form-buttons">
                <input type='submit' id='lzy-calendar-default-submit' value='{{ Save }}' class='lzy-button form-button' />
                <input type='reset' id='lzy-calendar-default-cancel' value='{{ Cancel }}' class='lzy-button form-button' />
            </div>
        
        </form>
        <div style="display: none;">
            <span id="lzy-cal-new-event-header">{{ lzy-cal-new-entry }}</span>
            <span id="lzy-cal-modify-event-header">{{ lzy-cal-modif-entry }}</span>
        </div>

EOT;
        return $popupForm;
    } // renderDefaultCalPopUpForm




    private function prepareCategoryCombo()
    {
        $category = $this->category;

        $categoryCombo = '';
        if ($category) {
            $categories = $this->categories;
            if (sizeof($categories) > 1) {
                foreach ($categories as $cat) {
                    $catVal = $cat = trim($cat);
                    $catVal = preg_replace('/\s+/', '-', $catVal);
                    $catVal = preg_replace('/[^\w-]/', '', $catVal);
                    $categoryCombo .= "\t\t<option value='$catVal'>$cat</option>\n";
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
        $categoryCombo = <<<EOT
    <div class='lzy-field-wrapper'>
$categoryCombo
    </div><!-- /lzy-field-wrapper -->
EOT;

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

            <div class='lzy-field-wrapper lzy-field-type-textarea'>
                <label for='lzy_cal_comment' class="lzy-cal-label">{{ comment }}:</label>
                <textarea id='lzy_cal_comment' class="lzy-cal-field" name='comment' placeholder='{{ lzy-cal-comment-placeholder }}'></textarea>
            </div><!-- /lzy-field-wrapper -->

EOT;

            } else {
                $div = <<<EOT

            <div class='lzy-field-wrapper lzy-field-type-text lzy-cal-$field'>
                <label for='lzy_cal_event_$field' class="lzy-cal-label">{{ $field }}:</label>
                <input type='text' id='lzy_cal_event_$field' class="lzy-cal-field" name='$field'  placeholder='{{^ lzy-cal-$field-placeholder }}' />
            </div><!-- /lzy-field-wrapper -->

EOT;
            }
            $out .= $div;
        }
        if ($out) {
            $out = <<<EOT
    <div class='lzy-cal-custom-fields'>
$out
    </div><!-- /lzy-cal-custom-fields -->
EOT;

        }
        return $out;
    } // prepareCustomFields




    private function loadDefaultPopup()
    {
        // render popup related arguments:
        $jq = <<<EOT
lzyPopup({
    contentRef: '#lzy-cal-popup-template',
    class: 'lzy-cal-popup',
    draggable: true,
    header: true,
    closeOnBgClick: true,
});
EOT;
        $this->lzy->page->addModules( 'POPUPS' );
        $this->lzy->page->addJq( $jq );
    } // renderDefaultPopup



    public function publishICal()
    {
        // get destination filename:
        $destFile = $this->publish;
        if ($destFile === true) {
            $destFile = "~/ics/".basename($this->source);

        } elseif ($destFile[0] !== '~') {
            $destFile = "~/ics/$destFile";
        }
        $destFile = fileExt($destFile, true).'.ics';
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




    private function checkAndFixData()
    {
        $modify = false;
        $db = new DataStorage2([
            'dataFile' => $this->source,
            'useRecycleBin' => $this->useRecycleBin,
            'exportInternalFields' => true,
        ]);
        $data = $db->read();

        // check whether _uid is defined:
        foreach ($data as $key => $rec) {
            if (!@$rec['_uid'] || !@$rec['_user'] || !@$rec['_creator']) {
                $modify = true;
            }

            // drop badly formed records:
            if (!@$rec['start'] || !@$rec['end']) {
                unset($data[$key]);
                $modify = true;
                continue;
            }
        }
        if (!$modify) {
            return;
        }
        foreach ($data as $key => $rec) {
            if (!isset($rec['_uid']) || !$rec['_uid']) {
                $rec['_uid'] = createHash(12);
            }
            if (!isset($rec['_user']) || !$rec['_user']) {
                $rec['_user'] = 'anon';
            }
            if (!isset($rec['_creator']) || !$rec['_creator']) {
                $rec['_creator'] = 'anon';
            }
            $data[$key] = $rec;
        }
        $db->write( $data );
    } // checkAndFixData
} // class LzyCalendar

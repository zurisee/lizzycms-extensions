<?php

if (!defined('CALENDAR_BACKEND')) { define('CALENDAR_BACKEND', '~sys/extensions/calendar/backend/_cal-backend.php'); }
define('DEFAULT_EVENT_DURATION', 120); // in minutes

$GLOBALS['lizzy']['calInitialized'] = false;

if (isset($page)) {
    $page->addModules('POPUPS,TOOLTIPSTER');
}

class LzyCalendar
{
    private $calSession = null;

    public function __construct($lzy, $inx, $args)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
        $this->inx = $inx;
        $this->tickHash = '';
        $this->lang = $this->lzy->config->lang;
        $this->source = $args['file'];
        $this->calOptions = '';
        $this->fullCalendarOptions = '';
        if (isset($args['fullCalendarOptions'])) {
            $this->fullCalendarOptions = "\t\t{$args['fullCalendarOptions']}\n";
        }
        $this->editingPermission = isset($args['editingPermission']) ? $args['editingPermission'] : false;
        $this->editPermissionWarning = isset($args['editPermissionWarning']) ? $args['editPermissionWarning'] : false;

        $this->fields = isset($args['fields']) ? $args['fields'] : false;
        if ($this->fields) {
            $this->fields = explodeTrim(',', $this->fields);
        } else {
            $this->fields = [];
        }

        $this->id = isset($args['id']) ? $args['id'] : "cal-$inx";
        $this->class = isset($args['class']) ? $args['class'] : false;

        $css = '';
        $this->colorMode = isset($args['colorMode']) ? $args['colorMode'] : false;
        if (strpos($this->colorMode, 'light') !== false) {
            $this->class .= ' lzy-cal-light-mode';
            $css = ".lzy-section { --lzy-cal-color-base: 202, 30%; --lzy-cal-event-bg: hsl( 202, 30%, 80%); }";
        } elseif (strpos($this->colorMode, 'dark') !== false) {
            $this->class .= ' lzy-cal-dark-mode';
            $css = ".lzy-section { --lzy-cal-color-base: 202, 30%; --lzy-cal-event-bg: hsl( 202, 30%, 20%); }";
        }
        if (@$args['baseColor']) {
            $this->page->addCss(".lzy-section .lzy-calendar { --lzy-cal-color-base: {$args['baseColor']}; }");
            if (!$this->colorMode) {
                $this->class .= ' lzy-cal-light-mode';
            }

        } elseif ($this->colorMode && $css) {
            $this->page->addCss( $css );

        } elseif (strpos($this->colorMode, 'no') === false) {
            $this->page->addCss(".lzy-section { --lzy-cal-color-base: 60, 50%; --lzy-cal-event-bg: hsl( 199, 30%, 30%); }");
            $this->class .= ' lzy-cal-light-mode';
        }

        // Whether to publish the calendar (i.e. save in an .ics file):
        $this->publish = isset($args['publish']) ? $args['publish'] : false;
        $this->publishCallback = isset($args['publishCallback']) ? $args['publishCallback'] : false;

        // Whether to suppress out generation (e.g. if only used to publish .ics)
        $this->output = isset($args['output']) ? $args['output'] : true;

        // Tooltips:
        $tooltips = isset($args['tooltips']) ? $args['tooltips'] : false;
        $this->tooltips = $tooltips ? 'true' : 'false';
        if ($tooltips) {
            $lzy->page->addModules('QTIP');
        }

        // Event overlap:
        if (isset($args['eventOverlap'])) {
            $this->fullCalendarOptions .= "\t\teventOverlap: " . ($args['eventOverlap'] ? 'true' : 'false') . ",\n";
        }

        // visible hours:
        if (isset($args['visibleHours']) && $args['visibleHours']) {
            list($bStart, $bEnd) = explodeTrim('-', $args['visibleHours']);
            if ($bStart && $bEnd) {
                $this->fullCalendarOptions .= "\t\tslotMinTime: '$bStart',\n";
                $this->fullCalendarOptions .= "\t\tslotMaxTime: '$bEnd',\n";
            } else {
                die("Calendar: error in option 'visibleHours' ({$args['visibleHours']})");
            }
        }

        // business hours:
        if (isset($args['businessHours']) && $args['businessHours']) {
            list($bStart, $bEnd) = explodeTrim('-', $args['businessHours']);
            if ($bStart && $bEnd) {
                $this->fullCalendarOptions .= "\t\tbusinessHours: { daysOfWeek: [ 1, 2, 3, 4, 5 ], startTime: '$bStart', endTime: '$bEnd'},\n";
            } else {
                die("Calendar: error in option 'businessHours' ({$args['businessHours']})");
            }
        }

        // Categories:
        $this->category = isset($args['categories']) ? $args['categories'] : '';
        $this->categories = $categories = explodeTrim(',', $this->category);
        $this->showCategories = isset($args['showCategories']) ? $args['showCategories'] : '';

        // special case '_users_' for categories:
        $cats = $this->category;
        $cats = preg_replace('|</?em>|', '_', $cats); // '_' may have been translated to '<em>' by MD compiler
        if (preg_match_all('|_(.*?)_|', $cats, $m)) {
            foreach ($m[1] as $i => $group) {
                if (($group === 'all') || ($group === 'users')) {
                    $group = '';
                    $args['sort'] = 'a';
                }
                $users = $this->lzy->auth->getListOfUsers($group);
                $cats = str_replace($m[0][$i], $users, $cats);
            }

            $cats = rtrim(str_replace(',,', ',', $cats), ',');
            $categories = explodeTrim(',', $cats);

            if (@$args['sort']) {
                if (($args['sort'] === true) || ($args['sort'] && ($args['sort'][0] !== 'd'))) {
                    sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
                } else {
                    rsort($categories, SORT_NATURAL | SORT_FLAG_CASE);
                }
            }

            $this->categories = $categories;
        }

        $this->domain = isset($args['domain']) ? $args['domain'] : $_SERVER["HTTP_HOST"];
        $this->eventTitleRequired = isset($args['eventTitleRequired']) ? $args['eventTitleRequired'] : true;

        // Prefixes:
        $this->categoryPrefixes = $categoryPrefixes = [];
        $n = sizeof($categories);
        $defaultCatPrefix = isset($args['prefix']) ? $args['prefix'] : '';
        $defaultCatPrefix = isset($args['defaultPrefix']) && $args['defaultPrefix'] ? $args['defaultPrefix'] : $defaultCatPrefix;
        $catPrefixes = isset($args['categoryPrefixes']) ? $args['categoryPrefixes'] : '';
        $this->catPrefixesStr = '';
        $lPrfx = $lzy->trans->getVariable('lzy-cal-l-prefix');
        $rPrfx = $lzy->trans->getVariable('lzy-cal-r-prefix');

        // handle special case '_group_':
        $catPrefixes = preg_replace('|</?em>|', '_', $catPrefixes);
        if (preg_match_all('|_(.*?)_|', $catPrefixes, $m)) { // '_' translated to '<em>' by MD compiler
            foreach ($m[1] as $i => $group) {
                if (($group === 'all') || ($group === 'users')) {
                    $group = '';
                    $args['sort'] = 'a';
                }
                $users = $this->lzy->auth->getListOfUsers( $group );
                $catPrefixes = str_replace($m[0][$i], $users, $catPrefixes);
            }

            $catPrefixes = rtrim(str_replace(',,', ',', $catPrefixes), ',');
        }

        if (strpos($catPrefixes, ',') !== false) {
            $categoryKeys = array_map(function($e) {
                $e = preg_replace('/\s+/', '-', $e);
                $e = preg_replace('/[^\w-]/', '', $e);
                return $e;
            }, $categories);
            $catPrefixes = explodeTrim(',', $catPrefixes);

            if (@$args['sort']) {
                if (($args['sort'] === true) || ($args['sort'] && ($args['sort'][0] !== 'd'))) {
                    sort($catPrefixes, SORT_NATURAL | SORT_FLAG_CASE);
                } else {
                    rsort($catPrefixes, SORT_NATURAL | SORT_FLAG_CASE);
                }
            }
            $catPrefixes = array_merge($catPrefixes, ['','','','','','','','','','','','','','','','','','','','','','','','','','']);

            $catPrefixes = array_slice($catPrefixes, 0, $n);
            foreach ($catPrefixes as $i => $s) {
                if (!$s) { continue; }
                $catPrefixesStr = "$lPrfx$s$rPrfx";
                $this->catPrefixesStr .= "{$categoryKeys[$i]}: '$catPrefixesStr', ";
                $cat = $categories[ $i ];
                $categoryPrefixes[ $cat ] = $catPrefixesStr;
            }
            $this->catPrefixesStr = '{ '.rtrim($this->catPrefixesStr, ', ').'}';
            $this->defaultCatPrefix = $defaultCatPrefix? $defaultCatPrefix: '';
        } else {
            $this->defaultCatPrefix = $defaultCatPrefix? $defaultCatPrefix: '';
        }
        if (!$this->catPrefixesStr) {
            $this->catPrefixesStr = "''";
        }
        $this->categoryPrefixes = $categoryPrefixes;

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

        // freezePast:
        $this->freezePast = @$args['freezePast'] ? $args['freezePast']: false;
        if (is_string($this->freezePast)) {
            $this->freezePast = checkPermission( $this->freezePast, $this->lzy, true );
        }

        $obj = [];
        foreach ($this as $key => $elem) {
            if (strpos('lzy,page', $key) !== false) {
                continue;
            }
            $obj[$key] = $elem;
        }
        $calObj = serialize($obj);
        $cacheFile = CACHE_PATH."$this->id.dat";
        preparePath(CACHE_PATH);
        file_put_contents($cacheFile, $calObj);

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
            publishICal( $this->id );
        }

        // suppress output if requested:
        if ($this->output === false) {
            return '';
        }

        $backend = CALENDAR_BACKEND;
        $viewMode = (isset($this->calSession['calMode'])) ? $this->calSession['calMode'] : $this->defaultView;
        if (isset($this->calSession['initialDate'])) {
            // try to fix picking wrong week/month after reload:
            $t = strtotime($this->calSession['initialDate']);
            if ($viewMode === 'dayGridMonth') {
                $initialDate = date('Y-m-d', strtotime('+1 week', $t));
            } else {
                $initialDate = date('Y-m-d', strtotime('+1 day', $t));
            }
            $this->initialDate = $initialDate;
        } else {
            $this->initialDate = date('Y-m-d');
            $this->calSession['initialDate'] = $this->initialDate;
        }


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
                $edPermitted = checkPermission( $edPermitted0, $this->lzy);
            }
        }
        $edPermStr2 = $edPermStr = $edPermitted? 'true': 'false';
        $this->edPermitted = $edPermitted;
        if (!$edPermitted && $this->editPermissionWarning) {
            $edPermStr = 'null';
        }

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
        $calCatPermission = 'false';
        if (!$GLOBALS['lizzy']['calInitialized']) {
            $userRec = $this->lzy->auth->getUserRec();
            if (isset($userRec['calCatetoryPermission'])) {
                if (!$userRec['calCatetoryPermission'] || ($userRec['calCatetoryPermission'] === 'self')) {
                    $calCatPermission = $userRec['username'];
                } else {
                    $calCatPermission = str_replace(' ', '', $userRec['calCatetoryPermission']);
                }
                $calCatPermission = "'$calCatPermission'";
            }

            $calRec['calCatPermission'] = $calCatPermission;

            $tck = new Ticketing(['defaultType' => 'cal', 'defaultMaxConsumptionCount' => 99]);
            $hash = $tck->createTicket($calRec);
            $this->tickHash = $hash;
            $GLOBALS['lizzy']['calInitialized'] = $hash;

            $js = <<<EOT

const calBackend = '$backend';
const calLang = '{$this->lang}';
const calUser = '{$_SESSION['lizzy']['user']}';
const calSubmitBtnLabel = ['{{ Save }}', '{{ lzy-cal-delete-entry-now }}'];
EOT;
        } else {
            $this->tickHash = $GLOBALS['lizzy']['calInitialized'];
        }
        $this->page->addJs($js);

        $categories = "['" . implode("','", $this->categories) . "']";

        $str = '';

        $class = $this->class? " {$this->class}": '';
        $edClass = $this->edPermitted ? ' lzy-cal-editing' : '';
        $freezePast = $this->freezePast? 'true':'false';

        $str .= "\t<div id='lzy-calendar-$inx' class='lzy-calendar$class$edClass' data-lzy-cal-inx='$inx' data-datasrc-ref='$this->tickHash'></div>\n";
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
        $calOptions = <<<EOT
    inx: $this->inx,
    initialView: '$viewMode',
    editingPermission: $edPermStr,
    freezePast: $freezePast,
    creatorOnlyPermission: $creatorOnlyPermission,
    categories: $categories,
    catPrefixes: $this->catPrefixesStr,
    catDefaultPrefix: '{$this->defaultCatPrefix}',
    calCatPermission: $calCatPermission,
    tooltips: $this->tooltips,
    defaultEventDuration: $defaultEventDuration,
    headerLeftButtons: '$headerLeftButtons',
    headerRightButtons: '$headerRightButtons',
    fullCalendarOptions: {
        initialView: '$viewMode',
        initialDate: '{$this->initialDate}',
        editable: $edPermStr2,
        buttonText: {
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
        weekText: '{{ lzy-cal-label-week-nr }}', // e.g. "KW"
        allDayText: '{{ lzy-cal-label-allday }}',
        moreLinkText: '{{ lzy-cal-label-more-link }}',
        noEventsText: '{{ lzy-cal-label-empty-list }}',
$this->fullCalendarOptions
    },
$this->calOptions
EOT;
        // append the call to invole lzyCalendar:
        $jq = <<<EOT
$('#lzy-calendar-$this->inx').lzyCalendar({
$calOptions
});
EOT;
        $this->lzy->page->addJq( $jq );
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
        $this->calOptions .= "\tfieldLabels: { $fieldNames },\n";
    } // renderFieldNames




    private function renderDefaultCalPopUpForm()
    {
        $inx = $this->inx;
        $categoryCombo = $this->prepareCategoryCombo();
        $customFields = $this->prepareCustomFields();
        $required = $this->eventTitleRequired? ' required': '';

        // render default popup-form:
        $popupForm = <<<EOT
    
        <form id='lzy-calendar-default-form-$inx' class="lzy-calendar-default-form">
            <input type='hidden' class='lzy-inx' name='inx' value='$inx' />
            <input type='hidden' class='lzy-cal-ref' name='lzy-cal-ref' value='$this->tickHash' />
            <input type='hidden' class='lzy-rec-id' name='rec-id' value='' />
            <input type='hidden' class='lzy-allday' name='allday' value='' />

$categoryCombo

            <div class='lzy-field-wrapper lzy-field-type-text lzy-cal-event'>
                <label for='lzy-cal-event-name-$inx' class="lzy-cal-label">{{ lzy-cal-event-title }}:</label>
                <input type='text' id='lzy-cal-event-name-$inx' class="lzy-cal-field lzy-cal-event-name" name='title'$required placeholder='{{^ lzy-cal-event-placeholder }}' />
            </div><!-- /lzy-field-wrapper -->
           
           
            <div id="lzy-cal-allday-event" class='lzy-field-wrapper lzy-cal-allday-event'>
                <label for="lzy-cal-allday-event-checkbox-$inx">{{ lzy-cal-allday-event }}:</label>
                <input type='checkbox' id="lzy-cal-allday-event-checkbox-$inx" class="lzy-cal-allday-event-checkbox" />
            </div><!-- /lzy-field-wrapper -->
            

            <fieldset class="lzy-cal-fieldset">
                <legend>{{ lzy-cal-legend-from }}</legend>
                <div class='lzy-field-type-date lzy-cal-start-date'>
                    <label for='lzy-cal-start-date-$inx' class="lzy-cal-label">{{ lzy-cal-start-date-label }}</label>
                    <input type='date' id='lzy-cal-start-date-$inx' name='start-date' placeholder='{{^ lzy-cal-start-date-placeholder }}' value='' />
                    <label for='lzy-cal-start-time-$inx' class="lzy-cal-label">{{ lzy-cal-start-time-label }}</label>
                    <input type='time' id='lzy-cal-start-time-$inx' name='start-time' placeholder='{{^ lzy-cal-start-time-placeholder }}' value='' />
                </div><!-- /lzy-field-wrapper -->
            </fieldset>

            <fieldset class="lzy-cal-fieldset">
                <legend>{{ lzy-cal-legend-till }}</legend>
                <div class='lzy-field-type-date lzy-cal-end-date'>
                    <label for='lzy-cal-end-date-$inx' class="lzy-cal-label">{{ lzy-cal-end-date-label }}</label>
                    <input type='date' id='lzy-cal-end-date-$inx' name='end-date' placeholder='{{^ lzy-cal-end-date-placeholder }}' value='' />
                    <label for='lzy-cal-end-time-$inx' class="lzy-cal-label">{{ lzy-cal-end-time-label }}</label>
                    <input type='time' id='lzy-cal-end-time-$inx' name='end-time' placeholder='{{^ lzy-cal-end-time-placeholder }}' value='' />
                </div><!-- /lzy-field-wrapper -->
            </fieldset>

 
$customFields
 
            <div id="lzy-cal-delete-entry-$inx" class='lzy-field-wrapper lzy-cal-delete-entry' style="display:none">
                <label for="lzy-cal-delete-entry-checkbox-$inx">{{ lzy-cal-delete-entry }}</label>
                <input type='checkbox' class="lzy-cal-delete-entry-checkbox" id="lzy-cal-delete-entry-checkbox-$inx" name="del"/>
            </div>
            
            <div class="lzy-cal-form-buttons">
                <input type='reset' id='lzy-calendar-default-cancel-$inx' value='{{ Cancel }}' class='lzy-button form-button lzy-calendar-default-cancel' />
                <input type='submit' id='lzy-calendar-default-submit-$inx' value='{{ Save }}' class='lzy-button form-button lzy-calendar-default-submit lzy-button-submit' />
            </div>
        
             <div style="display: none;">
                <span class="lzy-cal-new-event-header">{{ lzy-cal-new-entry }}</span>
                <span class="lzy-cal-modify-event-header">{{ lzy-cal-modif-entry }}</span>
             </div>
        </form>

EOT;
        return $popupForm;
    } // renderDefaultCalPopUpForm




    private function prepareCategoryCombo()
    {
        $inx = $this->inx;

        $categoryCombo = '';
        if ($this->categories) {
            $categories = $this->categories;
            if (sizeof($categories) > 1) {
                foreach ($categories as $cat) {
                    $catVal = $cat = trim($cat);
                    $catVal = preg_replace('/\s+/', '-', $catVal);
                    $catVal = preg_replace('/[^\w-]/', '', $catVal);
                    $categoryCombo .= "\t\t\t\t\t<option value='$catVal'>$cat</option>\n";
                }
                $categoryCombo = <<<EOT
                <label for="lzy-cal-category-$inx" class="lzy-cal-category-label">{{ lzy-cal-category-label }}:</label>
                <select id="lzy-cal-category-$inx" class="lzy-cal-category" name="category">
                    <option value="">{{ lzy-cal-category-all }}</option>
$categoryCombo                </select>
EOT;
            } else {
                $cat = trim($this->category);
                $categoryCombo = <<<EOT
      <div class="lzy-cal-category-field"><span class="lzy-cal-category-label">{{ lzy-cal-category-label }}: </span><span class="lzy-cal-category lzy-cal-category-value">$cat</span></div>
        <input type="hidden" class="lzy-cal-category" name="category" value="{$categories[0]}" />
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
                <label for='lzy-cal-comment' class="lzy-cal-label">{{ comment }}:</label>
                <textarea id='lzy-cal-comment' class="lzy-cal-field" name='comment' placeholder='{{ lzy-cal-comment-placeholder }}'></textarea>
            </div><!-- /lzy-field-wrapper -->

EOT;

            } else {
                $div = <<<EOT

            <div class='lzy-field-wrapper lzy-field-type-text lzy-cal-$field'>
                <label for='lzy-cal-event-$field' class="lzy-cal-label">{{ $field }}:</label>
                <input type='text' id='lzy-cal-event-$field' class="lzy-cal-field" name='$field'  placeholder='{{^ lzy-cal-$field-placeholder }}' />
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







    private function checkAndFixData()
    {
        $modify = false;
        $db = new DataStorage2([
            'dataFile' => $this->source,
            'useRecycleBin' => $this->useRecycleBin,
            'exportInternalFields' => true,
            'includeKeys' => true,
            'useNormalizedDb' => true,
        ]);
        $data = $db->read();

        // check whether .user/.creator is defined:
        foreach ($data as $key => $rec) {
            if (!@$rec['.user'] || !@$rec['.creator']) {
                $modify = true;
            }

            // drop badly formed records:
            if (!@$rec['start'] || !@$rec['end']) {
                unset($data[$key]);
                $modify = true;
                continue;
            }
            if ($rec['start'] > $rec['end']) {
                die("Error in Calendar Data (rec $key):<br>Start time ({$rec['start']}) after end time ({$rec['end']})");
            }
        }
        if (!$modify) {
            return;
        }
        foreach ($data as $key => $rec) {
            if (!isset($rec['.user']) || !$rec['.user']) {
                $rec['.user'] = 'anon';
            }
            if (!isset($rec['.creator']) || !$rec['.creator']) {
                $rec['.creator'] = 'anon';
            }
            $data[$key] = $rec;
        }
        $db->write( $data );
    } // checkAndFixData
} // class LzyCalendar




function publishICal( $id )
{
    $cacheFile = CACHE_PATH."cal-$id.dat";
    if (!file_exists($cacheFile)) {
        return;
    }
    $obj = unserialize( file_get_contents($cacheFile) );

    // get destination filename:
    $destFile = $obj['publish'];
    if ($destFile === true) {
        $destFile = "~/ics/".basename($obj['source']);

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
    $ds = new DataStorage2(['dataFile'=> $obj['source']]);
    $lastUpdated = intval($ds->lastDbModified());

    // publishCallback:
    if ($obj['publishCallback']) {
        $publishCallback = base_name($obj['publishCallback'], false);
        $publishCallback = '-' . ltrim($publishCallback, '-') . '.php';
        $publishCallbackCode = USER_CODE_PATH . $publishCallback;
        if (file_exists($publishCallbackCode)) {
            require_once $publishCallbackCode;  // -> must define function 'iCalDescriptionCallback($rec)'
        } else {
            $obj['publishCallback'] = false;
        }
    }

    // update if outdated:
    if ($lastUpdated > $lastExported) {
        $out = prepareICal( $obj, $ds );
        file_put_contents($destFile, $out);
        writeLog("calendar exported to '$destFile'");
    }
} // publishICal



function prepareICal( $obj, $ds )
{
    require_once '_lizzy/extensions/calendar/third-party/fullcalendar/php/utils.php';
    $timezone = isset($_SESSION['lizzy']['systemTimeZone']) ? $_SESSION['lizzy']['systemTimeZone'] : 'UTC';
    date_default_timezone_set($timezone);

    $data = $ds->read();

    $recsToShow = filterCalRecords($obj, $data);

    $vCalendar = new \Eluceo\iCal\Component\Calendar($obj['domain']);
    $name = base_name($obj['publish'], false);

    foreach ($recsToShow as $rec) {
        if ($obj['categoryPrefixes']) {
            $category = $rec['category'];
            $prefix = isset($obj['categoryPrefixes[$category']) ? $obj['categoryPrefixes[$category']: $obj['defaultCatPrefix'];
        } else {
            $prefix = $obj['defaultCatPrefix'];
        }
        // determine UID (unique id that is invariable even if calendar changes):
        $uid = (isset($rec[REC_KEY_ID]) && $rec[REC_KEY_ID])? $rec[REC_KEY_ID]: strtotime($rec['start']);
        $uid = "lzy-cal-$name-{$rec['category']}-$uid";

        // prepare description:
        if ($obj['publishCallback'] && function_exists('iCalDescriptionCallback')) {
            $description = iCalDescriptionCallback($rec);
        } else {
            $description = isset($rec['comment'])? $rec['comment']: '';
            $description = preg_replace('|<a href=["\'](.*?)["\'].*?>.*?</a>|', "$1", $description);
        }
        $title = trim("$prefix {$rec['title']}");

        // assemble iCalendar entry:
        $vEvent = new \Eluceo\iCal\Component\Event();
        $vEvent
            ->setDtStart(new \DateTime($rec['start']))
            ->setDtEnd(new \DateTime($rec['end']))
            ->setSummary($title)
            ->setLocation( isset($rec['location'])? $rec['location']: '' )
            ->setDescription( $description )
            ->setUniqueId($uid)
        ;
        $vCalendar->addComponent($vEvent);
    }
    return $vCalendar->render();
} // prepareICal




function filterCalRecords( $obj, $data)
{
    if (!$obj['showCategories']) {
        return $data;
    }

    $categoriesToShow = ',' . str_replace(' ', '', $obj['showCategories']) . ',';

    // Accumulate an output array of event data arrays.
    $output_arrays = array();
    if (!$data) {
        return [];
    }

    foreach ($data as $i => $rec) {

        // Convert the input array into a useful Event object
        $event = new Event($rec, $obj['timezone']);

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

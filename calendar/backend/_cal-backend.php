<?php

define('SYSTEM_PATH',           '../../../');
define('PATH_TO_APP_ROOT',      SYSTEM_PATH.'../');
define('CUSTOM_CAL_BACKEND',    PATH_TO_APP_ROOT.'code/_custom-cal-backend.php');

ob_start();

// Require Event class and datetime utilities
require dirname(__FILE__) . '/../third-party/fullcalendar/php/utils.php';

// Require Datastorage class:
require_once SYSTEM_PATH.'backend_aux.php';
require_once SYSTEM_PATH.'datastorage2.class.php';


// Check whether there is custome backend code 'code/_custom-cal-backend.php':
if (file_exists(CUSTOM_CAL_BACKEND)) {
    require_once CUSTOM_CAL_BACKEND;
}

if (!isset($_REQUEST['inx'])) {
    lzyExit('error: inx missing in ajax request');
}

session_start();

$inx = $_REQUEST['inx'];
$calSession = &$_SESSION['lizzy']['cal'][$inx];
$dataSrc = PATH_TO_APP_ROOT.$calSession['dataSource'];

$backend = new CalendarBackend($dataSrc);

if (isset($_GET['save'])) {
    lzyExit( $backend->saveData($_POST) );
}
if (isset($_GET['del'])) {
    lzyExit( $backend->deleteRec($_POST) );
}
if (isset($_GET['mode'])) {
    lzyExit( $backend->saveMode($_GET['mode']) );
}
if (isset($_GET['date'])) {
    lzyExit( $backend->saveCurrDate($_GET['date']) );
}

lzyExit( $backend->getData($inx) );




class CalendarBackend {

    public function __construct($dataSrc)
    {
        global $inx;
        $this->dataSrc = $dataSrc;
        if (isset($_GET['start'])) {
            $rangeStart = $_GET['start'];
            $_SESSION['lizzy']['defaultDate'] = $rangeStart;
        } else {
            $rangeStart = date('Y-m-d', strtotime('+1 week'));
        }
        $this->rangeStart = parseDateTime($rangeStart);

        if (isset($_GET['end'])) {
            $this->rangeEnd = parseDateTime($_GET['end']);
        } else {
            $this->rangeEnd = parseDateTime(date('Y-m-d', strtotime('-1 month')));
        }

        $this->timezone = new DateTimeZone($_SESSION['lizzy']['systemTimeZone']);

        $useRecycleBin = $_SESSION['lizzy']['cal'][$inx]['useRecycleBin'];
        $this->ds = new DataStorage2(['dataFile' => $dataSrc, 'useRecycleBin' => $useRecycleBin]);
        $this->calCatPermission = @$_SESSION['lizzy']['cal'][$inx]['calCatPermission'];

    } // __construct



    //--------------------------------------------------------------
    public function getData($inx)
    {
        $data = $this->ds->read();
        $categoriesToShow = $_SESSION['lizzy']['cal'][$inx]['calShowCategories'];
        // possible enhancement: chose category from browser:
        //        if ($categoriesToShow2 = (isset($_GET['category'])) ? $_GET['category']: '') {
        //          $categoriesToShow = "$categoriesToShow,$categoriesToShow2";
        //        }

        $data1 = $this->filterCalRecords($categoriesToShow, $data);
        $data1 = $this->prepareDataForClient($data1);

        // Send JSON to the client.
        $json = json_encode($data1);
        return $json;
    } // getData



    //--------------------------------------------------------------
    public function saveData($post)
    {
        global $inx;

        $creator = '';
        if (isset($post['json'])) {
            $rec0 = json_decode($post['json'], true);
        } else {
            $rec0 = $post;
        }
        mylog( var_r($rec0) );

        $data = $this->ds->read();

        if (isset($rec0['rec-id']) && ($rec0['rec-id'] !== '')) {    // Modify:
            $recId = intval($rec0['rec-id']);
            $msg = 'Modified event';
            if (isset($data[$recId])) {
                $oldRec = $data[$recId];
                unset($data[$recId]);
            } else {
                $oldRec = false;
            }

            $creatorOnlyPermission = $_SESSION["lizzy"]['cal'][$inx]['creatorOnlyPermission'];
            if (isset($oldRec['_creator'])) {
                $creator = $oldRec['_creator'];

                // enforce creator-only rule:
                if ($creatorOnlyPermission && ($creatorOnlyPermission !== $creator)) {
                    // Note: this case is already taken care of in the client,
                    // so we probably have a hacking attempt here:
                    lzyExit("Error: user {$creatorOnlyPermission} attempted to modify event created by $creator");
                }
            }
            if (isset($oldRec['_uid'])) {
                $uid = $oldRec['_uid'];
            } else {
                $uid = time();
            }

        } else {                // New Entry:
            $uid = time();
            $msg = 'Created new event';
            $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']: false;
            if ($user) {
                $creator =  $user;
            }
        }

        $rec = $this->prepareRecord($rec0);
        $this->writeLogEntry($msg, $rec);

        $rec['_creator'] =  $creator;
        $rec['_uid'] =  $uid;
        $data[] = $rec;
        $data = array_values($data);
        $this->ds->write( $data );

        return 'ok';
    } // saveData



    //--------------------------------------------------------------
    public function deleteRec($rec)
    {
        if (!isset($rec['rec-id']) || ($rec['rec-id'] === '')) {
            return 'not ok';
        }
        $recId = $rec['rec-id'];
        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']:'anonymous';
        $rec['_user'] = $user;
        $this->writeLogEntry("Deleted event", $this->prepareRecord($rec));
        return $this->_deleteRec($recId);
    } // deleteRec




    //--------------------------------------------------------------
    private function _deleteRec($recId)
    {
        $this->ds->deleteRecord($recId);
        return 'ok';
    } // deleteRec




    //--------------------------------------------------------------
    public function saveMode($mode)
    {
        $inx = intval($_GET['inx']);
        $_SESSION['lizzy']['cal'][$inx]['calMode'] = $mode;
        return '';
    } // saveMode



    //--------------------------------------------------------------
    public function saveCurrDate($date)
    {
        $inx = $_GET['inx'];
        $_SESSION['lizzy']['cal'][$inx]['initialDate'] = $date;
        return '';
    } // saveCurrDate



    //--------------------------------------------------------------
    public function filterCalRecords($categoriesToShow, $data)
    {
        if ($categoriesToShow) {
            $categoriesToShow = ',' . str_replace(' ', '', $categoriesToShow) . ',';
        }


        // Accumulate an output array of event data arrays.
        if (!$data) {
            return [];
        }
        $output_arrays = array();
        foreach ($data as $i => $rec) {
            if ((!isset($rec['start']) || !$rec['start']) ||
                (!isset($rec['end']) || !$rec['end'])  ) {
                continue;
            }
            // Convert the input array into a useful Event object
            $event = new Event($rec, $this->timezone);

            // check for category:
            $show = true;
            if ($categoriesToShow && isset($event->properties["category"]) && $event->properties["category"]) {
                $show = false;
                $eventsCategory = explode(',', $event->properties["category"]);
                foreach ($eventsCategory as $evTag) {
                    if ($evTag && (stripos($categoriesToShow, ",$evTag,") !== false)) {
                        $show = true;
                        break;
                    }
                }
            }
            if (!$show) {
                continue;
            }

            // If the event is in-bounds, add it to the output
            if ($event->isWithinDayRange($this->rangeStart, $this->rangeEnd)) {
                $output_arrays[] = array_merge($event->toArray(), ['i' => $i]);
            }
        }
        return $output_arrays;
    } // filterCalRecords




    //--------------------------------------------------------------
    private function prepareDataForClient($data)
    {
        foreach ($data as $key => $rec) {
            unset($data[$key]['_user']);
        }
        return $data;
    } // prepareDataForClient




    //--------------------------------------------------------------
    private function prepareRecord($rec)
    {
        if (!isset($rec['start-date']) || !isset($rec['end-date'])) {
            return [];
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rec['start-date'], $m)) {
            $startDate = $m[1];
        } else {
            $startDate = $rec['start-date'];
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rec['end-date'], $m)) {
            $endDate = $m[1];
        } else {
            $endDate = $rec['end-date'];
        }
        $startTime = isset($rec['start-time'])? $rec['start-time']: '';
        $endTime = isset($rec['end-time'])? $rec['end-time']: '';

        if (isset($rec['allday']) && (($rec['allday'] === 'true') || ($rec['allday'] === true))) {
            $startTime = '';
            $endTime = '';
            $endDate = date('Y-m-d', strtotime("+1 day", strtotime($endDate)));
        }
        unset($rec['inx']);
        unset($rec['rec-id']);
        unset($rec['start-time']);
        unset($rec['start-date']);
        unset($rec['end-time']);
        unset($rec['end-date']);
        unset($rec['allday']);
        unset($rec['_creator']);
        $rec['start'] = trim($startDate . ' ' . $startTime);
        $rec['end'] = trim($endDate . ' ' . $endTime);

        $rec['_user'] = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user'] ? $_SESSION['lizzy']['user'] : 'anonymous';

        if (function_exists('customPrepareData')) {
            $rec = customPrepareData($rec);
        }
        return $rec;
    } // prepareRecord




    //--------------------------------------------------------------
    private function writeLogEntry($msg, $rec)
    {
        $logEntry = json_encode($rec);

        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']:'anonymous';
        $rec['_user'] = $user;
        mylog("$msg ({$this->dataSrc}): $logEntry", $user);

    } // writeLogEntry

} // class



<?php

define('SYSTEM_PATH',           '../../../');
define('PATH_TO_APP_ROOT',      SYSTEM_PATH.'../');
define('CUSTOM_CAL_BACKEND',    PATH_TO_APP_ROOT.'code/_custom-cal-backend.php');
define('LOG_WIDTH', 80);

ob_start();

 // Require Event class and datetime utilities
require dirname(__FILE__) . '/../third-party/fullcalendar/php/utils.php';

 // Require Datastorage class:
require_once SYSTEM_PATH.'backend_aux.php';
require_once SYSTEM_PATH.'datastorage2.class.php';
require_once SYSTEM_PATH.'ticketing.class.php';


 // Check whether there is custome backend code 'code/_custom-cal-backend.php':
if (file_exists(CUSTOM_CAL_BACKEND)) {
    require_once CUSTOM_CAL_BACKEND;
}

if (!isset($_REQUEST['inx'])) {
    lzyExit('<>Error: inx missing in ajax request');
}

 // prevent "PHPSESSID"-Cookie warning:
session_set_cookie_params(["SameSite" => "Strict"]); //none, lax, strict
session_set_cookie_params(["Secure" => "true"]); //false, true
session_set_cookie_params(["HttpOnly" => "true"]); //false, true

session_start();

$backend = new CalendarBackend();

if (@$_SESSION['lizzy']['debug'] && @$_REQUEST) {
    mylog( var_r($_REQUEST) );
}

if (isset($_GET['save'])) {
    lzyExit( $backend->saveData($_POST) );
}
if (isset($_GET['del'])) {
    lzyExit( $backend->deleteRec($_POST) );
}
if (isset($_GET['mode'])) {
    lzyExit( $backend->saveMode($_GET['mode']) );
}

lzyExit( $backend->getData() );




class CalendarBackend {

    public function __construct()
    {
        $inx = $_REQUEST['inx'];

        if (!isset($_SESSION['lizzy']['systemTimeZone'])) {
            mylog('Error in CalendarBackend: systemTimeZone not defined');
            // <> signals the client that it's a server error msg -> will be shown in debug mode only:
            die('<>Error in CalendarBackend: systemTimeZone not defined');
        }
        $timezoneStr = $_SESSION['lizzy']['systemTimeZone'];
        $this->timezone = $timezone = new DateTimeZone( $timezoneStr );
        date_default_timezone_set( $timezoneStr );

        $this->calSession = &$_SESSION['lizzy']['cal'][$inx];

        $this->tickHash = $_GET['ds'];
        $this->tck = new Ticketing();
        $calRec = $this->tck->consumeTicket( $this->tickHash );
        $this->calRec = $calRec;

        if (!isset($calRec['dataSource'])) {
            die('<>Error cal-backend: "dataSource" not defined.');
        }

        $dataSrc = PATH_TO_APP_ROOT.$calRec['dataSource'];
        $this->dataSrc = $dataSrc;
        if (isset($_GET['start'])) {
            $rangeStart = $_GET['start'];
            $calRec['defaultDate'] = $rangeStart;
        } else {
            $rangeStart = date('Y-m-d', strtotime('+1 week'));
        }
        $this->rangeStartStr = $rangeStart;
        $this->rangeStart = parseDateTime($rangeStart, $timezone);

        if (isset($_GET['end'])) {
            $this->rangeEnd = parseDateTime($_GET['end'], $timezone);
        } else {
            $this->rangeEnd = parseDateTime(date('Y-m-d', strtotime('-1 month')), $timezone);
        }

        $useRecycleBin = $calRec['useRecycleBin'];
        $this->ds = new DataStorage2([
            'dataFile' => $dataSrc,
            'useRecycleBin' => $useRecycleBin,
            'exportInternalFields' => true,
            ]);

        $this->calCatPermission = @$calRec['calCatPermission'];
    } // __construct



    //--------------------------------------------------------------
    public function getData()
    {
        $data = $this->ds->read();
        $categoriesToShow = $this->calRec['calShowCategories'];
        // possible enhancement: chose category from browser:
        //        if ($categoriesToShow2 = (isset($_GET['category'])) ? $_GET['category']: '') {
        //          $categoriesToShow = "$categoriesToShow,$categoriesToShow2";
        //        }

        $data1 = $this->filterCalRecords($categoriesToShow, $data);
        $data1 = $this->prepareDataForClient($data1);

        $date = substr($this->rangeStartStr,0, 10);
        $this->calSession['initialDate'] = $date;


        // Send JSON to the client.
        $json = json_encode($data1);
        return $json;
    } // getData



    //--------------------------------------------------------------
    public function saveData($post)
    {
        if (isset($post['json'])) {
            $suppliedRec = json_decode($post['json'], true);
        } else {
            $suppliedRec = $post;
        }
        $suppliedRec['.user'] = @$_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']: 'anon';

        // check freezePast:
        $freezePast = false;
        if ($this->calRec['freezePast']) {
            // End is in the past -> just reject:
            $end = strtotime("{$suppliedRec['end-date']} {$suppliedRec['end-time']}");
            if ($end < time()) {
                $this->writeLogEntry("freezePast", $suppliedRec);
                return 'Event in the past may not be created or modified.';
            }
            $start = strtotime("{$suppliedRec['start-date']} {$suppliedRec['start-time']}");
            if ($start < time()) {
                $freezePast = true;
            }
        }

        $data = $this->ds->read();

        if ((@$suppliedRec['rec-id'] !== '') && (isset($data[$suppliedRec['rec-id']]))) {    // Modify:
            $oldRecId = $suppliedRec['rec-id'];
            $oldRec = $data[ $oldRecId ];
            $isNewRec = false;
            $msg = 'Modified event';
            $suppliedRec['.uid'] = $oldRec['.uid'];
            $suppliedRec['.creator'] =  $oldRec['.creator'];
            if ($freezePast) {
                $oldEnd = strtotime($oldRec['start']);
                $suppliedRec['start-date'] = date('Y-m-d', $oldEnd);
                $suppliedRec['start-time'] = date('H:i', $oldEnd);
            }

        } else {                // New Entry:
            $isNewRec = true;
            $msg = 'Created new event';
            $suppliedRec['.uid'] = createHash(12);
            $suppliedRec['.creator'] =  $suppliedRec['.user'];
            if ($freezePast) {
                $this->writeLogEntry("freezePast", $suppliedRec);
                return 'Events in the past may not be created or modified.';
            }
        }

        $newRec = $this->prepareRecord($suppliedRec);
        $this->writeLogEntry($msg, $newRec);

        if ($isNewRec) {
            $data[] = $newRec;
        } else {
            $data[ $oldRecId ] = $newRec;
        }

        usort($data, function($a, $b) {
            return ($a['start'] < $b['start']) ? -1 : 1;
        });
        $this->ds->write( $data );

        return 'ok';
    } // saveData



    //--------------------------------------------------------------
    public function deleteRec($rec)
    {
        if (!isset($rec['rec-id']) || ($rec['rec-id'] === '')) {
            return '<>Error deleteRec: rec-id not supplied';
        }
        $recId = $rec['rec-id'];
        $msg = 'Deleted event';
        $deletePast = true;

        // check freezePast:
        if ($this->calRec['freezePast']) {
            $end = strtotime("{$rec['end-date']} {$rec['end-time']}");
            if ($end < time()) {
                return 'Events in the past may not be deleted.';
            }
            $start = strtotime("{$rec['start-date']} {$rec['start-time']}");
            if ($start < time()) {
                $msg = 'Event partially deleted.';
                $deletePast = false;
            }
        }

        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']:'anon';
        $rec['.user'] = $user;
        $this->writeLogEntry($msg, $this->prepareRecord($rec));
        $this->_deleteRec($recId, $deletePast);
        return '';
    } // deleteRec




    //--------------------------------------------------------------
    private function _deleteRec($recId, $deletePast)
    {
        $data = $this->ds->read();
        if (isset($data[$recId])) {
            if ($deletePast) {
                unset($data[$recId]);
                $data = array_values($data);
            } else {
                $data[$recId]['end'] = date('Y-m-d H:i');
            }
            // make sure index remains numeric:
            $this->ds->write( $data );
        } else {
            $this->writeLogEntry("Error deleting event '$recId'");
        }
        return 'ok';
    } // deleteRec




    //--------------------------------------------------------------
    public function saveMode($mode)
    {
        $this->calSession['calMode'] = $mode;
        return '';
    } // saveMode



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
            unset($data[$key]['.user']);
        }
        return $data;
    } // prepareDataForClient




    //--------------------------------------------------------------
    private function prepareRecord( $rec )
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

        if (function_exists('customPrepareData')) {
            $outRec = customPrepareData($rec);
        } else {
            $outRec = [];
            $outRec['title'] = date('c', strtotime($startDate . ' ' . $startTime));
            $outRec['start'] = trim("$startDate $startTime");
            $outRec['end']   = trim("$endDate $endTime");
            foreach ($rec as $key => $elem) {
                if (strpos(',start,end,inx,rec-id,lzy-cal-ref,start-time,start-date,end-time,end-date,allday,.creator,', ",$key,") === false) {
                    $outRec[$key] = $elem;
                }
            }
            $outRec['__uid'] = @$rec['.uid'];
            $outRec['__creator'] = @$rec['.creator'];
            $outRec['__user'] = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user'] ? $_SESSION['lizzy']['user'] : 'anon';
        }
        return $outRec;
    } // prepareRecord




    //--------------------------------------------------------------
    private function writeLogEntry($msg, $rec)
    {
        $logEntry = json_encode($rec);

        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']:'anonymous';
        $rec['.user'] = $user;

        if (!is_string($msg)) {
            $msg = var_export($msg, true);
            $msg = str_replace("\n", '', $msg);
        }
        $msg = "$msg ({$this->dataSrc}): $logEntry";

        $str = '';
        $indent = '                     ';
        $s = str_replace(["\n", "\t", "\r"], [' ',' ',''], $msg);
        while (strlen($s) > LOG_WIDTH) {
            $str .= substr($s, 0,LOG_WIDTH)."\n$indent";
            $s = substr($s, LOG_WIDTH);
        }
        $str .= $s;

        writeLog($str, $user, PATH_TO_APP_ROOT . LOG_PATH . 'log.txt');
    } // writeLogEntry

} // class



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
    lzyExit('error: inx missing in ajax request');
}

// prevent "PHPSESSID"-Cookie warning:
session_set_cookie_params(["SameSite" => "Strict"]); //none, lax, strict
session_set_cookie_params(["Secure" => "true"]); //false, true
session_set_cookie_params(["HttpOnly" => "true"]); //false, true

session_start();

$backend = new CalendarBackend();

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

lzyExit( $backend->getData() );




class CalendarBackend {

    public function __construct()
    {
        $inx = $_REQUEST['inx'];

        if (!isset($_SESSION['lizzy']['systemTimeZone'])) {
            mylog('Error in CalendarBackend: systemTimeZone not defined');
            die('Error in CalendarBackend: systemTimeZone not defined');
        }
        $timezoneStr = $_SESSION['lizzy']['systemTimeZone'];
        $this->timezone = $timezone = new DateTimeZone( $timezoneStr );
        date_default_timezone_set( $timezoneStr );

        $this->calSession = &$_SESSION['lizzy']['cal'][$inx];

        $this->tickHash = $_GET['ds'];
        $this->tck = new Ticketing();
        $calRec = $this->tck->consumeTicket( $this->tickHash );
        $this->calRec = $calRec;

        $dataSrc = PATH_TO_APP_ROOT.$calRec['dataSource'];
        $this->dataSrc = $dataSrc;
        if (isset($_GET['start'])) {
            $rangeStart = $_GET['start'];
            $calRec['defaultDate'] = $rangeStart;
        } else {
            $rangeStart = date('Y-m-d', strtotime('+1 week'));
        }
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
        $suppliedRec['_user'] = @$_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']: 'anon';
        mylog( var_r($suppliedRec) );

        $freeze = false;
        // check freezePast:
        if ($this->calRec['freezePast']) {
            // End is in the past -> just reject:
            $end = strtotime("{$suppliedRec['end-date']} {$suppliedRec['end-time']}");
//$diff = ($end - $now);
//$endStr = date('Y-m-d H:i:s', $end);
//$nowStr = date('Y-m-d H:i:s');
//$timeZone = date_default_timezone_get ( );
            if ($end < time()) {
                $this->writeLogEntry("freezePast", $suppliedRec);
                return 'Event in the past may not be created or modified';
            }
            $start = strtotime("{$suppliedRec['start-date']} {$suppliedRec['start-time']}");
            if ($start < time()) {
                $freeze = true;
            }
        }

        $data = $this->ds->read();

        if ((@$suppliedRec['rec-id'] !== '') && (isset($data[$suppliedRec['rec-id']]))) {    // Modify:
            $oldRecId = $suppliedRec['rec-id'];
            $oldRec = $data[ $oldRecId ];
            $isNewRec = false;
            $msg = 'Modified event';
            $suppliedRec['_uid'] = $oldRec['_uid'];
            $suppliedRec['_creator'] =  $oldRec['_creator'];

        } else {                // New Entry:
            $isNewRec = true;
            $msg = 'Created new event';
            $suppliedRec['_uid'] = createHash(12);
            $suppliedRec['_creator'] =  $suppliedRec['_user'];
        }

        $newRec = $this->prepareRecord($suppliedRec);
//        if ($freeze) {
//            $newRec['start'] = $freeze;
//        }
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
            return 'not ok';
        }
        $recId = $rec['rec-id'];

        // check freezePast:
        if ($this->calRec['freezePast']) {
            $start = strtotime("{$rec['start-date']} {$rec['start-time']}");
            if ($start < time()) {
                return 'Event in the past may not be deleted';
            }
        }

        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']:'anonymous';
        $rec['_user'] = $user;
        $this->writeLogEntry("Deleted event", $this->prepareRecord($rec));
        return $this->_deleteRec($recId);
    } // deleteRec




    //--------------------------------------------------------------
    private function _deleteRec($recId)
    {
        $data = $this->ds->read();
        if (isset($data[$recId])) {
            unset($data[$recId]);
            // make sure index remains numeric:
            $data = array_values($data);
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
    public function saveCurrDate($date)
    {
        $this->calSession['initialDate'] = $date;
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
                if (strpos(',start,end,inx,rec-id,lzy-cal-ref,start-time,start-date,end-time,end-date,allday,_creator,', ",$key,") === false) {
                    $outRec[$key] = $elem;
                }
            }
            $outRec['_creator'] = @$rec['_creator'];
            $outRec['_user'] = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user'] ? $_SESSION['lizzy']['user'] : 'anon';
        }
        return $outRec;
    } // prepareRecord




    //--------------------------------------------------------------
    private function writeLogEntry($msg, $rec)
    {
        $logEntry = json_encode($rec);

        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']:'anonymous';
        $rec['_user'] = $user;

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



<?php

define('SYSTEM_PATH', '../../../');
define('PATH_TO_APP_ROOT', SYSTEM_PATH.'../');
define('CUSTOM_CAL_BACKEND', PATH_TO_APP_ROOT.'code/_custom-cal-backend.php');

require_once SYSTEM_PATH.'vendor/autoload.php';

//use Symfony\Component\Yaml\Yaml;


// Require Event class and datetime utilities
require dirname(__FILE__) . '/../code/utils.php';

// Require Datastorage class:
require_once SYSTEM_PATH.'datastorage2.class.php';
require_once SYSTEM_PATH.'backend_aux.php';


// Check whether there is custome backend code 'code/_custom-cal-backend.php':
if (file_exists(CUSTOM_CAL_BACKEND)) {
    require_once CUSTOM_CAL_BACKEND;
}

if (!isset($_REQUEST['inx'])) {
    exit('error: inx missing in ajax request');
}

session_start();

$inx = $_REQUEST['inx'];
$dataSrc = PATH_TO_APP_ROOT.$_SESSION['lizzy']['cal'][$inx];

$backend = new CalendarBackend($dataSrc);

if (isset($_GET['save'])) {
    exit( $backend->saveNewData($_POST) );
}
if (isset($_GET['del'])) {
    exit( $backend->deleteRec($_POST) );
}
if (isset($_GET['mode'])) {
    exit( $backend->saveMode($_GET['mode']) );
}

exit( $backend->getData($inx) );




class CalendarBackend {

    public function __construct($dataSrc)
    {
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

        $this->ds = new DataStorage2(['dataFile' => $dataSrc]);

    } // __construct



    //--------------------------------------------------------------
    public function getData($inx)
    {
        $data = $this->ds->read();
        $categoriesToShow = $_SESSION['lizzy']['calShowCategories'][$inx];
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
    public function saveNewData($post)
    {
        $creator = '';
        if (isset($post['json'])) {
            $rec0 = json_decode($post['json'], true);
        } else {
            $rec0 = $post;
        }

        if (isset($rec0['rec-id']) && intval($rec0['rec-id'])) {    // Modify:
            $recId = intval($rec0['rec-id']);
            $msg = 'Modified event';
            $oldRec = $this->ds->readElement($recId);
            if (isset($oldRec['_creator'])) {
                $creator = $oldRec['_creator'];
            }

        } else {                // New Entry:
            $recId = time();
            $msg = 'Created new event';
            $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']: false;
            if ($user) {
                $creator =  $user;
            }
        }

        $rec = $this->prepareRecord($rec0);
        $this->writeLogEntry($msg, $rec);

        $this->_deleteRec($recId);
        $rec['_creator'] =  $creator;
        $this->ds->writeElement($recId, $rec);

        return 'ok';
    } // saveNewData



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
        $this->ds->delete($recId);
        return 'ok';
    } // deleteRec




    //--------------------------------------------------------------
    public function saveMode($mode)
    {
        $_SESSION['lizzy']['calMode'] = $mode;
        return 'ok';
    } // saveMode



    //--------------------------------------------------------------
    public function filterCalRecords($categoriesToShow, $data)
    {
        if ($categoriesToShow) {
            $categoriesToShow = ',' . str_replace(' ', '', $categoriesToShow) . ',';
        }


        // Accumulate an output array of event data arrays.
        $output_arrays = array();
        foreach ($data as $i => $rec) {
            if ((!isset($rec['title']) || !$rec['title']) ||
                (!isset($rec['start']) || !$rec['start']) ||
                (!isset($rec['end']) || !$rec['end'])  ) {
                continue;
            }
            // Convert the input array into a useful Event object
            $event = new Event($rec, $this->timezone);

            // check for category:
            if ($categoriesToShow && isset($event->properties["category"]) && $event->properties["category"]) {
                $eventsCategory = explode(',', $event->properties["category"]);
                foreach ($eventsCategory as $evTag) {
                    if ($evTag && (stripos($categoriesToShow, ",$evTag,") === false)) {
                        continue 2;
                    }
                }
            }

            // If the event is in-bounds, add it to the output
            if ($event->isWithinDayRange($this->rangeStart, $this->rangeEnd)) {
                $output_arrays[] = array_merge($event->toArray(), ['i' => $i]);
            }
        }
        return $output_arrays;
    } // filterCalRecords



/*
    private function convertYaml($str)
    {
        $data = null;
        if ($str) {
            $str = str_replace("\t", '    ', $str);
            try {
                $data = Yaml::parse($str);
            } catch(Exception $e) {
                die("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
            }
        }
        return $data;
    } // convertYaml




    //--------------------------------------------------------------
    private function convertToYaml($data, $level = 3)
    {
        return Yaml::dump($data, $level);
    } // convertToYaml


    private function timeArrayToStr($arr) {
        $str = $arr[0].'-'.$this->numPad($arr[1]+1).'-'.$this->numPad($arr[2]).' '.$this->numPad($arr[3]).':'.$this->numPad($arr[4]);
        return $str;
    }

    //--------------------------------------------------------------
    private function numPad($str, $len = 2) {
        while (strlen($str) < $len) {
            $str = '0'.$str;
        }
        return $str;
    }
*/




    private function prepareDataForClient($data)
    {
        foreach ($data as $key => $rec) {
            unset($data[$key]['_creator']);
            unset($data[$key]['_user']);
        }
        return $data;
    }




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




    private function writeLogEntry($msg, $rec)
    {
        $logEntry = json_encode($rec);

        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']:'anonymous';
        $rec['_user'] = $user;
        mylog("$msg ({$this->dataSrc}): $logEntry", $user);

    } // writeLogEntry

} // class



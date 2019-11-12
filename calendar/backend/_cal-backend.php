<?php

define('SYSTEM_PATH', '../../../');
define('PATH_TO_APP_ROOT', SYSTEM_PATH.'../');
define('CUSTOM_CAL_BACKEND', PATH_TO_APP_ROOT.'code/_custom-cal-backend.php');

require_once SYSTEM_PATH.'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;


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

        $output_arrays = $this->filterCalRecords($categoriesToShow, $data);

        // Send JSON to the client.
        $json = json_encode($output_arrays);
        return $json;
    } // getData



    //--------------------------------------------------------------
    public function saveNewData($post)
    {
        if (isset($post['json'])) {
            $post = json_decode($post['json'], true);
        }

        $recId = (isset($post['rec-id']) && intval($post['rec-id'])) ? intval($post['rec-id']) : time();

        $rec = $this->prepareRecord($post);
        $this->deleteRec($post);
        $this->ds->writeElement($recId, $rec);

        return 'ok';
    } // saveNewData



    //--------------------------------------------------------------
    public function deleteRec($rec)
    {
        if (isset($rec['rec-id']) && ($rec['rec-id'] !== '')) {
            $recId = $rec['rec-id'];
            $this->ds->delete($recId);
        }
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




    //--------------------------------------------------------------
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


    private function numPad($str, $len = 2) {
        while (strlen($str) < $len) {
            $str = '0'.$str;
        }
        return $str;
    }




    private function prepareRecord($rec)
    {
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
        $startTime = $rec['start-time'];
        $endTime = $rec['end-time'];

        if (($rec['allday'] === 'true') || ($rec['allday'] === true)) {
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
        $rec['start'] = trim($startDate . ' ' . $startTime);
        $rec['end'] = trim($endDate . ' ' . $endTime);
        if (function_exists('customPrepareData')) {
            $rec = customPrepareData($rec);
        }
        return $rec;
    } // prepareRecord

} // class



<?php

define('SYSTEM_PATH', '../../../');
define('PATH_TO_APP_ROOT', SYSTEM_PATH.'../');
define('CUSTOM_CAL_BACKEND', PATH_TO_APP_ROOT.'code/_custom-cal-backend.php');

require_once SYSTEM_PATH.'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;


// Require Event class and datetime utilities
require dirname(__FILE__) . '/utils.php';

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

        $this->timezone = null;
        if (isset($_GET['timezone'])) {
            $this->timezone = new DateTimeZone($_GET['timezone']);
        }

        $this->ds = new DataStorage2(['dataFile' => $dataSrc]);
    } // __construct



    //--------------------------------------------------------------
    public function getData($inx)
    {
        $data = $this->ds->read();
//        $tags = (isset($_GET['tags'])) ? $_GET['tags']: '';
        $tags = $_SESSION['lizzy']['calShowTags'][$inx];
        if ($tags) {
            $tags = ',' . str_replace(' ', '', $tags) . ',';
        }


        // Accumulate an output array of event data arrays.
        $output_arrays = array();
        foreach ($data as $i => $rec) {

            // Convert the input array into a useful Event object
            $event = new Event($rec, $this->timezone);

            // check for tags:
            if ($tags && isset($event->properties["tags"]) && $event->properties["tags"]) {
                $eventsTags = explode(',', $event->properties["tags"]);
                foreach ($eventsTags as $evTag) {
                    if (strpos($tags, ",$evTag,") === false) {
                        continue 2;
                    }
                }
            }

            // If the event is in-bounds, add it to the output
            if ($event->isWithinDayRange($this->rangeStart, $this->rangeEnd)) {
                $output_arrays[] = array_merge($event->toArray(), ['i' => $i]);
            }
        }

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
        $this->deleteRec($post);

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $post['start-date'], $m)) {
            $startDate = $m[1];
        } else {
            $startDate = $post['start-date'];
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $post['end-date'], $m)) {
            $endDate = $m[1];
        } else {
            $endDate = $post['end-date'];
        }
        $startTime = $post['start-time'];
        $endTime = $post['end-time'];

        $recId = (isset($post['rec-id']) && intval($post['rec-id'])) ? intval($post['rec-id']) : time();

        if (preg_match('/\[(.*)\]/', $startTime, $m)) { // array of events:
            $startTimes = explode(',', $m[1]);
            $endTimes = explode(',', str_replace(['[',']'], '', $endTime));
            foreach ($startTimes as $i => $startTime) {
                $endTime = $endTimes[$i];
                if ($post['allday']) {
                    $startTime = '';
                    $endTime = '';
                    $endDate = date("+1 day", strtotime($endDate));
                }
                $rec = [
                    'title' => $post['title'],
                    'start' => trim($startDate.' '.$startTime),
                    'end' => trim($endDate.' '.$endTime),
                    'allDay' => $post['allday'],
                    'location' => $post['location'],
                    'comment' => $post['comment'],
                    'tags' => $post['tags'],
                ];
                if (function_exists('customPrepareData')) {
                    $rec = customPrepareData($rec);
                }
                $this->ds->writeElement($recId++, $rec);
            }

        } else {        // single event:
            if (($post['allday'] === 'true') || ($post['allday'] === true)) {
                $startTime = '';
                $endTime = '';
                $endDate = date('Y-m-d', strtotime("+1 day", strtotime($endDate)));
            }
            $rec = [
                'title' => $post['title'],
                'start' => trim($startDate.' '.$startTime),
                'end' => trim($endDate.' '.$endTime),
                'location' => $post['location'],
                'comment' => $post['comment'],
                'tags' => $post['tags'],
            ];
            if (function_exists('customPrepareData')) {
                $rec = customPrepareData($rec);
            }
            $this->ds->writeElement($recId, $rec);
        }

        return 'ok';
    } // saveNewData



    //--------------------------------------------------------------
    public function deleteRec($post)
    {
        if (isset($post['rec-id']) && ($post['rec-id'] !== '')) {
            $recId = $post['rec-id'];
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

} // class



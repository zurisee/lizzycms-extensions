<?php
// Backend for live-data() macro


//define('SYSTEM_PATH', 		'');		 // same directory
//define('PATH_TO_APP_ROOT', 	'../');		 // root folder of web app
define('SYSTEM_PATH',           '../../../');
define('PATH_TO_APP_ROOT',      SYSTEM_PATH.'../');
define('DEFAULT_POLLING_TIME', 	60);		 // s
define('MAX_POLLING_TIME', 	    90);		 // s
define('POLLING_INTERVAL', 	330000);		 // Us

require_once SYSTEM_PATH . 'vendor/autoload.php';
require_once SYSTEM_PATH . 'backend_aux.php';
require_once SYSTEM_PATH . 'datastorage2.class.php';
require_once SYSTEM_PATH . 'ticketing.class.php';

$serv = new LiveDataService();
$response = $serv->execute();
exit($response);



class LiveDataService
{
    public function __construct()
    {
        session_start();
        $this->session = isset($_SESSION['lizzy']) ? $_SESSION['lizzy'] : [];
        $timezone = isset($session['systemTimeZone']) && $this->session['systemTimeZone'] ? $this->session['systemTimeZone'] : 'CET';
        date_default_timezone_set($timezone);

        $this->lastModif = 0;
        $this->pollingTime = DEFAULT_POLLING_TIME;
        session_abort();
    } // __construct




    public function execute()
    {
        $dynDataSel = isset($_GET['dynDataSel']) ? $_GET['dynDataSel'] : false;
        $dynDataSelectors = [];
        if ($dynDataSel && preg_match('/(.*):(.*)/', $dynDataSel, $m)) {
            $dynDataSelectors[ 'name' ] = $m[1];
            $dynDataSelectors[ 'value' ] = $m[2];
        }
        $this->dynDataSelectors = $dynDataSelectors;
        $this->dynDataSel = $dynDataSel;

        $this->openDataSrcs();

        $returnImmediately = isset($_GET['returnImmediately']);
        if ($returnImmediately) {
            $this->lastUpdated = microtime( true );
            $returnData = $this->assembleResponse();
            $returnData['result'] = 'Ok';

        } else {
            if ($this->awaitDataChange()) {
                $returnData = $this->assembleResponse();
                $returnData['result'] = 'Ok';
            } else {
                $returnData['result'] = 'None';
            }
        }

        $returnData['lastUpdated'] = microtime(true) + 0.000001;
        $json = json_encode($returnData);
        exit($json);
    } // execute




    private function getListOfTickets()
    {
        if (!isset($_POST['ref'])) {
            exit('Error: "ref" missing in call to _live_data_service.php');
        }
        $ref = $_POST['ref'];
        $this->lastUpdated = floatval($_POST['last']);

        $tickets = explode(',', $ref);
        $ticketList = [];
        foreach ($tickets as $ticket) {
            if (in_array($ticket, $ticketList) === false) {
                $ticketList[] = $ticket;
            }
        }
        return $ticketList;
    } // getListOfTickets




    private function openDataSrcs()
    {
        $ticketList = $this->getListOfTickets();

        $tick = new Ticketing();
        $this->dataSrcs = [];
        foreach ($ticketList as $ticket) {
            $recs = $tick->consumeTicket($ticket);
            if (!is_array($recs)) {
                continue;
            }
            foreach ($recs as $rec) {
                $file = $rec['dataSource'];
                if (!isset($this->dataSrcs[$file])) {
                    $this->dataSrcs[$file] = [$rec];
                } else {
                    array_push($this->dataSrcs[$file], $rec);
                }

                if (isset($rec['pollingTime']) && ($rec['pollingTime'] > 2)) {
                    $this->pollingTime = $rec['pollingTime'];
                } else {
                    $this->pollingTime = DEFAULT_POLLING_TIME;
                }
            }
        }
        foreach ($this->dataSrcs as $file => $elems) {
            $this->dataSrcs[$file]['db'] = new DataStorage2(PATH_TO_APP_ROOT . $file);
        }
    } // openDataSrcs





    private function awaitDataChange()
    {
        $till = time() + min($this->pollingTime,100); // s
        while (time() < $till) {
            $this->outData = [];
            // there may be multiple data sources, so loop over all of them:
            foreach ($this->dataSrcs as $file => $taskDescr) {
                $db = $taskDescr['db'];
                $lastDbModified = $db->lastDbModified();
                if (($this->lastUpdated < $lastDbModified)) {
                    $this->lastUpdated = $lastDbModified;
                    return true;
                }

                foreach ($taskDescr as $k => $r) {
                    if (!is_int($k)) {
                        continue;
                    }
                    $this->lastModif = $db->lastModifiedElement( $k );
                    if ($this->lastUpdated < $this->lastModif) {
                        $this->lastUpdated = $this->lastModif;
                        return true;
                    }
                }
            }
            $this->checkAbort();
            usleep( POLLING_INTERVAL );
        }
        return false;
    } // awaitDataChange




    private function assembleResponse()
    {
        $outData = [];
        foreach ($this->dataSrcs as $file => $dbDescr) {
            $outRec = $this->getData( $dbDescr );
            if (is_array($outRec)) {
                $outData = array_merge($outData, $outRec);
            } else {
                $outData = $outRec; // case error msg
                break;
            }
        }
        return $outData;
    } // assembleResponse




    private function getData( $dbDescr )
    {
        $db = $dbDescr['db'];
        $dbIsLocked = $db->isDbLocked( false );
        $lockedElements = [];
        $tmp = [];
        foreach ($dbDescr as $k => $elem) {
            if (!is_int($k)) {
                continue;
            }

            $dataKey = $dbDescr[$k]["dataSelector"];
            $targetSelector = $dbDescr[$k]['targetSelector'];

            if (str_replace(' ', '', $dataKey) === '*,*') {
                $data = $db->read();
                $r = 1;
                foreach ($data as $key => $rec) {
                    $c = 1;
                  foreach ($rec as $k => $v) {
                      if (preg_match('/(.*?) \* (.*?) \* (.*?) /x', $targetSelector, $m)) {
                          $targSel = "{$m[1]}$r{$m[2]}$c{$m[3]}";
                          $tmp[$targSel] = $v;
                          $c++;
                      } else {
                          die("Error in ...");
                      }
                  }
                  $r++;
                }
                continue;
            }

            if ((strpos($dataKey, '{') !== false) && $this->dynDataSelectors) {
                $dataKey = preg_replace('/\{'.$this->dynDataSelectors['name'].'\}/', $this->dynDataSelectors['value'], $dataKey);
            }

//            $targetSelector = $dbDescr[$k]['targetSelector'];
            if ((strpos($targetSelector, '{') !== false) && $this->dynDataSelectors) {
                $targetSelector = preg_replace('/\{'.$this->dynDataSelectors['name'].'\}/', $this->dynDataSelectors['value'], $targetSelector);
            }
            if ($dbIsLocked || $db->isRecLocked( $dataKey )) {
                $lockedElements[] = $targetSelector;
            }
            if (preg_match('/(.*?),\*$/', $dataKey, $m)) {
                $value = $db->readRecord( $m[1] );
            } else {
                $value = $db->readElement( $dataKey );
            }
//            $value = $db->readElement( $dataKey );
            if (is_array($value)) {
                $j = 1;
                foreach ($value as $i => $v) {
                    $t = str_replace('*', $j++, $targetSelector);
//                    if (is_int($i)) {
//                        $t = str_replace('*', ($i + 1), $targetSelector);
//                    } else {
//                        $t = str_replace('*', $j++, $targetSelector);
////                        $t = str_replace('*', $i, $targetSelector);
//                    }
//                    $t = str_replace('*', ($i + 1), $targetSelector);
                    $tmp[$t] = $v;
                }
            } else {
                $tmp[$targetSelector] = $value;
            }
        }
        ksort($tmp);
        $outData['data'] = $tmp;


        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($lockedElements !== $_SESSION['lizzy']['hasLockedElements']) {
            $outData['locked'] = $lockedElements;
            $_SESSION['lizzy']['hasLockedElements'] = $lockedElements;
        }
        session_abort();
        return $outData;
    } // getData




    private function checkAbort()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['lizzy']['ajaxServerAbort'])) {
            $_SESSION['lizzy']['ajaxServerAbort'] = false;
            session_write_close();
            return;
        }
        $abortRequest = $_SESSION['lizzy']['ajaxServerAbort'];
        if ($abortRequest !== false) {
            writeLog("live-data ajax-server aborting (\$_SESSION['lizzy']['ajaxServerAbort'] = {$_SESSION['lizzy']['ajaxServerAbort']})");
            $_SESSION['lizzy']['ajaxServerAbort'] = false;
            session_write_close();
            $returnData['result'] = 'None';
            $returnData['lastUpdated'] = microtime(true) + 0.000001;
            $json = json_encode($returnData);
            exit($json);
        }
        session_abort();
    } // checkAbort

} // LiveDataService
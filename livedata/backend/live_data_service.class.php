<?php

class LiveDataService
{
    private $dynDataSelector = null;

    public function __construct()
    {
        if (!session_start()) {
            mylog("ERROR in __construct(): failed to start session");
        }
        $this->session = isset($_SESSION['lizzy']) ? $_SESSION['lizzy'] : [];
        $timezone = isset($session['systemTimeZone']) && $this->session['systemTimeZone'] ? $this->session['systemTimeZone'] : 'CET';
        date_default_timezone_set($timezone);

        $this->lastModif = 0;
        $this->lastUpdated = 0;
        $this->pollingTime = DEFAULT_POLLING_TIME;
    } // __construct




    private function getListOfTickets()
    {
        if (!isset($_POST['ref'])) {
            lzyExit('Error: "ref" missing in call to _live_data_service.php');
        }
        $ref = $_POST['ref'];

        $tickets = explode(',', $ref);
        $ticketList = [];
        $setList = [];
        foreach ($tickets as $ticket) {
            $set = '';
            if (preg_match('/(.*?):(.*)/', $ticket, $m)) {
                $ticket = $m[1];
                $set = $m[2];
            }
            if (in_array($ticket, $ticketList) === false) {
                $ticketList[] = $ticket;
            }
            $setList[$set] = $ticket;
        }
        return $ticketList;
    } // getListOfTickets




    private function openDataSrcs()
    {
        $ticketList = $this->getListOfTickets();

        $tick = new Ticketing();
        foreach ($ticketList as $ticket) {
            $this->sets = $tick->consumeTicket($ticket);
            if (!is_array($this->sets)) {
                continue;
            }
            foreach ($this->sets as $setName => $set) {
                if (strpos($setName, 'set') !== 0) {
                    continue;
                }
                if ($set) {
                    $db = new DataStorage2([
                        'dataFile' => PATH_TO_APP_ROOT . $set['_dataSource'],
                        'logModifTimes' => true,
                    ]);
                    $this->sets[$setName]['_db'] = $db;

                    if (isset($set['_pollingTime']) && ($set['_pollingTime'] > 2)) {
                        $this->pollingTime = $set['_pollingTime'];
                    } else {
                        $this->pollingTime = DEFAULT_POLLING_TIME;
                    }
                }
            }
        }
    } // openDataSrcs





    private function awaitDataChange()
    {
        $till = time() + min($this->pollingTime,100); // s
        while (time() < $till) {
            $this->outData = [];
            // there may be multiple data sources, so loop over all of them:
            foreach ($this->sets as $setName => $set) {
                if (strpos($setName, 'set') !== 0) {
                    continue;
                }
                $lastDbModified = $set['_db']->lastDbModified();
                if (($this->lastUpdated < $lastDbModified)) {
                    $this->lastUpdated = $lastDbModified;
                    return true;
                }
                foreach ($set as $k => $elem) {
                    if ($k[0] === '_') {
                        continue;
                    }
                    $recId = $elem["dataSelector"];
                    $this->lastModif = $set['_db']->lastModifiedElement( $recId );
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
        $freezeFieldAfter = false;
        foreach ($this->sets as $setName => $set) {
            if (strpos($setName, 'freeze') === 0) {
                $freezeFieldAfter = $set;
                continue;
            }
            if (strpos($setName, 'set') !== 0) {
                continue;
            }
            $outRec = $this->getData( $set, $freezeFieldAfter );
            if (!$outData) {
                $outData = $outRec;
            } else {
                $outData['data'] = array_merge($outData['data'], $outRec['data']);
                $outData['locked'] = array_merge($outData['locked'], $outRec['locked']);
                if ($freezeFieldAfter) {
                    $outData['frozen'] = array_merge($outData['frozen'], $outRec['frozen']);
                }
            }
        }
        return $outData;
    } // assembleResponse




    public function getChangedData()
    {
        session_abort();
        $this->lastUpdated = isset($_POST['lastUpdated']) ? floatval($_POST['lastUpdated']) : false;
        $requestedDataSelector = isset($_POST['dynDataSel']) ? $_POST['dynDataSel'] : false;
        $this->requestedDataSelector = $requestedDataSelector;

        $dynDataSelector = [];
        if ($requestedDataSelector && preg_match('/(.*):(.*)/', $requestedDataSelector, $m)) {
            $dynDataSelector[ 'name' ] = $m[1];
            $dynDataSelector[ 'value' ] = $m[2];
        }
        $this->dynDataSelector = $dynDataSelector;

        $this->openDataSrcs();

        $dumpDB = isset($_GET['dumpDB']) ? $_GET['dumpDB'] : false;
        if ($dumpDB) {
            $this->dumpDB();
        }

        if ($this->lastUpdated == -1) { // means "skip initial update"
            $this->lastUpdated = microtime(true);
        }
        if (!$this->lastUpdated) {
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

        $returnData['lastUpdated'] = str_replace(',', '.', microtime(true) + 0.000001 );
        $json = json_encode($returnData);
        lzyExit($json);
    } // getChangedData




    private function getData( $set, $freezeFieldAfter = false )
    {
        if (!$set) {
            return [];
        }
        $db = $set['_db'];

        $dbIsLocked = $db->isDbLocked( false );
//        $lockedElements = [];
        $lockedElements = $db->getLockedRecords();
        $frozenElements = [];
        $tmp = [];

        // loop over fields in set:
        foreach ($set as $fldName => $fldRec) {
            if ($fldName[0] === '_') {    // skip meta elements
                continue;
            }

            $dataKey = $fldRec["dataSelector"];
            $targetSelector = $fldRec['targetSelector'];

            // field can contain wild-card - if so, render entire data array:
            if (strpos($dataKey, '*') !== false) {
//if (true) {
                $tmp = $this->getValueArray($set, $fldName);
                continue;

            } else {
                // in case client sent a dyn data selector: modify data- and target selectors accordingly:
                list($dataKey, $targetSelector) = $this->handleDynamicDataSelector($dataKey, $targetSelector);

                $elemIsLocked = false;
                if ($dbIsLocked || $db->isRecLocked( $dataKey )) {
//                    $lockedElements[] = $targetSelector;
                    $elemIsLocked = true;
                }
                if ($value = $db->readElement( $dataKey )) {
                    if ($freezeFieldAfter && $value) {
                        $lastModif = $db->lastModifiedElement($dataKey);
                        if ($lastModif < (time() - $freezeFieldAfter)) {
                            $frozenElements[] = $targetSelector;
                            if (!$elemIsLocked) {
                                $db->lockRec($dataKey, false, true);
                            }
                        }
                    }
                } else {
                    $value = '';
                }
                $tmp[$targetSelector] = $value;
            }
        }
        ksort($tmp);
        $outData['data'] = $tmp;


//        if (session_status() === PHP_SESSION_NONE) {
//            session_start();
//        }
//        if ($lockedElements !== $_SESSION['lizzy']['hasLockedElements']) {
//            $outData['locked'] = $lockedElements;
//            $_SESSION['lizzy']['hasLockedElements'] = $lockedElements;
//        }
        $outData['locked'] = $lockedElements;
        if ($frozenElements) {
            $outData['frozen'] = $frozenElements;
        }
//        session_abort();
        return $outData;
    } // getData



    public function getValueArray($set, $fldName)
    {
        $fldRec = $set[ $fldName ];
        $dataKey = $fldRec["dataSelector"];
        $targetSelector = $fldRec['targetSelector'];

        // in case client sent a dyn data selector: modify data- and target selectors accordingly:
        if ($this->dynDataSelector) {
            list($dataKey, $targetSelector) = $this->handleDynamicDataSelector($dataKey, $targetSelector);

            // try to retrieve requested record using compiled dataKey:
            $rec = $set['_db']->readRecord( $dataKey );
            if ($rec) {
                $c = 1;
                foreach ($rec as $k => $v) {
                    if (preg_match('/(.*?) \* (.*?)/x', $targetSelector, $m)) {
                        $targSel = "{$m[1]}$c{$m[2]}";
                        $tmp[$targSel] = $v;
                        $c++;
                    } else {
                        die("Error in targetSelector '$targetSelector' -> '*' missing");
                    }
                }
                return $tmp;
            } else {
                return [];
            }
        }

        $outData = [];
        $data = $set['_db']->read();
//        $dataChanged = $set['_db']->readModified( $this->lastUpdated );
        $r = 0;
        if (preg_match('/^ (.*?) \* (.*?) \* (.*?) $/x', $targetSelector, $m)) {
            list($dummy, $s1, $s2, $s3) = $m;
        } elseif (preg_match('/^ (.*?) \* (.*?) $/x', $targetSelector, $m)) {
            list($dummy, $s1, $s2) = $m;
            $s3 = '';
        } else {
            die("Error in targetSelector '$targetSelector' -> '*' missing");
        }

        foreach ($data as $key => $rec) {
            if (is_array($rec)) {
                $c = 0;
                foreach ($rec as $k => $v) {
//                    if (isset($dataChanged[$key][$k])) {
if (true) {
                        $targSel = "$s1$r$s2$c$s3";
                        $targSel = str_replace(['&#34;', '&#39;'], ['"', "'"], $targSel);
                        $outData[$targSel] = $v;
                        $c++;
                    }
                }

            } else {
                if (isset($dataChanged[$key])) {
                    $targSel = "$s1$r$s2";
                    $targSel = str_replace(['&#34;', '&#39;'], ['"', "'"], $targSel);
                    $outData[$targSel] = $rec;
                }
            }
            $r++;
        }

        return $outData;
    } // getValueArray



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
            $returnData['lastUpdated'] = str_replace(',', '.', microtime(true) + 0.000001);
            $json = json_encode($returnData);
            lzyExit($json);
        }
        session_abort();
    } // checkAbort



    private function dumpDB()
    {
        $out = "My SessionID: ".session_id()."\n";
        foreach ($this->sets as $setName => $set) {
            $db = $set['_db'];
            $s = $db->dumpDb( true, false );
            $out .= $s;
        }
        $returnData['result'] = 'info';
        $returnData['data'] = base64_encode($out);
        $json = json_encode($returnData);
        lzyExit($json);
    } // dumpDB




    private function handleDynamicDataSelector($dataKey, $targetSelector): array
    {
        if ($this->dynDataSelector) {
            // check for '{r}' pattern in dataKey and replace it value in client request:
            if (strpos($dataKey, '{') !== false) {
                $dataKey = preg_replace('/\{' . $this->dynDataSelector['name'] . '\}/', $this->dynDataSelector['value'], $dataKey);
            }

            // check for '{r}' pattern in targetSelector and replace it value in client request:
            if (strpos($targetSelector, '{') !== false) {
                $targetSelector = preg_replace('/\{' . $this->dynDataSelector['name'] . '\}/', $this->dynDataSelector['value'], $targetSelector);
            }
        }
        return array($dataKey, $targetSelector);
    } // handleDynamicDataSelector

} // LiveDataService
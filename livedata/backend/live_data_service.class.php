<?php

class LiveDataService
{
    public function __construct()
    {
        session_start();
        $this->session = isset($_SESSION['lizzy']) ? $_SESSION['lizzy'] : [];
        $timezone = isset($session['systemTimeZone']) && $this->session['systemTimeZone'] ? $this->session['systemTimeZone'] : 'CET';
        date_default_timezone_set($timezone);

        $this->lastModif = 0;
        $this->lastUpdate = 0;
        $this->pollingTime = DEFAULT_POLLING_TIME;
        session_abort();
    } // __construct




    public function execute()
    {
        $this->lastUpdate = isset($_POST['last']) ? $_POST['last'] : false;
        $requestedDataSelector = isset($_GET['dynDataSel']) ? $_GET['dynDataSel'] : false;
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

        if ($this->lastUpdate == -1) { // means "skip initial update"
            $this->lastUpdated = microtime(true) - 0.1;
        }
        if (!$this->lastUpdate) {
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
    } // execute




    private function getListOfTickets()
    {
        if (!isset($_POST['ref'])) {
            lzyExit('Error: "ref" missing in call to _live_data_service.php');
        }
        $ref = $_POST['ref'];
        $this->lastUpdated = floatval($_POST['last']);

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
                    $this->lastModif = $set['_db']->lastModifiedElement( $k );
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




    private function getData( $set, $freezeFieldAfter = false )
    {
        if (!$set) {
            return [];
        }
        $db = $set['_db'];

        $dbIsLocked = $db->isDbLocked( false );
        $lockedElements = [];
        $frozenElements = [];
        $tmp = [];
        foreach ($set as $k => $elem) {
            if ($k[0] === '_') {    // skip meta elements
                continue;
            }
            $dataKey = $elem["dataSelector"];
            $targetSelector = $elem['targetSelector'];

            if (strpos($dataKey, '*') !== false) {
                $tmp = $this->getValueArray($set, $k);
                continue;

            } else {
                if ($this->dynDataSelector) {
                    if (strpos($dataKey, '{') !== false) {
                        $dataKey = preg_replace('/\{' . $this->dynDataSelector['name'] . '\}/', $this->dynDataSelector['value'], $dataKey);
                    }

                    if (strpos($targetSelector, '{') !== false) {
                        $targetSelector = preg_replace('/\{' . $this->dynDataSelector['name'] . '\}/', $this->dynDataSelector['value'], $targetSelector);
                    }
                }
                $elemIsLocked = false;
                if ($dbIsLocked || $db->isRecLocked( $dataKey )) {
                    $lockedElements[] = $targetSelector;
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


        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($lockedElements !== $_SESSION['lizzy']['hasLockedElements']) {
            $outData['locked'] = $lockedElements;
            $_SESSION['lizzy']['hasLockedElements'] = $lockedElements;
        }
        if ($frozenElements) {
            $outData['frozen'] = $frozenElements;
        }
        session_abort();
        return $outData;
    } // getData



    public function getValueArray($set, $k)
    {
        $elem = $set[ $k ];
        $dataKey = $elem["dataSelector"];
        $targetSelector = $elem['targetSelector'];

        // dynDataSelector: select a data element upon client request:
        if ($this->dynDataSelector) {
            // check for '{r}' pattern in dataKey and replace it value in client request:
            if (strpos($dataKey, '{') !== false) {
                $dataKey = preg_replace('/{' . $this->dynDataSelector['name'] . '}/', $this->dynDataSelector['value'], $dataKey);
            }

            // check for '{r}' pattern in targetSelector and replace it value in client request:
            if (strpos($this->targetSelector, '{') !== false) {
                $targetSelector = preg_replace('/{' . $this->dynDataSelector['name'] . '}/', $this->dynDataSelector['value'], $targetSelector);
            }

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
        $dataChanged = $set['_db']->readModified( $this->lastUpdate );
        $r = 0;
        if (preg_match('/^ (.*?) \* (.*?) \* (.*?) $/x', $targetSelector, $m)) {
            list($s, $s1, $s2, $s3) = $m;
        } elseif (preg_match('/^ (.*?) \* (.*?) $/x', $targetSelector, $m)) {
            list($s, $s1, $s2) = $m;
            $s3 = '';
        } else {
            die("Error in targetSelector '$targetSelector' -> '*' missing");
        }

        foreach ($data as $key => $rec) {
            if (is_array($rec)) {
                $c = 0;
                foreach ($rec as $k => $v) {
                    if (isset($dataChanged[$key][$k])) {
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

} // LiveDataService
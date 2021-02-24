<?php

class LiveDataService
{
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




    private function getLiveElements()
    {
        if (!isset($_POST['ref'])) {
            lzyExit('Error: "ref" missing in call to _live_data_service.php');
        }
        $ref = $_POST['ref'];
        $this->liveElements = json_decode($ref);
        return $this->liveElements;
    } // getLiveElements




    private function openDataSrcs()
    {
        $this->sets = [];
        $elementRefs = $this->getLiveElements();
        foreach ($elementRefs as $i => $ref) {
            if ($ref->srcRef) {
                $setName = preg_replace('/.*?:/', '', $ref->srcRef);;
            } else {
                $setName = 'set1';
            }
            $this->sets[ $setName ][] = $ref;
        }

        $rec0 = reset($elementRefs);
        $tickHash = preg_replace('/:.*/', '', $rec0->srcRef);
        $tick = new Ticketing();
        $ticketRec = $tick->consumeTicket($tickHash);

        foreach ($ticketRec as $setName => $set) {
            if (isset($set['_pollingTime']) && ($set['_pollingTime'] > 2)) {
                $this->pollingTime = $set['_pollingTime'];
            }

            $db = new DataStorage2([
                'dataFile' => PATH_TO_APP_ROOT . $set['_dataSource'],
                'logModifTimes' => true,
            ]);
            $this->sets[$setName]['_db'] = $db;
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
                    $recId = $elem->dataRef;
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
        $lockedElements = [];
        $frozenElements = [];
        $tmp = [];

        // loop over fields in set:
        foreach ($set as $i => $rec) {
            if (is_string($i) && ($i[0] === '_')) {    // skip meta elements
                continue;
            }

            $dataKey = $rec->dataRef;

            // field can contain wild-card - if so, render entire data array:
            if (strpos($dataKey, '*') !== false) {
                $tmp = $this->getValueArray($set, $dataKey);
                continue;

            } else {
                $elemIsLocked = false;
                if ($dbIsLocked || $db->isRecLocked( $dataKey )) {
                    $lockedElements[] = "[data-ref='$dataKey']";
                    $elemIsLocked = true;
                }
                if ($value = $db->readElement( $dataKey )) {

                    // check freeze:
                    if ($freezeFieldAfter && $value) {
                        $lastModif = $db->lastModifiedElement($dataKey);
                        if ($lastModif < (time() - $freezeFieldAfter)) {
                            $frozenElements[] = "[data-ref='$dataKey']";
                            if (!$elemIsLocked) {
                                $db->lockRec($dataKey, false, true);
                            }
                        }
                    }
                } else {
                    $value = '';
                }
                if (isset($rec->md) && $rec->md) {
                    $value = compileMarkdownStr( $value );
                }
                if (isset( $rec->id )) {
                    $tmp[ $rec->id ] = $value;
                } else {
                    $tmp["[data-ref='$dataKey']"] = $value;
                }
            }
        }
        ksort($tmp);
        $outData['data'] = $tmp;

        $outData['locked'] = $lockedElements;
        if ($frozenElements) {
            $outData['frozen'] = $frozenElements;
        }
        return $outData;
    } // getData



    public function getValueArray($set, $dataKey)
    {

        // $targetSelector = '.lzy-row-* .lzy-col-*'; // for testing
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
            exit('abort');
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
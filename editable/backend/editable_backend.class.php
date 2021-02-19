<?php

require_once '../../livedata/backend/live_data_service.class.php';


class EditableBackend extends LiveDataService
{
	public function __construct()
	{
		if (sizeof($_POST) < 1) {
			lzyExit('Hello, this is '.basename(__FILE__));
		}

        parent::__construct();

        if (!isset($_SESSION['lizzy']['userAgent'])) {
            mylog("*** Fishy request from {$_SERVER['HTTP_USER_AGENT']} (no valid session)");
            $this->sendResponse( false,'restart');
        }

		$this->sessionId = session_id();
        $this->clientID = substr($this->sessionId, 0, 4);
		if (!isset($_SESSION['lizzy']['lastUpdated'])) {
			$_SESSION['lizzy']['lastUpdated'] = 0;
		}
        if (!isset($_SESSION['lizzy']['pagePath'])) {
		    die('Fatal Error: $_SESSION[\'lizzy\'][\'pagePath\'] not defined');
        }

        $this->sets = false;
        $this->user = '';
        if (isset($_SESSION['lizzy']['userDisplayName']) && $_SESSION['lizzy']['userDisplayName']) {    // get user name for logging
            $this->user = '['.$_SESSION['lizzy']['userDisplayName'].']';

        } elseif (isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']) {
            $this->user = '['.$_SESSION['lizzy']['user'].']';

        }

        if (!isset($_SESSION['lizzy']['hash'])) {
            $_SESSION['lizzy']['hash'] = '#';
        }
        $this->hash = $_SESSION['lizzy']['hash'];
        $this->pageName = isset($_SESSION['lizzy']['pageName']) ? $_SESSION['lizzy']['pagePath']: false;
        $this->pagePath = $_SESSION["lizzy"]["pageFolder"];

        preparePath(DATA_PATH);

		$this->remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
		$this->userAgent = isset($_SESSION['lizzy']['userAgent']) ? $_SESSION['lizzy']['userAgent'] : $_SERVER["HTTP_USER_AGENT"];
		$this->isLocalhost = (($this->remoteAddress == 'localhost') || (strpos($this->remoteAddress, '192.') === 0) || ($this->remoteAddress == '::1'));

        session_write_close();

    } // __construct



	public function execute()
	{
	    $cmd = $this->init();
        if ($cmd === 'lock') {
            $this->lock();
        }

        if ($cmd === 'unlock') {
            $this->unlock();
        }

        if ($cmd === 'save') {
            $this->save();
        }
	} // handleUrlArguments



    private function init()
    {
        $this->id = $this->getRequestData('id');
        if (!$this->id) {
            mylog("### lock: Error -> id not defined");
            $this->sendResponse( false,"failed#lock (id not defined)");
        }
        $this->dataRef = $this->getRequestData('dataRef');

        if (!$this->openDB()) {
            $cmd = str_replace(['ds', '_', 'srcRef'], '', implode('', array_keys($_GET)));
            mylog("### openDB -> failed: $cmd");
            $this->sendResponse( false, "failed#openDB:$cmd");
        }

        if (!isset($_POST['cmd'])) { // cmd missing:
            lzyExit('Hello, this is '.basename(__FILE__));
        }
        return $this->getRequestData('cmd');
    } // init



	private function lock() {
	    $id = $this->id;

        $dataKey = $this->dataRef;
        $db = $this->set['_db'];
        if ($this->set['_freezeFieldAfter']) {
            $feezeFieldAfter = intval($this->set['_freezeFieldAfter']);
            $val = $db->readElement( $dataKey );
            if ($val) {
                $lastModif = $db->lastModifiedElement($dataKey);
                if ($lastModif < (time() - $feezeFieldAfter)) {
                    mylog("### lock: '$dataKey' -> failed:frozen");
                    $this->sendResponse(['id' => $id], "failed#lockFrozen '$dataKey'");
                }
            }
        }
        $res = $db->lockRec( $dataKey );
        if (!$res) {
			mylog("### lock: '$dataKey' -> failed");
            $this->sendResponse(['id' => $id], "failed#lock '$dataKey'");

		} else {
            mylog("lock: $dataKey -> ok");
            $this->sendResponse( $dataKey, 'ok#lock');
		}
	} // lock



	private function unlock() {
        $set = $this->set;
        $dataKey = $this->dataRef? $this->dataRef: '*';
        $db = $set['_db'];
        $res = $db->unlockRec($dataKey);
        if ($res) {
			mylog("unlock: $dataKey -> ok");
            $this->sendResponse( $dataKey, 'ok#unlock');
        }
        mylog("### unlock: $dataKey -> failed: '$dataKey'");
        $this->sendResponse( false, "failed#unlock '$dataKey'");
	} // unlock



	private function save() {
        $id = $this->id;
        if (!$id) {
			mylog("### save & unlock: Error -> id not defined");
            $this->sendResponse( false,"failed#save (id not defined)");
		}

		$text = $this->getRequestData('text');
		$text = urldecode($text);
		if ($text === 'undefined') {
            mylog("### save: $id -> failed (text not defined)");
            $this->sendResponse( false,"failed#save (text not defined)");
        }

        $dataKey = $this->dataRef;
        $set = $this->set;
        $db = $set['_db'];

        if (!checkPermission( $set['_editableBy'] )) {
            mylog("### save: $id -> failed (no permission)");
            $this->sendResponse( false,"failed#save (no permission)");
        }

        if (strpos($dataKey, '#') !== false) {
            $oldRecKey = $this->getRequestData('recKey');
            if ($oldRecKey) {
                $newRecKey = $text;
                $rec = $db->readRecord( $oldRecKey );
                if ($rec) {
                    $db->deleteRecord( $oldRecKey );
                    $rec['_key'] = $newRecKey;
                    $res = $db->writeRecord($newRecKey, $rec);
                    if (!$res) {
                        mylog("### save failed!: $id -> [$text]");
                        $this->sendResponse( false,"failed#save under new key");
                    }
                }
            }

        } else {
            $res = $db->writeElement($dataKey, $text, true, true, true);
            if (!$res) {
                mylog("### save failed!: $id -> [$text]");
                $this->sendResponse( false,"failed#save (locked)");
            }
            $db->unlockRec($dataKey, true); // unlock all owner's locks
        }

        mylog("save: $id => '$text' -> ok");
        $this->sendResponse( $dataKey, 'ok#save');
	} // save



    private function sendResponse( $what, $result = null )
    {
        $outData = [];
        if ( $what && is_string($what) ) {
            if ($what !== '*') {
                $outData = $this->assembleResponse($what);
            }
        } elseif (is_array($what)) {
            $outData = $what;
        }
        if ($result !== null) {
            $outData['result'] = $result;
        } elseif (!isset($outData['result'])) {
            $outData['result'] = 'ok';
        }
        $json = json_encode( $outData );
        lzyExit( $json );
    } // sendResponse



    private function assembleResponse( $what = false )
    {
        $outData = [];
        foreach ($this->sets as $set) {
            $outRec = $this->getData($set, $what);
            if (!$outData) {
                $outData = $outRec;
            } else {
                $outData['data'] = array_merge($outData['data'], $outRec['data']);
                $outData['locked'] = array_merge($outData['locked'], $outRec['locked']);
            }
        }
        return $outData;
    } // assembleResponse



    private function getData( $set, $what = false )
    {
        $setDb = $set['_db'];
        $dbIsLocked = $setDb->isDbLocked( false );
        $lockedElements = [];
        $tmp = [];

        if ($what) {
            $value = $setDb->readElement( $what );
            if ($value !== null) {
                $tmp[ "[data-ref='$what']" ] = $value;
            }

        } else {
            $data = $setDb->read();
            foreach ($set as $key => $dataKey) {
                if ($key[0] === '_') {
                    continue;
                }

                if (strpos($dataKey, '*,*') !== false) {
                    if ($data) {
                        $r = -1;
                        foreach ($data as $rec) {
                            $r++;
                            // at this point we address data by position, not labels -> remove elem-labels:
                            $keys = array_keys($rec);
                            $rec = array_values($rec);
                            foreach ($rec as $c => $value) {
                                if ($keys[$c][0] === '_') {
                                    continue;
                                }
                                if (preg_match('/(.*? ) \* (.*? ) \* (.* )/x', $dataKey, $m)) {
                                    $tSel = $m[1] . ($r) . $m[2] . ($c) . $m[3];
                                }
                                $tmp[ "[data-ref='$tSel']" ] = $value;
                            }
                        }
                    } else {
                        $tmp = [];
                    }
                    continue;

                } elseif (strpos($dataKey, '*') !== false) {
                    if ($data) {
                        foreach ($data as $i => $value) {
                            if (is_int($i)) {
                                $tSel = preg_replace('/\*/', $i + 1, $dataKey);
                            } else {
                                $tSel = $i;
                            }
                            $tmp[ "[data-ref='$tSel']" ] = $value;
                        }
                    } else {
                        $tmp = [];
                    }
                    continue;

                } else {
                    if ($dbIsLocked || $setDb->isRecLocked($dataKey)) {
                        $lockedElements[] = "[data-ref='$dataKey']";
                    }
                    if (isset($data[$dataKey])) {
                        $value = $data[$dataKey];
                    } else {
                        $value = '';
                    }
                    $tmp[$dataKey] = $value;
                }
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




    private function openDB( $getValues = false ) {
	    if ($this->sets) {
	        return true;
        }

	    $useRecycleBin = false;
        $srcRef = $this->getRequestData('srcRef');
        if (!$srcRef || !preg_match('/^[A-Z][A-Z0-9]{4,20}/', $srcRef)) {   // dataRef missing
            return false;
        }
        if (preg_match('/^([A-Z0-9]{4,20})\:(.+)$/', $srcRef, $m)) {
            $srcRef = $m[1];
            $this->setInx = $m[2];
        } else {
            $this->setInx = false;
            die('failed: set-Index missing');
        }
        require_once SYSTEM_PATH . 'ticketing.class.php';
        $tick = new Ticketing();
        $ticketRec = $tick->consumeTicket($srcRef);
        $this->ticketRec = $ticketRec;

        // loop over sets:
        foreach ($ticketRec as $setInx => $set) {
            if (strpos($setInx, 'set') === false) {
                continue;
            }
            $this->sets[$setInx] = $set;

            // open DB:
            $useRecycleBin1 = $useRecycleBin || @$set['_useRecycleBin'];
            $this->sets[$setInx]['_db'] = new DataStorage2([
                'dataFile' => PATH_TO_APP_ROOT . $set['_dataSource'],
                'sid' => $this->sessionId,
                'lockDB' => false,
                'useRecycleBin' => $useRecycleBin1,
                'lockTimeout' => false,
                'logModifTimes' => true,
            ]);
        }
        $this->set = &$this->sets[ $this->setInx ];
        if (!in_array($this->dataRef, array_values($this->set))) {
            mylog("### openDB -> failed: dataRef '$this->dataRef' unknown");
            $this->sendResponse( false, "failed#openDB: dataRef unknown");
        }
        return true;
    } // openDB



	private function getRequestData($varName) {
		global $argv;

		$out = null;
		if (isset($_GET[$varName])) {
			$out = safeStr($_GET[$varName]);

		} elseif (isset($_POST[$varName])) {
			$out = $_POST[$varName];

		} elseif ($this->isLocalhost && isset($argv)) {	// for local debugging
			foreach ($argv as $s) {
				if (strpos($s, $varName) === 0) {
					$out = preg_replace('/^(\w+=)/', '', $s);
					break;
				}
			}
		}
		if ($out === 'undefined') {
            lzyExit( json_encode( ['result' => "Error: arg $varName undefined."] ) );
        }
		return $out;
	} // getRequestData

} // EditingService


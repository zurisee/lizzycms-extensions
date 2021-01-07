<?php

require_once '../../livedata/backend/live_data_service.class.php';


class EditableBackend extends LiveDataService
{
    private $dynDataSelector = null;

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
        if (!$this->openDB()) {
            $cmd = str_replace(['ds', '_', 'srcRef'], '', implode('', array_keys($_GET)));
            mylog("### openDB -> failed: $cmd");
            $this->sendResponse( false, "failed#openDB:$cmd");
        }

        $cmd = isset($_POST['cmd']) ? $_POST['cmd'] : false;
        $cmd = ",$cmd";
        $this->id = isset($_POST['id']) ? $_POST['id'] : false;
        $this->elemRef = isset($_POST['elemRef']) ? $_POST['elemRef'] : false;

        if (strpos($cmd, ',get') !== false) {     // get value(s)
            $this->get();
        }

        if (strpos($cmd, ',lock') !== false) {    // lock an editable field
            $this->lock();
        }

        if (strpos($cmd, ',unlock') !== false) {    // unlock an editable field
            $this->unlock();
        }

        if (strpos($cmd, ',save') !== false) {    // save data & unlock
            $this->save();
        }

        $this->handleGenericRequests( $cmd );     // log, info
    } // handleUrlArguments



	private function lock() {
	    $id = $this->id;
		if (!$id) {
			mylog("### lock: Error -> id not defined");
            $this->sendResponse( false,"failed#lock (id not defined)");
		}

		$set = $this->getSet( $id );
        $dataKey = $this->getDataSelector( $id );
        $db = $set['_db'];
        if ($set['_freezeFieldAfter']) {
            $feezeFieldAfter = intval($set['_freezeFieldAfter']);
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
            $this->sendResponse( $id, 'ok#lock');
		}
	} // lock



	private function unlock() {
        $id = $this->id;
        if (!$id) {
			mylog("### unlock: Error -> id not defined");
            $this->sendResponse( false,"failed#unlock (id not defined)");
		}
        $set = $this->getSet( $id );
        $dataKey = $this->getDataSelector( $id );
        $db = $set['_db'];
        $res = $db->unlockRec($dataKey);
        if ($res) {
			mylog("unlock: $dataKey -> ok");
            $this->sendResponse( $id, 'ok#unlock');
        }
        mylog("### unlock: $dataKey -> failed: '$dataKey'");
        $this->sendResponse( false, "failed#unlock '$dataKey'");
	} // unlock



	private function get() {
        mylog("get: $this->id -> ok");
        $this->sendResponse($this->id,'ok#get');
	} // get



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

        $dataKey = $this->getDataSelector( $id );
        $set = $this->getSet( $id );
        $db = $set['_db'];

        if (!checkPermission( $set['_editableBy'] )) {
            mylog("### save: $id -> failed (no permission)");
            $this->sendResponse( false,"failed#save (no permission)");
        }

        if (strpos($dataKey, '#') !== false) {
            $oldRecKey = @$_POST['recKey'];
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
        $this->sendResponse( $id, 'ok#save');
	} // save



    private function sendResponse( $sendData, $result = null )
    {
        $outData = [];
        if ( $sendData ) {
            $outData = $this->assembleResponse( $sendData );
        }
        if ($result !== null) {
            $outData['result'] = $result;
        } elseif (!isset($outData['result'])) {
            $outData['result'] = 'ok';
        }
        $json = json_encode( $outData );
        lzyExit( $json );
    } // sendResponse



    private function assembleResponse( $id = false )
    {
        $outData = [];
        if ($id && ($id[0] !== '#') && ($id[0] !== '.')) {
            $id = "#$id";
        }
        foreach ($this->sets as $setName => $set) {
            $outRec = $this->getData($setName);
            if ($id === true) {
                if (!$outData) {
                    $outData = $outRec;
                } else {
                    $outData['data'] = array_merge($outData['data'], $outRec['data']);
                    $outData['locked'] = array_merge($outData['locked'], $outRec['locked']);
                }

            } elseif ($this->elemRef && ($tSel = $this->getTSel( $this->elemRef ))) {
                $outData['data'][$tSel] = $outRec['data'][$tSel];
                return $outData;

            } else {
                foreach ($outRec['data'] as $key => $val) {
                    if ($key === $id) {
                        $outData['data'][$key] = $val;
                        if (isset($outData['locked'][$key])) {
                            $outData['locked'][$key] = true;
                        }
                        break 2;
                    }
                }
            }
        }
        return $outData;
    } // assembleResponse




    private function getTSel( $dSel )
    {
        foreach ($this->editableElements as $tSel => $rec) {
            if ($rec[0] === $dSel) {
                return $tSel;
            }
        }
        return false;
    } // getTSel




    private function getData( $setName )
    {
        $setDb = $this->sets[ $setName ]['_db'];
        $dbIsLocked = $setDb->isDbLocked( false );
        $lockedElements = [];
        $tmp = [];
        foreach ($this->sets[ $setName ] as $targetSelector => $dataKey) {
            if ($targetSelector[0] === '_') {
                continue;
            }
            $data = $setDb->read();
            if (strpos($dataKey, '*,*') !== false) {
                if ($data) {
                    $r = -1 ;
                    foreach ($data as $rec) {
                        $r++;
                        // at this point we address data by position, not labels -> remove elem-labels:
                        $rec = array_values($rec);
                        foreach ($rec as $c => $value) {
                            if (preg_match('/(.*? ) \* (.*? ) \* (.*? )/x', $targetSelector, $m)) {
                                $tSel = $m[1] . ($r+1) . $m[2] . ($c+1) . $m[3];
                            }
                            $tmp[ $tSel ] = $value;
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
                            $tSel = preg_replace('/\*/', $i + 1, $targetSelector);
                        } else {
                            $tSel = $i;
                        }
                        $tmp[ $tSel ] = $value;
                    }
                } else {
                    $tmp = [];
                }
                continue;

            } else {
//ToDo: dynDataSelector for editable
//                if ($this->dynDataSelector) {
//                    if (strpos($dataKey, '{') !== false) {
//                        $dataKey = preg_replace('/{' . $this->dynDataSelector['name'] . '}/', $this->dynDataSelector['value'], $dataKey);
//                    }
//
//                    if (strpos($targetSelector, '{') !== false) {
//                        $targetSelector = preg_replace('/{' . $this->dynDataSelector['name'] . '}/', $this->dynDataSelector['value'], $targetSelector);
//                    }
//                }

                if ($dbIsLocked || $setDb->isRecLocked( $dataKey )) {
                    $lockedElements[] = $targetSelector;
                }
                if (isset($data[ $dataKey ])) {
                    $value = $data[ $dataKey ];
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
        session_abort();
        return $outData;
    } // getData



    private function getSet( $id )
    {
        if ($id && ($id[0] !== '#') && ($id[0] !== '.')) {
            $id = "#$id";
        }
        if (isset($this->editableElements[$id])) {
            return $this->sets[ $this->editableElements[$id][1] ];

        } else {
            foreach ($this->editableElements as $k => $rec) {
                if ( $this->elemRef && ($rec[0] === $this->elemRef) ) {
                    return $this->sets[ $rec[1] ];
                }

                if (strpos($k,'*') !== false) {
                    return $this->sets[ $this->editableElements[$k][1] ];
                }
            }
        }
        $this->sendResponse( false,"Error: unidentified ID '$id' in getSet()");
    } // getSet



    private function getDataSelector( $id )
    {
        if ($id && ($id[0] !== '#') && ($id[0] !== '.')) {
            $id = "#$id";
        }
        if (isset($this->editableElements[$id])) {
            return $this->editableElements[$id][0];
        } else {
            if (isset($_POST['elemRef'])) {
                return $_POST['elemRef'];

            } else {
                $targs = array_keys($this->editableElements);
                foreach ($targs as $targ) {
                    if (preg_match('/(.*?) \* (.*?) \* (.*?)/x', $targ, $m)) {
                        if (preg_match('/lzy-elem-(\d+)-(\d+)/', $id, $mm)) {
                            $row = intval($mm[1]);
                            $col = intval($mm[2]);
                            return "$row,$col";
                        }

                    } elseif (preg_match('/(.*?) \* (.*?)/x', $targ, $m)) {
                        if (preg_match('/lzy-elem-\d+-(\d+)/', $id, $mm)) {
                            $col = intval($mm[1]) - 1;
                            return $col;
                        }
                    }
                }
            }
        }
        $this->sendResponse( false,"Error: unidentified ID '$id' in getDataSelector()");
        return false;
    } // getDataSelector



    private function openDB() {
	    if ($this->sets) {
	        return true;
        }

	    $useRecycleBin = false;
        $srcRef = $this->getRequestData('srcRef');
        if (!$srcRef || !preg_match('/^[A-Z][A-Z0-9]{4,20}/', $srcRef)) {   // elemRef missing
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

            // open DB:
            $useRecycleBin1 = $useRecycleBin || @$set['_useRecycleBin'];
            $this->sets[$setInx]['_db'] = new DataStorage2([
                'dataFile' => PATH_TO_APP_ROOT.$set['_dataSource'],
                'sid' => $this->sessionId,
                'lockDB' => false,
                'useRecycleBin' => $useRecycleBin1,
                'lockTimeout' => false,
                'logModifTimes' => true,
            ]);
            $this->sets[$setInx]['_editableBy'] = @$set['_editableBy'];
            $this->sets[$setInx]['_freezeFieldAfter'] = @$set['_freezeFieldAfter'];

            // loop over fields in set, extract editableElements:
            for ($i=1; $i<99; $i++) {
                $recInx = "fld$i";
                if (isset($set[$recInx])) {
                    $rec = $set[$recInx];
                    $this->sets[$setInx]['_tickRec'] = $rec;
                    $tSel = $rec['targetSelector'];
                    $this->sets[$setInx][ $tSel ] = $rec['dataSelector'];
                    $this->editableElements[ $tSel ][0] = $rec['dataSelector'];
                    $this->editableElements[ $tSel ][1] = $setInx;
                } else {
                    break;
                }
            }
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



    private function handleGenericRequests( $cmd )
    {
        if (strpos($cmd, ',log') !== false) {                                // remote log, write to backend's log
            $msg = $this->getRequestData('text');
            mylog("Client: $msg");
            $this->sendResponse( false,'ok');
        }

        if (strpos($cmd, ',info') !== false) {    // respond with info-msg
            $this->info();
        }
    } // handleGenericRequests



    private function info()
    {
        $localhost = ($this->isLocalhost) ? 'yes':'no';
        $dbs = var_r($_SESSION['lizzy']['db']);
        $msg = <<<EOT
	<pre>
	Page:		{$_SESSION['lizzy']['pagePath']}
	DB:		$dbs
	Hash:		{$this->hash}
	Remote Addr:	{$this->remoteAddress}
	UA:		{$this->userAgent}
	isLocalhost:	{$localhost}
	ClientID:	{$this->clientID}
	</pre>
EOT;
        $this->sendResponse(['text' => $msg], 'ok');
    } // info

} // EditingService


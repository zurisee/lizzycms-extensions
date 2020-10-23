<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Ajax Service for dynamic data, in particular 'editable' data
 * http://localhost/Lizzy/_lizzy/_ajax_server.php?lock=ed1
 *
 *  Protocol:

conn
	GET:	?conn=list-of-editable-fields

upd
	GET:	?upd=time

lock
	GET:	?lock=id

unlock
	GET:	?unlock=id

save
	GET:	?save=id
    POST:   text => data

get
	GET:	?get=id

reset
	GET:	?reset

log
	GET:	?log=message

info
	GET:	?info

getfile
	GET:	?getfile=filename

*/


class EditableBackend
{
	public function __construct()
	{
        $this->terminatePolling = false;
		if (sizeof($_GET) < 1) {
			lzyExit('Hello, this is '.basename(__FILE__));
		}
        if (!session_start()) {
            mylog("ERROR in __construct(): failed to start session");
        }
        $this->clear_duplicate_cookies();

        $timezone = isset($_SESSION['lizzy']['systemTimeZone']) ? $_SESSION['lizzy']['systemTimeZone'] : 'CET';
        date_default_timezone_set($timezone);


        if (!isset($_SESSION['lizzy']['userAgent'])) {
            mylog("*** Fishy request from {$_SERVER['HTTP_USER_AGENT']} (no valid session)");
            lzyExit('restart');
        }

		$this->sessionId = session_id();
        $this->clientID = substr($this->sessionId, 0, 4);
		if (!isset($_SESSION['lizzy']['hash'])) {
			$_SESSION['lizzy']['hash'] = '#';
		}
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

		$this->hash = $_SESSION['lizzy']['hash'];
		session_write_close();
        preparePath(DATA_PATH);

		$this->remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
		$this->userAgent = isset($_SESSION['lizzy']['userAgent']) ? $_SESSION['lizzy']['userAgent'] : $_SERVER["HTTP_USER_AGENT"];
		$this->isLocalhost = (($this->remoteAddress == 'localhost') || (strpos($this->remoteAddress, '192.') === 0) || ($this->remoteAddress == '::1'));
//		$this->handleUrlArguments();
		$this->config = [];
	} // __construct



	//---------------------------------------------------------------------------
//	private function handleUrlArguments()
	public function execute()
	{
        $this->handleEditableRequests();    // Editable: conn, get, reset, lock, unlock, save

        $this->handleGenericRequests();     // log, info

    } // handleUrlArguments



	//---------------------------------------------------------------------------
	private function info()
	{
		$localhost = ($this->isLocalhost) ? 'yes':'no';
		$dbs = $this->var_r($_SESSION['lizzy']['db']);
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
		lzyExit($msg);
	} // info



	//---------------------------------------------------------------------------
	private function initConnection() {
        if (!session_start()) {
            mylog("ERROR in initConnection(): failed to start session");
        }
        $this->clear_duplicate_cookies();		if (!isset($_SESSION['lizzy']['pageName']) || !isset($_SESSION['lizzy']['pagePath'])) {
            mylog("*** Client connection failed: [{$this->remoteAddress}] {$this->userAgent}");
            lzyExit('failed#conn');
        }

        $pagePath = $_SESSION["lizzy"]["pageFolder"];
        mylog("Client connected: [{$this->remoteAddress}] {$this->userAgent} (pagePath: $pagePath)");

		$_SESSION['lizzy']['lastSentData'] = '';
		session_write_close();

		lzyExit('ok#conn');
	} // initConnection




	//---------------------------------------------------------------------------
	private function lock($id) {
		if (!$id) {
			mylog("lock: Error -> id not defined");
			lzyExit();
		}

        $db = $this->sets[ $this->setInx ];
        $res = $db->lockRec($id);
        if (!$res) {
			mylog("lock: $id -> failed");
			lzyExit('failed#lock');

		} else {
            lzyExit($this->prepareClientData($id).'#lock:ok');
		}
	} // lock




	//---------------------------------------------------------------------------
	private function unlock($id) {
		if (!$id) {
			mylog("unlock: Error -> id not defined");
			lzyExit();
		}
        $db = $this->sets[ $this->setInx ];
        $res = $db->unlockRec($id);
        if ($res) {
			mylog("unlock: $id -> ok");
            lzyExit($this->prepareClientData($id).'#unlock:ok');
        }
        lzyExit('failed#unlock');
	} // unlock




	//---------------------------------------------------------------------------
	private function get($id) {
		lzyExit($this->prepareClientData($id).'#get');
	} // get




	//---------------------------------------------------------------------------
	private function save($id) {
		if (!$id) {
			mylog("save & unlock: Error -> id not defined");
            lzyExit('failed#save');
		}
		$text = $this->get_request_data('text');
		if ($text === 'undefined') {
            lzyExit('failed#save');
        }
		$id1 = "#$id";
		foreach ($this->ticketRec as $setInx => $set) {
		    if (strpos($setInx, 'set') !== 0) {
		        continue;
            }
		    foreach ($set as $k => $rec) {
		        if ($id1 === $rec['targetSelector']) {
                    break 2;
                }
            }
        }

//        $ref = $this->getRef();
//        mylog("save: {$id}[$ref] -> [$text]");
//        if (!$this->openDB()) {
//            lzyExit('failed#save');
//        }
//		$lzyEditableFreezeAfter = $this->freezeFieldAfter;
//		if ($lzyEditableFreezeAfter) {
//		    $freezeBefore = time() - $lzyEditableFreezeAfter;
//		    $lastModif = $this->db->lastModified($id);
//		    if ($lastModif && ($lastModif < $freezeBefore)) {
//                mylog("### value frozen - save failed!: $id -> [$text]");
//                lzyExit('restart');
//            }
//        }

        $db = $this->sets[$setInx];
        $res = $db->writeElement($rec['dataSelector'], $text, false, false);
//        $db = $this->sets[ $this->setInx ];
//        $res = $db->writeElement($id, $text, false, false);

        if (!$res) {
            mylog("### save failed!: $id -> [$text]");
        }
		$db->unlockRec($id, true); // unlock all owner's locks

		lzyExit($this->prepareClientData($id).'#save');
	} // save




	//---------------------------------------------------------------------------
//	private function getAllData()
//    {
////        if (!$this->openDB()) {
////            lzyExit('failed#save');
////        }
//        lzyExit($this->prepareClientData().'#get-all');
//    } // getAllData




    //---------------------------------------------------------------------------
    private function reset()
    {
//        if (!$this->openDB()) {
//            lzyExit('failed#reset');
//        }
//
        //Todo: add some protection from fraululant use -> at least restrict to loggedin users
        $this->sets->unlockDB( true );
        $data = $this->sets->read();
        foreach ($data as $key => $value) {
            $this->sets->unlockRec( $key, true );
        }
        lzyExit();
    } // reset




	//------------------------------------------------------------
	private function prepareClientData($key = false)
	{
        $outData = $this->assembleResponse();
        $outData['result'] = 'ok';
        return json_encode( $outData );

//        $outData = [];
//        foreach ($this->dbs as $setInx => $db) {
//            $dbLocked = $db->isLockDB( false );
//            $data = $db->read();
//            foreach ($data as $key => $elem) {
//                if (is_array($elem)) {
//                    if (preg_match('/(.*?-)(\d+)$/', $key, $m)) {
//                        $key0 = $m[1];
//                        $i = intval($m[2]);
//                    }
//                    foreach ($elem as $r => $row) {
//                        if (is_array($row)) {
//                            foreach ($row as $c => $val) {
//                                if ($dbLocked || $db->isRecLocked("$key.$r.$c", true)) {
//                                    $val .= '**LOCKED**';
//                                }
//                                $data["$key0$i"] = $val;
//                                $i++;
//                            }
//                        } else {
//                            $data["$key0$i"] = $row;
//                            $i++;
//                        }
//                    }
//                } else {
//                    if ($dbLocked || $db->isRecLocked($key, true)) {
//                        $data[$key] .= '**LOCKED**';
//                    }
//                }
//            }
//            $outData = array_merge($outData, $data);
//        }
//
//        return json_encode(['data' =>$data, 'result' => 'ok']);
	} // prepareClientData




    private function assembleResponse()
    {
        $outData = [];
        foreach ($this->sets as $setName => $set) {
            $outRec = $this->getData( $set );
            if (!$outData) {
                $outData = $outRec;
            } else {
                $outData['data'] = array_merge($outData['data'], $outRec['data']);
                $outData['locked'] = array_merge($outData['locked'], $outRec['locked']);
            }
        }
        return $outData;
    } // assembleResponse




    private function getData( $setDb )
    {
//        $setDb = $set['_db'];
        $data = $setDb->read();
//        $data = $setDb->data;

        $dbIsLocked = $setDb->isDbLocked( false );
        $lockedElements = [];
        $tmp = [];
//        foreach ($setDb as $k => $elem) {
        foreach ($this->config as $k => $elem) {
            if ($k[0] === '_') {
                continue;
            }

            $dataKey = $elem["dataSelector"];
            $targetSelector = $elem['targetSelector'];

            if (strpos($dataKey, '*') !== false) {
                $tmp = $this->getValueArray($setDb, $k);
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

                if ($dbIsLocked || $setDb->isRecLocked( $dataKey )) {
                    $lockedElements[] = $targetSelector;
                }
                if (isset($data[ $dataKey ])) {
                    $value = $data[ $dataKey ];
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



    public function getValueArray($set, $k)
    {
        $elem = $set[ $k ];
        $dataKey = $elem["dataSelector"];
        $targetSelector = $elem['targetSelector'];

        // dynDataSelector: select a data element upuo client request:
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

        $tmp = [];
        $data = $set['_db']->read();
        $r = 1;
        foreach ($data as $key => $rec) {
            if (is_array($rec)) {
                $c = 1;
                foreach ($rec as $k => $v) {
                    if (preg_match('/(.*?) \* (.*?) \* (.*?) /x', $targetSelector, $m)) {
                        $targSel = "{$m[1]}$r{$m[2]}$c{$m[3]}";
                        $tmp[$targSel] = $v;
                        $c++;
                    } else {
                        die("Error in targetSelector '$targetSelector' -> '*' missing");
                    }
                }
            } else {
                if (preg_match('/(.*?) \* (.*?)/x', $targetSelector, $m)) {
                    $targSel = "{$m[1]}$r{$m[2]}";
                    $tmp[$targSel] = $rec;
                } else {
                    die("Error in targetSelector '$targetSelector' -> '*' missing");
                }
            }
            $r++;
        }
        return $tmp;
    } // getValueArray



    //---------------------------------------------------------------------------
    private function openDB() {
	    if ($this->sets) {
	        return true;
        }

	    $useRecycleBin = false;
        $dataRef = $this->get_request_data('ds');
        if (!$dataRef || !preg_match('/^[A-Z][A-Z0-9]{4,20}/', $dataRef)) {   // dataRef missing
            return false;
        }
        if (preg_match('/^([A-Z0-9]{4,20})\:(.+)$/', $dataRef, $m)) {
            $dataRef = $m[1];
            $this->setInx = $m[2];
        } else {
            $this->setInx = false;
            die('failed: set-Index missing');
        }
        require_once SYSTEM_PATH . 'ticketing.class.php';
        $tick = new Ticketing();
        $ticketRec = $tick->consumeTicket($dataRef);
        $this->ticketRec = $ticketRec;

        foreach ($ticketRec as $setInx => $tRec) {
            if (strpos($setInx, 'set') === false) {
                continue;
            }
            for ($i=1; $i<99; $i++) {
                $recInx = "e$i";
                if ($recInx && isset($tRec[$recInx])) {
                    $rec = $tRec[$recInx];
                    $this->config[$recInx] = $rec;
                    $this->freezeFieldAfter = intval($rec['freezeFieldAfter']);
                    $useRecycleBin |= $rec['useRecycleBin'];
                    $this->sets[$setInx] = new DataStorage2([
                        'dataFile' => PATH_TO_APP_ROOT.$rec['dataSource'],
                        'sid' => $this->sessionId,
                        'lockDB' => false,
                        'useRecycleBin' => $useRecycleBin,
                        'lockTimeout' => false,
                    ]);
                } else {
                    break;
                }
            }
        }
        return true;
    } // openDB




//------------------------------------------------------------
	private function get_request_data($varName) {
		global $argv;
		$out = null;
		if (isset($_GET[$varName])) {
			$out = $this->safeStr($_GET[$varName]);

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
		return $out;
	} // get_request_data




    //---------------------------------------------------------------------------
	private function var_r($data, $varName = false)
	{
		$out = var_export($data, true);
		if ($varName) {
		    $out = "$varName: ".$out;
        }
		return str_replace("\n", '', $out);
	} // var_r




    //---------------------------------------------------------------------------
	/**
     * http://php.net/manual/de/function.session-start.php
     * Every time you call session_start(), PHP adds another
     * identical session cookie to the response header. Do this
     * enough times, and your response header becomes big enough
     * to choke the web server.
     *
     * This method clears out the duplicate session cookies. You can
     * call it after each time you've called session_start(), or call it
     * just before you send your headers.
     */
    private function clear_duplicate_cookies() {
        // If headers have already been sent, there's nothing we can do
        if (headers_sent()) {
            return;
        }
        $cookies = array();
        foreach (headers_list() as $header) {
            // Identify cookie headers
            if (strpos($header, 'Set-Cookie:') === 0) {
                $cookies[] = $header;
            }
        }
        // Removes all cookie headers, including duplicates
        header_remove('Set-Cookie');

        // Restore one copy of each cookie
        foreach(array_unique($cookies) as $cookie) {
            header($cookie, false);
        }
    } // clear_duplicate_cookies




	function safeStr($str) {
		if (preg_match('/^\s*$/', $str)) {
			return '';
		}
		$str = substr($str, 0, MAX_URL_ARG_SIZE);	// restrict size to safe value
		return $str;
	} // safe_str




    private function handleEditableRequests()
    {
        if (isset($_GET['conn'])) {                                // conn  initial interaction with client, defines used ids
            $this->initConnection();
        }

        if (!$this->openDB()) {
            $cmd = str_replace(['ds', '_', 'ref'], '', implode('', array_keys($_GET)));
            lzyExit("failed#$cmd");
        }

        if ($id = $this->get_request_data('get')) {     // get value(s)
            $this->get($id);
        }

        if (isset($_GET['reset'])) {                            // reset all locks
            $this->reset();
        }

        if ($id = $this->get_request_data('lock')) {    // lock an editable field
            $this->lock($id);
        }

        if ($id = $this->get_request_data('unlock')) {    // unlock an editable field
            $this->unlock($id);
        }

        if ($id = $this->get_request_data('save')) {    // save data & unlock
            $this->save($id);
        }
    } // handleEditableRequests





    private function handleGenericRequests()
    {
        if (isset($_GET['log'])) {                                // remote log, write to backend's log
            $msg = $this->get_request_data('text');
            mylog("Client: $msg");
            lzyExit();
        }
        if ($this->get_request_data('info') !== null) {    // respond with info-msg
            $this->info();
        }
    } // handleGenericRequests




    private function getRef()
    {
        return $this->get_request_data('ref');
    } // getRef

} // EditingService


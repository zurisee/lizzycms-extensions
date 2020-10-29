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
            $this->sendResponse('restart');
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
	} // __construct



	//---------------------------------------------------------------------------
	public function execute()
	{
        if (isset($_GET['conn'])) {                                // conn  initial interaction with client, defines used ids
            $this->initConnection();
        }

        if (!$this->openDB()) {
            $cmd = str_replace(['ds', '_', 'ref'], '', implode('', array_keys($_GET)));
            mylog("### openDB -> failed: $cmd");
            $this->sendResponse("failed#openDB:$cmd");
        }

        if ($id = $this->getRequestData('get')) {     // get value(s)
            $this->get($id);
        }

        if ($id = $this->getRequestData('lock')) {    // lock an editable field
            $this->lock($id);
        }

        if ($id = $this->getRequestData('unlock')) {    // unlock an editable field
            $this->unlock($id);
        }

        if ($id = $this->getRequestData('save')) {    // save data & unlock
            $this->save($id);
        }

        $this->handleGenericRequests();     // log, info

    } // handleUrlArguments



	//---------------------------------------------------------------------------
	private function initConnection() {
        if (!session_start()) {
            mylog("ERROR in initConnection(): failed to start session");
        }
        $this->clear_duplicate_cookies();		if (!isset($_SESSION['lizzy']['pageName']) || !isset($_SESSION['lizzy']['pagePath'])) {
            mylog("### Client connection failed: [{$this->remoteAddress}] {$this->userAgent}");
            $this->sendResponse('failed#conn');
        }

        $ok = $this->openDB();

        $pagePath = $_SESSION["lizzy"]["pageFolder"];
        mylog("Client connected: [{$this->remoteAddress}] {$this->userAgent} (pagePath: $pagePath)");

		$_SESSION['lizzy']['lastSentData'] = '';
		session_write_close();

		if ($ok) {
            mylog("conn -> ok");
            $this->sendResponse('ok#conn');
        } else {
            mylog("### conn -> failed");
            $this->sendResponse('failed#conn');
        }
	} // initConnection




	//---------------------------------------------------------------------------
	private function lock($id) {
		if (!$id) {
			mylog("### lock: Error -> id not defined");
            $this->sendResponse("failed#lock (id not defined)");
		}

		$set = $this->getSet( $id );
        $dataKey = $this->getDataSelector( $id );
        $db = $set['_db'];
        $res = $db->lockRec( $dataKey );
        if (!$res) {
			mylog("### lock: $dataKey -> failed");
            $this->sendResponse(['id' => $id], 'failed#lock');

		} else {
            mylog("lock: $dataKey -> ok");
            $this->sendResponse($this->assembleResponse($id), 'ok#lock');
		}
	} // lock




	//---------------------------------------------------------------------------
	private function unlock($id) {
		if (!$id) {
			mylog("### unlock: Error -> id not defined");
            $this->sendResponse("failed#unlock (id not defined)");
		}
        $db = $this->sets[ $this->setInx ]['_db'];
        $dataKey = $this->getDataSelector( $id );
        $res = $db->unlockRec($dataKey);
        if ($res) {
			mylog("unlock: $dataKey -> ok");
            $this->sendResponse($this->assembleResponse($id), 'ok#unlock');
        }
        mylog("### unlock: $dataKey -> failed");
        $this->sendResponse(['id' => $id], 'failed#unlock');
	} // unlock




	//---------------------------------------------------------------------------
	private function get($id) {
        mylog("get: $id -> ok");
        $this->sendResponse($this->assembleResponse($id),'ok#get');
	} // get




	//---------------------------------------------------------------------------
	private function save($id) {
		if (!$id) {
			mylog("### save & unlock: Error -> id not defined");
            $this->sendResponse("failed#save (id not defined)");
		}
		$text = $this->getRequestData('text');
		if ($text === 'undefined') {
            mylog("### save: $id -> failed (text not defined)");
            $this->sendResponse("failed#save (text not defined)");
        }

        $dataKey = $this->getDataSelector( $id );
        $set = $this->getSet( $id );
        $db = $set['_db'];
        $res = $db->writeElement($dataKey, $text, false, false);

        if (!$res) {
            mylog("### save failed!: $id -> [$text]");
            $this->sendResponse("failed#save (locked)");
        }
		$db->unlockRec($dataKey, true); // unlock all owner's locks

        mylog("save: $id => '$text' -> ok");
        $this->sendResponse($this->assembleResponse($id), 'ok#save');
	} // save




    private function assembleResponse()
    {
        $outData = [];
        foreach ($this->sets as $setName => $set) {
            $outRec = $this->getData( $setName );
            if (!$outData) {
                $outData = $outRec;
            } else {
                $outData['data'] = array_merge($outData['data'], $outRec['data']);
                $outData['locked'] = array_merge($outData['locked'], $outRec['locked']);
            }
        }
        return $outData;
    } // assembleResponse



    private function sendResponse( $out, $result = null )
    {
        $outData = [];
        if (is_string($out)) {
            $outData['result'] = $out;
        } else {
            $outData = $out;
            if ($result !== null) {
                $outData['result'] = $result;
            } elseif (!isset($outData['result'])) {
                $outData['result'] = 'ok';
            }
        }
        lzyExit( json_encode( $outData ) );
    } // sendResponse




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
            if (strpos($dataKey, '*') !== false) {
                $tmp = $this->getValueArray($setDb, $targetSelector);
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

                $lastModif = $setDb->lastModifiedElement($dataKey);

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



    private function getSet( $id )
    {
        if (isset($this->editableElements[$id])) {
            return $this->sets[ $this->editableElements[$id][1] ];
        }
        return false;
    } // getSet



    private function getDataSelector( $id )
    {
        if (isset($this->editableElements[$id])) {
            return $this->editableElements[$id][0];
        }
        return false;
    } // getDataSelector



    //---------------------------------------------------------------------------
    private function openDB() {
	    if ($this->sets) {
	        return true;
        }

	    $useRecycleBin = false;
        $dataRef = $this->getRequestData('ds');
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
                    $this->sets[$setInx]['_tickRec'] = $rec;
                    $this->freezeFieldAfter = intval($rec['freezeFieldAfter']);
                    $useRecycleBin |= $rec['useRecycleBin'];
                    $this->sets[$setInx]['_db'] = new DataStorage2([
                        'dataFile' => PATH_TO_APP_ROOT.$rec['dataSource'],
                        'sid' => $this->sessionId,
                        'lockDB' => false,
                        'useRecycleBin' => $useRecycleBin,
                        'lockTimeout' => false,
                        'logModifTimes' => true,
                    ]);
                    $tSel = substr($rec['targetSelector'], 1);
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




//------------------------------------------------------------
	private function getRequestData($varName) {
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
	} // getRequestData




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




    private function handleGenericRequests()
    {
        if (isset($_GET['log'])) {                                // remote log, write to backend's log
            $msg = $this->getRequestData('text');
            mylog("Client: $msg");
            $this->sendResponse('ok');
        }
        if ($this->getRequestData('info') !== null) {    // respond with info-msg
            $this->info();
        }
    } // handleGenericRequests




    private function getRef()
    {
        return $this->getRequestData('ref');
    } // getRef



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
        $this->sendResponse(['text' => $msg], 'ok');
    } // info

} // EditingService


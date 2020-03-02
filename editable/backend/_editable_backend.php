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

define('SYSTEM_PATH', 		'../../../');		                    //
define('PATH_TO_APP_ROOT', 	SYSTEM_PATH.'../');		                // root folder of web app

define('LOCK_TIMEOUT', 		120);	                                // max time till field is automatically unlocked
define('MAX_URL_ARG_SIZE',  255);
if (!defined('MKDIR_MASK')) {
    define('MKDIR_MASK', 0700);
}

require_once SYSTEM_PATH.'backend_aux.php';
require_once SYSTEM_PATH.'datastorage2.class.php';
require_once SYSTEM_PATH.'elementLevelDataStorage.class.php';
require_once SYSTEM_PATH.'ticketing.class.php';

define('DEFAULT_EDITABLE_DATA_FILE', 'editable.'.LZY_DEFAULT_FILE_TYPE);

$appRoot = preg_replace('/_lizzy\/.*$/', '', getcwd());

$serv = new EditableBackend;

class EditableBackend
{
	public function __construct()
	{
        $this->terminatePolling = false;
		if (sizeof($_GET) < 1) {
			exit('Hello, this is '.basename(__FILE__));
		}
        if (!session_start()) {
            mylog("ERROR in __construct(): failed to start session");
        }
        $this->clear_duplicate_cookies();

        $timezone = isset($_SESSION['lizzy']['systemTimeZone']) ? $_SESSION['lizzy']['systemTimeZone'] : 'CET';
        date_default_timezone_set($timezone);


        if (!isset($_SESSION['lizzy']['userAgent'])) {
            mylog("*** Fishy request from {$_SERVER['HTTP_USER_AGENT']} (no valid session)");
            exit('restart');
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

        $this->db = false;
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
		$this->handleUrlArguments();
		$this->config = [];
	} // __construct



	//---------------------------------------------------------------------------
	private function handleUrlArguments()
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
		exit($msg);
	} // info



	//---------------------------------------------------------------------------
	private function initConnection() {
        if (!session_start()) {
            mylog("ERROR in initConnection(): failed to start session");
        }
        $this->clear_duplicate_cookies();		if (!isset($_SESSION['lizzy']['pageName']) || !isset($_SESSION['lizzy']['pagePath'])) {
            mylog("*** Client connection failed: [{$this->remoteAddress}] {$this->userAgent}");
            exit('failed#conn');
        }

        $pagePath = $_SESSION['lizzy']['pagePath'];
        mylog("Client connected: [{$this->remoteAddress}] {$this->userAgent} (pagePath: $pagePath)");

		$_SESSION['lizzy']['lastSentData'] = '';
		session_write_close();

		exit('ok#conn');
	} // initConnection




	//---------------------------------------------------------------------------
	private function lock($id) {
		if (!$id) {
			mylog("lock: Error -> id not defined");
			exit;
		}

        if (!$this->openDB()) {
            exit('failed#lock/DB');
        }

        $res = $this->db->lockElement($id);

        if (!$res) {
			mylog("lock: $id -> failed");
			exit('failed#lock');

		} else {
            exit($this->prepareClientData($id).'#lock:ok');
		}
	} // lock




	//---------------------------------------------------------------------------
	private function unlock($id) {
		if (!$id) {
			mylog("unlock: Error -> id not defined");
			exit;
		}
        if (!$this->openDB()) {
            exit('failed#unlock');
        }
        $res = $this->db->unLockElement($id);
        if ($res) {
			mylog("unlock: $id -> ok");
            exit($this->prepareClientData($id).'#unlock:ok');
        }
        exit('failed#unlock');
	} // unlock




	//---------------------------------------------------------------------------
    //	private function update($upd) {
    //		exit($this->prepareClientData($upd).'#update');
    //	} // get




	//---------------------------------------------------------------------------
	private function get($id) {
		exit($this->prepareClientData($id).'#get');
	} // get




	//---------------------------------------------------------------------------
	private function save($id) {
		if (!$id) {
			mylog("save & unlock: Error -> id not defined");
            exit('failed#save');
		}
		$text = $this->get_request_data('text');
		if ($text === 'undefined') {
            exit('failed#save');
        }

        $ref = $this->getRef();
        mylog("save: {$id}[$ref] -> [$text]");
        if (!$this->openDB()) {
            exit('failed#save');
        }
//		$lzyEditableFreezeAfter = $this->freezeFieldAfter;
//		if ($lzyEditableFreezeAfter) {
//		    $freezeBefore = time() - $lzyEditableFreezeAfter;
//		    $lastModif = $this->db->lastModified($id);
//		    if ($lastModif && ($lastModif < $freezeBefore)) {
//                mylog("### value frozen - save failed!: $id -> [$text]");
//                exit('restart');
//            }
//        }

        $res = $this->db->writeElement($id, $text, $ref);

        if (!$res) {
            mylog("### save failed!: $id -> [$text]");
        }
		$this->db->unLockElement(true); // unlock all owner's locks

		exit($this->prepareClientData($id).'#save');
	} // save




	//---------------------------------------------------------------------------
	private function getAllData()
    {
        if (!$this->openDB()) {
            exit('failed#save');
        }
        exit($this->prepareClientData().'#get-all');
    } // getAllData




	//------------------------------------------------------------
	private function prepareClientData($key = false)
	{
        if (!$key || ($key === '_all')) {   // return all data
            $out = [];
            for ($i=0; $i<99; $i++) {
                if (!$this->openDB($i)) {   // try index till one fails...
                    break;
                }
                $data = $this->db->read();
                if (!$data) {
                    $out[$i] = [];
                    continue;
                }
                $tFreeze = microtime( true ) - $this->freezeFieldAfter;
//$diff = microtime( true ) - $tFreeze;
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        if ($this->db->isElementLocked($k)) {
                            $data[$k] .= '**LOCKED**';
                        }
                        $t = $this->db->elementLastModified($k);
                        if ($this->freezeFieldAfter && ($t < $tFreeze)) {
                            $data[$k] .= '**FROZEN**';
                        }
                    }
                    $out[$i] = $data;
                }
            }
            $json = json_encode($out);
            return $json;

        } else {        // return specific data element:
            if (!$this->openDB()) {
                exit('failed#getData');
            }
            $ref = $this->getRef();

            $val = $this->db->readElement($key, $ref);
            if ($this->db->isElementLocked($key)) {
                $val .= '**LOCKED**';
            }
            $data[$key] = $val;
        }
		if (!$data) {
			$data = [];
        }
		return json_encode($data);
	} // prepareClientData




    //---------------------------------------------------------------------------
    private function openDB( $overrideInx = false) {
        $this->dataFile = false;
	    $useRecycleBin = false;
        $dataRef = $this->get_request_data('ds');
        if ($dataRef && preg_match('/^[A-Z0-9]{4,20}\:\d+$/', $dataRef)) {     // dataRef (=ticket hash) available
            require_once SYSTEM_PATH . 'ticketing.class.php';
            list($dataRef, $inx) = explode(':', $dataRef);
            if ($overrideInx !== false) {
                $inx = $overrideInx;
            }

            if (!isset($this->config[$inx])) {
                $ticketing = new Ticketing();
                $ticketRec = $ticketing->consumeTicket($dataRef);
                if (isset($ticketRec[$inx])) {      // corresponding ticket found
                    $this->dataFile = $ticketRec[$inx]['dataSrc'];
                    $this->config[$inx] = $ticketRec[$inx];
                    $this->freezeFieldAfter = intval($ticketRec[$inx]['freezeFieldAfter']);
                    $useRecycleBin = $ticketRec[$inx]['useRecycleBin'];

                } else {
                    return false;
                }
            } else {
                $this->dataFile = $this->config[$inx]['dataSrc'];
            }
        } else {
            return false;
        }

        if ($this->dataFile) {
            $this->db = new ElementLevelDataStorage([
                'dataFile' => PATH_TO_APP_ROOT.$this->dataFile,
                'sid' => $this->sessionId,
                'lockDB' => false,
                'useRecycleBin' => $useRecycleBin,
                'lockTimeout' => false,
            ]);
            return true;
        }
        return false;
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




    //    private function endPolling()
    //    {
    //        $this->terminatePolling = true;
    //        $this->mylog("termination initiated");
    //        exit('#termination initiated');
    //    }




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
        // Possible enhancement: continuous updating of editable fields:
        //		if ($upd = $this->get_request_data('upd')) {		// update request (long-polling)
        //			$this->update($upd);
        //			exit;
        //		}
        //        if ($this->get_request_data('end') !== null) {	    // end update
        //            $this->endPolling();
        //        }


        if (isset($_GET['conn'])) {                                // conn  initial interaction with client, defines used ids
            $this->initConnection();
            exit;
        }

        if ($id = $this->get_request_data('get')) {     // get value(s)
            $this->get($id);
            exit;
        }

        if (isset($_GET['reset'])) {                            // reset all locks
            $this->reset();
            exit;
        }

        if ($id = $this->get_request_data('lock')) {    // lock an editable field
            $this->lock($id);
            exit;
        }

        if ($id = $this->get_request_data('unlock')) {    // unlock an editable field
            $this->unlock($id);
            exit;
        }

        if ($id = $this->get_request_data('save')) {    // save data & unlock
            $this->save($id);
            exit;
        }
    } // handleEditableRequests





    private function handleGenericRequests()
    {
        if (isset($_GET['log'])) {                                // remote log, write to backend's log
            $msg = $this->get_request_data('text');
            mylog("Client: $msg");
            exit;
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


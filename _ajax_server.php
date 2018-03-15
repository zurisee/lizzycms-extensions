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

date_default_timezone_set('CET');		// modify as appropriate
define('DATA_PATH', 		'../data/');		// modify if necessary
define('SERVICE_LOG', 		'../.#logs/log.txt');	// modify if necessary
define('ERROR_LOG', 		'../.#logs/errlog.txt');	// modify if necessary
define('LONG_POLL_FREQ', 	1);		// local polling cycle time, i.e. how often data is read
define('LOCK_TIMEOUT', 		120); //90);	// max time till field is automatically unlocked
define('MAX_URL_ARG_SIZE', 255);
define('SYSTEM_PATH','_lizzy/');

require_once 'vendor/autoload.php';
require_once 'datastorage.class.php';

use Symfony\Component\Yaml\Yaml;

$serv = new AjaxServer;

class AjaxServer
{
	public function __construct()
	{
        $this->terminatePolling = false;
		if (sizeof($_GET) < 1) {
			exit('Hello, this is '.basename(__FILE__));
		}
		session_start();
		if (!isset($_SESSION['lizzy']['userAgent'])) {
            $this->clientID = '????';
            $this->user = '????';
            $this->mylog("*** Fishy request from {$_SERVER['HTTP_USER_AGENT']} (no valid session)");
            exit('failed');
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
        $pagePath = $_SESSION['lizzy']['pagePath'];

        if (isset($_SESSION['lizzy']['db'][$pagePath]) && $_SESSION['lizzy']['db'][$pagePath]) {
            $dbFile = '../' . $_SESSION['lizzy']['db'][$pagePath];
            $this->db = new DataStorage($dbFile, $this->sessionId, true);
        }
        if (isset($_SESSION['lizzy']['userDisplayName']) && $_SESSION['lizzy']['userDisplayName']) {    // get user name for logging
            $this->user = '['.$_SESSION['lizzy']['userDisplayName'].']';

        } elseif (isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']) {
            $this->user = '['.$_SESSION['lizzy']['user'].']';

        } else {
            $this->user = '';
        }

		$this->hash = $_SESSION['lizzy']['hash'];
		session_write_close();
        preparePath(DATA_PATH);

		$this->remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
		$this->userAgent = isset($_SESSION['lizzy']['userAgent']) ? $_SESSION['lizzy']['userAgent'] : $_SERVER["HTTP_USER_AGENT"];
		$this->isLocalhost = (($this->remoteAddress == 'localhost') || (strpos($this->remoteAddress, '192.') === 0) || ($this->remoteAddress == '::1'));
		$this->handleUrlArguments();
	} // __construct



	//---------------------------------------------------------------------------
	private function handleUrlArguments()
	{
		if ($upd = $this->get_request_data('upd')) {		// update request (long-polling)
			$this->update($upd);
			exit;
		}

		if ($id = $this->get_request_data('get')) {		// get value(s)
			$this->get($id);
			exit;
		}

        if ($ids = $this->get_request_data('conn')) {	    // conn  initial interaction with client, defines used ids
			$this->initConnection( $ids );
			exit;
		}

		if (isset($_GET['reset'])) {					// reset all locks
			$this->reset();
			exit;
		}

		if ($id = $this->get_request_data('lock')) {	// lock an editable field
			$this->lock($id);
			exit;
		}

		if ($id = $this->get_request_data('unlock')) {	// unlock an editable field
			$this->unlock($id);
			exit;
		}

		if ($id = $this->get_request_data('save')) {	// save data & unlock
			$this->save($id);
			exit;
		}

		if ($msg = $this->get_request_data('log')) {	// remote log, write to backend's log
			$this->mylog("Client: $msg");
			exit;
		}
		if ($this->get_request_data('info') !== null) {	// respond with info-msg
			$this->info();
		}

		if ($this->get_request_data('end') !== null) {	// end update
			$this->endPolling();
		}

		if ($this->get_request_data('getfile') !== null) {	// send md-file
			$md = '';
			if (isset($_POST['lzy_filename'])) {
			    $filename = $_POST['lzy_filename'];
                $approot = trunkPath($_SERVER['SCRIPT_FILENAME']);
                if ($filename == 'sitemap') {
			        $filename = $approot . 'config/sitemap.txt';
                } else {
                    $filename = $approot . $filename;
                }
                if (file_exists($filename)) {
                    $md = file_get_contents($filename);
                }
            }
			exit($md);
		}

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
	private function initConnection($ids) {
		session_start();
		if (!isset($_SESSION['lizzy']['pageName']) || !isset($_SESSION['lizzy']['pagePath'])) {
            $this->mylog("*** Client connection failed: [{$this->remoteAddress}] {$this->userAgent}");
            exit('failed#conn');
        }

        $pagePath = $_SESSION['lizzy']['pagePath'];
        $pathToPage = $_SESSION['lizzy']['pathToPage'];

        $this->mylog("=======");
        $this->mylog("Client connected: [{$this->remoteAddress}] {$this->userAgent} (pagePath: $pagePath)");

        if (isset($_SESSION['lizzy']['db'][$pagePath]) && $_SESSION['lizzy']['db'][$pagePath]) {   // explicit data path provided
            $dbFile = '../'.$_SESSION['lizzy']['db'][$pagePath];

        } else {                                            // no data path, use default: local to page
            $dbFile = "../{$pathToPage}editable.yaml";
            $_SESSION['lizzy']['db'][$pagePath] = "{$pathToPage}editable.yaml";
        }

		$_SESSION['lizzy']['lastSentData'] = '';
		session_write_close();

        $this->mylog("Database File: $dbFile");
        $this->db = new DataStorage($dbFile, $this->sessionId, true);

        $ids = explode(',', rtrim($ids, ','));
        $this->db->initRecs($ids);

		$this->db->unlock(true);
		exit('ok#conn');
	} // initConnection



    private function endPolling()
    {
        $this->terminatePolling = true;
        $this->mylog("termination initiated");
        exit('#termination initiated');
    }

	//---------------------------------------------------------------------------
	private function update($pollDuration = 60)
	{
		$pollCycles = ($pollDuration / LONG_POLL_FREQ) + rand(0, 5);

        $tClient = $this->getLastUpdated();
        for ($i=0; $i<$pollCycles; $i++) {
            if (!isset($this->db)) {
                sleep(1);
                exit('#Error: db not initialized');
            }
            $tData = $this->db->lastModified();
            if ($tData > $tClient) {
                $data = $this->db->read('*', true);
                $dataToSend = $this->prepareClientData($data);
                $this->setLastUpdated($tData);
                // $this->mylog("% update reply: new data [$dataToSend]");
                exit($dataToSend.'#upd new data');
            }

			if ((time()%10) == 0) {        // check timeouts every 10 seconds
                if ($id = $this->db->unlockTimedOut(LOCK_TIMEOUT)) {
                    $this->mylog("% update ########## FORCED UNLOCK: $id");
                }
            }
            if ($this->terminatePolling) {
                exit('#long-polling ended');
            }
			sleep(LONG_POLL_FREQ);
		}
        $data = $this->db->read('*', true);
        $dataToSend = $this->prepareClientData($data);
        $this->setLastUpdated();
		exit($dataToSend.'#upd heartbeat ('.$pollDuration.'s)');
	} // update




	//---------------------------------------------------------------------------
	private function lock($id) {
		if (!$id) {
			$this->mylog("lock: Error -> id not defined");
			exit;
		}
		if (!isset($this->db) || !$this->db) {
            $this->mylog("lock: Error: need to initialize connection first");
			exit('Error: need to initialize connection first');
		}
		if (!$this->db->lock($id)) {
			$this->mylog("lock: $id -> failed");
			exit('failed');
		} else {
			$this->mylog("lock: $id -> ok");
			exit('ok');
		}
	} // lock




	//---------------------------------------------------------------------------
	private function unlock($id) {
		if (!$id) {
			$this->mylog("unlock: Error -> id not defined");
			exit;
		}
		if ($this->db->unlock($id)) {
			$this->mylog("unlock: $id -> ok");
            exit('ok');
        }
	} // unlock




	//---------------------------------------------------------------------------
	private function get($id) {
		$data = $this->db->read($id);
		exit($this->prepareClientData($data, true).'#get');
	} // get




	//---------------------------------------------------------------------------
	private function save($id) {
		if (!$id) {
			$this->mylog("save & unlock: Error -> id not defined");
			exit;
		}
		$text = $this->get_request_data('text');
		$this->mylog("save: $id -> [$text]");
		if (!$this->db->write($id, $text)) {
            $this->mylog("### save failed!: $id -> [$text]");
        }
		$this->db->unlock(true); // unlock all owner's locks
		$data = $this->db->read();

		exit($this->prepareClientData($data,  true).'#save');
	} // save




	//---------------------------------------------------------------------------
	private function reset() {
		if ($this->db) {
			$this->db->unlock('*');
		}
		session_start();
		unset($_SESSION['lizzy']['hash']);
		unset($_SESSION['lizzy']['lastSentData']);
		unset($_SESSION['lizzy']['db']);
		session_write_close();
		exit('ok');
	} // reset




	//------------------------------------------------------------
	private function prepareClientData($data)
	{
		if (!$data) {
			$data = array();
		}
		return json_encode($data);
	} // prepareClientData




	//------------------------------------------------------------
	private function get_request_data($varName) {
		global $argv;
		$out = null;
		if (isset($_GET[$varName])) {
			$out = $this->safeStr($_GET[$varName]);

		} elseif (isset($_POST[$varName])) {
			$out = $this->safeStr($_POST[$varName]);

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
	private function var_r($data)
	{
		$out = var_export($data, true);
		return str_replace("\n", '', $out);
	} // var_r




	//---------------------------------------------------------------------------
	private function mylog($str)
	{
		if (!file_exists(dirname(SERVICE_LOG))) {
			mkdir(dirname(SERVICE_LOG), 0777, true);
		}
		file_put_contents(SERVICE_LOG, $this->timestamp()." {$this->clientID}{$this->user}:  $str\n", FILE_APPEND);
	} // mylog




	//---------------------------------------------------------------------------
	private function getLastUpdated()
    {
        session_start();
        $t = $_SESSION['lizzy']['lastUpdated'];
        session_abort();
        return $t;
    } // getLastUpdated




    //---------------------------------------------------------------------------
    private function setLastUpdated($t = false)
    {
        if (!$t) {
            $t = time();
        }
        session_start();
        $_SESSION['lizzy']['lastUpdated'] = $t;
        session_write_close();
        $this->clear_duplicate_cookies();
    } // setLastUpdated




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




	//---------------------------------------------------------------------------
	private function timestamp($short = false)
    {
		if (!$short) {
			return date('Y-m-d H:i:s');
		} else {
			return date('Y-m-d');
		}
	} // timestamp




	//---------------------------------------------------------------------------
	function safeStr($str) {
		if (preg_match('/^\s*$/', $str)) {
			return '';
		}
		$str = substr($str, 0, MAX_URL_ARG_SIZE);	// restrict size to safe value
		return $str;
	} // safe_str

} // EditingService



//--------------------------------------------------------------
function convertYaml($str)
{
	$data = null;
	if ($str) {
		$str = str_replace("\t", '    ', $str);
		try {
			$data = Yaml::parse($str);
		} catch(Exception $e) {
            fatalError("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
		}
	}
	return $data;
} // convertYaml




//--------------------------------------------------------------
function convertToYaml($data, $level = 3)
{
	return Yaml::dump($data, $level);
} // convertToYaml




//-----------------------------------------------------------------------------
function trunkPath($path, $n = 1)
{
	$path = ($path[strlen($path)-1] == '/') ? rtrim($path, '/') : dirname($path);
	return implode('/', explode('/', $path, -$n)).'/';
} // trunkPath




//------------------------------------------------------------
function preparePath($path)
{
    $path = dirname($path.'x');
    if (!file_exists($path)) {
        if (!mkdir($path, 0777, true)) {
            fatalError("Error: failed to create folder '$path'");
        }
    }
} // preparePath



//------------------------------------------------------------
function fatalError($msg)
{
    $msg = date('Y-m-d H:i:s')." [_ajax_server.php]\n$msg";
    file_put_contents(ERROR_LOG, $msg, FILE_APPEND);
    exit;
} // fatalError

//------------------------------------------------------------------------------
function var_r($var, $varName = '', $flat = true, $html = true)
{
    if ($html) {
        $out = "<div><pre>$varName: " . var_export($var, true) . "\n</pre></div>\n";
    } else {
        $out = "$varName: " . var_export($var, true);
    }
    if ($flat) {
        $out = preg_replace("/\n/", '', $out);
    }
    return $out;
}


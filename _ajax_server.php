<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Ajax Service for dynamic data, in particular 'editable' data
 * http://localhost/Lizzy/_lizzy/_ajax_server.php?lock=ed1
*/

date_default_timezone_set('CET');		// modify as appropriate
define('DATA_PATH', 		'../data/');		// modify if necessary
define('SERVICE_LOG', 		'../.#logs/log.txt');	// modify if necessary
define('ERROR_LOG', 		'../.#logs/errlog.txt');	// modify if necessary
define('SHORTER_POLL_TIME', 10);	// max polling duration if no changes occure -> touch devices
define('LONG_POLL_TIME', 	60);	// max polling duration if no changes occure
define('LONG_POLL_FREQ', 	2);		// local polling cycle time, i.e. how often data is read
define('LOCK_TIMEOUT', 		90);	// max time till field is automatically unlocked
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
		if (sizeof($_GET) < 1) {
			exit('Hello, this is '.basename(__FILE__));
		}
		session_start();
		if (!isset($_SESSION['lizzy']['userAgent'])) {
            $this->clientID = '????';
            $this->mylog("*** Fishy request from {$_SERVER['HTTP_USER_AGENT']} (no valid session)");
            exit('failed');
        }
		$this->sessionId = session_id();
        $this->clientID = substr($this->sessionId, 0, 4);
		if (!isset($_SESSION['lizzy']['hash'])) {
			$_SESSION['lizzy']['hash'] = '#';
		}
		if (!isset($_SESSION['lizzy']['lastSentData'])) {
			$_SESSION['lizzy']['lastSentData'] = '';
		}
		if (!isset($_SESSION['lizzy']['db']) || !$_SESSION['lizzy']['db']) {
			$db = $_SESSION['lizzy']['db'] = '';
			$this->db = null;
		} else {
			$db = $_SESSION['lizzy']['db'];
			$this->db = new DataStorage($db, $this->sessionId, true);
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

		if (isset($_GET['conn'])) {	// initial interaction with client, defines used ids
			$this->initConnection();
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

		if ($this->get_request_data('getfile') !== null) {	// send md-file
			$md = '';
			if (isset($_POST['filename'])) {
			    $filename = $_POST['filename'];
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
		$msg = <<<EOT
	<pre>
	DB:		{$_SESSION['lizzy']['db']}
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
		$this->mylog("Client connected: [{$this->remoteAddress}] {$this->userAgent}");

		session_start();
		if (!isset($_SESSION['lizzy']['pageName'])) {
            exit('failed#conn');
        }
        $dbFile = DATA_PATH.$_SESSION['lizzy']['pageName'].'.yaml';
		$_SESSION['lizzy']['db'] = $dbFile;
		$_SESSION['lizzy']['lastSentData'] = '';
		session_write_close();

        $this->mylog("Database File: $dbFile");
        $this->db = new DataStorage($dbFile, $this->sessionId);

		$this->db->unlock(true);
		exit('ok#conn');
	} // initConnection



	//---------------------------------------------------------------------------
	private function update($shorterPoll = false)
	{
		$pollDuration = ($shorterPoll == 'true') ? SHORTER_POLL_TIME : LONG_POLL_TIME;
		$pollCycles = $pollDuration / LONG_POLL_FREQ;

		for ($i=0; $i<$pollCycles; $i++) {
			$data = $this->db->read('*', true);
			if ($dataToSend = $this->prepareClientData($data)) {
				exit($dataToSend.'#upd new data');
			}

			if ((time()%10) <= 1) {        // check timeouts every 10 seconds
                if ($this->db->unlockTimedOut()) {
                    continue;
                }
            }
			sleep(LONG_POLL_FREQ);
		}
		exit($this->prepareClientData($data).'#upd heartbeat ('.$pollDuration.')');
	} // update



	//---------------------------------------------------------------------------
	private function lock($id) {
		if (!$id) {
			$this->mylog("lock: Error -> id not defined");
			exit;
		}
		if (!isset($this->db) || !$this->db) {
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
        $this->mylog("unlock: $id");
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
		$this->mylog("save: $id -> $text");
		$this->db->write($id, $text);
		$this->db->unlock($id);
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
	private function prepareClientData($data, $force = false)
	{
		if (!$data) {
			$data = array();
		}
		$dataToSend = json_encode($data);
		session_start();
		$lastSentData =	$_SESSION['lizzy']['lastSentData'];
		if (($dataToSend == $lastSentData) && !$force) {
			$dataToSend = false;
		} else {
			$_SESSION['lizzy']['lastSentData'] = $dataToSend;
        }
		session_write_close();
		if ($dataToSend) {
			$this->mylog($dataToSend);
		}
		return $dataToSend;
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
		file_put_contents(SERVICE_LOG, $this->timestamp()." {$this->clientID}  $str\n", FILE_APPEND);
	} // mylog




	//---------------------------------------------------------------------------
	private function timestamp($short = false) {
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
		$str = preg_replace('/[^[:print:]À-ž]/m', '#', $str);
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


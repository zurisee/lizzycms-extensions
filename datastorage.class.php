<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 * Simple file-based key-value database
 *
 * $db = new DataStorage($dbFile, $sid = '', $lockDB = false, $format = '', $lockTimeout = 120, $secure = false)
 *      $dbFile:        file where data is stored
 *      $sid:           session id - required if file locking is used
 *      $lockDB:        activates file locking
 *      $format:        json, yaml or csv, if not set, file extension is used
 *      $lockTimeout:   time (ms) till a locked file is unlocked automatically
 *      $secure:        prevents accessing files in the 'config/' folder
 *
 * write($key, $value = null)
 *      -> $key == key && $value == value   -> store tuple
 *      -> $key == tuple [key =>value,...]  -> store array
 *      -> $key == array ** $value != null  -> replace data
 *
 * read($key = '*')
 *      -> $key == null || '*'  -> read all records, return array
 *      -> $key == key  -> return value
 *
 * lock($key)
 *
 * unlock($key = true)
 *      -> $key == true  -> unlock all owner's records
 *      -> $key == '*'  -> unlock all records
 *
 * convert($source, $destinationFormat)
 *
 * Modified:
 *      writeAll($data, $destFile = '')  -> write($data)
 *      readValue($key) -> read($key)
 *      readRec($key = '*') -> read('*')
 *
 * 2D Data:
 *      -> when using csv files
 *      -> $key of type 'x,y' (column,row) instead of field id, indices start at 0
 *      e.g. $db->read('0,4');
 *
 * Meta-Data:
 *      is maintained either in same file, then under key '_mega_'
 *      or in separate file in case of 2D data in .csv files -> in this case meta data is not under '_mega_'
*/

define('LIZZY_META', '_meta_');
define('LIZZY_LOCK', 'lock');
define('LIZZY_LOCK_TIME', 'time');
define('LIZZY_SID', 'sid');     // session ID
define('LIZZY_MODIF_TIME', 'modif');
define('LIZZY_LOCK_ALL', 'lock_all');
define('LIZZY_DEFAULT_FILE_TYPE', 'json');

require_once SYSTEM_PATH.'vendor/autoload.php';


use Symfony\Component\Yaml\Yaml;

class DataStorage
{
	private $dataFile;
	private $sid;
	private $dbMetaDBfile = false;
	private $dataModified = false;



    public function __construct($dbFile, $sid = '', $lockDB = false, $format = '', $lockTimeout = 120, $secure = false)
    {
        if (is_array($dbFile)) {
            list($dbFile, $sid, $lockDB, $format, $lockTimeout, $secure, $useRecycleBin) = $this->parseArguments($dbFile);
        } else {
            $useRecycleBin = false;
        }

        if ($secure && (strpos($dbFile, 'config/') !== false)) {
            return null;
        }
        if (file_exists($dbFile)) {
	        $this->dataFile = $dbFile;

		} else {
			if (file_exists(pathinfo($dbFile, PATHINFO_DIRNAME))) {
		        $this->dataFile = $dbFile;
	            touch($this->dataFile);

            } else {
			    if (function_exists('fatalError')) {
                    fatalError("DataStorage: unable to create file '$dbFile'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                } else {
			        die("DataStorage: unable to create file '$dbFile'");
                }
			}
        }
        if (strpos($dbFile, '.csv') !== false) {
            $this->dbMetaDBfile = str_replace('.csv', '_meta.'.LIZZY_DEFAULT_FILE_TYPE, $dbFile);
            if (!file_exists($this->dbMetaDBfile)) {
                touch($this->dbMetaDBfile);
            }
        }
        $this->sid = $sid;
        $this->lockDB = $lockDB;
        $this->format = ($format) ? $format : pathinfo($dbFile, PATHINFO_EXTENSION) ;
        $this->lockTimeout = $lockTimeout;
        $this->useRecycleBin = $useRecycleBin;

        $this->checkDB();   // make sure DB is initialized
        return;
    } // __construct



	//---------------------------------------------------------------------------
    public function write($key, $value = null)
    {
        if (isset($this->meta[LIZZY_LOCK_ALL])) { // entire DB is locked
            if (!$this->isMySessionID($this->meta[LIZZY_LOCK_ALL])) {
                return false;
            }
        }

        $data = &$this->data;
        if ($this->dbMetaDBfile) {
            $meta = &$this->meta;
        } else {
            if (!isset($this->data[LIZZY_META])) {
                $this->data[LIZZY_META] = [];
            }
            $meta = $this->data[LIZZY_META];
        }

        if (!is_array($data)) {
            return false;
        }
        if (is_array($key)) {
            if ($value) {
                $data = $key;   // overwrite all //???
                $this->dataModified = true;

            } else {
                $array = $key;
                foreach ($array as $key => $value) {
                    if (!isset($meta[$key][LIZZY_LOCK][LIZZY_LOCK_TIME])) { // skip meta / lock-time
                        $this->setValue($key, $value);


                    } elseif (isset($meta[$key][LIZZY_LOCK][LIZZY_SID]) &&
                            ($meta[$key][LIZZY_LOCK][LIZZY_SID] == $this->sid)) { // skip meta / sessionID
                        $this->setValue($key, $value);

                    } else {
                        return false;
                    }
                }
            }

        } else {
            $this->setValue($key, $value);
        }
        return $this->lowLevelWrite();
    } // write



	//---------------------------------------------------------------------------
    public function writeMeta($key, $value)
    {
        if (isset($this->meta[LIZZY_LOCK_ALL])) { // entire DB is locked
            if (!$this->isMySessionID($this->meta[LIZZY_LOCK_ALL])) {
                return false;
            }
        }

        if ($this->dbMetaDBfile) {
            $meta = &$this->meta;
        } else {
            if (!isset($this->data[LIZZY_META])) {
                $this->data[LIZZY_META] = [];
            }
            $meta = &$this->data[LIZZY_META];
        }
        $meta[$key] = $value;
        return $this->lowLevelWrite();
    } // writeMeta




	//---------------------------------------------------------------------------
    public function append($newData)
    {
        $data = &$this->data;
        if (!is_array($data)) {
            return false;
        }
        if (!isset($newData[0])) {
            foreach ($newData as $k => $rec) {
                while (isset($data[$k])) { // just in case of multiple concurrent actions
                    $k = (is_int($k)) ? $k+1 : $k.'1'; //???
                }
                $data[$k] = $rec; //???
            }
        } else {
            $data = array_merge($data, $newData);
        }
        $this->dataModified = true;
        return $this->lowLevelWrite();
    } // append



	//---------------------------------------------------------------------------
    public function read($key = '*', $reportLockState = false)
    {
        if (!isset($this->data)) {
            $this->lowLevelRead();
        }
        $data = $this->data;
        if ($this->dbMetaDBfile) {
            $meta = &$this->meta;
        } else {
            if (!isset($this->data[LIZZY_META])) {
                $this->data[LIZZY_META] = [];
            }
            $meta = &$this->data[LIZZY_META];
        }
        if (!is_array($data)) {
            return null;
        }
        if ($key === '*') {     // return all data
            if ($reportLockState) {
                if (isset($data[LIZZY_META])) {
                    $mega = $data[LIZZY_META];
                    unset($data[LIZZY_META]);
                }
                foreach ($data as $key => $value) {
                    if (is_array($value)) { // 2D array
                        $row = $value;
                        foreach ($row as $k2 => $value) {
                            $k = "$key,$k2";
                            if (isset($meta[$k][LIZZY_LOCK][LIZZY_SID]) && ($meta[$k][LIZZY_LOCK][LIZZY_SID] != $this->sid)) {
                                // locked by some other user
                                $data[$key][$k2] = str_replace('**LOCKED**', '', $value) . '**LOCKED**';
                            }
                        }

                    } elseif (isset($meta[$key][LIZZY_LOCK][LIZZY_SID]) && ($meta[$key][LIZZY_LOCK][LIZZY_SID] != $this->sid)) {
                        // locked by some other user
                        $data[$key] = str_replace('**LOCKED**', '', $value) . '**LOCKED**';
                    }
                }
            }

            if (isset($data[LIZZY_META])) {   // make sure no sessionIds are passed on
                unset($data[LIZZY_META]);
            }
            $value = $data;

        } else {        // return specific data element
            if (is_string($key) && strpos($key, '/') !== false) { // access to nested element: 'a/b/c'
                $k = "\$meta['" . str_replace('/', "']['", $key) . "']";

                $expr = "if (isset($k)) { return $k; } else { return null; }";
                $value = eval("$expr");

            } else {
                $value = $this->getValue($key);
            }
        }
        return $value;
    } // read




    public function readMeta($key)
    {
        if ($this->dbMetaDBfile) {
            $meta = &$this->meta;
        } else {
            if (!isset($this->data[LIZZY_META])) {
                $this->data[LIZZY_META] = [];
            }
            $meta = &$this->data[LIZZY_META];
        }
        $value = isset($meta[$key]) ? $meta[$key] : false;
        return $value;
    } // read




    //---------------------------------------------------------------------------
    public function delete($key)
    {
        $modified = false;
        if (list($c, $r) = $this->array2DKey($key)) {      // 2-dimensional key
            if (isset($this->data[$r][$c])) {
                unset($this->data[$r][$c]);
                $modified = true;
            }
        } else {
            if (isset($this->data[$key])) {
                unset($this->data[$key]);
                $modified = true;
            }
        }
        if (isset($this->meta[$key])) {
            unset($this->meta[$key]);
            $modified = true;
        }
        if ($modified) {
            return $this->lowLevelWrite();
        }
    } // delete




    //---------------------------------------------------------------------------
    public function lastModified($key = false)
    {
        if ($key) {
            $meta = $this->meta;
            if (isset($meta[$key][LIZZY_MODIF_TIME])) {
                return $meta[$key][LIZZY_MODIF_TIME];
            } else {
                return 0;
            }
        } else {
            if (file_exists($this->dataFile)) {
                clearstatcache();
                return filemtime($this->dataFile);
            } else {
                return 0;
            }
        }
    } // lastModified




    //---------------------------------------------------------------------------
    public function initRecs($ids)
    {
        $data = $this->read('*');
        $keys = array_keys($data);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if (!in_array($id, $keys)) {
                    $this->setValue($id, '');
                }
            }
        } elseif (is_string($ids)) {
            if (!in_array($ids, $keys)) {
                $this->setValue($ids, '');
            }
        } else {
            return null;
        }
        $this->dataModified = true;
        return $this->lowLevelWrite();
    } // initRecs




    //---------------------------------------------------------------------------
    public function findRec($key, $value)
    {
        //TODO: extend for 2D data
        $data = &$this->data;

        foreach ($data as $datakey => $rec) {
            foreach ($rec as $k => $v) {
                if (($key == $k) && ($value == $v)) {
                    return $datakey;
                }
            }
        }
        return false;
    } // findRec





    //---------------------------------------------------------------------------
    public function isLocked($key)
    {
        if ($this->dbMetaDBfile) {
            $meta = &$this->meta;
        } else {
            $meta = &$this->data[LIZZY_META];
        }

        if (!is_array($meta) || !$key) {
            return false;
        }

        if (isset($meta[$key][LIZZY_LOCK][LIZZY_SID])) { // is data element locked?
            $sid = $meta[$key][LIZZY_LOCK][LIZZY_SID];
            if ($meta[$key][LIZZY_LOCK][LIZZY_SID] != $this->sid) { // locked by other sid?
                return true;   // element was locked, locking failed
            }
        }
        return false;
    } // isLocked




    //---------------------------------------------------------------------------
    public function lock($key)
    {
        if ($this->dbMetaDBfile) {
            $meta = &$this->meta;
        } else {
            $meta = &$this->data[LIZZY_META];
        }

        if (!is_array($meta) || !$key) {
            return false;
        }

        if ($key == 'all') {        // lock entire DB
            $meta[LIZZY_LOCK_ALL] = $this->getSessionID();

        } else {
            if (isset($meta[$key][LIZZY_LOCK][LIZZY_SID])) { // is data element locked?
                $sid = $meta[$key][LIZZY_LOCK][LIZZY_SID];
                if ($meta[$key][LIZZY_LOCK][LIZZY_SID] != $this->sid) { // locked by other sid?
                    return false;   // element was locked, locking failed
                } else {
                    return true;    // already locked by caller
                }
            }
            $meta[$key][LIZZY_LOCK][LIZZY_LOCK_TIME] = time();
            $meta[$key][LIZZY_LOCK][LIZZY_SID] = $this->sid;
        }
        $this->lowLevelWrite();

        return true;
    } // lock




    private function getSessionID()
    {
        if (!$sessionId = session_id()) {
            session_start();
            $sessionId = session_id();
            session_abort();
        }
        return $sessionId;
    } // getSessionID




    private function isMySessionID( $sid )
    {
        if (!$sessionId = session_id()) {
            session_start();
            $sessionId = session_id();
            session_abort();
        }
        return ($sid == $sessionId);
    } // isMySessionID




    //---------------------------------------------------------------------------
    public function unlock($key = true)
    {
        if ($this->dbMetaDBfile) {
            $meta = &$this->meta;
        } else {
            $meta = &$this->data[LIZZY_META];
        }
        if (!is_array($meta)) {
            return false;
        }

        if ($key === 'all') {        // lock entire DB
            unset($meta[LIZZY_LOCK_ALL]);

        } elseif ($key === '*') {    // unlock all records
            foreach ($meta as $id => $rec) {
                unset($meta[$id][LIZZY_LOCK]);
            }

        } elseif ($key === true) {    // unlock all owner's records
            $mySid = $this->sid;
            foreach ($meta as $key => $value) {
                if (isset($value[LIZZY_LOCK][LIZZY_SID]) && ($value[LIZZY_LOCK][LIZZY_SID] == $mySid)) {
                    unset($meta[$key][LIZZY_LOCK]);
                }
            }
        } else {
            if (isset($meta[$key][LIZZY_LOCK][LIZZY_SID]) &&
                ($meta[$key][LIZZY_LOCK][LIZZY_SID] == $this->sid)) {
                unset($meta[$key][LIZZY_LOCK]);
            }
        }
        return $this->lowLevelWrite();
    } // unlock




    //---------------------------------------------------------------------------
    private function lowLevelWrite()
    {   // returns true if successful
        $this->saveToRycleBin();

        if ($this->lockDB) {
            clearstatcache();
            if ($this->dbMetaDBfile) {
                $file = $this->dbMetaDBfile;
                $rawData = $this->encode(false, true);
            } else {
                $file = $this->dataFile;
                $rawData = $this->encode();
            }
            $fp = fopen($file, "r+");
            $try = 3;
            while ($try > 0) {
                if (flock($fp, LOCK_EX)) {  // acquire an exclusive lock on the (meta-)file
                    ftruncate($fp, 0) ;
                    fwrite($fp, $rawData);

                    if ($this->dbMetaDBfile && $this->dataModified) { // if metafile, save data as well
                        file_put_contents($this->dataFile, $this->encode());
                    }

                    flock($fp, LOCK_UN);    // release the lock
                    fclose($fp);
                    $this->dataModified = false;
                    return true;
                } else {
                    $try--;
                    usleep(100);
                }
            }
            return false;

        } else {    // skip locking DB

            if ($this->dbMetaDBfile) {
                file_put_contents($this->dbMetaDBfile, $this->encode(false, true));
                if ($this->dataModified) {
                    file_put_contents($this->dataFile, $this->encode());
                }
            } else {
                file_put_contents($this->dataFile, $this->encode());
            }
            $this->dataModified = false;
            return true;
        }
    } // lowLevelWrite




    //---------------------------------------------------------------------------
    private function lowLevelRead()
    {
        if (!$this->dataFile || !file_exists($this->dataFile)) {
            return false;
        }
        if ($this->lockDB) {
            clearstatcache();
            if ($this->dbMetaDBfile) {
                $file = $this->dbMetaDBfile;
            } else {
                $file = $this->dataFile;
            }
            $fp = fopen($file, 'r');
            if (flock($fp, LOCK_SH)) {
                $rawData = fread($fp, 100000);
                flock($fp, LOCK_UN);    // release the lock
                fclose($fp);
                if ($this->dbMetaDBfile) {  // separate meta file
                    $this->meta = $this->decode($rawData, LIZZY_DEFAULT_FILE_TYPE);

                    $rawData = file_get_contents($this->dataFile); // read payload separately
                }
                $encod = mb_detect_encoding($rawData, 'UTF-8, ISO-8859-1');
                if ($encod == 'ISO-8859-1') {
                    $rawData = utf8_encode($rawData);
                }
                $data = $this->decode($rawData);

            } else {
                $data =  false;
            }

        } else {    // skip DB locking
            $rawData = file_get_contents($this->dataFile);
            $encod = mb_detect_encoding($rawData, 'UTF-8, ISO-8859-1');
            if ($encod == 'ISO-8859-1') {
                $rawData = utf8_encode($rawData);
            }
            $data = $this->decode($rawData);
        }
        $this->data = $data;
        if ($this->dbMetaDBfile) {
            $this->meta = $this->decode(file_get_contents($this->dbMetaDBfile), LIZZY_DEFAULT_FILE_TYPE);
        } else {
            $this->meta = &$this->data[LIZZY_META]; // no sep meta file -> meta == data
        }
        return $data;
    } // lowLevelRead




    //---------------------------------------------------------------------------
    private function saveToRycleBin()
    {
        if (!$this->useRecycleBin || !$this->dataModified) {
            return;
        }

        $destPath = dirname($this->dataFile) . '/' . RECYCLE_BIN_PATH;
        if (!file_exists($destPath)) {
            mkdir($destPath, 0777);
        }
        $destFile = "$destPath/" . date('Y-m-d H.i.s') . ' ' . basename($this->dataFile);
        copy($file, $destFile);
    } // saveToRycleBin




    //---------------------------------------------------------------------------
    private function checkDB()
    {
        if (!$this->dataFile || !$this->lockDB) {
            return false;
        }

        if (!isset($this->meta)) {
            $this->lowLevelRead();
        }

        $this->resetTimedOutLocks();

        return true;
    } // checkDB




    //---------------------------------------------------------------------------
    private function resetTimedOutLocks()
    {
        $th = time() - $this->lockTimeout;
        $meta = &$this->meta;
        $modified = false;
        foreach ($meta as $id => $rec) {
            if (isset($rec[LIZZY_LOCK][LIZZY_LOCK_TIME])) {
                $t = $rec[LIZZY_LOCK][LIZZY_LOCK_TIME];
                if ($rec[LIZZY_LOCK][LIZZY_LOCK_TIME] < $th) {
                    unset($meta[$id][LIZZY_LOCK]);
                    $modified = true;
                }
            }
        }
        if ($modified) {
            $this->lowLevelWrite();
        }
    } // resetTimedOutLocks




    //---------------------------------------------------------------------------
    private function setValue($key, $value)
    {
        $data = &$this->data;
        if ($this->dbMetaDBfile) {
            $meta = &$this->meta;
        } else {
            $meta = &$this->data[LIZZY_META];
        }
        if (strpos($key, '/') === false) {      // regular value
            if (list($c, $r) = $this->array2DKey($key)) {      // 2-dimensional key
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $data[$r][$c][$k] = $v;
                    }
                } else {
                    $data[$r][$c] = $value;
                }
                $meta[$key][LIZZY_MODIF_TIME] = time();

            } else {                                    // normal key
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $data[$key][$k] = $v;
                    }
                } else {
                    $data[$key] = $value;
                }
                $meta[$key][LIZZY_MODIF_TIME] = time();

            }

        } else {                                // meta-value
            $expr = "\$data['" . str_replace('/', "']['", $key) . "'] = \$value;";
            eval("$expr");
        }
        $this->dataModified = true;
        return $data;
    } // setValue




    //---------------------------------------------------------------------------
    private function getValue($key)
    {
        if (list($c, $r) = $this->array2DKey($key)) {      // 2 dimensional data
            $value = isset($this->data[$r][$d]) ? $this->data[$r][$d] : null;

        } else {
            $value = isset($this->data[$key]) ? $this->data[$key] : null;
        }
        return $value;
    } // getValue




    //---------------------------------------------------------------------------
    private function parseArguments($args)
    {
        $dbFile = isset($args['dbFile']) ? $args['dbFile'] : '';
        $sid = isset($args['sid']) ? $args['sid'] : '';
        $lockDB = isset($args['lockDB']) ? $args['lockDB'] : false;
        $format = isset($args['format']) ? $args['format'] : '';
        $lockTimeout = isset($args['lockTimeout']) ? $args['lockTimeout'] : 120;
        $secure = isset($args['secure']) ? $args['secure'] : false;
        $useRecycleBin = isset($args['useRecycleBin']) ? $args['useRecycleBin'] : false;

        return [$dbFile, $sid, $lockDB, $format, $lockTimeout, $secure, $useRecycleBin];
    } // parseArguments




    //---------------------------------------------------------------------------
    public function array2DKey(&$key)
    {
        $key = str_replace(' ','', $key);
        $a = explode(',', $key);
        if (sizeof($a) > 1) {
            return $a;
        } else {
            return false;
        }
    } // array2DKey




    //---------------------------------------------------------------------------
    private function decode($rawData, $format = false)
    {
        $data = false;
        if (!$format) {
            $format = $this->format;
        }
        if ($format == 'json') {
            $rawData = str_replace(["\r", "\n", "\t"], '', $rawData);
            $data = json_decode($rawData, true);

        } elseif ($format == 'yaml') {
            $data = $this->convertYaml($rawData);

        } elseif (($format == 'csv') || ($this->format == 'txt')) {
            $data = $this->parseCsv($rawData);
        }
        if (!$data) {
            $data = array();
        }
        return $data;
    } // decode



    //---------------------------------------------------------------------------
    private function encode($format = false, $metaData = false)
    {
        if ($metaData) {
            $data = $this->meta;
            $format = LIZZY_DEFAULT_FILE_TYPE;
        } else {
            $data = &$this->data;
        }

        if (!$format) {
            $format = $this->format;
        }
        $encodedData = false;
        if ($format == 'json') {
            $encodedData = json_encode($data);
        } elseif ($format == 'yaml') {
            $encodedData = $this->convertToYaml($data);
        } elseif ($format == 'csv') {
            $encodedData = $this->arrayToCsv($data);
        }
        return $encodedData;
    } // encode



    //--------------------------------------------------------------
    private function convertYaml($str)
    {
        $data = null;
        if ($str) {
            $str = str_replace("\t", '    ', $str);
            try {
                $data = Yaml::parse($str);
            } catch(Exception $e) {
                die("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
            }
        }
        return $data;
    } // convertYaml




//--------------------------------------------------------------
    private function convertToYaml($data, $level = 3)
    {
        return Yaml::dump($data, $level);
    } // convertToYaml



//--------------------------------------------------------------
    function arrayToCsv($array, $quote = '"', $delim = ',')
    {
        $out = '';
        $nCols = 0;
        $nRows = sizeof($array);
        foreach ($array as $row) {
            $nCols = max($nCols, sizeof($row));
        }
        for ($r=0; $r < $nRows; $r++) {
            $rowStr = '';
            for ($c=0; $c < $nCols; $c++) {
                $elem = isset($array[$r][$c]) ? $array[$r][$c] : '';
                if (strpbrk($elem, $quote.$delim)) {
                    $elem = $quote . str_replace($quote, $quote.$quote, $elem) . $quote;
                }
                $rowStr .= "$elem,";
            }
            $out .= substr($rowStr, 0, -1)."\n";
        }
        return $out;
    } // arrayToCsv



//--------------------------------------------------------------
    function parseCsv($str, $delim = false, $enclos = false) {

        if (!$delim) {
            $delim = (substr_count($str, ',') > substr_count($str, ';')) ? ',' : ';';
            $delim = (substr_count($str, $delim) > substr_count($str, "\t")) ? $delim : "\t";
        }
        if (!$enclos) {
            $enclos = (substr_count($str, '"') > substr_count($str, "'")) ? '"': "'";
        }

        $lines = explode(PHP_EOL, $str);
        $array = array();
        foreach ($lines as $line) {
            if (!$line) { continue; }
            $array[] = str_getcsv($line, $delim, $enclos);
        }
        return $array;
    } // parseCsv



} // DataStorage


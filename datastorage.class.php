<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 * Simple file-based key-value database
 *
 * $db = new DataStorage($dbFile, $sid = '', $lockDB = false, $format = '')
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
*/

define('LIZZY_META', '_meta_');
define('LIZZY_LOCK', 'lock');
define('LIZZY_LOCK_TIME', 'time');
define('LIZZY_SID', 'sid');     // session ID
define('LIZZY_MODIF_TIME', 'modif');
define('LIZZY_LOCK_ALL', 'lock_all');

require_once SYSTEM_PATH.'vendor/autoload.php';


use Symfony\Component\Yaml\Yaml;

class DataStorage
{
	private $dataFile;
	private $sid;

 	//---------------------------------------------------------------------------
   public function __construct($dbFile, $sid = '', $lockDB = false, $format = '', $lockTimeout = 120, $secure = false)
    {
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
			    if (function_exists(fatalError)) {
                    fatalError("DataStorage: unable to create file '$dbFile'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                } else {
			        die("DataStorage: unable to create file '$dbFile'");
                }
			}
        }
        $this->sid = $sid;
        $this->lockDB = $lockDB;
        $this->format = ($format) ? $format : pathinfo($dbFile, PATHINFO_EXTENSION) ;
        $this->lockTimeout = $lockTimeout;
        $this->data = $this->lowLevelRead();

        $this->checkDB();   // make sure DB is initialized
        return;
    } // __construct



	//---------------------------------------------------------------------------
    public function lastModified($key = false)
    {
        if ($key) {
            $data = &$this->data;
            if (isset($data[LIZZY_META][$key][LIZZY_MODIF_TIME])) {
                return $data[LIZZY_META][$key][LIZZY_MODIF_TIME];
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
    public function write($key, $value = null)
    {
        $data = &$this->data;
        if (!is_array($data)) {
            return false;
        }
        if (is_array($key)) {
            if ($value) {
                $data = $key;   // overwrite all

            } else {
                $array = $key;
                foreach ($array as $key => $value) {
                    if (!isset($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_LOCK_TIME])) {
                        $data[$key] = $value;

                    } elseif (isset($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID]) && ($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID] == $this->sid)) {
                        $data[$key] = $value;

                    } else {
                        return false;
                    }
                }
            }

        } else {
            if (strpos($key, '/') === false) {      // regular value
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $data[$key][$k] = $v;
                    }
                } else {
                    $data[$key] = $value;
                }
                $data[LIZZY_META][$key][LIZZY_MODIF_TIME] = time();

            } else {                                // meta-value
                $expr = "\$data['" . str_replace('/', "']['", $key) . "'] = \$value;";
                eval("$expr");
            }
        }
        return $this->lowLevelWrite();
    } // write



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
                    $k = (is_int($k)) ? $k+1 : $k.'1';
                }
                $data[$k] = $rec;
            }
        } else {
            $data = array_merge($data, $newData);
        }
        return $this->lowLevelWrite();
    } // append



	//---------------------------------------------------------------------------
    public function read($key = '*', $reportLockState = false)
    {
        $data = &$this->data;
        if (!is_array($data)) {
            return null;
        }
        if ($key === '*') {
            if ($reportLockState) {
                foreach ($data as $key => $value) {
                    if (($key != LIZZY_META) &&  isset($data[LIZZY_META][$key]) &&  // if locked
                        ($data[LIZZY_META][$key][LIZZY_SID] != $this->sid)) {           //but not by client that initiated the lock:
                            $data[$key] = str_replace('**LOCKED**', '', $value) . '**LOCKED**';
                    }
                }
            }
            if (isset($data[LIZZY_META])) {   // make sure no sessionIds are passed on
                unset($data[LIZZY_META]);
            }
            $value = $data;

        } elseif (strpos($key, '/') !== false) {
            $k = "\$data['" . str_replace('/', "']['", $key) . "']";

            $expr = "if (isset($k)) { return $k; } else { return null; }";
            $value = eval("$expr");

        } elseif (isset($data[$key])) {
            $value = $data[$key];

        } else {
            $value =  null;
        }
        return $value;
    } // read



    //---------------------------------------------------------------------------
    public function delete($key)
    {
        if (isset($this->data[LIZZY_META][$key])) {
            unset($this->data[LIZZY_META][$key]);
        }

        if (isset($this->data[$key])) {
            unset($this->data[$key]);
            return $this->lowLevelWrite();
        }
    } // delete


        //---------------------------------------------------------------------------
    public function initRecs($ids)
    {
        $data = &$this->data;
        $keys = array_keys($data);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if (!in_array($id, $keys)) {
                    $data[$id] = '';
                }
            }
        } elseif (is_string($ids)) {
            if (!in_array($ids, $keys)) {
                $data[$ids] = '';
            }
        } else {
            return null;
        }
        return $this->lowLevelWrite();
    } // initRecs



    //---------------------------------------------------------------------------
    public function findRec($key, $value)
    {
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
        $data = &$this->data;

        if (!is_array($data) || !$key) {
            return false;
        }

        if (isset($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID])) { // is data element locked?
            $sid = $data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID];
            if ($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID] != $this->sid) { // locked by other sid?
                return true;   // element was locked, locking failed
            }
        }
        return false;
    } // lock


    //---------------------------------------------------------------------------
    public function lock($key)
    {
        $data = &$this->data;

        if (!is_array($data) || !$key) {
            return false;
        }

        if ($key == 'all') {        // lock entire DB
            $data[LIZZY_META][LIZZY_LOCK_ALL] = true;

        } else {
            if (isset($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID])) { // is data element locked?
                $sid = $data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID];
                if ($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID] != $this->sid) { // locked by other sid?
                    return false;   // element was locked, locking failed
                } else {
                    return true;    // already locked by caller
                }
            }
            $data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_LOCK_TIME] = time();
            $data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID] = $this->sid;
        }
        $this->lowLevelWrite();

        return true;
    } // lock



    //---------------------------------------------------------------------------
    public function unlock($key = true)
    {
        $data = &$this->data;
        if (!is_array($data)) {
            return false;
        }
        if (isset($data[LIZZY_META][LIZZY_LOCK_ALL]) && $data[LIZZY_META][LIZZY_LOCK_ALL]) { // entire DB was locked
            unset($data[LIZZY_META][LIZZY_LOCK_ALL]);

        }

        if ($key == 'all') {        // lock entire DB
            unset($data[LIZZY_META][LIZZY_LOCK_ALL]);

        } else
            if ($key === '*') {    // unlock all records
            foreach ($data[LIZZY_META] as $id => $rec) {
                unset($data[LIZZY_META][$id][LIZZY_LOCK]);
            }

        } elseif ($key === true) {    // unlock all owner's records
            $mySid = $this->sid;
            foreach ($data[LIZZY_META] as $key => $value) {
                if (isset($value[LIZZY_LOCK][LIZZY_SID]) && ($value[LIZZY_LOCK][LIZZY_SID] == $mySid)) {
                    unset($data[LIZZY_META][$key][LIZZY_LOCK]);
                }
            }
        } else {
            if (isset($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID]) &&
                ($data[LIZZY_META][$key][LIZZY_LOCK][LIZZY_SID] == $this->sid)) {
                unset($data[LIZZY_META][$key][LIZZY_LOCK]);
            }
        }
        return $this->lowLevelWrite();
    } // unlock




    //---------------------------------------------------------------------------
    private function lowLevelWrite()
    {   // returns true if successful
        $rawData = $this->encode();
        if ($this->lockDB) {
            clearstatcache();
            $fp = fopen($this->dataFile, "r+");
            $try = 3;
            while ($try > 0) {
                if (flock($fp, LOCK_EX)) {  // acquire an exclusive lock on the file
                    ftruncate($fp, 0) ;
                    fwrite($fp, $rawData);
                    flock($fp, LOCK_UN);    // release the lock
                    fclose($fp);
                    return true;
                } else {
                    $try--;
                    usleep(100);
                }
            }
            return false;

        } else {    // skip locking DB
            file_put_contents($this->dataFile, $rawData);
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
            $fp = fopen($this->dataFile, 'r');
            if (flock($fp, LOCK_SH)) {
                $rawData = fread($fp, 100000);
                flock($fp, LOCK_UN);    // release the lock
                fclose($fp);
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

        return $data;
    } // lowLevelRead




    //---------------------------------------------------------------------------
    private function checkDB()
    {
        if (!$this->dataFile || !$this->lockDB) {
            return false;
        }
        if (!isset($this->data[LIZZY_META])) {
            $this->data[LIZZY_META] = [];
        }

        $this->resetTimedOutLocks();

        return true;
    } // checkDB




    //---------------------------------------------------------------------------
    private function resetTimedOutLocks()
    {
        $th = time() - $this->lockTimeout;
        $meta = &$this->data[LIZZY_META];
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
    private function decode($rawData)
    {
        $data = false;
        if ($this->format == 'json') {
            $rawData = str_replace(["\r", "\n", "\t"], '', $rawData);
            $data = json_decode($rawData, true);

        } elseif ($this->format == 'yaml') {
            $data = $this->convertYaml($rawData);

        } elseif (($this->format == 'csv') || ($this->format == 'txt')) {
            $data = parseCsv($rawData);
        }
        if (!$data) {
            $data = array();
        }
        return $data;
    } // decode



    //---------------------------------------------------------------------------
    private function encode($format = false)
    {
        $data = &$this->data;
        if (!$format) {
            $format = $this->format;
        }
        $encodedData = false;
        if ($format == 'json') {
            $encodedData = json_encode($data);
        } elseif ($format == 'yaml') {
            $encodedData = $this->convertToYaml($data);
        } elseif ($format == 'csv') {
            $encodedData = arrayToCsv($data);
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


} // DataStorage


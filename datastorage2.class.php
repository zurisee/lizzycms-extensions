<?php
/*
 *	LZY - small and fast web-page rendering engine
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


if (!defined('LIZZY_DB')) {
    define('LIZZY_DB',  SYSTEM_PATH.'data/.lzy_db.sqlite');
    define('LZY_LOCK_ALL_DURATION_DEFAULT', 900.0); // 15 minutes
}

require_once SYSTEM_PATH.'vendor/autoload.php';


use Symfony\Component\Yaml\Yaml;

class DataStorage2
{
    private $lzyDb = null;
	protected $dataFile;
//	private $dataFile;
	private $tableName;
	private $data = null;
	private $rawData = null;
	private $sid;
	private $format;
	private $lockDB = false;
	private $defaultTimeout = 30; // [s]
	private $defaultPollingSleepTime = 500; // [ms]


    //--------------------------------------------------------------
    public function __construct($lzy, $args)
    {
        if (isset($lzy->lzyDb) && ($lzy->lzyDb !== null)) {   // open DB if not already opened:
            $this->lzyDb = $lzy->lzyDb;
        } else {
            $this->initLizzyDB();
        }

        $this->parseArguments($args);
        $this->initDbTable();
    } // __construct



    public function read()
    {
        return $this->getData();
    }



    public function readElement($key)
    {
        $data = $this->getData();

        if (strpos($key, ',') !== false) {
            $rec = $data;
            foreach (array_reverse(explode(',', $key)) as $k) {
                $k = trim($k);
                if (isset($rec[$k])) {
                    $rec = $rec[$k];
                } else {
                    return null;
                }
            }
            return $rec;
        }

        if (isset($data[$key])) {
            return $data[$key];
        } else {
            return null;
        }
    } // readElement



    public function append($newData)
    {
        if ($this->isLockDB()) {
            return false;
        }
        $data = $this->getData();
        $data = array_merge($data, $newData);
        return $this->update($data);
    } // append



    public function write($data)
    {
        if ($this->isLockDB()) {
            return false;
        }
        return $this->update($data);
    } // write



    public function writeElement($key, $value)
    {
        if ($this->isLockDB()) {
            return false;
        }
        $data = $this->getData();
        if (strpos($key, ',') !== false) {
            $keys = explode(',', $key);
            if ($this->format === 'csv') {
                $keys = array_reverse($keys);
            }
            $rec = &$data;
            foreach ($keys as $k) {
                $k = trim($k);
                if (!isset($rec[$k])) {
                    $rec[$k] = null;    // instantiate element if not existing
                }
                $rec = &$rec[$k];
            }
            $rec = $value;

        } else {
            $data[$key] = $value;
        }
        return $this->update($data);
    } // writeElement




    public function getRecStructure()
    {
        $rawData = $this->getRawData();
        $structure = $this->jsonDecode($rawData['structure']);
        return $structure;
    }




    public function isLockDB()
    {
        $rawData = $this->getRawData();
        $mySessionID = $this->getSessionID();
        if ($rawData['lockTime'] < (microtime(true) - LZY_LOCK_ALL_DURATION_DEFAULT)) {
            $rawData['lockedBy'] = '';
            $rawData['lockTime'] = 0.0;
            $this->updateMetaData($rawData);
            return false;   // lock timed out
        }
        if ($rawData['lockedBy'] !== '') {  // it's locked
            if ($rawData['lockedBy'] === $mySessionID) { // it's locked by myself
                return false;

            } else {    // it's locked by other session
                return true;
            }
        }
        return false;   // it's not locked
    } // lockDB




    public function lockDB()
    {
        $rawData = $this->getRawData();
        $mySessionID = $this->getSessionID();
        if ($rawData['lockedBy'] !== '') {
            if ($rawData['lockedBy'] === $mySessionID) {
                $rawData['lockTime'] = microtime(true);

            } else {
                if ($rawData['lockTime'] < (microtime(true) - LZY_LOCK_ALL_DURATION_DEFAULT)) {
                    $rawData['lockedBy'] = $mySessionID;
                    $rawData['lockTime'] = microtime(true);

                } else {
                    return false;
                }
            }
        } else {
            $rawData['lockedBy'] = $mySessionID;
            $rawData['lockTime'] = microtime(true);
        }
        $this->updateMetaData($rawData);
        return true;
    } // lockDB




    public function unLockDB($force = false)
    {
        $rawData = $this->getRawData();
        $mySessionID = $this->getSessionID();
        if ($rawData['lockedBy'] !== '') {
            if ($rawData['lockedBy'] === $mySessionID) {
                $rawData['lockTime'] = 0.0;
                $rawData['lockedBy'] = '';

            } elseif ($force) {
                $rawData['lockTime'] = 0.0;
                $rawData['lockedBy'] = '';

            } else {
                return false;
            }
        }
        $this->updateMetaData($rawData);
        return true;
    }




    //---------------------------------------------------------------------------
    public function lastModified()
    {
        $rawData = $this->getRawData();
        return $rawData['lastUpdate'];
    }




    //---------------------------------------------------------------------------
    public function checkNewData($lastUpdate, $returnJson = false)
    {
        $rawData = $this->getRawData();
        if ($rawData['lastUpdate'] > $lastUpdate) {
            $data = $this->getData();
            if ($returnJson) {
                $data['__lastUpdate'] = $rawData['lastUpdate'];
                $data = json_encode($data);
            }
            return $data;
        } else {
            return null;
        }
    }




    //---------------------------------------------------------------------------
    public function awaitChangedData($lastUpdate, $timeout = false, $pollingSleepTime = false)
    {
        $timeout = $timeout ? $timeout : $this->defaultTimeout;
        $pollingSleepTime = $pollingSleepTime ? $pollingSleepTime : $this->defaultPollingSleepTime;
        $pollingSleepTime = $pollingSleepTime*1000;
        $json = $this->checkNewData($lastUpdate, true);
        if ($json !== null) {
            return $json;
        }
        $tEnd = microtime(true) + $timeout - 0.01;

        while ($tEnd > microtime(true)) {
            $json = $this->checkNewData($lastUpdate, true);
            if ($json !== null) {
                return $json;
            }
            usleep($pollingSleepTime);
        }
        return '';
    } // awaitChangedData




// === private methods ===============
//    private function update($data, $isJson = false, $markModified = true)
    private function update($data, $isJson = false, $markModified = true, $structure = false)
    {
        $this->lzyDb->close();
        $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READWRITE);
        $this->lzyDb->exec('PRAGMA journal_mode = wal;'); // https://www.php.net/manual/de/sqlite3.exec.php

//        if ($isJson) {
//            $json = $data;
//        } else {
//            $json = json_encode($data);
//        }
        $json = $this->jsonEncode($data, $isJson);
//        $json = str_replace('"', '⌑⌇⌑', $json);
//TODO: use SQLITE3_TEXT instead -> https://www.php.net/manual/de/sqlite3stmt.bindparam.php
        $json = SQLite3::escapeString($json);
        $ftime = microtime(true);
        $modified = $markModified ? 1 : 0;

        if ($structure) {
//            $structure = $this->jsonEncode($structure);
            $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json", 
    "structure" = "$structure", 
    "lastUpdate" = $ftime, 
    'modified' = $modified;

EOT;
        } else {
            $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json", 
    "lastUpdate" = $ftime, 
    'modified' = $modified;

EOT;
        }

        $res = $this->lzyDb->query($sql);
        $this->lzyDb->close();
        $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READONLY);
        $this->rawData = $this->getRawData();

        return $res;
    } // update




    private function updateMetaData($rawData)
    {
        $this->lzyDb->close();
        $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READWRITE);
        $this->lzyDb->exec('PRAGMA journal_mode = wal;'); // https://www.php.net/manual/de/sqlite3.exec.php
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    'modified' = 1,
    'lockedBy' = '{$rawData['lockedBy']}',
    'lockTime' = '{$rawData['lockTime']}'

EOT;

        $res = $this->lzyDb->query($sql);
        $this->lzyDb->close();
        $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READONLY);
        $this->rawData = $this->getRawData();
    }




    //---------------------------------------------------------------------------
    private function initLizzyDB()
    {
        $lizzyDbFile = PATH_TO_APP_ROOT.LIZZY_DB;
        if (!file_exists($lizzyDbFile)) {
            preparePath($lizzyDbFile);
            touch($lizzyDbFile);
        }
        $this->lzyDb = new SQLite3($lizzyDbFile, SQLITE3_OPEN_READONLY);
    } // initLizzyDB




    //---------------------------------------------------------------------------
    private function initDbTable()
    {
        // 'dataFile' refers to a yaml or csv file that contains the original data source
        // each dataFile is copied into a table within the lizzyDB

        if ($this->secure && (strpos($this->dataFile, 'config/') !== false)) {
            return null;
        }

        // check data file
        if (!file_exists($this->dataFile)) {
            $path = pathinfo($this->dataFile, PATHINFO_DIRNAME);
            if (!file_exists($path)) {
                if (!mkdir($path, 0777, true) || !is_writable($path)) {
                    if (function_exists('fatalError')) {
                        fatalError("DataStorage: unable to create file '{$this->dataFile}'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                    } else {
                        die("DataStorage: unable to create file '{$this->dataFile}'");
                    }
                }
            }
            touch($this->dataFile);
        }

        // check whether dataFile-table exists:
        if ($this->tableName) {
            $tableName = $this->tableName;
        } else {
            $tableName = $this->deriveTableName();
            $this->tableName = $tableName;
        }

        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName';";
        $res = $this->lzyDb->query($sql);
        $table = $res->fetchArray(SQLITE3_ASSOC);
        if (!$table) {  // if table does not exist: create it and populate it with data from origFile
            $this->lzyDb->close();
            $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READWRITE);
            $sql = "CREATE TABLE IF NOT EXISTS \"$tableName\" (";
            $sql .= '"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,';
            $sql .= '"data" VARCHAR, "lastUpdate" REAL, "structure" VARCHAR, "origFile" VARCHAR, "modified" INTEGER, "lockedBy" VARCHAR, "lockTime" REAL)';
            $res = $this->lzyDb->query($sql);
            if ($res === false) {
                die("Error: unable to create table in lzyDB: '$tableName'");
            }
            $sql = <<<EOT
INSERT INTO "$tableName" ("data", "lastUpdate", "structure", "origFile", "modified", "lockedBy", "lockTime")
VALUES ("", 0.0, "", "{$this->dataFile}", 0, "", 0.0);
EOT;
            $res = $this->lzyDb->query($sql);
            if ($res === false) {
                die("Error: unable to initialize table in lzyDB: '$tableName'");
            }

            $res = $this->importFromFile( true );
            if ($res === false) {
                die("Error: unable to populate table in lzyDB: '$tableName'");
            }
            $this->lzyDb->close();
            $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READONLY);

        } else { // if table exists, check whether update necessary:
            $ftime = floatval(filemtime($this->dataFile));
            $rawData = $this->getRawData();
            if ($ftime > $rawData['lastUpdate']) {
                $res = $this->importFromFile();
                if ($res === false) {
                    die("Error: unable to update table in lzyDB: '$tableName'");
                }
            } else {
                $this->getData();
            }
        }

        return;
    } // initDbTable




    //--------------------------------------------------------------
    private function importFromFile($initial = false)
    {
        $lines = file($this->dataFile);
        $rawData = '';
        foreach ($lines as $line) {
            if ($line && ($line[0] !== '#') && ($line[0] !== "\n")) { // skip commented and empty lines
                $rawData .= $line;
            }
        }
        list($json, $structure) = $this->decode($rawData, false, true);
//        $json = $this->decode($rawData, false, true);
        if ($initial) {
            $this->update($json, true, false, $structure);
        } else {
            $this->update($json, true, false);
        }
    } // importFromFile




    //--------------------------------------------------------------
    private function exportToFile()
    {
        $rawData = $this->getRawData();
        if ($rawData['modified']) {
            $filename = $rawData['origFile'];

            if ($this->useRecycleBin) {
                require_once SYSTEM_PATH.'page-source.class.php';
                $ps = new PageSource;
                $ps->copyFileToRecycleBin($filename);
            }

            if ($this->format == 'yaml') {
                $this->writeToYamlFile($filename, $this->data);

            } elseif ($this->format == 'json') {
                file_put_contents($filename, $this->jsonEncode($this->data));
//                file_put_contents($filename, json_encode($this->data));

            } elseif ($this->format == 'csv') {
                $this->writeToCsvFile($filename, $this->data);
            }
        }
        return;
    } // exportToFile




    //--------------------------------------------------------------
    private function writeToYamlFile($filename, $data)
    {
        $yaml = Yaml::dump($data, 3);

        // retrieve header from original file:
        $lines = file($filename);
        $hdr = '';
        foreach ($lines as $line) {
            if ($line && ($line[0] !== '#')) {
                break;
            }
            $hdr .= "$line\n";
        }
        file_put_contents($filename, $hdr.$yaml);
    } // writeToYamlFile




    //--------------------------------------------------------------
    private function writeToCsvFile($filename, $array, $quote = '"', $delim = ',', $forceQuotes = true)
    {
        $out = '';
        foreach ($array as $row) {
            foreach ($row as $i => $elem) {
                if ($forceQuotes || strpbrk($elem, "$quote$delim")) {
                    $row[$i] = $quote . str_replace($quote, $quote.$quote, $elem) . $quote;
                }
                $row[$i] = str_replace(["\n", "\r"], ["\\n", ''], $row[$i]);
            }
            $out .= implode($delim, $row)."\n";
        }
        file_put_contents($filename, $out);
    } // writeToCsvFile




    private function getData()
    {
        $rawData = $this->getRawData();
        $json = $rawData['data'];
//TODO: use SQLITE3_TEXT instead -> https://www.php.net/manual/de/sqlite3stmt.bindparam.php
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = $this->jsonDecode($json);
//        $data = json_decode($json, true);
        $this->data = $data;
        return $data;
    } // getData




    protected function getRawData()
    {
        $query = "SELECT * FROM \"{$this->tableName}\"";
        $this->rawData = $this->lzyDb->querySingle($query, true);
        return $this->rawData;
    } // getRawData




    private function jsonEncode($data, $isAlreadyJson = false)
    {
        if ($isAlreadyJson) {
            $json = $data;
        } else {
            $json = json_encode($data);
        }
        $json = str_replace('"', '⌑⌇⌑', $json);
        return $json;
    }




    private function jsonDecode($json)
    {
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = json_decode($json, true);
        return $data;
    }




    //---------------------------------------------------------------------------
    private function parseArguments($args)
    {
        $this->dataFile = isset($args['dataFile']) ? $args['dataFile'] :
            (isset($args['dbFile']) ? $args['dbFile'] : ''); // for compatibility
        $this->sid = isset($args['sid']) ? $args['sid'] : '';
        $this->lockDB = isset($args['lockDB']) ? $args['lockDB'] : false;
        $this->format = isset($args['format']) ? $args['format'] : '';
        $this->lockTimeout = isset($args['lockTimeout']) ? $args['lockTimeout'] : 120;
        $this->secure = isset($args['secure']) ? $args['secure'] : false;
        $this->useRecycleBin = isset($args['useRecycleBin']) ? $args['useRecycleBin'] : false;
        $this->format = ($this->format) ? $this->format : pathinfo($this->dataFile, PATHINFO_EXTENSION) ;
        $this->tableName = isset($args['tableName']) ? $args['tableName'] : '';
        if ($this->tableName && !$this->dataFile) {
            $rawData = $this->getRawData();
            $this->dataFile = PATH_TO_APP_ROOT.$rawData["origFile"];
        }
        return;
    } // parseArguments




    //---------------------------------------------------------------------------
    private function decode($rawData, $format = false, $outputAsJson = false)
    {
        $data = false;
        $structure = [];
        if (!$format) {
            $format = $this->format;
        }
        if ($format === 'json') {
            $rawData = str_replace(["\r", "\n", "\t"], '', $rawData);
            if ($outputAsJson) {
                $this->data = $this->jsonDecode($rawData);
//                $this->data = json_decode($rawData, true);
//                $data = $rawData;

                $rec0 = $data[0];
                $structure['key'] = 'index';
                $structure['labels'] = array_values($rec0);
                $structure['types'] = array_fill(0, sizeof($rec0), 'string');
                $structure = $this->jsonEncode($structure);

                $data = [$rawData, $structure];
            } else {
                $data = $this->jsonDecode($rawData, true);
//                $data = json_decode($rawData, true);
            }

        } elseif ($format === 'yaml') {
            $data = $this->convertYaml($rawData);
            if ($outputAsJson) {
                if (isset($data["_structure"])) {
                    $structure = $data["_structure"];
                    unset($data["_structure"]);
                } else {
                    $rec0 = reset($data);
                    $key0 = (array_keys($data))[0];
                    if (preg_match('/^ \d{2,4} - \d\d - \d\d/x', $key0)) {
                        $structure['key'] = 'date';
                    } else {
                        $structure['key'] = 'string';
                    }
                    $structure['labels'] = array_keys($rec0);
                    $structure['types'] = array_fill(0, sizeof($rec0), 'string');
                }

                $this->data = $data;

                $structure = $this->jsonEncode($structure);
                $data = [$this->jsonEncode($data), $structure];
            }

        } elseif (($format == 'csv') || ($this->format == 'txt')) {
            $data = $this->parseCsv($rawData);
            if ($outputAsJson) {
                $this->data = $data;

                $rec0 = $data[0];
                $structure['key'] = 'index';
                $structure['labels'] = array_values($rec0);
                $structure['types'] = array_fill(0, sizeof($rec0), 'string');
                array_shift($data); // first row contains headers, so remove it from payload data

                $structure = $this->jsonEncode($structure);
                $data = [$this->jsonEncode($data), $structure];
            }

        }
        if (!$data) {
            $data = array();
        }
        return $data;
    } // decode




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
    private function parseCsv($str, $delim = false, $enclos = false) {

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
            $line = str_replace("\\n", "\n", $line);
            $array[] = str_getcsv($line, $delim, $enclos);
        }
        return $array;
    } // parseCsv



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


    protected function deriveTableName()
//    private function deriveTableName()
    {
//        $tableName = str_replace(['/', '.'], '_', preg_replace("/\.[^.]+$/", "", $this->dataFile));
        $tableName = str_replace(['/', '.'], '_', $this->dataFile);
        $tableName = preg_replace('|^[\./_]*|', '', $tableName);
        return $tableName; // remove leading '../...'
    }



    //---------------------------------------------------------------------------
    public function __destruct()
    {
        $this->exportToFile(); // saves data if modified
    } // __destruct

} // DataStorage


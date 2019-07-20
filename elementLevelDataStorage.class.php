<?php

if (!defined('LZY_META')) {
    define('LZY_META', '_meta');
}
define('LZY_LOCK', 'lock');
define('LZY_LOCK_TIME', 'time');
define('LZY_SID', 'sid');     // session ID
define('LZY_MODIF_TIME', 'modif');
define('LZY_DEFAULT_ELEM_LOCK_TIME', 120);

class ElementLevelDataStorage extends DataStorage2
{
    public function __construct($args)
    {
        if (is_string($args)) {
            $sid = session_id();
            $args = ['dataFile' => $args, 'sid' => $sid];
        } elseif (!isset($args['sid'])) {
            $args['sid'] = session_id();
        }
        $this->lockTimeout = isset($args['lockTimeout']) ? $args['lockTimeout'] : LZY_DEFAULT_ELEM_LOCK_TIME;
        $this->sid = $args['sid'];
        parent::__construct($args);
    }



    public function lockElement($key)
    {
        $meta = parent::getMetaData();

        if (isset($meta[$key][LZY_LOCK][LZY_SID])) { // is data element locked?
            if ($meta[$key][LZY_LOCK][LZY_SID] != $this->sid) { // locked by other sid?
                if ($meta[$key][LZY_LOCK][LZY_LOCK_TIME] < (time() - $this->lockTimeout)) {
                    // lock expired -> unlock
                    unset($meta[$key][LZY_LOCK]);

                } else {
                    return false;   // element was locked, locking failed
                }
            } else {
                return true;    // already locked by caller
            }
        }
        // ok, lock now:
        $meta[$key][LZY_LOCK][LZY_LOCK_TIME] = time();
        $meta[$key][LZY_LOCK][LZY_SID] = $this->sid;
        parent::updateMetaData($meta);
        return true;
    } // lockElement




    public function unLockElement($key = true)
    {
        $modified = false;
        $meta = parent::getMetaData();

        if (($key[0] === '*') || (strpos($key, 'all') === 0)) {    // unlock all records
            foreach ($meta as $key => $rec) {
                if ($key[0] != '_') {
                    unset($meta[$key][LZY_LOCK]);
                    $modified = true;
                }
            }

        } elseif ($key === true) {    // unlock all owner's records
            $mySid = $this->sid;
            foreach ($meta as $key => $value) {
                if (isset($value[LZY_LOCK][LZY_SID]) && ($value[LZY_LOCK][LZY_SID] == $mySid)) {
                    unset($meta[$key][LZY_LOCK]);
                    $modified = true;
                }
            }
        } else {
            if (isset($meta[$key][LZY_LOCK][LZY_SID]) &&
                ($meta[$key][LZY_LOCK][LZY_SID] == $this->sid)) {
                unset($meta[$key][LZY_LOCK]);
                $modified = true;
            }
        }
        if ($modified) {
            return parent::updateMetaData($meta);
        }
        return true;
    } // unLockElement



    public function isElementLocked($key)
    {
        $meta = parent::getMetaData();

        if (!is_array($meta) || !$key) {
            return false;
        }

        if (isset($meta[$key][LZY_LOCK][LZY_SID])) { // is data element locked?
            if ($meta[$key][LZY_LOCK][LZY_SID] != $this->sid) { // locked by other sid?
                if ($meta[$key][LZY_LOCK][LZY_LOCK_TIME] < (time() - $this->lockTimeout)) {
                    // lock expired -> unlock
                    unset($meta[$key][LZY_LOCK]);
                    parent::updateMetaData($meta);
                    return false;

                } else {
                    return true;   // element was locked
                }
            }
        }
        return false;
    } // isElementLocked




    public function elementLastModified($key)
    {
        $meta = parent::getMetaData();
        if (isset($meta[$key][LZY_MODIF_TIME])) {
            return $meta[$key][LZY_MODIF_TIME];
        } else {
            return PHP_INT_MAX;
        }
    } // elementLastModified



    public function readElement($key, $ref2D = false)
    {
        $value = null;
//        if (!$this->isElementLocked($key)) {
        if ($ref2D) {
            $value = parent::readElement($ref2D);
        } else {
            $value = parent::readElement($key);
        }
//        }
        return $value;
    } // readElement




    public function writeElement($key, $value, $ref2D = false)
    {
        if ($this->isElementLocked($key)) {
            return false;
        }
        if ($ref2D) {
            parent::updateElement($ref2D, $value);

        } else {
            parent::updateElement($key, $value);
        }

//        $data = parent::getData( true );
        $data = [];
        $data[LZY_META][$key][LZY_MODIF_TIME] = microtime(true);
        parent::update($data);
//        parent::updateMetaData($meta);
    } // writeElement



    //---------------------------------------------------------------------------
    public function initRecs($ids)
    {
        return true;

        $data = $this->read();
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
    } // initRecs


} // class ElementLevelDataStorage

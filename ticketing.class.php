<?php

define ('DEFAULT_TICKET_STORAGE_FILE', DATA_PATH.'_tickets.'.LZY_DEFAULT_FILE_TYPE);
define ('DEFAULT_TICKET_HASH_SIZE', 6);
define ('DEFAULT_TICKET_VALIDITY_TIME', 900);

/*
 * $validityPeriod:
 *      null = system default
 *      0    = infinite
 *      string
 */

class Ticketing
{
    public function __construct($options = [])
    {
        $dataSrc = isset($options['dataSrc']) ? $options['dataSrc'] : DEFAULT_TICKET_STORAGE_FILE;
        $this->hashSize = isset($options['hashSize']) ? $options['hashSize'] : DEFAULT_TICKET_HASH_SIZE;
        $this->defaultType = isset($options['defaultType']) ? $options['defaultType'] : 'generic';
        $this->unambiguous = isset($options['unambiguous']) ? $options['unambiguous'] : false;
        $this->defaultValidityPeriod = isset($options['defaultValidityPeriod']) ? $options['defaultValidityPeriod'] : DEFAULT_TICKET_VALIDITY_TIME;
        $this->defaultMaxConsumptionCount = isset($options['defaultMaxConsumptionCount']) ? $options['defaultMaxConsumptionCount'] : 1;
        $this->ds = new DataStorage($dataSrc, '', true);
        $this->purgeExpiredTickets();
    } // __construct




    public function createTicket($rec, $maxConsumptionCount = false, $validityPeriod = null, $type = false)
    {
        $ticket = $rec;
        $ticket['lzy_maxConsumptionCount'] = ($maxConsumptionCount !== false) ?$maxConsumptionCount : $this->defaultMaxConsumptionCount;
        $ticket['lzy_ticketType'] = $type ? $type : $this->defaultType;

        if ($validityPeriod === null) {
            $ticket['lzy_ticketValidTill'] = time() + $this->defaultValidityPeriod;

        } elseif (($validityPeriod === false) || ($validityPeriod <= 0)) {
            $ticket['lzy_ticketValidTill'] = PHP_INT_MAX;

        } elseif (is_string($validityPeriod)) {
            $ticket['lzy_ticketValidTill'] = strtotime( $validityPeriod );
            mylog('ticket till: '.date('Y-m-d', $ticket['lzy_ticketValidTill']));

        } else {
            $ticket['lzy_ticketValidTill'] = time() + $validityPeriod;
        }
        $ticketHash = $this->createHash();

        $this->ds->write($ticketHash, $ticket);

        return $ticketHash;
    } // createTicket




    public function findTicket($value, $key = false)
    {
        // finds a ticket that matches the given hash
        // if $key is provided, it finds a ticket that contains given data (i,e, key and value match)
        if ($key) {
            return $this->ds->findRec($key, $value, true);
        } else {
            return $this->ds->read($value); // $value assumed to be the hash
        }
    } // findTicket



    public function consumeTicket($ticketHash, $type = false)
    {
        $this->ds->lock($ticketHash);
        $ticketRec = $this->ds->read($ticketHash);

        if ($type && ($type !== $ticketRec['lzy_ticketType'])) {
            $ticketRec = false;

        } elseif ($ticketRec['lzy_ticketValidTill'] < time()) {      // ticket expired
            $this->ds->delete($ticketHash);
            $ticketRec = false;

        } else {
            $n = $ticketRec['lzy_maxConsumptionCount'];
            if ($n > 1) {
                $ticketRec['lzy_maxConsumptionCount'] = $n - 1;
                $this->ds->write($ticketHash, $ticketRec);
                $this->ds->unlock($ticketHash);
            } else {
                $this->ds->delete($ticketHash);
            }

            $lzy_ticketType = $ticketRec['lzy_ticketType'];

            unset($ticketRec['lzy_maxConsumptionCount']);  // don't return private properties
            unset($ticketRec['lzy_ticketValidTill']);

            if ($lzy_ticketType === 'sessionVar') {     // type 'sessionVar': make ticket available in session variable
                $_SESSION['lizzy']['ticket'] = $ticketRec;
            }
            unset($ticketRec['lzy_ticketType']);
        }
        return $ticketRec;
    } // consumeTicket



    private function createHash()
    {
        do {
            $hash = chr(random_int(65, 90));  // first always a letter
            $hash .= strtoupper(substr(sha1(random_int(0, PHP_INT_MAX)), 0, $this->hashSize - 1));  // letters and digits
        } while ($this->unambiguous && strpbrk($hash, '0O'));
        return $hash;
    } // createHash



    private function purgeExpiredTickets()
    {
        $lastPurge = $this->ds->readMeta('lastPurge');
        if ($lastPurge > (time() - 3600)) { // perform purge only once per hour at most
            return;
        }
        $this->ds->doLockDB();
        $tickets = $this->ds->read();
        $now = time();
        foreach ($tickets as $key => $ticket) {
            if ($ticket['lzy_ticketValidTill'] < $now) {    // has expired
                $this->ds->delete($key);
            }
        }
        $this->ds->writeMeta('lastPurge', time());
        $this->ds->doUnlockDB();
    } // purgeExpiredTickets

} // Ticketing
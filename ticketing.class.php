<?php

define ('DEFAULT_TICKET_STORAGE_FILE', CACHE_PATH.'tickets.yaml');
define ('DEFAULT_TICKET_HASH_SIZE', 6);
define ('DEFAULT_TICKET_VALIDITY_TIME', 900);

/*
 * $validityPeriod:
 *      null = system default
 *      0    = infinite
 */

class Ticketing
{
    public function __construct($options = [])
    {
        $dataSrc = isset($options['dataSrc']) ? $options['dataSrc'] : DEFAULT_TICKET_STORAGE_FILE;
        $this->hashSize = isset($options['hashSize']) ? $options['hashSize'] : DEFAULT_TICKET_HASH_SIZE;
        $this->defaultType = isset($options['defaultType']) ? $options['defaultType'] : 'generic';
        $this->defaultValidityPeriod = isset($options['defaultValidityPeriod']) ? $options['defaultValidityPeriod'] : DEFAULT_TICKET_VALIDITY_TIME;
        $this->ds = new DataStorage($dataSrc, '', true);
        $this->purgeExpiredTickets();
    } // __construct




    public function createTicket($rec, $maxConsumptionCount = 1, $validityPeriod = null, $type = false)
    {
        $ticket = $rec;
        $ticket['lzy_maxConsumptionCount'] = $maxConsumptionCount;
        $ticket['lzy_ticketType'] = $type ? $type : $this->defaultType;

        if ($validityPeriod === null) {
            $ticket['lzy_ticketValidTill'] = time() + $this->defaultValidityPeriod;

        } elseif (($validityPeriod === false) || ($validityPeriod <= 0)) {
            $ticket['lzy_ticketValidTill'] = PHP_INT_MAX;

        } else {
            $ticket['lzy_ticketValidTill'] = time() + $validityPeriod;
        }
        $ticketHash = $this->createHash();

        $this->ds->write($ticketHash, $ticket);

        return $ticketHash;
    } // createTicket




    public function findTicket($ticketHash)
    {
        return $this->ds->findRec($ticketHash);
    } // findTicket



    public function consumeTicket($ticketHash)
    {
        $this->ds->lock($ticketHash);
        $ticket = $this->ds->read($ticketHash);

        if ($ticket['lzy_ticketValidTill'] < time()) {      // ticket expired
            $this->ds->delete($ticketHash);
            $ticket = false;

        } else {
            $n = $ticket['lzy_maxConsumptionCount'];
            if ($n > 1) {
                $ticket['lzy_maxConsumptionCount'] = $n - 1;
                $this->ds->write($ticketHash, $ticket);
                $this->ds->unlock($ticketHash);
            } else {
                $this->ds->delete($ticketHash);
            }
            unset($ticket['lzy_maxConsumptionCount']);  // don't return private properties
            unset($ticket['lzy_ticketType']);
            unset($ticket['lzy_ticketValidTill']);
        }
        return $ticket;
    } // consumeTicket



    private function createHash()
    {
        $hash = chr(rand(65, 90));  // first always a letter
        $hash .= strtoupper(substr(sha1(rand()), 0, $this->hashSize-1));  // letters and digits
        return $hash;
    } // createHash



    private function purgeExpiredTickets()
    {
        $this->ds->lock('all');
        $tickets = $this->ds->read();
        $now = time();
        foreach ($tickets as $key => $ticket) {
            if ($ticket['lzy_ticketValidTill'] < $now) {    // has expired
                $this->ds->delete($key);
            }
        }
        $this->ds->unlock('all');
    } // purgeExpiredTickets

} // Ticketing
<?php

define('DEFAULT_POLLING_TIME', 60);

$GLOBALS['lizzy']['liveDataInx'] = 0;
$GLOBALS['lizzy']['liveDataInitialized'] = false;

class LiveData
{
    protected $args = [];
    protected $dataSelectors = [];
    protected $targetSelectors = [];

    public function __construct($lzy, $args = [])
    {
        $this->lzy = $lzy;
        $GLOBALS['lizzy']['liveDataInx']++;
        $this->setInx = $GLOBALS['lizzy']['liveDataInx'];
        $this->inx = 0; // index per set
        $this->args = $args;
    } // __construct



    public function render( $args = [], $returnAttrib = false )
    {
        $args = array_merge($this->args, $args);
        $this->init($args);

        $dataSelectors = explodeTrim('|', $this->dataSelector);
        $n = sizeof($dataSelectors);

        $targetSelectors = explodeTrim('|', $this->targetSelector);
        $nT = sizeof($targetSelectors);

        $values = [];
        $setId = "set$this->setInx";

        $tickRec = [];
        if (@$args['tickRecCustomFields']) {
            $tickRec["set$this->setInx"] = $args['tickRecCustomFields'];
        }

        // dataSelector can be scalar or array:
        if (sizeof($dataSelectors) === 1) {                  // scalar value:
            if ($nT === 0) {
                $targetSelectors[0] = "lzy-live-data-$this->setInx";
            }
            $tickRec[ "set$this->setInx" ][ $this->inx ] = $dataSelectors[0];

            $tickRec[ $setId ]['_dataSource'] = $this->dataSource;
            $tickRec[ $setId ]['_pollingTime'] = intval($this->polltime);

            if ($dataSelectors[0] === '*') {
                $values[0] = $this->db->read();
            } else {
                $values[0] = $this->db->readElement($dataSelectors[0]);
            }

        } else {                                            // array value:
            for ($i=$nT; $i<$n; $i++) { // add targetSelectors that are not explicitly supplied:
                $targetSelectors[$i] = "lzy-live-data-$this->setInx-" . ($i+1);
            }

            foreach ($dataSelectors as $dataSelector) {
                $tickRec[ "set$this->setInx" ][ $this->inx ] = $dataSelector;
                $values[] = $this->db->readElement($dataSelector);
                $this->inx++;
            }
            $tickRec[ $setId ]['_dataSource'] = $this->dataSource;
            $tickRec[ $setId ]['_supportBlobType'] = @$this->supportBlobType;
            $tickRec[ $setId ]['_pollingTime'] = intval($this->polltime);
        }

        $this->dataSelectors = $dataSelectors;
        $this->targetSelectors = $targetSelectors;

        $tck = new Ticketing(['defaultType' => 'live-data', 'defaultMaxConsumptionCount' => false]);
        if ($this->ticketHash && $tck->ticketExists($this->ticketHash)) {
            $ticketHash = $tck->createHash(true);
            $tck->updateTicket($this->ticketHash, $tickRec);
        } else {
            $ticketHash = $tck->createTicket($tickRec, false, 86400);
        }
        $dataSrcRef = "$ticketHash:set$this->setInx";

        if ($returnAttrib) {
            $str = " data-datasrc-ref='$dataSrcRef'";
        } else {
            $str = $this->renderHTML($dataSrcRef, $values);
        }

        return $str;
    } // render



    public function renderJs( $inject = true, $execInitialDataUpload = true ) {
        $execInitialDataUpload = $execInitialDataUpload? 'true': 'false';
        $activateWatchdog = $this->watchdog? 'true': 'false';
        $jq = <<<EOT

if ($('[data-datasrc-ref]').length && (typeof LiveData !== 'undefined')) {
    liveDataInit( $execInitialDataUpload, $activateWatchdog );
}

EOT;
        if ($inject && !$GLOBALS['lizzy']['liveDataInitialized']) {
            $this->lzy->page->addJq( $jq );
            $GLOBALS['lizzy']['liveDataInitialized'] = true;
        }
        return $jq;
    } // renderJs




    private function init($args)
    {
        if (isset($args['dataSource'])) {
            $this->dataSource = $args['dataSource'];
        } elseif (isset($args['dataSrc'])) {
            $this->dataSource = $args['dataSrc'];
        } else {
            $this->dataSource = (isset($args['file'])) ? $args['file'] : false;
        }

        if (!$this->dataSource) {
            exit("Error: argument 'dataSource' missing in call to LiveData");
        }
        $this->dataSource = makePathRelativeToPage($this->dataSource, true);

        if (isset($args['dataSelector'])) {
            $this->dataSelector = $args['dataSelector'];
        } else {
            $this->dataSelector = (isset($args['dataSel'])) ? $args['dataSel'] : '*,*';
        }

        $this->ticketHash = (isset($args['ticketHash'])) ? $args['ticketHash'] : false;

        if (isset($args['targetSelector'])) {
            $this->targetSelector = $args['targetSelector'];
        } else {
            $this->targetSelector = (isset($args['targetSel'])) ? $args['targetSel'] : false;
        }

        $this->polltime = (isset($args['pollingTime'])) ? $args['pollingTime'] : DEFAULT_POLLING_TIME;

        $this->output = (isset($args['output'])) ? $args['output'] : true;
        $this->mode = (isset($args['mode'])) ? $args['mode'] : false;
        $this->manual = (isset($args['manual'])) ? $args['manual'] : false;
        $this->initJs = (isset($args['initJs'])) ? $args['initJs'] : false;
//        $this->initJs = (isset($args['autoInit'])) ? $args['autoInit'] : ((isset($args['initJs'])) ? $args['initJs'] : false);
        $this->execInitialDataUpload = (isset($args['execInitialDataUpload'])) ? $args['execInitialDataUpload'] : true;

        $this->watchdog = (isset($args['watchdog'])) ? $args['watchdog'] : false;

        if ($this->manual !== 'silent') {
            $this->manual = !$this->output || (strpos($this->mode, 'manual') !== false);
        }
        $this->callback = (isset($args['callback'])) ? $args['callback'] : false;
        $this->postUpdateCallback = (isset($args['postUpdateCallback'])) ? $args['postUpdateCallback'] : false;

        if ($this->initJs) {
            $this->renderJs( true, $this->execInitialDataUpload);
        }

        $this->db = new DataStorage2([
            'dataFile' => $this->dataSource,
            'supportBlobType' => @$this->supportBlobType,
        ]);

        $_SESSION['lizzy']['hasLockedElements'] = false;
        $_SESSION['lizzy']['ajaxServerAbort'] = false;
    } // init




    private function renderHTML($ticketHash, $values)
    {
        if ($this->manual) {
            return " data-datasrc-ref='$ticketHash'";
            $str = <<<EOT
<div class='lzy-live-data-placeholder lzy-dispno' data-datasrc-ref="$ticketHash"></div>
EOT;
            return $str;
        }
        
        $str = '';
        $callback = '';
        if ($this->callback) {
            $callback = " data-live-callback='$this->callback'";
        }
        $postUpdateCallback = '';
        if ($this->postUpdateCallback) {
            $postUpdateCallback = " data-live-post-update-callback='$this->postUpdateCallback'";
        }
        // render data elements:
        foreach ($this->dataSelectors as $i => $dataSelector) {
            $k = $i + 1;
            $id = "id='lzy-live-data-$this->setInx-$k'";
            $class = "class='lzy-live-data'";
            $value = $values[$i];
            $str .= <<<EOT
<span $id $class data-ref="$dataSelector"$callback$postUpdateCallback>$value</span>

EOT;
        }

        // render data-datasrc-ref element:
        $str = <<<EOT
    <div class='lzy-live-data-wrapper' data-datasrc-ref="$ticketHash"$callback$postUpdateCallback>
$str
    </div>
EOT;
        return $str;
    } // renderHTML

} // class


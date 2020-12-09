<?php

define('DEFAULT_POLLING_TIME', 60);

$GLOBALS['lizzy']['liveDataInx'][ $GLOBALS["globalParams"]["pagePath"] ] = 0;

class LiveData
{
    protected $args = [];
    protected $dataSelectors = [];
    protected $targetSelectors = [];

    public function __construct($lzy, $args = [])
    {
        $this->lzy = $lzy;
        $pagePath = $GLOBALS["globalParams"]["pagePath"];
        $GLOBALS['lizzy']['liveDataInx'][ $pagePath ]++;
        $this->setInx = $GLOBALS['lizzy']['liveDataInx'][ $pagePath ];
        $this->inx = 1;
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

        $tickRec = @$args['tickRecCustomFields']? $args['tickRecCustomFields'] : [];
        $values = [];

        // dataSelector can be scalar or array:
        if (sizeof($dataSelectors) === 1) {                  // scalar value:
            if ($nT === 0) {
                $targetSelectors[0] = "lzy-live-data-$this->setInx";
            }
            $this->addTicketRec($targetSelectors[0], $dataSelectors[0], $this->polltime, $tickRec);
            if ($dataSelectors[0] === '*') {
                $values[0] = $this->db->read();
            } else {
                $values[0] = $this->db->readElement($dataSelectors[0]);
            }

        } else {                                            // array value:
            for ($i=$nT; $i<$n; $i++) { // add targetSelectors that are not explicitly supplied:
                $targetSelectors[$i] = "lzy-live-data-$this->setInx-" . ($i+1);
            }

            foreach ($dataSelectors as $i => $dataSelector) {
                $targetSelector = isset($targetSelectors[$i])? $targetSelectors[$i]: "lzy-live-data-$this->setInx-$this->inx";
                $this->addTicketRec($targetSelector, $dataSelector, $this->polltime, $tickRec, $i);
                $values[] = $this->db->readElement($dataSelector);
                $this->inx++;
            }
        }

        $this->dataSelectors = $dataSelectors;
        $this->targetSelectors = $targetSelectors;

        $tick = new Ticketing(['defaultType' => 'live-data']);
        $ticket = $tick->createTicket($tickRec, 99, 86400);
        $ticket .= ":set$this->setInx";

        if ($returnAttrib) {
            $str = " data-lzy-data-ref='$ticket'";
        } else {
            $str = $this->renderHTML($ticket, $values);
        }
        return $str;
    } // render




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
            $this->dataSelector = (isset($args['dataSel'])) ? $args['dataSel'] : false;
        }

        $this->dynamicArg = (isset($args['dynamicArg'])) ? $args['dynamicArg'] : false;

        if (isset($args['targetSelector'])) {
            $this->targetSelector = $args['targetSelector'];
        } else {
            $this->targetSelector = (isset($args['targetSel'])) ? $args['targetSel'] : false;
        }

        $this->polltime = (isset($args['pollingTime'])) ? $args['pollingTime'] : DEFAULT_POLLING_TIME;

        $this->output = (isset($args['output'])) ? $args['output'] : true;
        $this->mode = (isset($args['mode'])) ? $args['mode'] : false;
        $this->manual = (isset($args['manual'])) ? $args['manual'] : false;

        if ($this->manual !== 'silent') {
            $this->manual = !$this->output || (strpos($this->mode, 'manual') !== false);
        }
        $this->callback = (isset($args['callback'])) ? $args['callback'] : false;
        $this->postUpdateCallback = (isset($args['postUpdateCallback'])) ? $args['postUpdateCallback'] : false;

        if ($this->manual) {
            if (($this->dataSelector === false) || ($this->dataSelector === '')) {
                exit( "Error: argument ``dataSelector`` not specified.");
            }
        }

        $this->db = new DataStorage2([ 'dataFile' => $this->dataSource ]);

        $_SESSION['lizzy']['hasLockedElements'] = false;
    } // init



    private function addTicketRec($targetSelector, $dataSelector, $pollTime, &$tickRec, $inx = 0)
    {
        $targetSelector = $this->deriveTargetSelector($targetSelector, $dataSelector, $tickRec);
        $this->targetSelector = $targetSelector;
        $recInx = "e$this->inx";
        $tickRec["set$this->setInx"][$recInx] = [
            'dataSource' => $this->dataSource,
            'dataSelector' => $dataSelector,
            'targetSelector' => $targetSelector,
            'pollingTime' => intval($pollTime),
        ];
    } // addTicketRec



    private function deriveTargetSelector($targetSelector, $dataSelector, $tickRec)
    {
        if (!$targetSelector) {
            $targetSelector = preg_replace('/\{(.*?)\},/', "$1", $dataSelector);
            $targetSelector = str_replace([',','][', '[', ']'], ['-','-','',''], $targetSelector);
            $targetSelector = '#liv-'.strtolower(str_replace(' ', '-', $targetSelector));
            if ($tickRec) {
                $existingIds = array_map(function ($e) { return $e['targetSelector']; }, $tickRec);
                if (in_array($targetSelector, $existingIds)) {
                    if ($this->setInx > 1) {
                        $targetSelector .= "-$this->setInx";
                    }
                    $targetSelector .= "-$this->inx";
                }
            }
        }
        $c0 = $targetSelector[0];
        if (($c0 !== '#') && ($c0 !== '.')) {
            $targetSelector = "#$targetSelector";
        }
        return $targetSelector;
    } // deriveTargetSelector




    private function renderHTML($ticket, $values)
    {
        $str = '';
        $dynamicArg = '';
        if ($this->dynamicArg) {
            $dynamicArg = " data-live-data-param='$this->dynamicArg'";
        }

        $callback = '';
        if ($this->callback) {
            $callback = " data-live-callback='$this->callback'";
        }
        $postUpdateCallback = '';
        if ($this->postUpdateCallback) {
            $postUpdateCallback = " data-live-post-update-callback='$this->postUpdateCallback'";
        }

        // normally, this macro renders visible output directly.
        // 'mode: manual' overrides this -> just renders infrastructure, you place the visible code manually into your page
        // e.g. <span id="my-id""></span>
        if ($this->manual) {
            $comment = ($this->manual === 'silent') ?'' : '<!-- live-data manual mode -->';
            $str = <<<EOT
<span class='lzy-live-data disp-no' data-lzy-data-ref="$ticket"$dynamicArg$callback$postUpdateCallback>$comment</span>
EOT;

        } else {
            foreach ($this->targetSelectors as $i => $targetSelector) {
                $selector = ltrim($targetSelector, '#');
                if ($targetSelector[0] === '#') {
                    $selector = "id='$selector' class='lzy-live-data'";
                } elseif ($targetSelector[0] === '.') {
                    $selector = "class='lzy-live-data $selector'";
                } else {
                    $selector = "id='$targetSelector' class='lzy-live-data'";
                }
                $str .= <<<EOT
    <span $selector data-lzy-data-ref="$ticket"$dynamicArg$callback$postUpdateCallback>{$values[$i]}</span>

EOT;
            }
        }
        return $str;
    } // renderHTML

} // class


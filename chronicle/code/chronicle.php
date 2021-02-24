<?php
define('DEFAULT_CHRONICLE_DATA_FILE', '~page/chronicle.yaml');
define('ENTRY_MARKER_TEMPLATE',         '#### %ts %un');

$GLOBALS['lizzy']['chronicleLiveDataInitialized'] = false;

$response = getCliArg('chronicle');
if ($response) {
    $bEnd = new ChronicleBackend();
    $bEnd->handleResponse( $response );
}


require_once SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';

$this->page->addModules('~/css/_chronicle.css,~sys/extensions/chronicle/js/chronicle.js');
//$this->readTransvarsFromFile( resolvePath("~ext/$macroName/config/vars.yaml"), false, true);

$GLOBALS['lizzy']['chronicleCount'] = 0;

$page->addJq( "initChronicle();" );

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$dataSource = $this->getArg($macroName, 'dataSource', 'Specifies where to store entered data.', '');

	if ($dataSource === 'help') {
        $this->getArg($macroName, 'dataSelector', '(optional) If set, defines the dataKey into the DB.', '');
        $this->getArg($macroName, 'editableBy', '[true|false|loggedin|privileged|admins] If set, defines who can enter data.', '');
        $this->getArg($macroName, 'id', '(optional) Id applied to the widget wrapper.', '');
        $this->getArg($macroName, 'class', '(optional) Class applied to the widget wrapper.', '');
        $this->getArg($macroName, 'liveData', 'If true, data values are immediately updated if the database on the host is modified.', '');
        $this->getArg($macroName, 'watchdog', 'If true, activates liveData\'s watchdog-mechanism.', '');
        $this->getArg($macroName, 'mode', '[singleline|markdown] Sets the mode, default: markdown&multiline.', '');
        $this->getArg($macroName, 'entryAggregationPeriod', '(optional) Defines the time in sec during which entries by one user are aggregated and not preceded by a new entry marker (default: 300s resp. 5min).', '');
        $this->getArg($macroName, 'entryMarkerTemplate', '(optional) Defines the entry marker, patter "%ts" is replaced by a timestamp and "%un" by the current user-name. (default: "#### %ts %un").', '');
        $this->getArg($macroName, 'useRecycleBin', '[true|false] If true, previous values will be saved in a recycle bin rather than discared (default: false)', '');
        $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);    return '';
    }

    $args = $this->getArgsArray($macroName);

    if (isset($args['disableCaching'])) {
        unset($args['disableCaching']);
    }

    $chron = new Chronicle($this->lzy, $args);
    $out = $chron->render();

    return $out;

}); // addMacro




class Chronicle extends LiveData
{
    public function __construct($lzy, $args)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
        $this->args = $args;
    } // __construct



    public function render( $args = [], $returnAttrib = false )
    {
        if (isset($_SESSION['lizzy']['ajaxServerAbort'])) {
            unset( $_SESSION['lizzy']['ajaxServerAbort'] );
        }
        $GLOBALS['lizzy']['chronicleCount']++;
        $inx = $this->inx = $GLOBALS['lizzy']['chronicleCount'];

        $args = $this->parseArgs( $args );
        $args['tickRecCustomFields'] = [
            '_entryMarkerTemplate'    => $this->entryMarkerTemplate,
        ];
        parent::__construct( $this->lzy, $args );

        $dataSrcRef = $this->initDataRef( $args );
        $dataRef = "data-ref='$this->dataSelector'";

        $db = new DataStorage2( $args['dataSource'] );
        if (!$db) {
            die("chronicle DB not found");
        }

        // check dataRef for indirect addressing of data element:
        if (strpos('=.#', $this->dataSelector[0]) !== false) {
            $text = '⌛';
        } else {
            $text = $db->readElement($this->dataSelector);
        }

        $callbackAttr = '';
        if ($this->liveData) {
            $callbackAttr = ' data-live-pre-update-callback="chronicleLiveDataCallback"';
        }

        $pre = '';
        if ($this->compileMd) {
            $text = compileMarkdownStr($text);
            $this->class .= ' lzy-compile-md';
        } else {
            $pre = ' lzy-chronicle-presentation-pre';
        }

        $html = <<<EOT
    <div id="$this->id" class="$this->class" $dataSrcRef $callbackAttr>
		<div class="lzy-chronicle-presentation-wrapper lzy-scroll-hints">
		    <div class=" lzy-chronicle-presentation$pre" $dataRef aria-live="polite">
$text
            </div>
		</div>
EOT;
        if ($this->editableBy) {
            $html .= <<<EOT

		<div  class="lzy-chronicle-send">
        	<button id='lzy-chronicle-send-btn-$inx' class='lzy-button'><span class='lzy-icon lzy-icon-send'></span></button>
		</div><!-- /.lzy-chronicle-send -->

EOT;

            if ($this->multiline) {
                $html .= <<<EOT
        <div class="lzy-chronicle-entry-wrapper">
            <div class='lzy-textarea-autogrow'>
                <textarea class="lzy-chronicle-entry lzy-chronicle-entry-multiline" onInput='this.parentNode.dataset.replicatedValue = this.value'></textarea>
            </div>
		</div>
    </div>

EOT;
            } else {
                $html .= <<<EOT
        <div class="lzy-chronicle-entry-wrapper">
            <input class="lzy-chronicle-entry lzy-chronicle-entry-singleline" />
		</div>
    </div>

EOT;
            }
        } // editableBy

        return $html;
    } // render



    private function parseArgs( $args )
    {
        if ($args) {
            $args = array_merge($this->args, $args);
        } else {
            $args = $this->args;
        }
        $this->dataSource = $args['dataSource'] = (isset($args['dataSource']) && $args['dataSource']) ? $args['dataSource'] : DEFAULT_CHRONICLE_DATA_FILE;
        $this->dataSelector = $args['dataSelector'] = isset($args['dataSelector']) ? $args['dataSelector'] : "chronicle-$this->inx";
        $this->id = $args['id'] = isset($args['id']) ? $args['id'] : "lzy-chronicle-$this->inx";
        $this->class = $args['class'] = isset($args['class']) ? $args['class'] : "lzy-chronicle lzy-chronicle-$this->inx";
        $this->liveData = $args['liveData'] = isset($args['liveData']) ? $args['liveData'] : false;
        $this->watchdog = $args['watchdog'] = isset($args['watchdog']) ? $args['watchdog'] : false;
        $this->editableBy = $args['editableBy'] = isset($args['editableBy']) ? $args['editableBy'] : true;
        $this->useRecycleBin = $args['useRecycleBin'] = isset($args['useRecycleBin']) ? $args['useRecycleBin'] : false;
        $this->entryMarkerTemplate = $args['entryMarkerTemplate'] = isset($args['entryMarkerTemplate']) ? $args['entryMarkerTemplate'] : ENTRY_MARKER_TEMPLATE;
        $this->entryAggregationPeriod = $args['entryAggregationPeriod'] = isset($args['entryAggregationPeriod']) ? $args['entryAggregationPeriod'] : 300; // seconds
        $mode = isset($args['mode']) ? $args['mode'] : 'markdown';
        if ($mode) {
            $this->compileMd =  (strpos($mode, 'mark') !== false) || (strpos($mode, 'md') !== false);
            $this->multiline =  (strpos($mode, 'sing') === false);

        }
        if (!is_numeric( $this->entryAggregationPeriod )) {
            $this->entryAggregationPeriod = strtotime($this->entryAggregationPeriod);
        }
        $this->sendCallback = isset($args['sendCallback']) ? $args['sendCallback'] : false;

        if ($this->liveData) {
            if (!$GLOBALS['lizzy']['chronicleLiveDataInitialized']) {
                $liveData = @$args['liveData'];
                if ($liveData) {
                    $this->page->addModules('~sys/extensions/livedata/js/live_data.js');
                }
                $GLOBALS['lizzy']['chronicleLiveDataInitialized'] = true;
            }
        }
        if ($this->editableBy) {
            if ($this->editableBy !== true) {
                $this->editableBy = $this->lzy->auth->checkPrivilege( $this->editableBy );
            }
        }

        return $args;
    } // parseArgs



    private function initDataRef( $args )
    {
        $args['tickRecCustomFields'] = [
            '_entryMarkerTemplate'    => $this->entryMarkerTemplate,
            '_entryAggregationPeriod'    => $this->entryAggregationPeriod,
            '_saveAsMd'    => $this->compileMd,
            '_useRecycleBin'    => $this->useRecycleBin,
            '_editableBy'       => $this->editableBy,
            '_entryAggregationPeriod'=> $this->entryAggregationPeriod,
            '_dataRef'          => $this->dataSelector,
        ];

        return parent::render($args, true);
    } // initDataRef

} // Chronicle




class ChronicleBackend
{
    public function __construct()
    {
        $this->sessionId = session_id();
    }



    public function handleResponse( $response )
    {
        if ($response === 'save') {
            $this->saveChronicleResponse();
        }
    } // handleChronicleResponse



    private function saveChronicleResponse()
    {
        $text = getCliArg('text');
        $db = $this->openDB();

        if (!$this->editableBy) {
            $this->sendResponse( '', 'failed#Error: insufficient permission');
        }

        $dataKey = get_post_data('dataRef');
        if (!$dataKey) {
            $this->sendResponse( '', 'failed#Error: dataRef missing');
        }
        if (!$db->lockRec( $dataKey, true )) {
            $this->sendResponse( '', 'failed#Error: db locked');
        }
        $origValue = $db->readElement( $dataKey );

        $newValue = $this->injectEntryMarker( $origValue, $text );

        $db->writeElement( $dataKey, $newValue );
        $db->unlockRec( $dataKey);

            if ($this->saveAsMd) {
            $out = compileMarkdownStr($newValue);
        } else {
            $out = $newValue;
        }
        mylog("save: $dataKey => '$text' -> ok");

        $this->sendResponse( $out);
    } // saveChronicleResponse



    private function injectEntryMarker( $origValue, $text )
    {
        $tooFresh = false;
        $lastUn = '';
        $nl = $this->saveAsMd? "\n\n": "\n";

        $text = urldecode($text);
        $text = preg_replace('/\b\n\b/', '<br>', $text);

        $p = strrpos($origValue, '####');
        if ($p !== false) {
            $str = substr($origValue, $p);
            if (preg_match('/ (\d+-\d+-\d+\s\d+:\d+:\d+) \s+ (\w+) /x', $str, $m)) {
                $ts = $m[1];
                $lastUn = $m[2];
                $lastT = strtotime( $ts );
                $tooFresh = ($lastT > (time() - $this->entryAggregationPeriod));
            }
        }
        $un = $_SESSION["lizzy"]["user"] ? $_SESSION["lizzy"]["user"] : 'anon';
        if (!$tooFresh || ($un !== $lastUn)) {
            $ts = timestamp();
            $text = str_replace(['%ts', '%un'], [$ts, $un], $this->entryMarkerTemplate) . "$nl$text";
        }
        if ($origValue) {
            $text = "$origValue$nl$text";
        }
        return $text;
    } // injectEntryMarker



    private function openDB( $getValues = false ) {
        $useRecycleBin = false;
        $srcRef = get_post_data('srcRef');
        if (!$srcRef || !preg_match('/^[A-Z][A-Z0-9]{4,20}/', $srcRef)) {   // dataRef missing
            return false;
        }
        if (preg_match('/^([A-Z0-9]{4,20})\:(.+)$/', $srcRef, $m)) {
            $srcRef = $m[1];
            $setInx0 = $m[2];
        } else {
            die('failed: set-Index missing');
        }
        $tick = new Ticketing();
        $ticketRec = $tick->consumeTicket($srcRef);
        $this->ticketRec = $ticketRec;

        // loop over sets:
        foreach ($ticketRec as $setInx => $set) {
            if (strpos($setInx, 'set') === false) {
                continue;
            }
            if ($setInx !== $setInx0) {
                continue;
            }

            // open DB:
            $useRecycleBin1 = $useRecycleBin || @$set['_useRecycleBin'];
            $this->entryMarkerTemplate = @$set['_entryMarkerTemplate'];
            $this->entryAggregationPeriod = @$set['_entryAggregationPeriod'];
            $this->editableBy = @$set['_editableBy'];
            $this->saveAsMd = @$set['_saveAsMd'];

            $db = new DataStorage2([
                'dataFile' => PATH_TO_APP_ROOT . $set['_dataSource'],
                'sid' => $this->sessionId,
                'lockDB' => false,
                'useRecycleBin' => $useRecycleBin1,
                'lockTimeout' => false,
                'logModifTimes' => true,
            ]);
        }
        return $db;
    } // openDB




    private function sendResponse( $out, $result = 'ok' )
    {
        $outData['data'] = $out;
        $outData['result'] = $result;
        $json = json_encode( $outData );
        exit( $json );
    } // sendResponse


} // ChronicleBackend




<?php
define('DEFAULT_JOURNAL_DATA_FILE', '~page/journal.yaml');
define('ENTRY_MARKER_TEMPLATE',         '#### %ts %un');

$GLOBALS['lizzy']['journalLiveDataInitialized'] = false;
$macroName = basename(__FILE__, '.php');

$response = getCliArg('journal');
if ($response) {
    $bEnd = new JournalBackend();
    $bEnd->handleResponse( $response );
}


require_once SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';

$this->page->addModules(
    '~sys/extensions/journal/css/_journal.css,'.
    '~sys/extensions/journal/js/journal.js,'.
    'POPUPS,'.
    '~sys/js/editor.js,'.
    '~sys/third-party/simplemde/simplemde.min.js,~sys/third-party/simplemde/simplemde.min.css',
);
$this->readTransvarsFromFile( resolvePath("~ext/$macroName/" .LOCALES_PATH. "vars.yaml"), false, true);

$GLOBALS['lizzy']['journalCount'] = 0;

$page->addJq( "initJournal();" );

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$dataSource = $this->getArg($macroName, 'dataSource', 'Specifies where to store entered data.', '');

	if ($dataSource === 'help') {
        $this->getArg($macroName, 'dataSelector', '(optional) If set, defines the dataKey into the DB.', '');
        $this->getArg($macroName, 'writePermission', '[true|false|loggedin|privileged|admins] defines who can enter text. (Default: true = all)', '');
        $this->getArg($macroName, 'editableBy', '[true|false|loggedin|privileged|admins] If set, defines who can modify previously entered text. (Default: false = nobody)', '');
        $this->getArg($macroName, 'editorType', '[plain|rich] If set to "rich", a rich text editor is invoked. By default it\'s a plain-text editor.', '');
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

    $chron = new Journal($this->lzy, $args);
    $out = $chron->render();

    return $out;

}); // addMacro




class Journal extends LiveData
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
        $GLOBALS['lizzy']['journalCount']++;
        $inx = $this->inx = $GLOBALS['lizzy']['journalCount'];

        $args = $this->parseArgs( $args );
        $args['tickRecCustomFields'] = [
            '_entryMarkerTemplate'    => $this->entryMarkerTemplate,
        ];
        parent::__construct( $this->lzy, $args );

        $dataSrcRef = $this->initDataRef( $args );
        $dataRef = "data-ref='$this->dataSelector'";

        $db = new DataStorage2([
            'dataSource' => $args['dataSource'],
            'supportBlobType' => $this->supportBlobType,
        ]);
        if (!$db) {
            die("journal DB not found");
        }

        // check dataRef for indirect addressing of data element:
        if (strpos('=.#', $this->dataSelector[0]) !== false) {
            $text = '⌛';
        } else {
            $dataSelector = ltrim($this->dataSelector, '\\');
            $text = $db->readElement( $dataSelector );
        }

        $callbackAttr = '';
        if ($this->liveData) {
            $callbackAttr = ' data-live-pre-update-callback="journalLiveDataCallback"';
        }

        $editButton = '';
        if ($this->editableBy) {
            $editButton = <<<EOT

          <button id='lzy-journal-edit-btn-$this->inx' class='lzy-journal-edit-btn' title="{{^ lzy-journal-edit-btn }}">
            <span class='lzy-icon lzy-icon-edit' aria-hidden="true"></span>
            <span class="lzy-invisible">{{^ lzy-journal-edit-btn }}</span>
          </button>
          
EOT;
            if ($this->args['editorType'][0] === 'r') {
                $this->class .= ' lzy-editor-rich';
            }
        }

        $pre = '';
        if ($this->renderAsMd) {
            $text = compileMarkdownStr($text);
            $this->class .= ' lzy-compile-md';
        } else {
            $pre = ' lzy-journal-presentation-pre';
        }

        $html = <<<EOT
    <div id="$this->id" class="$this->class" $dataSrcRef $callbackAttr>
        <div>
		  <div class="lzy-journal-presentation-wrapper lzy-scroll-hints">
		    <div id="lzy-journal-presentation-$inx" class="lzy-journal-presentation$pre" $dataRef aria-live="polite">
$text
            </div><!-- /lzy-journal-presentation -->
          </div><!-- /lzy-journal-presentation-wrapper -->$editButton
		</div><!-- /div -->

EOT;
        if ($this->writePermission) {
            if ($this->multiline) {
                $html .= <<<EOT

        <div class="lzy-journal-entry-wrapper">
            <div class='lzy-textarea-autogrow'>
                <label for="lzy-journal-multiline-input-$inx" class="lzy-invisible">{{ lzy-journal-multiline-input-label }}</label>
                <textarea id="lzy-journal-multiline-input-$inx" class="lzy-journal-entry lzy-journal-entry-multiline" 
                aria-controls="lzy-journal-presentation-$inx"></textarea>
           </div><!-- /lzy-textarea-autogrow-->

EOT;
                $jq = <<<EOT
$('.lzy-textarea-autogrow textarea.lzy-journal-entry').on('input', function() {
    this.parentNode.dataset.replicatedValue = this.value;
});
EOT;

                $this->page->addJq($jq);
            } else {
                $html .= <<<EOT

        <div class="lzy-journal-entry-wrapper">
            <label for="lzy-journal-input-$inx" class="lzy-invisible">{{ lzy-journal-input-label }}</label>
            <input id='lzy-journal-input-$inx' class="lzy-journal-entry lzy-journal-entry-singleline" />

EOT;
            }

            $html .= <<<EOT

            <div  class="lzy-journal-send">
                <button class='lzy-button' title="{{ lzy-journal-send-btn }}">
                    <span class='lzy-icon lzy-icon-send' aria-hidden="true"></span>
                    <span class="lzy-invisible">{{ lzy-journal-send-btn }}</span>
                </button>
            </div><!-- /.lzy-journal-send -->
		</div><!-- /lzy-journal-entry-wrapper-->

EOT;
        } // writePermission

        $html .= "\t</div><!-- /lzy-journal -->\n";
        return $html;
    } // render



    private function parseArgs( $args )
    {
        if ($args) {
            $args = array_merge($this->args, $args);
        } else {
            $args = $this->args;
        }
        $this->dataSource = $args['dataSource'] = (isset($args['dataSource']) && $args['dataSource']) ? $args['dataSource'] : DEFAULT_JOURNAL_DATA_FILE;
        $this->dataSelector = $args['dataSelector'] = isset($args['dataSelector']) ? $args['dataSelector'] : "journal-$this->inx";
        $this->id = $args['id'] = isset($args['id']) ? $args['id'] : "lzy-journal-$this->inx";
        $this->class = $args['class'] = isset($args['class']) ? $args['class'] : "lzy-journal lzy-journal-$this->inx";
        $this->liveData = $args['liveData'] = isset($args['liveData']) ? $args['liveData'] : false;
        $this->watchdog = $args['watchdog'] = isset($args['watchdog']) ? $args['watchdog'] : false;
        $this->writePermission = $args['writePermission'] = isset($args['writePermission']) ? $args['writePermission'] : true;
        $this->editableBy = $args['editableBy'] = isset($args['editableBy']) ? $args['editableBy'] : false;
        $this->editorType = $args['editorType'] = isset($args['editorType']) ? $args['editorType'] : 'plain';
        $this->useRecycleBin = $args['useRecycleBin'] = isset($args['useRecycleBin']) ? $args['useRecycleBin'] : false;
        $this->entryMarkerTemplate = $args['entryMarkerTemplate'] = isset($args['entryMarkerTemplate']) ? $args['entryMarkerTemplate'] : ENTRY_MARKER_TEMPLATE;
        $this->entryAggregationPeriod = $args['entryAggregationPeriod'] = isset($args['entryAggregationPeriod']) ? $args['entryAggregationPeriod'] : 300; // seconds
        $mode = isset($args['mode']) ? $args['mode'] : 'markdown';

        $this->supportBlobType = $args['supportBlobType'] = (strpos($this->dataSelector, '~') !== false);

        $this->renderAsMd =  true;
        $this->multiline =  true;
        if ($mode) {
            $this->renderAsMd =  (strpos($mode, 'mark') !== false) || (strpos($mode, 'md') !== false);
            $this->multiline =  (strpos($mode, 'sing') === false);

        }
        if (!is_numeric( $this->entryAggregationPeriod )) {
            $this->entryAggregationPeriod = strtotime($this->entryAggregationPeriod);
        }
        $this->sendCallback = isset($args['sendCallback']) ? $args['sendCallback'] : false;

        if ($this->liveData) {
            if (!$GLOBALS['lizzy']['journalLiveDataInitialized']) {
                $liveData = @$args['liveData'];
                if ($liveData) {
                    $this->page->addModules('~sys/extensions/livedata/js/live_data.js');
                }
                $GLOBALS['lizzy']['journalLiveDataInitialized'] = true;
            }
        }
        if ($this->writePermission) {
            if ($this->writePermission !== true) {
                $this->writePermission = $this->lzy->auth->checkPrivilege( $this->writePermission );
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
            '_entryMarkerTemplate'      => $this->entryMarkerTemplate,
            '_entryAggregationPeriod'   => $this->entryAggregationPeriod,
            '_renderAsMd'               => $this->renderAsMd,
            '_useRecycleBin'            => $this->useRecycleBin,
            '_writePermission'          => $this->writePermission,
            '_editableBy'               => $this->editableBy,
            '_entryAggregationPeriod'   => $this->entryAggregationPeriod,
            '_dataRef'                  => $this->dataSelector,
            '_id'                       => $this->id,
            '_supportBlobType'          => $this->supportBlobType,
        ];

        return parent::render($args, true);
    } // initDataRef

} // Journal




class JournalBackend
{
    public function __construct()
    {
        $this->sessionId = session_id();
    }



    public function handleResponse( $response )
    {
        if ($response === 'save') {
            $this->saveJournalResponse();
        }
    } // handleJournalResponse



    private function saveJournalResponse()
    {
        $text = getCliArg('text');
        $db = $this->openDB();

        if (!@$this->writePermission) {
            mylog('### Journal: save -> insufficient permission');
            $this->sendResponse( '', 'failed#Error in Journal-module: insufficient permission');
        }

        $dataKey = get_post_data('dataRef');
        $dataKey = ltrim($dataKey, '\\');
        if (!$dataKey) {
            mylog('### Journal: save -> dataRef missing');
            $this->sendResponse( '', 'failed#Error in Journal-module: dataRef missing');
        }
        if (!$db->lockRec( $dataKey, true )) {
            mylog('### Journal: save -> database is currently locked');
            $this->sendResponse( '', 'failed#Sorry, database is currently locked');
        }

        $origValue = $db->readElement($dataKey);

        $newValue = $this->injectEntryMarker( $origValue, $text );

        $db->writeElement( $dataKey, $newValue );

        $db->unlockRec( $dataKey);

        if ($this->renderAsMd) {
            $newValue = preg_replace('/\b\n\b/', '<br>', $newValue);
            $out = compileMarkdownStr($newValue);
        } else {
            $out = $newValue;
        }
        mylog("Journal: save -> $dataKey => '$text' -> ok", 'backend-log.txt');

        $this->sendResponse( $out);
    } // saveJournalResponse



    private function injectEntryMarker( $origValue, $text )
    {
        $tooFresh = false;
        $lastUn = '';
        $nl = $this->renderAsMd? "\n\n": "\n";

        $text = urldecode($text);

        if ($this->entryMarkerTemplate) {
            $p = strrpos($origValue, '####');
            if ($p !== false) {
                $str = substr($origValue, $p);
                if (preg_match('/ (\d+-\d+-\d+\s\d+:\d+:\d+) \s+ (\w+) /x', $str, $m)) {
                    $ts = $m[1];
                    $lastUn = $m[2];
                    $lastT = strtotime($ts);
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
        } elseif ($origValue) {
            $text = "$origValue\n$text";
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
        if (!is_array($ticketRec)) {
            mylog("Error in openDB(): ticketRec emply ($ticketRec)");
            return false;
        }

        // loop over sets:
        foreach ($ticketRec as $setInx => $set) {
            if (strpos($setInx, 'set') === false) {
                continue;
            }
            if ($setInx !== $setInx0) {
                continue;
            }
            
            // get parameters from ticket:
            $this->writePermission = @$set['_writePermission'];
            $this->editableBy = @$set['_editableBy'];
            $this->renderAsMd = @$set['_renderAsMd'];
            $this->entryMarkerTemplate = @$set['_entryMarkerTemplate'];
            $this->entryAggregationPeriod = @$set['_entryAggregationPeriod'];
            $supportBlobType = @$set['_supportBlobType'];

            // open DB:
            $useRecycleBin1 = $useRecycleBin || @$set['_useRecycleBin'];

            $db = new DataStorage2([
                'dataFile' => PATH_TO_APP_ROOT . $set['_dataSource'],
                'supportBlobType' => $supportBlobType,
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


} // JournalBackend




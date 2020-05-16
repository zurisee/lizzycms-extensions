<?php

require_once SYSTEM_PATH.'forms.class.php';

// To open editing overlay, use:
//    var recKey = parseInt($('#recKey').text());
//    editDataLoadData( recKey );


$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name

$this->page->addModules(["~ext/$macroName/css/$macroName.css", "~ext/$macroName/js/$macroName.js"]);
$this->readTransvarsFromFile(resolvePath("~ext/$macroName/config/$macroName.yaml"));



$this->addMacro($macroName, function () {

	$macroName = basename(__FILE__, '.php');
	$inx = $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $dataSource = $this->getArg($macroName, 'dataSource', "Path to data-file, e.g. data.yaml (for a file that's in the page folder)", false);
    $this->getArg($macroName, 'structureDef', "File containing specifying the structure of the data to be edited", false);
    $this->getArg($macroName, 'preserveIndex', "If true, rec-level indices will be added to each record.", false);
    //    $this->getArg($macroName, 'mode', "[in-place|popup]", false); // not implemented yet
    $this->getArg($macroName, 'id', 'ID that will be applied to the wrapper div.', '');
    $this->getArg($macroName, 'class', 'Class that will be applied to the form (default: lzy-form).', '');
    $this->getArg($macroName, 'formName', '(optional) Name of form', '');
    $this->getArg($macroName, 'buttons', '(optional) Name of buttons', '');
    $this->getArg($macroName, 'buttonValues', '(submit,cancel,reset) Roles of these buttons', '');
    $this->getArg($macroName, 'renderTable', 'If true, entire data set will be rendered in a table', '');
    $this->getArg($macroName, 'preloadRec', '[false|integer|string] Defines how form fields will be prefilled: false=hour-glass, string=literal, integer=recKey (default: false)', false);
    $this->getArg($macroName, 'elemPrefix', '(optional) Prefix applied to input field IDs (default: \'fld_\')', false);
    //    $this->getArg($macroName, 'checkDataCallback', 'Name of a php script that will be invoked upon storing data received from the client', ''); // not implemented yet

    if ($dataSource === 'help') {
        return '';
    }
    $args = $this->getArgsArray($macroName);

    $ed = new EditData($this->lzy, $inx, $args);
    $str = $ed->render();

	return $str;
});


class EditData
{
    private $page;
    private $data;
    private $inx;
    private $keyFormat = '';

    public function __construct($lzy, $inx, $args)
    {
        $this->lzy = $lzy;
        $this->inx = $inx;
        $this->db = null;
        $this->args = $args;

        $this->dataSource       = isset($args['dataSource']) ? $args['dataSource'] :  false;
        $this->structureDef     = isset($args['structureDef']) ? $args['structureDef'] :  false;
        $this->preserveIndex    = isset($args['preserveIndex']) ? $args['preserveIndex'] :  false;
        $this->mode             = (isset($args['mode']) && $args['mode']) ? $args['mode'] :  'popup';
        $this->id               = (isset($args['id']) && $args['id']) ? $args['id'] :  "lzy-edit-data-$inx";
        $this->class            = (isset($args['class']) && $args['class']) ? $args['class'] :  'lzy-form lzy-edit-data-wrapper';
        $this->formName         = (isset($args['formName']) && $args['formName']) ? $args['formName'] :  '{{ lzy-edit-data-form }}';
        $this->buttons          = (isset($args['buttons']) && $args['buttons']) ? $args['buttons'] :  '{{ Save }},{{ Cancel }},{{ Reset }}';
        $this->buttonValues     = (isset($args['buttonValues']) && $args['buttonValues']) ? $args['buttonValues'] :  'submit,cancel,reset';
        $this->renderTable      = isset($args['renderTable']) ? $args['renderTable'] :  false;
        $this->preloadRec       = isset($args['preloadRec']) ? $args['preloadRec'] :  false;
        $this->elemPrefix       = isset($args['elemPrefix']) ? $args['elemPrefix'] :  false;
        $this->checkDataCallback = isset($args['checkDataCallback']) ? $args['checkDataCallback'] :  false;
        $this->next = isset($args['next']) ? $args['next'] :  false;
    } // __construct




    public function render()
    {
        $this->openDataSource();
        if ($this->renderTable) {
            $this->getData();
            return $this->renderEntireTable();
        } else {
            if ($this->preloadRec !== false) {
                $this->getDataRec();
            }
            return $this->renderRecForm();
        }
    } // render



    public function renderRecForm()
    {
        $tickRec['recDef'] = $this->formElems;
        $tickRec['checkDataCallback'] = $this->checkDataCallback;
        $tickRec['pagePath'] = $GLOBALS["globalParams"]["pagePath"];
        $tickRec['dataPath'] = $GLOBALS["globalParams"]["dataPath"];
        $tickRec['dataSrc'] = $this->dataSource;

        $ticket = $this->createOrUpdateTicket($tickRec);

        $this->form = new Forms($this->lzy);

        $options = [
            'type' => 'form-head',
            'label' => $this->formName,
            'mailto' => '',
            'mailfrp,' => '',
            'id' => $this->id,
        ];
        if ($this->class) {
            $options['class'] = $this->class;
        }
        if ($this->next) {
            $options['next'] = $this->next;
        }
        // create form head:
        $out = $this->form->render( $options );

        $out .= $this->form->render([
            'type' => 'hidden',
            'name' => 'data-ref',
            'value' => $ticket,
            'elemPrefix' => $this->elemPrefix,
        ]);

        $recKey = '';
        if (is_int($this->preloadRec)) {
            $recKey = $this->preloadRec;
        }
        $out .= $this->form->render([
            'type' => 'hidden',
            'name' => 'rec-key',
            'id' => 'recKey',
            'value' => $recKey,
            'elemPrefix' => $this->elemPrefix,
        ]);

        foreach ($this->formElems as $elemName => $rec) {
            if (is_string($this->preloadRec)) {
                $val = $this->preloadRec;
            } else {
                $val = isset($this->recData[$elemName]) ? $this->recData[$elemName] : '⌛';
            }
            $out .= $this->form->render([
                'label' => "$elemName:",
                'type' => $rec[1],
                'name' => $rec[0],
                'value' => $val,
                'elemPrefix' => $this->elemPrefix,
            ]);
        }

        $buttons = [ 'label' => $this->buttons, 'type' => 'button', 'value' => $this->buttonValues, 'wrapperClass' => 'lzy-form-buttons' ];
        $out .= $this->form->render($buttons);

        $out .= $this->form->render([ 'type' => 'form-tail' ]);

        return $out;
    } // renderRecForm



    private function renderEntireTable()
    {
        require_once SYSTEM_PATH.'htmltable.class.php';

        $args = $this->args;
        $args['dataSource'] = $this->data;
        $tbl = new HtmlTable($this->lzy, $this->inx, $args );
        $out = $tbl->render();
        return $out;
    } // renderEntireTable




    private function openDataSource()
    {
        $this->dataSource = resolvePath($this->dataSource, true);
        $structureDef = resolvePath($this->structureDef, true);
        if ($structureDef && file_exists($structureDef)) {
            $structure = getYamlFile($structureDef);
            if (isset($structure[0])) {
                $structure = $structure[0];
            }

        } else {
            $this->db = new DataStorage2($this->dataSource);
            $structure = $this->db->readRecord('_structure');
            if ($structure) {
                $structure = $this->db->getDbRecStructure();
            }
        }
        $this->recStructure = $structure;

        $availableTypes = ',text,password,tel,email,number,range,date,time,datetime,radio,checkbox,dropdown,textarea,hidden,bypassed,';
        $formElems = [];
        foreach ($structure as $elemName => $type) {
            if ($type !== 'ignore') {
                $formFieldName = translateToIdentifier($elemName);
                $elemType = (strpos($availableTypes, ",$type,") !== false) ? $type : 'text';
                $formElems[$elemName] = [$formFieldName, $elemType];
            }
        }
        $this->formElems = $formElems;
    } // openDataSource



    private function getData()
    {
        if (!$this->db) {
            $this->db = new DataStorage2($this->dataSource);
        }
        $data = $this->db->read();
        $data1 = $data;
        $data = [];
        foreach ($data1 as $inx => $rec) {
            if ($this->preserveIndex) {
                $rec['index'] = $inx;
            }
            $data[] = $rec;
        }
        unset($data1);

//        $availableTypes = ',text,password,tel,email,number,range,date,time,radio,checkbox,dropdown,textarea,hidden,bypassed,';
        $outData = [];
        foreach ($data as $i => $rec) {
            $j = 0;
            foreach ($this->recStructure as $elemName => $type) {
                if ($type !== 'ignore') {
                    $outData[$i][$j++] = $rec[$elemName];
                }
            }
        }
        $this->data = $outData;
    } // getData



    private function getDataRec()
    {
        if (($this->preloadRec !== false) && (!preg_match('/\D/', $this->preloadRec))) {
            if (!$this->db) {
                $this->db = new DataStorage2($this->dataSource);
            }
            $this->recData = $this->db->readRecord($this->preloadRec);
        } else {
            $this->recData = null;
        }
    } // getDataRec





    private function renderRec($rec = false)
    {
        if (is_string($rec)) {
            $inx = '@';
        } else {
            $inx = $this->inx++;
        }

        $class = '';
        if (is_string($rec)) {
            $class = " $rec";
            $rec = [];
        }
        $buttons = <<<EOT
            <div class="lzy-data-input-rec-buttons">
                <button class='lzy-data-input-add-rec lzy-button' title="{{ lzy-data-input-add-rec-tooltip }}">{{ lzy-data-input-add-rec-symbol }}</button>
                <button class='lzy-data-input-del-rec lzy-button' title="{{ lzy-data-input-del-rec-tooltip }}">{{ lzy-data-input-del-rec-symbol }}</button>
            </div>
EOT;

        $keyType = $this->keyType;
        $key = isset($rec['key']) ? $rec['key'] : '';
        if ($key && ($keyType === 'date')) {
            $key = date($this->keyFormat, $key);
        }
        $keyLabel = "{{ lzy-data-key-$keyType-label }}";

        if ($keyType !== 'datetime-local') {
        $out = <<<EOT
        <div class='lzy-data-input-rec$class'>
            <div class="lzy-data-input-box">
                <label for='lzy-data-key-$inx'>$keyLabel</label>
                <input type='{$keyType}' id='lzy-data-key-$inx' class='lzy-data-key' name='lzy_data_key[$inx]' value="$key">

EOT;
        } else {
            $key1 = date('Y-m-d', $key);
            $key2 = date('H:i', $key);
            $out = <<<EOT
        <div class='lzy-data-input-rec$class'>
            <div class="lzy-data-input-box">
                <label for='lzy-data-key-$inx'>{{ lzy-data-key-date-label }}</label>
                <input type='date' id='lzy-data-key-$inx' class='lzy-data-key' name='lzy_data_key[$inx]' value="$key1">
                <label class='lzy-data-input-time' for='lzy-data-key-$inx'>{{ lzy-data-key-time-label }}</label>
                <input type='time' id='lzy-data-key-{$inx}b' class='lzy-data-key' name='lzy_data_key[{$inx}b]' value="$key2">

EOT;
        }
        $out .= <<<EOT
                <div class='lzy-data-input-fields'>

EOT;

        $j = 1;
        foreach ($this->fields as $label => $fieldType) {
            $type = (stripos($fieldType, 'string') !== false) ? 'text' : $fieldType;
            $value = isset($rec[$label]) ? $rec[$label] : '';
            $name = str_replace('-', '_', $label);
            $out .= <<<EOT
                    <div class='lzy-data-input-field'>
                        <label for='lzy-data-field-$inx-$j'>{{ $label }}</label>
                        <input type='$type' id='lzy-data-field-$inx-$j' name='{$name}' value="$value">
                    </div>

EOT;
            $j++;
        }

        $out .= <<<EOT
                </div>
            </div>
$buttons
        </div><!-- /lzy-data-input-rec -->


EOT;
        return $out;
    } // renderRec



    private function handleUserSuppliedData($dataSource, $structure, $structDefined)
    {
        if (!isset($_POST['lzy_data_input_form'])) {
            return;
        }
        $hashedHeader = getHashCommentedHeader($dataSource);

        $data0 = json_decode($_POST['lzy_data_input_form'], true);
        $data = [];
        foreach ($data0 as $key => $rec) {
            if ($key) {
                if (($structure[$key] === 'date') && is_int($key)) {
                    $key = date('Y-m-d', $key);
                }
                foreach ($structure['fields'] as $fieldName => $type) {
                    $data[$key][$fieldName] = isset($rec[$fieldName]) ? $rec[$fieldName] : 'error';
                }
            }
        }
        if (($structure['key'] === 'date') || ($structure['key'] === 'datetime')) {
            ksort($data);
        }
        if ($structDefined) {   // prepend structure again
            $data = array_merge(['_structure' => $structure], $data);
        }
        $yaml = $hashedHeader . convertToYaml($data);

        require_once SYSTEM_PATH . 'page-source.class.php';
        $ps = new PageSource;
        $ps->copyFileToRecycleBin($dataSource);
        file_put_contents($dataSource, $yaml);

        exit('ok');
    } // handleUserSuppliedData



    private function createOrUpdateTicket(array $tickRec)
    {
        $tick = new Ticketing();
        if (isset($_SESSION['lizzy']['editDataTicket'])) {
            $ticket = $_SESSION['lizzy']['editDataTicket'];
            $res = $tick->findTicket($ticket);
            if ($res) {     // yes, ticket found
                if (!isset($res[$this->inx - 1])) {
                    $tick->updateTicket($ticket, $tickRec);
                }
            } else {    // it was some stray ticket
                $ticket = $tick->createTicket($tickRec, 99, 86400);
                $_SESSION['lizzy']['editDataTicket'] = $ticket;
            }
        } else {
            $ticket = $tick->createTicket($tickRec, 99, 86400);
            $_SESSION['lizzy']['editDataTicket'] = $ticket;
        }
        return $ticket;
    } // createOrUpdateTicket

} // EditData


<?php
// @info: -> one line description of macro <-

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name

$this->page->addModules(["~ext/$macroName/css/$macroName.css", "~ext/$macroName/js/$macroName.js"]);
$this->readTransvarsFromFile(resolvePath("~ext/$macroName/config/$macroName.yaml"));



$this->addMacro($macroName, function () {

	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $dataSource = $this->getArg($macroName, 'dataSource', "Path to data-file, e.g. data.yaml (for a file that's in the page folder)", false);
    $id = $this->getArg($macroName, 'id', 'ID that will be applied to the wrapper div.', 'default value');
    $class = $this->getArg($macroName, 'class', 'Class that will be applied to the wrapper div.', 'default value');
    $keyFormat = $this->getArg($macroName, 'keyFormat', 'Template to format date/time output as accepted by PHP strtotime() function, see. https://www.php.net/manual/en/function.strtotime.php.', false);
    if ($dataSource === 'help') {
        return '';
    }

    $ed = new EditData($this->page, $dataSource);
    $str = $ed->render($id, $class, $keyFormat);
	return $str;
});


class EditData
{
    private $page;
    private $data;
    private $inx;
    private $keyFormat = '';

    public function __construct($page, $dataSource)
    {
        $this->page = $page;

        $dataSource = resolvePath($dataSource, true);
        if (!file_exists($dataSource)) {
            fatalError("Error: data file missing: '$dataSource'");
        }
        list($this->data, $structure, $structDefined) = getYamlFile($dataSource, true);

        if (!isset($structure['key'])) {
            fatalError("Error in data file: structure def missing");
        }
        $this->keyType = $structure['key'];
        $this->fields = $structure['fields'];
        $this->inx = 1;

        $this->handleUserSuppliedData($dataSource, $structure, $structDefined); // exits if data received
    } // __construct




    public function render($id, $class, $keyFormat = false)
    {
        if ($keyFormat) {
            $this->keyFormat = $keyFormat;
        } elseif ($this->keyType === 'date') {
            $this->keyFormat = 'Y-m-d';
        } elseif ($this->keyType === 'datetime') {
            $this->keyFormat = 'Y-m-d\TH:i';
            $this->keyType = 'datetime-local';
        }

        $id = $id ? "id='$id'" : '';
        $class = $class ? "class='$class'" : '';
        $formButtons = <<<EOT

        <div class="lzy-data-input-form-buttons">
            <input type='submit' class='lzy-button' value='{{ lzy-data-input-submit }}'>
            <input type='reset' class='lzy-button' value='{{ lzy-data-input-cancel }}'>
        </div>

EOT;
        $nRecs = sizeof($this->data);
        $out = "\t<form $id $class method='post'>\n\t\t<input type='hidden' name='lzy-data-input-form' value='$nRecs'>\n";
        $out .= $formButtons;
        foreach ($this->data as $key => $rec) {
            $rec['key'] = $key;
            $out .= $this->renderRec($rec);
        }
        $out .= $this->renderRec();

        $templateRec = $this->renderRec('lzy-data-new-rec');
        $out .= <<<EOT
$formButtons    </form>
    <div class="lzy-data-imput-template" style="display: none;">
$templateRec
    </div>
EOT;

        return $out;
    } // render



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



    /**
     * @param $dataSource
     * @param $structure
     * @param $structDefined
     * @return void
     */
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

} // EditData


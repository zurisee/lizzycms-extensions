<?php
// @info: -> one line description of macro <-

$this->page->addModules('~sys/extensions/edit-data/css/edit-data.css');


$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {

	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $dataSource = $this->getArg($macroName, 'dataSource', '', false);
    $id = $this->getArg($macroName, 'id', 'Help-text', 'default value');
    $class = $this->getArg($macroName, 'class', 'Help-text', 'default value');
    $keyFormat = $this->getArg($macroName, 'keyFormat', 'Help-text', false);
    $labelsAsVars = $this->getArg($macroName, 'labelsAsVars', '(optional) If true, header elements will be rendered as variables (i.e. in curly brackets).', false);

    $ed = new EditData($this->page, $dataSource);
    $str = $ed->render($id, $class, $keyFormat, $labelsAsVars);
	return $str;
});


class EditData
{
    private $page;
    private $data;
    private $inx;

    public function __construct($page, $dataSource)
    {
        $this->page = $page;

        $dataSource = resolvePath($dataSource, true);
        list($this->data, $structure, $structDefined) = getDataFromFile($dataSource, true);
//        $type = fileExt($dataSource);
//        switch ($type) {
//            case 'yaml':
//                list($this->data, $structure, $structDefined) = getYamlFile($dataSource, true);
//                break;
//            case 'csv':
//                list($this->data, $structure, $structDefined) = getCsvFile($dataSource, true);
//                break;
//            default:
//                die("EditData not implemented yet for data type $type");
//        }

        if (!isset($structure['key'])) {
            fatalError("Error in data file: structure def missing");
        }
        $this->keyType = $structure['key'];
        $this->fields = $structure['fields'];
        $this->inx = 1;
        $this->renderJq();

        $this->handleUserSuppliedData($dataSource, $structure, $structDefined); // exits if data received
    } // __construct




    public function render($id, $class, $keyFormat = false, $labelsAsVars = false)
    {
        $this->keyFormat = $keyFormat ? $keyFormat : 'Y-m-d';
        $this->labelsAsVars = $labelsAsVars;

        $id = $id ? "id='$id'" : '';
        $class = $class ? "class='$class'" : '';
        $formButtons = <<<EOT

        <div class="lzy-data-input-form-buttons">
            <input type='submit' class='lzy-button' value='{{ Save }}'>
            <input type='reset' class='lzy-button' value='{{ Cancel }}'>
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
                <button class='lzy-data-input-add-rec lzy-button'>&plus;</button>
                <button class='lzy-data-input-del-rec lzy-button'>&minus;</button>
            </div>
EOT;

        $keyType = $this->keyType;
        $key = isset($rec['key']) ? $rec['key'] : '';
        if ($key && ($keyType == 'date')) {
            $key = date($this->keyFormat, $key);
        }
        if ($this->labelsAsVars) {
            $keyLabel = "{{ lzy-data-key-$keyType-label }}";
        } else {
            $keyLabel = '{{ key }}'; //$keyType;
        }

        $out = <<<EOT
        <div class='lzy-data-input-rec$class'>
            <div class="lzy-data-input-box">
                <label for='lzy-data-key-$inx'>$keyLabel</label>
                <input type='{$keyType}' id='lzy-data-key-$inx' name='lzy_data_key[$inx]' value="$key">
                <div class='lzy-data-input-fields'>

EOT;

        $j = 1;
        foreach ($this->fields as $label => $fieldType) {
            $type = (stripos($fieldType, 'string') !== false) ? 'text' : $fieldType;
            $value = isset($rec[$label]) ? $rec[$label] : '';
            $name = str_replace('-', '_', $label);
            if ($this->labelsAsVars) {
                $label = "{{ $label }}";
            }
            $out .= <<<EOT
                    <div class='lzy-data-input-field'>
                        <label for='lzy-data-field-$inx-$j'>$label</label>
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




    private function renderJq()
    {
        $jq = <<<'EOT'
    var nRecs = parseInt($('input[name=lzy-data-input-form]').val());
    console.log('nRecs: ' + nRecs);
    
    // add record:
    $('.lzy-data-input-add-rec').click(function() {
        console.log('lzy-data-input-add-rec');
        var $rec = $( this ).closest('.lzy-data-input-rec');
        nRecs = nRecs + 1;
    console.log('nRecs: ' + nRecs);
        var html = $('.lzy-data-imput-template').html();
        html = html.replace(/@/g, nRecs);
        $( html ).insertBefore( $rec );
        
        return false;
    });
    
    // delete record:
    $('.lzy-data-input-del-rec').click(function() {
        console.log('lzy-data-input-del-rec');
        var $rec = $( this ).closest('.lzy-data-input-rec');
        $rec.remove();
        return false;
    });
    
    // reset form:
    $('input[type=reset]').click(function() {
        console.log('reload page');
        var response = confirm("{{ lzy-discard-all-changes }}");
        if (response) {
            lzyReload();
        }
    });
    
    // submit form:
    $('form').submit(function() {
        var url = window.location.href;
        console.log('submit ' + url);
        var $form = $( this );
        var data = {};
        $('.lzy-data-input-rec').each(function() {
            var $this = $(this);
            var d = $('input[type=date]', $this).val();
            data[d] = {};
            $('.lzy-data-input-field', $this).each(function() {
                var $inp = $( 'input', $(this) );
                var name = $inp.attr('name');
                var val = $inp.val();
                data[d][name] = val;
            });
        });
        console.log(data);
        var json = JSON.stringify(data);
        $.ajax({
            url: url,
            type: 'post',
            data: {lzy_data_input_form: json},
            success: function( response ) {
                lzyReload();
            }
        });
        return false;
    });
EOT;
        $this->page->addJq($jq);
    }




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
        $data0 = json_decode($_POST['lzy_data_input_form'], true);
        if (writeDataToFile($dataSource, $data0)) {
            exit('ok');
        } else {
            exit('error');
        }

        $hashedHeader = getHashCommentedHeader($dataSource);

        $data0 = json_decode($_POST['lzy_data_input_form'], true);
        $data = [];
        foreach ($data0 as $key => $rec) {
            if ($key) {
                if (($structure[$key] == 'date') && is_int($key)) {
                    $key = date('Y-m-d', $key);
                }
                foreach ($structure['fields'] as $fieldName => $type) {
                    $data[$key][$fieldName] = isset($rec[$fieldName]) ? $rec[$fieldName] : 'error';
                }
            }
        }
        if ($structure['key'] == 'date') {
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


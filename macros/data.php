<?php
// @info:

define('TABLE_VIEW_CLASS', 'lzy-data-table-view');
define('RECORD_VIEW_CLASS', 'lzy-data-record-view');
define('LAYOUT_BREAKPOINT', 700);

$this->page->addModules('~sys/css/_edit-data.css');


$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {

	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $dataSource = $this->getArg($macroName, 'dataSource', '', false);

    if ($dataSource == 'help') {
        $this->getArg($macroName, 'id', 'ID applied to wrapper tag', '');
        $this->getArg($macroName, 'class', 'Class applied to wrapper tag', '');
        $this->getArg($macroName, 'editableBy', '[groups] Defines who can edit data, e.g. "admins".', false);
        $this->getArg($macroName, 'layout', '[table, record, auto, false] Defines how data shall be presented, i.e. in table or record form. False means no styling at all will be applied.', 'auto');
        $this->getArg($macroName, 'layoutBreakpoint', '[integer] In layout-auto-mode defines when layout switches from table to record mode.', LAYOUT_BREAKPOINT);
        $this->getArg($macroName, 'indexPresentation', '[true, false, auto] Defines whether keys of data-records shall be included in the layout.', 'auto');
        $this->getArg($macroName, 'labelsAsVars', 'If true, header elements will be rendered as variables (i.e. in curly brackets), so you  can translate them.', false);
        $this->getArg($macroName, 'dateFormat', '[format] Presentation of dates and times can be formatted, e.g. "d.m.Y" (see PHP date() function)', false);
        $this->getArg($macroName, 'useRecycleBin', 'If true, files containing data will be moved to the recycle bin, rather than overwritten.', false);
        return '';
    }

    $args = $this->getArgsArray($macroName);
    $ed = new EditData($this->lzy, $args, $inx);
    $str = $ed->render();
	return $str;
});


class EditData
{
    private $page;
    private $data;
    private $inx;

    public function __construct($lzy, $args, $inx)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
        $this->args = $args;
        $this->invocationIndex = $inx;

        $dataSource = $this->getArg('dataSource');
        $id = $this->getArg('id');
        $class = $this->getArg('class');
        $this->keyFormat = $this->getArg('keyFormat');
        $layout = $this->getArg('layout','auto');
        $layoutBreakpoint = $this->getArg('layoutBreakpoint',LAYOUT_BREAKPOINT);
        $indexPresentation = $this->getArg('indexPresentation', 'auto');
        $this->labelsAsVars = $this->getArg('labelsAsVars');
        $useRecycleBin = $this->getArg('useRecycleBin');

        $editableBy = $this->getArg('editableBy');
        $this->editable =  $editableBy && $this->lzy->auth->checkAdmission($editableBy);
        $this->editing =  $editableBy && getUrlArg('edit-data');

        $this->id = $id ? " id='$id'" : '';

        if ($layout) {
            $tableViewClass = TABLE_VIEW_CLASS;
            $recordViewClass = RECORD_VIEW_CLASS;
            if ($layout == 'table') {
                $class .= 'lzy-data-table-view';

            } elseif ($layout == 'record') {
                $class .= 'lzy-data-record-view';

            } elseif ($layout == 'auto') {
                $jq = <<<EOT
    switchDataLayout();
    $( window ).resize(function() { switchDataLayout(); });
    
    function switchDataLayout() {
        if ( $( window ).width() > $layoutBreakpoint ) {
            $('.lzy-data-wrapper$inx').removeClass('$recordViewClass').addClass('$tableViewClass');
        } else {
            $('.lzy-data-wrapper$inx').removeClass('$tableViewClass').addClass('$recordViewClass');
        }
    }
EOT;
                $this->page->addJq($jq);
            }
        }

        // get data:
        $dataSource = resolvePath($dataSource, true);
        $this->ds = new DataStorage(['dbFile' => $dataSource, 'useRecycleBin' => $useRecycleBin]);
        if ($this->editing) {
            if (!$this->ds->doLockDB()) {
                $this->page->addPopup('{{ lzy-DB-currently-locked }}');
                $this->editing = false;
            }
        } else {
            $this->ds->doUnlockDB();
        }

        $this->structure = $structure = $this->ds->getRecStructure();


        if (($indexPresentation === true) || (($indexPresentation === 'auto') && ($structure["key"] !== 'index'))) {
            $class .= ' lzy-data-show-index';
        }


        $this->class = trim("lzy-data-wrapper lzy-data-wrapper$inx $class");

        $this->formButtonsTemplate = <<<EOT
        <div class="lzy-data-input-form-buttons">
            <input type='submit' class='lzy-button' value='{{ Save }}'>
            <input type='reset' class='lzy-button' value='{{ Cancel }}'>
        </div>

EOT;
        $this->recButtonsTemplate = <<<EOT
            <div class="lzy-data-input-rec-buttons">
                <button class='lzy-data-input-add-rec lzy-button' title="{{ lzy-data-add-record }}">&plus;</button>
                <button class='lzy-data-input-del-rec lzy-button' title="{{ lzy-data-delete-record }}">&minus;</button>
            </div>

EOT;

        if ($this->editable) {
            $this->handleUserSuppliedData($dataSource); // exits if data received
        }
    } // __construct




    public function render()
    {
        if (($this->invocationIndex == 1) && $this->editing) {
            $this->renderJq();
        }

        if ($this->editing) {
            $formButtons = $this->formButtonsTemplate;
            $this->recButtons = $this->recButtonsTemplate;

        } elseif ($this->editable) {
            $formButtons = "<div class='lzy-data-input-form-buttons'><a href='./?edit-data' class='lzy-data-edit-button lzy-button'>{{ Edit }}</a></div>";
            $this->recButtons = '';

        } else {
            $formButtons = '';
            $this->recButtons = '';
        }

        if ($this->structure["key"] == 'date') {
            $this->class .= ' lzy-data-date-index';
        }
        // get data:
        $data = $this->ds->read('*');
        $this->fieldNames = $this->structure['labels'];
        $nRecs = sizeof($data);

        // prepare output fragments:
        $out = <<<EOT
    <form{$this->id} class="{$this->class}" data-lzy-n-recs="$nRecs">
$formButtons
EOT;
        $outTail = <<<EOT
$formButtons
    </form>

EOT;

        // wrap Key into variable braces:
        if ($this->labelsAsVars) {
            $this->keyLabel0 = '{{ Key }}';
        } else {
            $this->keyLabel0 = 'Key';
        }

        $out .= $this->renderHeadersRow();

        // loop over data records:
        $r = 1;
        foreach ($data as $key => $rec) {
            $out .= $this->renderRec($r, $key, $rec);
            $r++;
        }

        // editing:
        if ($this->editing) {
            $key1 = is_int($key) ? $key+1 : '';
            $out .= $this->renderRec($r, $key1, $rec, true);
            $out .= $this->renderRec($r, '', $rec, 'template');
        }

        $out .= $outTail;
        $out .= "\t\t<div style='display: none;'><div id='lzy-cancel-popup' class='lzy-popup lzy-close-button popup_content'>{{ lzy-discard-all-changes }}</div></div>\n";
        return $out;
    } // render





    private function renderRec($inx, $key, $rec, $emptyRec = false)
    {
        $invocationIndex = $this->invocationIndex;
        $cls = ($emptyRec === 'template') ? 'lzy-data-input-template dispno' : "lzy-data-row$inx";
        $keyType = $this->structure["key"];
        $keyId = "lzy-data-key-label{$invocationIndex}-$inx";
        $recStr = <<<EOT
        <div class="lzy-data-row $cls">
            <div class='lzy-data-key' data-field-type="$keyType"><label for="$keyId" class="lzy-data-key-label">$this->keyLabel0</label><div id="$keyId" class="lzy-data-key-value">$key</div></div>

EOT;

        $f = 1;
        foreach ($rec as $k => $value) {
            if (!is_string($k)) {
                $keyLabel = $this->fieldNames[$f - 1];
            } else {
                $keyLabel = $k;
            }
            $elemName = $this->translateToName($keyLabel);
            if ($this->labelsAsVars) {
                $keyLabel = "{{ $keyLabel }}";
            }
            if (!$emptyRec) {
                $fldId = "lzy-data-elem{$invocationIndex}-$inx-$f";
                $value = trim($value);

            } elseif ($emptyRec === 'template') {
                $fldId = "lzy-data-elem{$invocationIndex}-@-$f";
                $value = '';

            } else {
                $fldId = "lzy-data-elem{$invocationIndex}-$inx-$f";
                $value = '';
            }
            $fType = isset($this->structure['types'][$f-1]) ? $this->structure['types'][$f-1] : 'text';
            if ($fType == 'string') {
                $fType = 'text';
            }
            $recStr .= <<<EOT
            <div class='lzy-data-elem'>
                <label for="$fldId">$keyLabel</label><div id="$fldId" class="lzy-data-field" data-field-name="$elemName" data-field-type="$fType">$value</div>
            </div>

EOT;
            $f++;
        }
        $recStr .= $this->recButtons;
        $recStr .= "\t\t</div><!-- /.lzy-data-row -->\n\n";
        return $recStr;

    } // renderRec



    private function translateToName($str)
    {
        $str = preg_replace('/[^\w\s]/', '', $str);
        return str_replace(' ', '_', $str);
    }




    private function renderJq()
    {
        $jq = <<<'EOT'
    
    // init add record button:
    $('.lzy-data-input-add-rec').click(function() { lzyDataAddRec( this ); return false; });
    
    // init delete record button:
    $('.lzy-data-input-del-rec').click( function() { lzyDataDeleteRec( this ); return false; });
 
    // reset form:
    $('input[type=reset]').click(function() {
        var $form = $( this ).closest('form');
        console.log('reload page');
        if (!$form.hasClass('lzy-data-buttons-active')) {
            lzyReload();
            return;
        }
        $('#lzy-cancel-popup').popup('show');
            $('#lzy-cancel-popup')
				.addClass('lzy-popup lzy-popup3 lzy-close-button lzy-popup-confirm')
				.append("<div class='lzy-popup-buttons'><button class='lzy-popup-cancel lzy-popup-button'>{{ No }}</button> <button class='lzy-popup-confirm lzy-popup-button'>{{ Yes }}</button> </div>")
			    .popup({
					closebutton: true,
					autoopen: true,
					blur: true,
					opacity: 0.8,
					color: '#000',
					transition: 'all 0.3s',
					});
			$('#lzy-cancel-popup .lzy-popup-confirm').click(function(e) {
			     lzyReload();
			    $popup.popup('hide');
			});
			
			$('#lzy-cancel-popup .lzy-popup-cancel').click(function(e) {
			    var $popup = $(e.target).closest('.popup_content');
			    $popup.popup('hide');
			});
    });
    
    // submit form:
    $('form').submit(function() {
        var url = window.location.href;
        console.log('submit ' + url);
        var $form = $( this ).closest('form');
        if (!$form.hasClass('lzy-data-buttons-active')) {
            return false;
        }
        var data = {};
        var d = 0;
        var abort = false;
        var keys = [];
        $('.lzy-data-row').each(function() {
            var $this = $(this);
            if ($this.hasClass('lzy-data-input-template')) {
                return;
            }
            if (d !== '') {
                var keyType = $('.lzy-data-key' , $this).attr('data-field-type');
                if (keyType == 'index') {
                    var inx = $('.lzy-data-key-value', $this).text();
                } else {
                    var inx = $('.lzy-data-key input', $this).val();
                }
                if (keys.includes(inx)) {   // check whether key is unique:
                    alert('Conflict: this key is not unique: '+inx);
                        $('.lzy-data-key input', $this).addClass('lzy-data-required');
                        abort = true;
                        return false;                    
                }
                keys.push(inx);
                data[inx] = {};
                var recEmpty = true;
                $('.lzy-data-elem', $this).each(function() {
                    var $inp = $( 'input', $(this) );
                    var name = $inp.attr('name');
                    var val = $inp.val();
                    data[inx][name] = val;
                    if (val) {
                        recEmpty = false;
                    }
                });
                if (keyType != 'index') {
                    if ((inx == '') && !recEmpty) {
                        $('.lzy-data-key input', $this).addClass('lzy-data-required');
                        abort = true;
                        return false;
                    }
                }
            }
            d = d + 1;
        });
        if (abort) {
            return false;
        }
        deactivateFormButtons();
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
    
    // make editable:
    $('.lzy-data-wrapper .lzy-data-row').each(function() {
        var $this = $( this );
        
        var $keyElem = $('.lzy-data-key-value', $this);
        var keyType = $keyElem.parent().attr('data-field-type');
        if (keyType != 'index') {
            var id = $keyElem.attr('id');
            var text = $keyElem.text();
            $( '.lzy-data-key', $this ).append('<input type="' + keyType + '" id="' + id + '" name="key" value="' + text + '" />');
            $keyElem.remove();
        }

        $('.lzy-data-elem', $this).each(function () {
            var $elem = $('.lzy-data-field', this );
            var id = $elem.attr('id');
            var text = $elem.text();
            var name = $elem.attr('data-field-name');
            var type = $elem.attr('data-field-type');
            $( this ).append('<input type="' + type + '" id="' + id + '" name="' + name + '" value="' + text + '" />');
            $elem.remove();
        });
        
    });
    $('.lzy-data-wrapper input').change( function() { 
        activateFormButtons(); } 
    );
    $('.lzy-data-wrapper').addClass('lzy-editing');

    
    function lzyDataAddRec( that ) {
        console.log('lzy-data-input-add-rec');
        var $rec = $( that ).closest('.lzy-data-row');
        var $form = $('.lzy-data-wrapper').closest('form');
        var nRecs = parseInt($form.attr('data-lzy-n-recs'));
        nRecs = nRecs + 1;
        $form.attr('data-lzy-n-recs', nRecs);
        console.log('nRecs: ' + nRecs);

        var height = $rec.height();
        var marginTop = 'margin-top:-' + height + 'px;';
        var html = $('.lzy-data-input-template').html();
        var cls1 = 'lzy-data-row' + nRecs;
        html = html.replace(/@/g, nRecs);
        html = html.replace(/lzy-data-key-value">/g, 'lzy-data-key-value">' + (nRecs));
        html = '<div class="_lzy-data-row" style="overflow:hidden;"><div class="lzy-data-row ' + cls1 + '" style="' + marginTop + '">' + html + '</div></div>';
        $( html ).insertBefore( $rec );
        $( '.' + cls1 ).animate({marginTop:0}, 300);
        setTimeout(function() { $( '._lzy-data-row').css('overflow', 'visible'); }, 510);            
        $('.' + cls1 + ' .lzy-data-input-add-rec').click( function() { lzyDataAddRec( this ); return false; });
        $('.' + cls1 + ' .lzy-data-input-del-rec').click( function() { lzyDataDeleteRec( this ); return false; });
        activateFormButtons();
        return false;
    }

    function lzyDataDeleteRec( that ) {
        console.log('lzy-data-input-del-rec');
        var $rec = $( that ).closest('.lzy-data-row');
        var html = $rec.html();
        var height = $rec.height();
        $rec.html('<div id="lzy-tmp-rec">empty</div>').css('overflow', 'hidden'); // wrap element in div
        $('#lzy-tmp-rec').html( html ).animate({marginTop: '-'+height+'px'}, 300);
        setTimeout(function() { $rec.remove(); }, 310);
        activateFormButtons();  
        return false;
    }

    function activateFormButtons() {
        if (!$('.lzy-data-wrapper').hasClass('lzy-data-buttons-active')) {
            $('.lzy-data-wrapper').addClass('lzy-data-buttons-active');
        }
    }

    function deactivateFormButtons() {
        if ($('.lzy-data-wrapper').hasClass('lzy-data-buttons-active')) {
            $('.lzy-data-wrapper').removeClass('lzy-data-buttons-active');
        }
    }

EOT;
        $this->page->addJq($jq);
    }




    private function handleUserSuppliedData($dataSource)
    {
        if (!isset($_POST['lzy_data_input_form'])) {
            return;
        }
        $data0 = json_decode($_POST['lzy_data_input_form'], true);  // decode

        $keyL = array_pop(array_keys($data0));
        $lastRec = $data0[$keyL];    // remove last record if empty
        if (!implode('', $lastRec)) {
            array_pop($data0);
        }

        if ($this->structure["key"] == 'date') {
            ksort($data0);
        }

        $this->ds->write($data0, null);

        // log activity:
        $user = $_SESSION["lizzy"]["user"];
        writeLog("data(): user '$user' modified DB '$dataSource'");
    } // handleUserSuppliedData




    private function renderHeadersRow()
    {
        $out = "\t\t<div class='lzy-data-headers'>\n\t\t\t<div class='lzy-data-key'>{{ Key }}</div>\n";
        foreach ($this->structure['labels'] as $fieldName) {
            if ($this->labelsAsVars) {
                $fieldName = "{{ $fieldName }}";
            }
            $out .= <<<EOT
            <div class='lzy-data-header'>$fieldName</div>

EOT;
        }
        $out .= "\t\t</div><!-- /.lzy-data-headers -->\n\n";
        return $out;
    } // renderHeadersRow




    private function getArg($name, $default = false)
    {
        return (isset($this->args[$name])) ? $this->args[$name] : $default;
    }
} // EditData


<?php

$GLOBALS['globalParams']['editaleSetInx'] = 0;
$GLOBALS['globalParams']['editableInitialized'] = false;

$page->addModules('JS_POPUPS');

$okSymbol = true? '&#10003;': '&radic;';
$cancelSymbol = '&times;';

$textResources = <<<EOT
<div style="display: none">
    <div id='lzy-db-error' style="display: none">{{ lzy-editable-db-error }}</div>
    <div id='lzy-error-locked' style="display: none">{{ lzy-editable-db-locked }}</div>
    <div id='lzy-info-frozen' style="display: none">{{ lzy-editable-element-frozen }}</div>
    <div id='lzy-conn-error' style="display: none">{{ lzy-editable-conn-error }}</div>
    <div id='lzy-editable-ok-text' style="display: none"><button class='lzy-editable-submit-button lzy-button' title='{{ lzy-editable-ok-text }}'>$okSymbol<span class='invisible'> {{ lzy-editable-ok-text }}</span></button></div>
    <div id='lzy-editable-cancel-text' style="display: none"><button class='lzy-editable-cancel-button lzy-button' title='{{ lzy-editable-cancel-text }}'>$cancelSymbol<span class='invisible'> {{ lzy-editable-cancel-text }}</span></button></div>
</div>

EOT;

$page->addBody($textResources);


class Editable extends LiveData
{
    public function __construct($lzy, $args = false)
    {
        if (!@$args['dataSource']) {
            $args['dataSource'] = DEFAULT_EDITABLE_DATA_FILE;
        }
        parent::__construct($lzy, $args);
        $this->page = $lzy->page;
        $args = &$this->args;

        // handle case liveData enabled:
        $liveData =  isset($args['liveData']) ? $args['liveData'] : false;
        if ($liveData) {
            $this->page->addModules('~sys/extensions/livedata/js/live_data.js');
//            $jq = <<<EOT
//
//if (typeof LiveData !== 'undefined') {
//    LiveData.init();
//}
//
//EOT;
//            $this->page->addJq( $jq );
        }

        // check permission:
        $args['editableBy'] = $editableBy = isset($args['editableBy']) ? $args['editableBy'] : true;
        $args['edEnabled'] = false;
        if ($editableBy) {
            if ($editableBy === true) {
                $args['edEnabled'] = true;
            } else {
                $args['edEnabled'] = $this->lzy->auth->checkPrivilege($editableBy);
            }
        }

        // initialize Editable mechanism:
        if (!$GLOBALS['globalParams']['editableInitialized']) {
            $this->page->addModules('~sys/extensions/editable/css/editable.css');
            if ($args['edEnabled']) {
                $this->page->addModules('~sys/extensions/editable/js/editable.js');
                $jq = <<<EOT

if (typeof Editable !== 'undefined') {
    Editable.init();
}

EOT;
                $this->page->addJq( $jq );
            } else {
                $this->page->addJq('mylog("Editable feature disabled - insufficient privilege.");');
            }

            if (isset($args['enableDoubleClick']) && $args['enableDoubleClick']) {
                $this->page->addJs("\nconst lzyEnableEditableDoubleClick = true;\n");
            } else {
                $this->page->addJs("\nconst lzyEnableEditableDoubleClick = false;\n");
            }

            $jq = <<<EOT
$('.lzy-editable').tooltipster({
    animation: 'fade',
    delay: 200,
    animation: 'grow',
    maxWidth: 420,
});

EOT;
            $this->page->addJq( $jq );
            $this->page->addModules( 'TOOLTIPSTER' );


            $GLOBALS['globalParams']['editableInitialized'] = true;
        }

        // option liveData:
        $liveData = @$args['liveData'];
        if ($liveData) {
            $this->page->addModules('~sys/extensions/livedata/js/live_data.js');
//            $jq = <<<EOT
//
//if ($('[data-lzy-data-ref]').length) {
//    LiveData.init();
//}
//
//EOT;
//            $this->page->addJq($jq);
        }
        $GLOBALS['lizzy']['editableLiveDataInitialized'] = true;
        $this->editaleFldInx = 1;

    } // __construct



    public function render( $args = [], $returnAttrib = false )
    {
        $GLOBALS['globalParams']['editaleSetInx']++;
        $this->editaleSetInx = $GLOBALS['globalParams']['editaleSetInx'];

        $args = $this->prepareArguments( $args );

        if (isset($args['output']) && ($args['output'] === false)) {
            $args = $this->args;
            $args['dataSource'] = '~/' . $args['dataSource'];
            $dataRef = parent::render($args, true);
            $out = "\t<div class='lzy-data-ref'$dataRef></div>\n";
            $this->initDataRef($args); // add 'editableBy' to ticket

        } else {
            $out = $this->renderEditableFieldSet();
        }
        return $out;
    } // render




    private function renderEditableFieldSet()
    {
        $args = &$this->args;
        $args['mode'] = 'array';    // to get back an array of elements instead of a string
        $args['tag'] = 'div';
        $args['dataSource'] = '~/'.$args['dataSource'];

        $dataRef = $this->initDataRef($args);

        $data = $this->db->read();

        $id = $args['id'] ? " id='{$args['id']}'" : '';
        $eClass = '';

        if (@$args['multiline']) {
            $args['wrapperClass'] .= ' lzy-editable-multiline';
        }

        $class = trim("lzy-editable-wrapper {$args['showButtonClass']}{$args['wrapperClass']}");
        if (!$args['edEnabled']) {
            $class .= ' lzy-editable-inactive';
        }

        $out = '';

        foreach ($this->targetSelectors as $i => $eId) {
            if ($eId[0] === '#') {
                $eId = substr($eId, 1);
                $eClass = '';
            } elseif ($eId[0] === '.') {
                $eClass = substr($eId, 1);
                $eId = "lzy-editable-$this->editaleSetInx-". ($i + 1);
            }
            $value = '';
            $title = '';
            if (isset( $data[ $this->dataSelectors[$i] ]) ) {
                if ($this->db->isRecLocked($this->dataSelectors[$i])) {
                    $eClass .= ' lzy-element-locked';
                    $title = ' title="{{ lzy-editable-element-locked }}"';
                }
                $value = $data[ $this->dataSelectors[$i] ];
                if ($value && $this->freezeFieldAfter) {
                    $lastModif = $this->db->lastModifiedElement($this->dataSelectors[$i]);
                    if ($lastModif < (time() - $this->freezeFieldAfter)) {
                        $eClass .= ' lzy-element-frozen';
                        $title = ' title="{{ lzy-editable-element-frozen }}"';
                    }
                }
            }
            $eClass = $eClass? ' '.trim($eClass): '';
            $dRef = " data-ref='{$this->dataSelectors[$i]}'";
            $out .= "\t\t<div id='$eId' class='lzy-editable$eClass'$dRef$title>$value</div>\n";
        }

        $class = $class? str_replace('  ', ' ', $class): '';
        $out = "\t<div$id class='$class'$dataRef>\n$out";
        $out .= "\t</div><!-- /lzy-editable-wrapper -->\n";
        return $out;
    } // renderEditableFieldSet




    //---------------------------------------------------------------
    private function prepareArguments( $args )
    {
        if ($args) {
            $args = array_merge($this->args, $args);
        } else {
            $args = $this->args;
        }
        $args['nCols'] = isset($args['nCols']) ? $args['nCols'] : 1;
        $args['nRows'] = isset($args['nRows']) ? $args['nRows'] : 1;

        $setInx = $this->setInx;
        if (!isset($args['dataSelector'])) {
            if ($args['nCols'] > 1) {
                if ($args['nRows'] > 1) {
                    $args['dataSelector'] = '*,*';
                } else {
                    $args['dataSelector'] = '*';
                }
            } else {
                $editaleFldInx1 = $this->editaleFldInx;
                $args['dataSelector'] = "elem-$setInx-$this->editaleFldInx";
                if (@$args['nFields']) {
                    $n = intval($args['nFields']);
                    for ($i=1; $i<$n; $i++) {
                        $this->editaleFldInx++;
                        $args['dataSelector'] .= "|elem-$setInx-$this->editaleFldInx";
                    }
                }
            }
        }
        if (!isset($args['targetSelector'])) {
            if (($args['nCols'] > 1) || ($args['nRows'] > 1)) {
                if ($args['nRows'] > 1) {
                    $args['targetSelector'] = "#lzy-table{$this->setInx} .lzy-row-* .lzy-col-*";
                } else {
                    $args['targetSelector'] = "#lzy-table{$this->setInx} .lzy-col-*";
                }
            } else {
                $args['targetSelector'] = "#lzy-editable-$setInx-$editaleFldInx1";
                if (@$args['nFields']) {
                    $n = intval($args['nFields']);
                    for ($i=1; $i<$n; $i++) {
                        $editaleFldInx1++;
                        $args['targetSelector'] .= "|#lzy-editable-$setInx-$editaleFldInx1";
                    }
                }
            }
        }

        $args['id'] = @$args['id']? translateToClassName($args['id']) : '';
        $args['wrapperClass'] = @$args['class'] ? " {$args['class']}" : '';

        if (@$args['freezeFieldAfter']) {
            $args['freezeThreshold'] = time() - intval($args['freezeFieldAfter']);
            $this->freezeFieldAfter = intval($args['freezeFieldAfter']);
        } else {
            $args['freezeThreshold'] = 0;
            $this->freezeFieldAfter = false;
        }

        $args['showButtonClass'] = '';
        if (isset($args['showButton'])) {

            if ($args['showButton'] === 'ok') {                 // explicit: ok
                $args['showButtonClass'] = ' lzy-editable-show-button';

            } elseif ($args['showButton'] === 'auto') {         // explicit: auto
                $args['showButtonClass'] = ' lzy-editable-auto-show-button';

            } elseif ($args['showButton']) {                    // explicit: true
                $args['showButtonClass'] = ' lzy-editable-show-buttons';
            }
        } else {                                                // implicit: true
            $args['showButtonClass'] = ' lzy-editable-show-buttons';
        }

//        if ($GLOBALS['globalParams']['editableInx'] === 1) {
//            if (isset($args['enableDoubleClick']) && $args['enableDoubleClick']) {
//                $this->lzy->page->addJs("\nconst lzyEnableEditableDoubleClick = true;\n");
//            } else {
//                $this->lzy->page->addJs("\nconst lzyEnableEditableDoubleClick = false;\n");
//            }
//        }
        // dataFile synonyme for dataSource for backward compatibility:
        if (!isset($args['dataSource']) && @$args['dataFile']) {
            $args['dataSource'] = $args['dataFile'];
        }

        if (@$args['dataSource']) {
            if (is_string(@$args['dataKey']) && preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*$/', $args['dataKey'], $m)) {
                $args['dataKey'] = (intval($m[1]) - 1) . ',' . (intval($m[2]) - 1);
                $args['id'] = 'lzy-editable-field' . $this->editaleSetInx;
            }

            $dataSource1 = resolvePath($args['dataSource'], true);
            if (file_exists($dataSource1)) {
                if (is_file($dataSource1)) {
                    $args['dataSource'] = $dataSource1;
                } elseif (is_dir($dataSource1)) {
                    $args['dataSource'] = $dataSource1 . DEFAULT_EDITABLE_DATA_FILE;
                } else {
                    fatalError("Error: folder to store editable data does not exist.");
                }

            } elseif (file_exists(basename($dataSource1))) {    // folder exists, but not the file
                $args['dataSource'] = $dataSource1 . DEFAULT_EDITABLE_DATA_FILE;

            } else {
                preparePath($dataSource1);
                touch($dataSource1);
                $args['dataSource'] = $dataSource1;
            }
        } else {
            $args['dataSource'] = $GLOBALS['globalParams']['pageFolder'] . DEFAULT_EDITABLE_DATA_FILE;
        }
        $this->args = $args;
        return $args;
    } // prepareArguments



/*
//    private function prepareProtectedCellsArray($args)
//    {
//        $protectedCells = [];
//        if ($args['protectedCells']) {
//            $protCells = $args['protectedCells'];
//            $delim = (substr_count($protCells, ',') > substr_count($protCells, '|')) ? ',' : '|';
//            $elems = parseArgumentStr($protCells, $delim);
//            $protectedCells = array_fill(0, $args['nRows'], array_fill(0, $args['nCols'], false));;
//            foreach ($elems as $elem) {
//                if (!trim($elem)) {
//                    continue;
//                }
//                $param = substr($elem, 1);
//                $paramArr = parseNumbersetDescriptor($param);
//                switch ($elem[0]) {
//                    case 'r':
//                        while ($r = array_shift($paramArr)) {
//                            $r = intval($param) - 1;
//                            $protectedCells[$r] = array_fill(0, $args['nCols'], true);
//                        }
//                        break;
//                    case 'c':
//                        while ($c = array_shift($paramArr)) {
//                            $c = intval($c) - 1;
//                            for ($r = 0; $r < $args['nRows']; $r++) {
//                                $protectedCells[$r][$c] = true;
//                            }
//                        }
//                        break;
//                    case 'e':
//                        list($c, $r) = preg_split('/\D/', $param);
//                        $r = intval($r) - 1;
//                        $c = intval($c) - 1;
//                        $protectedCells[$r][$c] = true;
//                        break;
//                }
//            }
//        }
//        $args['protectedCells'] = $protectedCells;
//        return $args;
//    } // prepareProtectedCellsArray
*/




    private function initDataRef($args)
    {
        $args['tickRecCustomFields'] = [
            '_useRecycleBin'    => @$args['useRecycleBin'],
            '_freezeFieldAfter' => @$args['freezeFieldAfter'],
            '_editableBy'       => @$args['editableBy'],
            '_multiline'        => @$args['multiline'],
        ];

        return parent::render($args, true);
    } // initDataRef

} // class Editable

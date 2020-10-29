<?php

$GLOBALS['globalParams']['editableInx'] = 0;

$page->addModules('JS_POPUPS');

$textResources = <<<EOT
<div style="display: none">
    <div id='db-error' style="display: none">{{ lzy-editable-db-error }}</div>
    <div id='error-locked' style="display: none">{{ lzy-editable-db-locked }}</div>
    <div id='conn-error' style="display: none">{{ lzy-editable-conn-error }}</div>
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
    } // __construct



    public function render( $args = [], $returnAttrib = false )
    {
        $GLOBALS['globalParams']['editableInx']++;
        $this->editaleSetInx = $GLOBALS['globalParams']['editableInx'];

        $args = $this->prepareArguments( $args );

        if (($args['nCols'] === 1) && ($args['nRows'] === 1)) {
            $out = $this->renderEditableFields();
        } else {
            $out = $this->renderTable();
        }
        return $out;
    } // render



    //---------------------------------------------------------------
    private function renderEditableFields()
    {
        $args = &$this->args;
        $args['mode'] = 'array';    // to get back an array of elements instead of a string
        $args['tag'] = 'div';
        $args['dataSource'] = '~/'.$args['dataSource'];

        $tickRecCustomFields = [
            'useRecycleBin' => @$args['useRecycleBin'],
            'freezeFieldAfter' => @$args['freezeFieldAfter'],
        ];
        $args['tickRecCustomFields'] = $tickRecCustomFields;

        $dataRef = parent::render( $args, true );

        $data = $this->db->read();

        $id = $args['id'] ? " id='{$args['id']}'" : '';
        $eClass = '';

        $class = trim("lzy-editable-wrapper {$args['showButtonClass']}{$args['wrapperClass']}");
        $out = '';

        foreach ($this->targetSelectors as $i => $eId) {
            if ($eId[0] === '#') {
                $eId = substr($eId, 1);
                $eClass = '';
            } elseif ($eId[0] === '.') {
                $eClass = substr($eId, 1);
                $eId = "lzy-editable-$this->editaleSetInx-1";
            }
            $value = '';
            if (isset( $data[ $this->dataSelectors[$i] ]) ) {
                if ($this->db->isRecLocked($this->dataSelectors[$i])) {
                    $eClass .= ' lzy-editable-locked';
                }
                $value = $data[ $this->dataSelectors[$i] ];
                if ($value && $this->freezeFieldAfter) {
                    $lastModif = $this->db->lastModifiedElement($this->dataSelectors[$i]);
                    if ($lastModif < (time() - $this->freezeFieldAfter)) {
                        $eClass .= ' lzy-editable-frozen';
                    }
                }
            }
            $eClass = trim($eClass);
            $out .= "\t\t<div id='$eId' class='lzy-editable $eClass'>$value</div>\n";
        }

        $out = "\t<div$id class='$class'$dataRef>\n$out";
        $out .= "\t</div><!-- /lzy-editable-wrapper -->\n";
        return $out;
    } // renderEditableFields



    //---------------------------------------------------------------
    private function renderTable()
    {
        require_once SYSTEM_PATH.'htmltable.class.php';

        $options = $this->args;
        $options['id'] = '';
        $options['cellClass'] = 'lzy-editable';
        $options['tableDataAttr'] = "lzy-editable=$this->ticketHash";
        $options['includeCellRefs'] = true;
        if (!$options['nCols']) {
            $options['nCols'] = 1;
        }
        if (!$options['nRows']) {
            $options['nRows'] = 1;
        }
        if ($options['class']) {
            $options['cellClass'] = $options['class'];
            $options['class'] = '';
        }
    //    $options['cellMask'] = $args['protectedCells'];
    //    $options['cellMaskedClass'] = 'lzy-non-editable';
        unset($options['dataSource']);

        $tbl = new HtmlTable($this->lzy, $this->editaleSetInx, $options);
        $out = $tbl->render();
        return $out;
    } // renderTable




    //---------------------------------------------------------------
    private function prepareArguments( $args )
    {
        if ($args) {
            $args = array_merge($this->args, $args);
        } else {
            $args = $this->args;
        }
        $args['inx'] = $this->editaleSetInx;

        if (!isset($args['dataSelector'])) {
            $args['dataSelector'] = 'elem' . $this->editaleSetInx;
        }
        if (!isset($args['targetSelector'])) {
            $args['targetSelector'] = 'lzy-editable' . $this->editaleSetInx;
        }

        $args['id'] = $args['id']? translateToClassName($args['id']) : '';
        $args['wrapperClass'] = @$args['class'] ? " {$args['class']}" : '';
        $args['class'] = @$args['class']? "lzy-editable {$args['class']}": 'lzy-editable';

        if (@$args['freezeFieldAfter']) {
            $args['freezeThreshold'] = time() - intval($args['freezeFieldAfter']);
            $this->freezeFieldAfter = intval($args['freezeFieldAfter']);
        } else {
            $args['freezeThreshold'] = 0;
            $this->freezeFieldAfter = false;
        }
//        $args['freezeThreshold'] = @$args['freezeFieldAfter'] ? time() - intval($args['freezeFieldAfter']) : 0;
//        $this->freezeThreshold = intval($args['freezeFieldAfter']);


        $args['showButtonClass'] = '';
        if (isset($args['showButton'])) {
            if ($args['showButton'] === 'auto') {
                $args['showButtonClass'] = ' lzy-editable-auto-show-button';
            } elseif ($args['showButton'] === 'all') {
                $args['showButtonClass'] = ' lzy-editable-show-buttons';
            } elseif ($args['showButton']) {
                $args['showButtonClass'] = ' lzy-editable-show-button';
            }
        }

        //    $args = prepareProtectedCellsArray($args);

        $args['nCols'] = max(1, intval(@$args['nCols']));
        $args['nRows'] = max(1, intval(@$args['nRows']));

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




    //---------------------------------------------------------------
    private function prepareProtectedCellsArray($args)
    {
        $protectedCells = [];
        if ($args['protectedCells']) {
            $protCells = $args['protectedCells'];
            $delim = (substr_count($protCells, ',') > substr_count($protCells, '|')) ? ',' : '|';
            $elems = parseArgumentStr($protCells, $delim);
            $protectedCells = array_fill(0, $args['nRows'], array_fill(0, $args['nCols'], false));;
            foreach ($elems as $elem) {
                if (!trim($elem)) {
                    continue;
                }
                $param = substr($elem, 1);
                $paramArr = parseNumbersetDescriptor($param);
                switch ($elem[0]) {
                    case 'r':
                        while ($r = array_shift($paramArr)) {
                            $r = intval($param) - 1;
                            $protectedCells[$r] = array_fill(0, $args['nCols'], true);
                        }
                        break;
                    case 'c':
                        while ($c = array_shift($paramArr)) {
                            $c = intval($c) - 1;
                            for ($r = 0; $r < $args['nRows']; $r++) {
                                $protectedCells[$r][$c] = true;
                            }
                        }
                        break;
                    case 'e':
                        list($c, $r) = preg_split('/\D/', $param);
                        $r = intval($r) - 1;
                        $c = intval($c) - 1;
                        $protectedCells[$r][$c] = true;
                        break;
                }
            }
        }
        $args['protectedCells'] = $protectedCells;
        return $args;
    } // prepareProtectedCellsArray

} // class Editable

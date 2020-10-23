<?php

$GLOBALS['globalParams']['editableInx'] = 0;

class Editable extends LiveData
{
//    protected $args = [];

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

        // create or obtain ticket from previous session:
//        $ticketing = new Ticketing(['hashSize' => 8, 'defaultType' => 'editable', 'defaultValidityPeriod' => 900]);
//        $rec["e$inx"] = [
//            'dataSrc' => $args['dataSource'],
//            'useRecycleBin' => @$args['useRecycleBin'],
//            'freezeFieldAfter' => @$args['freezeFieldAfter'],
//        ];
//        $ticketHash = $ticketing->createTicket($rec, 99999);
//        $this->ticketHash = "$ticketHash:e$inx";

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

        $id = $args['id'] ? " id='{$args['id']}'" : '';
        $eClass = '';

        $class = trim("lzy-editable-wrapper {$args['showButtonClass']}{$args['wrapperClass']}");
        $out = "\t<div$id class='$class'$dataRef>\n";

        foreach ($this->targetSelectors as $eId) {
            if ($eId[0] === '#') {
                $eId = substr($eId, 1);
            } elseif ($eId[0] === '.') {
                $eClass = ' class="'.substr($eId, 1).'"';
                $eId = "lzy-editable-$this->editaleSetInx-1";
            }
            $out .= "\t\t<div id='$eId' class='lzy-editable'$eClass></div>\n";
        }

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
//            $args['targetSelector'] = '';
        }

        $args['id'] = $args['id']? translateToClassName($args['id']) : '';
        $args['wrapperClass'] = @$args['class'] ? " {$args['class']}" : '';
//        $args['class'] = 'lzy-editable';
        $args['class'] = @$args['class']? "lzy-editable {$args['class']}": 'lzy-editable';

        $args['freezeThreshold'] = @$args['freezeFieldAfter'] ? time() - intval($args['freezeFieldAfter']) : 0;

        $args['showButtonClass'] = '';
        if (isset($args['showButton'])) {
            if ($args['showButton'] === 'auto') {
                $args['showButtonClass'] = ' lzy-editable-auto-show-button';
            } elseif ($args['showButton']) {
                $args['showButtonClass'] = ' lzy-editable-show-button';
            }
        }

        //    $args = prepareProtectedCellsArray($args);

        $args['nCols'] = max(1, intval(@$args['nCols']));
        $args['nRows'] = max(1, intval(@$args['nRows']));
//        $args['nCols'] = intval(@$args['nCols']);
//        $args['nRows'] = intval(@$args['nRows']);

        // dataFile synonyme for dataSource for backward compatibility:
        if (!isset($args['dataSource']) && @$args['dataFile']) {
            $args['dataSource'] = $args['dataFile'];
        }

        if (@$args['dataSource']) {
            //        if (preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*$/', $args['dataKey'], $m)) {
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




//    //---------------------------------------------------------------
//    private function prepareDataSource($args)
//    {
//        if (@$args['dataSource']) {
//    //        if (preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*$/', $args['dataKey'], $m)) {
//            if (is_string(@$args['dataKey']) && preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*$/', $args['dataKey'], $m)) {
//                $args['dataKey'] = (intval($m[1]) - 1) . ',' . (intval($m[2]) - 1);
//                $args['id'] = 'lzy-editable-field' . $this->inx;
//            }
//
//            $dataSource1 = resolvePath($args['dataSource'], true);
//            if (file_exists($dataSource1)) {
//                if (is_file($dataSource1)) {
//                    $args['dataFile'] = $dataSource1;
//                } elseif (is_dir($dataSource1)) {
//                    $args['dataFile'] = $dataSource1 . DEFAULT_EDITABLE_DATA_FILE;
//                } else {
//                    fatalError("Error: folder to store editable data does not exist.");
//                }
//
//            } elseif (file_exists(basename($dataSource1))) {    // folder exists, but not the file
//                $args['dataFile'] = $dataSource1 . DEFAULT_EDITABLE_DATA_FILE;
//
//            } else {
//                preparePath($dataSource1);
//                touch($dataSource1);
//                $args['dataFile'] = $dataSource1;
//            }
//        } else {
//            $args['dataFile'] = $GLOBALS['globalParams']['pageFolder'] . DEFAULT_EDITABLE_DATA_FILE;
//        }
//        $args['dataSource'] = $args['dataFile'];
//        return $args;
//    } // prepareDataSource

} // class Editable

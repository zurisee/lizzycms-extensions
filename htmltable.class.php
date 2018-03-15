<?php


class HtmlTable
{
    public function __construct($page, $inx, $options)
    {
        global $tableCounter;
        $this->page 		= $page;
        $this->tableCounter = &$tableCounter;

        $this->id 			= (isset($options['id'])) ? $options['id'] : false;
        $this->tableclass 	= (isset($options['tableclass'])) ? " class='{$options['tableclass']}'" : '';
        $this->cellclass 	= (isset($options['cellclass'])) ? $options['cellclass'] : '';
        $this->nRows 		= (isset($options['nRows'])) ? $options['nRows'] : false;
        $this->nCols 		= (isset($options['nCols'])) ? $options['nCols'] : false;
        $this->columns 		= (isset($options['columns'])) ? $options['columns'] : false;
        $this->filter 		= (isset($options['filter'])) ? $options['filter'] : false;
        $this->sort 		= (isset($options['sort'])) ? $options['sort'] : false;
        $this->autoConvertLinks = (isset($options['autoConvertLinks'])) ? $options['autoConvertLinks'] : false;
        $this->paging 		= (isset($options['paging'])) ? " 'paging': ".(($options['paging']!='false')?'true':'false').',' : '';
        $this->searching 	= (isset($options['searching'])) ? " 'searching': ".(($options['searching']!='false')?'true':'false').',' : '';
        $this->processing	= (isset($options['processing'])) ? $options['processing'] : false;
        $this->caption	    = (isset($options['caption'])) ? $options['caption'] : false;
        $this->captionIndex = (isset($options['captionIndex'])) ? $options['captionIndex'] : false;
        $this->headersTop	= (isset($options['headersTop'])) ? $options['headersTop'] : false;
        $this->headersLeft	= (isset($options['headersLeft'])) ? $options['headersLeft'] : false;
        $this->renderAsDiv	= (isset($options['renderAsDiv'])) ? $options['renderAsDiv'] : false;
        $this->renderAsDiv  = !(($this->renderAsDiv === false) || ($this->renderAsDiv == 'false'));

        if ($this->headersTop && preg_match('/^\[(.*)\]$/', $this->headersTop, $m)) {
            $this->headersTop = explode(',', $m[1]);
        } else {
            $this->headersTop = false;
        }
        if ($this->headersLeft && preg_match('/^\[(.*)\]$/', $this->headersLeft, $m)) {
            $this->headersLeft = explode(',', $m[1]);
        } else {
            $this->headersLeft = false;
        }


        if (isset($options['dataSource'])) {
            $this->dataSource = $options['dataSource'];
            if (strpos($this->dataSource, '~') !== 0) {
                $this->dataSource = '~page/'.$this->dataSource;
            }
            $this->dataSource = resolvePath($options['dataSource'], true);
        } else {
            $this->dataSource = false;
        }

        if (!file_exists($this->dataSource)) {
            $this->dataSource = false;
        }

        $this->data = false;
        if ($this->dataSource) {
            $ds = new DataStorage($this->dataSource);
            $this->data = $ds->read();
        }


        if (!$this->id) {
            $this->id = 'table'.$inx;
        }

        if (!$this->data && !$this->nRows && !$this->nCols) {
            fatalError("Error in Table() macro: must specify at least one of [dataSource | nRows | nCols]", 'File: '.__FILE__.' Line: '.__LINE__);
        }

        if ($this->data) {
            $this->nRows = ($this->nRows) ? $this->nRows : sizeof($this->data);
            $this->nCols = ($this->nCols) ? $this->nCols : sizeof($this->data[0]);
        } else {
            $this->nRows = ($this->nRows) ? $this->nRows : 1;
            $this->nCols = ($this->nCols) ? $this->nCols : 1;
        }

        if ($this->processing) {
            $this->preProcessData();
        }

        if (strpos($this->tableclass, 'datatables') !== false) {
            $page->addCssFiles('DATATABLES_CSS');
            $page->addJqFiles('DATATABLES');
            $order = '';
            if ($this->sort) {
                $sortCols = csv_to_array($this->sort);
                $headers = $this->data[0];
                foreach ($sortCols as $sortCol) {
                    $sortCol = alphaIndexToInt($sortCol, $headers) - 1;
                    $order .= "[ $sortCol, 'asc' ],";
                }
                $order = rtrim($order, ',');
                $order = " 'order': [$order],";
            }
            $page->addJq("\t\$('.datatables').DataTable({ 'language':{'search':'{{QuickSearch}}:', 'info': '_TOTAL_ {{Records}}'}, $order {$this->paging}{$this->searching} });\n");
        }


        if ($this->caption) {
            $tableCounter++;
            $this->tableCounter = $tableCounter;
            if ($this->captionIndex) {
                $this->tableCounter = $this->captionIndex;
            }
            $this->caption = str_replace('##', $this->tableCounter, $this->caption);
            if ($this->renderAsDiv) {
                $this->caption = "\t\t<div class='caption'>$this->caption</div>\n";
            } else {
                $this->caption = "\t\t<caption>$this->caption</caption>\n";
            }
        }

    } // __construct




    //----------------------------------------------------------
    public function render()
    {
        if ($this->renderAsDiv) {
            return $this->renderDiv();
        } else {
            return $this->renderTable();
        }
    } // render



    //----------------------------------------------------------
    private function renderTable()
    {
        $header = '';
        $row = 1;
        $tdClass = ($this->cellclass) ? " class='{$this->cellclass}'" : '';

        $hdrLabels = false;
        if ($this->headersTop) {
            $hdrLabels = $this->headersTop;
        } elseif (isset($this->data[0])) {
            $hdrLabels = $this->data[0];
        }


        // Columns
        if ($this->columns) {
            $activeCols = parseNumbersetDescriptor($this->columns, 1, $this->nCols, $hdrLabels);
        } else {
            $activeCols = range(1,$this->nCols);
        }



        // Table Header:
        if ($this->headersTop || $this->data) {
            $header = <<<EOT

	<thead>
	  <tr>

EOT;

            foreach($activeCols as $col) {
                $name = false;
                if (is_array($col)) {   // it's an array if user used column definition containing column label, as in "A:Label"
                    $name = $col[1];
                }
                if (!$name) {
                    $col -= 1;
                    if (isset($hdrLabels[$col])) {
                        $name = $hdrLabels[$col];
                    } else {
                        $name = '???';
                    }
                }
                $header .= "\t\t<th>$name</th>\n";
            }
            $header .= "\t  </tr>\n\t</thead>\n";
            $row++;
        }

        if (isset($activeCols[0][0])) {
            array_walk($activeCols, function (&$e) {
                $e = $e[0];
            });
        }


        // Table Body:
        $tbody = '';
        for (; $row<=$this->nRows; $row++) {
            $rowData = &$this->data[$row-1];
            $rawData = null;
            $rowStr = '';

            if (!$this->applyFilter($rowData)) {
                continue;
            }

            foreach($activeCols as $col) {
                $col -= 1;
                $d = isset($rowData[$col]) ? $rowData[$col]: '';
                $rawData .= $d;
                $colName = $hdrLabels[$col];
                if ($this->autoConvertLinks && (($this->autoConvertLinks == 'true') ||
                        ((strpos($this->autoConvertLinks, $colName) !== false)))) {
                    if (preg_match('/^(.*)\@(.*\.\w{2,6})$/', $d, $m)) {
                        $d = "<a href='mailto:$d'>$d</a>";

                    } elseif (preg_match('/^([\d\-\s\(\)]*)$/', $d, $m)) {
                        $tel = preg_replace('/\D/', '', $d);
                        if (strlen($tel) > 7) {
                            $d = "<a href='tel:$tel'>$d</a>";
                        }
                    }
                }
                if (!$d) {
                    $d = '&nbsp;';
                }
                $rowStr .= "\t\t<td$tdClass>$d</td>\n";
            }
            if ($rawData !== null) {
                $tbody .= "\t  <tr>\n";
                $tbody .= $rowStr;
                $tbody .= "\t  </tr>\n";
            }
        }


        $out = <<<EOT

<table id='$this->id'{$this->tableclass}>
$this->caption$header
	<tbody>
$tbody	</tbody>
</table>

EOT;
        return $out;
    } // renderTable



    //----------------------------------------------------------
    private function renderDiv()
    {
        $out = "\t";
        $r = 1;
        if ($this->headersTop) {
            $cl = " class='divTableHdr'";
        } else {
            $cl = ($this->cellclass) ? " class='divTableCell $this->cellclass'" : '';
        }



        if (($this->nCols == 1) && ($this->nRows == 1)) {     // special case: single 1x1 ield
            if (isset($this->data[0][0])) {
                $val = $this->data[0][0];
            } else {
                $val = '';
            }
            $out .= "<div id='$this->id'$cl>$val</div>";
            return $out;
        }


        $colIds = [];
        for ($c=1; $c<=$this->nCols; $c++) {           // render header row
            $val = '';
            $colIds[$c] = $c;
            if (isset($this->headersTop[$c-1])) {
                $val = trim(preg_replace('/^"(.*)"$/', "$1", $this->headersTop[$c-1]));
                $colIds[$c] = translateToIdentifier($val);
            }
            $out .= "<div id='{$this->id}{$r}_{$colIds[$c]}'$cl>$val</div>";
        }
        $r++;
        if ($this->nCols > 1) {
            $out = "\t\t<div class='divTableRow'>\n\t\t$out\n\t\t</div><!-- /row -->\n";
        } else {
            $out = "\t\t$out\n";
        }


        $this->id0 = $this->id;
        for (; $r<=$this->nRows; $r++) {               // render table body
            $cells = '';
            for ($c=1; $c<=$this->nCols; $c++) {
                $this->id = "$this->id0{$r}_".$colIds[$c];
                $cl = ($this->cellclass) ? " class='divTableCell $this->cellclass'" : " class='divTableCell'";
                $val = '';
                if ($this->headersLeft) {
                    if ($c == 1) {
                        $td = 'th';
                        $cl = " class='divHdr'";
                        if (isset($this->headersLeft[$c-1])) {
                            $val = preg_replace('/^"(.*)"$/', "$1", $this->headersLeft[$c-1]);
                        }
                    }
                } elseif (isset($this->data[$r][$c])) {
                    $val = $this->data[$r][$c];
                }
                $cells .= "<div id='$this->id'$cl>$val</div>";
            }
            if ($this->nCols > 1) {
                $out .= "\t\t<div class='divTableRow'>\n\t\t\t$cells\n\t\t</div><!-- /row -->\n";
            } else {
                $out .= "\t\t$cells\n";
            }
        }

        if (($this->nRows > 1) || ($this->nCols > 1)) {
            $out = <<<EOT

	<div id='$this->id' class='divTable'>
$out{$this->caption}
	</div> <!-- /$this->id -->

EOT;
        }

        return $out;
    } // renderDiv




//-------------------------------------------------------------------
    private function preProcessData()
    {
        $processings = explode(';', $this->processing);
        foreach ($processings as $process) {
            if (!preg_match('/col\((.+)\):(.*)/', $process, $m)) {
                continue;
            }
            $algo = $m[2];
            if (strpos($algo, 'return') === false) {
                $algo = "return $algo;";
            }
            $cols = parseNumbersetDescriptor($m[1]);
            foreach ($cols as $col) {
                $col--;
                for ($r = 1; $r < $this->nRows; $r++) {
                    $val = $this->data[$r][$col];
                    $code = str_replace('$val', $val, $algo);
                    $this->data[$r][$col] = eval($code);
                }
            }
        }
    } // preProcessData



//-------------------------------------------------------------------
    private function applyFilter($rowData)
    {
        $filter = $this->filter;
        if (!$filter || !isset($this->data[0])) {
            return true;
        }

        $headers = $this->data[0];
        if ($filter) {				// filter, i.e. skip undesired rows
            while (preg_match('/(.*) col\(  ([^)]+)  \)  (.*)/x', $filter, $m)) {
                list($eaten, $before, $colName, $rest) = $m;
                $col = alphaIndexToInt($colName, $headers)-1;
                if (isset($rowData[$col]) && $rowData[$col]) {
                    $val = $rowData[$col];
                } else {
                    $val = '';
                }
                $filter = $before."'$val'".$rest;
            }
            $filter = "return ($filter);";
            $filter = eval($filter);
        } else {
            $filter = true;
        }
        return $filter;
    } // applyFilter



//-------------------------------------------------------------------
    private function getData()
    {
        $this->dataFile = $this->dataSource;
        if ($this->dataFile) {
            $ds = new DataStorage($this->dataFile);
        } else {
            $ds = new DataStorage('~page/'.$this->id.'.yaml');
        }

        $this->data = $ds->read();
        if (!$this->data) {
            return "<p>{{ no data found for }} '$this->id'</p>";
        }
        if (!isset($this->data[0])) {
            return '';
        }
        return true;
    } // getData

} // HtmlTable

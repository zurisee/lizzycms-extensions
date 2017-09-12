<?php
/*
 * Table Macro
 * takes a data-source containing 2 dimensional array data, wraps it in HTML
 * Data-sources formats:
 * 		- Yaml
 * 		- Json
 * 		- CVS (standard and Microsoft)
 *
 * DataTables Plugin:
 * -> activate by added table-class = 'datatables'
 * for options see https://datatables.net/manual/index
*/

define('ORD_A', 	ord('a'));
require_once SYSTEM_PATH.'datastorage.class.php';

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $options = $this->getArgsArray($macroName, false);

    $htmlTable = new HtmlTable($this->page, $inx, $options);
	$table = $htmlTable->render();
	
	return $table;
});

class HtmlTable
{	
	public function __construct($page, $inx, $options)
	{
		$this->page 		= $page;

		$this->id 			= (isset($options['id'])) ? $options['id'] : false;
		$this->tableclass 	= (isset($options['tableclass'])) ? ' '.$options['tableclass'] : '';
		$this->cellclass 	= (isset($options['cellclass'])) ? " class='{$options['cellclass']}'" : '';
		$this->columns 		=  (isset($options['columns'])) ? $options['columns'] : false;
		$this->filter 		= (isset($options['filter'])) ? $options['filter'] : false;
		$this->sort 		= (isset($options['sort'])) ? $options['sort'] : false;
		$this->autoConvertLinks = (isset($options['autoConvertLinks'])) ? $options['autoConvertLinks'] : false;
		$this->paging 		= (isset($options['paging'])) ? " 'paging': ".(($options['paging']!='false')?'true':'false').',' : '';
		$this->searching 	= (isset($options['searching'])) ? " 'searching': ".(($options['searching']!='false')?'true':'false').',' : '';
        $this->processing	= (isset($options['processing'])) ? $options['processing'] : false;
        $this->caption	    = (isset($options['caption'])) ? $options['caption'] : false;
        $this->captionIndex = (isset($options['captionIndex'])) ? $options['captionIndex'] : false;


		if (isset($options['dataSource'])) {
			$this->dataSource = $options['dataSource'];
			if (strpos($this->dataSource, '~') !== 0) {
                $this->dataSource = '~page/'.$this->dataSource;
            }
			$this->dataSource = resolvePath($options['dataSource'], true);
		} else {
			$this->dataSource = false;
			return;
		}
		
		if (!file_exists($this->dataSource)) {
			$this->dataSource = false;
		}
		
		if (!$this->id) {
			$this->id = 'table'.$inx;
		}

        $res = $this->getData();
        if (is_string($res)) {
            return $this->error;
        } elseif (!$res) {
            return '';
        }

        $this->nRows = sizeof($this->data);
        $this->nCols = sizeof($this->data[0]);

        if ($this->processing) {
            $this->preProcessData();
        }

        if (strpos($this->tableclass, 'datatables') !== false) {
			$page->addCssFiles('https://cdn.datatables.net/v/dt/dt-1.10.15/datatables.min.css');
			$page->addJqFiles('https://cdn.datatables.net/v/dt/dt-1.10.15/datatables.min.js');
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
	} // __construct




//-------------------------------------------------------------------
	public function render()
	{
	    if (!$this->dataSource) {
	        return '';
        }
		$tdClass = $this->cellclass;
		$headers = $this->data[0];

        if (strpos($this->tableclass, 'datatables') === false) {
            if ($this->sort) {        // Sort data
                $sort = alphaIndexToInt($this->sort, $headers) - 1;
                $this->data = sort2dArray($this->data, $sort);
            }
        }

        $caption = '';      // prepare table caption
        if ($this->caption) {
            if (!isset($this->tableCounter)) {
                $this->tableCounter = 1;
            } else {
                $this->tableCounter++;
            }
            if ($this->captionIndex) {
                $this->tableCounter = $this->captionIndex;
            }
            $caption = str_replace('##', $this->tableCounter, $this->caption);
            $caption = "\t\t<caption>$caption</caption>\n";
        }


		// Columns
		if ($this->columns) {
			$activeCols = parseNumbersetDescriptor($this->columns, 1, $this->nCols, $headers);
		} else {
            $activeCols = range(1,$this->nCols);
		}


		// render table-header:
		$out = "\t<table id='$this->id' class='lizzyTable{$this->tableclass}'>\n$caption\t\t<thead>\n\t\t  <tr>\n";
		$hdrLabels = $this->data[0];
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
			$out .= "\t\t\t<th>$name</th>\n";
		}
		$out .= "\t\t  </tr>\n\t\t</thead>\n\t\t<tbody>\n";

		if (isset($activeCols[0][0])) {
            array_walk($activeCols, function (&$e) {
                $e = $e[0];
            });
        }


        // render table-body:
		for ($row=1; $row<$this->nRows; $row++) {
		    $rowData = &$this->data[$row];
			$rawData = '';
			$rowStr = '';

			if (!$this->applyFilter($rowData)) {
				continue;
			}

			foreach($activeCols as $col) {
				$col -= 1;
				$d = isset($rowData[$col]) ? $rowData[$col]: '';
				$rawData .= $d;
				$colName = $headers[$col];
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
				$rowStr .= "\t\t\t<td$tdClass>$d</td>\n";
			}
			if ($rawData) {
				$out .= "\t\t  <tr>\n";
				$out .= $rowStr;
				$out .= "\t\t  </tr>\n";
			}
		}
		
		$out .= "\t\t</tbody>\n\t</table>\n";
		return $out;
	} // render



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
        $headers = $this->data[0];
        $filter = $this->filter;
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
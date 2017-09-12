<?php

class HtmlTable
{
    public function __construct($options)
    {
        $this->options = $options;
        $this->id = $this->getOption('id');
        $this->class = $this->getOption('class');
        $this->cols = $this->getOption('cols', 1);
        $this->rows = $this->getOption('rows', 1);
        $this->headersTop = $this->getOption('headersTop');
        $this->headersLeft = $this->getOption('headersLeft', false);
        $this->dataSource = $this->getOption('dataSource');
        $this->caption = $this->getOption('caption');
        $this->captionIndex = $this->getOption('captionIndex');
        $this->renderAsDiv = $this->getOption('renderAsDiv', false);
        $this->renderAsDiv = !(($this->renderAsDiv === false) || ($this->renderAsDiv == 'false'));
        $this->tableCounter = 0;


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


        $this->data = false;
        if ($this->dataSource) {
            $ds = new DataStorage($this->dataSource);
            $this->data = $ds->read();
        }


        if ($this->caption) {
            $this->tableCounter++;
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
        $r = 1;
        if ($this->headersTop) {
            $header = <<<EOT

	<thead>
	  <tr>

EOT;

            for ($c=1; $c<=$this->cols; $c++) {
                $hdr = '';
                if (isset($this->headersTop[$c-1])) {
                    $hdr = preg_replace('/^"(.*)"$/', "$1", $this->headersTop[$c-1]);
                }
                $header .= "		<th id='{$this->id}{$r}_$c'>$hdr</th>\n";
            }
            $header .= <<<EOT

	  </tr>
	</thead>

EOT;
            $r++;
        }

        $tbody = '';
        for (; $r<=$this->rows; $r++) {
            $cells = '';
            for ($c=1; $c<=$this->cols; $c++) {
                $val = '';
                $td = 'td';
                $cl = ($this->class) ? " class='$this->class'" : '';
                if (isset($this->data[$r][$c])) {
                    $val = $this->data[$r][$c];
                } else {
                    $val = '';
                }
                if ($this->headersLeft && ($c == 1)) {
                      if (isset($this->headersLeft[$c-1])) {
                          $val = preg_replace('/^"(.*)"$/', "$1", $this->headersLeft[$c - 1]);
                      }
                }
                $cells .= "		<$td id='$this->id${r}_$c'$cl>$val</$td>\n";
            }
            $tbody .= <<<EOT

	<tr>
$cells	</tr>

EOT;
        }
        $out = <<<EOT

<table id='$this->id'>
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
            $cl = " class='divHdr'";
        } else {
            $cl = ($this->class) ? " class='$this->class'" : '';
        }



        if (($this->cols == 1) && ($this->rows == 1)) {     // special case: single 1x1 ield
//            $val = $this->db->read($this->id);
            if (isset($this->data[0][0])) {
                $val = $this->data[0][0];
            } else {
                $val = '';
            }
            $out .= "<div id='$this->id'$cl>$val</div>";
            return $out;
        }


        $colIds = [];
        for ($c=1; $c<=$this->cols; $c++) {           // render header row
            $val = '';
            $colIds[$c] = $c;
            if (isset($this->headersTop[$c-1])) {
                $val = trim(preg_replace('/^"(.*)"$/', "$1", $this->headersTop[$c-1]));
                $colIds[$c] = translateToIdentifier($val);
            }
            $out .= "<div id='{$this->id}{$r}_{$colIds[$c]}'$cl>$val</div>";
        }
        $r++;
        if ($this->cols > 1) {
            $out = "\t\t<div class='row'>\n\t\t$out\n\t\t</div><!-- /row -->\n";
        } else {
            $out = "\t\t$out\n";
        }


        $this->id0 = $this->id;
        for (; $r<=$this->rows; $r++) {               // render table body
            $cells = '';
            for ($c=1; $c<=$this->cols; $c++) {
                $this->id = "$this->id0{$r}_".$colIds[$c];
                $cl = ($this->class) ? " class='$this->class'" : '';
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
//                    $val = $this->db->read($this->id); //??
                    $val = $this->data[$r][$c];
                }
                $cells .= "<div id='$this->id'$cl>$val</div>";
            }
            if ($this->cols > 1) {
                $out .= "\t\t<div class='row'>\n\t\t\t$cells\n\t\t</div><!-- /row -->\n";
            } else {
                $out .= "\t\t$cells\n";
            }
        }

        if (($this->rows > 1) || ($this->cols > 1)) {
            $out = <<<EOT

	<div id='$this->id' class='divTable'>
$out{$this->caption}
	</div> <!-- /$this->id -->

EOT;
        }

        return $out;
    } // renderDiv



    private function getOption($key, $default = '')
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        } else {
            return $default;
        }
    }
} // HtmlTable

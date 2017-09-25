<?php
/*
**	Credit: http://jaukia.github.io/zoomooz/
*/

$page->addJqFiles('~sys/third-party/zoomooz/jquery.zoomooz.min.js');

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = &$this->invocationCounter[$macroName];
//	$sys = $this->config->systemPath;

    $part = $this->getArg($macroName, 'part', '', '');
    $variable = $this->getArg($macroName, 'variable', '', '');

    if ($variable) {
        $str = "\t\t<div id='item$inx' class='zoomTarget' data-debug='true'>\n$variable\t\t</div><!-- /.item$inx -->\n";

    } elseif (($part == '') || ($part == 'head')) {
		$str = "\t\t<div id='item$inx' class='zoomTarget' data-debug='true'>\n";
	} else {
		$inx--;
		$str = "\t\t</div><!-- /.item$inx -->\n";
	}
	return $str;
});

//  data-targetsize="0.45" data-duration="600"
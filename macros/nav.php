<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $type = $this->getArg($macroName, 'type', '', '');
    if ($type == 'in-page') {
        return inPageNav($this, $inx);
    }

    $args = $this->getArgsArray($macroName);

	if ($this->siteStructure) {
		$out = $this->siteStructure->render($inx, $this->page, $args);
	} else {
		$out = '';
	}
	return $out;
});



function inPageNav($that, $inx)
{
    $macroName = basename(__FILE__, '.php');
//    $depth = $that->getArg($macroName, 'depth', '', '');
//    if (!$depth) {
//        $depth = 99;
//    }
    $targetElem = $that->getArg($macroName, 'targetElem', '', 'h1');
    $jq = <<<EOT

    var str = '';
    $('$targetElem').each(function() {
        \$this = $(this);
        var hdrText = \$this.text();
        var id = hdrText.replace(/\s/, '_');
        \$this.attr('id', id);
        var hdrId =  \$this.attr('id');
        str = '<li><a href="#'+hdrId+'">'+hdrText+'</a></li>';
        $('#in-page-nav$inx').append(str);
    });
EOT;
    $that->page->addJQ($jq);

    $out = "\t<div class='in-page-nav dont-print'><ul id='in-page-nav$inx' class='in-page-nav'></ul></div>\n";
    return $out;
} // inPageNav
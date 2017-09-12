<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function ( ) {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

    $offset = $this->getArg($macroName, 'offset', '', 0);
    $addNumberOfPages = $this->getArg($macroName, 'addNumberOfPages', '', '');

    $pageNumber = $this->siteStructure->currPageRec['inx'] + 1 + $offset;
    $out = "<span class='invisible'>{{ Seite }}</span> $pageNumber";
    if ( $addNumberOfPages ) {
        $nPages = $this->siteStructure->getNumberOfPages() + $offset;
        $out .= " {{ of }} $nPages</span><span aria-hidden='true'> $pageNumber / $nPages</span>";
    } else {
        $out .= "</span><span aria-hidden='true'> $pageNumber</span>";
    }

    if ( $addNumberOfPages ) {
        $nPages = $this->siteStructure->getNumberOfPages() + $offset;
        $out = "<span class='invisible'>{{ page }} $pageNumber {{ of }} $nPages</span> <span aria-hidden='true'>$pageNumber / $nPages</span>";

    } else {
        $out = "<span class='invisible'>{{ page }}</span> $pageNumber";
    }

	return $out; //$pageNumber;
});

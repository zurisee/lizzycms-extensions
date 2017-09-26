<?php

/*
** For manipulating the embedding page, use $page:
**		$page->addHead('');
**		$page->addKeywords('');
**		$page->addDescription('');
**		$page->addCssFiles('');
**		$page->addCss('');
**		$page->addJsFiles('');
**		$page->addJs('');
**		$page->addJqFiles('');
**		$page->addJq('');
**		$page->addBody_end_injections('');
**		$page->addMessage('');
**		$page->addPageReplacement('');
**		$page->addOverride('');
**		$page->addOverlay('');
*/

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

    $arg = $this->getArg($macroName, 'argName', 'Help-text', 'default value');
    $arg = $this->getArg($macroName, '', '', '');
    $args = $this->getArgsArray($macroName);

    $str = "<!-- sample extension -->\n";
	return $str;
});

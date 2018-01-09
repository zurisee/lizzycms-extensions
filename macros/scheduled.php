<?php

/*
** For manipulating the embedding page, use $page:
**		$page->addHead('');
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

    $text = $this->getArg($macroName, 'text', 'Text to be displayed', '');
    $variable = $this->getArg($macroName, 'variable', 'Text from variable to be displayed', '');
    $showFrom = $this->getArg($macroName, 'showFrom', 'Content will be visible after this time (format: YYYY-MM-DD HH:MM)', false);
    $showTill = $this->getArg($macroName, 'showTill', 'Content will be visible until this time (format: YYYY-MM-DD HH:MM)', false);

    if ($variable) {
        $text .= $this->getVariable($variable);
    }

    $from = 0;
    if ($showFrom) {
        $from = strtotime($showFrom);
    }
    $till = PHP_INT_MAX;
    if ($showTill) {
        $till = strtotime($showTill);
    }
    $now = time();
    if (($now < $from) || ($now > $till)) {
        $text = '';
    }
	return $text;
});

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

$this->addMacro($macroName, function ($args) {
	$macroName = basename(__FILE__, '.php');

    $count = $this->getArg($macroName, 'count', 'Number of times to repeat the process');
    $text0 = $this->getArg($macroName, 'text', 'Text to be repeated', '');
    $variable = $this->getArg($macroName, 'variable', 'Variable to be repeated');
    $wrapperClass = $this->getArg($macroName, 'wrapperClass', 'Variable to be repeated', '.repeated');
    $indexPlaceholder = $this->getArg($macroName, 'indexPlaceholder', 'Pattern that will be replaced by the index number, e.g. "##"', '##');

    if ($variable) {
        $text0 .= $this->getVariable($variable);
    }

    $str = '';
    for ($i=0; $i < $count; $i++) {
        if ($indexPlaceholder) {
            $text = str_replace($indexPlaceholder, $i+1, $text0);
        } else {
            $text = $text0;
        }
        $str .= ":::::::.$wrapperClass\n$text\n:::::::\n\n";
    }
    $str = compileMarkdownStr($str, true);

    return $str;
});



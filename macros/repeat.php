<?php

// @info: Renders given content multipe times.

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
    $bare = $this->getArg($macroName, 'bare', 'Output will be rendered without any wrapper code');
    $prefixVar = $this->getArg($macroName, 'prefixVar', 'Variable-value that will be prepended to output');
    $prefixText = $this->getArg($macroName, 'prefixText', 'Text that will be prepended to output');
    $postfixVar = $this->getArg($macroName, 'postfixVar', 'Variable-value that will be appended to output');
    $postfixText = $this->getArg($macroName, 'postfixText', 'Text that will be appended to output');
    $execMacros = $this->getArg($macroName, 'execMacros', 'Runs the output through Variable/Macro translation');

    if ($variable) {
        $text0 .= $this->getVariable($variable);
    }

    if ($prefixVar) {
        $prefixText .= $this->getVariable($prefixVar);
    }
    $postfixText = str_replace('\n', "\n", $postfixText);
    if ($postfixVar) {
        $postfixText .= $this->getVariable($postfixVar);
    }

    $str = '';
    for ($i=0; $i < $count; $i++) {
        if ($indexPlaceholder) {
            $text = str_replace($indexPlaceholder, $i+1, $text0);
        } else {
            $text = $text0;
        }
        if (!$bare) {
            $str .= ":::::::.$wrapperClass\n$text\n:::::::\n\n";
        } else {
            $str .= $text."\n";
        }
    }
    if (!$bare) {
        $str .= "\n";
    }
    if ($execMacros) {
        $str = $this->translateMacros($str);
    }

    return $prefixText.$str.$postfixText;
});



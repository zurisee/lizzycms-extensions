<?php

// @info: Adds pseudo text to the page.

/*
** For manipulating the embedding page, use $page:
**		$page->addHead('')
**		$page->addCssFiles('')
**		$page->addCss('')
**		$page->addJsFiles('')
**		$page->addJs('')
**		$page->addJqFiles('')
**		$page->addJq('')
**		$page->addBody_end_injections('')
**		$page->addMessage('')
**		$page->addPageReplacement('')
**		$page->addOverride('')
**		$page->addOverlay('')
*/

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $min = $this->getArg($macroName, 'min', '(optional) defines the minimum of a randomly chosen number of words out of "lorem ipsum"', '');
    $max = $this->getArg($macroName, 'max', '(optional) defines the minimum of a randomly chosen number of words out of "lorem ipsum"', '');
    $dot = $this->getArg($macroName, 'dot', '[true,false] specifies whether generated text shall be terminated by a dot "."', true);

    $words = explode(' ', 'Lörem üpsüm dolor sit ämet, consectetur adipisici elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. ');
	$nWords = sizeof($words) - 1;
	$min = intval($min);
	
	if (!$min) {
		$str = implode(' ', $words);
		
	} else {
		if (!intval($max)) {
			$n = $min;
		} else {
			$n = rand($min, min($nWords, $max));
		}
		
		$str = "";
		for ($i=0; $i<$n; $i++) {
			$str .= $words[rand(1, $nWords - 1 )].' ';
		}
		$str = preg_replace('/\W$/', '', trim($str));
		if ($dot != 'false') {
			$str .= '.';
		}
	}
	return ucfirst( $str );
});

<?php

// @info: Renders given pages multiple times.


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

// args: $file, $count

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function ($args) {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $file = $this->getArg($macroName, 'file', 'Name of file to be repeatedly included');
    $count = $this->getArg($macroName, 'count', 'Number of times to repeat the process');

    if ($file == 'help') {
        return '';
    }

    $file = resolvePath($file, true);
    if (!fileExists($file)) {
        fatalError("Error: file not found: '$file'", 'File: '.__FILE__.' Line: '.__LINE__);
    }
    $content = file_get_contents($file);

    $str = '';
    for ($i=0; $i < $count; $i++) {

        $str .= ":::.section\n$content\n:::\n\n";
    }

    $md = new MyMarkdown();
    $md->html5 = true;

    $newPage = new Page($this->config);
    $md->parse($str, $newPage);
    $str = $newPage->get('content');

    return $str;
});



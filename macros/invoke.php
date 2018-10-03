<?php

// @info: Initiates loading of a CSS=Framework.

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
**      $page->addAutoAttrFiles(['~/config/my-auto-attrs.yaml', '~page/autoattr.yaml']);
*/

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $name = strtolower( $this->getArg($macroName, 'name', 'Name of the css-framework to be loaded and activated', '') );

    $this->config->feature_cssFramework = $name;
    return '';
});

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
require_once SYSTEM_PATH.'forms.class.php';
$page->addCssFiles('~sys/css/lizzy_forms.css');

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function ($args) {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

	if ($inx == 1) {
		$this->form = new Forms($this->page, $this);
	}
	
	$args1 = '{ "type": "form-head",  "label": "Upload Form:"}';
	$str = $this->form->render($args1);

	$args = str_replace('{', '{"type": "file", ', $args);
	$str .= $this->form->render($args);

	$args1 = '{ "type": "form-tail"}';
	$str .= $this->form->render($args1);
	return $str;
});

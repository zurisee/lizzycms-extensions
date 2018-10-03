<?php

// @info: Renders an upload button and implements the entire upload functionality.


require_once SYSTEM_PATH.'forms.class.php';
$page->addCssFiles('~sys/css/lizzy_forms.css');

setStaticVariable('pagePath', $GLOBALS['globalParams']['pagePath']);
setStaticVariable('pathToPage', $GLOBALS['globalParams']['pathToPage']);
setStaticVariable('appRootUrl', $GLOBALS['globalParams']['host'].$GLOBALS['globalParams']['appRoot']);
setStaticVariable('absAppRoot', $GLOBALS['globalParams']['absAppRoot']);

$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $args = $this->getArgsArray($macroName);

    if ($inx == 1) {
		$this->form = new Forms($this->page, $this);
	}
	
	$args1 = ["type" => "form-head",  "label" => "Upload Form:"];
    if (!isset($args['label'])) {
        $args['label'] = "{{ File to upload }}";
    }
	$str = $this->form->render($args1);

    $args['type'] = 'file';
	$str .= $this->form->render($args);

    $str .= $this->form->render(['type' => 'form-tail' ]);
	return $str;
});

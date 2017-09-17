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

$this->addMacro($macroName, 'fun');

function fun( $trans ) {
    $macroName = basename(__FILE__, '.php');
    $inx = $trans->getInvocationCounter($macroName);

    $arg = $trans->getArg($macroName, 'text', 'Help-text', 'default value');

//    $trans->page->addJqFiles('QUICKVIEW');

    $trans->addVariable('newvar', " $macroName was here!");

    return "<strong>$arg</strong>";
}




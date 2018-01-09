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
**      $page->addAutoAttrFiles(['~/config/my-auto-attrs.yaml', '~page/autoattr.yaml']);
*/

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $name = strtolower( $this->getArg($macroName, 'name', 'Name of the css-framework to be loaded and activated', '') );

    $this->config->cssFramework = $name;
    return '';
//    $page = $this->page;
//    $out = "<!-- Framework '$name' loaded -->";

//    switch ($name) {
//        case 'bootstrap':
//            $page->addCssFiles('~sys/third-party/bootstrap4/css/bootstrap.min.css');
//            $page->addJqFiles(['~sys/third-party/tether.js/tether.min.js', '~sys/third-party/bootstrap4/js/bootstrap.min.js']);
//            $page->addAutoAttrFiles('~/'.CONFIG_PATH.'/bootstrap-auto-attrs.yaml');
//            break;
//
//        case 'purecss':
//            $page->addCssFiles('~sys/third-party/pure-css/pure-min.css');
//            $page->addAutoAttrFiles('~/'.CONFIG_PATH.'/purecss-auto-attrs.yaml');
//            break;
//
//        case 'w3css':
//        case 'w3.css':
//            $page->addCssFiles('~sys/third-party/w3.css/w3.css');
//            $page->addAutoAttrFiles('~/'.CONFIG_PATH.'/w3css-auto-attrs.yaml');
//            break;
//
//        default:
//            $out = '<!-- No Framework loaded -->';
//            $this->config->cssFramework = false;
//            break;
//    }
//	return $out;
});

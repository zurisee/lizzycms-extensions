<?php

// @info: Initiates loading of a CSS=Framework.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $name = strtolower( $this->getArg($macroName, 'name', '[w3css, purecss, bootstrap] Name of the css-framework to be loaded and activated', '') );

    $this->config->feature_cssFramework = $name;

    switch ($name) {
        case 'w3css':
            $this->config->loadModules['W3CSS_CSS'] = array('module' => 'extensions/invoke/third-party/w3.css/w3.css', 'weight' => 10);
            $this->page->addCSSFiles('W3CSS_CSS');
            $this->page->addJqFiles('JQUERY');
            break;

        case 'purecss':
            $this->config->loadModules['PURECSS_CSS'] = array('module' => 'extensions/invoke/third-party/pure-css/pure-min.css', 'weight' => 10);
            $this->page->addCSSFiles('PURECSS_CSS');
            $this->page->addJqFiles('JQUERY');
            break;

        case 'bootstrap':
            $this->config->loadModules['BOOTSTRAP_CSS'] = array('module' => 'extensions/invoke/third-party/bootstrap4/css/bootstrap.min.css', 'weight' => 10);
            $this->config->loadModules['BOOTSTRAP'] = array('module' => 'extensions/invoke/third-party/bootstrap4/js/bootstrap.min.js', 'weight' => 10);
            $this->page->addCSSFiles('BOOTSTRAP_CSS');
            $this->page->addJqFiles('JQUERY,BOOTSTRAP');
            break;

        case 'help';
            break;

        default:
            $this->page->addMessage("Warning: Unknown CSS-Framework '$name' requested in macro invoke().");
    }
    return '';
});

<?php

// @info: Renders blocks of content as either accordions or tab panels.


$page->addJQFiles('PANELS');

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $mode = $this->getArg($macroName, 'mode', '[ tabs | accordion | auto ] (optional) If set, forces a particular mode.', 'auto');
    $background = $this->getArg($macroName, 'background', 'Defines the background color, e.g. #ff0', '');
    $unselectedTabsBackground = $this->getArg($macroName, 'unselectedTabsBackground', '', '');
    $outlineColor = $this->getArg($macroName, 'outlineColor', 'Defines the background color of unselected tabs', '');
    $selectedTextColor = $this->getArg($macroName, 'selectedTextColor', 'Defines text color of selected tabs', '');
    $unselectedTextColor = $this->getArg($macroName, 'unselectedTextColor', 'Defines text color of unselected tabs', '');
    $shadow = $this->getArg($macroName, 'shadow', 'Activates a shadow around the widget (optionally, provide your own CSS styling here)', '');
    $headerBeautifier = $this->getArg($macroName, 'headerBeautifier', 'Activates an improved visual effect on tabs and accordion headers (optionally, provide your own CSS styling here)', false);
    $oneOpenOnly = $this->getArg($macroName, 'oneOpenOnly', 'If true, makes sure that only one panel is open at the time (in accordion mode)', '');
    $tilted = $this->getArg($macroName, 'tilted', 'Activates tilted sides on tabs, providing an improved visual effect', false);
    $applyGlobally = $this->getArg($macroName, 'applyGlobally', 'If true, the above options are applied to all widgets within the page', false);
    $threshold = $this->getArg($macroName, 'threshold', 'defines the width threshold used for switching between tabs- and accordion-mode (default is 480px)', '');

    // Threshold for switching modes:
    if ($threshold) {
        $threshold = preg_replace('/\D/', '', $threshold);

        $this->page->addCssFiles("PANELS_CSS?threshold={$threshold}px");
        $this->page->addJs("window.widthThreshold = '$threshold';");

    } else {
        $this->page->addCssFiles('PANELS_CSS');
    }


    // Where to apply these settings - to this or to all widgets:
    if ($applyGlobally) {
        $widgetSelector = ".lzy-panels-widget";
    } else {
        $widgetSelector = ".lzy-panels-widget$inx";
    }


    // Preset modes:
    if ($mode && ($mode != 'auto')) {
        $this->page->addJq("$('$widgetSelector').addClass('$mode');");
    }
    if ($tilted) {
        $this->page->addJq("$('$widgetSelector').addClass('lzy-tilted');");
    }
    if ($oneOpenOnly) {
        $this->page->addJq("$('$widgetSelector').addClass('one-open-only');");
    }

    // prepare CSS that depends on settings:
    $css = '';

    if ($unselectedTextColor) {
        $css .= "$widgetSelector.lzy-tab-mode .lzy-tabs-mode-panel-header { color: $unselectedTextColor; }\n";
        $css .= "$widgetSelector .lzy-accordion-mode-panel-header a { color: $unselectedTextColor; }\n";
    }
    if ($selectedTextColor) {
        $css .= "$widgetSelector .lzy-tabs-mode-panel-header[aria-selected=true] { color: $selectedTextColor; }\n";
    }
    if ($unselectedTabsBackground) {
        $css .= "$widgetSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetSelector .lzy-tabs-mode-panel-header::before { background: $unselectedTabsBackground; }\n";
        $css .= "$widgetSelector .lzy-accordion-mode-panel-header { background: $unselectedTabsBackground; }\n";
    }
    if ($background) {
        $css .= "$widgetSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header[aria-selected=true]::after, $widgetSelector .lzy-tabs-mode-panel-header[aria-selected=true]::before, $widgetSelector .lzy-panel-inner-wrapper { background: $background; }\n";
    }
    if ($outlineColor) {
        $css .= "$widgetSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetSelector .lzy-tabs-mode-panel-header::before, $widgetSelector.lzy-tab-mode .lzy-panel-body-wrapper::before { border: 1px solid $outlineColor; outline-color: $outlineColor; }\n";
        $css .= "$widgetSelector .lzy-accordion-mode-panel-header { border: 1px solid $outlineColor; }\n";
    }
    if (($shadow === true) || ($shadow === 'true')) {
        $css .= "$widgetSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetSelector .lzy-tabs-mode-panel-header::before, $widgetSelector .lzy-panel-body-wrapper::before { box-shadow: 0 0 15px gray; }\n";
        $css .= "$widgetSelector .lzy-accordion-mode-panel-header::before { box-shadow: 0 0 15px gray; }\n";
    } elseif ($shadow) {
        $css .= "$widgetSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetSelector .lzy-tabs-mode-panel-header::before, $widgetSelector .lzy-panel-body-wrapper::before { $shadow; }\n";
        $css .= "$widgetSelector .lzy-accordion-mode-panel-header::before { $shadow; }\n";
    }
    if (($headerBeautifier === true) || ($headerBeautifier === 'true')) {
        $css .= "$widgetSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetSelector .lzy-tabs-mode-panel-header::before { background-image: linear-gradient( hsla( 0,0%, 100%,.6), hsla( 0,0%, 100%, 0)); }\n";
        $css .= "$widgetSelector .lzy-accordion-mode-panel-header { background-image: linear-gradient( hsla( 0,0%, 100%,.6), hsla( 0,0%, 100%, 0)); }\n";
    } elseif ($headerBeautifier) {
        $css .= "$widgetSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetSelector .lzy-tabs-mode-panel-header::before { $headerBeautifier; }\n";
        $css .= "$widgetSelector .lzy-accordion-mode-panel-header { $headerBeautifier }\n";
    }

    if ($css) {
        $this->page->addCss($css);
    }

	return '';
});

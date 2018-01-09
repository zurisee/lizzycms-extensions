<?php

$page->addCssFiles('~sys/css/tabs.css');

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

    $class = $this->getArg($macroName, 'class', 'Class to be applied to the tabs themselves, <samp>tilted</samp> invokes predefined CSS to give the tabs a more realistic appearance', '');
    $class = ($class) ? " class='$class'" : '';

    $labels = $this->getArg($macroName, 'labels', 'Defines text for the labels, format: <samp>[ label 1 | label 2 | etc.]</samp>', '');
    $labels = ltrim($labels, '[');
    $labels = rtrim($labels, ']');

    $labels = explode('|', $labels);

    $str = '';
    $str2 = '';
    $i = 1;
    foreach ($labels as $label) {
        $label = trim($label);
        $id = 'lizzy-'.translateToIdentifier($label);
        $checked =  ($str) ? '' : ' checked="checked"';
        $str .= "\t<input class='lizzy-tab-radio lizzy-tab-radio$i' id='$id' type='radio' name='lizzy-tab-radio$inx'$checked />\n";
        $str2 .= "\t\t    <li class='lizzy-tab-label$i'><label for='$id'$class>$label</label></li>\n";
        $i++;
    }

    $str = <<<EOT
$str
    <nav class="lizzy-tab-labels">
        <ul>
$str2
        </ul>
    </nav>
EOT;

	return $str;
});

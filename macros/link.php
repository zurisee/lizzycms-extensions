<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $href = $this->getArg($macroName, 'href', '', '');
    $text = $this->getArg($macroName, 'text', '', '');
    $type = $this->getArg($macroName, 'type', '', '');
    $class = $this->getArg($macroName, 'class', '', '');
    $baseUrl = $this->getArg($macroName, 'baseUrl', '', '');

    $target = '';
	$title = '';
	$hiddenText = '';
	if (stripos($href, 'mailto:') !== false) {
		$class = ($class) ?  "$class mail_link" : 'mail_link';
		$title = " title='`opens mail app`'";
		if (!$text) {
			$text = substr($href, 7);
		} else {
			$hiddenText = "<span class='print_only'> [$href]</span>";
		}
	}
	if (!$text) {
		$text = $href;
	} else {
		$_href = $href;
		if ($baseUrl) {
			if (substr($href, 0, 2) == '..') {
				$_href = fixPath($baseUrl) . str_replace('../', '', $href);
			} elseif (substr($href, 0, 2) == '~/') {
				$_href = fixPath($baseUrl) . str_replace('~/', '', $href);
			}
		}
		$hiddenText = "<span class='print_only'> [$_href]</span>";
	}
	if ((stripos($type, 'extern') !== false) || (stripos($href, 'http') === 0)) {
		$target = " target='_blank'";
		$class = ($class) ?  "$class external_link" : 'external_link';
		$title = " title='`opens in new win`'";
	}
	$class = " class='$class'";
	$str = "<a href='$href' $class$title$target>$text$hiddenText</a>";
	return $str;
});

<?php
// @info: Creates a popup widget.



$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $inx = $this->invocationCounter[$macroName] + 1;
    $args = $this->getArgsArray($macroName);

    return $this->page->addPopup($inx, $args);
});


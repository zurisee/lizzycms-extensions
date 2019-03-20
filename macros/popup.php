<?php
// @info: Creates a popup widget.



$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');

    $args = $this->getArgsArray($macroName);

    return $this->page->addPopup($args);
});


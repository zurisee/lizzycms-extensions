<?php
// @info: Creates a lightbox widget (a kind of popup window).

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');

    $this->invocationCounter['popup'] = (!isset($this->invocationCounter['popup'])) ? 0 : ($this->invocationCounter['popup']+1);

    $inx = $this->invocationCounter['popup'] + 1;
    $args = $this->getArgsArray($macroName);
    $args['lightbox'] = true;

    return $this->page->addPopup($inx, $args);

});


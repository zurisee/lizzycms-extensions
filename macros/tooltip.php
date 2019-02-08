<?php
// @info: Creates a popup widget.

// TODO: mobile => close on bg-click

$page->addModules('TOOLTIPS');

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $text = $this->getArg($macroName, 'text', 'Text over which the tooltip will appear ("tooltip anchor").', '');
    $contentFrom = $this->getArg($macroName, 'contentFrom', 'CSS-selector of the text that will be displayed in the tooptip. E.g. "#tt-content"', '');
    $id = $this->getArg($macroName, 'id', 'ID to be applied to the tooltip anchor.', '');
    $class = $this->getArg($macroName, 'class', 'Class(es) to be applied to the tooltip anchor.', '');
    $icon = $this->getArg($macroName, 'icon', '(path-to-icon-file) Alternative to "text" argument.', 'false');
    $iconClass = $this->getArg($macroName, 'iconClass', 'Class(es) to be applied to the tooltip icon.', '');
    $position = $this->getArg($macroName, 'position', '[top,right,bottom,left,vertical,horizontal] Indicates where the tooltip shall be displayed.'.
        ' "vertical" means above or below depending where there is sufficient space. "horizontal" likewise.', '');
    $arrow = $this->getArg($macroName, 'arrow', 'If true, a small triangle will be displayed pointing to the anchor.', '');
    $arrowSize = $this->getArg($macroName, 'arrowSize', 'Size of the arrow.', '');
    $catchFocus = $this->getArg($macroName, 'catchFocus', '', '');

    $ch1 = isset($contentFrom[0]) ? $contentFrom[0] : '';
    if (($ch1 != '#') && ($ch1 != '.')) {
        $contentFrom = '#'.$contentFrom;
    }
    $attr = '';
    if (strlen($position) > 0) {
        $attr = " data-lzy-tooltip-where='".strtolower(substr($position, 0, 1))."'";
    }

    if ($arrow) {
        $class .= " lzy-tooltip-arrow";
    }
    if ($arrowSize) {
        $this->page->addCss("body $contentFrom { --lzy-tooltip-arrow-size: {$arrowSize}px;}");
        $attr .= " data-lzy-tooltip-arrow-size='$arrowSize'";
    }

    if ($catchFocus) {
        $class .= " lzy-tooltip-catch-focus";
    }

    $class = " class='".trim("lzy-tooltip-anchor $class")."'";

    if ($id) {
        $id = " id='$id'";
    } else {
        $id = " id='lzy-tooltip-anchor$inx'";
    }

    if ($icon != 'false') {
        if (!$icon || ($icon == 'default')) {
            $icon = '~sys/rsc/info.svg';
        }
        $text .= "<img src='$icon' class='lzy-tooltip-icon $iconClass' alt='' />";
    }
    $this->optionAddNoComment = true;
    return "<span$id data-lzy-tooltip-from='$contentFrom'$class$attr>$text</span>";
});


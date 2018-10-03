<?php

// @info: Renders a navigation menu.
require_once SYSTEM_PATH.'nav-renderer.class.php';

$this->page->addJqFiles('NAV');
$this->page->addCssFiles('NAV_CSS');


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $type = $this->getArg($macroName, 'type', '[top, side, sitemap, in-page] Specifies the type of output to be rendered.', '');
    $class = $this->getArg($macroName, 'class', 'Class to be applied to the surrounding NAV tag.');
    $this->getArg($macroName, 'layout', '[horizontal, vertical] Specifies direction of top-level items.', '');
    $this->getArg($macroName, 'animation', '[dropdown, slidedown, collapsable] Defines the type of animation applied to the rendered tree.', '');
    $this->getArg($macroName, 'options', '[top-level, curr-branch] These are filters that render a subset of items.', '');

    $this->getArg($macroName, 'navClass', 'Class to be applied to the surrounding NAV tag.');
    $this->getArg($macroName, 'ulClass', 'Class to be applied to UL tags.');
    $this->getArg($macroName, 'liClass', 'Class to be applied to LI tags.');
    $this->getArg($macroName, 'hasChildrenClass', 'Class to be applied to elements that have children.');
    $this->getArg($macroName, 'aClass', 'Class to be applied to A tags.');
    $this->getArg($macroName, 'title', 'A title to be inserted just after the NAV tag. If it doesn\'t contain HTML, the title will be wrapped in H1 tags.');

    $this->getArg($macroName, 'depth', 'The max depth of headers which shall be included', 6);
    $this->getArg($macroName, 'targetElement', 'For "in-page": specifies the html tag that shall be targeted', false);
    $this->getArg($macroName, 'listTag', '[ol, ul, div] Specifies type of list. Default is OL.', 'ol');
    $this->getArg($macroName, 'listWrapper', 'Specifies whether sub-lists get a wrapper.', 'div');
    $this->getArg($macroName, 'smallScreenHeaderText', 'Text in small-screen-header, typically the app name', '');

    if ($type == 'help') {
        return '';
    }

    $options = $this->getArgsArray($macroName);

    // -------- in-page
    if ($type == 'in-page') {
        return inPageNav($this, $inx);

    // -------- small-screen-header
    } elseif ($type == 'small-screen-header') {
        return renderSmallScreenHeader($this, $options);
    }

    // make 'class' synonym for 'navClass'
    if ($class) {
        $options['navClass'] = $class;
    }

    // case of one-pager -> has no site structure, so skip:
    if (!isset($this->siteStructure) || !$this->siteStructure) {
        return '';
    }

    $nav = new NavRenderer($this->lzy);
    return $nav->render($options);
});





function renderSmallScreenHeader($trans, $options)
{
    $smallScreenHeaderText = $options['smallScreenHeaderText'];
    if (!$smallScreenHeaderText) {
        $smallScreenHeaderText = $trans->getVariable('site_title');
    }
    if ($smallScreenHeaderText && (strpos($smallScreenHeaderText, '<') === false)) {
        $smallScreenHeaderText = "<h1>$smallScreenHeaderText</h1>";
    }

    $out =  "<div class='lzy-mobile-page-header' style='display: none;'>".
                "$smallScreenHeaderText".
                "<button id='lzy-nav-menu-icon' class='lzy-nav-menu-icon' tabindex='1'><div>&#9776;</div></button>".
            "</div>";

    return $out;
}



function inPageNav($that, $inx)
{
    $macroName = basename(__FILE__, '.php');
    $depth = $that->getArg($macroName, 'depth', '', 1);
    $targetElem = $that->getArg($macroName, 'targetElement', '', false);
    $listTag = $that->getArg($macroName, 'listTag', '', 'false');
    $title = $that->getArg($macroName, 'title', '', '');
    if ($title && (strpos($title, '<') === false)) {
        $title = "<h1>$title</h1>";
    }
    if ($targetElem) {
        inPageByTargetElement($that, $inx, $targetElem);
    } else {
        inPageHierarchie($that, $inx, $depth);
    }

    $out = "\t<nav class='lzy-in-page-nav dont-print'>$title<$listTag id='lzy-in-page-nav$inx' class='lzy-in-page-nav'></$listTag></nav>\n";
    return $out;
} // inPageNav



function inPageHierarchie($that, $inx, $depth)
{
    $depth = intval($depth);
    $hMax = "H$depth";
    $jq = <<<EOT

    $(':header').each(function() {
        var \$this = $(this);
        var hdrText = \$this.text();
        var id = hdrText.replace(/\s/, '_');
        \$this.attr('id', id);
        var hdrId =  \$this.attr('id');
        var nodeName = this.nodeName;
        
        if (nodeName <= '$hMax') {
            str = '<li class="'+nodeName+'"><a href="#'+hdrId+'">'+hdrText+'</a></li>';
            $('#lzy-in-page-nav$inx').append(str);
        }
    });
EOT;
    $that->page->addJQ($jq);

}


function inPageByTargetElement($that, $inx, $targetElem)
{
    $jq = <<<EOT

    $('$targetElem').each(function() {
        var \$this = $(this);
        var hdrText = \$this.text();
        var id = hdrText.replace(/\s/, '_');
        \$this.attr('id', id);
        var hdrId =  \$this.attr('id');
        str = '<li><a href="#'+hdrId+'">'+hdrText+'</a></li>';
        $('#lzy-in-page-nav$inx').append(str);
    });
EOT;
    $that->page->addJQ($jq);

}

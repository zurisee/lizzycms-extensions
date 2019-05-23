<?php

// @info: Renders a navigation menu.
require_once SYSTEM_PATH.'nav-renderer.class.php';

$this->page->addModules('NAV');


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $type = $this->getArg($macroName, 'type', '[top, side, sitemap, in-page] Specifies the type of output to be rendered.', '');
    $class = $this->getArg($macroName, 'class', 'Class to be applied to the surrounding NAV tag. (alias for \'navClass\')');
    $this->getArg($macroName, 'layout', '[horizontal, vertical] Specifies direction of top-level items.', '');
    $this->getArg($macroName, 'animation', '[dropdown, slidedown, collapsable] Defines the type of animation applied to the rendered tree.', '');
    $this->getArg($macroName, 'options', '[top-level, curr-branch] These are filters that render a subset of items.', '');
    $this->getArg($macroName, 'scheme', '[dark] Selects coloring defaults.', '');

    $this->getArg($macroName, 'navClass', 'Class to be applied to the surrounding NAV tag.');
    $this->getArg($macroName, 'ulClass', 'Class to be applied to UL tags.');
    $this->getArg($macroName, 'liClass', 'Class to be applied to LI tags.');
    $this->getArg($macroName, 'hasChildrenClass', 'Class to be applied to elements that have children.');
    $this->getArg($macroName, 'aClass', 'Class to be applied to A tags.');
    $this->getArg($macroName, 'title', 'A title to be inserted just after the NAV tag. If it doesn\'t contain HTML, the title will be wrapped in H1 tags.');
    $this->getArg($macroName, 'ariaLabel', 'A label that describes the role, e.g. "Main Menu" (&rarr; needed by assistive technologies)');
    $this->getArg($macroName, 'arrow', 'The symbol to be placed in front of nav elements that have children.');

    $this->getArg($macroName, 'primary', '[true|false] If true, applies class "lzy-primary-nav". By default, the first instance of nav() gets the "lzy-primary-nav" class. This options permits to override the mechanism.', null);

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
    if (($type == 'in-page') || ($type == 'inpage')) {
        return inPageNav($this->page, $options, $inx);

    // -------- small-screen-header
    } elseif ($type == 'small-screen-header') {
        return renderSmallScreenHeader($this, $options);
    }

    // make 'class' synonym for 'navClass'
    if ($class) {
        $options['navClass'] .= ' '.$class;
    }

    // case of one-pager -> has no site structure, so skip:
    if (!isset($this->siteStructure) || !$this->siteStructure) {
        return '';
    }

    $nav = new NavRenderer($this->lzy, $inx);
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

    $out =  "<div class='lzy-mobile-page-header'>".
                "$smallScreenHeaderText".
                "<button id='lzy-nav-menu-icon' class='lzy-nav-menu-icon' tabindex='1'><span>&#9776;</span></button>".
            "</div>";

    return $out;
}




function inPageNav($page, $options, $inx)
{

    $depth = $options['depth'];
    $targetElem = $options['targetElement'];
    $listTag = $options['listTag'];
    $title = $options['title'];

    if ($title && (strpos($title, '<') === false)) {
        $title = "<h1>$title</h1>";
    }

    $listElemTag = ($listTag == 'div') ? 'div' : 'li';
    if ($targetElem) {
        inPageByTargetElement($page, $inx, $targetElem, $listElemTag);
    } else {
        inPageHierarchie($page, $inx, $depth, $listElemTag);
    }

    $out = "\t<nav class='lzy-in-page-nav dont-print'>$title<$listTag id='lzy-in-page-nav$inx' class='lzy-in-page-nav'></$listTag></nav>\n";
    return $out;
} // inPageNav




function inPageHierarchie($page, $inx, $depth, $listElemTag)
{
    $depth = intval($depth);
    $hMax = "H$depth";
    $jq = <<<EOT

    $(':header').each(function() {
        var \$this = $(this);
        if (\$this.closest('.lzy-mobile-page-header').length) {
            return;
        }
        var hdrText = \$this.text();
        var id = hdrText.replace(/\s/, '_');
        \$this.attr('id', id);
        var hdrId =  \$this.attr('id');
        var nodeName = this.nodeName;

        if (nodeName <= '$hMax') {
            str = '<$listElemTag class="'+nodeName+'"><a href="#'+hdrId+'">'+hdrText+'</a></$listElemTag>';
            $('#lzy-in-page-nav$inx').append(str);
        }
    });
EOT;
    $page->addJQ($jq);

}



function inPageByTargetElement($page, $inx, $targetElem, $listElemTag)
{
    $jq = <<<EOT

    $('$targetElem').each(function() {
        var \$this = $(this);
        if (\$this.closest('.lzy-mobile-page-header').length) {
            return;
        }
        var hdrText = \$this.text();
        var id = hdrText.replace(/\s/, '_');
        \$this.attr('id', id);
        var hdrId =  \$this.attr('id');
        str = '<$listElemTag><a href="#'+hdrId+'">'+hdrText+'</a></$listElemTag>';
        $('#lzy-in-page-nav$inx').append(str);
    });
EOT;
    $page->addJQ($jq);

}

<?php

// @info: Is a generic macro to add images to the page.


require_once SYSTEM_PATH.'image-tag.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName] + 1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $src = $this->getArg($macroName, 'src', 'Name of image-file');
    $alt = $this->getArg($macroName, 'alt', 'Alt-text for image, i.e. a short text that describes the image');
    $class = $this->getArg($macroName, 'class', 'Class-name that will be applied to the image');
    $id = $this->getArg($macroName, 'id', 'ID-name that will be applied to the image');
    $caption = $this->getArg($macroName, 'caption', 'Optional caption');
    $size = $this->getArg($macroName, 'sizes', 'Specifies in which size the image shall be rendered (e.g. "300x200")');
    $srcset = $this->getArg($macroName, 'srcset', "Let's you override the automatic srcset mechanism.");
    $quickview = $this->getArg($macroName, 'quickview', "If set, activates the quickview mechanism.");
    $lateImgLoading = $this->getArg($macroName, 'lateImgLoading', "If set, activates the lazy-load mechanism: images get loaded after the page is ready otherwise.");

    $link = $this->getArg($macroName, 'link', "Wrap an <a> tag round the image");
    $linkClass = $this->getArg($macroName, 'linkClass', "Class applied to <a> tag");
    $linkTarget = $this->getArg($macroName, 'linkTarget', "Target-attribute applied to <a> tag");
    $linkTitle = $this->getArg($macroName, 'linkTitle', "Title-attribute applied to <a> tag");
    $linkType = $this->getArg($macroName, 'linkType', "[extern] used to present the link as an external link");

    if (!$id) {
        $id = "img$inx";
    }

    $lateImgLoading = ($lateImgLoading || $this->config->feature_lateImgLoading || (strpos($class, 'lzy-late-loading') !== false));

    // invoke quickview resources if required:
    if ($this->config->feature_quickview || $quickview || (strpos($class, 'lzy-quickview') !== false)) {
        $class .= ' lzy-quickview';
        if (!isset($this->page->quickviewLoaded)) {
            $this->page->addCssFiles('QUICKVIEW_CSS');
            $this->page->addJqFiles('QUICKVIEW');
            $this->page->addJq("\t$('.lzy-quickview').quickview();");
            $this->page->quickviewLoaded = true;
        }
    } elseif ($lateImgLoading) {    // lateImgLading requires quickview.js
        $this->page->addJqFiles('QUICKVIEW');
    }

    $impTag = new ImageTag($this, $src, $alt, $class, $size, $srcset, $lateImgLoading);
    $str = $impTag->render($id);


    // link around img
    if ($link) {
        require_once SYSTEM_PATH . 'create-link.class.php';
        $cl = new CreateLink();

        $str = str_replace('lzy-quickview', '', $str); // link overrides quickview

        $args = ['text' => $str, 'href' => $link, 'class' => $linkClass, 'type' => $linkType,
            'target' => $linkTarget, 'title' => $linkTitle, 'target' => '', 'subject' => '', 'body' => ''];
        $str = $cl->render($args);
    }

    // figure with caption:
    if ($caption) {
        if (!isset($this->figureCounter)) {
            $this->figureCounter = 1;
        } else {
            $this->figureCounter++;
        }

        if (preg_match('/(.*)\#\#=(\d+)(.*)/', $caption, $m)) {
            $this->figureCounter = intval($m[2]);
            $caption = $m[1].'##'.$m[3];
        }
        // make ref to figure available:
        $this->addVariable("fig_$id",$this->figureCounter);

        $caption = str_replace('##', $this->figureCounter, $caption);
        $caption = "\t<figcaption class='caption'>$caption</figcaption>\n";
        $str = "<figure id='figure_$id' class='$class'>\n\t$str\n$caption</figure>\n";
    }
    return $str;
});

<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $src = $this->getArg($macroName, 'src', 'Name of image-file');
    $alt = $this->getArg($macroName, 'alt', 'Alt-text for image, i.e. a short text that describes the image');
    $class = $this->getArg($macroName, 'class', 'Class-name that will be applied to the image');
    $caption = $this->getArg($macroName, 'caption', 'Optional caption');
    $altSizes = $this->getArg($macroName, 'altSizes', '');


    $src = makePathRelativeToPage($src);

    $imgFullsizeFile = false;
    $auxAttr = '';
    $attr = '';
    $id = "pic$inx";
    $modifier = '';

    // prepare quickview:
    if (($this->config->quickview && ($class === null)) || strpos($class, 'quickview') !== false) {
        if (preg_match('/([^\[]*)\[.*(\.\w+)/', $src, $m)) {
            $basename = $m[1];
            $ext = $m[2];
            $imgFullsizeFile = resolvePath($basename.$ext, true);
            if (file_exists($imgFullsizeFile)) {
                if (!isset($this->page->quickviewLoaded)) {
                    $this->page->addCssFiles('QUICKVIEW_CSS');
                    $this->page->addJqFiles('QUICKVIEW');
                    $this->page->addJq("\t$('.quickview').quickview();");
                    $this->page->quickviewLoaded = true;
                }
                list($w, $h) = getimagesize($imgFullsizeFile);
                $auxAttr = " data-qv-src='~/$imgFullsizeFile' data-qv-width='$w' data-qv-height='$h'";
                if ($this->config->quickview && (strpos($class, 'quickview') === false)) {
                    $class .= ' quickview';
                }
            } else {
                $imgFullsizeFile = false;
            }
        }
    }

    $srcFile = resolvePath($src, true);
    if (file_exists($srcFile)) {
        $fileSize = filesize($srcFile);
    } else {
        $fileSize = 0;
    }

    // prepare srcset in case of multiple image sources:
    $altSrc = '';
    if ($altSizes && !$imgFullsizeFile) {
        if (preg_match('/([^\[]*)(\.\w+)/', $src, $m)) {
            $basename = $m[1];
            $ext = $m[2];
        }
        $altSizes = explode(',', $altSizes);
        foreach ($altSizes as $i => $maxSize) {
            if ($i == 0) {
                $limit = intval($maxSize / 2);
                $smallestLimit = $limit;
            } else {
                $limit = $altSizes[$i - 1];
            }
            $altSrc = "\t\t<source media='(min-width: {$limit}px)' srcset='{$basename}[{$maxSize}x]$ext' />\n$altSrc";
        }
        $altSrc = "\t\t<source media='(min-width: {$maxSize}px)' srcset='{$basename}$ext' />\n$altSrc";
        $src = "{$basename}[{$smallestLimit}x]$ext";

    } elseif ((strpos($src, '[') === false) && ($fileSize > 100000)) { // no [x] and file<100k
        // get img dimensions: (unless picture option active)
        if (file_exists($srcFile)) {
            $picAttr = getimagesize($srcFile);
            $w = $picAttr[0];

            if (preg_match('/([^\[]*)(\.\w+)/', $src, $m)) {
                $basename = $m[1];
                $ext = $m[2];
            } else {
                die('bad');
                $basename = base_name($src, false);
                $ext = fileExt($src);
            }
            foreach ($altSizes = [500, 1000, 1500, 2000, 2500] as $i => $maxSize) {
                if ($i == 0) {
                    $limit = intval($maxSize / 2);
                    $smallestLimit = $limit;
                } else {
                    $limit = $altSizes[$i - 1];
                    if ($maxSize > $w) {
                        $altSrc = "\t\t<source media='(min-width: {$limit}px)' srcset='$basename$ext' />\n$altSrc";
                        break;
                    }
                }
                $altSrc = "\t\t<source media='(min-width: {$limit}px)' srcset='{$basename}[{$maxSize}x]$ext' />\n$altSrc";
                $limit = $w;
            }
            $src = "{$basename}[{$smallestLimit}x]$ext";
        }
    }

    if ($class) {
        $class = " $class";
    }

    // basic img code:
    $str = "<img  id='$id' class='pic$inx$class' src='$src' title='$alt' alt='$alt'$auxAttr />";


    // picture with multiple source files:
    if ($altSrc) {
        $str = "<picture class='picture$inx'>\n$altSrc\t\t$str\n</picture>\n";
    }

    // figure with caption:
    if ($caption) {
        if (!isset($this->figureCounter)) {
            $this->figureCounter = 1;
        } else {
            $this->figureCounter++;
        }
        $caption = str_replace('##', $this->figureCounter, $caption);
        $caption = "\t<figcaption class='caption'>$caption</figcaption>\n";
        $str = "<figure id='figure_$id'$class$modifier>\n\t$str\n$caption</figure>\n";
    }
	return $str;
});

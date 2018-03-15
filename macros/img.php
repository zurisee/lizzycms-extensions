<?php

// @info: Is a generic macro to add images to the page.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $src = $this->getArg($macroName, 'src', 'Name of image-file');
    $alt = $this->getArg($macroName, 'alt', 'Alt-text for image, i.e. a short text that describes the image');
    $class = $this->getArg($macroName, 'class', 'Class-name that will be applied to the image');
    $caption = $this->getArg($macroName, 'caption', 'Optional caption');
    $srcset = $this->getArg($macroName, 'srcset', "Let's you override the automatic srcset mechanism.");


    $src = makePathRelativeToPage($src);

    $imgFullsizeFile = false;
    $auxAttr = '';
    $attr = '';
    $id = "img$inx";
    $modifier = '';
    $w = $h = $w0 = $h0 = 1;
    $fileFound = false;

    $srcFile = resolvePath($src, true);
    if (file_exists($srcFile)) {
        list($w, $h, $type, $attr) = getimagesize($srcFile);
        $fileFound = true;
    }

    if (preg_match('/([^\[]*)\[(.*)\](\.\w+)/', $src, $m)) {    // [WxH] size specifier present?
        $basename = $m[1];
        $ext = $m[3];
        list($w, $h) = explode('x', $m[2]);
        $imgFullsizeFile = resolvePath($basename . $ext, true);
        if (file_exists($imgFullsizeFile)) {
            list($w0, $h0) = getimagesize($imgFullsizeFile);
            $aspRatio = $h0 / $w0;
        } else {
            return "<div class='missing-img $class'>Image missing: '$srcFile'</div>";
        }

        if (!$w) {
            $w = round($h / $aspRatio);
        } elseif (!$h) {
            $h = round($w * $aspRatio);
        }
        $src = $basename."[{$w}x$h]".$ext;
    } elseif (!$fileFound) {
        return "<div class='missing-img $class'>Image missing: '$srcFile'</div>";
    }
    // prepare quickview:
    if (($this->config->feature_quickview && !preg_match('/\bnoquickview\b/', $class)) ||   // config setting, but no 'noquickview' override
            preg_match('/\bquickview\b/', $class)) {                                // or 'quickview' class
        if ($imgFullsizeFile) {
            if (file_exists($imgFullsizeFile)) {
                if (!isset($this->page->quickviewLoaded)) {
                    $this->page->addCssFiles('QUICKVIEW_CSS');
                    $this->page->addJqFiles('QUICKVIEW');
                    $this->page->addJq("\t$('.quickview').quickview();");
                    $this->page->quickviewLoaded = true;
                }
                $auxAttr = " data-qv-src='~/$imgFullsizeFile' data-qv-width='$w0' data-qv-height='$h0'";
                if ($this->config->feature_quickview && (strpos($class, 'quickview') === false)) {
                    $class .= ' quickview';
                }
            } else {
                $imgFullsizeFile = false;
            }
        }
    }

    if (file_exists($imgFullsizeFile)) {
        $fileSize = filesize($imgFullsizeFile);
    } else {
        $fileSize = 0;
    }

    // prepare srcset:
    if (($srcset === '') && ($fileSize > 100000)) {
        $i = 2;
        $w1 = round($w * 2);
        $h1 = round($h * 2);
        while (($w1 < $w0) && ($i <= 4)) {
            $f =  $basename."[{$w1}x{$h1}]".$ext;
            $srcset .= "$f {$i}x, ";
            $i++;
            $w1 = round($w * $i);
            $h1 = round($h * $i);
        }
        $srcset = ' srcset="'.substr($srcset, 0, -2).'"';

    } elseif ($srcset && ($srcset != 'false')) {
        $srcset = " srcset='$srcset'";

    } else {
        $srcset = '';
    }

    if ($class) {
        $class = " $class";
    }

    // basic img code:
    $str = "<img  id='$id' class='img$inx$class' src='$src'$srcset title='$alt' alt='$alt' $attr$auxAttr />";


    // figure with caption:
    if ($caption) {
        if (!isset($this->figureCounter)) {
            $this->figureCounter = 1;
        } else {
            $this->figureCounter++;
        }
        $caption = str_replace('##', $this->figureCounter, $caption);
        $caption = "\t<figcaption class='caption'>$caption</figcaption>\n";
        $str = "<figure id='figure_$id' class='$class'$modifier>\n\t$str\n$caption</figure>\n";
    }
	return $str;
});
